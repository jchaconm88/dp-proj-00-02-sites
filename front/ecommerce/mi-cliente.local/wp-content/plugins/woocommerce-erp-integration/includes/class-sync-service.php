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
     * Initialize hooks for cron-based sync.
     */
    public function init(): void {
        add_action( 'erp_sync_products_cron', [ $this, 'syncProducts' ] );
        add_action( 'erp_sync_stock_cron', [ $this, 'syncStock' ] );
        add_action( 'erp_sync_prices_cron', [ $this, 'syncPrices' ] );

        // Register custom cron schedules.
        add_filter( 'cron_schedules', [ $this, 'register_cron_schedules' ] );
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
            'created' => 0,
            'updated' => 0,
            'errors'  => 0,
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
            $this->log( 'error', sprintf( 'Product sync failed to fetch from ERP: %s', $e->getMessage() ) );
            return $results;
        }

        if ( empty( $erp_products ) ) {
            $this->log( 'info', 'Product sync: no new or updated products found.' );
            update_option( self::OPTION_LAST_PRODUCT_SYNC, gmdate( 'c' ) );
            return $results;
        }

        foreach ( $erp_products as $erp_product ) {
            try {
                $result = $this->sync_single_product( $erp_product );
                if ( 'created' === $result ) {
                    $results['created']++;
                } else {
                    $results['updated']++;
                }
            } catch ( \Exception $e ) {
                $results['errors']++;
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
                'Product sync completed: %d created, %d updated, %d errors.',
                $results['created'],
                $results['updated'],
                $results['errors']
            )
        );

        return $results;
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
        $sku        = $erp_product['sku'] ?? '';
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

        // Set up attributes from variations.
        $attributes = $this->build_attributes_from_variations( $erp_product['variations'] );
        $product->set_attributes( $attributes );

        $product->save();

        // Sync each variation.
        foreach ( $erp_product['variations'] as $erp_variation ) {
            $this->sync_variation( $product->get_id(), $erp_variation );
        }

        return $product_id ? 'updated' : 'created';
    }

    /**
     * Sync a single product variation.
     *
     * @param int   $parent_id     Parent variable product ID.
     * @param array $erp_variation ERP variation data.
     */
    private function sync_variation( int $parent_id, array $erp_variation ): void {
        $variation_sku = $erp_variation['sku'] ?? '';
        $variation_id  = wc_get_product_id_by_sku( $variation_sku );

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
        $variation->set_manage_stock( true );
        $variation->set_stock_quantity( $erp_variation['stock_quantity'] ?? 0 );
        $variation->set_weight( (string) ( $erp_variation['weight'] ?? '' ) );

        // Set variation attributes.
        if ( ! empty( $erp_variation['attributes'] ) ) {
            $formatted_attributes = [];
            foreach ( $erp_variation['attributes'] as $attr_name => $attr_value ) {
                $taxonomy = 'pa_' . wc_sanitize_taxonomy_name( $attr_name );
                $formatted_attributes[ $taxonomy ] = sanitize_title( $attr_value );
            }
            $variation->set_attributes( $formatted_attributes );
        }

        // Update stock status.
        $stock_qty = (int) ( $erp_variation['stock_quantity'] ?? 0 );
        $variation->set_stock_status( $stock_qty > 0 ? 'instock' : 'outofstock' );

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
    private function build_attributes_from_variations( array $variations ): array {
        $attribute_values = [];

        foreach ( $variations as $variation ) {
            if ( empty( $variation['attributes'] ) ) {
                continue;
            }
            foreach ( $variation['attributes'] as $attr_name => $attr_value ) {
                if ( ! isset( $attribute_values[ $attr_name ] ) ) {
                    $attribute_values[ $attr_name ] = [];
                }
                $attribute_values[ $attr_name ][] = $attr_value;
            }
        }

        $attributes = [];
        $position   = 0;

        foreach ( $attribute_values as $attr_name => $values ) {
            $attribute = new \WC_Product_Attribute();
            $taxonomy  = 'pa_' . wc_sanitize_taxonomy_name( $attr_name );

            $attribute->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
            $attribute->set_name( $taxonomy );
            $attribute->set_options( array_unique( $values ) );
            $attribute->set_position( $position++ );
            $attribute->set_visible( true );
            $attribute->set_variation( true );

            $attributes[] = $attribute;
        }

        return $attributes;
    }

    /**
     * Transform ERP product format to WooCommerce format.
     *
     * @param array $erp_product ERP product data.
     * @return array WooCommerce-compatible product data.
     */
    private function transform_erp_to_wc( array $erp_product ): array {
        return [
            'name'              => $erp_product['name'] ?? $erp_product['title'] ?? '',
            'sku'               => $erp_product['sku'] ?? '',
            'description'       => $erp_product['description'] ?? '',
            'short_description' => $erp_product['short_description'] ?? $erp_product['excerpt'] ?? '',
            'regular_price'     => (string) ( $erp_product['price'] ?? $erp_product['regular_price'] ?? '' ),
            'sale_price'        => (string) ( $erp_product['sale_price'] ?? $erp_product['discount_price'] ?? '' ),
            'stock_quantity'    => (int) ( $erp_product['stock'] ?? $erp_product['stock_quantity'] ?? 0 ),
            'weight'            => (string) ( $erp_product['weight'] ?? '' ),
            'status'            => $this->map_erp_status( $erp_product['status'] ?? 'active' ),
            'categories'        => $erp_product['categories'] ?? [],
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
