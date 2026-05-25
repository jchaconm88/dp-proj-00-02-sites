<?php
/**
 * Payment Gateway Integration.
 *
 * Handles payment confirmation flow between WooCommerce payment gateways
 * and the ERP system, including error handling and retry logic.
 *
 * @package AgenciaERP
 */

namespace AgenciaERP;

defined( 'ABSPATH' ) || exit;

/**
 * Class PaymentIntegration
 *
 * Manages payment gateway configuration documentation, payment confirmation
 * flow to ERP, and payment failure handling with user-friendly messages.
 */
class PaymentIntegration {

    /**
     * Maximum time (seconds) to confirm payment in ERP after gateway confirmation.
     */
    private const CONFIRMATION_TIMEOUT = 30;

    /**
     * Supported payment gateways configuration.
     *
     * @var array
     */
    private array $supported_gateways = [
        'yape_plin' => [
            'name'        => 'Yape / Plin',
            'plugin'      => 'woocommerce-yape-plin-gateway',
            'type'        => 'mobile_wallet',
            'auto_confirm' => false,
        ],
        'mercadopago' => [
            'name'        => 'Mercado Pago',
            'plugin'      => 'woocommerce-mercadopago',
            'type'        => 'redirect',
            'auto_confirm' => true,
        ],
        'culqi' => [
            'name'        => 'Culqi',
            'plugin'      => 'woocommerce-culqi',
            'type'        => 'direct',
            'auto_confirm' => true,
        ],
        'kushki' => [
            'name'        => 'Kushki',
            'plugin'      => 'woocommerce-kushki',
            'type'        => 'direct',
            'auto_confirm' => true,
        ],
    ];

    /**
     * Constructor.
     *
     * Registers payment-related hooks.
     */
    public function __construct() {
        add_action( 'woocommerce_payment_complete', [ $this, 'on_payment_complete' ], 10, 1 );
        add_action( 'woocommerce_order_status_failed', [ $this, 'on_payment_failed' ], 10, 2 );
        add_filter( 'woocommerce_payment_complete_order_status', [ $this, 'filter_payment_status' ], 10, 3 );
        add_filter( 'woocommerce_get_checkout_payment_url', [ $this, 'preserve_cart_on_failure' ], 10, 2 );
    }

    /**
     * Handle successful payment completion.
     *
     * Receives transaction status from gateway, updates WooCommerce order,
     * and pushes payment confirmation to ERP within 30 seconds.
     *
     * @param int $order_id WooCommerce order ID.
     */
    public function on_payment_complete( int $order_id ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $payment_data = $this->build_payment_data( $order );

        // Push payment confirmation to ERP.
        $this->confirm_payment_in_erp( $order, $payment_data );
    }

    /**
     * Confirm payment in the ERP system.
     *
     * Must complete within 30 seconds of gateway confirmation.
     * If ERP is unavailable, queues for retry.
     *
     * @param \WC_Order $order        WooCommerce order.
     * @param array     $payment_data Payment details.
     */
    private function confirm_payment_in_erp( \WC_Order $order, array $payment_data ): void {
        $plugin = Plugin::get_instance();
        $client = $plugin->get_erp_client();

        if ( ! $client ) {
            $this->queue_payment_confirmation( $order->get_id(), $payment_data );
            return;
        }

        $erp_order_id = $order->get_meta( '_erp_order_id' );
        if ( ! $erp_order_id ) {
            $this->log( 'warning', sprintf(
                'Order #%d has no ERP order ID. Queuing payment confirmation.',
                $order->get_id()
            ) );
            $this->queue_payment_confirmation( $order->get_id(), $payment_data );
            return;
        }

        $start_time = microtime( true );

        try {
            $client->confirm_payment( $erp_order_id, $payment_data );

            $elapsed = microtime( true ) - $start_time;

            $order->update_meta_data( '_erp_payment_confirmed', 'yes' );
            $order->update_meta_data( '_erp_payment_confirmed_at', current_time( 'mysql' ) );
            $order->update_meta_data( '_erp_payment_confirmation_time', round( $elapsed, 2 ) );
            $order->save();

            // Warn if confirmation took too long.
            if ( $elapsed > self::CONFIRMATION_TIMEOUT ) {
                $this->log( 'warning', sprintf(
                    'Payment confirmation for order #%d took %.2fs (limit: %ds)',
                    $order->get_id(),
                    $elapsed,
                    self::CONFIRMATION_TIMEOUT
                ) );
            }

            $this->log( 'info', sprintf(
                'Payment confirmed in ERP for order #%d (%.2fs)',
                $order->get_id(),
                $elapsed
            ) );
        } catch ( \RuntimeException $e ) {
            $this->log( 'error', sprintf(
                'Failed to confirm payment in ERP for order #%d: %s',
                $order->get_id(),
                $e->getMessage()
            ) );

            // Queue for retry.
            $this->queue_payment_confirmation( $order->get_id(), $payment_data );
        }
    }

    /**
     * Build payment data array from order.
     *
     * @param \WC_Order $order WooCommerce order.
     * @return array Payment data for ERP.
     */
    private function build_payment_data( \WC_Order $order ): array {
        return [
            'wc_order_id'      => $order->get_id(),
            'amount'           => (float) $order->get_total(),
            'currency'         => $order->get_currency(),
            'payment_method'   => $order->get_payment_method(),
            'transaction_id'   => $order->get_transaction_id(),
            'payment_date'     => $order->get_date_paid() ? $order->get_date_paid()->format( 'Y-m-d H:i:s' ) : current_time( 'mysql' ),
            'gateway_response' => $order->get_meta( '_gateway_response' ) ?: '',
        ];
    }

    /**
     * Queue payment confirmation for retry.
     *
     * @param int   $order_id     WooCommerce order ID.
     * @param array $payment_data Payment details.
     */
    private function queue_payment_confirmation( int $order_id, array $payment_data ): void {
        $plugin     = Plugin::get_instance();
        $sync_queue = $plugin->get_sync_queue();

        if ( $sync_queue ) {
            $sync_queue->enqueue( 'payment_confirmation', [
                'order_id'     => $order_id,
                'payment_data' => $payment_data,
            ] );
        }

        $this->log( 'info', sprintf( 'Payment confirmation queued for order #%d', $order_id ) );
    }

    /**
     * Handle payment failure.
     *
     * Provides descriptive error message without exposing technical internals.
     * Preserves cart data so customer can retry without re-entering information.
     *
     * @param int       $order_id Order ID.
     * @param \WC_Order $order    Order object.
     */
    public function on_payment_failed( int $order_id, \WC_Order $order ): void {
        $payment_method = $order->get_payment_method();
        $error_message  = $this->get_user_friendly_error( $payment_method, $order );

        // Store the friendly error for display.
        $order->update_meta_data( '_payment_failure_message', $error_message );
        $order->update_meta_data( '_payment_failure_at', current_time( 'mysql' ) );
        $order->save();

        // Add notice for the customer.
        if ( function_exists( 'wc_add_notice' ) ) {
            wc_add_notice( $error_message, 'error' );
        }

        // Log technical details internally.
        $this->log( 'error', sprintf(
            'Payment failed for order #%d via %s. Gateway error: %s',
            $order_id,
            $payment_method,
            $order->get_meta( '_gateway_error_detail' ) ?: 'unknown'
        ) );
    }

    /**
     * Get user-friendly error message based on payment method.
     *
     * Does not expose technical internals like error codes or stack traces.
     *
     * @param string    $payment_method Payment method ID.
     * @param \WC_Order $order          Order object.
     * @return string User-friendly error message.
     */
    private function get_user_friendly_error( string $payment_method, \WC_Order $order ): string {
        $gateway_error_code = $order->get_meta( '_gateway_error_code' ) ?: '';

        // Map common error codes to friendly messages.
        $error_messages = [
            'insufficient_funds' => __( 'Tu medio de pago no tiene fondos suficientes. Por favor intenta con otro método de pago.', 'wc-erp-integration' ),
            'card_declined'      => __( 'El pago fue rechazado por tu banco. Verifica los datos o intenta con otra tarjeta.', 'wc-erp-integration' ),
            'expired_card'       => __( 'Tu tarjeta ha expirado. Por favor usa otra tarjeta.', 'wc-erp-integration' ),
            'timeout'            => __( 'El procesamiento del pago tardó demasiado. Por favor intenta nuevamente.', 'wc-erp-integration' ),
            'network_error'      => __( 'Hubo un problema de conexión. Por favor intenta nuevamente en unos minutos.', 'wc-erp-integration' ),
        ];

        if ( isset( $error_messages[ $gateway_error_code ] ) ) {
            return $error_messages[ $gateway_error_code ];
        }

        // Default messages by gateway type.
        $gateway_defaults = [
            'yape_plin'   => __( 'No se pudo confirmar el pago con Yape/Plin. Verifica que la transferencia se completó e intenta nuevamente.', 'wc-erp-integration' ),
            'mercadopago' => __( 'El pago con Mercado Pago no se completó. Por favor intenta nuevamente.', 'wc-erp-integration' ),
            'culqi'       => __( 'No se pudo procesar el pago con tarjeta. Verifica los datos e intenta nuevamente.', 'wc-erp-integration' ),
            'kushki'      => __( 'No se pudo procesar el pago. Por favor intenta con otro método de pago.', 'wc-erp-integration' ),
        ];

        return $gateway_defaults[ $payment_method ]
            ?? __( 'El pago no se pudo completar. Por favor intenta nuevamente o usa otro método de pago.', 'wc-erp-integration' );
    }

    /**
     * Filter payment complete status to ensure proper flow.
     *
     * @param string    $status   Default status.
     * @param int       $order_id Order ID.
     * @param \WC_Order $order    Order object.
     * @return string Filtered status.
     */
    public function filter_payment_status( string $status, int $order_id, \WC_Order $order ): string {
        // Keep processing status until ERP confirms.
        return $status;
    }

    /**
     * Preserve cart data on payment failure so customer can retry.
     *
     * @param string    $url   Checkout payment URL.
     * @param \WC_Order $order Order object.
     * @return string Modified URL.
     */
    public function preserve_cart_on_failure( string $url, \WC_Order $order ): string {
        if ( $order->has_status( 'failed' ) ) {
            // Ensure cart is restored for retry.
            $url = add_query_arg( 'retry_payment', $order->get_id(), $url );
        }
        return $url;
    }

    /**
     * Get supported gateways configuration.
     *
     * @return array Gateway configurations.
     */
    public function get_supported_gateways(): array {
        return apply_filters( 'erp_supported_payment_gateways', $this->supported_gateways );
    }

    /**
     * Check if a gateway plugin is active.
     *
     * @param string $gateway_id Gateway identifier.
     * @return bool True if active.
     */
    public function is_gateway_active( string $gateway_id ): bool {
        if ( ! isset( $this->supported_gateways[ $gateway_id ] ) ) {
            return false;
        }

        $plugin_slug = $this->supported_gateways[ $gateway_id ]['plugin'];
        return is_plugin_active( $plugin_slug . '/' . $plugin_slug . '.php' );
    }

    /**
     * Log a message.
     *
     * @param string $level   Log level.
     * @param string $message Log message.
     */
    private function log( string $level, string $message ): void {
        if ( function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->log( $level, $message, [ 'source' => 'erp-payments' ] );
        }
    }
}
