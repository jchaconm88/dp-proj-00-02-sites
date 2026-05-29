<?php
/**
 * Product, Stock, and Price Sync Service.
 *
 * Handles synchronization of products, stock levels, and prices
 * between the ERP system and WooCommerce.
 *
 * @package AgenciaERP
 */

namespace AgenciaERP;

defined( 'ABSPATH' ) || exit;

/**
 * Class SyncService
 *
 * Manages delta product sync, stock updates (webhook + polling),
 * and price updates (webhook + polling) from ERP to WooCommerce.
 */
class SyncService {

    /**
     * Option key for last product sync timestamp.
     */
    private const OPTION_LAST_PRODUCT_SYNC = 'erp_last_product_sync';

    /**
     * Option key for last stock sync timestamp.
     */
    private const OPTION_LAST_STOCK_SYNC = 'erp_last_stock_sync';

    /**
     * Option key for last price sync timestamp.
     */
    private const OPTION_LAST_PRICE_SYNC = 'erp_last_price_sync';

    /**
     * Default product sync interval in seconds (1 hour).
     */
    private const DEFAULT_PRODUCT_SYNC_INTERVAL = 3600;

    /**
     * Default stock polling interval in seconds (5 minutes).
     */
    private const DEFAULT_STOCK_POLL_INTERVAL = 300;

    /**
     * Default price polling interval in seconds (30 minutes).
     */
    private const DEFAULT_PRICE_POLL_INTERVAL = 1800;

    /**
     * Stock threshold to reactivate a product.
     */
    private const REACTIVATION_THRESHOLD = 1;

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
     * Initialize hooks (sin cron — event-driven via webhooks).
     *
     * Los métodos syncProducts, syncStock, syncPrices se invocan
     * desde WebhookHandler (webhooks entrantes ERP) o manualmente
     * desde Admin. No hay acciones de cron.
     */
    public function init(): void {
        // Sin acciones de cron — event-driven.
    }

    /**
     * Register custom cron schedules for sync intervals.
     *
     * @param array $schedules Existing cron schedules.
     * @return array Modified schedules.
     */
    public function register_cron_schedules( array $schedules ): array {
        if ( ! isset( $schedules['five_minutes'] ) ) {
            $schedules['five_minutes'] = [
                'interval' => self::DEFAULT_STOCK_POLL_INTERVAL,
                'display'  => __( 'Every 5 Minutes', 'wc-erp-integration' ),
            ];
        }

        if ( ! isset( $schedules['thirty_minutes'] ) ) {
            $schedules['thirty_minutes'] = [
                'interval' => self::DEFAULT_PRICE_POLL_INTERVAL,
                'display'  => __( 'Every 30 Minutes', 'wc-erp-integration' ),
            ];
        }

        return $schedules;
    }

    /**
     * Sync products from ERP to WooCommerce.
     *
     * Performs delta sync based on updated_at timestamp. On full sync,
     * fetches all products. Transforms ERP format to WC format including
     * variable products with N variations.
     *
     * @param bool $full_sync Whether to perform a full sync (ignore last sync time).
     * @return array{created: int, updated: int, errors: int} Sync results.
     */
    public function syncProducts( bool $full_sync = false ): array {
        $results = [
            'created'     => 0,
            'updated'     => 0,
            'errors'      => 0,
            'skipped'     => 0,
            'fetched'     => 0,
            'fetch_error' => '',
            'last_error'  => '',
        ];

        $since = null;
        if ( ! $full_sync ) {
            $last_sync = get_option( self::OPTION_LAST_PRODUCT_SYNC, '' );
            if ( $last_sync ) {
                $since = $last_sync;
            }
        }

        try {
            $erp_products = $this->erp_client->get_products( [], $since );
        } catch ( \Exception $e ) {
            $message = $e->getMessage();
            $this->log( 'error', sprintf( 'Product sync failed to fetch from ERP: %s', $message ) );
            $results['fetch_error'] = $message;
            return $results;
        }

        $results['fetched'] = count( $erp_products );

        if ( empty( $erp_products ) ) {
            $this->log( 'info', 'Product sync: ERP returned 0 products.' );
            update_option( self::OPTION_LAST_PRODUCT_SYNC, gmdate( 'c' ) );
            return $results;
        }

        foreach ( $erp_products as $erp_product ) {
            $erp_product = $this->normalize_erp_api_product( $erp_product );
            $sku         = trim( (string) ( $erp_product['sku'] ?? '' ) );
            if ( '' === $sku ) {
                $results['skipped']++;
                $this->log(
                    'warning',
                    sprintf(
                        'Skipping product "%s": SKU is required for WooCommerce sync.',
                        $erp_product['name'] ?? 'unknown'
                    )
                );
                continue;
            }

            try {
                $result = $this->sync_single_product( $erp_product );
                if ( 'created' === $result ) {
                    $results['created']++;
                } else {
                    $results['updated']++;
                }
            } catch ( \Exception $e ) {
                $results['errors']++;
                $results['last_error'] = $e->getMessage();
                $this->log(
                    'error',
                    sprintf(
                        'Failed to sync product SKU %s: %s',
                        $erp_product['sku'] ?? 'unknown',
                        $e->getMessage()
                    )
                );
            }
        }

        update_option( self::OPTION_LAST_PRODUCT_SYNC, gmdate( 'c' ) );

        $this->log(
            'info',
            sprintf(
                'Product sync completed: %d created, %d updated, %d errors, %d skipped.',
                $results['created'],
                $results['updated'],
                $results['errors'],
                $results['skipped']
            )
        );

        return $results;
    }

    /**
     * Map ERP API v1 payload (snake_case, variants) to plugin-internal shape.
     *
     * @param array $erp_product Raw product from GET /api/v1/products.
     * @return array Normalized product.
     */
    private function normalize_erp_api_product( array $erp_product ): array {
        $normalized = $erp_product;

        if ( ! empty( $erp_product['ecommerce_status'] ) && empty( $erp_product['status'] ) ) {
            $normalized['status'] = $erp_product['ecommerce_status'];
        }

        if ( ! empty( $erp_product['category_path'] ) && empty( $erp_product['categories'] ) ) {
            $normalized['categories'] = $erp_product['category_path'];
        }

        if ( isset( $erp_product['sale_price'] ) && ! isset( $erp_product['regular_price'] ) && ! isset( $erp_product['price'] ) ) {
            $normalized['regular_price'] = $erp_product['sale_price'];
        }

        if ( isset( $erp_product['sale_price_promo'] ) && $erp_product['sale_price_promo'] !== null ) {
            $normalized['sale_price'] = $erp_product['sale_price_promo'];
        }

        if ( ! empty( $erp_product['variants'] ) && empty( $erp_product['variations'] ) ) {
            $normalized['variations'] = array_map( [ $this, 'normalize_erp_api_variation' ], $erp_product['variants'] );
        }

        if ( ! empty( $erp_product['attribute_definitions'] ) && empty( $erp_product['attributeDefinitions'] ) ) {
            $normalized['attributeDefinitions'] = $erp_product['attribute_definitions'];
        }

        if ( ! empty( $erp_product['attribute_labels'] ) && empty( $erp_product['attributeLabels'] ) ) {
            $normalized['attribute_labels'] = $erp_product['attribute_labels'];
        }

        return $normalized;
    }

    /**
     * @param array $variation Raw variant from API v1.
     * @return array Normalized variation.
     */
    private function normalize_erp_api_variation( array $variation ): array {
        $normalized = $variation;

        if ( isset( $variation['sale_price'] ) ) {
            $normalized['regular_price'] = $variation['sale_price'];
        }
        if ( isset( $variation['sale_price_promo'] ) && $variation['sale_price_promo'] !== null ) {
            $normalized['sale_price'] = $variation['sale_price_promo'];
        }
        if ( isset( $variation['weight_kg'] ) ) {
            $normalized['weight'] = (string) $variation['weight_kg'];
        }

        if ( ! empty( $variation['attributes'] ) && is_array( $variation['attributes'] ) ) {
            $normalized['attributes'] = $variation['attributes'];
        }

        return $normalized;
    }

    /**
     * Sync stock levels from ERP to WooCommerce.
     *
     * Handles stock_updated webhook data or polls ERP for current levels.
     * Maps SKU to Product ID, updates stock_quantity per variation,
     * marks out-of-stock if 0, reactivates if above threshold.
     *
     * @param array|null $webhook_data Optional webhook payload with stock updates.
     * @return array{updated: int, errors: int} Sync results.
     */
    public function syncStock( ?array $webhook_data = null ): array {
        $results = [
            'updated' => 0,
            'errors'  => 0,
        ];

        try {
            if ( $webhook_data && ! empty( $webhook_data['items'] ) ) {
                $stock_levels = $webhook_data['items'];
            } else {
                // Polling fallback: fetch all stock levels from ERP.
                $stock_levels = $this->erp_client->get_stock_levels();
            }
        } catch ( \Exception $e ) {
            $this->log( 'error', sprintf( 'Stock sync failed to fetch from ERP: %s', $e->getMessage() ) );
            return $results;
        }

        if ( empty( $stock_levels ) ) {
            $this->log( 'info', 'Stock sync: no stock data received.' );
            update_option( self::OPTION_LAST_STOCK_SYNC, gmdate( 'c' ) );
            return $results;
        }

        foreach ( $stock_levels as $sku => $stock_data ) {
            try {
                $this->update_product_stock( $sku, $stock_data );
                $results['updated']++;
            } catch ( \Exception $e ) {
                $results['errors']++;
                $this->log(
                    'error',
                    sprintf( 'Failed to update stock for SKU %s: %s', $sku, $e->getMessage() )
                );
            }
        }

        update_option( self::OPTION_LAST_STOCK_SYNC, gmdate( 'c' ) );

        $this->log(
            'info',
            sprintf( 'Stock sync completed: %d updated, %d errors.', $results['updated'], $results['errors'] )
        );

        return $results;
    }

    /**
     * Sync prices from ERP to WooCommerce.
     *
     * Handles price_updated webhook data or polls ERP for current prices.
     * Updates regular_price and sale_price per SKU.
     *
     * @param array|null $webhook_data Optional webhook payload with price updates.
     * @return array{updated: int, errors: int} Sync results.
     */
    public function syncPrices( ?array $webhook_data = null ): array {
        $results = [
            'updated' => 0,
            'errors'  => 0,
        ];

        try {
            if ( $webhook_data && ! empty( $webhook_data['items'] ) ) {
                $price_data = $webhook_data['items'];
            } else {
                // Polling fallback: fetch all prices from ERP.
                $price_data = $this->erp_client->get_prices();
            }
        } catch ( \Exception $e ) {
            $this->log( 'error', sprintf( 'Price sync failed to fetch from ERP: %s', $e->getMessage() ) );
            return $results;
        }

        if ( empty( $price_data ) ) {
            $this->log( 'info', 'Price sync: no price data received.' );
            update_option( self::OPTION_LAST_PRICE_SYNC, gmdate( 'c' ) );
            return $results;
        }

        foreach ( $price_data as $sku => $prices ) {
            try {
                $this->update_product_prices( $sku, $prices );
                $results['updated']++;
            } catch ( \Exception $e ) {
                $results['errors']++;
                $this->log(
                    'error',
                    sprintf( 'Failed to update prices for SKU %s: %s', $sku, $e->getMessage() )
                );
            }
        }

        update_option( self::OPTION_LAST_PRICE_SYNC, gmdate( 'c' ) );

        $this->log(
            'info',
            sprintf( 'Price sync completed: %d updated, %d errors.', $results['updated'], $results['errors'] )
        );

        return $results;
    }

    /**
     * Sync a single product from ERP format to WooCommerce.
     *
     * Handles both simple and variable products. For variable products,
     * creates/updates the parent product and all variations.
     *
     * @param array $erp_product ERP product data.
     * @return string 'created' or 'updated'.
     * @throws \Exception On failure to create/update product.
     */
    private function sync_single_product( array $erp_product ): string {
        $sku        = trim( (string) ( $erp_product['sku'] ?? '' ) );
        $product_id = wc_get_product_id_by_sku( $sku );
        $is_new     = empty( $product_id );

        // Determine if this is a variable product.
        $has_variations = ! empty( $erp_product['variations'] );

        if ( $has_variations ) {
            return $this->sync_variable_product( $erp_product, $product_id );
        }

        return $this->sync_simple_product( $erp_product, $product_id );
    }

    /**
     * Sync a simple product.
     *
     * @param array $erp_product ERP product data.
     * @param int   $product_id  Existing WC product ID (0 if new).
     * @return string 'created' or 'updated'.
     */
    private function sync_simple_product( array $erp_product, int $product_id ): string {
        $product_data = $this->transform_erp_to_wc( $erp_product );

        if ( $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                throw new \RuntimeException( sprintf( 'Product ID %d not found.', $product_id ) );
            }
        } else {
            $product = new \WC_Product_Simple();
        }

        $product->set_name( $product_data['name'] );
        $product->set_sku( $product_data['sku'] );
        $product->set_description( $product_data['description'] ?? '' );
        $product->set_short_description( $product_data['short_description'] ?? '' );
        $product->set_regular_price( $product_data['regular_price'] ?? '' );
        $product->set_sale_price( $product_data['sale_price'] ?? '' );
        $product->set_manage_stock( true );
        $product->set_stock_quantity( $product_data['stock_quantity'] ?? 0 );
        $product->set_weight( $product_data['weight'] ?? '' );
        $product->set_status( $product_data['status'] ?? 'publish' );

        if ( ! empty( $product_data['categories'] ) ) {
            $product->set_category_ids( $this->resolve_category_ids( $product_data['categories'] ) );
        }

        // Store ERP metadata.
        $product->update_meta_data( '_erp_product_id', $erp_product['id'] ?? '' );
        $product->update_meta_data( '_erp_last_sync', gmdate( 'c' ) );

        $product->save();

        return $product_id ? 'updated' : 'created';
    }

    /**
     * Sync a variable product with its variations.
     *
     * @param array $erp_product ERP product data with variations.
     * @param int   $product_id  Existing WC product ID (0 if new).
     * @return string 'created' or 'updated'.
     */
    private function sync_variable_product( array $erp_product, int $product_id ): string {
        $product_data = $this->transform_erp_to_wc( $erp_product );

        if ( $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product || ! $product->is_type( 'variable' ) ) {
                // Convert to variable if needed or create new.
                $product = new \WC_Product_Variable( $product_id );
            }
        } else {
            $product = new \WC_Product_Variable();
        }

        $product->set_name( $product_data['name'] );
        $product->set_sku( $product_data['sku'] );
        $product->set_description( $product_data['description'] ?? '' );
        $product->set_short_description( $product_data['short_description'] ?? '' );
        $product->set_status( $product_data['status'] ?? 'publish' );

        if ( ! empty( $product_data['categories'] ) ) {
            $product->set_category_ids( $this->resolve_category_ids( $product_data['categories'] ) );
        }

        // Store ERP metadata.
        $product->update_meta_data( '_erp_product_id', $erp_product['id'] ?? '' );
        $product->update_meta_data( '_erp_last_sync', gmdate( 'c' ) );

        $attribute_labels = $this->get_attribute_labels_from_product( $erp_product );

        // Set up attributes from variations.
        $attributes = $this->build_attributes_from_variations( $erp_product['variations'], $attribute_labels );
        $product->set_attributes( $attributes );

        $product->save();

        // Sync each variation.
        $parent_sku = trim( (string) ( $erp_product['sku'] ?? '' ) );
        foreach ( $erp_product['variations'] as $erp_variation ) {
            $this->sync_variation( $product->get_id(), $erp_variation, $parent_sku, $attribute_labels );
        }

        return $product_id ? 'updated' : 'created';
    }

    /**
     * Sync a single product variation.
     *
     * @param int    $parent_id     Parent variable product ID.
     * @param array  $erp_variation ERP variation data.
     * @param string $parent_sku    Parent product SKU (for collision avoidance).
     */
    private function sync_variation( int $parent_id, array $erp_variation, string $parent_sku = '', array $attribute_labels = [] ): void {
        $variation_sku = trim( (string) ( $erp_variation['sku'] ?? '' ) );
        $parent_sku    = trim( $parent_sku );

        if ( '' === $variation_sku ) {
            throw new \RuntimeException( 'Variation SKU is required.' );
        }

        if ( '' !== $parent_sku && $variation_sku === $parent_sku ) {
            throw new \RuntimeException(
                sprintf(
                    'Variation SKU must differ from parent SKU "%s".',
                    $parent_sku
                )
            );
        }

        $variation_id = wc_get_product_id_by_sku( $variation_sku );

        if ( $variation_id ) {
            $existing = wc_get_product( $variation_id );
            if ( $existing && ! $existing->is_type( 'variation' ) ) {
                $variation_id = 0;
            }
        }

        if ( $variation_id ) {
            $variation = wc_get_product( $variation_id );
            if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
                $variation = new \WC_Product_Variation( $variation_id );
            }
        } else {
            $variation = new \WC_Product_Variation();
            $variation->set_parent_id( $parent_id );
        }

        $variation->set_sku( $variation_sku );
        $variation->set_regular_price( (string) ( $erp_variation['regular_price'] ?? '' ) );
        $variation->set_sale_price( (string) ( $erp_variation['sale_price'] ?? '' ) );
        $variation->set_weight( (string) ( $erp_variation['weight'] ?? '' ) );

        if ( array_key_exists( 'stock_quantity', $erp_variation ) ) {
            $variation->set_manage_stock( true );
            $stock_qty = (int) $erp_variation['stock_quantity'];
            $variation->set_stock_quantity( $stock_qty );
            $variation->set_stock_status( $stock_qty > 0 ? 'instock' : 'outofstock' );
        }

        // Set variation attributes (keys = type code; taxonomy label from denormalized map).
        if ( ! empty( $erp_variation['attributes'] ) ) {
            $formatted_attributes = [];
            foreach ( $erp_variation['attributes'] as $attr_code => $attr_value ) {
                $attr_code = (string) $attr_code;
                $label     = $this->resolve_attribute_label( $attr_code, $attribute_labels );
                $taxonomy  = $this->ensure_global_attribute_taxonomy( $label );
                $formatted_attributes[ $taxonomy ] = $this->ensure_attribute_term_slug( $taxonomy, (string) $attr_value );
            }
            $variation->set_attributes( $formatted_attributes );
        }

        $variation->update_meta_data( '_erp_variation_id', $erp_variation['id'] ?? '' );
        $variation->update_meta_data( '_erp_last_sync', gmdate( 'c' ) );

        $variation->save();
    }

    /**
     * Build WooCommerce product attributes from ERP variations.
     *
     * @param array $variations ERP variations data.
     * @return array WC_Product_Attribute objects.
     */
    private function build_attributes_from_variations( array $variations, array $attribute_labels = [] ): array {
        $attribute_values = [];

        foreach ( $variations as $variation ) {
            $attrs = $variation['attributes'] ?? [];
            if ( empty( $attrs ) || ! is_array( $attrs ) ) {
                continue;
            }
            foreach ( $attrs as $attr_code => $attr_value ) {
                $attr_code = (string) $attr_code;
                if ( ! isset( $attribute_values[ $attr_code ] ) ) {
                    $attribute_values[ $attr_code ] = [];
                }
                $attribute_values[ $attr_code ][] = $attr_value;
            }
        }

        $attributes = [];
        $position   = 0;

        foreach ( $attribute_values as $attr_code => $values ) {
            $label    = $this->resolve_attribute_label( (string) $attr_code, $attribute_labels );
            $taxonomy = $this->ensure_global_attribute_taxonomy( $label );
            $term_slugs = [];
            foreach ( array_unique( $values ) as $value ) {
                $term_slugs[] = $this->ensure_attribute_term_slug( $taxonomy, (string) $value );
            }

            $attr_id = (int) wc_attribute_taxonomy_id_by_name( $taxonomy );

            $attribute = new \WC_Product_Attribute();
            $attribute->set_id( $attr_id );
            $attribute->set_name( $taxonomy );
            $attribute->set_options( $term_slugs );
            $attribute->set_position( $position++ );
            // Visible=true hace que algunos temas muestren "Color: ..." además del selector de variaciones.
            $attribute->set_visible( false );
            $attribute->set_variation( true );

            $attributes[] = $attribute;
        }

        return $attributes;
    }

    /**
     * @param array $erp_product Normalized ERP product.
     * @return array<string, string> code => label
     */
    private function get_attribute_labels_from_product( array $erp_product ): array {
        $raw = $erp_product['attribute_labels'] ?? $erp_product['attributeLabels'] ?? [];
        if ( ! is_array( $raw ) ) {
            return [];
        }
        $out = [];
        foreach ( $raw as $code => $label ) {
            $code = strtolower( trim( (string) $code ) );
            $label = trim( (string) $label );
            if ( '' === $code || '' === $label ) {
                continue;
            }
            $out[ $code ] = $label;
        }
        return $out;
    }

    /**
     * @param string               $code            Attribute type code from ERP.
     * @param array<string,string> $attribute_labels Denormalized labels from product.
     */
    private function resolve_attribute_label( string $code, array $attribute_labels ): string {
        $code = strtolower( trim( $code ) );
        if ( '' !== $code && ! empty( $attribute_labels[ $code ] ) ) {
            return (string) $attribute_labels[ $code ];
        }
        return $code;
    }

    /**
     * Register global attribute taxonomy (pa_*) if missing.
     *
     * WooCommerce muestra en la tienda wc_attribute_label( $taxonomy ).
     * Si solo existe la taxonomía WP pero no la fila en woocommerce_attribute_taxonomies,
     * el front cae al slug técnico (pa_talla, pa_color).
     *
     * @param string $label Human label (e.g. Talla).
     * @return string Taxonomy name (pa_*).
     */
    private function ensure_global_attribute_taxonomy( string $label ): string {
        $label    = trim( $label );
        $slug     = wc_sanitize_taxonomy_name( $label );
        $taxonomy = 'pa_' . $slug;

        if ( '' === $slug ) {
            throw new \RuntimeException( 'Attribute label is required to create a global attribute.' );
        }

        $attr_id = $this->resolve_global_attribute_id( $taxonomy, $slug );

        /*
         * WooCommerce: wc_create_attribute() falla con "Slug already in use" si taxonomy_exists( pa_* )
         * pero aún no hay fila en woocommerce_attribute_taxonomies (taxonomía huérfana del plugin antiguo).
         * En ese caso hay que insertar la fila, no volver a llamar a wc_create_attribute().
         */
        if ( ! $attr_id && taxonomy_exists( $taxonomy ) ) {
            $attr_id = $this->repair_orphan_pa_taxonomy_row( $slug, $label );
        }

        if ( ! $attr_id ) {
            $created = wc_create_attribute(
                [
                    'name'         => $label,
                    'slug'         => $slug,
                    'type'         => 'select',
                    'order_by'     => 'menu_order',
                    'has_archives' => false,
                ]
            );

            if ( is_wp_error( $created ) ) {
                $code = $created->get_error_code();
                // Taxonomía registrada sin fila WC, o carrera: reintentar resolución / reparación.
                if ( 'invalid_product_attribute_slug_already_exists' === $code ) {
                    $attr_id = $this->repair_orphan_pa_taxonomy_row( $slug, $label );
                }
                if ( ! $attr_id ) {
                    $attr_id = $this->resolve_global_attribute_id( $taxonomy, $slug );
                }
                if ( ! $attr_id ) {
                    throw new \RuntimeException( $created->get_error_message() );
                }
            } else {
                $attr_id = (int) $created;
            }

            delete_transient( 'wc_attribute_taxonomies' );
        }

        $this->update_global_attribute_label_if_needed( $attr_id, $label );

        if ( ! taxonomy_exists( $taxonomy ) ) {
            register_taxonomy(
                $taxonomy,
                apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy, [ 'product' ] ),
                apply_filters(
                    'woocommerce_taxonomy_args_' . $taxonomy,
                    [
                        'labels'       => [
                            'name' => $label,
                        ],
                        'hierarchical' => false,
                        'show_ui'      => false,
                        'query_var'    => true,
                        'rewrite'      => false,
                    ]
                )
            );
        }

        delete_transient( 'wc_attribute_taxonomies' );

        return $taxonomy;
    }

    /**
     * Resolve WooCommerce global attribute ID (bypass stale transients).
     *
     * @param string $taxonomy pa_* taxonomy.
     * @param string $slug     attribute_name without pa_ prefix.
     */
    private function resolve_global_attribute_id( string $taxonomy, string $slug ): int {
        $attr_id = (int) wc_attribute_taxonomy_id_by_name( $taxonomy );
        if ( $attr_id > 0 ) {
            return $attr_id;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'woocommerce_attribute_taxonomies';
        $found = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT attribute_id FROM {$table} WHERE attribute_name = %s LIMIT 1",
                $slug
            )
        );

        if ( $found ) {
            delete_transient( 'wc_attribute_taxonomies' );
            return (int) $found;
        }

        return 0;
    }

    /**
     * Si existe la taxonomía pa_{slug} pero no la fila en woocommerce_attribute_taxonomies,
     * inserta la fila como haría wc_create_attribute (sin pasar la validación taxonomy_exists).
     *
     * @param string $slug  attribute_name (sin prefijo pa_).
     * @param string $label attribute_label.
     * @return int attribute_id o 0.
     */
    private function repair_orphan_pa_taxonomy_row( string $slug, string $label ): int {
        global $wpdb;

        $table = $wpdb->prefix . 'woocommerce_attribute_taxonomies';
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT attribute_id FROM {$table} WHERE attribute_name = %s LIMIT 1",
                $slug
            )
        );
        if ( $existing ) {
            return (int) $existing;
        }

        $data = [
            'attribute_label'   => $label,
            'attribute_name'    => $slug,
            'attribute_type'    => 'select',
            'attribute_orderby' => 'menu_order',
            'attribute_public'  => 0,
        ];

        $inserted = $wpdb->insert(
            $table,
            $data,
            [ '%s', '%s', '%s', '%s', '%d' ]
        );

        if ( false === $inserted ) {
            // Posible carrera: la fila apareció entre medias.
            $retry = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT attribute_id FROM {$table} WHERE attribute_name = %s LIMIT 1",
                    $slug
                )
            );
            return $retry ? (int) $retry : 0;
        }

        $id = (int) $wpdb->insert_id;
        do_action( 'woocommerce_attribute_added', $id, $data );

        delete_transient( 'wc_attribute_taxonomies' );
        if ( class_exists( '\WC_Cache_Helper' ) ) {
            \WC_Cache_Helper::invalidate_cache_group( 'woocommerce-attributes' );
        }

        return $id;
    }

    /**
     * Keep attribute_label in sync when ERP sends a better display name.
     *
     * @param int    $attr_id Attribute row ID.
     * @param string $label   Desired label.
     */
    private function update_global_attribute_label_if_needed( int $attr_id, string $label ): void {
        global $wpdb;

        $current = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT attribute_label FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_id = %d",
                $attr_id
            )
        );

        if ( is_string( $current ) && trim( $current ) !== trim( $label ) ) {
            $wpdb->update(
                $wpdb->prefix . 'woocommerce_attribute_taxonomies',
                [ 'attribute_label' => $label ],
                [ 'attribute_id' => $attr_id ],
                [ '%s' ],
                [ '%d' ]
            );
            delete_transient( 'wc_attribute_taxonomies' );
        }
    }

    /**
     * Ensure attribute term exists; return slug for variation meta.
     *
     * @param string $taxonomy pa_* taxonomy.
     * @param string $value    Term label.
     * @return string Term slug.
     */
    private function ensure_attribute_term_slug( string $taxonomy, string $value ): string {
        $value = trim( $value );
        if ( '' === $value ) {
            return '';
        }

        $term = get_term_by( 'name', $value, $taxonomy );
        if ( ! $term ) {
            $term = get_term_by( 'slug', sanitize_title( $value ), $taxonomy );
        }
        if ( $term && ! is_wp_error( $term ) ) {
            return $term->slug;
        }

        $inserted = wp_insert_term( $value, $taxonomy );
        if ( is_wp_error( $inserted ) ) {
            if ( 'term_exists' === $inserted->get_error_code() ) {
                $existing_id = (int) $inserted->get_error_data();
                $existing    = get_term( $existing_id, $taxonomy );
                if ( $existing && ! is_wp_error( $existing ) ) {
                    return $existing->slug;
                }
            }
            return sanitize_title( $value );
        }

        $created = get_term( (int) $inserted['term_id'], $taxonomy );
        return ( $created && ! is_wp_error( $created ) ) ? $created->slug : sanitize_title( $value );
    }

    /**
     * Transform ERP product format to WooCommerce format.
     *
     * @param array $erp_product ERP product data.
     * @return array WooCommerce-compatible product data.
     */
    private function transform_erp_to_wc( array $erp_product ): array {
        $erp_status = $erp_product['status'] ?? $erp_product['ecommerce_status'] ?? 'active';

        return [
            'name'              => $erp_product['name'] ?? $erp_product['title'] ?? '',
            'sku'               => $erp_product['sku'] ?? '',
            'description'       => $erp_product['description'] ?? '',
            'short_description' => $erp_product['short_description'] ?? $erp_product['excerpt'] ?? '',
            'regular_price'     => (string) ( $erp_product['regular_price'] ?? $erp_product['price'] ?? $erp_product['sale_price'] ?? '' ),
            'sale_price'        => (string) ( $erp_product['sale_price_promo'] ?? $erp_product['discount_price'] ?? '' ),
            'stock_quantity'    => (int) ( $erp_product['stock'] ?? $erp_product['stock_quantity'] ?? 0 ),
            'weight'            => (string) ( $erp_product['weight'] ?? $erp_product['weight_kg'] ?? '' ),
            'status'            => $this->map_erp_status( (string) $erp_status ),
            'categories'        => $erp_product['categories'] ?? $erp_product['category_path'] ?? [],
        ];
    }

    /**
     * Map ERP product status to WooCommerce status.
     *
     * @param string $erp_status ERP status value.
     * @return string WooCommerce post status.
     */
    private function map_erp_status( string $erp_status ): string {
        $status_map = [
            'active'       => 'publish',
            'inactive'     => 'draft',
            'discontinued' => 'private',
            'draft'        => 'draft',
        ];

        return $status_map[ $erp_status ] ?? 'draft';
    }

    /**
     * Resolve category names/slugs to WooCommerce term IDs.
     *
     * @param array $categories List of category names or slugs.
     * @return array List of term IDs.
     */
    private function resolve_category_ids( array $categories ): array {
        $term_ids = [];

        foreach ( $categories as $category ) {
            $cat_name = is_array( $category ) ? ( $category['name'] ?? '' ) : $category;
            if ( empty( $cat_name ) ) {
                continue;
            }

            $term = get_term_by( 'name', $cat_name, 'product_cat' );
            if ( ! $term ) {
                $term = get_term_by( 'slug', sanitize_title( $cat_name ), 'product_cat' );
            }
            if ( ! $term ) {
                // Create the category if it doesn't exist.
                $result = wp_insert_term( $cat_name, 'product_cat' );
                if ( ! is_wp_error( $result ) ) {
                    $term_ids[] = $result['term_id'];
                }
            } else {
                $term_ids[] = $term->term_id;
            }
        }

        return $term_ids;
    }

    /**
     * Update stock for a product identified by SKU.
     *
     * @param string    $sku        Product SKU.
     * @param array|int $stock_data Stock data (quantity or array with quantity key).
     */
    private function update_product_stock( string $sku, $stock_data ): void {
        $product_id = wc_get_product_id_by_sku( $sku );

        if ( ! $product_id ) {
            throw new \RuntimeException( sprintf( 'No product found for SKU: %s', $sku ) );
        }

        $product  = wc_get_product( $product_id );
        $quantity = is_array( $stock_data )
            ? (int) ( $stock_data['quantity'] ?? $stock_data['stock'] ?? 0 )
            : (int) $stock_data;

        $product->set_manage_stock( true );
        $product->set_stock_quantity( $quantity );

        // Mark out-of-stock if quantity is 0.
        if ( $quantity <= 0 ) {
            $product->set_stock_status( 'outofstock' );
        } elseif ( $quantity >= self::REACTIVATION_THRESHOLD ) {
            // Reactivate if above threshold.
            $product->set_stock_status( 'instock' );
        }

        $product->update_meta_data( '_erp_stock_last_sync', gmdate( 'c' ) );
        $product->save();
    }

    /**
     * Update prices for a product identified by SKU.
     *
     * @param string $sku    Product SKU.
     * @param array  $prices Price data with regular_price and/or sale_price.
     */
    private function update_product_prices( string $sku, array $prices ): void {
        $product_id = wc_get_product_id_by_sku( $sku );

        if ( ! $product_id ) {
            throw new \RuntimeException( sprintf( 'No product found for SKU: %s', $sku ) );
        }

        $product = wc_get_product( $product_id );

        if ( isset( $prices['regular_price'] ) ) {
            $product->set_regular_price( (string) $prices['regular_price'] );
        }

        if ( isset( $prices['sale_price'] ) ) {
            $sale_price = $prices['sale_price'];
            $product->set_sale_price( $sale_price ? (string) $sale_price : '' );
        }

        $product->update_meta_data( '_erp_price_last_sync', gmdate( 'c' ) );
        $product->save();
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
            $logger->log( $level, '[SyncService] ' . $message, [ 'source' => 'erp-integration' ] );
        } else {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( sprintf( '[ERP SyncService][%s] %s', strtoupper( $level ), $message ) );
        }
    }
}
