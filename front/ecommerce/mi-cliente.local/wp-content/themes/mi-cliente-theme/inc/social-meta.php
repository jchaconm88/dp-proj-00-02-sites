<?php
/**
 * Open Graph and Twitter Card Meta Tags
 *
 * Outputs Open Graph (og:) and Twitter Card meta tags in wp_head
 * for improved social media sharing. Only active when no SEO plugin
 * is detected (Yoast SEO or Rank Math).
 *
 * @package Mi_Cliente_Theme
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Output Open Graph and Twitter Card meta tags.
 *
 * Generates the following meta tags on all public pages:
 * - og:title, og:description, og:image, og:url, og:type
 * - twitter:card, twitter:title, twitter:description, twitter:image
 *
 * @since 1.0.0
 */
function mi_cliente_theme_social_meta_tags() {
    // Bail if SEO plugin is active (function defined in schema-markup.php).
    if ( function_exists( 'mi_cliente_theme_is_seo_plugin_active' ) && mi_cliente_theme_is_seo_plugin_active() ) {
        return;
    }

    // Don't output on admin or non-public pages.
    if ( is_admin() || is_feed() || is_robots() ) {
        return;
    }

    $og_title       = mi_cliente_theme_get_social_title();
    $og_description = mi_cliente_theme_get_social_description();
    $og_image       = mi_cliente_theme_get_social_image();
    $og_url         = mi_cliente_theme_get_social_url();
    $og_type        = mi_cliente_theme_get_social_type();

    // Open Graph meta tags.
    echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr( $og_description ) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url( $og_url ) . '">' . "\n";
    echo '<meta property="og:type" content="' . esc_attr( $og_type ) . '">' . "\n";
    echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
    echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '">' . "\n";

    if ( $og_image ) {
        echo '<meta property="og:image" content="' . esc_url( $og_image ) . '">' . "\n";
    }

    // Twitter Card meta tags.
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr( $og_title ) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr( $og_description ) . '">' . "\n";

    if ( $og_image ) {
        echo '<meta name="twitter:image" content="' . esc_url( $og_image ) . '">' . "\n";
    }
}
add_action( 'wp_head', 'mi_cliente_theme_social_meta_tags', 2 );

/**
 * Get the social sharing title.
 *
 * @since 1.0.0
 *
 * @return string The title for social meta tags.
 */
function mi_cliente_theme_get_social_title() {
    if ( is_singular() ) {
        return get_the_title();
    }

    if ( is_front_page() || is_home() ) {
        return get_bloginfo( 'name' ) . ' - ' . get_bloginfo( 'description' );
    }

    if ( is_category() || is_tag() || is_tax() ) {
        return single_term_title( '', false );
    }

    if ( function_exists( 'is_shop' ) && is_shop() ) {
        return get_the_title( wc_get_page_id( 'shop' ) );
    }

    if ( is_search() ) {
        return sprintf( __( 'Resultados de búsqueda: %s', 'mi-cliente-theme' ), get_search_query() );
    }

    if ( is_404() ) {
        return __( 'Página no encontrada', 'mi-cliente-theme' );
    }

    return get_bloginfo( 'name' );
}

/**
 * Get the social sharing description.
 *
 * @since 1.0.0
 *
 * @return string The description for social meta tags.
 */
function mi_cliente_theme_get_social_description() {
    if ( is_singular() ) {
        global $post;
        if ( ! empty( $post->post_excerpt ) ) {
            return wp_strip_all_tags( $post->post_excerpt );
        }
        return wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '...' );
    }

    if ( is_front_page() || is_home() ) {
        return get_bloginfo( 'description' );
    }

    if ( is_category() || is_tag() || is_tax() ) {
        $description = term_description();
        return $description ? wp_strip_all_tags( $description ) : get_bloginfo( 'description' );
    }

    return get_bloginfo( 'description' );
}

/**
 * Get the social sharing image URL.
 *
 * @since 1.0.0
 *
 * @return string|false The image URL or false if none available.
 */
function mi_cliente_theme_get_social_image() {
    if ( is_singular() ) {
        // Use featured image if available.
        $thumbnail_id = get_post_thumbnail_id();
        if ( $thumbnail_id ) {
            return wp_get_attachment_image_url( $thumbnail_id, 'large' );
        }

        // For WooCommerce products, try product image.
        if ( function_exists( 'is_product' ) && is_product() ) {
            $product = wc_get_product( get_the_ID() );
            if ( $product ) {
                $image_id = $product->get_image_id();
                if ( $image_id ) {
                    return wp_get_attachment_image_url( $image_id, 'large' );
                }
            }
        }
    }

    // Fallback to site logo.
    $custom_logo_id = get_theme_mod( 'custom_logo' );
    if ( $custom_logo_id ) {
        return wp_get_attachment_image_url( $custom_logo_id, 'full' );
    }

    return false;
}

/**
 * Get the canonical URL for social sharing.
 *
 * @since 1.0.0
 *
 * @return string The canonical URL.
 */
function mi_cliente_theme_get_social_url() {
    if ( is_singular() ) {
        return get_permalink();
    }

    if ( is_front_page() || is_home() ) {
        return home_url( '/' );
    }

    if ( is_category() || is_tag() || is_tax() ) {
        return get_term_link( get_queried_object() );
    }

    if ( function_exists( 'is_shop' ) && is_shop() ) {
        return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
    }

    return home_url( $_SERVER['REQUEST_URI'] ?? '/' );
}

/**
 * Get the Open Graph type for the current page.
 *
 * @since 1.0.0
 *
 * @return string The og:type value.
 */
function mi_cliente_theme_get_social_type() {
    if ( is_singular( 'product' ) ) {
        return 'product';
    }

    if ( is_singular( 'post' ) ) {
        return 'article';
    }

    return 'website';
}
