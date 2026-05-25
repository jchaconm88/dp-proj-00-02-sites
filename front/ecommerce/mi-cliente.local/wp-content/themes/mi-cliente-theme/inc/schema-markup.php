<?php
/**
 * JSON-LD Structured Data (Schema.org)
 *
 * Outputs JSON-LD structured data in wp_head for improved SEO and
 * rich search results. Only active when no SEO plugin is detected.
 *
 * @package Mi_Cliente_Theme
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Check if an SEO plugin is active.
 *
 * Returns true if Yoast SEO or Rank Math is active, indicating
 * the theme should defer structured data output to the plugin.
 *
 * @since 1.0.0
 *
 * @return bool True if an SEO plugin is active.
 */
function mi_cliente_theme_is_seo_plugin_active() {
    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $yoast_active    = is_plugin_active( 'wordpress-seo/wp-seo.php' );
    $rankmath_active = is_plugin_active( 'seo-by-rank-math/rank-math.php' );

    return $yoast_active || $rankmath_active;
}

/**
 * Output JSON-LD structured data in wp_head.
 *
 * Generates Schema.org markup for:
 * - Product pages (type: Product)
 * - Homepage (type: Organization)
 * - All pages with breadcrumbs (type: BreadcrumbList)
 *
 * @since 1.0.0
 */
function mi_cliente_theme_schema_markup() {
    if ( mi_cliente_theme_is_seo_plugin_active() ) {
        return;
    }

    // Organization schema on homepage.
    if ( is_front_page() || is_home() ) {
        mi_cliente_theme_schema_organization();
    }

    // Product schema on single product pages.
    if ( function_exists( 'is_product' ) && is_product() ) {
        mi_cliente_theme_schema_product();
    }

    // Breadcrumb schema on all pages except homepage.
    if ( ! is_front_page() ) {
        mi_cliente_theme_schema_breadcrumbs();
    }
}
add_action( 'wp_head', 'mi_cliente_theme_schema_markup', 5 );

/**
 * Output Organization JSON-LD schema.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_schema_organization() {
    $schema = array(
        '@context' => 'https://schema.org',
        '@type'    => 'Organization',
        'name'     => get_bloginfo( 'name' ),
        'url'      => home_url( '/' ),
    );

    // Add logo if custom logo is set.
    $custom_logo_id = get_theme_mod( 'custom_logo' );
    if ( $custom_logo_id ) {
        $logo_url       = wp_get_attachment_image_url( $custom_logo_id, 'full' );
        $schema['logo'] = $logo_url;
    }

    mi_cliente_theme_output_jsonld( $schema );
}

/**
 * Output Product JSON-LD schema.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_schema_product() {
    if ( ! function_exists( 'wc_get_product' ) ) {
        return;
    }

    global $post;
    $product = wc_get_product( $post->ID );

    if ( ! $product ) {
        return;
    }

    $schema = array(
        '@context'    => 'https://schema.org',
        '@type'       => 'Product',
        'name'        => $product->get_name(),
        'description' => wp_strip_all_tags( $product->get_short_description() ? $product->get_short_description() : $product->get_description() ),
    );

    // Product image.
    $image_id = $product->get_image_id();
    if ( $image_id ) {
        $schema['image'] = wp_get_attachment_image_url( $image_id, 'full' );
    }

    // Product offers (price and availability).
    $schema['offers'] = array(
        '@type'         => 'Offer',
        'price'         => $product->get_price(),
        'priceCurrency' => get_woocommerce_currency(),
        'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'url'           => get_permalink( $post->ID ),
    );

    mi_cliente_theme_output_jsonld( $schema );
}

/**
 * Output BreadcrumbList JSON-LD schema.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_schema_breadcrumbs() {
    $breadcrumbs = mi_cliente_theme_get_breadcrumb_items();

    if ( empty( $breadcrumbs ) ) {
        return;
    }

    $items = array();
    foreach ( $breadcrumbs as $position => $crumb ) {
        $items[] = array(
            '@type'    => 'ListItem',
            'position' => $position + 1,
            'name'     => $crumb['name'],
            'item'     => $crumb['url'],
        );
    }

    $schema = array(
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $items,
    );

    mi_cliente_theme_output_jsonld( $schema );
}

/**
 * Get breadcrumb items as an array.
 *
 * Builds the breadcrumb trail based on the current page context.
 * Used by both the JSON-LD schema and the visible breadcrumb navigation.
 *
 * @since 1.0.0
 *
 * @return array Array of breadcrumb items with 'name' and 'url' keys.
 */
function mi_cliente_theme_get_breadcrumb_items() {
    $breadcrumbs = array();

    // Always start with Home.
    $breadcrumbs[] = array(
        'name' => __( 'Inicio', 'mi-cliente-theme' ),
        'url'  => home_url( '/' ),
    );

    if ( is_singular( 'product' ) ) {
        // WooCommerce product: Home > Tienda > Product Name.
        $shop_page_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0;
        if ( $shop_page_id ) {
            $breadcrumbs[] = array(
                'name' => get_the_title( $shop_page_id ),
                'url'  => get_permalink( $shop_page_id ),
            );
        }

        // Product categories.
        $terms = get_the_terms( get_the_ID(), 'product_cat' );
        if ( $terms && ! is_wp_error( $terms ) ) {
            $term          = $terms[0];
            $breadcrumbs[] = array(
                'name' => $term->name,
                'url'  => get_term_link( $term ),
            );
        }

        $breadcrumbs[] = array(
            'name' => get_the_title(),
            'url'  => get_permalink(),
        );
    } elseif ( is_singular( 'post' ) ) {
        // Blog post: Home > Blog > Post Title.
        $blog_page_id = get_option( 'page_for_posts' );
        if ( $blog_page_id ) {
            $breadcrumbs[] = array(
                'name' => get_the_title( $blog_page_id ),
                'url'  => get_permalink( $blog_page_id ),
            );
        }

        $breadcrumbs[] = array(
            'name' => get_the_title(),
            'url'  => get_permalink(),
        );
    } elseif ( is_page() ) {
        // Page: Home > Parent Page > Current Page.
        global $post;
        $ancestors = array_reverse( get_post_ancestors( $post ) );
        foreach ( $ancestors as $ancestor_id ) {
            $breadcrumbs[] = array(
                'name' => get_the_title( $ancestor_id ),
                'url'  => get_permalink( $ancestor_id ),
            );
        }

        $breadcrumbs[] = array(
            'name' => get_the_title(),
            'url'  => get_permalink(),
        );
    } elseif ( is_category() || is_tag() || is_tax() ) {
        // Taxonomy archive: Home > Archive Name.
        $breadcrumbs[] = array(
            'name' => single_term_title( '', false ),
            'url'  => get_term_link( get_queried_object() ),
        );
    } elseif ( function_exists( 'is_shop' ) && is_shop() ) {
        // WooCommerce shop page.
        $breadcrumbs[] = array(
            'name' => __( 'Tienda', 'mi-cliente-theme' ),
            'url'  => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : get_permalink(),
        );
    } elseif ( is_search() ) {
        $breadcrumbs[] = array(
            'name' => sprintf( __( 'Resultados de búsqueda: %s', 'mi-cliente-theme' ), get_search_query() ),
            'url'  => get_search_link(),
        );
    } elseif ( is_404() ) {
        $breadcrumbs[] = array(
            'name' => __( 'Página no encontrada', 'mi-cliente-theme' ),
            'url'  => '',
        );
    }

    return $breadcrumbs;
}

/**
 * Output a JSON-LD script tag.
 *
 * @since 1.0.0
 *
 * @param array $schema The schema data array.
 */
function mi_cliente_theme_output_jsonld( $schema ) {
    echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}
