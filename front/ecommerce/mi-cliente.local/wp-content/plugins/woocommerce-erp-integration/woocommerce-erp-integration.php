<?php
/**
 * Plugin Name: WooCommerce ERP Integration
 * Plugin URI:  https://agencia.example.com/plugins/erp-integration
 * Description: Capa de integración bidireccional con ERP propio del cliente.
 * Version:     1.0.0
 * Author:      Agencia Digital
 * Author URI:  https://agencia.example.com
 * Text Domain: wc-erp-integration
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 7.0
 *
 * @package AgenciaERP
 */

namespace AgenciaERP;

defined( 'ABSPATH' ) || exit;

define( 'AGENCIA_ERP_VERSION', '1.0.0' );
define( 'AGENCIA_ERP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AGENCIA_ERP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AGENCIA_ERP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for plugin classes.
 *
 * Maps AgenciaERP namespace to the includes/ directory.
 */
spl_autoload_register( function ( $class ) {
    $prefix = 'AgenciaERP\\';

    if ( strpos( $class, $prefix ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, strlen( $prefix ) );

    // Convert namespace separators and class name to file path.
    $file_parts = explode( '\\', $relative_class );
    $class_name = array_pop( $file_parts );

    // Convert CamelCase to kebab-case for file naming.
    // First: insert hyphen between consecutive uppercase letters followed by lowercase (e.g., ERPClient → ERP-Client).
    $kebab = preg_replace( '/([A-Z]+)([A-Z][a-z])/', '$1-$2', $class_name );
    // Second: insert hyphen between lowercase/digit and uppercase (e.g., syncService → sync-Service).
    $kebab = preg_replace( '/([a-z\d])([A-Z])/', '$1-$2', $kebab );
    $file_name = 'class-' . strtolower( $kebab ) . '.php';

    $path = AGENCIA_ERP_PLUGIN_DIR . 'includes/';
    if ( ! empty( $file_parts ) ) {
        $path .= strtolower( implode( '/', $file_parts ) ) . '/';
    }
    $path .= $file_name;

    if ( file_exists( $path ) ) {
        require_once $path;
    }
} );

/**
 * Main plugin class.
 *
 * Bootstraps the ERP integration plugin, registers hooks,
 * and initializes core components.
 */
final class Plugin {

    /**
     * Singleton instance.
     *
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * ERP client instance.
     *
     * @var ERPClient|null
     */
    private ?ERPClient $erp_client = null;

    /**
     * Sync queue instance.
     *
     * @var SyncQueue|null
     */
    private ?SyncQueue $sync_queue = null;

    /**
     * Conflict resolver instance.
     *
     * @var ConflictResolver|null
     */
    private ?ConflictResolver $conflict_resolver = null;

    /**
     * Sync service instance.
     *
     * @var SyncService|null
     */
    private ?SyncService $sync_service = null;

    /**
     * Order sync instance.
     *
     * @var OrderSync|null
     */
    private ?OrderSync $order_sync = null;

    /**
     * Customer sync instance.
     *
     * @var CustomerSync|null
     */
    private ?CustomerSync $customer_sync = null;

    /**
     * Webhook handler instance.
     *
     * @var WebhookHandler|null
     */
    private ?WebhookHandler $webhook_handler = null;

    /**
     * Connection monitor instance.
     *
     * @var ConnectionMonitor|null
     */
    private ?ConnectionMonitor $connection_monitor = null;

    /**
     * Get singleton instance.
     *
     * @return Plugin
     */
    public static function get_instance(): Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to enforce singleton.
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Register activation and deactivation hooks, and runtime actions.
     */
    private function init_hooks(): void {
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );

        add_action( 'plugins_loaded', [ $this, 'init' ] );
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_front_styles' ] );
        add_filter( 'image_sideload_extensions', [ $this, 'allow_avif_sideload_extensions' ] );
    }

    /**
     * Allow AVIF when importing ERP image URLs into the Media Library.
     *
     * WordPress core sideload only whitelists jpg/png/gif/webp by default.
     *
     * @param string[] $extensions Allowed file extensions.
     * @return string[]
     */
    public function allow_avif_sideload_extensions( array $extensions ): array {
        if ( ! in_array( 'avif', $extensions, true ) ) {
            $extensions[] = 'avif';
        }
        return $extensions;
    }

    /**
     * Plugin activation callback.
     *
     * Creates the sync queue database table (sin crons de sync — event-driven).
     */
    public function activate(): void {
        $this->create_sync_queue_table();
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation callback.
     *
     * Clears any remaining scheduled cron events.
     */
    public function deactivate(): void {
        wp_clear_scheduled_hook( 'erp_sync_stock_cron' );
        wp_clear_scheduled_hook( 'erp_sync_products_cron' );
        wp_clear_scheduled_hook( 'erp_sync_prices_cron' );
        wp_clear_scheduled_hook( 'erp_process_sync_queue' );
        wp_clear_scheduled_hook( 'erp_connection_check' );
        flush_rewrite_rules();
    }

    /**
     * Initialize plugin components after all plugins are loaded.
     */
    public function init(): void {
        // Verify WooCommerce is active.
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', [ $this, 'woocommerce_missing_notice' ] );
            return;
        }

        $this->erp_client       = new ERPClient();
        $this->sync_queue       = new SyncQueue();
        $this->conflict_resolver = new ConflictResolver();

        // Initialize sync service.
        $this->sync_service = new SyncService( $this->erp_client, $this->sync_queue );
        $this->sync_service->init();

        // Initialize order sync.
        $this->order_sync = new OrderSync( $this->erp_client, $this->sync_queue );
        $this->order_sync->init();

        // Initialize customer sync.
        $this->customer_sync = new CustomerSync( $this->erp_client, $this->sync_queue );
        $this->customer_sync->init();

        // Initialize webhook handler.
        $this->webhook_handler = new WebhookHandler();
        $this->webhook_handler->set_sync_service( $this->sync_service );
        $this->webhook_handler->init();

        // Register cron handler for queued WC→ERP operations (event-driven retry).
        add_action( 'erp_process_sync_queue', [ $this->sync_queue, 'process_queue' ] );

        // Initialize passive connection monitor (sin cron).
        $this->connection_monitor = new ConnectionMonitor( $this->erp_client, $this->sync_queue );
        $this->connection_monitor->set_sync_service( $this->sync_service );
        $this->connection_monitor->init();
    }

    /**
     * Hide variation summary attributes duplicated by some themes.
     *
     * Some themes render the selected attributes ("Color: Azul", "Talla: S")
     * and also render the dropdowns, which looks duplicated.
     */
    public function enqueue_front_styles(): void {
        if ( ! function_exists( 'is_product' ) || ! is_product() ) {
            return;
        }

        $css = '
            .single-product .single_variation .woocommerce-variation-attributes,
            .single-product .single_variation .woocommerce-variation-attributes-wrapper {
                display: none !important;
            }
            .single-product .single_variation dl.variation,
            .single-product .single_variation .woocommerce-variation-description dl.variation,
            .single-product .single_variation .woocommerce-variation-description .variation {
                display: none !important;
            }
        ';

        wp_register_style( 'agencia-erp-frontend', false, [], AGENCIA_ERP_VERSION );
        wp_enqueue_style( 'agencia-erp-frontend' );
        wp_add_inline_style( 'agencia-erp-frontend', $css );
    }

    /**
     * Register admin settings page.
     */
    public function register_admin_menu(): void {
        $admin_settings = new AdminSettings();
        $admin_settings->register();
    }

    /**
     * Display admin notice when WooCommerce is not active.
     */
    public function woocommerce_missing_notice(): void {
        echo '<div class="notice notice-error"><p>';
        esc_html_e(
            'WooCommerce ERP Integration requiere WooCommerce activo.',
            'wc-erp-integration'
        );
        echo '</p></div>';
    }

    /**
     * Get the ERP client instance.
     *
     * @return ERPClient|null
     */
    public function get_erp_client(): ?ERPClient {
        return $this->erp_client;
    }

    /**
     * Get the sync queue instance.
     *
     * @return SyncQueue|null
     */
    public function get_sync_queue(): ?SyncQueue {
        return $this->sync_queue;
    }

    /**
     * Get the conflict resolver instance.
     *
     * @return ConflictResolver|null
     */
    public function get_conflict_resolver(): ?ConflictResolver {
        return $this->conflict_resolver;
    }

    /**
     * Get the sync service instance.
     *
     * @return SyncService|null
     */
    public function get_sync_service(): ?SyncService {
        return $this->sync_service;
    }

    /**
     * Get the order sync instance.
     *
     * @return OrderSync|null
     */
    public function get_order_sync(): ?OrderSync {
        return $this->order_sync;
    }

    /**
     * Get the customer sync instance.
     *
     * @return CustomerSync|null
     */
    public function get_customer_sync(): ?CustomerSync {
        return $this->customer_sync;
    }

    /**
     * Get the webhook handler instance.
     *
     * @return WebhookHandler|null
     */
    public function get_webhook_handler(): ?WebhookHandler {
        return $this->webhook_handler;
    }

    /**
     * Get the connection monitor instance.
     *
     * @return ConnectionMonitor|null
     */
    public function get_connection_monitor(): ?ConnectionMonitor {
        return $this->connection_monitor;
    }

    /**
     * Create the sync queue database table.
     */
    private function create_sync_queue_table(): void {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'erp_sync_queue';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            operation_type VARCHAR(50) NOT NULL,
            payload LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            max_retries INT UNSIGNED NOT NULL DEFAULT 3,
            next_retry_at DATETIME NULL,
            error_message TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_next_retry (next_retry_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

}

// Initialize the plugin.
Plugin::get_instance();
