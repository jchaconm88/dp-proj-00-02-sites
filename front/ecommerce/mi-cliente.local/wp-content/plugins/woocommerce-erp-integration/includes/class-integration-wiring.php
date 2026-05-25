<?php
/**
 * Integration Wiring.
 *
 * Connects all integration components with WooCommerce hooks.
 * This is the central orchestration class that wires:
 * - ERP Integration Layer with WooCommerce order/customer hooks
 * - Shipping method with checkout flow
 * - Payment gateways with invoicing flow
 * - POS sync with stock management
 *
 * @package AgenciaERP
 */

namespace AgenciaERP;

defined( 'ABSPATH' ) || exit;

/**
 * Class IntegrationWiring
 *
 * Registers all WooCommerce hooks and connects integration components.
 */
class IntegrationWiring {

    /**
     * Payment integration instance.
     *
     * @var PaymentIntegration|null
     */
    private ?PaymentIntegration $payment_integration = null;

    /**
     * Invoice integration instance.
     *
     * @var InvoiceIntegration|null
     */
    private ?InvoiceIntegration $invoice_integration = null;

    /**
     * POS integration instance.
     *
     * @var POSIntegration|null
     */
    private ?POSIntegration $pos_integration = null;

    /**
     * Constructor.
     *
     * Initializes all integration components and registers hooks.
     */
    public function __construct() {
        add_action( 'init', [ $this, 'init_integrations' ] );
    }

    /**
     * Initialize all integration components.
     *
     * Called on 'init' to ensure WooCommerce is loaded.
     */
    public function init_integrations(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        // Initialize components.
        $this->payment_integration = new PaymentIntegration();
        $this->invoice_integration = new InvoiceIntegration();
        $this->pos_integration     = new POSIntegration();

        // Wire ERP Integration Layer with WooCommerce hooks.
        $this->wire_erp_order_hooks();
        $this->wire_erp_customer_hooks();

        // Wire shipping method with checkout flow.
        $this->wire_shipping_method();

        // Wire payment gateways with invoicing flow.
        $this->wire_payment_invoicing();

        // Wire POS sync with stock management.
        $this->wire_pos_stock_sync();

        // Wire webhook handlers.
        $this->wire_webhook_handlers();
    }

    /**
     * Wire ERP Integration Layer with WooCommerce order hooks.
     *
     * Hooks: woocommerce_new_order, woocommerce_payment_complete,
     * woocommerce_order_status_changed.
     */
    private function wire_erp_order_hooks(): void {
        // New order created → Push to ERP.
        add_action( 'woocommerce_new_order', [ $this, 'on_new_order' ], 10, 2 );

        // Payment completed → Confirm in ERP + Request invoice.
        add_action( 'woocommerce_payment_complete', [ $this, 'on_payment_complete' ], 10, 1 );

        // Order status changed → Sync status to ERP.
        add_action( 'woocommerce_order_status_changed', [ $this, 'on_order_status_changed' ], 10, 4 );
    }

    /**
     * Wire ERP Integration Layer with WooCommerce customer hooks.
     *
     * Hooks: woocommerce_created_customer, woocommerce_update_customer.
     */
    private function wire_erp_customer_hooks(): void {
        // New customer registered → Sync to ERP.
        add_action( 'woocommerce_created_customer', [ $this, 'on_customer_created' ], 10, 3 );

        // Customer profile updated → Sync to ERP.
        add_action( 'woocommerce_update_customer', [ $this, 'on_customer_updated' ], 10, 1 );

        // Also hook into profile updates.
        add_action( 'profile_update', [ $this, 'on_profile_updated' ], 10, 2 );
    }

    /**
     * Wire shipping method with WooCommerce checkout flow.
     *
     * Registers the erp_shipping method so it appears in shipping zones.
     */
    private function wire_shipping_method(): void {
        // Register ERP shipping method.
        add_filter( 'woocommerce_shipping_methods', [ $this, 'register_shipping_method' ] );

        // Add custom order status for shipped orders.
        add_action( 'init', [ $this, 'register_shipped_order_status' ] );
        add_filter( 'wc_order_statuses', [ $this, 'add_shipped_order_status' ] );
    }

    /**
     * Wire payment gateways with invoicing flow.
     *
     * On payment complete → Request invoice from ERP.
     */
    private function wire_payment_invoicing(): void {
        // Invoice request is already hooked in InvoiceIntegration constructor
        // with priority 20 on woocommerce_payment_complete.
        // This ensures payment confirmation (priority 10) happens before invoice request.

        // Additional hook: when order is manually marked as paid.
        add_action( 'woocommerce_order_status_pending_to_processing', [ $this, 'on_manual_payment_confirmation' ], 10, 2 );
        add_action( 'woocommerce_order_status_on-hold_to_processing', [ $this, 'on_manual_payment_confirmation' ], 10, 2 );
    }

    /**
     * Wire POS sync with stock management.
     *
     * Stock changes in WooCommerce trigger POS sync.
     * POS integration hooks are registered in its constructor.
     */
    private function wire_pos_stock_sync(): void {
        // POS hooks are already registered in POSIntegration constructor.
        // Additional: when order reduces stock, ensure POS is notified.
        add_action( 'woocommerce_reduce_order_stock', [ $this, 'on_order_stock_reduced' ], 10, 1 );

        // When stock is restored (cancelled/refunded order).
        add_action( 'woocommerce_restore_order_stock', [ $this, 'on_order_stock_restored' ], 10, 1 );
    }

    /**
     * Wire webhook endpoint handlers.
     *
     * Registers REST API routes for incoming ERP webhooks.
     */
    private function wire_webhook_handlers(): void {
        add_action( 'rest_api_init', [ $this, 'register_webhook_routes' ] );
    }

    // =========================================================================
    // Order Hook Handlers
    // =========================================================================

    /**
     * Handle new order creation.
     *
     * Pushes the order to the ERP system.
     *
     * @param int       $order_id Order ID.
     * @param \WC_Order $order    Order object.
     */
    public function on_new_order( int $order_id, $order ): void {
        if ( ! $order instanceof \WC_Order ) {
            $order = wc_get_order( $order_id );
        }

        if ( ! $order ) {
            return;
        }

        $plugin = Plugin::get_instance();
        $client = $plugin->get_erp_client();

        if ( ! $client ) {
            $this->queue_operation( 'create_order', [ 'order_id' => $order_id ] );
            return;
        }

        try {
            $order_data = $this->build_erp_order_data( $order );
            $response   = $client->create_order( $order_data );

            if ( ! empty( $response['erp_order_id'] ) ) {
                $order->update_meta_data( '_erp_order_id', $response['erp_order_id'] );
                $order->update_meta_data( '_erp_order_synced_at', current_time( 'mysql' ) );
                $order->save();
            }

            $this->log( 'info', sprintf( 'Order #%d pushed to ERP.', $order_id ) );
        } catch ( \RuntimeException $e ) {
            $this->log( 'error', sprintf( 'Failed to push order #%d to ERP: %s', $order_id, $e->getMessage() ) );
            $this->queue_operation( 'create_order', [ 'order_id' => $order_id ] );
        }
    }

    /**
     * Handle payment completion.
     *
     * Payment integration and invoice integration handle their own hooks.
     * This method handles additional ERP status update.
     *
     * @param int $order_id Order ID.
     */
    public function on_payment_complete( int $order_id ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $erp_order_id = $order->get_meta( '_erp_order_id' );
        if ( ! $erp_order_id ) {
            return;
        }

        $plugin = Plugin::get_instance();
        $client = $plugin->get_erp_client();

        if ( $client ) {
            try {
                $client->update_order_status( $erp_order_id, 'paid' );
            } catch ( \RuntimeException $e ) {
                $this->log( 'warning', sprintf(
                    'Failed to update ERP order status to paid for order #%d: %s',
                    $order_id,
                    $e->getMessage()
                ) );
            }
        }
    }

    /**
     * Handle order status change.
     *
     * Syncs the new status to the ERP.
     *
     * @param int       $order_id   Order ID.
     * @param string    $old_status Previous status.
     * @param string    $new_status New status.
     * @param \WC_Order $order      Order object.
     */
    public function on_order_status_changed( int $order_id, string $old_status, string $new_status, \WC_Order $order ): void {
        $erp_order_id = $order->get_meta( '_erp_order_id' );
        if ( ! $erp_order_id ) {
            return;
        }

        // Map WC statuses to ERP statuses.
        $status_map = [
            'processing' => 'processing',
            'completed'  => 'completed',
            'cancelled'  => 'cancelled',
            'refunded'   => 'refunded',
            'on-hold'    => 'on_hold',
            'shipped'    => 'shipped',
        ];

        $erp_status = $status_map[ $new_status ] ?? $new_status;

        $plugin = Plugin::get_instance();
        $client = $plugin->get_erp_client();

        if ( $client ) {
            try {
                $client->update_order_status( $erp_order_id, $erp_status );
                $this->log( 'info', sprintf(
                    'Order #%d status updated in ERP: %s → %s',
                    $order_id,
                    $old_status,
                    $new_status
                ) );
            } catch ( \RuntimeException $e ) {
                $this->log( 'warning', sprintf(
                    'Failed to sync status change for order #%d: %s',
                    $order_id,
                    $e->getMessage()
                ) );
                $this->queue_operation( 'update_order_status', [
                    'order_id'     => $order_id,
                    'erp_order_id' => $erp_order_id,
                    'status'       => $erp_status,
                ] );
            }
        }
    }

    // =========================================================================
    // Customer Hook Handlers
    // =========================================================================

    /**
     * Handle new customer creation.
     *
     * @param int   $customer_id   Customer ID.
     * @param array $new_customer_data Customer data.
     * @param bool  $password_generated Whether password was generated.
     */
    public function on_customer_created( int $customer_id, array $new_customer_data, bool $password_generated ): void {
        $this->sync_customer_to_erp( $customer_id );
    }

    /**
     * Handle customer update via WooCommerce.
     *
     * @param int $customer_id Customer ID.
     */
    public function on_customer_updated( int $customer_id ): void {
        $this->sync_customer_to_erp( $customer_id );
    }

    /**
     * Handle profile update via WordPress.
     *
     * @param int      $user_id       User ID.
     * @param \WP_User $old_user_data Previous user data.
     */
    public function on_profile_updated( int $user_id, $old_user_data ): void {
        // Only sync if user is a WooCommerce customer.
        $user = get_userdata( $user_id );
        if ( $user && in_array( 'customer', $user->roles, true ) ) {
            $this->sync_customer_to_erp( $user_id );
        }
    }

    /**
     * Sync customer data to ERP.
     *
     * @param int $customer_id Customer/User ID.
     */
    private function sync_customer_to_erp( int $customer_id ): void {
        $customer = new \WC_Customer( $customer_id );

        $customer_data = [
            'wc_customer_id' => $customer_id,
            'email'          => $customer->get_email(),
            'first_name'     => $customer->get_first_name(),
            'last_name'      => $customer->get_last_name(),
            'phone'          => $customer->get_billing_phone(),
            'document_type'  => get_user_meta( $customer_id, 'billing_document_type', true ),
            'document_number' => get_user_meta( $customer_id, 'billing_document_number', true ),
            'billing_address' => [
                'address_1' => $customer->get_billing_address_1(),
                'address_2' => $customer->get_billing_address_2(),
                'city'      => $customer->get_billing_city(),
                'state'     => $customer->get_billing_state(),
                'postcode'  => $customer->get_billing_postcode(),
                'country'   => $customer->get_billing_country(),
            ],
        ];

        $plugin = Plugin::get_instance();
        $client = $plugin->get_erp_client();

        if ( $client ) {
            try {
                $response = $client->sync_customer( $customer_data );
                if ( ! empty( $response['erp_customer_id'] ) ) {
                    update_user_meta( $customer_id, '_erp_customer_id', $response['erp_customer_id'] );
                }
                $this->log( 'info', sprintf( 'Customer #%d synced to ERP.', $customer_id ) );
            } catch ( \RuntimeException $e ) {
                $this->log( 'warning', sprintf(
                    'Failed to sync customer #%d to ERP: %s',
                    $customer_id,
                    $e->getMessage()
                ) );
                $this->queue_operation( 'sync_customer', [ 'customer_id' => $customer_id ] );
            }
        } else {
            $this->queue_operation( 'sync_customer', [ 'customer_id' => $customer_id ] );
        }
    }

    // =========================================================================
    // Shipping Method Registration
    // =========================================================================

    /**
     * Register ERP shipping method with WooCommerce.
     *
     * @param array $methods Existing shipping methods.
     * @return array Modified shipping methods.
     */
    public function register_shipping_method( array $methods ): array {
        $methods['erp_shipping'] = ERPShippingMethod::class;
        return $methods;
    }

    /**
     * Register custom "shipped" order status.
     */
    public function register_shipped_order_status(): void {
        register_post_status( 'wc-shipped', [
            'label'                     => _x( 'Enviado', 'Order status', 'wc-erp-integration' ),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            /* translators: %s: number of orders */
            'label_count'               => _n_noop(
                'Enviado <span class="count">(%s)</span>',
                'Enviados <span class="count">(%s)</span>',
                'wc-erp-integration'
            ),
        ] );
    }

    /**
     * Add "shipped" status to WooCommerce order statuses.
     *
     * @param array $statuses Existing order statuses.
     * @return array Modified order statuses.
     */
    public function add_shipped_order_status( array $statuses ): array {
        $statuses['wc-shipped'] = _x( 'Enviado', 'Order status', 'wc-erp-integration' );
        return $statuses;
    }

    // =========================================================================
    // Payment + Invoicing Wiring
    // =========================================================================

    /**
     * Handle manual payment confirmation (admin marks order as processing).
     *
     * Triggers invoice request for manually confirmed payments.
     *
     * @param int       $order_id Order ID.
     * @param \WC_Order $order    Order object.
     */
    public function on_manual_payment_confirmation( int $order_id, \WC_Order $order ): void {
        // Only trigger if invoice hasn't been requested yet.
        if ( ! $order->get_meta( '_erp_invoice_requested' ) ) {
            $this->invoice_integration->request_invoice_on_payment( $order_id );
        }
    }

    // =========================================================================
    // POS + Stock Wiring
    // =========================================================================

    /**
     * Handle order stock reduction.
     *
     * Ensures POS is notified when order reduces stock.
     *
     * @param \WC_Order $order Order object.
     */
    public function on_order_stock_reduced( $order ): void {
        if ( ! $order instanceof \WC_Order ) {
            return;
        }

        $this->log( 'debug', sprintf( 'Stock reduced for order #%d. POS sync triggered via product hooks.', $order->get_id() ) );
    }

    /**
     * Handle order stock restoration (cancelled/refunded).
     *
     * Ensures POS is notified when stock is restored.
     *
     * @param \WC_Order $order Order object.
     */
    public function on_order_stock_restored( $order ): void {
        if ( ! $order instanceof \WC_Order ) {
            return;
        }

        $this->log( 'debug', sprintf( 'Stock restored for order #%d. POS sync triggered via product hooks.', $order->get_id() ) );
    }

    // =========================================================================
    // Webhook Routes
    // =========================================================================

    /**
     * Register REST API webhook routes.
     */
    public function register_webhook_routes(): void {
        register_rest_route( 'erp/v1', '/webhooks/shipping', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_shipping_webhook' ],
            'permission_callback' => [ $this, 'verify_webhook_signature' ],
        ] );

        register_rest_route( 'erp/v1', '/webhooks/invoice', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_invoice_webhook' ],
            'permission_callback' => [ $this, 'verify_webhook_signature' ],
        ] );

        register_rest_route( 'erp/v1', '/webhooks/pos-stock', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_pos_webhook' ],
            'permission_callback' => [ $this, 'verify_webhook_signature' ],
        ] );
    }

    /**
     * Handle shipping webhook (shipment_created, shipment_updated).
     *
     * @param \WP_REST_Request $request REST request.
     * @return \WP_REST_Response Response.
     */
    public function handle_shipping_webhook( \WP_REST_Request $request ): \WP_REST_Response {
        $payload = $request->get_json_params();
        $event   = $payload['event'] ?? '';

        switch ( $event ) {
            case 'shipment_created':
                ERPShippingMethod::handle_shipment_created( $payload );
                break;

            case 'shipment_updated':
                ERPShippingMethod::handle_shipment_updated( $payload );
                break;

            default:
                return new \WP_REST_Response( [ 'error' => 'Unknown event' ], 400 );
        }

        return new \WP_REST_Response( [ 'status' => 'ok' ], 200 );
    }

    /**
     * Handle invoice webhook (invoice_generated, invoice_rejected).
     *
     * @param \WP_REST_Request $request REST request.
     * @return \WP_REST_Response Response.
     */
    public function handle_invoice_webhook( \WP_REST_Request $request ): \WP_REST_Response {
        $payload = $request->get_json_params();
        $event   = $payload['event'] ?? '';

        switch ( $event ) {
            case 'invoice_generated':
                InvoiceIntegration::handle_invoice_generated( $payload );
                break;

            case 'invoice_rejected':
                InvoiceIntegration::handle_invoice_rejected( $payload );
                break;

            default:
                return new \WP_REST_Response( [ 'error' => 'Unknown event' ], 400 );
        }

        return new \WP_REST_Response( [ 'status' => 'ok' ], 200 );
    }

    /**
     * Handle POS stock webhook.
     *
     * @param \WP_REST_Request $request REST request.
     * @return \WP_REST_Response Response.
     */
    public function handle_pos_webhook( \WP_REST_Request $request ): \WP_REST_Response {
        $payload = $request->get_json_params();

        if ( $this->pos_integration ) {
            $this->pos_integration->handle_pos_stock_update( $payload );
        }

        return new \WP_REST_Response( [ 'status' => 'ok' ], 200 );
    }

    /**
     * Verify webhook signature for authentication.
     *
     * @param \WP_REST_Request $request REST request.
     * @return bool True if signature is valid.
     */
    public function verify_webhook_signature( \WP_REST_Request $request ): bool {
        $signature = $request->get_header( 'X-ERP-Signature' );
        $secret    = get_option( 'erp_webhook_secret', '' );

        if ( ! $signature || ! $secret ) {
            // Allow if no secret configured (development mode).
            return empty( $secret );
        }

        $body            = $request->get_body();
        $expected_signature = hash_hmac( 'sha256', $body, $secret );

        return hash_equals( $expected_signature, $signature );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build ERP order data from WooCommerce order.
     *
     * @param \WC_Order $order WooCommerce order.
     * @return array Order data for ERP.
     */
    private function build_erp_order_data( \WC_Order $order ): array {
        $items = [];
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            $items[] = [
                'sku'      => $product ? $product->get_sku() : '',
                'name'     => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'price'    => (float) $item->get_total(),
                'tax'      => (float) $item->get_total_tax(),
            ];
        }

        return [
            'wc_order_id'    => $order->get_id(),
            'order_number'   => $order->get_order_number(),
            'status'         => $order->get_status(),
            'total'          => (float) $order->get_total(),
            'tax_total'      => (float) $order->get_total_tax(),
            'currency'       => $order->get_currency(),
            'payment_method' => $order->get_payment_method(),
            'customer'       => [
                'email'      => $order->get_billing_email(),
                'first_name' => $order->get_billing_first_name(),
                'last_name'  => $order->get_billing_last_name(),
                'phone'      => $order->get_billing_phone(),
                'document_type'   => $order->get_meta( '_billing_document_type' ),
                'document_number' => $order->get_meta( '_billing_document_number' ),
            ],
            'billing'        => [
                'address_1' => $order->get_billing_address_1(),
                'address_2' => $order->get_billing_address_2(),
                'city'      => $order->get_billing_city(),
                'state'     => $order->get_billing_state(),
                'postcode'  => $order->get_billing_postcode(),
                'country'   => $order->get_billing_country(),
            ],
            'shipping'       => [
                'address_1' => $order->get_shipping_address_1(),
                'address_2' => $order->get_shipping_address_2(),
                'city'      => $order->get_shipping_city(),
                'state'     => $order->get_shipping_state(),
                'postcode'  => $order->get_shipping_postcode(),
                'country'   => $order->get_shipping_country(),
            ],
            'items'          => $items,
            'created_at'     => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d H:i:s' ) : '',
        ];
    }

    /**
     * Queue an operation for retry via the sync queue.
     *
     * @param string $operation Operation type.
     * @param array  $data      Operation data.
     */
    private function queue_operation( string $operation, array $data ): void {
        $plugin     = Plugin::get_instance();
        $sync_queue = $plugin->get_sync_queue();

        if ( $sync_queue ) {
            $sync_queue->enqueue( $operation, $data );
        }
    }

    /**
     * Log a message.
     *
     * @param string $level   Log level.
     * @param string $message Log message.
     */
    private function log( string $level, string $message ): void {
        if ( function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->log( $level, $message, [ 'source' => 'erp-wiring' ] );
        }
    }
}
