<?php
/**
 * Product Catalog - Categories, Filters, Variable Products, and Product Cards.
 *
 * Registers product categories (Ropa, Zapatillas, Accesorios with subcategories),
 * implements filter UI helpers, variable product support with image gallery/zoom,
 * color/size selectors, and product card display.
 *
 * @package Mi_Cliente_Theme
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register default product categories on theme activation.
 *
 * Creates hierarchical product categories: Ropa, Zapatillas, Accesorios
 * with their respective subcategories.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_register_product_categories() {
    if ( ! mi_cliente_theme_is_woocommerce_active() ) {
        return;
    }

    $categories = array(
        'Ropa' => array(
            'Camisetas',
            'Pantalones',
            'Chaquetas',
            'Vestidos',
            'Faldas',
        ),
        'Zapatillas' => array(
            'Running',
            'Casual',
            'Deportivas',
            'Sandalias',
        ),
        'Accesorios' => array(
            'Bolsos',
            'Gorras',
            'Cinturones',
            'Relojes',
            'Gafas',
        ),
    );

    foreach ( $categories as $parent_name => $subcategories ) {
        $parent_term = term_exists( $parent_name, 'product_cat' );
        if ( ! $parent_term ) {
            $parent_term = wp_insert_term(
                $parent_name,
                'product_cat',
                array( 'slug' => sanitize_title( $parent_name ) )
            );
        }

        if ( is_wp_error( $parent_term ) ) {
            continue;
        }

        $parent_id = is_array( $parent_term ) ? $parent_term['term_id'] : $parent_term;

        foreach ( $subcategories as $sub_name ) {
            if ( ! term_exists( $sub_name, 'product_cat' ) ) {
                wp_insert_term(
                    $sub_name,
                    'product_cat',
                    array(
                        'slug'   => sanitize_title( $sub_name ),
                        'parent' => (int) $parent_id,
                    )
                );
            }
        }
    }
}
add_action( 'after_switch_theme', 'mi_cliente_theme_register_product_categories' );


/**
 * Register product filter attributes for WooCommerce.
 *
 * Registers custom product attributes used for filtering:
 * type, size, gender, color, brand.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_register_product_attributes() {
    if ( ! mi_cliente_theme_is_woocommerce_active() ) {
        return;
    }

    $attributes = array(
        'pa_tipo'   => 'Tipo',
        'pa_talla'  => 'Talla',
        'pa_genero' => 'Género',
        'pa_color'  => 'Color',
        'pa_marca'  => 'Marca',
    );

    foreach ( $attributes as $slug => $label ) {
        if ( ! taxonomy_exists( $slug ) ) {
            register_taxonomy(
                $slug,
                'product',
                array(
                    'label'        => $label,
                    'hierarchical' => false,
                    'show_ui'      => true,
                    'query_var'    => true,
                    'rewrite'      => array( 'slug' => str_replace( 'pa_', '', $slug ) ),
                )
            );
        }
    }
}
add_action( 'init', 'mi_cliente_theme_register_product_attributes', 5 );


/**
 * Render product catalog filter UI.
 *
 * Outputs filter controls for: type, size, gender, color, brand, and price range.
 * Uses AJAX for dynamic filtering without full page reload.
 *
 * @since 1.0.0
 *
 * @param array $args Optional. Filter configuration arguments.
 */
function mi_cliente_theme_render_product_filters( $args = array() ) {
    if ( ! mi_cliente_theme_is_woocommerce_active() ) {
        return;
    }

    $defaults = array(
        'show_type'   => true,
        'show_size'   => true,
        'show_gender' => true,
        'show_color'  => true,
        'show_brand'  => true,
        'show_price'  => true,
    );
    $args = wp_parse_args( $args, $defaults );

    $active_filters = array(
        'tipo'   => isset( $_GET['filter_tipo'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_tipo'] ) ) : '',
        'talla'  => isset( $_GET['filter_talla'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_talla'] ) ) : '',
        'genero' => isset( $_GET['filter_genero'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_genero'] ) ) : '',
        'color'  => isset( $_GET['filter_color'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_color'] ) ) : '',
        'marca'  => isset( $_GET['filter_marca'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_marca'] ) ) : '',
        'min_price' => isset( $_GET['min_price'] ) ? absint( $_GET['min_price'] ) : '',
        'max_price' => isset( $_GET['max_price'] ) ? absint( $_GET['max_price'] ) : '',
    );
    ?>
    <form class="mi-cliente-product-filters" method="get" aria-label="<?php esc_attr_e( 'Filtros de producto', 'mi-cliente-theme' ); ?>">
        <?php if ( $args['show_type'] ) : ?>
        <div class="filter-group filter-group--type">
            <label for="filter-tipo"><?php esc_html_e( 'Tipo', 'mi-cliente-theme' ); ?></label>
            <?php mi_cliente_theme_render_filter_select( 'pa_tipo', 'filter_tipo', $active_filters['tipo'] ); ?>
        </div>
        <?php endif; ?>

        <?php if ( $args['show_size'] ) : ?>
        <div class="filter-group filter-group--size">
            <label for="filter-talla"><?php esc_html_e( 'Talla', 'mi-cliente-theme' ); ?></label>
            <?php mi_cliente_theme_render_filter_select( 'pa_talla', 'filter_talla', $active_filters['talla'] ); ?>
        </div>
        <?php endif; ?>

        <?php if ( $args['show_gender'] ) : ?>
        <div class="filter-group filter-group--gender">
            <label for="filter-genero"><?php esc_html_e( 'Género', 'mi-cliente-theme' ); ?></label>
            <?php mi_cliente_theme_render_filter_select( 'pa_genero', 'filter_genero', $active_filters['genero'] ); ?>
        </div>
        <?php endif; ?>

        <?php if ( $args['show_color'] ) : ?>
        <div class="filter-group filter-group--color">
            <label for="filter-color"><?php esc_html_e( 'Color', 'mi-cliente-theme' ); ?></label>
            <?php mi_cliente_theme_render_filter_select( 'pa_color', 'filter_color', $active_filters['color'] ); ?>
        </div>
        <?php endif; ?>

        <?php if ( $args['show_brand'] ) : ?>
        <div class="filter-group filter-group--brand">
            <label for="filter-marca"><?php esc_html_e( 'Marca', 'mi-cliente-theme' ); ?></label>
            <?php mi_cliente_theme_render_filter_select( 'pa_marca', 'filter_marca', $active_filters['marca'] ); ?>
        </div>
        <?php endif; ?>

        <?php if ( $args['show_price'] ) : ?>
        <div class="filter-group filter-group--price">
            <label><?php esc_html_e( 'Precio', 'mi-cliente-theme' ); ?></label>
            <div class="price-range-inputs">
                <input type="number" name="min_price" id="filter-min-price"
                    placeholder="<?php esc_attr_e( 'Mín', 'mi-cliente-theme' ); ?>"
                    value="<?php echo esc_attr( $active_filters['min_price'] ); ?>"
                    min="0" aria-label="<?php esc_attr_e( 'Precio mínimo', 'mi-cliente-theme' ); ?>">
                <span class="price-separator">—</span>
                <input type="number" name="max_price" id="filter-max-price"
                    placeholder="<?php esc_attr_e( 'Máx', 'mi-cliente-theme' ); ?>"
                    value="<?php echo esc_attr( $active_filters['max_price'] ); ?>"
                    min="0" aria-label="<?php esc_attr_e( 'Precio máximo', 'mi-cliente-theme' ); ?>">
            </div>
        </div>
        <?php endif; ?>

        <div class="filter-actions">
            <button type="submit" class="filter-btn filter-btn--apply">
                <?php esc_html_e( 'Aplicar filtros', 'mi-cliente-theme' ); ?>
            </button>
            <a href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>" class="filter-btn filter-btn--clear">
                <?php esc_html_e( 'Limpiar', 'mi-cliente-theme' ); ?>
            </a>
        </div>
    </form>
    <?php
}


/**
 * Render a filter select dropdown for a given taxonomy.
 *
 * @since 1.0.0
 *
 * @param string $taxonomy     The taxonomy slug.
 * @param string $filter_name  The query parameter name.
 * @param string $active_value The currently selected value.
 */
function mi_cliente_theme_render_filter_select( $taxonomy, $filter_name, $active_value = '' ) {
    $terms = get_terms( array(
        'taxonomy'   => $taxonomy,
        'hide_empty' => true,
    ) );

    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        echo '<select name="' . esc_attr( $filter_name ) . '" id="' . esc_attr( $filter_name ) . '" disabled>';
        echo '<option>' . esc_html__( 'Sin opciones', 'mi-cliente-theme' ) . '</option>';
        echo '</select>';
        return;
    }
    ?>
    <select name="<?php echo esc_attr( $filter_name ); ?>" id="<?php echo esc_attr( $filter_name ); ?>">
        <option value=""><?php esc_html_e( 'Todos', 'mi-cliente-theme' ); ?></option>
        <?php foreach ( $terms as $term ) : ?>
            <option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $active_value, $term->slug ); ?>>
                <?php echo esc_html( $term->name ); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php
}


/**
 * Apply product filters to WooCommerce product query.
 *
 * @since 1.0.0
 *
 * @param WP_Query $query The main query object.
 */
function mi_cliente_theme_apply_product_filters( $query ) {
    if ( is_admin() || ! $query->is_main_query() ) {
        return;
    }

    if ( ! is_shop() && ! is_product_taxonomy() ) {
        return;
    }

    $tax_query = mi_cliente_theme_apply_shop_attribute_tax_query( $query->get( 'tax_query', array() ) );
    if ( ! empty( $tax_query ) ) {
        $query->set( 'tax_query', $tax_query );
    }

    $filtered = mi_cliente_theme_apply_shop_price_meta_query( $query->get( 'meta_query', array() ) );
    if ( ! empty( $filtered ) ) {
        $query->set( 'meta_query', $filtered );
    }
}
add_action( 'pre_get_posts', 'mi_cliente_theme_apply_product_filters' );

/**
 * Append min_price / max_price clauses to a meta_query array.
 *
 * @param array<int|string, mixed> $meta_query Existing meta query.
 * @return array<int|string, mixed>
 */
function mi_cliente_theme_apply_shop_price_meta_query( array $meta_query ): array {
    $min_price = isset( $_GET['min_price'] ) ? wc_format_decimal( wp_unslash( (string) $_GET['min_price'] ), wc_get_price_decimals() ) : '';
    $max_price = isset( $_GET['max_price'] ) ? wc_format_decimal( wp_unslash( (string) $_GET['max_price'] ), wc_get_price_decimals() ) : '';

    if ( '' !== $min_price ) {
        $meta_query[] = array(
            'key'     => '_price',
            'value'   => (float) $min_price,
            'compare' => '>=',
            'type'    => 'NUMERIC',
        );
    }

    if ( '' !== $max_price ) {
        $meta_query[] = array(
            'key'     => '_price',
            'value'   => (float) $max_price,
            'compare' => '<=',
            'type'    => 'NUMERIC',
        );
    }

    return $meta_query;
}

/**
 * Append attribute tax_query clauses from filter_* query params.
 *
 * @param array<int|string, mixed> $tax_query Existing tax query.
 * @return array<int|string, mixed>
 */
function mi_cliente_theme_apply_shop_attribute_tax_query( array $tax_query ): array {
    $filter_map = array(
        'filter_tipo'   => 'pa_tipo',
        'filter_talla'  => 'pa_talla',
        'filter_genero' => 'pa_genero',
        'filter_color'  => 'pa_color',
        'filter_marca'  => 'pa_marca',
    );

    if ( mi_cliente_theme_is_woocommerce_active() && function_exists( 'wc_get_attribute_taxonomies' ) ) {
        foreach ( wc_get_attribute_taxonomies() as $attribute ) {
            $filter_map[ 'filter_' . $attribute->attribute_name ] = 'pa_' . $attribute->attribute_name;
        }
    }

    foreach ( $filter_map as $param => $taxonomy ) {
        if ( empty( $_GET[ $param ] ) || ! taxonomy_exists( $taxonomy ) ) {
            continue;
        }

        $raw = $_GET[ $param ];
        if ( is_array( $raw ) ) {
            $terms_val = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $raw ) );
        } else {
            $terms_val = array_filter( array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( (string) $raw ) ) ) ) );
        }

        if ( empty( $terms_val ) ) {
            continue;
        }

        $tax_query[] = array(
            'taxonomy' => $taxonomy,
            'field'    => 'slug',
            'terms'    => $terms_val,
            'operator' => 'IN',
        );
    }

    if ( count( $tax_query ) > 1 && ! isset( $tax_query['relation'] ) ) {
        $tax_query['relation'] = 'AND';
    }

    return $tax_query;
}


/**
 * Resolve color/size taxonomies for a variable product (ERP uses pa_talla-calzado, not pa_talla).
 *
 * @param WC_Product $product Variable product.
 * @return array{color: string, size: string} pa_* taxonomy names.
 */
function mi_cliente_theme_get_variation_attribute_taxonomies( $product ): array {
    $color = '';
    $size  = '';

    if ( ! $product || ! $product->is_type( 'variable' ) ) {
        return array(
            'color' => '',
            'size'  => '',
        );
    }

    $ordered = array();
    foreach ( $product->get_variation_attributes() as $taxonomy => $options ) {
        unset( $options );
        $ordered[] = $taxonomy;
        $label     = strtolower( wc_attribute_label( $taxonomy, $product ) );
        $slug      = str_replace( 'pa_', '', $taxonomy );

        if ( '' === $color && ( false !== strpos( $label, 'color' ) || 'color' === $slug ) ) {
            $color = $taxonomy;
            continue;
        }

        if ( '' === $size && ( false !== strpos( $label, 'talla' ) || false !== strpos( $label, 'size' ) || false !== strpos( $slug, 'talla' ) ) ) {
            $size = $taxonomy;
        }
    }

    if ( '' === $color && isset( $ordered[0] ) ) {
        $color = $ordered[0];
    }
    if ( '' === $size && isset( $ordered[1] ) ) {
        $size = $ordered[1];
    }

    return array(
        'color' => $color,
        'size'  => $size,
    );
}

/**
 * WooCommerce form field name for a variation attribute taxonomy.
 */
function mi_cliente_theme_variation_field_name( string $taxonomy ): string {
    if ( '' === $taxonomy ) {
        return '';
    }
    if ( function_exists( 'wc_variation_attribute_name' ) ) {
        return wc_variation_attribute_name( $taxonomy );
    }
    return 'attribute_' . sanitize_title( $taxonomy );
}

/**
 * Read one variation attribute value from WC available_variation payload.
 */
function mi_cliente_theme_get_variation_attribute_value( array $attrs, string $taxonomy ): string {
    if ( '' === $taxonomy || empty( $attrs ) ) {
        return '';
    }

    $preferred = mi_cliente_theme_variation_field_name( $taxonomy );
    if ( $preferred && isset( $attrs[ $preferred ] ) && '' !== (string) $attrs[ $preferred ] ) {
        return (string) $attrs[ $preferred ];
    }

    $needle = sanitize_title( str_replace( 'pa_', '', $taxonomy ) );
    foreach ( $attrs as $field => $value ) {
        if ( '' === (string) $value ) {
            continue;
        }
        $field_slug = sanitize_title( str_replace( 'attribute_', '', (string) $field ) );
        if ( $field === $preferred || $field_slug === sanitize_title( $taxonomy ) || false !== strpos( $field_slug, $needle ) ) {
            return (string) $value;
        }
    }

    return '';
}

/**
 * Resolve color/size form field names (fallback: first two variation attributes).
 *
 * @return array{color: string, size: string}
 */
function mi_cliente_theme_resolve_variation_form_fields( $product ): array {
    $taxonomies = mi_cliente_theme_get_variation_attribute_taxonomies( $product );
    $fields     = array(
        'color' => mi_cliente_theme_variation_field_name( $taxonomies['color'] ),
        'size'  => mi_cliente_theme_variation_field_name( $taxonomies['size'] ),
    );

    if ( $fields['color'] && $fields['size'] ) {
        return $fields;
    }

    $variations = $product->get_available_variations();
    if ( empty( $variations[0]['attributes'] ) || ! is_array( $variations[0]['attributes'] ) ) {
        return $fields;
    }

    $keys = array_keys( $variations[0]['attributes'] );
    if ( ! $fields['color'] && isset( $keys[0] ) ) {
        $fields['color'] = (string) $keys[0];
    }
    if ( ! $fields['size'] && isset( $keys[1] ) ) {
        $fields['size'] = (string) $keys[1];
    }

    return $fields;
}


/**
 * Enqueue variable product scripts and styles.
 *
 * Loads JS for image gallery with zoom, color selector with visual swatches,
 * and size selector with availability indicators.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_variable_product_assets() {
    if ( ! mi_cliente_theme_is_woocommerce_active() ) {
        return;
    }

    if ( ! is_product() ) {
        return;
    }

    global $product;
    if ( ! $product || ! $product->is_type( 'variable' ) ) {
        return;
    }

    $script_path = get_template_directory() . '/assets/js/variable-product.js';
    wp_enqueue_script(
        'mi-cliente-variable-product',
        get_template_directory_uri() . '/assets/js/variable-product.js',
        array( 'jquery', 'wc-add-to-cart-variation' ),
        file_exists( $script_path ) ? (string) filemtime( $script_path ) : wp_get_theme()->get( 'Version' ),
        true
    );

    $variations_data = mi_cliente_theme_get_variations_data( $product );
    $taxonomies      = mi_cliente_theme_get_variation_attribute_taxonomies( $product );
    $form_fields     = mi_cliente_theme_resolve_variation_form_fields( $product );

    wp_localize_script( 'mi-cliente-variable-product', 'miClienteVariations', array(
        'variations'       => $variations_data,
        'attributeFields'  => $form_fields,
        'colorTaxonomy'    => $taxonomies['color'],
        'sizeTaxonomy'     => $taxonomies['size'],
        'galleryZoom'      => true,
        'i18n'        => array(
            'outOfStock'   => __( 'Agotado', 'mi-cliente-theme' ),
            'selectColor'  => __( 'Selecciona un color', 'mi-cliente-theme' ),
            'selectSize'   => __( 'Selecciona una talla', 'mi-cliente-theme' ),
            'inStock'      => __( 'Disponible', 'mi-cliente-theme' ),
            'unavailable'  => __( 'No disponible', 'mi-cliente-theme' ),
        ),
    ) );
}
add_action( 'wp_enqueue_scripts', 'mi_cliente_theme_variable_product_assets' );


/**
 * Get structured variations data for a variable product.
 *
 * Returns variation data organized by color, including available sizes,
 * stock status, images, and pricing for each combination.
 *
 * @since 1.0.0
 *
 * @param WC_Product_Variable $product The variable product.
 * @return array Structured variations data.
 */
function mi_cliente_theme_get_variations_data( $product ) {
    $variations = $product->get_available_variations();
    $taxonomies = mi_cliente_theme_get_variation_attribute_taxonomies( $product );
    $color_tax  = $taxonomies['color'];
    $size_tax   = $taxonomies['size'];
    $data       = array(
        'colors' => array(),
        'sizes'  => array(),
    );

    foreach ( $variations as $variation ) {
        $attrs = isset( $variation['attributes'] ) && is_array( $variation['attributes'] )
            ? $variation['attributes']
            : array();
        $color = mi_cliente_theme_get_variation_attribute_value( $attrs, $color_tax );
        $size  = mi_cliente_theme_get_variation_attribute_value( $attrs, $size_tax );

        if ( $color && ! isset( $data['colors'][ $color ] ) ) {
            $color_term = $color_tax ? get_term_by( 'slug', $color, $color_tax ) : false;
            $color_hex  = get_term_meta(
                $color_term ? $color_term->term_id : 0,
                'mi_cliente_color_hex',
                true
            );
            $data['colors'][ $color ] = array(
                'name'  => $color_term ? $color_term->name : $color,
                'slug'  => $color,
                'hex'   => $color_hex ? $color_hex : '#cccccc',
                'image' => $variation['image']['url'] ?? '',
                'sizes' => array(),
            );
        }

        if ( $color && $size ) {
            $data['colors'][ $color ]['sizes'][ $size ] = array(
                'variation_id' => $variation['variation_id'],
                'in_stock'     => $variation['is_in_stock'],
                'max_qty'      => $variation['max_qty'] ?? 0,
                'price_html'   => $variation['price_html'],
                'image'        => $variation['image']['url'] ?? '',
            );
        }

        if ( $size && ! in_array( $size, $data['sizes'], true ) ) {
            $data['sizes'][] = $size;
        }
    }

    return $data;
}


/**
 * Resolve a display hex for a color value.
 *
 * Order of precedence:
 *   1. Term meta `mi_cliente_color_hex` (set in admin / by ERP sync for
 *      filterable attributes).
 *   2. A built-in map of common Spanish/English color names.
 *
 * Returns '' when no color can be resolved (caller falls back to the
 * product image swatch instead of a flat color).
 *
 * @since 1.0.0
 *
 * @param string $name Color term name (e.g. "Verde", "Rojo").
 * @param int    $term_id Optional term ID to read stored hex meta.
 * @return string Hex color (e.g. "#1f9d3a") or '' if unknown.
 */
function mi_cliente_theme_resolve_color_hex( string $name, int $term_id = 0 ): string {
    if ( $term_id > 0 ) {
        $stored = get_term_meta( $term_id, 'mi_cliente_color_hex', true );
        if ( is_string( $stored ) && sanitize_hex_color( $stored ) ) {
            return strtolower( sanitize_hex_color( $stored ) );
        }
    }

    // Common color names → hex. Keyed by a normalized (accent-stripped,
    // lowercased) name so "Café", "cafe" and "CAFE" all match.
    $map = array(
        'negro'      => '#1a1a1a',
        'black'      => '#1a1a1a',
        'blanco'     => '#ffffff',
        'white'      => '#ffffff',
        'gris'       => '#9aa0a6',
        'gray'       => '#9aa0a6',
        'grey'       => '#9aa0a6',
        'plata'      => '#c0c0c0',
        'plateado'   => '#c0c0c0',
        'silver'     => '#c0c0c0',
        'rojo'       => '#d32f2f',
        'red'        => '#d32f2f',
        'vino'       => '#7b1f2b',
        'guinda'     => '#7b1f2b',
        'rosa'       => '#e91e8c',
        'rosado'     => '#e91e8c',
        'pink'       => '#e91e8c',
        'fucsia'     => '#c2185b',
        'naranja'    => '#f57c00',
        'orange'     => '#f57c00',
        'amarillo'   => '#fbc02d',
        'yellow'     => '#fbc02d',
        'dorado'     => '#d4af37',
        'oro'        => '#d4af37',
        'gold'       => '#d4af37',
        'verde'      => '#1f9d3a',
        'green'      => '#1f9d3a',
        'verde claro'=> '#7cb342',
        'verde oscuro'=> '#1b5e20',
        'oliva'      => '#808000',
        'turquesa'   => '#1abc9c',
        'celeste'    => '#4fc3f7',
        'azul'       => '#1976d2',
        'blue'       => '#1976d2',
        'azul marino'=> '#1a237e',
        'marino'     => '#1a237e',
        'navy'       => '#1a237e',
        'morado'     => '#7b1fa2',
        'violeta'    => '#7b1fa2',
        'purple'     => '#7b1fa2',
        'lila'       => '#b39ddb',
        'marron'     => '#795548',
        'cafe'       => '#6d4c41',
        'brown'      => '#795548',
        'beige'      => '#e8d8b0',
        'crema'      => '#f3e9d2',
        'camel'      => '#c19a6b',
        'khaki'      => '#bdb76b',
        'caqui'      => '#bdb76b',
        'multicolor' => 'linear-gradient(135deg,#d32f2f 0 25%,#fbc02d 25% 50%,#1f9d3a 50% 75%,#1976d2 75% 100%)',
    );

    $key = strtolower( trim( remove_accents( $name ) ) );

    return isset( $map[ $key ] ) ? $map[ $key ] : '';
}

/**
 * Render variable product color swatches.
 *
 * Outputs visual color swatches that update the main product image
 * and available sizes when selected.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_render_color_swatches() {
    global $product;

    if ( ! $product || ! $product->is_type( 'variable' ) ) {
        return;
    }

    $taxonomies      = mi_cliente_theme_get_variation_attribute_taxonomies( $product );
    $color_tax       = $taxonomies['color'] ?: 'pa_color';
    $variations_data = mi_cliente_theme_get_variations_data( $product );
    $allowed_colors  = array_keys( $variations_data['colors'] );
    $colors          = wc_get_product_terms( $product->get_id(), $color_tax, array( 'fields' => 'all' ) );

    if ( empty( $colors ) ) {
        return;
    }

    if ( ! empty( $allowed_colors ) ) {
        $colors = array_values(
            array_filter(
                $colors,
                static function ( $term ) use ( $allowed_colors ) {
                    return $term instanceof WP_Term && in_array( $term->slug, $allowed_colors, true );
                }
            )
        );
    }

    if ( empty( $colors ) ) {
        return;
    }

    // Hide the native Color dropdown row in the variations table: the custom
    // swatches below replace it (they sync the hidden native <select> via JS).
    // Talla keeps its native dropdown. Attribute taxonomy codes are dynamic
    // (e.g. pa_at-0002), so target the row by the resolved color field name.
    $color_field   = 'attribute_' . $color_tax;
    $color_images  = array();
    foreach ( $variations_data['colors'] as $slug => $info ) {
        if ( ! empty( $info['image'] ) ) {
            $color_images[ $slug ] = $info['image'];
        }
    }
    ?>
    <style>
        .variations tr:has( select[name="<?php echo esc_attr( $color_field ); ?>"] ) {
            display: none;
        }
        .mi-cliente-color-swatches {
            margin: 0 0 18px;
        }
        .mi-cliente-color-swatches .swatches-label {
            display: inline-block;
            font-weight: 600;
            margin-right: 6px;
        }
        .mi-cliente-color-swatches .swatches-selected-name {
            color: var(--wp--preset--color--contrast, #333);
        }
        .mi-cliente-color-swatches .swatches-list {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-top: 10px;
        }
        .mi-cliente-color-swatches .color-swatch {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 0;
            border: 0;
            background: none;
            cursor: pointer;
        }
        .mi-cliente-color-swatches .color-swatch__chip {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            border: 2px solid rgba(0, 0, 0, 0.12);
            background-size: cover;
            background-position: center;
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        }
        .mi-cliente-color-swatches .color-swatch__name {
            font-size: 12px;
            line-height: 1.2;
            color: var(--wp--preset--color--contrast, #444);
            max-width: 72px;
            text-align: center;
        }
        .mi-cliente-color-swatches .color-swatch:hover .color-swatch__chip {
            transform: scale(1.06);
        }
        .mi-cliente-color-swatches .color-swatch[aria-checked="true"] .color-swatch__chip {
            border-color: var(--wp--preset--color--accent, #1f9d3a);
            box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--wp--preset--color--accent, #1f9d3a);
        }
        .mi-cliente-color-swatches .color-swatch[aria-checked="true"] .color-swatch__name {
            font-weight: 600;
        }
    </style>
    <div class="mi-cliente-color-swatches" role="radiogroup" aria-label="<?php esc_attr_e( 'Seleccionar color', 'mi-cliente-theme' ); ?>">
        <span class="swatches-label"><?php esc_html_e( 'Color:', 'mi-cliente-theme' ); ?></span>
        <span class="swatches-selected-name" aria-live="polite"></span>
        <div class="swatches-list">
            <?php
            foreach ( $colors as $color ) :
                $image_url = $color_images[ $color->slug ] ?? '';
                $hex       = mi_cliente_theme_resolve_color_hex( $color->name, (int) $color->term_id );

                // Prefer the real product image for this color; fall back to a
                // resolved flat color; last resort a neutral grey.
                if ( '' !== $image_url ) {
                    $chip_style = sprintf( "background-image: url('%s');", esc_url( $image_url ) );
                } elseif ( '' !== $hex && 0 === strpos( $hex, 'linear-gradient' ) ) {
                    $chip_style = sprintf( 'background-image: %s;', $hex );
                } elseif ( '' !== $hex ) {
                    $chip_style = sprintf( 'background-color: %s;', esc_attr( $hex ) );
                } else {
                    $chip_style = 'background-color: #cccccc;';
                }
            ?>
                <button type="button"
                    class="color-swatch"
                    data-color="<?php echo esc_attr( $color->slug ); ?>"
                    data-color-name="<?php echo esc_attr( $color->name ); ?>"
                    role="radio"
                    aria-checked="false"
                    aria-label="<?php echo esc_attr( $color->name ); ?>"
                    title="<?php echo esc_attr( $color->name ); ?>">
                    <span class="color-swatch__chip" style="<?php echo $chip_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" aria-hidden="true"></span>
                    <span class="color-swatch__name"><?php echo esc_html( $color->name ); ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
add_action( 'woocommerce_before_variations_form', 'mi_cliente_theme_render_color_swatches' );


/**
 * Render variable product size boxes.
 *
 * Outputs the size (Talla) attribute as clickable boxes instead of the native
 * <select>, mirroring the color swatches. Boxes drive the hidden native size
 * dropdown via JS, and sizes with no stock for the chosen color are shown
 * struck-through (unavailable).
 *
 * @since 1.0.0
 */
function mi_cliente_theme_render_size_boxes() {
    global $product;

    if ( ! $product || ! $product->is_type( 'variable' ) ) {
        return;
    }

    $taxonomies = mi_cliente_theme_get_variation_attribute_taxonomies( $product );
    $size_tax   = $taxonomies['size'];

    if ( '' === $size_tax ) {
        return;
    }

    $variations_data = mi_cliente_theme_get_variations_data( $product );
    $allowed_sizes   = $variations_data['sizes']; // term slugs present in variations.
    $sizes           = wc_get_product_terms( $product->get_id(), $size_tax, array( 'fields' => 'all' ) );

    if ( empty( $sizes ) ) {
        return;
    }

    if ( ! empty( $allowed_sizes ) ) {
        $sizes = array_values(
            array_filter(
                $sizes,
                static function ( $term ) use ( $allowed_sizes ) {
                    return $term instanceof WP_Term && in_array( $term->slug, $allowed_sizes, true );
                }
            )
        );
    }

    if ( empty( $sizes ) ) {
        return;
    }

    // Numeric sort so 7.5, 8, 8.5, 9, 9.5, 10 display in order.
    usort(
        $sizes,
        static function ( $a, $b ) {
            $av = (float) str_replace( ',', '.', $a->name );
            $bv = (float) str_replace( ',', '.', $b->name );
            if ( $av === $bv ) {
                return strcmp( $a->name, $b->name );
            }
            return ( $av < $bv ) ? -1 : 1;
        }
    );

    // Hide the native Talla dropdown row; the boxes below replace it (they sync
    // the hidden native <select> via JS). Taxonomy codes are dynamic.
    $size_field = 'attribute_' . $size_tax;
    ?>
    <style>
        .variations tr:has( select[name="<?php echo esc_attr( $size_field ); ?>"] ) {
            display: none;
        }
        .mi-cliente-size-boxes {
            margin: 0 0 18px;
        }
        .mi-cliente-size-boxes .size-boxes-label {
            display: inline-block;
            font-weight: 600;
            margin-right: 6px;
        }
        .mi-cliente-size-boxes .size-boxes-selected-name {
            color: var(--wp--preset--color--contrast, #333);
        }
        .mi-cliente-size-boxes .size-boxes-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .mi-cliente-size-boxes .size-box {
            min-width: 52px;
            padding: 12px 10px;
            border: 1px solid rgba(0, 0, 0, 0.35);
            border-radius: 8px;
            background: #fff;
            font-size: 15px;
            line-height: 1;
            color: var(--wp--preset--color--contrast, #333);
            cursor: pointer;
            text-align: center;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }
        .mi-cliente-size-boxes .size-box:hover {
            border-color: var(--wp--preset--color--accent, #1f9d3a);
        }
        .mi-cliente-size-boxes .size-box--active {
            border-color: var(--wp--preset--color--accent, #1f9d3a);
            box-shadow: 0 0 0 1px var(--wp--preset--color--accent, #1f9d3a);
            font-weight: 600;
        }
        /* Unavailable size: struck-through diagonal + muted. */
        .mi-cliente-size-boxes .size-box.is-unavailable {
            color: #9aa0a6;
            border-color: rgba(0, 0, 0, 0.15);
            cursor: not-allowed;
            background-image: linear-gradient(
                to top left,
                transparent calc(50% - 1px),
                rgba(0, 0, 0, 0.35) 50%,
                transparent calc(50% + 1px)
            );
        }
        .mi-cliente-size-boxes .size-box.is-unavailable:hover {
            border-color: rgba(0, 0, 0, 0.15);
        }
    </style>
    <div class="mi-cliente-size-boxes" role="radiogroup" aria-label="<?php esc_attr_e( 'Seleccionar talla', 'mi-cliente-theme' ); ?>">
        <span class="size-boxes-label"><?php esc_html_e( 'Talla:', 'mi-cliente-theme' ); ?></span>
        <span class="size-boxes-selected-name" aria-live="polite"><?php esc_html_e( 'Selecciona una talla', 'mi-cliente-theme' ); ?></span>
        <div class="size-boxes-list">
            <?php foreach ( $sizes as $size ) : ?>
                <button type="button"
                    class="size-box"
                    data-size="<?php echo esc_attr( $size->slug ); ?>"
                    data-size-name="<?php echo esc_attr( $size->name ); ?>"
                    role="radio"
                    aria-checked="false"
                    aria-label="<?php echo esc_attr( $size->name ); ?>">
                    <?php echo esc_html( $size->name ); ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
add_action( 'woocommerce_before_variations_form', 'mi_cliente_theme_render_size_boxes', 15 );


/**
 * Render product image gallery with zoom support.
 *
 * Outputs a gallery with main image and thumbnails. Supports zoom on hover
 * and updates main image when a color swatch is selected.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_render_product_gallery() {
    global $product;

    if ( ! $product ) {
        return;
    }

    $gallery_ids = $product->get_gallery_image_ids();
    $main_image_id = $product->get_image_id();

    if ( ! $main_image_id ) {
        return;
    }

    $all_images = array_merge( array( $main_image_id ), $gallery_ids );
    ?>
    <div class="mi-cliente-product-gallery" aria-label="<?php esc_attr_e( 'Galería de producto', 'mi-cliente-theme' ); ?>">
        <div class="gallery-main">
            <div class="gallery-main__image-wrapper" data-zoom="true">
                <?php echo wp_get_attachment_image(
                    $main_image_id,
                    'woocommerce_single',
                    false,
                    array(
                        'class'       => 'gallery-main__image',
                        'id'          => 'mi-cliente-main-product-image',
                        'data-zoom-src' => wp_get_attachment_image_url( $main_image_id, 'full' ),
                    )
                ); ?>
                <div class="gallery-zoom-lens" aria-hidden="true"></div>
            </div>
        </div>
        <?php if ( count( $all_images ) > 1 ) : ?>
        <div class="gallery-thumbnails" role="list" aria-label="<?php esc_attr_e( 'Miniaturas', 'mi-cliente-theme' ); ?>">
            <?php foreach ( $all_images as $index => $image_id ) : ?>
                <button type="button"
                    class="gallery-thumbnail <?php echo 0 === $index ? 'gallery-thumbnail--active' : ''; ?>"
                    data-image-url="<?php echo esc_url( wp_get_attachment_image_url( $image_id, 'woocommerce_single' ) ); ?>"
                    data-zoom-url="<?php echo esc_url( wp_get_attachment_image_url( $image_id, 'full' ) ); ?>"
                    role="listitem"
                    aria-label="<?php printf( esc_attr__( 'Imagen %d', 'mi-cliente-theme' ), $index + 1 ); ?>">
                    <?php echo wp_get_attachment_image( $image_id, 'woocommerce_gallery_thumbnail' ); ?>
                </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
}


/**
 * Render product card for catalog/archive pages.
 *
 * Displays: main image, product name, price, discount badge, and color swatches.
 *
 * @since 1.0.0
 *
 * @param WC_Product|null $product Optional product object. Uses global if not provided.
 */
function mi_cliente_theme_render_product_card( $product = null ) {
    if ( ! $product ) {
        global $product;
    }

    if ( ! $product ) {
        return;
    }

    $permalink = get_permalink( $product->get_id() );
    $image_id  = $product->get_image_id();
    $on_sale   = $product->is_on_sale();
    $colors    = wc_get_product_terms( $product->get_id(), 'pa_color', array( 'fields' => 'all' ) );
    ?>
    <article class="mi-cliente-product-card" aria-label="<?php echo esc_attr( $product->get_name() ); ?>">
        <a href="<?php echo esc_url( $permalink ); ?>" class="product-card__image-link">
            <div class="product-card__image-wrapper">
                <?php if ( $image_id ) : ?>
                    <?php echo wp_get_attachment_image( $image_id, 'woocommerce_thumbnail', false, array(
                        'class' => 'product-card__image',
                        'loading' => 'lazy',
                    ) ); ?>
                <?php else : ?>
                    <img src="<?php echo esc_url( wc_placeholder_img_src() ); ?>"
                        alt="<?php echo esc_attr( $product->get_name() ); ?>"
                        class="product-card__image" loading="lazy">
                <?php endif; ?>

                <?php if ( $on_sale ) : ?>
                    <?php
                    $discount_pct = '';
                    if ( $product->is_type( 'simple' ) ) {
                        $regular = (float) $product->get_regular_price();
                        $sale    = (float) $product->get_sale_price();
                        if ( $regular > 0 ) {
                            $discount_pct = round( ( ( $regular - $sale ) / $regular ) * 100 );
                        }
                    }
                    ?>
                    <span class="product-card__badge product-card__badge--sale" aria-label="<?php esc_attr_e( 'En oferta', 'mi-cliente-theme' ); ?>">
                        <?php if ( $discount_pct ) : ?>
                            -<?php echo esc_html( $discount_pct ); ?>%
                        <?php else : ?>
                            <?php esc_html_e( 'Oferta', 'mi-cliente-theme' ); ?>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>
        </a>

        <div class="product-card__info">
            <h3 class="product-card__name">
                <a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
            </h3>

            <div class="product-card__price">
                <?php echo wp_kses_post( $product->get_price_html() ); ?>
            </div>

            <?php if ( ! empty( $colors ) ) : ?>
            <div class="product-card__colors" aria-label="<?php esc_attr_e( 'Colores disponibles', 'mi-cliente-theme' ); ?>">
                <?php foreach ( array_slice( $colors, 0, 5 ) as $color ) :
                    $hex = get_term_meta( $color->term_id, 'mi_cliente_color_hex', true );
                    $hex = $hex ? $hex : '#cccccc';
                ?>
                    <span class="product-card__color-dot"
                        style="background-color: <?php echo esc_attr( $hex ); ?>;"
                        title="<?php echo esc_attr( $color->name ); ?>"
                        aria-label="<?php echo esc_attr( $color->name ); ?>"></span>
                <?php endforeach; ?>
                <?php if ( count( $colors ) > 5 ) : ?>
                    <span class="product-card__color-more">+<?php echo count( $colors ) - 5; ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </article>
    <?php
}

/**
 * Save color hex meta for product color terms.
 *
 * Adds a color picker field to the pa_color taxonomy edit screen.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_color_term_fields( $term ) {
    $hex = '';
    if ( is_object( $term ) ) {
        $hex = get_term_meta( $term->term_id, 'mi_cliente_color_hex', true );
    }
    ?>
    <tr class="form-field">
        <th scope="row"><label for="mi_cliente_color_hex"><?php esc_html_e( 'Color (Hex)', 'mi-cliente-theme' ); ?></label></th>
        <td>
            <input type="color" name="mi_cliente_color_hex" id="mi_cliente_color_hex"
                value="<?php echo esc_attr( $hex ? $hex : '#cccccc' ); ?>">
            <p class="description"><?php esc_html_e( 'Selecciona el color para la muestra visual.', 'mi-cliente-theme' ); ?></p>
        </td>
    </tr>
    <?php
}
add_action( 'pa_color_edit_form_fields', 'mi_cliente_theme_color_term_fields' );

/**
 * Add color hex field to new term form.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_color_term_add_fields() {
    ?>
    <div class="form-field">
        <label for="mi_cliente_color_hex"><?php esc_html_e( 'Color (Hex)', 'mi-cliente-theme' ); ?></label>
        <input type="color" name="mi_cliente_color_hex" id="mi_cliente_color_hex" value="#cccccc">
        <p class="description"><?php esc_html_e( 'Selecciona el color para la muestra visual.', 'mi-cliente-theme' ); ?></p>
    </div>
    <?php
}
add_action( 'pa_color_add_form_fields', 'mi_cliente_theme_color_term_add_fields' );

/**
 * Save color hex meta on term save.
 *
 * @since 1.0.0
 *
 * @param int $term_id The term ID.
 */
function mi_cliente_theme_save_color_term_meta( $term_id ) {
    if ( isset( $_POST['mi_cliente_color_hex'] ) ) {
        update_term_meta(
            $term_id,
            'mi_cliente_color_hex',
            sanitize_hex_color( wp_unslash( $_POST['mi_cliente_color_hex'] ) )
        );
    }
}
add_action( 'edited_pa_color', 'mi_cliente_theme_save_color_term_meta' );
add_action( 'created_pa_color', 'mi_cliente_theme_save_color_term_meta' );

/**
 * Min/max catalog prices for the current shop or product taxonomy view.
 *
 * @return array{min: float, max: float}|null
 */
function mi_cliente_theme_get_shop_price_bounds(): ?array {
	if ( ! mi_cliente_theme_is_woocommerce_active() ) {
		return null;
	}

	$query_args = array(
		'limit'  => -1,
		'status' => 'publish',
		'return' => 'ids',
	);

	if ( is_product_taxonomy() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => $term->taxonomy,
					'field'    => 'term_id',
					'terms'    => $term->term_id,
				),
			);
		}
	}

	$product_ids = wc_get_products( $query_args );
	if ( empty( $product_ids ) ) {
		return null;
	}

	$min = null;
	$max = 0.0;

	foreach ( $product_ids as $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			continue;
		}
		$price = (float) $product->get_price();
		if ( $price <= 0 ) {
			continue;
		}
		$min = null === $min ? $price : min( $min, $price );
		$max = max( $max, $price );
	}

	if ( null === $min || $max <= 0 || $min > $max ) {
		return null;
	}

	return array(
		'min' => $min,
		'max' => $max,
	);
}

/**
 * Output hidden fields to preserve active query args in filter forms.
 *
 * @param array<int, string> $exclude Query keys to skip.
 */
function mi_cliente_theme_shop_filter_hidden_fields( array $exclude = array() ): void {
	$exclude = array_merge( $exclude, array( 'min_price', 'max_price', 'filter', 'submit', 'paged', 'page' ) );

	foreach ( $_GET as $key => $value ) {
		if ( in_array( $key, $exclude, true ) || is_array( $value ) ) {
			continue;
		}
		$value = sanitize_text_field( wp_unslash( (string) $value ) );
		if ( '' === $value ) {
			continue;
		}
		echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
	}

	foreach ( $_GET as $key => $value ) {
		if ( ! is_array( $value ) || in_array( $key, $exclude, true ) ) {
			continue;
		}
		foreach ( $value as $item ) {
			$item = sanitize_text_field( wp_unslash( (string) $item ) );
			if ( '' === $item ) {
				continue;
			}
			echo '<input type="hidden" name="' . esc_attr( $key ) . '[]" value="' . esc_attr( $item ) . '">';
		}
	}
}

/**
 * Apply shop filters to Product Collection block queries (FSE archives).
 *
 * @param array<string, mixed> $query Query vars.
 * @param WP_Block             $block Block instance.
 * @param int                  $page  Page number.
 * @return array<string, mixed>
 */
function mi_cliente_theme_shop_filters_query_loop( array $query, WP_Block $block, int $page ): array {
	unset( $page );

	if ( ! mi_cliente_theme_is_woocommerce_active() ) {
		return $query;
	}

	if ( ! isset( $block->name ) || 'woocommerce/product-collection' !== $block->name ) {
		return $query;
	}

	$tax_query  = isset( $query['tax_query'] ) && is_array( $query['tax_query'] ) ? $query['tax_query'] : array();
	$meta_query = isset( $query['meta_query'] ) && is_array( $query['meta_query'] ) ? $query['meta_query'] : array();

	$tax_query  = mi_cliente_theme_apply_shop_attribute_tax_query( $tax_query );
	$meta_query = mi_cliente_theme_apply_shop_price_meta_query( $meta_query );

	if ( ! empty( $tax_query ) ) {
		$query['tax_query'] = $tax_query;
	}

	if ( ! empty( $meta_query ) ) {
		$query['meta_query'] = $meta_query;
	}

	return $query;
}
add_filter( 'query_loop_block_query_vars', 'mi_cliente_theme_shop_filters_query_loop', 20, 3 );

/**
 * CSS title modifier for shop filter groups (mockup: green vs navy headings).
 *
 * @param string $taxonomy pa_* taxonomy.
 */
function mi_cliente_theme_shop_filter_title_class( string $taxonomy ): string {
	return 'pa_color' === $taxonomy
		? 'mi-cliente-shop-filters__title--navy'
		: 'mi-cliente-shop-filters__title--accent';
}

/**
 * Render checkbox list for a shop attribute filter.
 *
 * @param string              $filter_name   Query param (e.g. filter_color).
 * @param string              $taxonomy      pa_* taxonomy.
 * @param array<int, WP_Term> $terms         Terms to show.
 * @param array<int, string>  $active_values Selected slugs.
 */
function mi_cliente_theme_render_attribute_filter_options( string $filter_name, string $taxonomy, array $terms, array $active_values ): void {
	if ( 'pa_color' === $taxonomy ) {
		mi_cliente_theme_render_attribute_filter_color_swatches( $filter_name, $terms, $active_values );
		return;
	}

	echo '<ul class="mi-cliente-attribute-filter__list">';
	foreach ( $terms as $term ) {
		if ( ! $term instanceof WP_Term ) {
			continue;
		}
		$is_checked = in_array( $term->slug, $active_values, true );
		echo '<li class="mi-cliente-attribute-filter__item">';
		echo '<label class="mi-cliente-attribute-filter__option">';
		printf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" %3$s onchange="this.form.submit()">',
			esc_attr( $filter_name ),
			esc_attr( $term->slug ),
			checked( $is_checked, true, false )
		);
		echo '<span class="mi-cliente-attribute-filter__label">' . esc_html( $term->name ) . '</span>';
		echo '</label></li>';
	}
	echo '</ul>';
}

/**
 * Color attribute filter as circular swatches (uses term meta mi_cliente_color_hex).
 *
 * @param string              $filter_name   Query param.
 * @param array<int, WP_Term> $terms         Color terms.
 * @param array<int, string>  $active_values Selected slugs.
 */
/**
 * Resolve display hex for a pa_color term (meta, name map, or neutral fallback).
 *
 * @param WP_Term $term Color attribute term.
 */
function mi_cliente_theme_get_color_term_hex( WP_Term $term ): string {
	$stored = get_term_meta( $term->term_id, 'mi_cliente_color_hex', true );
	if ( is_string( $stored ) && sanitize_hex_color( $stored ) ) {
		return sanitize_hex_color( $stored );
	}
	return '';
}

function mi_cliente_theme_render_attribute_filter_color_swatches( string $filter_name, array $terms, array $active_values ): void {
	echo '<ul class="mi-cliente-attribute-filter__swatches">';
	foreach ( $terms as $term ) {
		if ( ! $term instanceof WP_Term ) {
			continue;
		}
		$is_checked = in_array( $term->slug, $active_values, true );
		$hex        = mi_cliente_theme_get_color_term_hex( $term );
		$light      = in_array( strtolower( $hex ), array( '#ffffff', '#fff', '#f8f9fa', '#fafafa' ), true );

		echo '<li class="mi-cliente-attribute-filter__swatch-item">';
		echo '<label class="mi-cliente-color-swatch' . ( $is_checked ? ' is-active' : '' ) . ( $light ? ' is-light' : '' ) . '">';
		printf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" %3$s onchange="this.form.submit()">',
			esc_attr( $filter_name ),
			esc_attr( $term->slug ),
			checked( $is_checked, true, false )
		);
		printf(
			'<span class="mi-cliente-color-swatch__dot" style="background-color:%s" aria-hidden="true"></span>',
			esc_attr( $hex )
		);
		echo '<span class="screen-reader-text">' . esc_html( $term->name ) . '</span>';
		echo '</label></li>';
	}
	echo '</ul>';
}

/**
 * Scripts for shop archive filters (price range slider).
 */
function mi_cliente_theme_shop_archive_assets(): void {
	if ( ! mi_cliente_theme_is_woocommerce_active() || ! ( is_shop() || is_product_taxonomy() ) ) {
		return;
	}

	wp_enqueue_script(
		'mi-cliente-shop-filters',
		get_template_directory_uri() . '/assets/js/shop-filters.js',
		array(),
		'1.0.0',
		true
	);
}
add_action( 'wp_enqueue_scripts', 'mi_cliente_theme_shop_archive_assets', 25 );

/**
 * Render one attribute filter block in the shop sidebar.
 *
 * @param object               $attribute              WC attribute row.
 * @param array<int, object>   $attribute_taxonomies All attributes (for hidden fields).
 * @param string               $action_url             Form action URL.
 */
function mi_cliente_theme_render_shop_attribute_filter_group( $attribute, array $attribute_taxonomies, string $action_url ): void {
	$taxonomy    = 'pa_' . $attribute->attribute_name;
	$label       = $attribute->attribute_label ? $attribute->attribute_label : $attribute->attribute_name;
	$filter_name = 'filter_' . $attribute->attribute_name;
	$title_class = mi_cliente_theme_shop_filter_title_class( $taxonomy );

	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
		)
	);

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return;
	}

	$raw_value = isset( $_GET[ $filter_name ] ) ? $_GET[ $filter_name ] : '';
	if ( is_array( $raw_value ) ) {
		$active_values = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $raw_value ) );
	} else {
		$active_values = array_filter( array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( (string) $raw_value ) ) ) ) );
	}
	?>
	<div class="mi-cliente-shop-filters__group mi-cliente-shop-filters__group--<?php echo esc_attr( sanitize_html_class( $attribute->attribute_name ) ); ?>">
		<h3 class="mi-cliente-shop-filters__title <?php echo esc_attr( $title_class ); ?>">
			<?php echo esc_html( $label ); ?>
		</h3>
		<form class="mi-cliente-attribute-filter" method="get" action="<?php echo esc_url( $action_url ); ?>">
			<?php
			if ( ! empty( $_GET['min_price'] ) ) {
				$min_hidden = wc_format_decimal( wp_unslash( (string) $_GET['min_price'] ), wc_get_price_decimals() );
				echo '<input type="hidden" name="min_price" value="' . esc_attr( $min_hidden ) . '">';
			}
			if ( ! empty( $_GET['max_price'] ) ) {
				$max_hidden = wc_format_decimal( wp_unslash( (string) $_GET['max_price'] ), wc_get_price_decimals() );
				echo '<input type="hidden" name="max_price" value="' . esc_attr( $max_hidden ) . '">';
			}
			foreach ( $attribute_taxonomies as $other_attr ) {
				$other_name = 'filter_' . $other_attr->attribute_name;
				if ( $other_name === $filter_name || empty( $_GET[ $other_name ] ) ) {
					continue;
				}
				$other_raw  = $_GET[ $other_name ];
				$other_vals = is_array( $other_raw )
					? array_map( 'sanitize_text_field', array_map( 'wp_unslash', $other_raw ) )
					: array( sanitize_text_field( wp_unslash( (string) $other_raw ) ) );
				foreach ( $other_vals as $ov ) {
					if ( '' !== $ov ) {
						echo '<input type="hidden" name="' . esc_attr( $other_name ) . '[]" value="' . esc_attr( $ov ) . '">';
					}
				}
			}
			mi_cliente_theme_render_attribute_filter_options( $filter_name, $taxonomy, $terms, $active_values );
			?>
		</form>
	</div>
	<?php
}

/**
 * Price filter for shop and category archives.
 *
 * Usage: [mi_cliente_price_filter]
 *
 * @return string
 */
function mi_cliente_theme_price_filter_shortcode(): string {
	if ( ! mi_cliente_theme_is_woocommerce_active() || ! ( is_shop() || is_product_taxonomy() ) ) {
		return '';
	}

	$bounds = mi_cliente_theme_get_shop_price_bounds();
	if ( ! $bounds ) {
		return '';
	}

	$action_url = get_pagenum_link( 1, false );
	$action_url = strtok( (string) $action_url, '?' );

	$min_bound = (float) $bounds['min'];
	$max_bound = (float) $bounds['max'];

	$current_min = isset( $_GET['min_price'] ) ? (float) wc_format_decimal( wp_unslash( (string) $_GET['min_price'] ), wc_get_price_decimals() ) : $min_bound;
	$current_max = isset( $_GET['max_price'] ) ? (float) wc_format_decimal( wp_unslash( (string) $_GET['max_price'] ), wc_get_price_decimals() ) : $max_bound;

	$current_min = max( $min_bound, min( $current_min, $max_bound ) );
	$current_max = max( $min_bound, min( $current_max, $max_bound ) );

	ob_start();
	?>
	<div class="mi-cliente-shop-filters__group mi-cliente-price-filter">
		<h3 class="mi-cliente-shop-filters__title mi-cliente-shop-filters__title--accent">
			<?php esc_html_e( 'Filtrar por precio', 'mi-cliente-theme' ); ?>
		</h3>
		<form class="mi-cliente-price-filter__form" method="get" action="<?php echo esc_url( $action_url ); ?>">
			<?php mi_cliente_theme_shop_filter_hidden_fields(); ?>
			<div class="mi-cliente-price-filter__slider">
				<div class="mi-cliente-price-filter__slider-track" aria-hidden="true"></div>
				<input type="range" class="mi-cliente-price-filter__range mi-cliente-price-filter__range--min" min="<?php echo esc_attr( (string) $min_bound ); ?>" max="<?php echo esc_attr( (string) $max_bound ); ?>" step="0.01" value="<?php echo esc_attr( (string) $current_min ); ?>" aria-label="<?php esc_attr_e( 'Precio mínimo', 'mi-cliente-theme' ); ?>">
				<input type="range" class="mi-cliente-price-filter__range mi-cliente-price-filter__range--max" min="<?php echo esc_attr( (string) $min_bound ); ?>" max="<?php echo esc_attr( (string) $max_bound ); ?>" step="0.01" value="<?php echo esc_attr( (string) $current_max ); ?>" aria-label="<?php esc_attr_e( 'Precio máximo', 'mi-cliente-theme' ); ?>">
			</div>
			<div class="mi-cliente-price-filter__inputs">
				<label class="screen-reader-text" for="mi-cliente-min-price"><?php esc_html_e( 'Precio mínimo', 'mi-cliente-theme' ); ?></label>
				<input type="number" id="mi-cliente-min-price" class="mi-cliente-price-filter__input" name="min_price" min="<?php echo esc_attr( (string) $min_bound ); ?>" max="<?php echo esc_attr( (string) $max_bound ); ?>" step="0.01" value="<?php echo esc_attr( (string) $current_min ); ?>">
				<span class="mi-cliente-price-filter__sep" aria-hidden="true">~</span>
				<label class="screen-reader-text" for="mi-cliente-max-price"><?php esc_html_e( 'Precio máximo', 'mi-cliente-theme' ); ?></label>
				<input type="number" id="mi-cliente-max-price" class="mi-cliente-price-filter__input" name="max_price" min="<?php echo esc_attr( (string) $min_bound ); ?>" max="<?php echo esc_attr( (string) $max_bound ); ?>" step="0.01" value="<?php echo esc_attr( (string) $current_max ); ?>">
			</div>
			<button type="submit" class="mi-cliente-shop-filters__btn mi-cliente-shop-filters__btn--navy mi-cliente-price-filter__submit">
				<?php esc_html_e( 'Filtrar', 'mi-cliente-theme' ); ?>
			</button>
		</form>
	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'mi_cliente_price_filter', 'mi_cliente_theme_price_filter_shortcode' );
