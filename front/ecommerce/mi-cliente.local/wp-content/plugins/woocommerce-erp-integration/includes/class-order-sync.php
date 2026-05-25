<?php
/**
 * Order Sync Service.
 *
 * Pushes WooCommerce orders to the ERP system on creation
 * and payment completion. Handles duplicate detection (409 Conflict).
 *
 * @package AgenciaERP
 */

namespace AgenciaERP;

defined( 'ABSPATH' ) || exit;

/**
 * Class OrderSync
 *
 * Hooks into WooCommerce order lifecycle events to push
 * order data to the ERP in real-time.
 */
class OrderSync {

    /**
     * Meta key for storing the ERP order ID on WC orders.
     */
    private const META_ERP_ORDER_ID = '_erp_order_id';

    /**
     * Meta key for tracking sync status.
     */
    private const META_SYNC_STATUS = '_erp_sync_status';

    /**
     * Meta key for last sync attempt timestamp.
     */
    private const META_LAST_SYNC = '_erp_last_sync_attempt';

    /**
     * ERP client instance.
     *
     * @var ERPClient
     */
    private ERPClient $erp_client;

    /**
     * Sync queue instance.
     *
     * @var SyncQueue
     */
    private SyncQueue $sync_queue;

    /**
     * Constructor.
     *
     * @param ERPClient $erp_client ERP client instance.
     * @param SyncQueue $sync_queue Sync queue instance.
     */
    public function __construct( ERPClient $erp_client, SyncQueue $sync_queue ) {
        $this->erp_client = $erp_client;
        $this->sync_queue = $sync_queue;
    }

    /**
     * Initialize WooCommerce hooks for order sync.
     */
    public function init(): void {
        add_action( 'woocommerce_new_order', [ $this, 'on_new_order' ], 10, 2 );
        add_action( 'woocommerce_payment_complete', [ $this, 'on_payment_complete' ], 10, 1 );
    }

    /**
     * Handle new order creation.
     *
     * Pushes the order to ERP immediately. If it fails,
     * enqueues the operation for retry.
     *
     * @param int       $order_id WooCommerce order ID.
     * @param \WC_Order $order    WooCommerce order object.
     */
    public function on_new_order( int $order_id, $order = null ): void {
        if ( ! $order ) {
            $order = wc_get_order( $order_id );
        }

        if ( ! $order ) {
            $this->log( 'error', sprintf( 'Order %d not found for ERP sync.', $order_id ) );
            return;
        }

        // Skip if already synced.
        $erp_order_id = $order->get_meta( self::META_ERP_ORDER_ID );
        if ( $erp_order_id ) {
            return;
        }

        $this->push_order_to_erp( $order );
    }

    /**
     * Handle payment completion.
     *
     * Confirms payment in ERP if order was already synced,
     * or pushes the full order if not yet synced.
     *
     * @param int $order_id WooCommerce order ID.
     */
    public function on_payment_complete( int $order_id ): void {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            $this->log( 'error', sprintf( 'Order %d not found for payment confirmation.', $order_id ) );
            return;
        }

        $erp_order_id = $order->get_meta( self::META_ERP_ORDER_ID );

        if ( $erp_order_id ) {
            // Order already in ERP, confirm payment.
            $this->confirm_payment_in_erp( $order, $erp_order_id );
        } else {
            // Order not yet in ERP, push it now.
            $this->push_order_to_erp( $order );
        }
    }

    /**
     * Push a WooCommerce order to the ERP.
     *
     * Maps WC order data to ERP OrderPayload format and sends it.
     * Handles 409 Conflict for duplicate orders.
     *
     * @param \WC_Order $order WooCommerce order object.
     */
    private function push_order_to_erp( \WC_Order $order ): void {
        $payload = $this->map_order_to_erp_payload( $order );

        try {
            $response = $this->erp_client->create_order( $payload );

            // Store ERP order ID as order meta.
            $erp_order_id = $response['erp_order_id'] ?? $response['id'] ?? '';
            if ( $erp_order_id ) {
                $order->update_meta_data( self::META_ERP_ORDER_ID, $erp_order_id );
                $order->update_meta_data( self::META_SYNC_STATUS, 'synced' );
                $order->update_meta_data( self::META_LAST_SYNC, gmdate( 'c' ) );
                $order->save();
            }

            $this->log(
                'info',
                sprintf( 'Order %d synced to ERP (ERP ID: %s).', $order->get_id(), $erp_order_id )
            );
        } catch ( \RuntimeException $e ) {
            // Handle 409 Conflict - duplicate order.
            if ( str_contains( $e->getMessage(), '409' ) ) {
                $this->handle_duplicate_order( $order, $e );
                return;
            }

            // Enqueue for retry on other failures.
            $order->update_meta_data( self::META_SYNC_STATUS, 'failed' );
            $order->update_meta_data( self::META_LAST_SYNC, gmdate( 'c' ) );
            $order->save();

            $this->sync_queue->enqueue( 'push_order', $payload, $e->getMessage() );

            $this->log(
                'error',
                sprintf( 'Failed to push order %d to ERP: %s. Enqueued for retry.', $order->get_id(), $e->getMessage() )
            );
        }
    }

    /**
     * Confirm payment in the ERP for an already-synced order.
     *
     * @param \WC_Order $order        WooCommerce order object.
     * @param string    $erp_order_id ERP order identifier.
     */
    private function confirm_payment_in_erp( \WC_Order $order, string $erp_order_id ): void {
        $payment_data = [
            'method'         => $order->get_payment_method(),
            'method_title'   => $order->get_payment_method_title(),
            'transaction_id' => $order->get_transaction_id(),
            'amount'         => $order->get_total(),
            'currency'       => $order->get_currency(),
            'date_paid'      => $order->get_date_paid() ? $order->get_date_paid()->format( 'c' ) : gmdate( 'c' ),
        ];

        try {
            $this->erp_client->confirm_payment( $erp_order_id, $payment_data );

            $this->log(
                'info',
                sprintf( 'Payment confirmed in ERP for order %d (ERP ID: %s).', $order->get_id(), $erp_order_id )
            );
        } catch ( \RuntimeException $e ) {
            // Enqueue for retry.
            $this->sync_queue->enqueue(
                'confirm_payment',
                [
                    'erp_order_id' => $erp_order_id,
                    'payment_data' => $payment_data,
                ],
                $e->getMessage()
            );

            $this->log(
                'error',
                sprintf(
                    'Failed to confirm payment for order %d in ERP: %s. Enqueued for retry.',
                    $order->get_id(),
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Handle 409 Conflict (duplicate order) from ERP.
     *
     * Attempts to retrieve the existing ERP order ID and store it.
     *
     * @param \WC_Order         $order     WooCommerce order object.
     * @param \RuntimeException $exception The 409 exception.
     */
    private function handle_duplicate_order( \WC_Order $order, \RuntimeException $exception ): void {
        $this->log(
            'warning',
            sprintf(
                'Order %d already exists in ERP (409 Conflict). Attempting to retrieve ERP ID.',
                $order->get_id()
            )
        );

        // Mark as duplicate - the ERP already has this order.
        $order->update_meta_data( self::META_SYNC_STATUS, 'duplicate' );
        $order->update_meta_data( self::META_LAST_SYNC, gmdate( 'c' ) );
        $order->save();

        // Add order note for admin visibility.
        $order->add_order_note(
            __( 'ERP: Pedido ya existe en el sistema ERP (conflicto 409). Verificar manualmente.', 'wc-erp-integration' )
        );
    }

    /**
     * Map a WooCommerce order to ERP OrderPayload format.
     *
     * @param \WC_Order $order WooCommerce order object.
     * @return array ERP-compatible order payload.
     */
    private function map_order_to_erp_payload( \WC_Order $order ): array {
        $items = [];
        foreach ( $order->get_items() as $item ) {
            /** @var \WC_Order_Item_Product $item */
            $product = $item->get_product();
            $items[] = [
                'sku'        => $product ? $product->get_sku() : '',
                'name'       => $item->get_name(),
                'quantity'   => $item->get_quantity(),
                'unit_price' => (float) ( $order->get_item_total( $item, false ) ),
                'total'      => (float) $item->get_total(),
                'tax'        => (float) $item->get_total_tax(),
            ];
        }

        $payload = [
            'external_id' => (string) $order->get_id(),
            'customer'    => [
                'email'      => $order->get_billing_email(),
                'first_name' => $order->get_billing_first_name(),
                'last_name'  => $order->get_billing_last_name(),
                'phone'      => $order->get_billing_phone(),
                'company'    => $order->get_billing_company(),
                'address'    => [
                    'address_1' => $order->get_billing_address_1(),
                    'address_2' => $order->get_billing_address_2(),
                    'city'      => $order->get_billing_city(),
                    'state'     => $order->get_billing_state(),
                    'postcode'  => $order->get_billing_postcode(),
                    'country'   => $order->get_billing_country(),
                ],
            ],
            'shipping'    => [
                'first_name' => $order->get_shipping_first_name(),
                'last_name'  => $order->get_shipping_last_name(),
                'company'    => $order->get_shipping_company(),
                'address_1'  => $order->get_shipping_address_1(),
                'address_2'  => $order->get_shipping_address_2(),
                'city'       => $order->get_shipping_city(),
                'state'      => $order->get_shipping_state(),
                'postcode'   => $order->get_shipping_postcode(),
                'country'    => $order->get_shipping_country(),
            ],
            'items'       => $items,
            'totals'      => [
                'subtotal'      => (float) $order->get_subtotal(),
                'shipping'      => (float) $order->get_shipping_total(),
                'shipping_tax'  => (float) $order->get_shipping_tax(),
                'discount'      => (float) $order->get_discount_total(),
                'discount_tax'  => (float) $order->get_discount_tax(),
                'tax'           => (float) $order->get_total_tax(),
                'total'         => (float) $order->get_total(),
            ],
            'payment'     => [
                'method'         => $order->get_payment_method(),
                'method_title'   => $order->get_payment_method_title(),
                'transaction_id' => $order->get_transaction_id(),
            ],
            'status'      => $order->get_status(),
            'currency'    => $order->get_currency(),
            'created_at'  => $order->get_date_created() ? $order->get_date_created()->format( 'c' ) : gmdate( 'c' ),
            'notes'       => $order->get_customer_note(),
        ];

        /**
         * Filter the ERP order payload before sending.
         *
         * @param array     $payload ERP order payload.
         * @param \WC_Order $order   WooCommerce order object.
         */
        return apply_filters( 'erp_order_payload', $payload, $order );
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
            $logger->log( $level, '[OrderSync] ' . $message, [ 'source' => 'erp-integration' ] );
        } else {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( sprintf( '[ERP OrderSync][%s] %s', strtoupper( $level ), $message ) );
        }
    }
}
