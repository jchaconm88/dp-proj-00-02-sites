<?php
/**
 * Conflict Resolver Implementation.
 *
 * Resolves data conflicts between WooCommerce and the ERP
 * using predefined strategies per entity type.
 *
 * @package AgenciaERP
 */

namespace AgenciaERP;

defined( 'ABSPATH' ) || exit;

/**
 * Class ConflictResolver
 *
 * Implements conflict resolution strategies:
 * - Stock: ERP always wins (ERP is source of truth for inventory).
 * - Customer: Merge strategy (WC may have newer contact data).
 * - Order status: ERP wins except for payment status from WC.
 */
class ConflictResolver {

    /**
     * WooCommerce order statuses that represent payment confirmation.
     * These statuses originate from WC payment gateways and take precedence.
     *
     * @var string[]
     */
    private const PAYMENT_STATUSES = [
        'wc-completed',
        'wc-processing',
        'wc-refunded',
    ];

    /**
     * Resolve a stock level conflict between WooCommerce and ERP.
     *
     * Strategy: ERP always wins. The ERP is the authoritative source
     * for inventory data across all sales channels.
     *
     * @param int    $wc_stock  Current WooCommerce stock level.
     * @param int    $erp_stock ERP reported stock level.
     * @param string $sku       Product SKU for logging.
     * @return int Resolved stock level (always ERP value).
     */
    public function resolve_stock_conflict( int $wc_stock, int $erp_stock, string $sku ): int {
        if ( $wc_stock !== $erp_stock ) {
            $this->log(
                'info',
                sprintf(
                    'Stock conflict for SKU %s: WC=%d, ERP=%d. Resolved to ERP value.',
                    $sku,
                    $wc_stock,
                    $erp_stock
                )
            );
        }

        return $erp_stock;
    }

    /**
     * Resolve a customer data conflict between WooCommerce and ERP.
     *
     * Strategy: Merge. ERP provides the base data, but WooCommerce
     * may have more recent contact information (email, phone, address)
     * since customers update their profiles on the storefront.
     *
     * Merge rules:
     * - Core identity fields (ID, tax ID, business name): ERP wins.
     * - Contact fields (email, phone): Use most recently updated.
     * - Address fields: Use most recently updated.
     * - Metadata/preferences: WC wins (storefront-specific data).
     *
     * @param array $wc_data  WooCommerce customer data.
     * @param array $erp_data ERP customer data.
     * @return array Merged customer data.
     */
    public function resolve_customer_conflict( array $wc_data, array $erp_data ): array {
        $merged = $erp_data;

        // Identity fields: ERP always wins.
        $erp_identity_fields = [ 'erp_customer_id', 'tax_id', 'ruc', 'dni', 'business_name', 'credit_limit' ];
        foreach ( $erp_identity_fields as $field ) {
            if ( isset( $erp_data[ $field ] ) ) {
                $merged[ $field ] = $erp_data[ $field ];
            }
        }

        // Contact fields: Use most recently updated source.
        $contact_fields = [ 'email', 'phone', 'mobile' ];
        $wc_updated     = strtotime( $wc_data['updated_at'] ?? '1970-01-01' );
        $erp_updated    = strtotime( $erp_data['updated_at'] ?? '1970-01-01' );

        foreach ( $contact_fields as $field ) {
            if ( $wc_updated > $erp_updated && ! empty( $wc_data[ $field ] ) ) {
                $merged[ $field ] = $wc_data[ $field ];
            } elseif ( ! empty( $erp_data[ $field ] ) ) {
                $merged[ $field ] = $erp_data[ $field ];
            }
        }

        // Address fields: Use most recently updated source.
        $address_fields = [
            'billing_address_1',
            'billing_address_2',
            'billing_city',
            'billing_state',
            'billing_postcode',
            'billing_country',
            'shipping_address_1',
            'shipping_address_2',
            'shipping_city',
            'shipping_state',
            'shipping_postcode',
            'shipping_country',
        ];

        foreach ( $address_fields as $field ) {
            if ( $wc_updated > $erp_updated && ! empty( $wc_data[ $field ] ) ) {
                $merged[ $field ] = $wc_data[ $field ];
            } elseif ( ! empty( $erp_data[ $field ] ) ) {
                $merged[ $field ] = $erp_data[ $field ];
            }
        }

        // Storefront-specific metadata: WC wins.
        $wc_meta_fields = [ 'marketing_consent', 'preferred_language', 'newsletter_subscribed', 'account_notes' ];
        foreach ( $wc_meta_fields as $field ) {
            if ( isset( $wc_data[ $field ] ) ) {
                $merged[ $field ] = $wc_data[ $field ];
            }
        }

        // Set the most recent updated_at timestamp.
        $merged['updated_at'] = gmdate( 'Y-m-d\TH:i:s\Z', max( $wc_updated, $erp_updated ) );

        $this->log(
            'debug',
            sprintf(
                'Customer conflict resolved via merge. WC updated: %s, ERP updated: %s.',
                $wc_data['updated_at'] ?? 'unknown',
                $erp_data['updated_at'] ?? 'unknown'
            )
        );

        return $merged;
    }

    /**
     * Resolve an order status conflict between WooCommerce and ERP.
     *
     * Strategy: ERP wins for all status transitions EXCEPT payment
     * confirmations which originate from WooCommerce payment gateways.
     *
     * Payment-related statuses (processing, completed, refunded) from WC
     * take precedence because WC is the authoritative source for payment state.
     *
     * @param string $wc_status  Current WooCommerce order status (with wc- prefix).
     * @param string $erp_status ERP reported order status.
     * @param string $order_id   Order identifier for logging.
     * @return string Resolved order status.
     */
    public function resolve_order_conflict( string $wc_status, string $erp_status, string $order_id ): string {
        // Normalize WC status (ensure wc- prefix for comparison).
        $normalized_wc_status = $this->normalize_wc_status( $wc_status );

        // If WC status is a payment-related status, WC wins.
        if ( in_array( $normalized_wc_status, self::PAYMENT_STATUSES, true ) ) {
            $this->log(
                'info',
                sprintf(
                    'Order %s status conflict: WC=%s (payment status), ERP=%s. WC wins.',
                    $order_id,
                    $wc_status,
                    $erp_status
                )
            );
            return $wc_status;
        }

        // For all other statuses, ERP wins.
        if ( $wc_status !== $erp_status ) {
            $this->log(
                'info',
                sprintf(
                    'Order %s status conflict: WC=%s, ERP=%s. ERP wins.',
                    $order_id,
                    $wc_status,
                    $erp_status
                )
            );
        }

        return $erp_status;
    }

    /**
     * Normalize a WooCommerce status to include the wc- prefix.
     *
     * @param string $status Status string, with or without prefix.
     * @return string Status with wc- prefix.
     */
    private function normalize_wc_status( string $status ): string {
        if ( str_starts_with( $status, 'wc-' ) ) {
            return $status;
        }
        return 'wc-' . $status;
    }

    /**
     * Log a message.
     *
     * @param string $level   Log level.
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
            $logger->log( $level, '[ConflictResolver] ' . $message, [ 'source' => 'erp-integration' ] );
        } else {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( sprintf( '[ERP ConflictResolver][%s] %s', strtoupper( $level ), $message ) );
        }
    }
}
