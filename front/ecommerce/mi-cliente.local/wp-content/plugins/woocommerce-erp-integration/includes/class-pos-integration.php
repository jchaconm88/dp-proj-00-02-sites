<?php
/**
 * POS Integration.
 *
 * Handles bidirectional stock synchronization between WooCommerce
 * and Point of Sale systems (Vend/Lightspeed, Square, or local POS).
 *
 * @package AgenciaERP
 */

namespace AgenciaERP;

defined( 'ABSPATH' ) || exit;

/**
 * Class POSIntegration
 *
 * Manages POS sync configuration, bidirectional stock updates,
 * low stock alerts, movement logging, and disconnection handling.
 */
class POSIntegration {

    /**
     * Maximum sync delay in seconds.
     */
    private const MAX_SYNC_DELAY = 60;

    /**
     * Default low stock threshold.
     */
    private const DEFAULT_LOW_STOCK_THRESHOLD = 5;

    /**
     * Option key for POS connection status.
     */
    private const OPTION_CONNECTION_STATUS = 'erp_pos_connection_status';

    /**
     * Option key for last successful sync timestamp.
     */
    private const OPTION_LAST_SYNC = 'erp_pos_last_sync';

    /**
     * Stock movement log table name suffix.
     */
    private const MOVEMENT_TABLE = 'erp_stock_movements';

    /**
     * Constructor.
     *
     * Registers POS sync hooks and cron events.
     */
    public function __construct() {
        // Stock change hooks (WooCommerce → POS).
        add_action( 'woocommerce_product_set_stock', [ $this, 'on_wc_stock_change' ], 10, 1 );
        add_action( 'woocommerce_variation_set_stock', [ $this, 'on_wc_stock_change' ], 10, 1 );

        // Cron for periodic full sync.
        add_action( 'erp_pos_full_sync', [ $this, 'full_sync' ] );
        add_action( 'erp_pos_health_check', [ $this, 'check_connection' ] );

        // Admin hooks.
        add_action( 'admin_init', [ $this, 'schedule_cron_events' ] );

        // Low stock check.
        add_action( 'woocommerce_low_stock', [ $this, 'on_low_stock' ], 10, 1 );
        add_action( 'woocommerce_no_stock', [ $this, 'on_no_stock' ], 10, 1 );
    }

    /**
     * Handle stock change in WooCommerce and sync to POS.
     *
     * Triggers within 60 seconds of the change.
     *
     * @param \WC_Product $product Product with updated stock.
     */
    public function on_wc_stock_change( $product ): void {
        if ( ! $product instanceof \WC_Product ) {
            return;
        }

        $stock_quantity = $product->get_stock_quantity();
        $sku            = $product->get_sku();

        if ( ! $sku ) {
            return;
        }

        // Log the movement.
        $this->log_movement( [
            'sku'             => $sku,
            'product_id'      => $product->get_id(),
            'type'            => 'wc_update',
            'channel'         => 'woocommerce',
            'quantity_before'  => (int) $product->get_meta( '_previous_stock_quantity' ) ?: 0,
            'quantity_after'  => (int) $stock_quantity,
            'resulting_stock' => (int) $stock_quantity,
        ] );

        // Sync to POS.
        $this->push_stock_to_pos( $sku, (int) $stock_quantity );

        // Check if product should be deactivated or reactivated.
        $this->update_product_visibility( $product, (int) $stock_quantity );

        // Check low stock threshold.
        $this->check_low_stock_alert( $product, (int) $stock_quantity );
    }

    /**
     * Handle incoming stock update from POS.
     *
     * Called via webhook when POS reports a stock change.
     *
     * @param array $payload POS webhook payload.
     */
    public function handle_pos_stock_update( array $payload ): void {
        $sku       = $payload['sku'] ?? '';
        $new_stock = isset( $payload['quantity'] ) ? (int) $payload['quantity'] : null;
        $channel   = $payload['channel'] ?? 'pos';

        if ( ! $sku || null === $new_stock ) {
            $this->log( 'error', 'POS stock update missing SKU or quantity.' );
            return;
        }

        // Find WooCommerce product by SKU.
        $product_id = wc_get_product_id_by_sku( $sku );
        if ( ! $product_id ) {
            $this->log( 'warning', sprintf( 'POS stock update: SKU %s not found in WooCommerce.', $sku ) );
            return;
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return;
        }

        $previous_stock = (int) $product->get_stock_quantity();

        // Log the movement.
        $this->log_movement( [
            'sku'             => $sku,
            'product_id'      => $product_id,
            'type'            => 'pos_update',
            'channel'         => $channel,
            'quantity_before'  => $previous_stock,
            'quantity_after'  => $new_stock,
            'resulting_stock' => $new_stock,
        ] );

        // Update WooCommerce stock (without triggering sync back to POS).
        remove_action( 'woocommerce_product_set_stock', [ $this, 'on_wc_stock_change' ] );
        $product->set_stock_quantity( $new_stock );
        $product->save();
        add_action( 'woocommerce_product_set_stock', [ $this, 'on_wc_stock_change' ], 10, 1 );

        // Update product visibility.
        $this->update_product_visibility( $product, $new_stock );

        // Check low stock.
        $this->check_low_stock_alert( $product, $new_stock );

        // Update last sync timestamp.
        update_option( self::OPTION_LAST_SYNC, time() );

        $this->log( 'info', sprintf(
            'POS stock update: SKU %s updated from %d to %d (channel: %s)',
            $sku,
            $previous_stock,
            $new_stock,
            $channel
        ) );
    }

    /**
     * Push stock update to POS system.
     *
     * @param string $sku      Product SKU.
     * @param int    $quantity New stock quantity.
     */
    private function push_stock_to_pos( string $sku, int $quantity ): void {
        $pos_api_url = get_option( 'erp_pos_api_url', '' );
        $pos_api_key = get_option( 'erp_pos_api_key', '' );

        if ( ! $pos_api_url || ! $pos_api_key ) {
            return;
        }

        $response = wp_remote_post(
            rtrim( $pos_api_url, '/' ) . '/inventory/update',
            [
                'timeout' => 15,
                'headers' => [
                    'Authorization' => 'Bearer ' . $pos_api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode( [
                    'sku'      => $sku,
                    'quantity' => $quantity,
                    'source'   => 'woocommerce',
                    'timestamp' => current_time( 'c' ),
                ] ),
            ]
        );

        if ( is_wp_error( $response ) ) {
            $this->log( 'error', sprintf(
                'Failed to push stock to POS for SKU %s: %s',
                $sku,
                $response->get_error_message()
            ) );

            // Mark connection as potentially broken.
            $this->handle_connection_error();
            return;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code >= 400 ) {
            $this->log( 'warning', sprintf(
                'POS API returned %d for stock update SKU %s',
                $status_code,
                $sku
            ) );
        }
    }

    /**
     * Update product visibility based on stock level.
     *
     * - Stock = 0: Mark as out of stock
     * - Stock > threshold: Reactivate if was out of stock
     *
     * @param \WC_Product $product  Product object.
     * @param int         $quantity Current stock quantity.
     */
    private function update_product_visibility( \WC_Product $product, int $quantity ): void {
        $reactivation_threshold = (int) get_option( 'erp_pos_reactivation_threshold', 1 );

        if ( 0 === $quantity ) {
            $product->set_stock_status( 'outofstock' );
            $product->save();
        } elseif ( $quantity >= $reactivation_threshold && 'outofstock' === $product->get_stock_status() ) {
            $product->set_stock_status( 'instock' );
            $product->save();
        }
    }

    /**
     * Check low stock threshold and send alert.
     *
     * @param \WC_Product $product  Product object.
     * @param int         $quantity Current stock quantity.
     */
    private function check_low_stock_alert( \WC_Product $product, int $quantity ): void {
        $threshold = (int) get_option( 'erp_pos_low_stock_threshold', self::DEFAULT_LOW_STOCK_THRESHOLD );

        if ( $quantity > 0 && $quantity <= $threshold ) {
            $this->send_low_stock_alert( $product, $quantity, $threshold );
        }
    }

    /**
     * Send low stock alert email to admin.
     *
     * @param \WC_Product $product   Product object.
     * @param int         $quantity  Current stock.
     * @param int         $threshold Configured threshold.
     */
    private function send_low_stock_alert( \WC_Product $product, int $quantity, int $threshold ): void {
        // Avoid duplicate alerts within 24 hours.
        $last_alert = (int) $product->get_meta( '_erp_last_low_stock_alert' );
        if ( $last_alert && ( time() - $last_alert ) < DAY_IN_SECONDS ) {
            return;
        }

        $admin_email = get_option( 'erp_pos_alert_email', get_option( 'admin_email' ) );

        $subject = sprintf(
            /* translators: 1: product name, 2: stock quantity */
            __( '[Stock Bajo] %1$s - Quedan %2$d unidades', 'wc-erp-integration' ),
            $product->get_name(),
            $quantity
        );

        $message = sprintf(
            __( "Alerta de stock bajo:\n\nProducto: %1\$s\nSKU: %2\$s\nStock actual: %3\$d unidades\nUmbral configurado: %4\$d unidades\n\nPor favor reabastecer.", 'wc-erp-integration' ),
            $product->get_name(),
            $product->get_sku(),
            $quantity,
            $threshold
        );

        wp_mail( $admin_email, $subject, $message );

        // Record alert timestamp.
        $product->update_meta_data( '_erp_last_low_stock_alert', time() );
        $product->save();

        $this->log( 'info', sprintf( 'Low stock alert sent for %s (stock: %d)', $product->get_sku(), $quantity ) );
    }

    /**
     * Handle WooCommerce low stock notification.
     *
     * @param \WC_Product $product Product object.
     */
    public function on_low_stock( $product ): void {
        if ( $product instanceof \WC_Product ) {
            $this->check_low_stock_alert( $product, (int) $product->get_stock_quantity() );
        }
    }

    /**
     * Handle WooCommerce no stock notification.
     *
     * @param \WC_Product $product Product object.
     */
    public function on_no_stock( $product ): void {
        if ( $product instanceof \WC_Product ) {
            $this->log( 'info', sprintf( 'Product %s is now out of stock.', $product->get_sku() ) );
        }
    }

    /**
     * Log a stock movement.
     *
     * Records: date, type, channel, resulting stock.
     *
     * @param array $data Movement data.
     */
    private function log_movement( array $data ): void {
        global $wpdb;

        $table_name = $wpdb->prefix . self::MOVEMENT_TABLE;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            $table_name,
            [
                'sku'             => $data['sku'] ?? '',
                'product_id'      => $data['product_id'] ?? 0,
                'movement_type'   => $data['type'] ?? 'unknown',
                'channel'         => $data['channel'] ?? 'unknown',
                'quantity_before'  => $data['quantity_before'] ?? 0,
                'quantity_after'  => $data['quantity_after'] ?? 0,
                'resulting_stock' => $data['resulting_stock'] ?? 0,
                'created_at'      => current_time( 'mysql' ),
            ],
            [ '%s', '%d', '%s', '%s', '%d', '%d', '%d', '%s' ]
        );
    }

    /**
     * Perform full stock synchronization.
     *
     * Used on reconnection after disconnection or scheduled periodic sync.
     */
    public function full_sync(): void {
        $this->log( 'info', 'Starting full POS stock synchronization.' );

        $pos_api_url = get_option( 'erp_pos_api_url', '' );
        $pos_api_key = get_option( 'erp_pos_api_key', '' );

        if ( ! $pos_api_url || ! $pos_api_key ) {
            $this->log( 'error', 'POS API not configured. Cannot perform full sync.' );
            return;
        }

        // Get all stock from POS.
        $response = wp_remote_get(
            rtrim( $pos_api_url, '/' ) . '/inventory/all',
            [
                'timeout' => 60,
                'headers' => [
                    'Authorization' => 'Bearer ' . $pos_api_key,
                    'Accept'        => 'application/json',
                ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            $this->log( 'error', 'Full sync failed: ' . $response->get_error_message() );
            $this->handle_connection_error();
            return;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $items = $body['data'] ?? [];

        $synced = 0;
        foreach ( $items as $item ) {
            $sku      = $item['sku'] ?? '';
            $quantity = isset( $item['quantity'] ) ? (int) $item['quantity'] : null;

            if ( ! $sku || null === $quantity ) {
                continue;
            }

            $product_id = wc_get_product_id_by_sku( $sku );
            if ( ! $product_id ) {
                continue;
            }

            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                continue;
            }

            $current_stock = (int) $product->get_stock_quantity();
            if ( $current_stock !== $quantity ) {
                // Update without triggering POS push.
                remove_action( 'woocommerce_product_set_stock', [ $this, 'on_wc_stock_change' ] );
                $product->set_stock_quantity( $quantity );
                $product->save();
                add_action( 'woocommerce_product_set_stock', [ $this, 'on_wc_stock_change' ], 10, 1 );

                $this->update_product_visibility( $product, $quantity );

                $this->log_movement( [
                    'sku'             => $sku,
                    'product_id'      => $product_id,
                    'type'            => 'full_sync',
                    'channel'         => 'pos',
                    'quantity_before'  => $current_stock,
                    'quantity_after'  => $quantity,
                    'resulting_stock' => $quantity,
                ] );

                $synced++;
            }
        }

        // Mark connection as healthy.
        update_option( self::OPTION_CONNECTION_STATUS, 'connected' );
        update_option( self::OPTION_LAST_SYNC, time() );

        $this->log( 'info', sprintf( 'Full sync completed. %d products updated.', $synced ) );
    }

    /**
     * Check POS connection health.
     *
     * Detects disconnection, logs it, and notifies admin.
     */
    public function check_connection(): void {
        $pos_api_url = get_option( 'erp_pos_api_url', '' );
        $pos_api_key = get_option( 'erp_pos_api_key', '' );

        if ( ! $pos_api_url || ! $pos_api_key ) {
            return;
        }

        $response = wp_remote_get(
            rtrim( $pos_api_url, '/' ) . '/health',
            [
                'timeout' => 10,
                'headers' => [
                    'Authorization' => 'Bearer ' . $pos_api_key,
                    'Accept'        => 'application/json',
                ],
            ]
        );

        $previous_status = get_option( self::OPTION_CONNECTION_STATUS, 'unknown' );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) >= 400 ) {
            // Connection lost.
            if ( 'connected' === $previous_status ) {
                $this->handle_connection_error();
            }
            return;
        }

        // Connection restored.
        if ( 'disconnected' === $previous_status ) {
            $this->handle_reconnection();
        }

        update_option( self::OPTION_CONNECTION_STATUS, 'connected' );
    }

    /**
     * Handle POS connection error.
     *
     * Detects disconnection, logs it, and notifies admin.
     */
    private function handle_connection_error(): void {
        $current_status = get_option( self::OPTION_CONNECTION_STATUS, 'unknown' );

        if ( 'disconnected' === $current_status ) {
            return; // Already handling disconnection.
        }

        update_option( self::OPTION_CONNECTION_STATUS, 'disconnected' );
        update_option( 'erp_pos_disconnected_at', current_time( 'mysql' ) );

        $this->log( 'error', 'POS connection lost. Stock sync paused.' );

        // Notify admin.
        $admin_email = get_option( 'erp_pos_alert_email', get_option( 'admin_email' ) );
        wp_mail(
            $admin_email,
            __( '[ALERTA] Conexión POS perdida', 'wc-erp-integration' ),
            __( "Se ha perdido la conexión con el sistema POS.\n\nLa sincronización de stock está pausada.\nSe realizará una sincronización completa cuando se restablezca la conexión.\n\nPor favor verifique el estado del POS.", 'wc-erp-integration' )
        );
    }

    /**
     * Handle POS reconnection.
     *
     * Triggers full sync and notifies admin.
     */
    private function handle_reconnection(): void {
        update_option( self::OPTION_CONNECTION_STATUS, 'connected' );

        $this->log( 'info', 'POS connection restored. Triggering full sync.' );

        // Schedule immediate full sync.
        wp_schedule_single_event( time(), 'erp_pos_full_sync' );

        // Notify admin.
        $admin_email = get_option( 'erp_pos_alert_email', get_option( 'admin_email' ) );
        wp_mail(
            $admin_email,
            __( '[INFO] Conexión POS restablecida', 'wc-erp-integration' ),
            __( "La conexión con el sistema POS se ha restablecido.\n\nSe está ejecutando una sincronización completa de stock.", 'wc-erp-integration' )
        );
    }

    /**
     * Schedule POS-related cron events.
     */
    public function schedule_cron_events(): void {
        if ( ! wp_next_scheduled( 'erp_pos_health_check' ) ) {
            wp_schedule_event( time(), 'five_minutes', 'erp_pos_health_check' );
        }

        if ( ! wp_next_scheduled( 'erp_pos_full_sync' ) ) {
            wp_schedule_event( time(), 'daily', 'erp_pos_full_sync' );
        }
    }

    /**
     * Create stock movements table on plugin activation.
     */
    public static function create_movements_table(): void {
        global $wpdb;

        $table_name      = $wpdb->prefix . self::MOVEMENT_TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            sku VARCHAR(100) NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            movement_type VARCHAR(50) NOT NULL,
            channel VARCHAR(50) NOT NULL,
            quantity_before INT NOT NULL DEFAULT 0,
            quantity_after INT NOT NULL DEFAULT 0,
            resulting_stock INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sku (sku),
            KEY idx_product_id (product_id),
            KEY idx_created_at (created_at),
            KEY idx_channel (channel)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Get POS connection status.
     *
     * @return array Status information.
     */
    public function get_status(): array {
        return [
            'connection' => get_option( self::OPTION_CONNECTION_STATUS, 'unknown' ),
            'last_sync'  => get_option( self::OPTION_LAST_SYNC, 0 ),
            'pos_url'    => get_option( 'erp_pos_api_url', '' ) ? 'configured' : 'not_configured',
        ];
    }

    /**
     * Log a message.
     *
     * @param string $level   Log level.
     * @param string $message Log message.
     */
    private function log( string $level, string $message ): void {
        if ( function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->log( $level, $message, [ 'source' => 'erp-pos' ] );
        }
    }
}
