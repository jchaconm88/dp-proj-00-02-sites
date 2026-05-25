<?php
/**
 * Admin Settings Page.
 *
 * Provides a WordPress admin interface for configuring the
 * ERP integration plugin settings.
 *
 * @package AgenciaERP
 */

namespace AgenciaERP;

defined( 'ABSPATH' ) || exit;

/**
 * Class AdminSettings
 *
 * Registers the admin menu page and handles settings registration,
 * rendering, and sanitization. Credentials are stored encrypted.
 */
class AdminSettings {

    /**
     * Settings page slug.
     */
    private const PAGE_SLUG = 'erp-integration';

    /**
     * Settings option group.
     */
    private const OPTION_GROUP = 'erp_integration_settings';

    /**
     * Settings section ID.
     */
    private const SECTION_CONNECTION = 'erp_connection_section';

    /**
     * Sync settings section ID.
     */
    private const SECTION_SYNC = 'erp_sync_section';

    /**
     * Advanced settings section ID.
     */
    private const SECTION_ADVANCED = 'erp_advanced_section';

    /**
     * Register the admin menu and settings.
     */
    public function register(): void {
        add_menu_page(
            __( 'ERP Integration', 'wc-erp-integration' ),
            __( 'ERP Integration', 'wc-erp-integration' ),
            'manage_woocommerce',
            self::PAGE_SLUG,
            [ $this, 'render_settings_page' ],
            'dashicons-update',
            58
        );

        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    /**
     * Register all settings fields with the WordPress Settings API.
     */
    public function register_settings(): void {
        // --- Connection Section ---
        add_settings_section(
            self::SECTION_CONNECTION,
            __( 'Conexión API', 'wc-erp-integration' ),
            [ $this, 'render_connection_section' ],
            self::PAGE_SLUG
        );

        // API Base URL.
        register_setting( self::OPTION_GROUP, 'erp_api_base_url', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ] );
        add_settings_field(
            'erp_api_base_url',
            __( 'URL Base del API', 'wc-erp-integration' ),
            [ $this, 'render_text_field' ],
            self::PAGE_SLUG,
            self::SECTION_CONNECTION,
            [
                'id'          => 'erp_api_base_url',
                'description' => __( 'URL base del API del ERP (ej: https://erp.cliente.com/api/v1)', 'wc-erp-integration' ),
                'placeholder' => 'https://erp.ejemplo.com/api/v1',
            ]
        );

        // API Key (encrypted).
        register_setting( self::OPTION_GROUP, 'erp_api_key_encrypted', [
            'type'              => 'string',
            'sanitize_callback' => [ $this, 'sanitize_encrypt_credential' ],
            'default'           => '',
        ] );
        add_settings_field(
            'erp_api_key_encrypted',
            __( 'API Key', 'wc-erp-integration' ),
            [ $this, 'render_password_field' ],
            self::PAGE_SLUG,
            self::SECTION_CONNECTION,
            [
                'id'          => 'erp_api_key_encrypted',
                'description' => __( 'Clave de API proporcionada por el ERP. Se almacena encriptada.', 'wc-erp-integration' ),
            ]
        );

        // API Secret (encrypted).
        register_setting( self::OPTION_GROUP, 'erp_api_secret_encrypted', [
            'type'              => 'string',
            'sanitize_callback' => [ $this, 'sanitize_encrypt_credential' ],
            'default'           => '',
        ] );
        add_settings_field(
            'erp_api_secret_encrypted',
            __( 'API Secret', 'wc-erp-integration' ),
            [ $this, 'render_password_field' ],
            self::PAGE_SLUG,
            self::SECTION_CONNECTION,
            [
                'id'          => 'erp_api_secret_encrypted',
                'description' => __( 'Secreto de API proporcionado por el ERP. Se almacena encriptado.', 'wc-erp-integration' ),
            ]
        );

        // Webhook Secret (encrypted).
        register_setting( self::OPTION_GROUP, 'erp_webhook_secret_encrypted', [
            'type'              => 'string',
            'sanitize_callback' => [ $this, 'sanitize_encrypt_credential' ],
            'default'           => '',
        ] );
        add_settings_field(
            'erp_webhook_secret_encrypted',
            __( 'Webhook Secret', 'wc-erp-integration' ),
            [ $this, 'render_password_field' ],
            self::PAGE_SLUG,
            self::SECTION_CONNECTION,
            [
                'id'          => 'erp_webhook_secret_encrypted',
                'description' => __( 'Secreto para validar webhooks entrantes del ERP.', 'wc-erp-integration' ),
            ]
        );

        // --- Sync Section ---
        add_settings_section(
            self::SECTION_SYNC,
            __( 'Intervalos de Sincronización', 'wc-erp-integration' ),
            [ $this, 'render_sync_section' ],
            self::PAGE_SLUG
        );

        // Stock sync interval.
        register_setting( self::OPTION_GROUP, 'erp_sync_interval_stock', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 300,
        ] );
        add_settings_field(
            'erp_sync_interval_stock',
            __( 'Intervalo Sync Stock (seg)', 'wc-erp-integration' ),
            [ $this, 'render_number_field' ],
            self::PAGE_SLUG,
            self::SECTION_SYNC,
            [
                'id'          => 'erp_sync_interval_stock',
                'description' => __( 'Intervalo en segundos para sincronizar inventario. Por defecto: 300 (5 min).', 'wc-erp-integration' ),
                'min'         => 60,
                'max'         => 86400,
                'default'     => 300,
            ]
        );

        // Products sync interval.
        register_setting( self::OPTION_GROUP, 'erp_sync_interval_products', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 3600,
        ] );
        add_settings_field(
            'erp_sync_interval_products',
            __( 'Intervalo Sync Productos (seg)', 'wc-erp-integration' ),
            [ $this, 'render_number_field' ],
            self::PAGE_SLUG,
            self::SECTION_SYNC,
            [
                'id'          => 'erp_sync_interval_products',
                'description' => __( 'Intervalo en segundos para sincronizar catálogo. Por defecto: 3600 (1 hora).', 'wc-erp-integration' ),
                'min'         => 300,
                'max'         => 86400,
                'default'     => 3600,
            ]
        );

        // Prices sync interval.
        register_setting( self::OPTION_GROUP, 'erp_sync_interval_prices', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 1800,
        ] );
        add_settings_field(
            'erp_sync_interval_prices',
            __( 'Intervalo Sync Precios (seg)', 'wc-erp-integration' ),
            [ $this, 'render_number_field' ],
            self::PAGE_SLUG,
            self::SECTION_SYNC,
            [
                'id'          => 'erp_sync_interval_prices',
                'description' => __( 'Intervalo en segundos para sincronizar precios. Por defecto: 1800 (30 min).', 'wc-erp-integration' ),
                'min'         => 300,
                'max'         => 86400,
                'default'     => 1800,
            ]
        );

        // Batch size.
        register_setting( self::OPTION_GROUP, 'erp_batch_size', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 50,
        ] );
        add_settings_field(
            'erp_batch_size',
            __( 'Tamaño de Lote', 'wc-erp-integration' ),
            [ $this, 'render_number_field' ],
            self::PAGE_SLUG,
            self::SECTION_SYNC,
            [
                'id'          => 'erp_batch_size',
                'description' => __( 'Número de elementos a procesar por lote de sincronización.', 'wc-erp-integration' ),
                'min'         => 10,
                'max'         => 500,
                'default'     => 50,
            ]
        );

        // --- Advanced Section ---
        add_settings_section(
            self::SECTION_ADVANCED,
            __( 'Configuración Avanzada', 'wc-erp-integration' ),
            [ $this, 'render_advanced_section' ],
            self::PAGE_SLUG
        );

        // Max retry attempts.
        register_setting( self::OPTION_GROUP, 'erp_retry_max_attempts', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 3,
        ] );
        add_settings_field(
            'erp_retry_max_attempts',
            __( 'Máximo Reintentos', 'wc-erp-integration' ),
            [ $this, 'render_number_field' ],
            self::PAGE_SLUG,
            self::SECTION_ADVANCED,
            [
                'id'          => 'erp_retry_max_attempts',
                'description' => __( 'Número máximo de reintentos para operaciones fallidas.', 'wc-erp-integration' ),
                'min'         => 1,
                'max'         => 10,
                'default'     => 3,
            ]
        );

        // Backoff intervals.
        register_setting( self::OPTION_GROUP, 'erp_retry_backoff_seconds', [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize_backoff_intervals' ],
            'default'           => [ 30, 120, 600 ],
        ] );
        add_settings_field(
            'erp_retry_backoff_seconds',
            __( 'Intervalos de Backoff (seg)', 'wc-erp-integration' ),
            [ $this, 'render_text_field' ],
            self::PAGE_SLUG,
            self::SECTION_ADVANCED,
            [
                'id'          => 'erp_retry_backoff_seconds',
                'description' => __( 'Intervalos de espera entre reintentos, separados por coma (ej: 30,120,600).', 'wc-erp-integration' ),
                'placeholder' => '30,120,600',
                'is_array'    => true,
            ]
        );

        // Log level.
        register_setting( self::OPTION_GROUP, 'erp_log_level', [
            'type'              => 'string',
            'sanitize_callback' => [ $this, 'sanitize_log_level' ],
            'default'           => 'info',
        ] );
        add_settings_field(
            'erp_log_level',
            __( 'Nivel de Log', 'wc-erp-integration' ),
            [ $this, 'render_select_field' ],
            self::PAGE_SLUG,
            self::SECTION_ADVANCED,
            [
                'id'          => 'erp_log_level',
                'description' => __( 'Nivel mínimo de mensajes a registrar en el log.', 'wc-erp-integration' ),
                'options'     => [
                    'debug'   => __( 'Debug', 'wc-erp-integration' ),
                    'info'    => __( 'Info', 'wc-erp-integration' ),
                    'warning' => __( 'Warning', 'wc-erp-integration' ),
                    'error'   => __( 'Error', 'wc-erp-integration' ),
                ],
            ]
        );

        // Conflict strategy.
        register_setting( self::OPTION_GROUP, 'erp_conflict_strategy', [
            'type'              => 'string',
            'sanitize_callback' => [ $this, 'sanitize_conflict_strategy' ],
            'default'           => 'erp_wins',
        ] );
        add_settings_field(
            'erp_conflict_strategy',
            __( 'Estrategia de Conflictos', 'wc-erp-integration' ),
            [ $this, 'render_select_field' ],
            self::PAGE_SLUG,
            self::SECTION_ADVANCED,
            [
                'id'          => 'erp_conflict_strategy',
                'description' => __( 'Cómo resolver conflictos de datos entre WooCommerce y el ERP.', 'wc-erp-integration' ),
                'options'     => [
                    'erp_wins'      => __( 'ERP siempre gana', 'wc-erp-integration' ),
                    'manual_review' => __( 'Revisión manual', 'wc-erp-integration' ),
                ],
            ]
        );

        // Enable webhooks.
        register_setting( self::OPTION_GROUP, 'erp_enable_webhooks', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => true,
        ] );
        add_settings_field(
            'erp_enable_webhooks',
            __( 'Habilitar Webhooks', 'wc-erp-integration' ),
            [ $this, 'render_checkbox_field' ],
            self::PAGE_SLUG,
            self::SECTION_ADVANCED,
            [
                'id'          => 'erp_enable_webhooks',
                'description' => __( 'Recibir notificaciones push del ERP vía webhooks.', 'wc-erp-integration' ),
            ]
        );

        // Fallback on failure.
        register_setting( self::OPTION_GROUP, 'erp_fallback_on_failure', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => true,
        ] );
        add_settings_field(
            'erp_fallback_on_failure',
            __( 'Fallback en Fallo', 'wc-erp-integration' ),
            [ $this, 'render_checkbox_field' ],
            self::PAGE_SLUG,
            self::SECTION_ADVANCED,
            [
                'id'          => 'erp_fallback_on_failure',
                'description' => __( 'Usar datos cacheados si el ERP no responde.', 'wc-erp-integration' ),
            ]
        );
    }

    /**
     * Render the main settings page.
     */
    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        // Handle health check action.
        $health_status = null;
        if ( isset( $_GET['action'] ) && 'health_check' === $_GET['action'] ) {
            check_admin_referer( 'erp_health_check' );
            $erp_client = Plugin::get_instance()->get_erp_client();
            if ( $erp_client ) {
                $health_status = $erp_client->health_check();
            }
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'ERP Integration Settings', 'wc-erp-integration' ); ?></h1>

            <?php if ( $health_status ) : ?>
                <div class="notice notice-<?php echo 'healthy' === $health_status['status'] ? 'success' : 'warning'; ?>">
                    <p>
                        <?php
                        printf(
                            /* translators: 1: status, 2: latency */
                            esc_html__( 'Estado del ERP: %1$s (latencia: %2$d ms)', 'wc-erp-integration' ),
                            esc_html( $health_status['status'] ),
                            (int) $health_status['latency_ms']
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php
                settings_fields( self::OPTION_GROUP );
                do_settings_sections( self::PAGE_SLUG );
                submit_button( __( 'Guardar Configuración', 'wc-erp-integration' ) );
                ?>
            </form>

            <hr>
            <h2><?php esc_html_e( 'Diagnóstico', 'wc-erp-integration' ); ?></h2>
            <p>
                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&action=health_check' ), 'erp_health_check' ) ); ?>" class="button button-secondary">
                    <?php esc_html_e( 'Verificar Conexión', 'wc-erp-integration' ); ?>
                </a>
            </p>

            <?php $this->render_queue_stats(); ?>
        </div>
        <?php
    }

    /**
     * Render connection section description.
     */
    public function render_connection_section(): void {
        echo '<p>' . esc_html__( 'Configure las credenciales de conexión al API del ERP. Las claves se almacenan encriptadas.', 'wc-erp-integration' ) . '</p>';
    }

    /**
     * Render sync section description.
     */
    public function render_sync_section(): void {
        echo '<p>' . esc_html__( 'Configure los intervalos de sincronización automática entre WooCommerce y el ERP.', 'wc-erp-integration' ) . '</p>';
    }

    /**
     * Render advanced section description.
     */
    public function render_advanced_section(): void {
        echo '<p>' . esc_html__( 'Configuración avanzada de reintentos, logging y resolución de conflictos.', 'wc-erp-integration' ) . '</p>';
    }

    /**
     * Render a text input field.
     *
     * @param array $args Field arguments.
     */
    public function render_text_field( array $args ): void {
        $id    = $args['id'];
        $value = get_option( $id, '' );

        // Handle array values (display as comma-separated).
        if ( ! empty( $args['is_array'] ) && is_array( $value ) ) {
            $value = implode( ',', $value );
        }

        printf(
            '<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text" placeholder="%3$s">',
            esc_attr( $id ),
            esc_attr( $value ),
            esc_attr( $args['placeholder'] ?? '' )
        );

        if ( ! empty( $args['description'] ) ) {
            printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
        }
    }

    /**
     * Render a password input field.
     *
     * @param array $args Field arguments.
     */
    public function render_password_field( array $args ): void {
        $id        = $args['id'];
        $has_value = ! empty( get_option( $id, '' ) );

        printf(
            '<input type="password" id="%1$s" name="%1$s" value="" class="regular-text" placeholder="%2$s" autocomplete="new-password">',
            esc_attr( $id ),
            $has_value ? esc_attr__( '••••••••  (guardado)', 'wc-erp-integration' ) : ''
        );

        if ( ! empty( $args['description'] ) ) {
            printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
        }

        if ( $has_value ) {
            printf(
                '<p class="description"><em>%s</em></p>',
                esc_html__( 'Deje en blanco para mantener el valor actual.', 'wc-erp-integration' )
            );
        }
    }

    /**
     * Render a number input field.
     *
     * @param array $args Field arguments.
     */
    public function render_number_field( array $args ): void {
        $id      = $args['id'];
        $value   = get_option( $id, $args['default'] ?? 0 );
        $min     = $args['min'] ?? 0;
        $max     = $args['max'] ?? 999999;

        printf(
            '<input type="number" id="%1$s" name="%1$s" value="%2$d" min="%3$d" max="%4$d" class="small-text">',
            esc_attr( $id ),
            (int) $value,
            (int) $min,
            (int) $max
        );

        if ( ! empty( $args['description'] ) ) {
            printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
        }
    }

    /**
     * Render a select dropdown field.
     *
     * @param array $args Field arguments.
     */
    public function render_select_field( array $args ): void {
        $id      = $args['id'];
        $value   = get_option( $id, '' );
        $options = $args['options'] ?? [];

        printf( '<select id="%1$s" name="%1$s">', esc_attr( $id ) );
        foreach ( $options as $option_value => $option_label ) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr( $option_value ),
                selected( $value, $option_value, false ),
                esc_html( $option_label )
            );
        }
        echo '</select>';

        if ( ! empty( $args['description'] ) ) {
            printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
        }
    }

    /**
     * Render a checkbox field.
     *
     * @param array $args Field arguments.
     */
    public function render_checkbox_field( array $args ): void {
        $id    = $args['id'];
        $value = get_option( $id, true );

        printf(
            '<input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s>',
            esc_attr( $id ),
            checked( $value, true, false )
        );

        if ( ! empty( $args['description'] ) ) {
            printf( '<label for="%1$s"> %2$s</label>', esc_attr( $id ), esc_html( $args['description'] ) );
        }
    }

    /**
     * Render queue statistics section.
     */
    private function render_queue_stats(): void {
        $sync_queue = Plugin::get_instance()->get_sync_queue();
        if ( ! $sync_queue ) {
            return;
        }

        $stats = $sync_queue->get_stats();

        ?>
        <h2><?php esc_html_e( 'Cola de Sincronización', 'wc-erp-integration' ); ?></h2>
        <table class="widefat striped" style="max-width: 400px;">
            <tbody>
                <tr>
                    <td><?php esc_html_e( 'Pendientes', 'wc-erp-integration' ); ?></td>
                    <td><strong><?php echo (int) $stats['pending']; ?></strong></td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'En proceso', 'wc-erp-integration' ); ?></td>
                    <td><strong><?php echo (int) $stats['processing']; ?></strong></td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Fallidos', 'wc-erp-integration' ); ?></td>
                    <td><strong style="color: #d63638;"><?php echo (int) $stats['failed']; ?></strong></td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Completados', 'wc-erp-integration' ); ?></td>
                    <td><strong style="color: #00a32a;"><?php echo (int) $stats['completed']; ?></strong></td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * Sanitize and encrypt a credential value.
     *
     * If the submitted value is empty, keep the existing stored value.
     *
     * @param string $value Submitted value.
     * @return string Encrypted value or existing value.
     */
    public function sanitize_encrypt_credential( string $value ): string {
        if ( empty( $value ) ) {
            // Determine which option we're saving by checking the current filter.
            $option_name = str_replace( 'sanitize_option_', '', current_filter() );
            return get_option( $option_name, '' );
        }

        return ERPClient::encrypt_credential( sanitize_text_field( $value ) );
    }

    /**
     * Sanitize backoff intervals input.
     *
     * Accepts comma-separated integers and returns an array.
     *
     * @param mixed $value Input value (string or array).
     * @return int[] Array of integer intervals.
     */
    public function sanitize_backoff_intervals( $value ): array {
        if ( is_array( $value ) ) {
            return array_map( 'absint', $value );
        }

        if ( is_string( $value ) ) {
            $parts = explode( ',', $value );
            return array_map( 'absint', array_filter( array_map( 'trim', $parts ) ) );
        }

        return [ 30, 120, 600 ];
    }

    /**
     * Sanitize log level value.
     *
     * @param string $value Submitted log level.
     * @return string Valid log level.
     */
    public function sanitize_log_level( string $value ): string {
        $valid_levels = [ 'debug', 'info', 'warning', 'error' ];
        return in_array( $value, $valid_levels, true ) ? $value : 'info';
    }

    /**
     * Sanitize conflict strategy value.
     *
     * @param string $value Submitted strategy.
     * @return string Valid strategy.
     */
    public function sanitize_conflict_strategy( string $value ): string {
        $valid = [ 'erp_wins', 'manual_review' ];
        return in_array( $value, $valid, true ) ? $value : 'erp_wins';
    }
}
