<?php
/**
 * Breadcrumb Navigation
 *
 * Outputs navigable breadcrumbs with BreadcrumbList Schema.org markup
 * (JSON-LD) on all pages except the homepage. Provides a callable function
 * for use in templates.
 *
 * @package Mi_Cliente_Theme
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Display breadcrumb navigation.
 *
 * Outputs an accessible breadcrumb navigation element with structured
 * data markup. Can be called directly in templates or hooked into actions.
 *
 * Usage in templates:
 *   <?php mi_cliente_theme_breadcrumbs(); ?>
 *
 * @since 1.0.0
 *
 * @param array $args {
 *     Optional. Arguments to customize breadcrumb output.
 *
 *     @type string $wrapper_class CSS class for the nav wrapper. Default 'breadcrumbs'.
 *     @type string $separator     Separator between breadcrumb items. Default ' / '.
 *     @type bool   $echo          Whether to echo or return the output. Default true.
 * }
 * @return string|void HTML output if $echo is false, void otherwise.
 */
function mi_cliente_theme_breadcrumbs( $args = array() ) {
    // Don't show breadcrumbs on the homepage.
    if ( is_front_page() ) {
        return;
    }

    $defaults = array(
        'wrapper_class' => 'breadcrumbs',
        'separator'     => ' / ',
        'echo'          => true,
    );

    $args = wp_parse_args( $args, $defaults );

    // Get breadcrumb items (function defined in schema-markup.php).
    if ( ! function_exists( 'mi_cliente_theme_get_breadcrumb_items' ) ) {
        return;
    }

    $breadcrumbs = mi_cliente_theme_get_breadcrumb_items();

    if ( empty( $breadcrumbs ) ) {
        return;
    }

    $output  = '<nav class="' . esc_attr( $args['wrapper_class'] ) . '" aria-label="' . esc_attr__( 'Breadcrumb', 'mi-cliente-theme' ) . '">' . "\n";
    $output .= '<ol class="breadcrumbs__list">' . "\n";

    $total = count( $breadcrumbs );

    foreach ( $breadcrumbs as $index => $crumb ) {
        $is_last = ( $index === $total - 1 );

        $output .= '<li class="breadcrumbs__item">';

        if ( $is_last ) {
            // Current page — no link, aria-current.
            $output .= '<span class="breadcrumbs__current" aria-current="page">' . esc_html( $crumb['name'] ) . '</span>';
        } else {
            // Linked breadcrumb.
            $output .= '<a class="breadcrumbs__link" href="' . esc_url( $crumb['url'] ) . '">' . esc_html( $crumb['name'] ) . '</a>';
            $output .= '<span class="breadcrumbs__separator" aria-hidden="true">' . esc_html( $args['separator'] ) . '</span>';
        }

        $output .= '</li>' . "\n";
    }

    $output .= '</ol>' . "\n";
    $output .= '</nav>' . "\n";

    // Output JSON-LD for breadcrumbs (inline with the visible breadcrumbs).
    if ( ! mi_cliente_theme_is_seo_plugin_active() ) {
        $items = array();
        foreach ( $breadcrumbs as $position => $crumb ) {
            $item = array(
                '@type'    => 'ListItem',
                'position' => $position + 1,
                'name'     => $crumb['name'],
            );
            if ( ! empty( $crumb['url'] ) ) {
                $item['item'] = $crumb['url'];
            }
            $items[] = $item;
        }

        $schema = array(
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        );

        $output .= '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
    }

    if ( $args['echo'] ) {
        echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped above.
        return;
    }

    return $output;
}
