<?php
/**
 * ERP Shipping Method.
 *
 * Custom WooCommerce shipping method that retrieves rates from the ERP
 * and provides fallback rates when the ERP is unavailable.
 *
 * @package AgenciaERP
 */

namespace AgenciaERP;

defined( 'ABSPATH' ) || exit;

/**
 * Class ERPShippingMethod
 *
 * Extends WC_Shipping_Method to provide ERP-based shipping rates
 * with carrier selection, fallback zones, and oversized package handling.
 */
class ERPShippingMethod extends \WC_Shipping_Method {

    /**
     * ERP request timeout in seconds.
     */
    private const ERP_TIMEOUT = 8;

    /**
     * Maximum weight (kg) before requiring special quote.
     */
    private const OVERSIZED_WEIGHT_LIMIT = 30;

    /**
     * Maximum single dimension (cm) before requiring special quote.
     */
    private const OVERSIZED_DIMENSION_LIMIT = 150;

    /**
     * Constructor.
     *
     * @param int $instance_id Shipping method instance ID.
     */
    public function __construct( int $instance_id = 0 ) {
        $this->id                 = 'erp_shipping';
        $this->instance_id        = absint( $instance_id );
        $this->method_title       = __( 'Envío ERP', 'wc-erp-integration' );
        $this->method_description = __( 'Tarifas de envío calculadas desde el ERP con soporte de múltiples transportistas.', 'wc-erp-integration' );
        $this->supports           = [
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        ];

        $this->init();
    }

    /**
     * Initialize settings and form fields.
     */
    private function init(): void {
        $this->init_form_fields();
        $this->init_settings();

        $this->title   = $this->get_option( 'title', __( 'Envío estándar', 'wc-erp-integration' ) );
        $this->enabled = $this->get_option( 'enabled', 'yes' );

        add_action(
            'woocommerce_update_options_shipping_' . $this->id,
            [ $this, 'process_admin_options' ]
        );
    }

    /**
     * Define settings form fields.
     */
    public function init_form_fields(): void {
        $this->instance_form_fields = [
            'title'              => [
                'title'   => __( 'Título', 'wc-erp-integration' ),
                'type'    => 'text',
                'default' => __( 'Envío estándar', 'wc-erp-integration' ),
            ],
            'fallback_enabled'   => [
                'title'   => __( 'Habilitar tarifas de respaldo', 'wc-erp-integration' ),
                'type'    => 'checkbox',
                'default' => 'yes',
                'desc'    => __( 'Usar tarifas predefinidas cuando el ERP no responde.', 'wc-erp-integration' ),
            ],
            'fallback_rates'     => [
                'title'   => __( 'Tarifas de respaldo por zona', 'wc-erp-integration' ),
                'type'    => 'textarea',
                'default' => "lima:10.00:2-3\nprovincia:25.00:5-7\nselva:45.00:7-10",
                'desc'    => __( 'Formato: zona:precio:días_estimados (una por línea).', 'wc-erp-integration' ),
            ],
            'preferred_carrier'  => [
                'title'   => __( 'Transportista preferido', 'wc-erp-integration' ),
                'type'    => 'select',
                'default' => 'auto',
                'options' => [
                    'auto'    => __( 'Automático (mejor tarifa)', 'wc-erp-integration' ),
                    'olva'    => 'Olva Courier',
                    'shalom'  => 'Shalom',
                    'cruz'    => 'Cruz del Sur Cargo',
                    'rappi'   => 'Rappi',
                ],
            ],
            'oversized_message'  => [
                'title'   => __( 'Mensaje paquete sobredimensionado', 'wc-erp-integration' ),
                'type'    => 'text',
                'default' => __( 'Requiere cotización especial. Nos contactaremos contigo.', 'wc-erp-integration' ),
            ],
        ];
    }

    /**
     * Calculate shipping rates.
     *
     * Calls the ERP for live rates. Falls back to zone-based rates
     * if the ERP times out (> 8 seconds) or returns an error.
     *
     * @param array $package Shipping package data.
     */
    public function calculate_shipping( $package = [] ): void {
        $destination = $package['destination'] ?? [];
        $contents    = $package['contents'] ?? [];

        // Calculate total weight and dimensions.
        $weight    = $this->calculate_total_weight( $contents );
        $dimensions = $this->calculate_max_dimensions( $contents );

        // Check for oversized packages.
        if ( $this->is_oversized( $weight, $dimensions ) ) {
            $this->add_rate( [
                'id'    => $this->get_rate_id( 'oversized' ),
                'label' => __( 'Requiere cotización especial', 'wc-erp-integration' ),
                'cost'  => 0,
                'meta_data' => [
                    'oversized'   => true,
                    'description' => $this->get_option( 'oversized_message' ),
                ],
            ] );
            return;
        }

        // Attempt to get rates from ERP.
        $erp_rates = $this->get_erp_rates( $destination, $weight, $dimensions );

        if ( ! empty( $erp_rates ) ) {
            $this->add_erp_rates( $erp_rates, $destination );
            return;
        }

        // Fallback to zone-based rates.
        if ( 'yes' === $this->get_option( 'fallback_enabled', 'yes' ) ) {
            $this->add_fallback_rates( $destination );
        }
    }

    /**
     * Get shipping rates from the ERP.
     *
     * @param array $destination Destination address data.
     * @param float $weight      Total weight in kg.
     * @param array $dimensions  Max dimensions [length, width, height] in cm.
     * @return array ERP rate options or empty array on failure.
     */
    private function get_erp_rates( array $destination, float $weight, array $dimensions ): array {
        $plugin = Plugin::get_instance();
        $client = $plugin->get_erp_client();

        if ( ! $client ) {
            return [];
        }

        $origin = [
            'city'     => get_option( 'woocommerce_store_city', '' ),
            'state'    => get_option( 'woocommerce_default_country', '' ),
            'postcode' => get_option( 'woocommerce_store_postcode', '' ),
        ];

        $request = [
            'origin'      => $origin,
            'destination' => [
                'city'     => $destination['city'] ?? '',
                'state'    => $destination['state'] ?? '',
                'postcode' => $destination['postcode'] ?? '',
                'country'  => $destination['country'] ?? 'PE',
            ],
            'weight'      => $weight,
            'dimensions'  => [
                'length' => $dimensions['length'] ?? 0,
                'width'  => $dimensions['width'] ?? 0,
                'height' => $dimensions['height'] ?? 0,
            ],
        ];

        $start_time = microtime( true );

        try {
            $rates = $client->get_shipping_rates( $request );
            $elapsed = microtime( true ) - $start_time;

            // If ERP took longer than timeout, log warning.
            if ( $elapsed > self::ERP_TIMEOUT ) {
                $this->log_warning(
                    sprintf( 'ERP shipping rates response took %.2fs (timeout: %ds)', $elapsed, self::ERP_TIMEOUT )
                );
                return [];
            }

            return $rates;
        } catch ( \RuntimeException $e ) {
            $this->log_warning( 'ERP shipping rates unavailable: ' . $e->getMessage() );
            return [];
        }
    }

    /**
     * Add ERP rates to the shipping method, applying carrier selection rules.
     *
     * @param array $erp_rates   Rates from ERP.
     * @param array $destination Destination data.
     */
    private function add_erp_rates( array $erp_rates, array $destination ): void {
        $preferred_carrier = $this->get_option( 'preferred_carrier', 'auto' );

        // Sort by price ascending.
        usort( $erp_rates, function ( $a, $b ) {
            return ( $a['price'] ?? 0 ) <=> ( $b['price'] ?? 0 );
        } );

        foreach ( $erp_rates as $rate ) {
            $carrier_id   = $rate['carrier_id'] ?? 'unknown';
            $carrier_name = $rate['carrier_name'] ?? __( 'Transportista', 'wc-erp-integration' );
            $service      = $rate['service'] ?? '';
            $price        = (float) ( $rate['price'] ?? 0 );
            $days         = $rate['estimated_days'] ?? '';

            // Apply carrier preference filter.
            if ( 'auto' !== $preferred_carrier && $carrier_id !== $preferred_carrier ) {
                continue;
            }

            $label = sprintf( '%s - %s', $carrier_name, $service );
            if ( $days ) {
                $label .= sprintf( ' (%s días)', $days );
            }

            $this->add_rate( [
                'id'        => $this->get_rate_id( $carrier_id . '_' . sanitize_title( $service ) ),
                'label'     => $label,
                'cost'      => $price,
                'meta_data' => [
                    'carrier_id'     => $carrier_id,
                    'carrier_name'   => $carrier_name,
                    'service'        => $service,
                    'estimated_days' => $days,
                ],
            ] );
        }
    }

    /**
     * Add fallback rates based on shipping zone configuration.
     *
     * @param array $destination Destination address data.
     */
    private function add_fallback_rates( array $destination ): void {
        $fallback_config = $this->get_option( 'fallback_rates', '' );
        $zone            = $this->determine_zone( $destination );
        $lines           = array_filter( explode( "\n", $fallback_config ) );

        foreach ( $lines as $line ) {
            $parts = array_map( 'trim', explode( ':', $line ) );

            if ( count( $parts ) < 3 ) {
                continue;
            }

            list( $zone_name, $price, $days ) = $parts;

            if ( $zone_name === $zone || 'default' === $zone_name ) {
                $this->add_rate( [
                    'id'    => $this->get_rate_id( 'fallback_' . sanitize_title( $zone_name ) ),
                    'label' => sprintf(
                        /* translators: 1: zone name, 2: estimated days */
                        __( 'Envío %1$s (%2$s días hábiles)', 'wc-erp-integration' ),
                        ucfirst( $zone_name ),
                        $days
                    ),
                    'cost'      => (float) $price,
                    'meta_data' => [
                        'fallback'       => true,
                        'zone'           => $zone_name,
                        'estimated_days' => $days,
                    ],
                ] );
                return;
            }
        }

        // If no zone matched, use first rate as default.
        if ( ! empty( $lines ) ) {
            $parts = array_map( 'trim', explode( ':', $lines[0] ) );
            if ( count( $parts ) >= 3 ) {
                $this->add_rate( [
                    'id'    => $this->get_rate_id( 'fallback_default' ),
                    'label' => sprintf( __( 'Envío estándar (%s días hábiles)', 'wc-erp-integration' ), $parts[2] ),
                    'cost'  => (float) $parts[1],
                    'meta_data' => [ 'fallback' => true ],
                ] );
            }
        }
    }

    /**
     * Handle shipment_created webhook from ERP.
     *
     * Stores tracking number, updates order status, and sends notification.
     *
     * @param array $payload Webhook payload.
     */
    public static function handle_shipment_created( array $payload ): void {
        $order_id        = $payload['wc_order_id'] ?? 0;
        $tracking_number = $payload['tracking_number'] ?? '';
        $carrier         = $payload['carrier'] ?? '';

        if ( ! $order_id || ! $tracking_number ) {
            self::log_static( 'error', 'shipment_created webhook missing required fields.' );
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            self::log_static( 'error', sprintf( 'Order #%d not found for shipment webhook.', $order_id ) );
            return;
        }

        // Store tracking information.
        $order->update_meta_data( '_erp_tracking_number', sanitize_text_field( $tracking_number ) );
        $order->update_meta_data( '_erp_shipping_carrier', sanitize_text_field( $carrier ) );
        $order->update_meta_data( '_erp_shipment_created_at', current_time( 'mysql' ) );

        // Update order status to shipped.
        $order->set_status( 'wc-shipped', sprintf(
            /* translators: 1: carrier, 2: tracking number */
            __( 'Enviado vía %1$s. Tracking: %2$s', 'wc-erp-integration' ),
            $carrier,
            $tracking_number
        ) );
        $order->save();

        // Send notification to customer.
        self::send_shipment_notification( $order, $tracking_number, $carrier );

        self::log_static( 'info', sprintf( 'Shipment created for order #%d. Tracking: %s', $order_id, $tracking_number ) );
    }

    /**
     * Handle shipment_updated webhook from ERP.
     *
     * Updates tracking status and notifies customer of delivery.
     *
     * @param array $payload Webhook payload.
     */
    public static function handle_shipment_updated( array $payload ): void {
        $order_id = $payload['wc_order_id'] ?? 0;
        $status   = $payload['shipment_status'] ?? '';
        $details  = $payload['details'] ?? '';

        if ( ! $order_id ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Update shipment meta.
        $order->update_meta_data( '_erp_shipment_status', sanitize_text_field( $status ) );
        $order->update_meta_data( '_erp_shipment_last_update', current_time( 'mysql' ) );

        if ( $details ) {
            $order->update_meta_data( '_erp_shipment_details', sanitize_text_field( $details ) );
        }

        // If delivered, update order status.
        if ( 'delivered' === $status ) {
            $order->set_status( 'completed', __( 'Pedido entregado al cliente.', 'wc-erp-integration' ) );
        }

        $order->save();

        // Send update notification.
        self::send_shipment_notification( $order, '', '', $status );

        self::log_static( 'info', sprintf( 'Shipment updated for order #%d. Status: %s', $order_id, $status ) );
    }

    /**
     * Send shipment notification email to customer.
     *
     * @param \WC_Order $order           Order object.
     * @param string    $tracking_number Tracking number.
     * @param string    $carrier         Carrier name.
     * @param string    $status          Shipment status.
     */
    private static function send_shipment_notification( \WC_Order $order, string $tracking_number = '', string $carrier = '', string $status = '' ): void {
        $customer_email = $order->get_billing_email();
        if ( ! $customer_email ) {
            return;
        }

        if ( $tracking_number ) {
            $subject = sprintf(
                /* translators: %s: order number */
                __( 'Tu pedido #%s ha sido enviado', 'wc-erp-integration' ),
                $order->get_order_number()
            );
            $message = sprintf(
                /* translators: 1: carrier, 2: tracking number */
                __( "Tu pedido ha sido enviado vía %1\$s.\n\nNúmero de seguimiento: %2\$s\n\nGracias por tu compra.", 'wc-erp-integration' ),
                $carrier,
                $tracking_number
            );
        } else {
            $subject = sprintf(
                /* translators: %s: order number */
                __( 'Actualización de envío - Pedido #%s', 'wc-erp-integration' ),
                $order->get_order_number()
            );
            $message = sprintf(
                /* translators: %s: shipment status */
                __( "Estado de tu envío: %s\n\nGracias por tu compra.", 'wc-erp-integration' ),
                $status
            );
        }

        wp_mail( $customer_email, $subject, $message );
    }

    /**
     * Determine the shipping zone based on destination.
     *
     * @param array $destination Destination address data.
     * @return string Zone identifier.
     */
    private function determine_zone( array $destination ): string {
        $state = strtolower( $destination['state'] ?? '' );
        $city  = strtolower( $destination['city'] ?? '' );

        // Lima metropolitan area.
        $lima_districts = [ 'lima', 'callao', 'lim', 'cal' ];
        if ( in_array( $state, $lima_districts, true ) || str_contains( $city, 'lima' ) ) {
            return 'lima';
        }

        // Selva region.
        $selva_states = [ 'loreto', 'ucayali', 'madre de dios', 'san martin', 'amazonas' ];
        if ( in_array( $state, $selva_states, true ) ) {
            return 'selva';
        }

        // Default to provincia.
        return 'provincia';
    }

    /**
     * Calculate total weight of package contents.
     *
     * @param array $contents Package contents.
     * @return float Total weight in kg.
     */
    private function calculate_total_weight( array $contents ): float {
        $weight = 0.0;

        foreach ( $contents as $item ) {
            $product = $item['data'] ?? null;
            if ( $product && method_exists( $product, 'get_weight' ) ) {
                $item_weight = (float) $product->get_weight();
                $weight     += $item_weight * ( $item['quantity'] ?? 1 );
            }
        }

        // Convert to kg if store uses grams.
        $unit = get_option( 'woocommerce_weight_unit', 'kg' );
        if ( 'g' === $unit ) {
            $weight /= 1000;
        }

        return $weight;
    }

    /**
     * Calculate maximum dimensions from package contents.
     *
     * @param array $contents Package contents.
     * @return array Dimensions [length, width, height] in cm.
     */
    private function calculate_max_dimensions( array $contents ): array {
        $max_length = 0;
        $max_width  = 0;
        $max_height = 0;

        foreach ( $contents as $item ) {
            $product = $item['data'] ?? null;
            if ( $product && method_exists( $product, 'get_length' ) ) {
                $max_length = max( $max_length, (float) $product->get_length() );
                $max_width  = max( $max_width, (float) $product->get_width() );
                $max_height = max( $max_height, (float) $product->get_height() );
            }
        }

        // Convert to cm if store uses mm.
        $unit = get_option( 'woocommerce_dimension_unit', 'cm' );
        if ( 'mm' === $unit ) {
            $max_length /= 10;
            $max_width  /= 10;
            $max_height /= 10;
        }

        return [
            'length' => $max_length,
            'width'  => $max_width,
            'height' => $max_height,
        ];
    }

    /**
     * Check if package is oversized.
     *
     * @param float $weight     Total weight in kg.
     * @param array $dimensions Dimensions in cm.
     * @return bool True if oversized.
     */
    private function is_oversized( float $weight, array $dimensions ): bool {
        if ( $weight > self::OVERSIZED_WEIGHT_LIMIT ) {
            return true;
        }

        $max_dim = max(
            $dimensions['length'] ?? 0,
            $dimensions['width'] ?? 0,
            $dimensions['height'] ?? 0
        );

        return $max_dim > self::OVERSIZED_DIMENSION_LIMIT;
    }

    /**
     * Log a warning message.
     *
     * @param string $message Warning message.
     */
    private function log_warning( string $message ): void {
        if ( function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->warning( $message, [ 'source' => 'erp-shipping' ] );
        }
    }

    /**
     * Static logging helper.
     *
     * @param string $level   Log level.
     * @param string $message Log message.
     */
    private static function log_static( string $level, string $message ): void {
        if ( function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->log( $level, $message, [ 'source' => 'erp-shipping' ] );
        }
    }
}
