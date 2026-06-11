<?php
/**
 * ERP Client Implementation.
 *
 * Handles all HTTP communication with the ERP API including
 * authentication, token refresh, retries, and error handling.
 *
 * @package AgenciaERP
 */

namespace AgenciaERP;

defined( 'ABSPATH' ) || exit;

/**
 * Class ERPClient
 *
 * Concrete implementation of ERPClientInterface.
 * Manages authentication tokens, handles rate limiting,
 * timeouts, and implements exponential backoff.
 */
class ERPClient implements ERPClientInterface {

    /**
     * Option key for encrypted API key.
     */
    private const OPTION_API_KEY = 'erp_api_key_encrypted';

    /**
     * Option key for encrypted API secret.
     */
    private const OPTION_API_SECRET = 'erp_api_secret_encrypted';

    /**
     * Option key for cached auth token.
     */
    private const OPTION_AUTH_TOKEN = 'erp_auth_token';

    /**
     * Option key for token expiration timestamp.
     */
    private const OPTION_TOKEN_EXPIRES = 'erp_token_expires_at';

    /**
     * Default request timeout in seconds.
     */
    private const DEFAULT_TIMEOUT = 30;

    /**
     * Backoff intervals in seconds for retries.
     *
     * @var int[]
     */
    private array $backoff_intervals;

    /**
     * Base URL for the ERP API.
     *
     * @var string
     */
    private string $api_base_url;

    /**
     * Current authentication token.
     *
     * @var string|null
     */
    private ?string $token = null;

    /**
     * Token expiration timestamp.
     *
     * @var int
     */
    private int $token_expires_at = 0;

    /**
     * Constructor.
     *
     * Loads configuration from WordPress options.
     */
    public function __construct() {
        $this->api_base_url     = get_option( 'erp_api_base_url', '' );
        $this->backoff_intervals = get_option( 'erp_retry_backoff_seconds', [ 30, 120, 600 ] );

        // Load cached token if available.
        $cached_token   = get_option( self::OPTION_AUTH_TOKEN, '' );
        $cached_expires = (int) get_option( self::OPTION_TOKEN_EXPIRES, 0 );

        if ( $cached_token && $cached_expires > time() ) {
            $this->token            = $cached_token;
            $this->token_expires_at = $cached_expires;
        }
    }

    /**
     * Authenticate with the ERP API.
     *
     * Sends credentials to the auth endpoint and stores the token.
     * Automatically refreshes on 401 responses during requests.
     *
     * @return array{token: string, expires_at: int} Token data.
     * @throws \RuntimeException On authentication failure.
     */
    public function authenticate(): array {
        $api_key    = trim( self::decrypt_credential( (string) get_option( self::OPTION_API_KEY, '' ) ) );
        $api_secret = trim( self::decrypt_credential( (string) get_option( self::OPTION_API_SECRET, '' ) ) );

        if ( empty( $api_key ) || empty( $api_secret ) ) {
            throw new \RuntimeException(
                __( 'ERP API credentials not configured.', 'wc-erp-integration' )
            );
        }

        $response = wp_remote_post(
            $this->build_url( '/auth/token' ),
            [
                'timeout' => self::DEFAULT_TIMEOUT,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'body'    => wp_json_encode( [
                    'api_key'    => $api_key,
                    'api_secret' => $api_secret,
                ] ),
            ]
        );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException(
                sprintf(
                    /* translators: %s: error message */
                    __( 'ERP authentication failed: %s', 'wc-erp-integration' ),
                    $response->get_error_message()
                )
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $status_code ) {
            $detail = is_array( $body ) ? (string) ( $body['detail'] ?? $body['message'] ?? '' ) : '';
            throw new \RuntimeException(
                trim(
                    sprintf(
                        /* translators: 1: HTTP status code, 2: API detail */
                        __( 'ERP authentication returned status %1$d.%2$s', 'wc-erp-integration' ),
                        $status_code,
                        $detail ? ' ' . $detail : ''
                    )
                )
            );
        }

        if ( empty( $body['token'] ) || empty( $body['expires_at'] ) ) {
            throw new \RuntimeException(
                __( 'ERP authentication response missing token data.', 'wc-erp-integration' )
            );
        }

        $this->token            = $body['token'];
        $this->token_expires_at = (int) $body['expires_at'];

        // Cache the token in WordPress options.
        update_option( self::OPTION_AUTH_TOKEN, $this->token );
        update_option( self::OPTION_TOKEN_EXPIRES, $this->token_expires_at );

        return [
            'token'      => $this->token,
            'expires_at' => $this->token_expires_at,
        ];
    }

    /**
     * Check ERP API health status.
     *
     * @return array{status: string, latency_ms: int} Health status.
     * @throws \RuntimeException On connection failure.
     */
    public function health_check(): array {
        $start_time = microtime( true );

        $response = wp_remote_get(
            $this->build_url( '/health' ),
            [
                'timeout' => 10,
                'headers' => [ 'Accept' => 'application/json' ],
            ]
        );

        $latency_ms = (int) round( ( microtime( true ) - $start_time ) * 1000 );

        if ( is_wp_error( $response ) ) {
            return [
                'status'     => 'unreachable',
                'latency_ms' => $latency_ms,
                'error'      => $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = json_decode( wp_remote_retrieve_body( $response ), true );

        return [
            'status'     => 200 === $status_code ? 'healthy' : 'degraded',
            'latency_ms' => $latency_ms,
            'details'    => $body ?? [],
        ];
    }

    /**
     * Retrieve products from the ERP.
     *
     * @param array       $filters Optional filters.
     * @param string|null $since   ISO 8601 date for incremental sync.
     * @return array List of product data.
     */
    public function get_products( array $filters = [], ?string $since = null ): array {
        $params = $filters;
        if ( $since ) {
            $params['updated_since'] = $since;
        }
        if ( empty( $params['per_page'] ) ) {
            $params['per_page'] = 100;
        }

        $all_products = [];
        $page         = 1;
        $total_pages  = 1;

        do {
            $params['page'] = $page;
            $response       = $this->request( 'GET', '/products', [ 'query' => $params ] );
            $batch          = $response['data'] ?? [];
            if ( is_array( $batch ) ) {
                $all_products = array_merge( $all_products, $batch );
            }
            $total_pages = max( 1, (int) ( $response['pagination']['total_pages'] ?? 1 ) );
            $page++;
        } while ( $page <= $total_pages );

        return $all_products;
    }

    /**
     * Retrieve a single product by SKU.
     *
     * @param string $sku Product SKU.
     * @return array|null Product data or null.
     */
    public function get_product_by_sku( string $sku ): ?array {
        $sku = trim( $sku );
        if ( '' === $sku ) {
            return null;
        }
        try {
            $response = $this->request( 'GET', '/products/' . rawurlencode( $sku ) );
            if ( ! is_array( $response ) || empty( $response ) ) {
                return null;
            }
            if ( isset( $response['sku'] ) ) {
                return $response;
            }
            return $response['data'] ?? null;
        } catch ( \RuntimeException $e ) {
            if ( str_contains( $e->getMessage(), '404' ) ) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Retrieve stock levels.
     *
     * @param array $skus List of SKUs.
     * @return array SKU => stock level mapping.
     */
    public function get_stock_levels( array $skus = [] ): array {
        $params = [];
        if ( ! empty( $skus ) ) {
            $params['skus'] = implode( ',', $skus );
        }

        $response = $this->request( 'GET', '/inventory/stock', [ 'query' => $params ] );
        return $response['data'] ?? [];
    }

    /**
     * Retrieve prices.
     *
     * @param array $skus List of SKUs.
     * @return array SKU => price data mapping.
     */
    public function get_prices( array $skus = [] ): array {
        $params = [];
        if ( ! empty( $skus ) ) {
            $params['skus'] = implode( ',', $skus );
        }

        $response = $this->request( 'GET', '/products/prices', [ 'query' => $params ] );
        return $response['data'] ?? [];
    }

    /**
     * Create an order in the ERP.
     *
     * @param array $order_data Order payload.
     * @return array ERP order response.
     */
    public function create_order( array $order_data ): array {
        return $this->request( 'POST', '/orders', [ 'body' => $order_data ] );
    }

    /**
     * Update order status in the ERP.
     *
     * @param string $erp_order_id ERP order ID.
     * @param string $status       New status.
     * @return bool True on success.
     */
    public function update_order_status( string $erp_order_id, string $status ): bool {
        $this->request( 'PUT', '/orders/' . urlencode( $erp_order_id ) . '/status', [
            'body' => [ 'status' => $status ],
        ] );
        return true;
    }

    /**
     * Confirm payment for an order.
     *
     * @param string $erp_order_id ERP order ID.
     * @param array  $payment_data Payment data.
     * @return bool True on success.
     */
    public function confirm_payment( string $erp_order_id, array $payment_data ): bool {
        $this->request( 'POST', '/orders/' . urlencode( $erp_order_id ) . '/payments', [
            'body' => $payment_data,
        ] );
        return true;
    }

    /**
     * Get shipment status.
     *
     * @param string $erp_order_id ERP order ID.
     * @return array Shipment status data.
     */
    public function get_shipment_status( string $erp_order_id ): array {
        $response = $this->request( 'GET', '/orders/' . urlencode( $erp_order_id ) . '/shipment' );
        return $response['data'] ?? [];
    }

    /**
     * Get shipping rates.
     *
     * @param array $request Shipping quote request.
     * @return array List of rate options.
     */
    public function get_shipping_rates( array $request ): array {
        $response = $this->request( 'POST', '/shipping/rates', [ 'body' => $request ] );
        return $response['data'] ?? [];
    }

    /**
     * Request invoice generation.
     *
     * @param string $erp_order_id ERP order ID.
     * @param string $invoice_type Invoice type.
     * @return array Invoice request response.
     */
    public function request_invoice( string $erp_order_id, string $invoice_type ): array {
        return $this->request( 'POST', '/orders/' . urlencode( $erp_order_id ) . '/invoice', [
            'body' => [ 'type' => $invoice_type ],
        ] );
    }

    /**
     * Sync customer data to the ERP.
     *
     * @param array $customer_data Customer payload.
     * @return array ERP customer response.
     */
    public function sync_customer( array $customer_data ): array {
        return $this->request( 'POST', '/customers', [ 'body' => $customer_data ] );
    }

    /**
     * Make an authenticated HTTP request to the ERP API.
     *
     * Handles token refresh on 401, rate limiting on 429,
     * and retries with exponential backoff on server errors.
     *
     * @param string $method  HTTP method (GET, POST, PUT, DELETE).
     * @param string $endpoint API endpoint path.
     * @param array  $options  Request options (query, body).
     * @return array Decoded JSON response body.
     * @throws \RuntimeException On unrecoverable request failure.
     */
    private function request( string $method, string $endpoint, array $options = [] ): array {
        $this->ensure_authenticated();

        $max_attempts = count( $this->backoff_intervals ) + 1;

        for ( $attempt = 0; $attempt < $max_attempts; $attempt++ ) {
            $url = $this->build_url( $endpoint );

            // Append query parameters for GET requests.
            if ( ! empty( $options['query'] ) && 'GET' === $method ) {
                $url = add_query_arg( $options['query'], $url );
            }

            $args = [
                'method'  => $method,
                'timeout' => self::DEFAULT_TIMEOUT,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
            ];

            if ( ! empty( $options['body'] ) && in_array( $method, [ 'POST', 'PUT', 'PATCH' ], true ) ) {
                $args['body'] = wp_json_encode( $options['body'] );
            }

            $response = wp_remote_request( $url, $args );

            // Handle WP_Error (timeout, DNS failure, etc.).
            if ( is_wp_error( $response ) ) {
                $this->log(
                    'error',
                    sprintf( 'Request failed: %s %s - %s', $method, $endpoint, $response->get_error_message() )
                );

                if ( $attempt < $max_attempts - 1 ) {
                    $this->backoff( $attempt );
                    continue;
                }

                throw new \RuntimeException(
                    sprintf(
                        /* translators: 1: HTTP method, 2: endpoint, 3: error message */
                        __( 'ERP request failed after retries: %1$s %2$s - %3$s', 'wc-erp-integration' ),
                        $method,
                        $endpoint,
                        $response->get_error_message()
                    )
                );
            }

            $status_code = wp_remote_retrieve_response_code( $response );
            $body        = json_decode( wp_remote_retrieve_body( $response ), true );

            // Handle 401 Unauthorized - refresh token and retry.
            if ( 401 === $status_code ) {
                $this->log( 'info', 'Token expired, refreshing authentication.' );
                $this->token = null;
                $this->ensure_authenticated();
                continue;
            }

            // Handle 429 Too Many Requests - respect rate limit.
            if ( 429 === $status_code ) {
                $retry_after = (int) wp_remote_retrieve_header( $response, 'retry-after' );
                $wait_time   = $retry_after > 0 ? $retry_after : ( $this->backoff_intervals[ $attempt ] ?? 600 );

                $this->log(
                    'warning',
                    sprintf( 'Rate limited on %s %s. Waiting %d seconds.', $method, $endpoint, $wait_time )
                );

                if ( $attempt < $max_attempts - 1 ) {
                    sleep( $wait_time );
                    continue;
                }

                throw new \RuntimeException(
                    __( 'ERP API rate limit exceeded after retries.', 'wc-erp-integration' )
                );
            }

            // Handle 500/503 server errors - retry with backoff.
            if ( in_array( $status_code, [ 500, 502, 503 ], true ) ) {
                $this->log(
                    'warning',
                    sprintf( 'Server error %d on %s %s. Attempt %d.', $status_code, $method, $endpoint, $attempt + 1 )
                );

                if ( $attempt < $max_attempts - 1 ) {
                    $this->backoff( $attempt );
                    continue;
                }

                throw new \RuntimeException(
                    sprintf(
                        /* translators: 1: status code, 2: HTTP method, 3: endpoint */
                        __( 'ERP server error %1$d on %2$s %3$s after retries.', 'wc-erp-integration' ),
                        $status_code,
                        $method,
                        $endpoint
                    )
                );
            }

            // Handle other client errors (4xx).
            if ( $status_code >= 400 && $status_code < 500 ) {
                $error_message = $body['detail'] ?? $body['message'] ?? $body['title'] ?? __( 'Unknown client error.', 'wc-erp-integration' );
                throw new \RuntimeException(
                    sprintf(
                        /* translators: 1: status code, 2: error message */
                        __( 'ERP client error %1$d: %2$s', 'wc-erp-integration' ),
                        $status_code,
                        $error_message
                    )
                );
            }

            // Success.
            $this->log(
                'debug',
                sprintf( 'Successful request: %s %s (status %d)', $method, $endpoint, $status_code )
            );

            return $body ?? [];
        }

        throw new \RuntimeException(
            __( 'ERP request failed: max attempts reached.', 'wc-erp-integration' )
        );
    }

    /**
     * Ensure we have a valid authentication token.
     *
     * @throws \RuntimeException If authentication fails.
     */
    private function ensure_authenticated(): void {
        if ( $this->token && $this->token_expires_at > time() + 60 ) {
            return;
        }

        $this->authenticate();
    }

    /**
     * Sleep for the backoff interval at the given attempt index.
     *
     * @param int $attempt Zero-based attempt index.
     */
    private function backoff( int $attempt ): void {
        $interval = $this->backoff_intervals[ $attempt ] ?? end( $this->backoff_intervals );
        $this->log( 'debug', sprintf( 'Backing off for %d seconds (attempt %d).', $interval, $attempt + 1 ) );
        sleep( (int) $interval );
    }

    /**
     * Build a full URL from an endpoint path.
     *
     * @param string $endpoint API endpoint path.
     * @return string Full URL.
     */
    private function build_url( string $endpoint ): string {
        return rtrim( $this->api_base_url, '/' ) . '/' . ltrim( $endpoint, '/' );
    }

    /**
     * Encrypt a credential value for storage.
     *
     * Uses WordPress AUTH_KEY as encryption key with AES-256-CBC.
     *
     * @param string $value Plain text value.
     * @return string Base64-encoded encrypted value.
     */
    public static function encrypt_credential( string $value ): string {
        if ( empty( $value ) ) {
            return '';
        }

        $key    = hash( 'sha256', AUTH_KEY, true );
        $iv     = openssl_random_pseudo_bytes( 16 );
        $cipher = openssl_encrypt( $value, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

        if ( false === $cipher ) {
            return '';
        }

        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
        return base64_encode( $iv . $cipher );
    }

    /**
     * Decrypt a stored credential value.
     *
     * @param string $encrypted_value Base64-encoded encrypted value.
     * @return string Decrypted plain text value.
     */
    public static function decrypt_credential( string $encrypted_value ): string {
        if ( empty( $encrypted_value ) ) {
            return '';
        }

        $key  = hash( 'sha256', AUTH_KEY, true );
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
        $data = base64_decode( $encrypted_value, true );

        if ( false === $data || strlen( $data ) < 17 ) {
            return '';
        }

        $iv        = substr( $data, 0, 16 );
        $encrypted = substr( $data, 16 );
        $decrypted = openssl_decrypt( $encrypted, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

        if ( false !== $decrypted && '' !== $decrypted ) {
            return $decrypted;
        }

        // Valor guardado en claro (o AUTH_KEY cambió tras cifrar): usar si parece API key.
        if ( str_starts_with( $encrypted_value, 'erp_' ) ) {
            return $encrypted_value;
        }

        return '';
    }

    /**
     * Verify stored credentials against POST /auth/token (for admin diagnostics).
     *
     * @return array{ok: bool, message: string}
     */
    public function test_authentication(): array {
        try {
            $this->token            = null;
            $this->token_expires_at = 0;
            delete_option( self::OPTION_AUTH_TOKEN );
            delete_option( self::OPTION_TOKEN_EXPIRES );
            $this->authenticate();
            $key_preview = substr( trim( self::decrypt_credential( (string) get_option( self::OPTION_API_KEY, '' ) ) ), 0, 12 );

            return [
                'ok'      => true,
                'message' => sprintf(
                    /* translators: %s: first chars of API key */
                    __( 'Autenticación correcta (API Key empieza por %s…).', 'wc-erp-integration' ),
                    $key_preview
                ),
            ];
        } catch ( \Throwable $e ) {
            return [
                'ok'      => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Log a message using the configured log level.
     *
     * @param string $level   Log level (debug, info, warning, error).
     * @param string $message Log message.
     */
    private function log( string $level, string $message ): void {
        $configured_level = get_option( 'erp_log_level', 'info' );
        $levels           = [ 'debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3 ];

        $configured_priority = $levels[ $configured_level ] ?? 1;
        $message_priority    = $levels[ $level ] ?? 1;

        if ( $message_priority < $configured_priority ) {
            return;
        }

        if ( function_exists( 'wc_get_logger' ) ) {
            $logger = wc_get_logger();
            $logger->log( $level, $message, [ 'source' => 'erp-integration' ] );
        } else {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( sprintf( '[ERP Integration][%s] %s', strtoupper( $level ), $message ) );
        }
    }
}
