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
	// El botón flotante lo renderiza storefront (dsam-floating-wa) con la misma URL.
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
	if ( ! function_exists( 'mi_cliente_storefront_resolve_whatsapp_url' ) ) {
		return;
	}
	if ( ! mi_cliente_storefront_resolve_whatsapp_url() ) {
		return;
	}
	// Estilos del botón flotante: clases Tailwind compiladas en tailwind.css.
}
add_action( 'wp_enqueue_scripts', 'mi_cliente_theme_whatsapp_assets' );
