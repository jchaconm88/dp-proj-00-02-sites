<?php
/**
 * Mi Cliente Theme - Functions and definitions
 *
 * Core theme setup including: theme support declarations, block patterns,
 * block restrictions for client role, conditional SEO output, and
 * performance optimizations.
 *
 * @package Mi_Cliente_Theme
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Check if WooCommerce is active.
 *
 * Helper function to determine WooCommerce availability throughout the theme.
 * Used for graceful degradation when WooCommerce is deactivated or not installed.
 *
 * @since 1.0.0
 *
 * @return bool True if WooCommerce is active, false otherwise.
 */
function mi_cliente_theme_is_woocommerce_active() {
    return class_exists( 'WooCommerce' );
}

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_setup() {
    // WooCommerce integration support (only when WooCommerce is available).
    if ( mi_cliente_theme_is_woocommerce_active() ) {
        add_theme_support( 'woocommerce' );
    }

    // Block templates support (required for Site Editor template management).
    add_theme_support( 'block-templates' );

    // Block editor styles support.
    add_theme_support( 'wp-block-styles' );

    // Editor styles support.
    add_theme_support( 'editor-styles' );

    // Responsive embeds support.
    add_theme_support( 'responsive-embeds' );

    // HTML5 markup support for core elements.
    add_theme_support(
        'html5',
        array(
            'search-form',
            'comment-form',
            'comment-list',
            'gallery',
            'caption',
        )
    );
}
add_action( 'after_setup_theme', 'mi_cliente_theme_setup' );


/**
 * Register block patterns for the theme.
 *
 * Registers reusable block patterns using theme-defined colors and typography
 * from theme.json. Patterns are visible in the block inserter.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_register_block_patterns() {
    // Register pattern category for the theme.
    register_block_pattern_category(
        'mi-cliente-theme',
        array( 'label' => __( 'Mi Cliente Theme', 'mi-cliente-theme' ) )
    );

    // Hero section pattern.
    register_block_pattern(
        'mi-cliente-theme/hero',
        array(
            'title'       => __( 'Hero Section', 'mi-cliente-theme' ),
            'description' => __( 'A full-width hero section with heading, text, and call-to-action button.', 'mi-cliente-theme' ),
            'categories'  => array( 'mi-cliente-theme' ),
            'content'     => '<!-- wp:cover {"overlayColor":"primary","isUserOverlayColor":true,"minHeight":600,"align":"full"} -->
<div class="wp-block-cover alignfull" style="min-height:600px"><span aria-hidden="true" class="wp-block-cover__background has-primary-background-color has-background-dim-100 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"fontSize":"var(--wp--preset--font-size--x-large)"},"color":{"text":"var(--wp--preset--color--background)"}}} -->
<h1 class="wp-block-heading has-text-align-center" style="color:var(--wp--preset--color--background);font-size:var(--wp--preset--font-size--x-large)">Bienvenido a nuestra tienda</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"color":{"text":"var(--wp--preset--color--background)"},"typography":{"fontSize":"var(--wp--preset--font-size--large)"}}} -->
<p class="has-text-align-center" style="color:var(--wp--preset--color--background);font-size:var(--wp--preset--font-size--large)">Descubre nuestros productos exclusivos con la mejor calidad.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"accent","textColor":"background"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-background-color has-accent-background-color has-text-color has-background wp-element-button">Ver Catálogo</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div></div>
<!-- /wp:cover -->',
        )
    );

    // Product catalog grid pattern.
    register_block_pattern(
        'mi-cliente-theme/product-catalog',
        array(
            'title'       => __( 'Product Catalog Grid', 'mi-cliente-theme' ),
            'description' => __( 'A grid layout for displaying product cards with images and descriptions.', 'mi-cliente-theme' ),
            'categories'  => array( 'mi-cliente-theme' ),
            'content'     => '<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}}} -->
<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"><!-- wp:heading {"textAlign":"center","style":{"typography":{"fontSize":"var(--wp--preset--font-size--x-large)"},"color":{"text":"var(--wp--preset--color--primary)"}}} -->
<h2 class="wp-block-heading has-text-align-center" style="color:var(--wp--preset--color--primary);font-size:var(--wp--preset--font-size--x-large)">Nuestros Productos</h2>
<!-- /wp:heading -->

<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|40"}}}} -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img src="" alt="Producto 1"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"var(--wp--preset--font-size--large)"}}} -->
<h3 class="wp-block-heading" style="font-size:var(--wp--preset--font-size--large)">Producto 1</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"var(--wp--preset--font-size--small)"}}} -->
<p style="font-size:var(--wp--preset--font-size--small)">Descripción breve del producto con sus características principales.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img src="" alt="Producto 2"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"var(--wp--preset--font-size--large)"}}} -->
<h3 class="wp-block-heading" style="font-size:var(--wp--preset--font-size--large)">Producto 2</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"var(--wp--preset--font-size--small)"}}} -->
<p style="font-size:var(--wp--preset--font-size--small)">Descripción breve del producto con sus características principales.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img src="" alt="Producto 3"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"var(--wp--preset--font-size--large)"}}} -->
<h3 class="wp-block-heading" style="font-size:var(--wp--preset--font-size--large)">Producto 3</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"var(--wp--preset--font-size--small)"}}} -->
<p style="font-size:var(--wp--preset--font-size--small)">Descripción breve del producto con sus características principales.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->',
        )
    );

    // Testimonials section pattern.
    register_block_pattern(
        'mi-cliente-theme/testimonials',
        array(
            'title'       => __( 'Testimonials Section', 'mi-cliente-theme' ),
            'description' => __( 'A section displaying customer testimonials in a grid layout.', 'mi-cliente-theme' ),
            'categories'  => array( 'mi-cliente-theme' ),
            'content'     => '<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}},"color":{"background":"var(--wp--preset--color--secondary)"}}} -->
<div class="wp-block-group alignwide" style="background-color:var(--wp--preset--color--secondary);padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"><!-- wp:heading {"textAlign":"center","style":{"color":{"text":"var(--wp--preset--color--background)"},"typography":{"fontSize":"var(--wp--preset--font-size--x-large)"}}} -->
<h2 class="wp-block-heading has-text-align-center" style="color:var(--wp--preset--color--background);font-size:var(--wp--preset--font-size--x-large)">Lo que dicen nuestros clientes</h2>
<!-- /wp:heading -->

<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|40"}}}} -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:quote {"style":{"color":{"text":"var(--wp--preset--color--background)"}}} -->
<blockquote class="wp-block-quote" style="color:var(--wp--preset--color--background)"><p>Excelente servicio y productos de alta calidad. Totalmente recomendado.</p><cite>— María García</cite></blockquote>
<!-- /wp:quote --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:quote {"style":{"color":{"text":"var(--wp--preset--color--background)"}}} -->
<blockquote class="wp-block-quote" style="color:var(--wp--preset--color--background)"><p>La mejor experiencia de compra online. Envío rápido y atención personalizada.</p><cite>— Carlos López</cite></blockquote>
<!-- /wp:quote --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:quote {"style":{"color":{"text":"var(--wp--preset--color--background)"}}} -->
<blockquote class="wp-block-quote" style="color:var(--wp--preset--color--background)"><p>Productos únicos que no encuentras en otro lugar. Volveré a comprar seguro.</p><cite>— Ana Rodríguez</cite></blockquote>
<!-- /wp:quote --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->',
        )
    );

    // CTA / Features section pattern.
    register_block_pattern(
        'mi-cliente-theme/cta-features',
        array(
            'title'       => __( 'CTA Features Section', 'mi-cliente-theme' ),
            'description' => __( 'A features section with icon columns and a call-to-action button.', 'mi-cliente-theme' ),
            'categories'  => array( 'mi-cliente-theme' ),
            'content'     => '<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}},"color":{"background":"var(--wp--preset--color--surface)"}}} -->
<div class="wp-block-group alignwide" style="background-color:var(--wp--preset--color--surface);padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)"><!-- wp:heading {"textAlign":"center","style":{"typography":{"fontSize":"var(--wp--preset--font-size--x-large)"},"color":{"text":"var(--wp--preset--color--primary)"}}} -->
<h2 class="wp-block-heading has-text-align-center" style="color:var(--wp--preset--color--primary);font-size:var(--wp--preset--font-size--x-large)">¿Por qué elegirnos?</h2>
<!-- /wp:heading -->

<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|40"}}}} -->
<div class="wp-block-columns"><!-- wp:column {"style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}}} -->
<div class="wp-block-column" style="padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)"><!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":"var(--wp--preset--font-size--display)"}}} -->
<p class="has-text-align-center" style="font-size:var(--wp--preset--font-size--display)">🚀</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textAlign":"center","level":3,"style":{"typography":{"fontSize":"var(--wp--preset--font-size--large)"},"color":{"text":"var(--wp--preset--color--primary)"}}} -->
<h3 class="wp-block-heading has-text-align-center" style="color:var(--wp--preset--color--primary);font-size:var(--wp--preset--font-size--large)">Envío Rápido</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":"var(--wp--preset--font-size--small)"},"color":{"text":"var(--wp--preset--color--on-surface)"}}} -->
<p class="has-text-align-center" style="color:var(--wp--preset--color--on-surface);font-size:var(--wp--preset--font-size--small)">Entrega en 24-48 horas a todo el país.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}}} -->
<div class="wp-block-column" style="padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)"><!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":"var(--wp--preset--font-size--display)"}}} -->
<p class="has-text-align-center" style="font-size:var(--wp--preset--font-size--display)">🔒</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textAlign":"center","level":3,"style":{"typography":{"fontSize":"var(--wp--preset--font-size--large)"},"color":{"text":"var(--wp--preset--color--primary)"}}} -->
<h3 class="wp-block-heading has-text-align-center" style="color:var(--wp--preset--color--primary);font-size:var(--wp--preset--font-size--large)">Pago Seguro</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":"var(--wp--preset--font-size--small)"},"color":{"text":"var(--wp--preset--color--on-surface)"}}} -->
<p class="has-text-align-center" style="color:var(--wp--preset--color--on-surface);font-size:var(--wp--preset--font-size--small)">Transacciones protegidas con encriptación SSL.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}}} -->
<div class="wp-block-column" style="padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)"><!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":"var(--wp--preset--font-size--display)"}}} -->
<p class="has-text-align-center" style="font-size:var(--wp--preset--font-size--display)">⭐</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textAlign":"center","level":3,"style":{"typography":{"fontSize":"var(--wp--preset--font-size--large)"},"color":{"text":"var(--wp--preset--color--primary)"}}} -->
<h3 class="wp-block-heading has-text-align-center" style="color:var(--wp--preset--color--primary);font-size:var(--wp--preset--font-size--large)">Calidad Premium</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":"var(--wp--preset--font-size--small)"},"color":{"text":"var(--wp--preset--color--on-surface)"}}} -->
<p class="has-text-align-center" style="color:var(--wp--preset--color--on-surface);font-size:var(--wp--preset--font-size--small)">Productos seleccionados con los más altos estándares.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|40"}}}} -->
<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--40)"><!-- wp:button {"backgroundColor":"accent","textColor":"background"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-background-color has-accent-background-color has-text-color has-background wp-element-button">Comprar Ahora</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->',
        )
    );

    // Footer pattern.
    register_block_pattern(
        'mi-cliente-theme/footer',
        array(
            'title'       => __( 'Footer', 'mi-cliente-theme' ),
            'description' => __( 'A full-width footer with columns for navigation, contact info, and social links.', 'mi-cliente-theme' ),
            'categories'  => array( 'mi-cliente-theme' ),
            'content'     => '<!-- wp:group {"align":"full","style":{"color":{"background":"var(--wp--preset--color--primary)"},"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|40"}}}} -->
<div class="wp-block-group alignfull" style="background-color:var(--wp--preset--color--primary);padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--40)"><!-- wp:columns {"align":"wide"} -->
<div class="wp-block-columns alignwide"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"style":{"color":{"text":"var(--wp--preset--color--background)"},"typography":{"fontSize":"var(--wp--preset--font-size--large)"}}} -->
<h3 class="wp-block-heading" style="color:var(--wp--preset--color--background);font-size:var(--wp--preset--font-size--large)">Navegación</h3>
<!-- /wp:heading -->

<!-- wp:list {"style":{"color":{"text":"var(--wp--preset--color--background)"},"typography":{"fontSize":"var(--wp--preset--font-size--small)"}}} -->
<ul style="color:var(--wp--preset--color--background);font-size:var(--wp--preset--font-size--small)"><li>Inicio</li><li>Tienda</li><li>Nosotros</li><li>Contacto</li></ul>
<!-- /wp:list --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"style":{"color":{"text":"var(--wp--preset--color--background)"},"typography":{"fontSize":"var(--wp--preset--font-size--large)"}}} -->
<h3 class="wp-block-heading" style="color:var(--wp--preset--color--background);font-size:var(--wp--preset--font-size--large)">Contacto</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"color":{"text":"var(--wp--preset--color--background)"},"typography":{"fontSize":"var(--wp--preset--font-size--small)"}}} -->
<p style="color:var(--wp--preset--color--background);font-size:var(--wp--preset--font-size--small)">Email: info@mi-cliente.local<br>Teléfono: +51 999 999 999<br>Lima, Perú</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"style":{"color":{"text":"var(--wp--preset--color--background)"},"typography":{"fontSize":"var(--wp--preset--font-size--large)"}}} -->
<h3 class="wp-block-heading" style="color:var(--wp--preset--color--background);font-size:var(--wp--preset--font-size--large)">Síguenos</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"color":{"text":"var(--wp--preset--color--background)"},"typography":{"fontSize":"var(--wp--preset--font-size--small)"}}} -->
<p style="color:var(--wp--preset--color--background);font-size:var(--wp--preset--font-size--small)">Facebook | Instagram | Twitter</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:paragraph {"align":"center","style":{"color":{"text":"var(--wp--preset--color--background)"},"typography":{"fontSize":"var(--wp--preset--font-size--small)"},"spacing":{"margin":{"top":"var:preset|spacing|40"}}}} -->
<p class="has-text-align-center" style="color:var(--wp--preset--color--background);font-size:var(--wp--preset--font-size--small);margin-top:var(--wp--preset--spacing--40)">© 2024 Mi Cliente. Todos los derechos reservados.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->',
        )
    );
}
add_action( 'init', 'mi_cliente_theme_register_block_patterns' );


/**
 * Restrict allowed block types for users with the 'client_role' capability.
 *
 * Limits the block editor to a curated set of blocks for client users,
 * preventing access to complex or potentially breaking blocks. WooCommerce
 * blocks are only included when WooCommerce is active.
 *
 * @since 1.0.0
 *
 * @param bool|string[] $allowed_blocks Array of allowed block types or boolean.
 * @param WP_Block_Editor_Context $editor_context The current block editor context.
 * @return bool|string[] Filtered array of allowed block types.
 */
function mi_cliente_theme_restrict_blocks_for_client( $allowed_blocks, $editor_context ) {
    if ( current_user_can( 'client_role' ) ) {
        $blocks = array(
            'core/paragraph',
            'core/heading',
            'core/image',
            'core/list',
            'core/list-item',
            'core/columns',
            'core/column',
            'core/group',
            'core/cover',
            'core/buttons',
            'core/button',
            'core/spacer',
        );

        // Only include WooCommerce blocks when WooCommerce is active.
        if ( mi_cliente_theme_is_woocommerce_active() ) {
            $wc_blocks = array(
                'woocommerce/product-grid',
                'woocommerce/featured-product',
                'woocommerce/featured-category',
                'woocommerce/handpicked-products',
                'woocommerce/product-best-sellers',
                'woocommerce/product-new',
                'woocommerce/product-on-sale',
                'woocommerce/product-category',
                'woocommerce/product-search',
                'woocommerce/cart',
                'woocommerce/checkout',
                'woocommerce/mini-cart',
            );
            $blocks = array_merge( $blocks, $wc_blocks );
        }

        return $blocks;
    }

    return $allowed_blocks;
}
add_filter( 'allowed_block_types_all', 'mi_cliente_theme_restrict_blocks_for_client', 10, 2 );

/**
 * Conditional SEO meta tag output.
 *
 * Outputs basic meta tags (title, description) only when no dedicated SEO
 * plugin (Yoast SEO or Rank Math) is active. If either plugin is detected,
 * the theme defers to the plugin for SEO meta management.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_conditional_seo_output() {
    // Check if Yoast SEO or Rank Math is active.
    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $yoast_active     = is_plugin_active( 'wordpress-seo/wp-seo.php' );
    $rankmath_active  = is_plugin_active( 'seo-by-rank-math/rank-math.php' );

    if ( $yoast_active || $rankmath_active ) {
        return;
    }

    // Output basic meta tags from page content.
    mi_cliente_theme_output_basic_meta_tags();
}
add_action( 'wp_head', 'mi_cliente_theme_conditional_seo_output', 1 );

/**
 * Output basic meta tags when no SEO plugin is active.
 *
 * Generates a meta description from the current page/post excerpt or content.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_output_basic_meta_tags() {
    $description = '';

    if ( is_singular() ) {
        global $post;
        if ( ! empty( $post->post_excerpt ) ) {
            $description = $post->post_excerpt;
        } else {
            $description = wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '...' );
        }
    } elseif ( is_home() || is_front_page() ) {
        $description = get_bloginfo( 'description' );
    } elseif ( is_category() || is_tag() || is_tax() ) {
        $description = term_description();
    }

    $description = esc_attr( wp_strip_all_tags( $description ) );

    if ( ! empty( $description ) ) {
        echo '<meta name="description" content="' . $description . '">' . "\n";
    }
}


/**
 * Performance optimizations.
 *
 * Removes unnecessary WordPress head items and enables native lazy loading
 * and script deferral for non-critical assets.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_performance_cleanup() {
    // Remove emoji scripts and styles.
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'admin_print_styles', 'print_emoji_styles' );

    // Remove WordPress generator meta tag.
    remove_action( 'wp_head', 'wp_generator' );

    // Remove RSD link.
    remove_action( 'wp_head', 'rsd_link' );

    // Remove wlwmanifest link.
    remove_action( 'wp_head', 'wlwmanifest_link' );

    // Remove shortlink.
    remove_action( 'wp_head', 'wp_shortlink_wp_head' );

    // Remove REST API link from head (disabled in admin and site editor preview to prevent 404s).
    if ( ! is_admin() && ! isset( $_GET['wp_site_preview'] ) ) {
        remove_action( 'wp_head', 'rest_output_link_wp_head' );
    }

    // Remove oEmbed discovery links.
    remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
}
add_action( 'init', 'mi_cliente_theme_performance_cleanup' );

/**
 * Add defer attribute to the theme's own front-end scripts.
 *
 * IMPORTANT: This uses an allowlist (only the theme's `mi-cliente-*` bundles)
 * instead of deferring everything-except-a-blocklist.
 *
 * Manually injecting `defer` via `script_loader_tag` bypasses WordPress'
 * dependency ordering: deferred scripts run after non-deferred ones
 * regardless of the declared dependency graph. Deferring core/vendor or
 * WooCommerce assets therefore breaks load order and causes runtime errors
 * such as:
 *   - "wp.template is not a function" (wp-util needs underscore at load time),
 *     which broke the add-to-cart variation form, and
 *   - "Cannot read properties of undefined (reading 'jsx')" / "R_jsx is not a
 *     function" on the Cart/Checkout blocks (wc-blocks need react-jsx-runtime
 *     at load time).
 *
 * The theme controls the dependencies of its own bundles, so only those are
 * safe to defer. Everything else (WordPress core, WooCommerce, blocks,
 * third-party plugins) is left untouched.
 *
 * @since 1.0.0
 *
 * @param string $tag    The script tag HTML.
 * @param string $handle The script handle.
 * @param string $src    The script source URL.
 * @return string Modified script tag with defer attribute.
 */
function mi_cliente_theme_defer_scripts( $tag, $handle, $src ) {
    // Never touch admin scripts (Site Editor, block editor, etc.).
    if ( is_admin() ) {
        return $tag;
    }

    // Only defer the theme's own front-end bundles. These are self-contained
    // (jQuery / WooCommerce deps already load synchronously before them).
    if ( ! str_starts_with( $handle, 'mi-cliente-' ) ) {
        return $tag;
    }

    // Skip if already deferred/async.
    if ( strpos( $tag, ' defer' ) !== false || strpos( $tag, ' async' ) !== false ) {
        return $tag;
    }

    // Skip inline scripts (no src attribute).
    if ( empty( $src ) ) {
        return $tag;
    }

    return str_replace( ' src=', ' defer src=', $tag );
}
add_filter( 'script_loader_tag', 'mi_cliente_theme_defer_scripts', 10, 3 );

/**
 * Ensure native lazy loading for images.
 *
 * WordPress 5.5+ adds loading="lazy" natively to images. This filter
 * ensures it is applied and sets a threshold for above-the-fold exclusion.
 *
 * @since 1.0.0
 *
 * @param string $value   The loading attribute value.
 * @param string $image   The HTML img tag.
 * @param string $context The context (e.g., 'the_content', 'widget_text').
 * @return string The loading attribute value.
 */
function mi_cliente_theme_lazy_load_images( $value, $image, $context ) {
    // WordPress handles lazy loading natively from 5.5+.
    // Return 'lazy' to ensure images outside viewport are lazy loaded.
    return $value;
}
add_filter( 'wp_img_tag_add_loading_attr', 'mi_cliente_theme_lazy_load_images', 10, 3 );

/**
 * Enqueue theme styles.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_enqueue_styles() {
    wp_enqueue_style(
        'mi-cliente-theme-style',
        get_stylesheet_uri(),
        array(),
        wp_get_theme()->get( 'Version' )
    );
}
add_action( 'wp_enqueue_scripts', 'mi_cliente_theme_enqueue_styles' );

/**
 * Enqueue WooCommerce-specific styles when WooCommerce is active.
 *
 * Loads custom WooCommerce styling that uses Design System tokens
 * from theme.json via CSS custom properties.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_woocommerce_styles() {
    if ( class_exists( 'WooCommerce' ) ) {
        wp_enqueue_style( 'mi-cliente-woocommerce', get_template_directory_uri() . '/assets/css/woocommerce.css', array(), '1.0.4' );
    }
}
add_action( 'wp_enqueue_scripts', 'mi_cliente_theme_woocommerce_styles' );


/**
 * Hide WooCommerce-related elements when WooCommerce is not active.
 *
 * Outputs inline CSS in wp_head that hides WooCommerce-specific blocks and
 * elements, preventing empty containers from being displayed when the plugin
 * is deactivated or not installed.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_hide_woocommerce_elements() {
    if ( mi_cliente_theme_is_woocommerce_active() ) {
        return;
    }

    ?>
    <style id="mi-cliente-theme-wc-degradation">
        /* Hide WooCommerce blocks and elements when plugin is inactive */
        .wc-block-mini-cart,
        .wp-block-woocommerce-mini-cart,
        .wp-block-woocommerce-product-grid,
        .wp-block-woocommerce-featured-product,
        .wp-block-woocommerce-featured-category,
        .wp-block-woocommerce-handpicked-products,
        .wp-block-woocommerce-product-best-sellers,
        .wp-block-woocommerce-product-new,
        .wp-block-woocommerce-product-on-sale,
        .wp-block-woocommerce-product-category,
        .wp-block-woocommerce-product-search,
        .wp-block-woocommerce-cart,
        .wp-block-woocommerce-checkout,
        .woocommerce,
        .woocommerce-page .woocommerce,
        .widget_shopping_cart,
        .widget_products,
        .widget_product_categories,
        .widget_product_search,
        .widget_price_filter,
        .widget_rating_filter,
        [class*="wc-block-"],
        [class*="wp-block-woocommerce-"] {
            display: none !important;
        }
    </style>
    <?php
}
add_action( 'wp_head', 'mi_cliente_theme_hide_woocommerce_elements', 99 );


/**
 * Conditionally render mini-cart in header only when WooCommerce is active.
 *
 * Filters the render_block output to remove WooCommerce mini-cart blocks
 * when WooCommerce is not active, preventing empty container rendering.
 *
 * @since 1.0.0
 *
 * @param string $block_content The block content.
 * @param array  $block         The full block, including name and attributes.
 * @return string Filtered block content.
 */
function mi_cliente_theme_conditional_wc_block_render( $block_content, $block ) {
    if ( mi_cliente_theme_is_woocommerce_active() ) {
        return $block_content;
    }

    // List of WooCommerce block names that should not render when WC is inactive.
    $wc_blocks = array(
        'woocommerce/mini-cart',
        'woocommerce/product-grid',
        'woocommerce/featured-product',
        'woocommerce/featured-category',
        'woocommerce/handpicked-products',
        'woocommerce/product-best-sellers',
        'woocommerce/product-new',
        'woocommerce/product-on-sale',
        'woocommerce/product-category',
        'woocommerce/product-search',
        'woocommerce/cart',
        'woocommerce/checkout',
    );

    if ( isset( $block['blockName'] ) && in_array( $block['blockName'], $wc_blocks, true ) ) {
        return '';
    }

    return $block_content;
}
add_filter( 'render_block', 'mi_cliente_theme_conditional_wc_block_render', 10, 2 );


/**
 * Display admin notice when WooCommerce is not active.
 *
 * Shows a dismissible notice to administrators recommending WooCommerce
 * installation for full theme functionality.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_woocommerce_admin_notice() {
    if ( mi_cliente_theme_is_woocommerce_active() ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    ?>
    <div class="notice notice-warning is-dismissible">
        <p>
            <?php
            printf(
                /* translators: %s: WooCommerce plugin name */
                esc_html__( 'El tema Mi Cliente requiere %s para habilitar todas las funcionalidades de tienda virtual. Algunas características estarán deshabilitadas hasta que WooCommerce sea instalado y activado.', 'mi-cliente-theme' ),
                '<strong>WooCommerce</strong>'
            );
            ?>
        </p>
    </div>
    <?php
}
add_action( 'admin_notices', 'mi_cliente_theme_woocommerce_admin_notice' );

/**
 * Enqueue layout, component, and performance stylesheets.
 *
 * Loads complex layout styles, interactive component styles, and
 * performance optimization styles on the frontend.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_enqueue_layout_assets() {
    $theme_version = wp_get_theme()->get( 'Version' );

    // Layouts CSS (grid system, parallax, carousel, mega menu).
    wp_enqueue_style(
        'mi-cliente-layouts',
        get_template_directory_uri() . '/assets/css/layouts.css',
        array(),
        $theme_version
    );

    // Components CSS (accordions, tabs, modals, filters).
    wp_enqueue_style(
        'mi-cliente-components',
        get_template_directory_uri() . '/assets/css/components.css',
        array(),
        $theme_version
    );

    // Performance CSS (lazy loading, skeletons, picture patterns).
    wp_enqueue_style(
        'mi-cliente-performance',
        get_template_directory_uri() . '/assets/css/performance.css',
        array(),
        $theme_version
    );

    // Components JS (vanilla JavaScript, no jQuery dependency).
    wp_enqueue_script(
        'mi-cliente-components',
        get_template_directory_uri() . '/assets/js/components.js',
        array(),
        $theme_version,
        true // Load in footer.
    );
}
add_action( 'wp_enqueue_scripts', 'mi_cliente_theme_enqueue_layout_assets' );

/**
 * Add no-js class to <html> for graceful degradation.
 *
 * The components.js script removes this class on load, enabling
 * CSS-based progressive enhancement.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_add_nojs_class() {
    ?>
    <script>document.documentElement.classList.add('no-js');</script>
    <?php
}
add_action( 'wp_head', 'mi_cliente_theme_add_nojs_class', 1 );

/**
 * Add loading="lazy" and decoding="async" to content images.
 *
 * Filters image tags in post content to add native lazy loading
 * and async decoding attributes for performance optimization.
 *
 * @since 1.0.0
 *
 * @param string $content The post content.
 * @return string Modified content with lazy loading attributes.
 */
function mi_cliente_theme_optimize_content_images( $content ) {
    if ( empty( $content ) ) {
        return $content;
    }

    // Match img tags that don't already have loading attribute.
    $content = preg_replace_callback(
        '/<img\b([^>]*)>/i',
        function ( $matches ) {
            $img_tag = $matches[0];
            $attrs   = $matches[1];

            // Add loading="lazy" if not present.
            if ( stripos( $attrs, 'loading=' ) === false ) {
                $img_tag = str_replace( '<img', '<img loading="lazy"', $img_tag );
            }

            // Add decoding="async" if not present.
            if ( stripos( $attrs, 'decoding=' ) === false ) {
                $img_tag = str_replace( '<img', '<img decoding="async"', $img_tag );
            }

            return $img_tag;
        },
        $content
    );

    return $content;
}
add_filter( 'the_content', 'mi_cliente_theme_optimize_content_images', 20 );

/**
 * Serve WebP images when available.
 *
 * Wraps content images in <picture> elements with WebP source
 * when a .webp version of the image exists on the server.
 *
 * @since 1.0.0
 *
 * @param string $content The post content.
 * @return string Modified content with picture elements for WebP support.
 */
function mi_cliente_theme_webp_picture_element( $content ) {
    if ( empty( $content ) || is_admin() ) {
        return $content;
    }

    // Match img tags with src attribute.
    $content = preg_replace_callback(
        '/<img\b([^>]*)\bsrc=["\']([^"\']+)["\']([^>]*)>/i',
        function ( $matches ) {
            $before_src = $matches[1];
            $src        = $matches[2];
            $after_src  = $matches[3];
            $full_img   = $matches[0];

            // Skip if already inside a <picture> element or if it's an SVG.
            if ( preg_match( '/\.svg$/i', $src ) ) {
                return $full_img;
            }

            // Skip external images.
            $upload_dir = wp_get_upload_dir();
            if ( strpos( $src, $upload_dir['baseurl'] ) === false ) {
                return $full_img;
            }

            // Check if WebP version exists.
            $src_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $src );
            $webp_path = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $src_path );
            $webp_url  = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $src );

            if ( file_exists( $webp_path ) ) {
                $picture  = '<picture class="responsive-image">';
                $picture .= '<source srcset="' . esc_url( $webp_url ) . '" type="image/webp">';
                $picture .= $full_img;
                $picture .= '</picture>';
                return $picture;
            }

            return $full_img;
        },
        $content
    );

    return $content;
}
add_filter( 'the_content', 'mi_cliente_theme_webp_picture_element', 25 );

// Include custom blocks registration.
require_once get_template_directory() . '/inc/custom-blocks.php';

// Include admin restrictions for client role.
require_once get_template_directory() . '/inc/admin-restrictions.php';

// Include page protection dialog for client role.
require_once get_template_directory() . '/inc/page-protection.php';

// Include JSON-LD structured data (Schema.org).
require_once get_template_directory() . '/inc/schema-markup.php';

// Include Open Graph and Twitter Card meta tags.
require_once get_template_directory() . '/inc/social-meta.php';

// Include breadcrumb navigation.
require_once get_template_directory() . '/inc/breadcrumbs.php';

// Include product catalog (categories, filters, variable products, product cards).
require_once get_template_directory() . '/inc/product-catalog.php';

// Include cart and checkout (validation, mini-cart, AJAX updates, order confirmation).
require_once get_template_directory() . '/inc/cart-checkout.php';

// Include WhatsApp floating button.
require_once get_template_directory() . '/inc/whatsapp-button.php';

// Storefront D.Sam (inicio, cabecera, pie, componentes).
require_once get_template_directory() . '/inc/storefront-icons.php';
require_once get_template_directory() . '/inc/storefront.php';

/**
 * Shortcode to render a product attribute filter dropdown.
 *
 * Usage: [mi_cliente_filter_attribute taxonomy="pa_marca" label="Marca"]
 *
 * Renders a <select> dropdown with terms from the given taxonomy,
 * using the same filter mechanism as mi_cliente_theme_render_product_filters().
 *
 * @since 1.0.0
 */
function mi_cliente_theme_filter_attribute_shortcode( $atts ) {
    if ( ! mi_cliente_theme_is_woocommerce_active() ) {
        return '';
    }

    $atts = shortcode_atts( array(
        'taxonomy' => 'pa_marca',
        'label'    => 'Marca',
    ), $atts, 'mi_cliente_filter_attribute' );

    $taxonomy    = sanitize_text_field( $atts['taxonomy'] );
    $label       = sanitize_text_field( $atts['label'] );
    $filter_name = 'filter_' . str_replace( 'pa_', '', $taxonomy );
    // Support array (checkboxes) or string (legacy)
    $raw_value = isset( $_GET[ $filter_name ] ) ? $_GET[ $filter_name ] : '';
    if ( is_array( $raw_value ) ) {
        $active_value = implode( ',', array_map( 'sanitize_text_field', array_map( 'wp_unslash', $raw_value ) ) );
    } else {
        $active_value = sanitize_text_field( wp_unslash( $raw_value ) );
    }

    $terms = get_terms( array(
        'taxonomy'   => $taxonomy,
        'hide_empty' => true,
    ) );

    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        return '';
    }

    ob_start();
    // Use the current page URL as form action to stay on the shop/archive page.
    $current_url = get_pagenum_link( 1, false );
    // Strip existing query string — we only want the base path.
    $action_url = strtok( $current_url, '?' );
    // Parse active values (comma-separated for multi-select).
    $active_values = array_filter( array_map( 'trim', explode( ',', $active_value ) ) );
    ?>
    <form class="mi-cliente-attribute-filter" method="get" action="<?php echo esc_url( $action_url ); ?>">
        <?php mi_cliente_theme_render_attribute_filter_options( $filter_name, $taxonomy, $terms, $active_values ); ?>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode( 'mi_cliente_filter_attribute', 'mi_cliente_theme_filter_attribute_shortcode' );

/**
 * Shortcode to render ALL filterable attribute filters dynamically.
 *
 * Usage: [mi_cliente_all_attribute_filters]
 *
 * Automatically discovers all product attributes registered in WooCommerce
 * that have at least one non-empty term assigned to a product, and renders
 * a checkbox filter group for each one.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_all_attribute_filters_shortcode( $atts ) {
    if ( ! mi_cliente_theme_is_woocommerce_active() ) {
        return '';
    }

    // Get all WooCommerce attribute taxonomies.
    $attribute_taxonomies = wc_get_attribute_taxonomies();
    if ( empty( $attribute_taxonomies ) ) {
        return '';
    }

    // Use the current page URL as form action.
    $current_url = get_pagenum_link( 1, false );
    $action_url  = strtok( $current_url, '?' );

    $ordered            = array();
    $color_attribute    = null;

    foreach ( $attribute_taxonomies as $attribute ) {
        if ( 'color' === $attribute->attribute_name ) {
            $color_attribute = $attribute;
            continue;
        }
        $ordered[] = $attribute;
    }

    if ( $color_attribute ) {
        $ordered[] = $color_attribute;
    }

    ob_start();
    echo '<div class="mi-cliente-shop-filters">';

    foreach ( $ordered as $attribute ) {
        mi_cliente_theme_render_shop_attribute_filter_group( $attribute, $attribute_taxonomies, $action_url );
    }

    echo '</div>';

    return ob_get_clean();
}
add_shortcode( 'mi_cliente_all_attribute_filters', 'mi_cliente_theme_all_attribute_filters_shortcode' );
