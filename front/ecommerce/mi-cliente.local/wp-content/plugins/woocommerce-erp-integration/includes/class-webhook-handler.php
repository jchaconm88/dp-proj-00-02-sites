<?php
/**
 * Webhook Handler.
 *
 * Registers a REST API endpoint to receive incoming webhooks from the ERP.
 * Validates HMAC signatures and dispatches events to appropriate handlers.
 *
 * @package AgenciaERP
 */

namespace AgenciaERP;

defined( 'ABSPATH' ) || exit;

/**
 * Class WebhookHandler
 *
 * Handles incoming ERP webhooks via a registered REST route.
 * Validates HMAC-SHA256 signatures and routes events to
 * the appropriate sync service methods.
 */
class WebhookHandler {

    /**
     * REST API namespace.
     */
    private const REST_NAMESPACE = 'erp-integration/v1';

    /**
     * REST API route.
     */
    private const REST_ROUTE = '/webhook';

    /**
     * HMAC signature header name.
     */
    private const SIGNATURE_HEADER = 'X-ERP-Signature';

    /**
     * Timestamp header name for replay protection.
     */
    private const TIMESTAMP_HEADER = 'X-ERP-Timestamp';

    /**
     * Maximum age of a webhook request in seconds (5 minutes).
     */
    private const MAX_TIMESTAMP_AGE = 300;

    /**
     * Supported webhook event types.
     */
    private const SUPPORTED_EVENTS = [
        'stock_updated',
        'order_status_changed',
        'shipment_created',
        'shipment_updated',
        'price_updated',
        'invoice_generated',
    ];

    /**
     * Sync service instance.
     *
     * @var SyncService|null
     */
    private ?SyncService $sync_service = null;

    /**
     * Initialize the webhook handler.
     */
    public function init(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Set the sync service instance.
     *
     * @param SyncService $sync_service Sync service instance.
     */
    public function set_sync_service( SyncService $sync_service ): void {
        $this->sync_service = $sync_service;
    }

    /**
     * Register REST API routes for webhook handling.
     */
    public function register_routes(): void {
        register_rest_route(
            self::REST_NAMESPACE,
            self::REST_ROUTE,
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'handle_webhook' ],
                'permission_callback' => [ $this, 'validate_signature' ],
            ]
        );
    }

    /**
     * Validate the HMAC signature on incoming webhook requests.
     *
     * @param \WP_REST_Request $request Incoming REST request.
     * @return bool|\WP_Error True if valid, WP_Error if invalid.
     */
    public function validate_signature( \WP_REST_Request $request ) {
        $signature = $request->get_header( self::SIGNATURE_HEADER );
        $timestamp = $request->get_header( self::TIMESTAMP_HEADER );

        if ( empty( $signature ) ) {
            $this->log( 'warning', 'Webhook rejected: missing signature header.' );
            return new \WP_Error(
                'erp_webhook_unauthorized',
                __( 'Missing webhook signature.', 'wc-erp-integration' ),
                [ 'status' => 401 ]
            );
        }

        // Replay protection: check timestamp freshness.
        if ( $timestamp ) {
            $request_time = (int) $timestamp;
            $current_time = time();

            if ( abs( $current_time - $request_time ) > self::MAX_TIMESTAMP_AGE ) {
                $this->log( 'warning', 'Webhook rejected: timestamp too old (replay protection).' );
                return new \WP_Error(
                    'erp_webhook_expired',
                    __( 'Webhook request expired.', 'wc-erp-integration' ),
                    [ 'status' => 401 ]
                );
            }
        }

        // Compute expected signature.
        $webhook_secret = get_option( 'erp_webhook_secret', '' );
        if ( empty( $webhook_secret ) ) {
            $this->log( 'error', 'Webhook rejected: webhook secret not configured.' );
            return new \WP_Error(
                'erp_webhook_misconfigured',
                __( 'Webhook secret not configured.', 'wc-erp-integration' ),
                [ 'status' => 500 ]
            );
        }

        $body = $request->get_body();

        // Build the signing payload: timestamp + body (if timestamp provided).
        $signing_payload = $timestamp ? $timestamp . '.' . $body : $body;
        $expected_signature = hash_hmac( 'sha256', $signing_payload, $webhook_secret );

        if ( ! hash_equals( $expected_signature, $signature ) ) {
            $this->log( 'warning', 'Webhook rejected: invalid signature.' );
            return new \WP_Error(
                'erp_webhook_unauthorized',
                __( 'Invalid webhook signature.', 'wc-erp-integration' ),
                [ 'status' => 401 ]
            );
        }

        return true;
    }

    /**
     * Handle an incoming webhook request.
     *
     * Routes the event to the appropriate handler based on event type.
     *
     * @param \WP_REST_Request $request Incoming REST request.
     * @return \WP_REST_Response Response to the webhook sender.
     */
    public function handle_webhook( \WP_REST_Request $request ): \WP_REST_Response {
        $body = $request->get_json_params();

        $event_type = $body['event'] ?? $body['event_type'] ?? '';
        $event_data = $body['data'] ?? $body['payload'] ?? [];

        if ( empty( $event_type ) ) {
            $this->log( 'warning', 'Webhook received with no event type.' );
            return new \WP_REST_Response(
                [ 'error' => 'Missing event type.' ],
                400
            );
        }

        if ( ! in_array( $event_type, self::SUPPORTED_EVENTS, true ) ) {
            $this->log( 'info', sprintf( 'Webhook received unsupported event: %s', $event_type ) );
            return new \WP_REST_Response(
                [ 'status' => 'ignored', 'message' => 'Unsupported event type.' ],
                200
            );
        }

        $this->log( 'info', sprintf( 'Processing webhook event: %s', $event_type ) );

        try {
            $this->dispatch_event( $event_type, $event_data );

            return new \WP_REST_Response(
                [ 'status' => 'processed', 'event' => $event_type ],
                200
            );
        } catch ( \Exception $e ) {
            $this->log(
                'error',
                sprintf( 'Webhook event %s processing failed: %s', $event_type, $e->getMessage() )
            );

            return new \WP_REST_Response(
                [ 'status' => 'error', 'message' => 'Processing failed.' ],
                500
            );
        }
    }

    /**
     * Dispatch a webhook event to the appropriate handler.
     *
     * @param string $event_type Event type identifier.
     * @param array  $event_data Event payload data.
     */
    private function dispatch_event( string $event_type, array $event_data ): void {
        switch ( $event_type ) {
            case 'stock_updated':
                $this->handle_stock_updated( $event_data );
                break;

            case 'order_status_changed':
                $this->handle_order_status_changed( $event_data );
                break;

            case 'shipment_created':
            case 'shipment_updated':
                $this->handle_shipment_event( $event_type, $event_data );
                break;

            case 'price_updated':
                $this->handle_price_updated( $event_data );
                break;

            case 'invoice_generated':
                $this->handle_invoice_generated( $event_data );
                break;
        }

        /**
         * Fires after a webhook event is processed.
         *
         * @param string $event_type Event type.
         * @param array  $event_data Event data.
         */
        do_action( 'erp_webhook_processed', $event_type, $event_data );
    }

    /**
     * Handle stock_updated webhook event.
     *
     * @param array $data Stock update data.
     */
    private function handle_stock_updated( array $data ): void {
        if ( $this->sync_service ) {
            $this->sync_service->syncStock( $data );
        }
    }

    /**
     * Handle order_status_changed webhook event.
     *
     * Updates the WooCommerce order status based on ERP status change.
     *
     * @param array $data Order status change data.
     */
    private function handle_order_status_changed( array $data ): void {
        $erp_order_id = $data['erp_order_id'] ?? $data['order_id'] ?? '';
        $new_status   = $data['status'] ?? $data['new_status'] ?? '';

        if ( empty( $erp_order_id ) || empty( $new_status ) ) {
            $this->log( 'warning', 'order_status_changed: missing order_id or status.' );
            return;
        }

        // Find WC order by ERP order ID.
        $orders = wc_get_orders( [
            'meta_key'   => '_erp_order_id',
            'meta_value' => $erp_order_id,
            'limit'      => 1,
        ] );

        if ( empty( $orders ) ) {
            $this->log( 'warning', sprintf( 'No WC order found for ERP order ID: %s', $erp_order_id ) );
            return;
        }

        $order     = $orders[0];
        $wc_status = $this->map_erp_order_status( $new_status );

        if ( $wc_status && $order->get_status() !== $wc_status ) {
            $order->update_status(
                $wc_status,
                sprintf(
                    /* translators: %s: ERP status */
                    __( 'Estado actualizado desde ERP: %s', 'wc-erp-integration' ),
                    $new_status
                )
            );

            $this->log(
                'info',
                sprintf( 'Order %d status updated to %s from ERP.', $order->get_id(), $wc_status )
            );
        }
    }

    /**
     * Handle shipment_created and shipment_updated webhook events.
     *
     * Stores tracking information on the WooCommerce order.
     *
     * @param string $event_type Event type (shipment_created or shipment_updated).
     * @param array  $data       Shipment data.
     */
    private function handle_shipment_event( string $event_type, array $data ): void {
        $erp_order_id = $data['erp_order_id'] ?? $data['order_id'] ?? '';

        if ( empty( $erp_order_id ) ) {
            $this->log( 'warning', sprintf( '%s: missing order_id.', $event_type ) );
            return;
        }

        // Find WC order by ERP order ID.
        $orders = wc_get_orders( [
            'meta_key'   => '_erp_order_id',
            'meta_value' => $erp_order_id,
            'limit'      => 1,
        ] );

        if ( empty( $orders ) ) {
            $this->log( 'warning', sprintf( 'No WC order found for ERP order ID: %s', $erp_order_id ) );
            return;
        }

        $order = $orders[0];

        // Store shipment tracking data.
        $tracking_number  = $data['tracking_number'] ?? $data['tracking_code'] ?? '';
        $carrier          = $data['carrier'] ?? $data['shipping_carrier'] ?? '';
        $tracking_url     = $data['tracking_url'] ?? '';
        $shipment_status  = $data['status'] ?? '';

        $order->update_meta_data( '_erp_tracking_number', $tracking_number );
        $order->update_meta_data( '_erp_shipping_carrier', $carrier );
        $order->update_meta_data( '_erp_tracking_url', $tracking_url );
        $order->update_meta_data( '_erp_shipment_status', $shipment_status );
        $order->save();

        // Add order note with tracking info.
        if ( $tracking_number ) {
            $note = sprintf(
                /* translators: 1: carrier name, 2: tracking number, 3: tracking URL */
                __( 'Envío %1$s: Número de seguimiento %2$s', 'wc-erp-integration' ),
                $carrier,
                $tracking_number
            );
            if ( $tracking_url ) {
                $note .= sprintf( ' - %s', $tracking_url );
            }
            $order->add_order_note( $note, 1 ); // Customer-visible note.
        }

        // Update order status to shipped if applicable.
        if ( 'shipment_created' === $event_type && 'processing' === $order->get_status() ) {
            $order->update_status(
                'shipped',
                __( 'Pedido enviado desde ERP.', 'wc-erp-integration' )
            );
        }

        $this->log(
            'info',
            sprintf( 'Shipment %s processed for order %d.', $event_type, $order->get_id() )
        );
    }

    /**
     * Handle price_updated webhook event.
     *
     * @param array $data Price update data.
     */
    private function handle_price_updated( array $data ): void {
        if ( $this->sync_service ) {
            $this->sync_service->syncPrices( $data );
        }
    }

    /**
     * Handle invoice_generated webhook event.
     *
     * Stores invoice data on the WooCommerce order.
     *
     * @param array $data Invoice data.
     */
    private function handle_invoice_generated( array $data ): void {
        $erp_order_id = $data['erp_order_id'] ?? $data['order_id'] ?? '';

        if ( empty( $erp_order_id ) ) {
            $this->log( 'warning', 'invoice_generated: missing order_id.' );
            return;
        }

        // Find WC order by ERP order ID.
        $orders = wc_get_orders( [
            'meta_key'   => '_erp_order_id',
            'meta_value' => $erp_order_id,
            'limit'      => 1,
        ] );

        if ( empty( $orders ) ) {
            $this->log( 'warning', sprintf( 'No WC order found for ERP order ID: %s', $erp_order_id ) );
            return;
        }

        $order = $orders[0];

        // Store invoice metadata.
        $invoice_number = $data['invoice_number'] ?? $data['number'] ?? '';
        $invoice_type   = $data['invoice_type'] ?? $data['type'] ?? '';
        $invoice_url    = $data['invoice_url'] ?? $data['pdf_url'] ?? '';

        $order->update_meta_data( '_erp_invoice_number', $invoice_number );
        $order->update_meta_data( '_erp_invoice_type', $invoice_type );
        $order->update_meta_data( '_erp_invoice_url', $invoice_url );
        $order->save();

        // Add order note.
        $order->add_order_note(
            sprintf(
                /* translators: 1: invoice type, 2: invoice number */
                __( 'Documento tributario generado: %1$s #%2$s', 'wc-erp-integration' ),
                $invoice_type,
                $invoice_number
            )
        );

        $this->log(
            'info',
            sprintf( 'Invoice %s stored for order %d.', $invoice_number, $order->get_id() )
        );
    }

    /**
     * Map ERP order status to WooCommerce order status.
     *
     * @param string $erp_status ERP order status.
     * @return string|null WooCommerce order status or null if no mapping.
     */
    private function map_erp_order_status( string $erp_status ): ?string {
        $status_map = [
            'confirmed'  => 'processing',
            'processing' => 'processing',
            'shipped'    => 'shipped',
            'delivered'  => 'completed',
            'completed'  => 'completed',
            'cancelled'  => 'cancelled',
            'refunded'   => 'refunded',
            'on_hold'    => 'on-hold',
        ];

        return $status_map[ $erp_status ] ?? null;
    }

    /**
     * Log a message using WooCommerce logger.
     *
     * @param string $level   Log level.
     * @param string $message Log message.
     */
    private function log( string $level, string $message ): void {
        if ( function_exists( 'wc_get_logger' ) ) {
            $logger = wc_get_logger();
            $logger->log( $level, '[WebhookHandler] ' . $message, [ 'source' => 'erp-integration' ] );
        } else {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( sprintf( '[ERP WebhookHandler][%s] %s', strtoupper( $level ), $message ) );
        }
    }
}
