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

    $tax_query = $query->get( 'tax_query', array() );

    $filter_map = array(
        'filter_tipo'   => 'pa_tipo',
        'filter_talla'  => 'pa_talla',
        'filter_genero' => 'pa_genero',
        'filter_color'  => 'pa_color',
        'filter_marca'  => 'pa_marca',
    );

    foreach ( $filter_map as $param => $taxonomy ) {
        if ( ! empty( $_GET[ $param ] ) ) {
            $tax_query[] = array(
                'taxonomy' => $taxonomy,
                'field'    => 'slug',
                'terms'    => sanitize_text_field( wp_unslash( $_GET[ $param ] ) ),
            );
        }
    }

    if ( ! empty( $tax_query ) ) {
        $tax_query['relation'] = 'AND';
        $query->set( 'tax_query', $tax_query );
    }

    // Price range filter.
    $meta_query = $query->get( 'meta_query', array() );
    if ( ! empty( $_GET['min_price'] ) ) {
        $meta_query[] = array(
            'key'     => '_price',
            'value'   => absint( $_GET['min_price'] ),
            'compare' => '>=',
            'type'    => 'NUMERIC',
        );
    }
    if ( ! empty( $_GET['max_price'] ) ) {
        $meta_query[] = array(
            'key'     => '_price',
            'value'   => absint( $_GET['max_price'] ),
            'compare' => '<=',
            'type'    => 'NUMERIC',
        );
    }
    if ( ! empty( $meta_query ) ) {
        $query->set( 'meta_query', $meta_query );
    }
}
add_action( 'pre_get_posts', 'mi_cliente_theme_apply_product_filters' );


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

    wp_enqueue_script(
        'mi-cliente-variable-product',
        get_template_directory_uri() . '/assets/js/variable-product.js',
        array( 'jquery', 'wc-add-to-cart-variation' ),
        wp_get_theme()->get( 'Version' ),
        true
    );

    $variations_data = mi_cliente_theme_get_variations_data( $product );

    wp_localize_script( 'mi-cliente-variable-product', 'miClienteVariations', array(
        'variations'  => $variations_data,
        'galleryZoom' => true,
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
    $data = array(
        'colors' => array(),
        'sizes'  => array(),
    );

    foreach ( $variations as $variation ) {
        $color = isset( $variation['attributes']['attribute_pa_color'] )
            ? $variation['attributes']['attribute_pa_color'] : '';
        $size  = isset( $variation['attributes']['attribute_pa_talla'] )
            ? $variation['attributes']['attribute_pa_talla'] : '';

        if ( $color && ! isset( $data['colors'][ $color ] ) ) {
            $color_term = get_term_by( 'slug', $color, 'pa_color' );
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

    $colors = wc_get_product_terms( $product->get_id(), 'pa_color', array( 'fields' => 'all' ) );

    if ( empty( $colors ) ) {
        return;
    }
    ?>
    <div class="mi-cliente-color-swatches" role="radiogroup" aria-label="<?php esc_attr_e( 'Seleccionar color', 'mi-cliente-theme' ); ?>">
        <span class="swatches-label"><?php esc_html_e( 'Color:', 'mi-cliente-theme' ); ?></span>
        <span class="swatches-selected-name" aria-live="polite"></span>
        <div class="swatches-list">
            <?php foreach ( $colors as $color ) :
                $hex = get_term_meta( $color->term_id, 'mi_cliente_color_hex', true );
                $hex = $hex ? $hex : '#cccccc';
            ?>
                <button type="button"
                    class="color-swatch"
                    data-color="<?php echo esc_attr( $color->slug ); ?>"
                    data-color-name="<?php echo esc_attr( $color->name ); ?>"
                    style="background-color: <?php echo esc_attr( $hex ); ?>;"
                    role="radio"
                    aria-checked="false"
                    aria-label="<?php echo esc_attr( $color->name ); ?>"
                    title="<?php echo esc_attr( $color->name ); ?>">
                    <span class="screen-reader-text"><?php echo esc_html( $color->name ); ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
add_action( 'woocommerce_before_variations_form', 'mi_cliente_theme_render_color_swatches' );


/**
 * Render variable product size selector with availability.
 *
 * Shows only sizes with stock for the selected color.
 * Out-of-stock variations are greyed out and unselectable.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_render_size_selector() {
    global $product;

    if ( ! $product || ! $product->is_type( 'variable' ) ) {
        return;
    }

    $sizes = wc_get_product_terms( $product->get_id(), 'pa_talla', array( 'fields' => 'all' ) );

    if ( empty( $sizes ) ) {
        return;
    }
    ?>
    <div class="mi-cliente-size-selector" role="radiogroup" aria-label="<?php esc_attr_e( 'Seleccionar talla', 'mi-cliente-theme' ); ?>">
        <span class="size-label"><?php esc_html_e( 'Talla:', 'mi-cliente-theme' ); ?></span>
        <div class="size-options">
            <?php foreach ( $sizes as $size ) : ?>
                <button type="button"
                    class="size-option"
                    data-size="<?php echo esc_attr( $size->slug ); ?>"
                    role="radio"
                    aria-checked="false"
                    aria-label="<?php echo esc_attr( $size->name ); ?>">
                    <?php echo esc_html( $size->name ); ?>
                </button>
            <?php endforeach; ?>
        </div>
        <span class="size-availability" aria-live="polite"></span>
    </div>
    <?php
}
add_action( 'woocommerce_before_variations_form', 'mi_cliente_theme_render_size_selector', 15 );


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
