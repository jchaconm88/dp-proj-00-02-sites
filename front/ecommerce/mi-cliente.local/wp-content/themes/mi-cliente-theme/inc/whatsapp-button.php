<?php
/**
 * WhatsApp Floating Button - Contact integration with admin configuration.
 *
 * Implements a floating WhatsApp button with:
 * - Fixed position bottom-right, z-index 9999, min 48x48px touch area
 * - Product pages: message includes product name + URL
 * - Other pages: generic consultation message
 * - Official WhatsApp icon, WCAG 2.1 AA contrast (4.5:1 minimum)
 * - Mobile: no overlap with navigation or action buttons
 * - Admin panel: phone number, message templates, excluded pages
 * - Hidden if phone number not configured (no JS errors, no empty HTML)
 *
 * @package Mi_Cliente_Theme
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register WhatsApp settings in the admin.
 *
 * Creates a settings page under Appearance for configuring the WhatsApp button.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_whatsapp_admin_menu() {
    add_theme_page(
        __( 'WhatsApp Button', 'mi-cliente-theme' ),
        __( 'WhatsApp', 'mi-cliente-theme' ),
        'manage_options',
        'mi-cliente-whatsapp',
        'mi_cliente_theme_whatsapp_settings_page'
    );
}
add_action( 'admin_menu', 'mi_cliente_theme_whatsapp_admin_menu' );

/**
 * Register WhatsApp settings.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_whatsapp_register_settings() {
    register_setting( 'mi_cliente_whatsapp', 'mi_cliente_whatsapp_phone', array(
        'type'              => 'string',
        'sanitize_callback' => 'mi_cliente_theme_sanitize_phone',
        'default'           => '',
    ) );

    register_setting( 'mi_cliente_whatsapp', 'mi_cliente_whatsapp_product_message', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_textarea_field',
        'default'           => __( 'Hola, me interesa el producto: {product_name} ({product_url})', 'mi-cliente-theme' ),
    ) );

    register_setting( 'mi_cliente_whatsapp', 'mi_cliente_whatsapp_generic_message', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_textarea_field',
        'default'           => __( 'Hola, me gustaría hacer una consulta.', 'mi-cliente-theme' ),
    ) );

    register_setting( 'mi_cliente_whatsapp', 'mi_cliente_whatsapp_excluded_pages', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_textarea_field',
        'default'           => '',
    ) );

    // Settings sections and fields.
    add_settings_section(
        'mi_cliente_whatsapp_main',
        __( 'Configuración del botón de WhatsApp', 'mi-cliente-theme' ),
        '__return_false',
        'mi-cliente-whatsapp'
    );

    add_settings_field(
        'mi_cliente_whatsapp_phone',
        __( 'Número de teléfono', 'mi-cliente-theme' ),
        'mi_cliente_theme_whatsapp_phone_field',
        'mi-cliente-whatsapp',
        'mi_cliente_whatsapp_main'
    );

    add_settings_field(
        'mi_cliente_whatsapp_product_message',
        __( 'Mensaje para productos', 'mi-cliente-theme' ),
        'mi_cliente_theme_whatsapp_product_message_field',
        'mi-cliente-whatsapp',
        'mi_cliente_whatsapp_main'
    );

    add_settings_field(
        'mi_cliente_whatsapp_generic_message',
        __( 'Mensaje genérico', 'mi-cliente-theme' ),
        'mi_cliente_theme_whatsapp_generic_message_field',
        'mi-cliente-whatsapp',
        'mi_cliente_whatsapp_main'
    );

    add_settings_field(
        'mi_cliente_whatsapp_excluded_pages',
        __( 'Páginas excluidas', 'mi-cliente-theme' ),
        'mi_cliente_theme_whatsapp_excluded_pages_field',
        'mi-cliente-whatsapp',
        'mi_cliente_whatsapp_main'
    );
}
add_action( 'admin_init', 'mi_cliente_theme_whatsapp_register_settings' );


/**
 * Sanitize phone number to international format (digits only, with country code).
 *
 * @since 1.0.0
 *
 * @param string $phone The phone number input.
 * @return string Sanitized phone number (digits only).
 */
function mi_cliente_theme_sanitize_phone( $phone ) {
    // Remove everything except digits and leading +.
    $phone = preg_replace( '/[^\d+]/', '', $phone );
    // Remove + and keep only digits for WhatsApp API.
    $phone = ltrim( $phone, '+' );
    return $phone;
}

/**
 * Render phone number settings field.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_whatsapp_phone_field() {
    $value = get_option( 'mi_cliente_whatsapp_phone', '' );
    ?>
    <input type="text" name="mi_cliente_whatsapp_phone" id="mi_cliente_whatsapp_phone"
        value="<?php echo esc_attr( $value ); ?>" class="regular-text"
        placeholder="51999999999"
        aria-describedby="whatsapp-phone-description">
    <p class="description" id="whatsapp-phone-description">
        <?php esc_html_e( 'Número en formato internacional sin + ni espacios (ej: 51999999999). El botón no se mostrará si este campo está vacío.', 'mi-cliente-theme' ); ?>
    </p>
    <?php
}

/**
 * Render product message template field.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_whatsapp_product_message_field() {
    $value = get_option( 'mi_cliente_whatsapp_product_message', __( 'Hola, me interesa el producto: {product_name} ({product_url})', 'mi-cliente-theme' ) );
    ?>
    <textarea name="mi_cliente_whatsapp_product_message" id="mi_cliente_whatsapp_product_message"
        class="large-text" rows="3" aria-describedby="whatsapp-product-msg-description"><?php echo esc_textarea( $value ); ?></textarea>
    <p class="description" id="whatsapp-product-msg-description">
        <?php esc_html_e( 'Variables disponibles: {product_name}, {product_url}', 'mi-cliente-theme' ); ?>
    </p>
    <?php
}

/**
 * Render generic message field.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_whatsapp_generic_message_field() {
    $value = get_option( 'mi_cliente_whatsapp_generic_message', __( 'Hola, me gustaría hacer una consulta.', 'mi-cliente-theme' ) );
    ?>
    <textarea name="mi_cliente_whatsapp_generic_message" id="mi_cliente_whatsapp_generic_message"
        class="large-text" rows="3"><?php echo esc_textarea( $value ); ?></textarea>
    <?php
}

/**
 * Render excluded pages field.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_whatsapp_excluded_pages_field() {
    $value = get_option( 'mi_cliente_whatsapp_excluded_pages', '' );
    ?>
    <textarea name="mi_cliente_whatsapp_excluded_pages" id="mi_cliente_whatsapp_excluded_pages"
        class="large-text" rows="3" aria-describedby="whatsapp-excluded-description"><?php echo esc_textarea( $value ); ?></textarea>
    <p class="description" id="whatsapp-excluded-description">
        <?php esc_html_e( 'IDs de páginas separados por coma donde NO se mostrará el botón (ej: 10,25,42).', 'mi-cliente-theme' ); ?>
    </p>
    <?php
}


/**
 * Render the WhatsApp settings page.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_whatsapp_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Configuración del botón de WhatsApp', 'mi-cliente-theme' ); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'mi_cliente_whatsapp' );
            do_settings_sections( 'mi-cliente-whatsapp' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

/**
 * Render the floating WhatsApp button on the frontend.
 *
 * Only renders if phone number is configured and current page is not excluded.
 * Uses official WhatsApp icon SVG with WCAG 2.1 AA compliant colors.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_render_whatsapp_button() {
    // Do not render in admin.
    if ( is_admin() ) {
        return;
    }

    $phone = get_option( 'mi_cliente_whatsapp_phone', '' );

    // Hide button if phone number not configured - no HTML output, no JS errors.
    if ( empty( $phone ) ) {
        return;
    }

    // Check excluded pages.
    $excluded = get_option( 'mi_cliente_whatsapp_excluded_pages', '' );
    if ( ! empty( $excluded ) ) {
        $excluded_ids = array_map( 'absint', array_filter( explode( ',', $excluded ) ) );
        if ( is_page( $excluded_ids ) || is_single( $excluded_ids ) ) {
            return;
        }
    }

    // Build WhatsApp message based on page context.
    $message = mi_cliente_theme_get_whatsapp_message();
    $whatsapp_url = 'https://wa.me/' . esc_attr( $phone ) . '?text=' . rawurlencode( $message );
    ?>
    <a href="<?php echo esc_url( $whatsapp_url ); ?>"
        class="mi-cliente-whatsapp-btn"
        target="_blank"
        rel="noopener noreferrer"
        aria-label="<?php esc_attr_e( 'Contactar por WhatsApp', 'mi-cliente-theme' ); ?>"
        title="<?php esc_attr_e( 'Contactar por WhatsApp', 'mi-cliente-theme' ); ?>">
        <svg class="mi-cliente-whatsapp-btn__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="28" height="28" fill="currentColor" aria-hidden="true" focusable="false">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
        </svg>
    </a>
    <?php
}
add_action( 'wp_footer', 'mi_cliente_theme_render_whatsapp_button', 99 );


/**
 * Get the WhatsApp message based on current page context.
 *
 * Product pages: includes product name and URL.
 * Other pages: generic consultation message.
 *
 * @since 1.0.0
 *
 * @return string The formatted WhatsApp message.
 */
function mi_cliente_theme_get_whatsapp_message() {
    // Product page: include product name and URL.
    if ( function_exists( 'is_product' ) && is_product() ) {
        global $product;
        $template = get_option(
            'mi_cliente_whatsapp_product_message',
            __( 'Hola, me interesa el producto: {product_name} ({product_url})', 'mi-cliente-theme' )
        );

        $product_name = $product ? $product->get_name() : get_the_title();
        $product_url  = get_permalink();

        $message = str_replace(
            array( '{product_name}', '{product_url}' ),
            array( $product_name, $product_url ),
            $template
        );

        return $message;
    }

    // Generic message for all other pages.
    return get_option(
        'mi_cliente_whatsapp_generic_message',
        __( 'Hola, me gustaría hacer una consulta.', 'mi-cliente-theme' )
    );
}

/**
 * Enqueue WhatsApp button styles.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_whatsapp_assets() {
    $phone = get_option( 'mi_cliente_whatsapp_phone', '' );

    // Don't load assets if phone not configured.
    if ( empty( $phone ) ) {
        return;
    }

    wp_enqueue_style(
        'mi-cliente-whatsapp',
        get_template_directory_uri() . '/assets/css/whatsapp.css',
        array(),
        wp_get_theme()->get( 'Version' )
    );
}
add_action( 'wp_enqueue_scripts', 'mi_cliente_theme_whatsapp_assets' );
