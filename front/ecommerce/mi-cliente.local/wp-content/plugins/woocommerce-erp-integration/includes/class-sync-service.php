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
     * Maximum slug length for WooCommerce attribute taxonomies.
     */
    private const MAX_TAXONOMY_SLUG_LENGTH = 28;

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
     * Initialize hooks (sin cron â€” event-driven via webhooks).
     *
     * Los mÃ©todos syncProducts, syncStock, syncPrices se invocan
     * desde WebhookHandler (webhooks entrantes ERP) o manualmente
     * desde Admin. No hay acciones de cron.
     */
    public function init(): void {
        // Sin acciones de cron â€” event-driven.
        add_filter( 'woocommerce_variation_option_name', [ $this, 'filter_variation_option_name' ], 10, 4 );
        add_filter( 'woocommerce_available_variation', [ $this, 'normalize_available_variation_attributes' ], 10, 3 );
        // En ficha de producto incluir variantes agotadas en el JSON para que WC muestre "Sin stock" (no botón gris sin mensaje).
        add_filter( 'option_woocommerce_hide_out_of_stock_items', [ $this, 'show_out_of_stock_variations_on_product_page' ] );
    }

    /**
     * @param mixed $value Option woocommerce_hide_out_of_stock_items ('yes'|'no').
     * @return mixed
     */
    public function show_out_of_stock_variations_on_product_page( $value ) {
        if ( function_exists( 'is_product' ) && is_product() ) {
            return 'no';
        }
        return $value;
    }

    /**
     * Ensure storefront variation JSON always uses canonical term slugs (7.5 → v7pt5).
     *
     * @param array                $data      Variation payload for add-to-cart JS.
     * @param \WC_Product          $product   Parent product.
     * @param \WC_Product_Variation $variation Variation.
     */
    public function normalize_available_variation_attributes( array $data, $product, $variation ): array {
        unset( $product, $variation );
        if ( empty( $data['attributes'] ) || ! is_array( $data['attributes'] ) ) {
            return $data;
        }

        foreach ( $data['attributes'] as $field => $raw_value ) {
            $raw_value = (string) $raw_value;
            if ( '' === $raw_value ) {
                continue;
            }

            $taxonomy = str_replace( 'attribute_', '', (string) $field );
            if ( ! taxonomy_exists( $taxonomy ) ) {
                continue;
            }

            $term = get_term_by( 'slug', $raw_value, $taxonomy );
            if ( ! $term || is_wp_error( $term ) ) {
                $term = get_term_by( 'name', $raw_value, $taxonomy );
            }
            if ( ( ! $term || is_wp_error( $term ) ) && preg_match( '/^\d+\.\d+$/', $raw_value ) ) {
                $term = $this->find_existing_attribute_term( $taxonomy, $raw_value );
            }

            if ( $term && ! is_wp_error( $term ) ) {
                $data['attributes'][ $field ] = $term->slug;
            }
        }

        return $data;
    }

    /**
     * Show ERP term label in variation dropdowns (name), not internal slug (v7pt5).
     *
     * @param string     $option_name Display text.
     * @param \WP_Term|null $term        Matching term.
     * @param string     $taxonomy    pa_* taxonomy.
     * @param \WC_Product $product    Parent product.
     */
    public function filter_variation_option_name( $option_name, $term, $taxonomy, $product ): string {
        unset( $product );
        $taxonomy = is_string( $taxonomy ) ? $taxonomy : '';

        if ( $term instanceof \WP_Term && '' !== trim( $term->name ) ) {
            $name = trim( $term->name );
            if ( ! preg_match( '/^v\d+pt\d+$/', $name ) ) {
                return $name;
            }
        }

        if ( '' !== $taxonomy ) {
            $resolved = get_term_by( 'slug', (string) $option_name, $taxonomy );
            if ( $resolved && ! is_wp_error( $resolved ) && '' !== trim( $resolved->name ) ) {
                $name = trim( $resolved->name );
                if ( ! preg_match( '/^v\d+pt\d+$/', $name ) ) {
                    return $name;
                }
            }
        }

        return $this->human_label_from_attribute_slug( (string) $option_name );
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
     * Sync a single parent product (and its variations) by parent SKU from the ERP API.
     *
     * Used by product_changed webhooks instead of a full catalog sync.
     *
     * @param string $sku Parent product SKU from ERP.
     * @return string|null 'created', 'updated', or null if product not found / skipped.
     * @throws \Exception On sync failure.
     */
    public function sync_product_by_sku( string $sku ): ?string {
        $sku = trim( $sku );
        if ( '' === $sku ) {
            return null;
        }

        $erp_product = $this->erp_client->get_product_by_sku( $sku );
        if ( empty( $erp_product ) || ! is_array( $erp_product ) ) {
            $this->log( 'warning', sprintf( 'Product sync by SKU: ERP returned no product for SKU %s.', $sku ) );
            return null;
        }

        $erp_product = $this->normalize_erp_api_product( $erp_product );
        $normalized_sku = trim( (string) ( $erp_product['sku'] ?? '' ) );
        if ( '' === $normalized_sku ) {
            $this->log( 'warning', sprintf( 'Product sync by SKU: missing SKU after normalize for %s.', $sku ) );
            return null;
        }

        try {
            $result = $this->sync_single_product( $erp_product );
            $this->log( 'info', sprintf( 'Product sync by SKU %s: %s.', $normalized_sku, $result ) );
            return $result;
        } catch ( \Throwable $e ) {
            $this->log(
                'error',
                sprintf( 'Product sync by SKU %s failed: %s', $normalized_sku, $e->getMessage() )
            );
            throw new \RuntimeException(
                sprintf( 'sync_product_by_sku(%s): %s', $normalized_sku, $e->getMessage() ),
                0,
                $e
            );
        }
    }

    /**
     * Load an existing WC variable product or convert a simple product to variable.
     *
     * @param int $product_id Existing WC product ID (0 for new).
     */
    private function load_or_create_variable_product( int $product_id ): \WC_Product_Variable {
        if ( $product_id > 0 ) {
            $existing = wc_get_product( $product_id );
            if ( $existing && $existing->is_type( 'variable' ) ) {
                return $existing;
            }
            if ( $existing ) {
                wp_remove_object_terms( $product_id, 'simple', 'product_type' );
                wp_set_object_terms( $product_id, 'variable', 'product_type', false );
                wc_delete_product_transients( $product_id );
                clean_post_cache( $product_id );
                $converted = wc_get_product( $product_id );
                if ( $converted && $converted->is_type( 'variable' ) ) {
                    return $converted;
                }
            }
            return new \WC_Product_Variable( $product_id );
        }

        return new \WC_Product_Variable();
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

        if ( ! empty( $normalized['variations'] ) && is_array( $normalized['variations'] ) ) {
            $normalized['variations'] = array_values(
                array_filter(
                    $normalized['variations'],
                    static function ( $variation ): bool {
                        if ( ! is_array( $variation ) ) {
                            return false;
                        }
                        return ! array_key_exists( 'active', $variation ) || false !== $variation['active'];
                    }
                )
            );
        }

        if ( ! empty( $erp_product['attribute_definitions'] ) && empty( $erp_product['attributeDefinitions'] ) ) {
            $normalized['attributeDefinitions'] = $erp_product['attribute_definitions'];
        }

        if ( ! empty( $erp_product['attribute_labels'] ) && empty( $erp_product['attributeLabels'] ) ) {
            $normalized['attribute_labels'] = $erp_product['attribute_labels'];
        }

        // Filterable attributes passthrough (already snake_case from API).
        // Note: if filterable_attributes is absent from API, sync_filterable_attributes
        // handles null gracefully per Requirement 4.10.
        if ( ! isset( $normalized['filterable_attribute_labels'] ) ) {
            $normalized['filterable_attribute_labels'] = [];
        }
        if ( ! isset( $normalized['filterable_attribute_value_colors'] ) ) {
            $normalized['filterable_attribute_value_colors'] = [];
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
            $attrs = [];
            foreach ( $variation['attributes'] as $code => $raw ) {
                $attrs[ (string) $code ] = $this->normalize_erp_attribute_value( $raw );
            }
            $normalized['attributes'] = $attrs;
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
        $stock_qty = max( 0, (int) ( $product_data['stock_quantity'] ?? 0 ) );
        $product->set_manage_stock( true );
        $product->set_stock_quantity( $stock_qty );
        $product->set_stock_status( $stock_qty > 0 ? 'instock' : 'outofstock' );
        $product->set_weight( $product_data['weight'] ?? '' );
        $product->set_status( $product_data['status'] ?? 'publish' );

        if ( ! empty( $product_data['categories'] ) ) {
            $product->set_category_ids( $this->resolve_category_ids( $product_data['categories'] ) );
        }

        // Sync filterable attributes for simple products.
        // Requirement 4.10: method handles null/missing filterable_attributes gracefully.
        $filterable_attributes             = $erp_product['filterable_attributes'] ?? null;
        $filterable_attribute_labels       = $erp_product['filterable_attribute_labels'] ?? [];
        $filterable_attribute_value_colors = $erp_product['filterable_attribute_value_colors'] ?? [];
        $attributes = $this->sync_filterable_attributes(
            $product,
            $filterable_attributes,
            $filterable_attribute_labels,
            [],
            $filterable_attribute_value_colors
        );
        $product->set_attributes( $attributes );

        // Store ERP metadata.
        $product->update_meta_data( '_erp_product_id', $erp_product['id'] ?? '' );
        $product->update_meta_data( '_erp_last_sync', gmdate( 'c' ) );

        $product->save();

        // Sync images after save (needs product ID).
        if ( ! empty( $product_data['images'] ) ) {
            $this->sync_product_images( $product->get_id(), $product_data['images'] );
        }

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

        $product = $this->load_or_create_variable_product( $product_id );

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
        $attribute_definitions = $erp_product['attribute_definitions'] ?? $erp_product['attributeDefinitions'] ?? [];
        if ( ! is_array( $attribute_definitions ) ) {
            $attribute_definitions = [];
        }

        // Set up attributes from catalog definitions + variation rows.
        $attributes = $this->build_attributes_from_variations(
            $erp_product['variations'],
            $attribute_labels,
            $attribute_definitions
        );

        // Sync filterable attributes (after variation attributes, per design).
        // Requirement 4.10: method handles null/missing filterable_attributes gracefully.
        $filterable_attributes             = $erp_product['filterable_attributes'] ?? null;
        $filterable_attribute_labels       = $erp_product['filterable_attribute_labels'] ?? [];
        $filterable_attribute_value_colors = $erp_product['filterable_attribute_value_colors'] ?? [];
        $attributes = $this->sync_filterable_attributes(
            $product,
            $filterable_attributes,
            $filterable_attribute_labels,
            $attributes,
            $filterable_attribute_value_colors
        );

        foreach ( $attributes as $index => $attr ) {
            if ( ! $attr instanceof \WC_Product_Attribute || $attr->get_id() <= 0 ) {
                continue;
            }
            $taxonomy = $attr->get_name();
            $attr->set_options(
                $this->normalize_product_attribute_options_to_term_ids( $taxonomy, $attr->get_options() )
            );
            $attributes[ $index ] = $attr;
        }

        $product->set_attributes( $attributes );

        $product->save();

        // Sync images after save (needs product ID).
        if ( ! empty( $product_data['images'] ) ) {
            $this->sync_product_images( $product->get_id(), $product_data['images'] );
        }

        // Sync each variation.
        $parent_sku      = trim( (string) ( $erp_product['sku'] ?? '' ) );
        $erp_variations  = [];
        $sync_errors     = 0;
        foreach ( $erp_product['variations'] as $erp_variation ) {
            if ( ! is_array( $erp_variation ) ) {
                continue;
            }
            $var_sku = trim( (string) ( $erp_variation['sku'] ?? '' ) );
            if ( '' === $var_sku ) {
                continue;
            }
            $erp_variations[] = $erp_variation;
            try {
                $this->sync_variation( $product->get_id(), $erp_variation, $parent_sku, $attribute_labels );
            } catch ( \Throwable $e ) {
                $sync_errors++;
                $this->log(
                    'error',
                    sprintf(
                        'Failed to sync variation SKU %s for parent %s: %s',
                        $var_sku,
                        $parent_sku,
                        $e->getMessage()
                    )
                );
            }
        }

        if ( $sync_errors > 0 && empty( $erp_variations ) ) {
            throw new \RuntimeException( 'No variations could be synced for this product.' );
        }

        $this->prune_orphan_variations( $product->get_id(), $erp_variations );
        $this->dedupe_variations_by_attribute_signature( $product->get_id(), $erp_variations );

        // Refresh lookup table so storefront variation dropdowns see new SKUs.
        if ( class_exists( '\WC_Product_Variable' ) ) {
            \WC_Product_Variable::sync( $product->get_id() );
        }
        wc_delete_product_transients( $product->get_id() );

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

        $variation_id = $this->find_variation_for_sync( $parent_id, $erp_variation );

        if ( $variation_id > 0 ) {
            $variation = wc_get_product( $variation_id );
            if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
                $variation = new \WC_Product_Variation( $variation_id );
            }
            if ( (int) $variation->get_parent_id() !== $parent_id ) {
                $variation->set_parent_id( $parent_id );
            }
        } else {
            $variation = new \WC_Product_Variation();
            $variation->set_parent_id( $parent_id );
        }

        $variation->set_status( 'publish' );
        $variation->set_sku( $variation_sku );

        $regular_price = (string) ( $erp_variation['regular_price'] ?? '' );
        if ( '' === $regular_price || ! is_numeric( $regular_price ) ) {
            $regular_price = (string) ( $erp_variation['sale_price'] ?? '0' );
        }
        $variation->set_regular_price( $regular_price );
        $variation->set_sale_price( (string) ( $erp_variation['sale_price'] ?? '' ) );
        $variation->set_weight( (string) ( $erp_variation['weight'] ?? '' ) );

        if ( array_key_exists( 'stock_quantity', $erp_variation ) && ! empty( $erp_variation['manage_stock'] ) ) {
            $stock_qty = max( 0, (int) $erp_variation['stock_quantity'] );
            $variation->set_manage_stock( true );
            $variation->set_stock_quantity( $stock_qty );
            $variation->set_stock_status( $stock_qty > 0 ? 'instock' : 'outofstock' );
        } else {
            // Sin kardex en ERP: variante publicada pero agotada hasta cargar stock-levels.
            $variation->set_manage_stock( true );
            $variation->set_stock_quantity( 0 );
            $variation->set_stock_status( 'outofstock' );
        }

        // Set variation attributes (keys = type code; taxonomy label from denormalized map).
        $formatted_attributes = [];
        if ( ! empty( $erp_variation['attributes'] ) ) {
            foreach ( $erp_variation['attributes'] as $attr_code => $attr_value ) {
                $attr_code = strtolower( trim( (string) $attr_code ) );
                if ( '' === $attr_code ) {
                    continue;
                }
                $value = $this->normalize_erp_attribute_value( $attr_value );
                if ( '' === $value ) {
                    continue;
                }
                $label    = $this->resolve_attribute_label( $attr_code, $attribute_labels );
                $taxonomy = $this->ensure_global_attribute_taxonomy( $label, false, $attr_code );
                $term     = $this->get_attribute_term_for_value( $taxonomy, $value );
                if ( ! $term ) {
                    $this->log(
                        'error',
                        sprintf(
                            'Variation SKU %s: could not resolve term "%s" for %s',
                            $variation_sku,
                            $value,
                            $taxonomy
                        )
                    );
                    continue;
                }
                $this->ensure_parent_attribute_includes_term( $parent_id, $taxonomy, $term );
                $formatted_attributes[ $taxonomy ] = $term->slug;
            }
            if ( ! empty( $formatted_attributes ) ) {
                $variation->set_attributes( $formatted_attributes );
            }
        }

        $variation->update_meta_data( '_erp_variation_id', $erp_variation['id'] ?? '' );
        $variation->update_meta_data( '_erp_last_sync', gmdate( 'c' ) );

        $variation->save();

        if ( ! empty( $formatted_attributes ) ) {
            $this->force_variation_attribute_meta( (int) $variation->get_id(), $formatted_attributes );
        }

        $variation_images = $this->resolve_erp_variation_images( $erp_variation );
        if ( ! empty( $variation_images ) ) {
            $this->sync_product_images( (int) $variation->get_id(), $variation_images );
        }
    }

    /**
     * Build WooCommerce product attributes from ERP variations.
     *
     * @param array $variations              ERP variations data.
     * @param array $attribute_labels        code => label.
     * @param array $attribute_definitions   code => allowed values from ERP catalog.
     * @return array WC_Product_Attribute objects.
     */
    private function build_attributes_from_variations(
        array $variations,
        array $attribute_labels = [],
        array $attribute_definitions = []
    ): array {
        $attribute_values = [];

        foreach ( $attribute_definitions as $code => $values ) {
            $code = strtolower( trim( (string) $code ) );
            if ( '' === $code || ! is_array( $values ) ) {
                continue;
            }
            $attribute_values[ $code ] = [];
            foreach ( $values as $value ) {
                $value = $this->normalize_erp_attribute_value( $value );
                if ( '' !== $value ) {
                    $attribute_values[ $code ][] = $value;
                }
            }
        }

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
                $attribute_values[ $attr_code ][] = $this->normalize_erp_attribute_value( $attr_value );
            }
        }

        $attributes = [];
        $position   = 0;

        foreach ( $attribute_values as $attr_code => $values ) {
            $attr_code = strtolower( trim( (string) $attr_code ) );
            if ( '' === $attr_code ) {
                continue;
            }
            try {
                $label    = $this->resolve_attribute_label( $attr_code, $attribute_labels );
                $taxonomy = $this->ensure_global_attribute_taxonomy( $label, false, $attr_code );
                $term_ids = $this->attribute_option_ids_from_values( $taxonomy, array_unique( $values ) );
                if ( empty( $term_ids ) ) {
                    continue;
                }

                $attr_id = (int) wc_attribute_taxonomy_id_by_name( $taxonomy );

                $attribute = new \WC_Product_Attribute();
                $attribute->set_id( $attr_id );
                $attribute->set_name( $taxonomy );
                $attribute->set_options( $term_ids );
                $attribute->set_position( $position++ );
                // Visible=false: el selector de variaciones ya muestra las opciones.
                $attribute->set_visible( false );
                $attribute->set_variation( true );
                $attributes[] = $attribute;
            } catch ( \Throwable $e ) {
                $this->log(
                    'error',
                    sprintf(
                        'Skipping variation attribute "%s": %s',
                        (string) $attr_code,
                        $e->getMessage()
                    )
                );
            }
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
     * Si solo existe la taxonomÃ­a WP pero no la fila en woocommerce_attribute_taxonomies,
     * el front cae al slug tÃ©cnico (pa_talla, pa_color).
     *
     * @param string $label        Human label (e.g. Talla).
     * @param bool   $has_archives Whether to enable archives (layered nav). Default false.
     * @param string $code         ERP attribute type code (preferred for pa_* slug).
     * @return string Taxonomy name (pa_*).
     */
    private function ensure_global_attribute_taxonomy( string $label, bool $has_archives = false, string $code = '' ): string {
        $label    = trim( $label );
        $slug     = $this->taxonomy_slug_for_attribute( $code, $label );

        // Truncate slug to 28 characters (WooCommerce taxonomy name limit).
        if ( strlen( $slug ) > 28 ) {
            $original_slug = $slug;
            $slug = substr( $slug, 0, 28 );
            $this->log(
                'warning',
                sprintf(
                    'Attribute slug truncated from "%s" to "%s" (original label: "%s").',
                    $original_slug,
                    $slug,
                    $label
                )
            );
        }

        $taxonomy = 'pa_' . $slug;

        if ( '' === $slug ) {
            throw new \RuntimeException( 'Attribute label is required to create a global attribute.' );
        }

        $attr_id = $this->resolve_global_attribute_id( $taxonomy, $slug );

        /*
         * WooCommerce: wc_create_attribute() falla con "Slug already in use" si taxonomy_exists( pa_* )
         * pero aÃºn no hay fila en woocommerce_attribute_taxonomies (taxonomÃ­a huÃ©rfana del plugin antiguo).
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
                    'has_archives' => $has_archives,
                ]
            );

            if ( is_wp_error( $created ) ) {
                $code = $created->get_error_code();
                // TaxonomÃ­a registrada sin fila WC, o carrera: reintentar resoluciÃ³n / reparaciÃ³n.
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

        // If has_archives requested and taxonomy was already existing, enable archives.
        if ( $has_archives && $attr_id ) {
            $this->enable_has_archives_if_needed( $attr_id );
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
     * Enable has_archives (attribute_public = 1) on an existing attribute taxonomy if not already set.
     *
     * Only upgrades from 0 â†’ 1; never downgrades. This respects existing taxonomies
     * that may have been registered by the theme or other plugins.
     *
     * @param int $attr_id WooCommerce attribute taxonomy ID.
     */
    private function enable_has_archives_if_needed( int $attr_id ): void {
        global $wpdb;

        $table   = $wpdb->prefix . 'woocommerce_attribute_taxonomies';
        $current = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT attribute_public FROM {$table} WHERE attribute_id = %d",
                $attr_id
            )
        );

        if ( null !== $current && (int) $current === 0 ) {
            $wpdb->update(
                $table,
                [ 'attribute_public' => 1 ],
                [ 'attribute_id' => $attr_id ],
                [ '%d' ],
                [ '%d' ]
            );
            delete_transient( 'wc_attribute_taxonomies' );
        }
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
     * Si existe la taxonomÃ­a pa_{slug} pero no la fila en woocommerce_attribute_taxonomies,
     * inserta la fila como harÃ­a wc_create_attribute (sin pasar la validaciÃ³n taxonomy_exists).
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
            // Posible carrera: la fila apareciÃ³ entre medias.
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
     * Stable pa_* slug from ERP attribute type code (fallback: label).
     *
     * @param string $code  ERP attribute type code (e.g. color, talla-calzado).
     * @param string $label Human label (e.g. Color).
     */
    private function taxonomy_slug_for_attribute( string $code, string $label ): string {
        $code_slug = wc_sanitize_taxonomy_name( strtolower( trim( $code ) ) );
        if ( '' !== $code_slug ) {
            if ( strlen( $code_slug ) > self::MAX_TAXONOMY_SLUG_LENGTH ) {
                $code_slug = substr( $code_slug, 0, self::MAX_TAXONOMY_SLUG_LENGTH );
            }
            return $code_slug;
        }

        $label_slug = wc_sanitize_taxonomy_name( trim( $label ) );
        if ( strlen( $label_slug ) > self::MAX_TAXONOMY_SLUG_LENGTH ) {
            $label_slug = substr( $label_slug, 0, self::MAX_TAXONOMY_SLUG_LENGTH );
        }
        return $label_slug;
    }

    /**
     * Normalize ERP attribute value for term name/slug (7,5 → 7.5; floats without drift).
     *
     * @param mixed $raw Raw value from API.
     */
    private function normalize_erp_attribute_value( $raw ): string {
        if ( is_int( $raw ) ) {
            return (string) $raw;
        }
        if ( is_float( $raw ) ) {
            $formatted = rtrim( rtrim( sprintf( '%.6F', $raw ), '0' ), '.' );
            return str_replace( ',', '.', $formatted );
        }

        $value = trim( str_replace( ',', '.', (string) $raw ) );
        return $value;
    }

    /**
     * Canonical term slug. Decimals use v7pt5 (not 7-5) — WC a veces no valida slugs con guión como talla.
     *
     * @param string $value Normalized ERP label (e.g. 7.5).
     */
    private function attribute_term_slug( string $value ): string {
        $value = $this->normalize_erp_attribute_value( $value );
        if ( '' === $value ) {
            return '';
        }

        if ( preg_match( '/^(\d+)\.(\d+)$/', $value, $m ) ) {
            return 'v' . $m[1] . 'pt' . $m[2];
        }

        if ( preg_match( '/^\d+$/', $value ) ) {
            return $value;
        }

        $slug = sanitize_title( $value );
        return '' !== $slug ? $slug : 'erp-' . substr( md5( $value ), 0, 12 );
    }

    /**
     * Legacy slugs from older plugin versions (7.5 → 7-5).
     *
     * @param string $value Normalized display value.
     * @return string[]
     */
    private function legacy_attribute_term_slug_candidates( string $value ): array {
        $candidates = [ $this->attribute_term_slug( $value ) ];
        if ( preg_match( '/^\d+\.\d+$/', $value ) ) {
            $candidates[] = str_replace( '.', '-', $value );
            $candidates[] = sanitize_title( $value );
        }
        return array_values( array_unique( array_filter( $candidates ) ) );
    }

    /**
     * @return \WP_Term|null
     */
    private function find_existing_attribute_term( string $taxonomy, string $value ) {
        $value = $this->normalize_erp_attribute_value( $value );
        if ( '' === $value ) {
            return null;
        }

        $term = get_term_by( 'name', $value, $taxonomy );
        if ( $term && ! is_wp_error( $term ) ) {
            return $term;
        }

        foreach ( $this->legacy_attribute_term_slug_candidates( $value ) as $slug ) {
            $term = get_term_by( 'slug', $slug, $taxonomy );
            if ( $term && ! is_wp_error( $term ) ) {
                return $term;
            }
        }

        return null;
    }

    /**
     * Keep term name as ERP label when slug was sanitized (legacy terms used slug as name).
     *
     * @param \WP_Term $term  Existing term.
     * @param string   $label Human-readable value from ERP.
     */
    /**
     * Human label for internal decimal slugs (v7pt5 → 7.5).
     */
    private function human_label_from_attribute_slug( string $slug ): string {
        if ( preg_match( '/^v(\d+)pt(\d+)$/', $slug, $m ) ) {
            return $m[1] . '.' . $m[2];
        }
        return $slug;
    }

    /**
     * Ensure term exists and return WP_Term (name = ERP label, slug canonical).
     *
     * @return \WP_Term|null
     */
    private function get_attribute_term_for_value( string $taxonomy, string $value ): ?\WP_Term {
        $value = $this->normalize_erp_attribute_value( $value );
        if ( '' === $value ) {
            return null;
        }

        $slug = $this->ensure_attribute_term( $taxonomy, $value );
        if ( '' === $slug ) {
            return null;
        }

        $term = get_term_by( 'slug', $slug, $taxonomy );
        if ( $term && ! is_wp_error( $term ) ) {
            $this->repair_attribute_term_display_name( $term, $value );
            return $term;
        }

        return $this->find_existing_attribute_term( $taxonomy, $value );
    }

    /**
     * WooCommerce expects term IDs in parent attribute options for global taxonomies.
     *
     * @param string $taxonomy pa_*.
     * @param array  $values   ERP labels.
     * @return int[]
     */
    private function attribute_option_ids_from_values( string $taxonomy, array $values ): array {
        $ids = [];
        foreach ( $values as $raw ) {
            $term = $this->get_attribute_term_for_value( $taxonomy, (string) $raw );
            if ( $term ) {
                $ids[ (int) $term->term_id ] = (int) $term->term_id;
            }
        }
        return array_values( $ids );
    }

    /**
     * Collapse mixed slug/name/id options to unique term IDs (drops orphan strings).
     *
     * @param string $taxonomy pa_*.
     * @param array  $options  Raw WC attribute options.
     * @return int[]
     */
    private function normalize_product_attribute_options_to_term_ids( string $taxonomy, array $options ): array {
        $ids = [];
        foreach ( $options as $opt ) {
            if ( is_numeric( $opt ) && (int) $opt > 0 ) {
                $term = get_term( (int) $opt, $taxonomy );
                if ( $term && ! is_wp_error( $term ) ) {
                    $ids[ (int) $term->term_id ] = (int) $term->term_id;
                }
                continue;
            }

            $raw = trim( (string) $opt );
            if ( '' === $raw ) {
                continue;
            }

            $term = get_term_by( 'slug', $raw, $taxonomy );
            if ( ! $term || is_wp_error( $term ) ) {
                $term = get_term_by( 'name', $raw, $taxonomy );
            }
            if ( ( ! $term || is_wp_error( $term ) ) && preg_match( '/^v\d+pt\d+$/', $raw ) ) {
                $term = $this->find_existing_attribute_term( $taxonomy, $this->human_label_from_attribute_slug( $raw ) );
            }
            if ( $term && ! is_wp_error( $term ) ) {
                $ids[ (int) $term->term_id ] = (int) $term->term_id;
            }
        }
        return array_values( $ids );
    }

    /**
     * Parent must list the term ID or WC leaves the variation attribute empty ("Any").
     */
    private function ensure_parent_attribute_includes_term( int $parent_id, string $taxonomy, \WP_Term $term ): void {
        $parent = wc_get_product( $parent_id );
        if ( ! $parent || ! $parent->is_type( 'variable' ) ) {
            return;
        }

        $attributes = $parent->get_attributes();
        $updated    = false;

        foreach ( $attributes as $key => $attr ) {
            if ( ! $attr instanceof \WC_Product_Attribute || $attr->get_name() !== $taxonomy ) {
                continue;
            }

            $ids = $this->normalize_product_attribute_options_to_term_ids( $taxonomy, $attr->get_options() );
            if ( ! in_array( (int) $term->term_id, $ids, true ) ) {
                $ids[] = (int) $term->term_id;
                $attr->set_options( $ids );
                $attributes[ $key ] = $attr;
                $updated              = true;
            }
            break;
        }

        if ( $updated ) {
            $parent->set_attributes( $attributes );
            $parent->save();
        }
    }

    private function repair_attribute_term_display_name( \WP_Term $term, string $label ): void {
        $label = trim( $label );
        $name  = trim( $term->name );
        if ( $name === $label ) {
            return;
        }
        if ( '' === $label ) {
            return;
        }
        if ( preg_match( '/^v\d+pt\d+$/', $name ) || $name !== $label ) {
            // Fall through to update.
        } else {
            return;
        }

        $updated = wp_update_term(
            (int) $term->term_id,
            $term->taxonomy,
            array(
                'name' => $label,
                'slug' => $term->slug,
            )
        );

        if ( is_wp_error( $updated ) ) {
            $this->log(
                'warning',
                sprintf(
                    'Could not repair attribute term name for "%s" in %s (term %d): %s',
                    $label,
                    $term->taxonomy,
                    (int) $term->term_id,
                    $updated->get_error_message()
                )
            );
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
        return $this->ensure_attribute_term( $taxonomy, $value );
    }

    /**
     * Create or resolve an attribute term (display name + URL slug).
     *
     * @param string   $taxonomy   pa_* taxonomy.
     * @param string   $value      Term label from ERP (e.g. "7.5").
     * @param int|null $menu_order Optional WooCommerce term order.
     * @return string Term slug.
     */
    private function ensure_attribute_term( string $taxonomy, string $value, ?int $menu_order = null ): string {
        $value = $this->normalize_erp_attribute_value( $value );
        if ( '' === $value ) {
            return '';
        }

        $canonical_slug = $this->attribute_term_slug( $value );
        $term           = $this->find_existing_attribute_term( $taxonomy, $value );

        if ( $term ) {
            if ( $term->slug !== $canonical_slug ) {
                $migrated = wp_update_term(
                    (int) $term->term_id,
                    $taxonomy,
                    [
                        'name' => $value,
                        'slug' => $canonical_slug,
                    ]
                );
                if ( ! is_wp_error( $migrated ) ) {
                    $term = get_term( (int) $term->term_id, $taxonomy );
                }
            }
            $this->repair_attribute_term_display_name( $term, $value );
            if ( null !== $menu_order ) {
                $this->update_term_menu_order( (int) $term->term_id, $menu_order );
            }
            return $term && ! is_wp_error( $term ) ? $term->slug : $canonical_slug;
        }

        $inserted = wp_insert_term(
            $value,
            $taxonomy,
            [ 'slug' => $canonical_slug ]
        );

        if ( is_wp_error( $inserted ) ) {
            if ( 'term_exists' === $inserted->get_error_code() ) {
                $existing_id = (int) $inserted->get_error_data();
                $existing    = get_term( $existing_id, $taxonomy );
                if ( $existing && ! is_wp_error( $existing ) ) {
                    $this->repair_attribute_term_display_name( $existing, $value );
                    if ( null !== $menu_order ) {
                        $this->update_term_menu_order( $existing_id, $menu_order );
                    }
                    return $existing->slug;
                }
            }
            $this->log(
                'error',
                sprintf(
                    'Could not create attribute term "%s" in %s: %s',
                    $value,
                    $taxonomy,
                    $inserted->get_error_message()
                )
            );
            return '';
        }

        $term_id = (int) $inserted['term_id'];
        if ( null !== $menu_order ) {
            $this->update_term_menu_order( $term_id, $menu_order );
        }

        $created = get_term( $term_id, $taxonomy );
        if ( $created && ! is_wp_error( $created ) ) {
            $this->repair_attribute_term_display_name( $created, $value );
            return $created->slug;
        }

        return $canonical_slug;
    }

    /**
     * Persist variation attribute meta (WC sometimes clears unknown slugs on save).
     *
     * @param int   $variation_id Variation post ID.
     * @param array $attributes   pa_* => term slug.
     */
    private function force_variation_attribute_meta( int $variation_id, array $attributes ): void {
        foreach ( $attributes as $taxonomy => $slug ) {
            $taxonomy = (string) $taxonomy;
            $slug     = (string) $slug;
            if ( '' === $taxonomy || '' === $slug ) {
                continue;
            }
            $meta_key = function_exists( 'wc_variation_attribute_name' )
                ? wc_variation_attribute_name( $taxonomy )
                : 'attribute_' . $taxonomy;
            update_post_meta( $variation_id, $meta_key, $slug );
        }
    }

    /**
     * Resolve existing WC variation under this parent (ERP id meta first, then SKU).
     */
    private function find_variation_for_sync( int $parent_id, array $erp_variation ): int {
        $erp_id = trim( (string) ( $erp_variation['id'] ?? '' ) );
        if ( '' !== $erp_id ) {
            $by_id = $this->find_child_variation_by_erp_id( $parent_id, $erp_id );
            if ( $by_id > 0 ) {
                return $by_id;
            }
        }

        $sku = trim( (string) ( $erp_variation['sku'] ?? '' ) );
        if ( '' === $sku ) {
            return 0;
        }

        $variation_id = (int) wc_get_product_id_by_sku( $sku );
        if ( $variation_id <= 0 ) {
            return 0;
        }

        $existing = wc_get_product( $variation_id );
        if ( ! $existing || ! $existing->is_type( 'variation' ) ) {
            return 0;
        }

        if ( (int) $existing->get_parent_id() !== $parent_id ) {
            return 0;
        }

        return $variation_id;
    }

    /**
     * @return int Variation post ID or 0.
     */
    private function find_child_variation_by_erp_id( int $parent_id, string $erp_id ): int {
        if ( $parent_id <= 0 || '' === trim( $erp_id ) ) {
            return 0;
        }

        $child_ids = get_posts(
            [
                'post_parent'    => $parent_id,
                'post_type'      => 'product_variation',
                'post_status'    => [ 'publish', 'private', 'draft' ],
                'numberposts'    => -1,
                'fields'         => 'ids',
                'meta_key'       => '_erp_variation_id',
                'meta_value'     => $erp_id,
                'suppress_filters' => true,
            ]
        );

        if ( empty( $child_ids ) ) {
            return 0;
        }

        return (int) $child_ids[0];
    }

    /**
     * Stable key for Color+Talla (normalized codes/values).
     *
     * @param array $attributes ERP attribute map.
     */
    private function variation_attribute_signature( array $attributes ): string {
        if ( empty( $attributes ) || ! is_array( $attributes ) ) {
            return '';
        }

        $pairs = [];
        foreach ( $attributes as $code => $value ) {
            $code = strtolower( trim( (string) $code ) );
            $value = $this->normalize_erp_attribute_value( $value );
            if ( '' === $code || '' === $value ) {
                continue;
            }
            $pairs[ $code ] = $value;
        }

        if ( empty( $pairs ) ) {
            return '';
        }

        ksort( $pairs );
        return wp_json_encode( $pairs );
    }

    /**
     * @param \WC_Product_Variation $variation WooCommerce variation.
     */
    private function wc_variation_attribute_signature( \WC_Product_Variation $variation ): string {
        $pairs = [];
        foreach ( $variation->get_attributes() as $taxonomy => $slug ) {
            $taxonomy = (string) $taxonomy;
            $slug     = (string) $slug;
            if ( '' === $taxonomy || '' === $slug ) {
                continue;
            }
            $term = get_term_by( 'slug', $slug, $taxonomy );
            if ( $term && ! is_wp_error( $term ) ) {
                $label = trim( $term->name );
            } else {
                $label = $this->human_label_from_attribute_slug( $slug );
            }
            if ( '' === $label ) {
                continue;
            }
            $code = preg_replace( '/^pa_/', '', $taxonomy );
            $pairs[ strtolower( (string) $code ) ] = $this->normalize_erp_attribute_value( $label );
        }

        if ( empty( $pairs ) ) {
            return '';
        }

        ksort( $pairs );
        return wp_json_encode( $pairs );
    }

    /**
     * Remove duplicate WC rows for the same attribute combo when ERP has a single variant.
     *
     * @param int   $parent_id      Parent product ID.
     * @param array $erp_variations Variations synced from ERP.
     */
    private function dedupe_variations_by_attribute_signature( int $parent_id, array $erp_variations ): void {
        if ( $parent_id <= 0 || empty( $erp_variations ) ) {
            return;
        }

        $erp_by_sig = [];
        foreach ( $erp_variations as $erp_variation ) {
            if ( ! is_array( $erp_variation ) ) {
                continue;
            }
            $sig = $this->variation_attribute_signature( $erp_variation['attributes'] ?? [] );
            if ( '' !== $sig ) {
                $erp_by_sig[ $sig ] = $erp_variation;
            }
        }

        $child_ids = get_posts(
            [
                'post_parent' => $parent_id,
                'post_type'   => 'product_variation',
                'post_status' => [ 'publish', 'private', 'draft' ],
                'numberposts' => -1,
                'fields'      => 'ids',
            ]
        );

        $wc_by_sig = [];
        foreach ( $child_ids as $child_id ) {
            $child_id = (int) $child_id;
            $child    = wc_get_product( $child_id );
            if ( ! $child || ! $child->is_type( 'variation' ) ) {
                continue;
            }
            $sig = $this->wc_variation_attribute_signature( $child );
            if ( '' === $sig ) {
                continue;
            }
            $wc_by_sig[ $sig ][] = $child_id;
        }

        foreach ( $wc_by_sig as $sig => $ids ) {
            if ( count( $ids ) <= 1 || ! isset( $erp_by_sig[ $sig ] ) ) {
                continue;
            }

            $keep_id = $this->pick_variation_to_keep( $ids, $erp_by_sig[ $sig ] );
            foreach ( $ids as $child_id ) {
                if ( (int) $child_id === (int) $keep_id ) {
                    continue;
                }
                $duplicate = wc_get_product( (int) $child_id );
                if ( $duplicate ) {
                    $duplicate->delete( true );
                    $this->log(
                        'info',
                        sprintf(
                            'Removed duplicate variation %d (same attributes as ERP variant %s).',
                            (int) $child_id,
                            (string) ( $erp_by_sig[ $sig ]['id'] ?? $erp_by_sig[ $sig ]['sku'] ?? '' )
                        )
                    );
                }
            }
        }
    }

    /**
     * @param int[] $variation_ids Candidate WC variation IDs.
     * @param array $erp_variation Matching ERP row.
     * @return int Variation ID to keep.
     */
    private function pick_variation_to_keep( array $variation_ids, array $erp_variation ): int {
        $erp_id = trim( (string) ( $erp_variation['id'] ?? '' ) );
        if ( '' !== $erp_id ) {
            foreach ( $variation_ids as $variation_id ) {
                $stored = trim( (string) get_post_meta( (int) $variation_id, '_erp_variation_id', true ) );
                if ( $stored === $erp_id ) {
                    return (int) $variation_id;
                }
            }
        }

        $sku = trim( (string) ( $erp_variation['sku'] ?? '' ) );
        if ( '' !== $sku ) {
            foreach ( $variation_ids as $variation_id ) {
                $child = wc_get_product( (int) $variation_id );
                if ( $child && trim( (string) $child->get_sku() ) === $sku ) {
                    return (int) $variation_id;
                }
            }
        }

        return (int) min( $variation_ids );
    }

    /**
     * Remove WC variations no longer present in ERP (by SKU or ERP id).
     *
     * @param int   $parent_id      Parent variable product ID.
     * @param array $erp_variations Variations synced from ERP.
     */
    private function prune_orphan_variations( int $parent_id, array $erp_variations ): void {
        if ( $parent_id <= 0 ) {
            return;
        }

        $allowed_skus = [];
        $allowed_ids  = [];
        foreach ( $erp_variations as $erp_variation ) {
            if ( ! is_array( $erp_variation ) ) {
                continue;
            }
            $sku = trim( (string) ( $erp_variation['sku'] ?? '' ) );
            if ( '' !== $sku ) {
                $allowed_skus[ $sku ] = true;
            }
            $erp_id = trim( (string) ( $erp_variation['id'] ?? '' ) );
            if ( '' !== $erp_id ) {
                $allowed_ids[ $erp_id ] = true;
            }
        }

        $child_ids = get_posts(
            [
                'post_parent' => $parent_id,
                'post_type'   => 'product_variation',
                'post_status' => [ 'publish', 'private', 'draft' ],
                'numberposts' => -1,
                'fields'      => 'ids',
            ]
        );

        foreach ( $child_ids as $child_id ) {
            $child_id = (int) $child_id;
            $child    = wc_get_product( $child_id );
            if ( ! $child || ! $child->is_type( 'variation' ) ) {
                continue;
            }

            $sku     = trim( (string) $child->get_sku() );
            $erp_id  = trim( (string) $child->get_meta( '_erp_variation_id', true ) );
            $allowed = ( '' !== $sku && isset( $allowed_skus[ $sku ] ) )
                || ( '' !== $erp_id && isset( $allowed_ids[ $erp_id ] ) );

            if ( $allowed ) {
                continue;
            }

            $child->delete( true );
            $this->log(
                'info',
                sprintf(
                    'Removed orphan variation %d (SKU %s, ERP id %s).',
                    $child_id,
                    $sku ?: '-',
                    $erp_id ?: '-'
                )
            );
        }
    }

    /**
     * Normalize a list of ERP image URLs (non-empty strings only).
     *
     * @param mixed $image_urls Raw images array from ERP.
     * @return array<int, string>
     */
    private function normalize_erp_image_urls( $image_urls ): array {
        if ( ! is_array( $image_urls ) ) {
            return [];
        }
        $out = [];
        foreach ( $image_urls as $url ) {
            $url = trim( (string) $url );
            if ( '' !== $url ) {
                $out[] = $url;
            }
        }
        return $out;
    }

    /**
     * Resolve catalog images for a product: parent images, or first variation with images.
     *
     * @param array $erp_product ERP product payload.
     * @return array<int, string>
     */
    private function resolve_erp_product_images( array $erp_product ): array {
        $images = $this->normalize_erp_image_urls(
            $erp_product['images'] ?? $erp_product['image_urls'] ?? []
        );
        if ( ! empty( $images ) ) {
            return $images;
        }
        foreach ( $erp_product['variations'] ?? [] as $variation ) {
            if ( ! is_array( $variation ) ) {
                continue;
            }
            $var_images = $this->resolve_erp_variation_images( $variation );
            if ( ! empty( $var_images ) ) {
                return $var_images;
            }
        }
        return [];
    }

    /**
     * Resolve images for a single ERP variation.
     *
     * @param array $erp_variation ERP variation payload.
     * @return array<int, string>
     */
    private function resolve_erp_variation_images( array $erp_variation ): array {
        return $this->normalize_erp_image_urls(
            $erp_variation['images'] ?? $erp_variation['image_urls'] ?? []
        );
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
            'images'            => $this->resolve_erp_product_images( $erp_product ),
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
     * Resolve an ordered ERP category path to WooCommerce term IDs (leaf assigned).
     *
     * Creates/finds terms as a parent chain so nested categories appear in the store.
     *
     * @param array $categories Ordered list of category names (root → leaf).
     * @return array List of term IDs (leaf only).
     */
    private function resolve_category_ids( array $categories ): array {
        $path_names = [];
        foreach ( $categories as $category ) {
            $name = is_array( $category )
                ? trim( (string) ( $category['name'] ?? '' ) )
                : trim( (string) $category );
            if ( '' !== $name ) {
                $path_names[] = $name;
            }
        }
        if ( empty( $path_names ) ) {
            return [];
        }

        $leaf_id = $this->ensure_product_category_path( $path_names );
        return $leaf_id ? [ $leaf_id ] : [];
    }

    /**
     * Ensure a hierarchical product_cat chain exists and return the leaf term ID.
     *
     * @param array<int, string> $path_names Category names from root to leaf.
     * @return int Leaf term ID or 0.
     */
    private function ensure_product_category_path( array $path_names ): int {
        $parent_id = 0;
        $term_id   = 0;

        foreach ( $path_names as $name ) {
            $term_id = $this->ensure_product_category_term( $name, $parent_id );
            if ( ! $term_id ) {
                return 0;
            }
            $parent_id = $term_id;
        }

        return $term_id;
    }

    /**
     * Find or create a product_cat term under a specific parent.
     *
     * @param string $name      Category display name.
     * @param int    $parent_id Parent term ID (0 for root).
     * @return int Term ID or 0 on failure.
     */
    private function ensure_product_category_term( string $name, int $parent_id ): int {
        $existing = get_terms(
            [
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'name'       => $name,
                'parent'     => $parent_id,
                'number'     => 1,
            ]
        );

        if ( ! is_wp_error( $existing ) && ! empty( $existing ) ) {
            return (int) $existing[0]->term_id;
        }

        $slug = sanitize_title( $name );
        if ( $parent_id > 0 ) {
            $parent_term = get_term( $parent_id, 'product_cat' );
            if ( $parent_term && ! is_wp_error( $parent_term ) && ! empty( $parent_term->slug ) ) {
                $slug = sanitize_title( $parent_term->slug . '-' . $name );
            }
        }

        $by_slug = get_terms(
            [
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'slug'       => $slug,
                'parent'     => $parent_id,
                'number'     => 1,
            ]
        );
        if ( ! is_wp_error( $by_slug ) && ! empty( $by_slug ) ) {
            return (int) $by_slug[0]->term_id;
        }

        $result = wp_insert_term(
            $name,
            'product_cat',
            [
                'slug'   => $slug,
                'parent' => $parent_id,
            ]
        );

        if ( is_wp_error( $result ) ) {
            if ( 'term_exists' === $result->get_error_code() ) {
                $existing_id = (int) $result->get_error_data();
                if ( $existing_id > 0 ) {
                    return $existing_id;
                }
            }
            $this->log(
                'warning',
                sprintf(
                    'Failed to create category "%s" (parent %d): %s',
                    $name,
                    $parent_id,
                    $result->get_error_message()
                )
            );
            return 0;
        }

        return (int) ( $result['term_id'] ?? 0 );
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
     * Sync product images from external URLs into WP Media Library.
     *
     * Downloads images from the ERP URLs, attaches them to the product,
     * sets the first image as featured (thumbnail), and the rest as gallery.
     *
     * @param int   $product_id WooCommerce product ID.
     * @param array $image_urls Array of public image URLs from the ERP.
     */
    private function sync_product_images( int $product_id, array $image_urls ): void {
        if ( empty( $image_urls ) || ! $product_id ) {
            return;
        }

        // Require WordPress media functions.
        if ( ! function_exists( 'media_sideload_image' ) ) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        // Get existing ERP-synced image URLs to avoid re-downloading.
        $existing_erp_urls = (array) get_post_meta( $product_id, '_erp_synced_image_urls', true );
        if ( ! is_array( $existing_erp_urls ) ) {
            $existing_erp_urls = [];
        }

        // If the image set hasn't changed, skip.
        if ( $existing_erp_urls === $image_urls ) {
            return;
        }

        $attachment_ids = [];

        foreach ( $image_urls as $url ) {
            $url = trim( (string) $url );
            if ( empty( $url ) ) {
                continue;
            }

            // Check if we already have this URL imported as an attachment.
            $existing_id = $this->find_attachment_by_erp_url( $url );
            if ( $existing_id ) {
                $attachment_ids[] = $existing_id;
                continue;
            }

            $attachment_id = $this->sideload_erp_image_url( $url, $product_id );
            if ( ! $attachment_id ) {
                continue;
            }

            // Store the ERP URL as meta on the attachment for future lookups.
            update_post_meta( $attachment_id, '_erp_source_url', $url );
            $attachment_ids[] = $attachment_id;
        }

        if ( empty( $attachment_ids ) ) {
            return;
        }

        // First image = featured (thumbnail).
        set_post_thumbnail( $product_id, $attachment_ids[0] );

        // Remaining images = gallery.
        $gallery_ids = array_slice( $attachment_ids, 1 );
        $product = wc_get_product( $product_id );
        if ( $product ) {
            $product->set_gallery_image_ids( $gallery_ids );
            $product->save();
        }

        // Store the synced URLs for change detection on next sync.
        update_post_meta( $product_id, '_erp_synced_image_urls', $image_urls );
    }

    /**
     * Download an ERP image URL into the Media Library.
     *
     * @param string $url       Public image URL from ERP/Firebase.
     * @param int    $post_id Parent product or variation post ID.
     * @return int|null Attachment ID or null on failure.
     */
    private function sideload_erp_image_url( string $url, int $post_id ): ?int {
        $attachment_id = media_sideload_image( $url, $post_id, '', 'id' );
        if ( ! is_wp_error( $attachment_id ) ) {
            return (int) $attachment_id;
        }

        $is_avif = (bool) preg_match( '/\.avif(\?|#|$)/i', $url );
        if ( ! $is_avif ) {
            $this->log(
                'warning',
                sprintf(
                    'Failed to sideload image for product %d: %s (URL: %s)',
                    $post_id,
                    $attachment_id->get_error_message(),
                    $url
                )
            );
            return null;
        }

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url( $url );
        if ( is_wp_error( $tmp ) ) {
            $this->log(
                'warning',
                sprintf(
                    'Failed to download AVIF for product %d: %s (URL: %s)',
                    $post_id,
                    $tmp->get_error_message(),
                    $url
                )
            );
            return null;
        }

        $path_from_url = (string) wp_parse_url( $url, PHP_URL_PATH );
        $name          = $path_from_url ? wp_basename( $path_from_url ) : 'image.avif';
        $file_array    = [
            'name'     => $name,
            'tmp_name' => $tmp,
        ];

        $id = media_handle_sideload( $file_array, $post_id );
        if ( is_wp_error( $id ) ) {
            @unlink( $tmp );
            $hint = wp_image_editor_supports( [ 'mime_type' => 'image/avif' ] )
                ? ''
                : ' Server PHP/GD does not process AVIF; re-upload as WebP/JPEG from ERP.';
            $this->log(
                'warning',
                sprintf(
                    'Failed to import AVIF for product %d: %s (URL: %s).%s',
                    $post_id,
                    $id->get_error_message(),
                    $url,
                    $hint
                )
            );
            return null;
        }

        return (int) $id;
    }

    /**
     * Find an existing attachment by its ERP source URL.
     *
     * @param string $url The original ERP image URL.
     * @return int|null Attachment ID or null if not found.
     */
    private function find_attachment_by_erp_url( string $url ): ?int {
        global $wpdb;
        $attachment_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_erp_source_url' AND meta_value = %s LIMIT 1",
            $url
        ) );
        return $attachment_id ? (int) $attachment_id : null;
    }

    /**
     * Sync filterable attributes from ERP to WooCommerce.
     *
     * Processes `filterable_attributes` from the API response. For each attribute:
     * - Derives slug via wc_sanitize_taxonomy_name($label), truncates to 28 chars if needed
     * - Reuses existing taxonomy if found in wc_attribute_taxonomies (does NOT modify has_archives)
     * - Creates new taxonomy with has_archives: true if not found
     * - Assigns with variation: false, visible: true
     * - Creates/reuses terms with menu_order based on array position
     *
     * Removal logic (Requirements 4.7, 6.7):
     * - Compares new filterable taxonomies with previously-synced ones (stored in product meta).
     * - If a filterable attribute was removed from the ERP:
     *   - If it was filterable-only → remove the attribute entirely from the product.
     *   - If it was combined (also in attribute_definitions) → revert to variation:true, visible:false, has_archives:false.
     *
     * Error handling (Requirements 4.9, 4.10):
     * - If taxonomy creation fails: log error, skip attribute, continue with remaining.
     * - If term creation fails: log error, skip term, continue with remaining terms.
     * - If slug exceeds 28 chars: truncate, log warning with original label and truncated slug.
     * - If `filterable_attributes` absent from API: treat as empty {}.
     *
     * @param \WC_Product $product                    WooCommerce product instance.
     * @param array|null  $filterable_attributes      Map of code => values from API (null if absent).
     * @param array       $filterable_attribute_labels Map of code => label from API.
     * @param array       $existing_attributes        Already-built WC_Product_Attribute objects (from variation sync).
     * @return array Merged array of WC_Product_Attribute objects.
     */
    private function sync_filterable_attributes(
        \WC_Product $product,
        ?array $filterable_attributes,
        array $filterable_attribute_labels,
        array $existing_attributes = [],
        array $filterable_attribute_value_colors = []
    ): array {
        // Requirement 4.10: If filterable_attributes absent in API, treat as empty {}.
        if ( null === $filterable_attributes || ! is_array( $filterable_attributes ) ) {
            $filterable_attributes = [];
        }

        // Collect the set of variation taxonomy names from existing_attributes for combined detection.
        $variation_taxonomies = [];
        foreach ( $existing_attributes as $attr ) {
            if ( $attr->get_variation() ) {
                $variation_taxonomies[] = $attr->get_name();
            }
        }

        // Retrieve previously-synced filterable taxonomy names from product meta.
        $previous_filterable_taxonomies = $this->get_previous_filterable_taxonomies( $product );

        $position = count( $existing_attributes );

        // Track the new set of filterable taxonomy names for this sync cycle.
        $current_filterable_taxonomies = [];

        foreach ( $filterable_attributes as $code => $values ) {
            $code = strtolower( trim( (string) $code ) );
            if ( '' === $code ) {
                continue;
            }

            // Resolve label: use provided label or fallback to code.
            $label = trim( (string) ( $filterable_attribute_labels[ $code ] ?? $code ) );
            if ( '' === $label ) {
                $label = $code;
            }

            // Same pa_* slug as variation attributes (by ERP type code).
            $slug = $this->taxonomy_slug_for_attribute( $code, $label );

            if ( '' === $slug ) {
                $this->log( 'error', sprintf( 'Filterable attribute label "%s" produced empty slug, skipping.', $label ) );
                continue;
            }

            // Requirement 4.10: If taxonomy creation fails, log error, skip attribute, continue.
            try {
                $taxonomy = $this->ensure_filterable_attribute_taxonomy( $slug, $label );
            } catch ( \Exception $e ) {
                $this->log(
                    'error',
                    sprintf(
                        'Failed to create/reuse taxonomy for filterable attribute "%s" (slug: "%s"): %s. Skipping attribute.',
                        $label,
                        $slug,
                        $e->getMessage()
                    )
                );
                continue;
            }

            $current_filterable_taxonomies[] = $taxonomy;

            // Ensure values is an array.
            if ( ! is_array( $values ) ) {
                $values = [ (string) $values ];
            }

            // Create/reuse terms with menu_order based on array position.
            // Requirement 4.10: If term creation fails, log error, skip term, continue.
            $term_slugs = [];
            foreach ( $values as $order => $value ) {
                $value = $this->normalize_erp_attribute_value( $value );
                if ( '' === $value ) {
                    continue;
                }
                try {
                    $term_slug = $this->ensure_attribute_term_with_order( $taxonomy, $value, (int) $order );
                    if ( '' !== $term_slug ) {
                        $term_obj = get_term_by( 'slug', $term_slug, $taxonomy );
                        if ( $term_obj && ! is_wp_error( $term_obj ) ) {
                            $term_slugs[] = (int) $term_obj->term_id;
                        }
                        $hex = '';
                        if (
                            isset( $filterable_attribute_value_colors[ $code ] )
                            && is_array( $filterable_attribute_value_colors[ $code ] )
                        ) {
                            $hex = (string) ( $filterable_attribute_value_colors[ $code ][ $value ] ?? '' );
                        }
                        if ( is_string( $hex ) && '' !== $hex && preg_match( '/^#[0-9A-Fa-f]{6}$/', $hex ) ) {
                            $term_obj = get_term_by( 'slug', $term_slug, $taxonomy );
                            if ( $term_obj && ! is_wp_error( $term_obj ) ) {
                                update_term_meta( (int) $term_obj->term_id, 'mi_cliente_color_hex', strtolower( $hex ) );
                            }
                        }
                    }
                } catch ( \Exception $e ) {
                    $this->log(
                        'error',
                        sprintf(
                            'Failed to create term "%s" under taxonomy "%s": %s. Skipping term.',
                            $value,
                            $taxonomy,
                            $e->getMessage()
                        )
                    );
                    continue;
                }
            }

            if ( empty( $term_slugs ) ) {
                continue;
            }

            $attr_id = (int) wc_attribute_taxonomy_id_by_name( $taxonomy );

            // Check if this taxonomy already exists in existing_attributes (merge case).
            // Requirement 6.3: When slug matches both attribute_definitions and filterable_attributes,
            // set variation: true, visible: true, has_archives: true.
            // Requirement 6.5: If previously variation-only, upgrade visible and has_archives.
            $merged = false;
            foreach ( $existing_attributes as &$existing_attr ) {
                if ( $existing_attr->get_name() === $taxonomy ) {
                    // Merge filterable values into variation attribute; keep visible=false on product page (solo selector).
                    $merged_options = $this->normalize_product_attribute_options_to_term_ids(
                        $taxonomy,
                        array_merge( $existing_attr->get_options(), $term_slugs )
                    );
                    $existing_attr->set_options( $merged_options );
                    $existing_attr->set_visible( false );
                    $existing_attr->set_variation( true );
                    // Layered nav / archives for filterable facet.
                    $this->enable_has_archives_if_needed( $attr_id );
                    $merged = true;
                    break;
                }
            }
            unset( $existing_attr );

            if ( ! $merged ) {
                $attribute = new \WC_Product_Attribute();
                $attribute->set_id( $attr_id );
                $attribute->set_name( $taxonomy );
                $attribute->set_options( $term_slugs );
                $attribute->set_position( $position++ );
                $attribute->set_visible( true );
                $attribute->set_variation( false );

                $existing_attributes[] = $attribute;
            }
        }

        // --- Removal logic (Requirements 4.7, 6.7) ---
        // Determine which filterable taxonomies were removed since last sync.
        $removed_taxonomies = array_diff( $previous_filterable_taxonomies, $current_filterable_taxonomies );

        foreach ( $removed_taxonomies as $removed_taxonomy ) {
            $is_variation = in_array( $removed_taxonomy, $variation_taxonomies, true );

            if ( $is_variation ) {
                // Combined attribute removed from filterable_attributes but still in attribute_definitions.
                // Requirement 6.7: Revert to variation:true, visible:false, has_archives:false.
                foreach ( $existing_attributes as &$existing_attr ) {
                    if ( $existing_attr->get_name() === $removed_taxonomy ) {
                        $existing_attr->set_visible( false );
                        $existing_attr->set_variation( true );
                        break;
                    }
                }
                unset( $existing_attr );

                // Disable has_archives on the global taxonomy.
                $this->disable_has_archives( $removed_taxonomy );

                $this->log(
                    'info',
                    sprintf(
                        'Combined attribute "%s" removed from filterable_attributes, reverted to variation-only (visible:false, has_archives:false).',
                        $removed_taxonomy
                    )
                );
            } else {
                // Filterable-only attribute removed from ERP.
                // Requirement 4.7: Remove the attribute entirely from the product.
                $existing_attributes = array_values(
                    array_filter(
                        $existing_attributes,
                        static function ( $attr ) use ( $removed_taxonomy ) {
                            return $attr->get_name() !== $removed_taxonomy;
                        }
                    )
                );

                $this->log(
                    'info',
                    sprintf(
                        'Filterable attribute "%s" removed from ERP, removed term association from product.',
                        $removed_taxonomy
                    )
                );
            }
        }

        // Store the current filterable taxonomy names for next sync comparison.
        $this->store_filterable_taxonomies( $product, $current_filterable_taxonomies );

        return $existing_attributes;
    }

    /**
     * Get the list of filterable taxonomy names previously synced for this product.
     *
     * @param \WC_Product $product WooCommerce product instance.
     * @return array List of taxonomy names (e.g., ['pa_marca', 'pa_genero']).
     */
    private function get_previous_filterable_taxonomies( \WC_Product $product ): array {
        $product_id = $product->get_id();
        if ( ! $product_id ) {
            return [];
        }

        $stored = get_post_meta( $product_id, '_erp_filterable_taxonomies', true );
        if ( ! is_array( $stored ) ) {
            return [];
        }

        return $stored;
    }

    /**
     * Store the current set of filterable taxonomy names on the product for next sync comparison.
     *
     * @param \WC_Product $product    WooCommerce product instance.
     * @param array       $taxonomies List of taxonomy names (e.g., ['pa_marca', 'pa_genero']).
     */
    private function store_filterable_taxonomies( \WC_Product $product, array $taxonomies ): void {
        $product_id = $product->get_id();
        if ( ! $product_id ) {
            // Product not yet saved — store via update_meta_data so it persists on next save.
            $product->update_meta_data( '_erp_filterable_taxonomies', $taxonomies );
            return;
        }

        update_post_meta( $product_id, '_erp_filterable_taxonomies', $taxonomies );
    }

    /**
     * Disable has_archives (attribute_public = 0) on a global attribute taxonomy.
     *
     * Used when a combined attribute is removed from filterable_attributes
     * but remains in attribute_definitions (Requirement 6.7).
     *
     * @param string $taxonomy Full taxonomy name (pa_*).
     */
    private function disable_has_archives( string $taxonomy ): void {
        $attr_id = (int) wc_attribute_taxonomy_id_by_name( $taxonomy );
        if ( ! $attr_id ) {
            return;
        }

        global $wpdb;

        $table   = $wpdb->prefix . 'woocommerce_attribute_taxonomies';
        $current = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT attribute_public FROM {$table} WHERE attribute_id = %d",
                $attr_id
            )
        );

        if ( null !== $current && (int) $current === 1 ) {
            $wpdb->update(
                $table,
                [ 'attribute_public' => 0 ],
                [ 'attribute_id' => $attr_id ],
                [ '%d' ],
                [ '%d' ]
            );
            delete_transient( 'wc_attribute_taxonomies' );
        }
    }

    /**
     * Ensure a global attribute taxonomy exists for a filterable attribute.
     *
     * Key difference from ensure_global_attribute_taxonomy():
     * - If the taxonomy ALREADY EXISTS in wc_attribute_taxonomies, it is reused
     *   WITHOUT modifying has_archives or other settings (theme compatibility).
     * - If the taxonomy does NOT exist, it is created with has_archives: true
     *   to enable WC_Layered_Nav filtering.
     *
     * @param string $slug  Sanitized slug (without pa_ prefix), max 28 chars.
     * @param string $label Human-readable label.
     * @return string Full taxonomy name (pa_*).
     * @throws \RuntimeException On failure to create taxonomy.
     */
    private function ensure_filterable_attribute_taxonomy( string $slug, string $label ): string {
        $taxonomy = 'pa_' . $slug;

        // Check if taxonomy already exists in wc_attribute_taxonomies.
        $attr_id = $this->resolve_global_attribute_id( $taxonomy, $slug );

        // Handle orphan taxonomy (WP taxonomy exists but no WC row).
        if ( ! $attr_id && taxonomy_exists( $taxonomy ) ) {
            $attr_id = $this->repair_orphan_pa_taxonomy_row( $slug, $label );
        }

        if ( $attr_id ) {
            // Taxonomy already exists - reuse WITHOUT modifying has_archives (Req 5.1, 5.8).
            // Only update the label if needed.
            $this->update_global_attribute_label_if_needed( $attr_id, $label );
        } else {
            // Taxonomy does not exist - create with has_archives: true (Req 5.6).
            $created = wc_create_attribute(
                [
                    'name'         => $label,
                    'slug'         => $slug,
                    'type'         => 'select',
                    'order_by'     => 'menu_order',
                    'has_archives' => true,
                ]
            );

            if ( is_wp_error( $created ) ) {
                $code = $created->get_error_code();
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

        // Ensure the taxonomy is registered in WordPress for the current request.
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

        return $taxonomy;
    }

    /**
     * Ensure attribute term exists and set its menu_order.
     *
     * Creates the term if it doesn't exist, then updates menu_order
     * so that theme's get_terms() with orderby=menu_order returns
     * values in the same order as the ERP array.
     *
     * @param string $taxonomy   pa_* taxonomy.
     * @param string $value      Term label/name.
     * @param int    $menu_order Position in the filterable_attributes array (0-based).
     * @return string Term slug.
     * @throws \RuntimeException On failure to create term.
     */
    private function ensure_attribute_term_with_order( string $taxonomy, string $value, int $menu_order ): string {
        $value = $this->normalize_erp_attribute_value( $value );
        if ( '' === $value ) {
            return '';
        }

        $slug = $this->ensure_attribute_term( $taxonomy, $value, $menu_order );
        if ( '' === $slug ) {
            throw new \RuntimeException(
                sprintf( 'Failed to create attribute term "%s" in %s.', $value, $taxonomy )
            );
        }

        return $slug;
    }

    /**
     * Update the menu_order (term_order) for a WooCommerce attribute term.
     *
     * WooCommerce stores term ordering in the termmeta table with key 'order'.
     * This is used by get_terms() when orderby=menu_order.
     *
     * @param int $term_id    Term ID.
     * @param int $menu_order Desired order position.
     */
    private function update_term_menu_order( int $term_id, int $menu_order ): void {
        update_term_meta( $term_id, 'order', $menu_order );
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
