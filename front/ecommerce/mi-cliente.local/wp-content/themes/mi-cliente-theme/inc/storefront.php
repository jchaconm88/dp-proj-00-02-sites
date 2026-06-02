<?php
/**
 * Storefront — portada D.Sam (markup + CSS de templates/mi-cliente.local/code.html)
 *
 * Customizer, componentes reutilizables y shortcodes para la tienda WooCommerce.
 *
 * @package Mi_Cliente_Theme
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carga un fragmento HTML de template-parts/storefront/{slug}.php
 *
 * @param string               $slug Nombre del archivo sin .php.
 * @param array<string, mixed> $args Variables disponibles en la plantilla.
 */
function mi_cliente_storefront_template( $slug, $args = array() ) {
	$path = get_template_directory() . '/template-parts/storefront/' . $slug . '.php';
	if ( ! is_readable( $path ) ) {
		return;
	}
	// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- plantillas con variables nombradas.
	extract( $args, EXTR_SKIP );
	include $path;
}

/**
 * Valores por defecto alineados con templates/mi-cliente.local/code.html
 *
 * @return array<string, mixed>
 */
function mi_cliente_storefront_defaults() {
	return array(
		'announcement_lines'   => "Oferta en zapatillas: 2x150 y 3x210 + envío GRATIS a todo el Perú\nContra entrega a todo el Perú - Paga al recibir",
		'hero_badge'           => 'Oferta Exclusiva',
		'hero_title'           => "Promo en\nZAPATILLAS 2x150",
		'hero_text'            => 'Combina tus modelos favoritos y paga contra entrega en todo el Perú. ¡Envío gratis incluido!',
		'hero_primary_label'   => 'VER CATÁLOGO',
		'hero_secondary_label' => 'MÁS INFO',
		'shipping_bar_text'    => 'Envíos a todo Perú (+ de 7 años)',
		'categories_title'     => 'Tienda Online: Contra Entrega a todo Perú',
		'promo_banner_text'    => 'EN ZAPATILLAS 2XS/150 O 3XS/210 + ENVÍO GRATIS - COMBINA MODELO, TALLA Y COLOR',
		'whatsapp_cta_text'    => 'Haz tu pedido aquí o por WhatsApp',
		'whatsapp_cta_phone'   => '+51 965 801 878',
		'orders_online_url'    => 'https://wa.link/cct7yu',
		'footer_tagline'       => 'D.Sam - Expertos en calzado y artículos para el hogar. Con más de 7 años de experiencia brindando los mejores productos con entrega segura a todo el Perú.',
	);
}

/**
 * Obtiene un valor del Customizer con fallback.
 *
 * @param string $key Clave sin prefijo mi_cliente_.
 * @return string
 */
function mi_cliente_storefront_mod( $key ) {
	$defaults = mi_cliente_storefront_defaults();
	$default  = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
	return (string) get_theme_mod( 'mi_cliente_' . $key, $default );
}

/**
 * URL de tienda WooCommerce.
 *
 * @return string
 */
function mi_cliente_storefront_shop_url() {
	if ( mi_cliente_theme_is_woocommerce_active() && function_exists( 'wc_get_page_permalink' ) ) {
		return wc_get_page_permalink( 'shop' );
	}
	return home_url( '/tienda/' );
}

/**
 * Enlace de categoría de producto por slug.
 *
 * @param string $slug Slug de product_cat.
 * @return string
 */
function mi_cliente_storefront_category_url( $slug ) {
	if ( ! $slug || ! mi_cliente_theme_is_woocommerce_active() ) {
		return mi_cliente_storefront_shop_url();
	}
	$term = get_term_by( 'slug', $slug, 'product_cat' );
	if ( $term && ! is_wp_error( $term ) ) {
		$link = get_term_link( $term );
		if ( ! is_wp_error( $link ) ) {
			return $link;
		}
	}
	return add_query_arg( 'product_cat', $slug, mi_cliente_storefront_shop_url() );
}

/**
 * Registro Customizer — Apariencia → Tienda D.Sam
 *
 * @param WP_Customize_Manager $wp_customize Manager.
 */
function mi_cliente_storefront_customize_register( $wp_customize ) {
	$wp_customize->add_section(
		'mi_cliente_storefront',
		array(
			'title'    => __( 'Tienda D.Sam (inicio)', 'mi-cliente-theme' ),
			'priority' => 30,
		)
	);

	$fields = array(
		'announcement_lines'   => array( 'label' => __( 'Barra superior (una línea por fila)', 'mi-cliente-theme' ), 'type' => 'textarea' ),
		'shipping_bar_text'    => array( 'label' => __( 'Barra de envíos', 'mi-cliente-theme' ), 'type' => 'text' ),
		'categories_title'     => array( 'label' => __( 'Título categorías rápidas', 'mi-cliente-theme' ), 'type' => 'text' ),
		'promo_banner_text'    => array( 'label' => __( 'Banner promo azul', 'mi-cliente-theme' ), 'type' => 'text' ),
		'whatsapp_cta_text'    => array( 'label' => __( 'CTA WhatsApp (texto)', 'mi-cliente-theme' ), 'type' => 'text' ),
		'whatsapp_cta_phone'   => array( 'label' => __( 'CTA WhatsApp (teléfono visible)', 'mi-cliente-theme' ), 'type' => 'text' ),
		'orders_online_url'    => array( 'label' => __( 'URL pedidos online / WhatsApp', 'mi-cliente-theme' ), 'type' => 'url' ),
		'footer_tagline'       => array( 'label' => __( 'Descripción footer', 'mi-cliente-theme' ), 'type' => 'textarea' ),
	);

	$defaults = mi_cliente_storefront_defaults();

	foreach ( $fields as $key => $field ) {
		$setting_id = 'mi_cliente_' . $key;
		if ( 'textarea' === $field['type'] ) {
			$sanitize = 'sanitize_textarea_field';
		} elseif ( 'url' === $field['type'] ) {
			$sanitize = 'esc_url_raw';
		} elseif ( 'image' === $field['type'] ) {
			$sanitize = 'absint';
		} else {
			$sanitize = 'sanitize_text_field';
		}

		$wp_customize->add_setting(
			$setting_id,
			array(
				'default'           => 'image' === $field['type'] ? 0 : ( $defaults[ $key ] ?? '' ),
				'sanitize_callback' => $sanitize,
				'transport'         => 'refresh',
			)
		);

		if ( 'image' === $field['type'] ) {
			$wp_customize->add_control(
				new WP_Customize_Media_Control(
					$wp_customize,
					$setting_id,
					array(
						'label'     => $field['label'],
						'section'   => 'mi_cliente_storefront',
						'mime_type' => 'image',
						'settings'  => $setting_id,
					)
				)
			);
			continue;
		}

		$wp_customize->add_control(
			$setting_id,
			array(
				'label'   => $field['label'],
				'section' => 'mi_cliente_storefront',
				'type'    => 'textarea' === $field['type'] ? 'textarea' : 'text',
			)
		);
	}

	// Hero slider (JSON).
	$wp_customize->add_setting(
		'mi_cliente_hero_slides',
		array(
			'default'           => wp_json_encode( mi_cliente_storefront_default_hero_slides() ),
			'sanitize_callback' => 'mi_cliente_storefront_sanitize_hero_slides_json',
			'transport'         => 'refresh',
		)
	);

	if ( ! class_exists( 'Mi_Cliente_Hero_Slides_Control', false ) ) {
		require_once get_template_directory() . '/inc/customizer/class-hero-slides-control.php';
	}

	$wp_customize->add_control(
		new Mi_Cliente_Hero_Slides_Control(
			$wp_customize,
			'mi_cliente_hero_slides',
			array(
				'label'       => __( 'Slider hero (diapositivas)', 'mi-cliente-theme' ),
				'description' => __( 'Añade, ordena y edita cada banner del inicio. Publica los cambios para verlos en la web.', 'mi-cliente-theme' ),
				'section'     => 'mi_cliente_storefront',
				'settings'    => 'mi_cliente_hero_slides',
			)
		)
	);

	// Secciones de productos en inicio (JSON en theme_mod).
	$wp_customize->add_setting(
		'mi_cliente_home_sections',
		array(
			'default'           => wp_json_encode( mi_cliente_storefront_default_sections() ),
			'sanitize_callback' => 'mi_cliente_storefront_sanitize_home_sections_json',
			'transport'         => 'refresh',
		)
	);

	if ( ! class_exists( 'Mi_Cliente_Home_Sections_Control', false ) ) {
		require_once get_template_directory() . '/inc/customizer/class-home-sections-control.php';
	}

	$wp_customize->add_control(
		new Mi_Cliente_Home_Sections_Control(
			$wp_customize,
			'mi_cliente_home_sections',
			array(
				'label'       => __( 'Secciones de productos', 'mi-cliente-theme' ),
				'description' => __( 'Bloques de la portada con productos por categoría WooCommerce. Ordena, edita o añade secciones.', 'mi-cliente-theme' ),
				'section'     => 'mi_cliente_storefront',
				'settings'    => 'mi_cliente_home_sections',
			)
		)
	);

	// Categorías rápidas.
	$wp_customize->add_setting(
		'mi_cliente_quick_categories',
		array(
			'default'           => wp_json_encode( mi_cliente_storefront_default_quick_categories() ),
			'sanitize_callback' => 'mi_cliente_storefront_sanitize_quick_categories_json',
			'transport'         => 'refresh',
		)
	);

	if ( ! class_exists( 'Mi_Cliente_Quick_Categories_Control', false ) ) {
		require_once get_template_directory() . '/inc/customizer/class-quick-categories-control.php';
	}

	$wp_customize->add_control(
		new Mi_Cliente_Quick_Categories_Control(
			$wp_customize,
			'mi_cliente_quick_categories',
			array(
				'label'       => __( 'Categorías rápidas', 'mi-cliente-theme' ),
				'description' => __( 'Círculos de acceso rápido bajo el hero. Ordena y edita cada categoría.', 'mi-cliente-theme' ),
				'section'     => 'mi_cliente_storefront',
				'settings'    => 'mi_cliente_quick_categories',
			)
		)
	);

	$wp_customize->add_setting(
		'mi_cliente_home_nav_extra',
		array(
			'default'           => wp_json_encode( mi_cliente_storefront_default_nav_extra() ),
			'sanitize_callback' => 'mi_cliente_storefront_sanitize_nav_extra_json',
			'transport'         => 'refresh',
		)
	);

	if ( ! class_exists( 'Mi_Cliente_Nav_Extra_Control', false ) ) {
		require_once get_template_directory() . '/inc/customizer/class-nav-extra-control.php';
	}

	$wp_customize->add_control(
		new Mi_Cliente_Nav_Extra_Control(
			$wp_customize,
			'mi_cliente_home_nav_extra',
			array(
				'label'       => __( 'Menú extra', 'mi-cliente-theme' ),
				'description' => __( 'Enlaces adicionales del menú superior (p. ej. Entregas, Tienda). No son bloques de productos.', 'mi-cliente-theme' ),
				'section'     => 'mi_cliente_storefront',
				'settings'    => 'mi_cliente_home_nav_extra',
			)
		)
	);
}
add_action( 'customize_register', 'mi_cliente_storefront_customize_register' );

/**
 * Opciones de ancla para el botón principal del hero.
 *
 * @return array<string, string> slug => etiqueta.
 */
function mi_cliente_storefront_hero_section_choices() {
	$choices = array(
		'tienda' => __( 'Tienda (catálogo)', 'mi-cliente-theme' ),
	);

	foreach ( mi_cliente_storefront_get_sections() as $section ) {
		if ( empty( $section['section_id'] ) ) {
			continue;
		}
		$id = sanitize_title( $section['section_id'] );
		$choices[ $id ] = ! empty( $section['menu_label'] )
			? (string) $section['menu_label']
			: ( ! empty( $section['title'] ) ? (string) $section['title'] : $id );
	}

	$extras = mi_cliente_storefront_get_json_mod( 'home_nav_extra', mi_cliente_storefront_default_nav_extra() );
	foreach ( $extras as $extra ) {
		if ( ! is_array( $extra ) || empty( $extra['section_id'] ) ) {
			continue;
		}
		$id             = sanitize_title( $extra['section_id'] );
		$choices[ $id ] = ! empty( $extra['menu_label'] ) ? (string) $extra['menu_label'] : $id;
	}

	return $choices;
}

/**
 * Scripts y estilos de controles repetidor en el Customizer.
 */
function mi_cliente_storefront_customize_controls_enqueue() {
	$theme_dir = get_template_directory();
	$theme_uri = get_template_directory_uri();
	$version   = wp_get_theme()->get( 'Version' );

	$assets = array(
		array(
			'style' => '/assets/css/customizer-hero-slides.css',
			'script' => '/assets/js/customizer-hero-slides.js',
			'style_handle' => 'mi-cliente-customizer-hero-slides',
			'script_handle' => 'mi-cliente-customizer-hero-slides',
		),
		array(
			'style' => '/assets/css/customizer-home-sections.css',
			'script' => '/assets/js/customizer-home-sections.js',
			'style_handle' => 'mi-cliente-customizer-home-sections',
			'script_handle' => 'mi-cliente-customizer-home-sections',
		),
		array(
			'style'         => '/assets/css/customizer-quick-categories.css',
			'script'        => '/assets/js/customizer-quick-categories.js',
			'style_handle'  => 'mi-cliente-customizer-quick-categories',
			'script_handle' => 'mi-cliente-customizer-quick-categories',
		),
		array(
			'style'         => '/assets/css/customizer-nav-extra.css',
			'script'        => '/assets/js/customizer-nav-extra.js',
			'style_handle'  => 'mi-cliente-customizer-nav-extra',
			'script_handle' => 'mi-cliente-customizer-nav-extra',
		),
	);

	wp_enqueue_media();

	foreach ( $assets as $asset ) {
		$css_path = $theme_dir . $asset['style'];
		$js_path  = $theme_dir . $asset['script'];
		$css_ver  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : $version;
		$js_ver   = file_exists( $js_path ) ? (string) filemtime( $js_path ) : $version;

		wp_enqueue_style(
			$asset['style_handle'],
			$theme_uri . $asset['style'],
			array(),
			$css_ver
		);
		wp_enqueue_script(
			$asset['script_handle'],
			$theme_uri . $asset['script'],
			array( 'jquery', 'customize-controls' ),
			$js_ver,
			true
		);
	}

	$attachment_urls = array();
	$raw             = get_theme_mod( 'mi_cliente_hero_slides', '' );
	$slides          = json_decode( (string) $raw, true );
	if ( ! is_array( $slides ) || empty( $slides ) ) {
		$slides = mi_cliente_storefront_default_hero_slides();
	}
	foreach ( $slides as $slide ) {
		if ( ! is_array( $slide ) || empty( $slide['image'] ) ) {
			continue;
		}
		$id = absint( $slide['image'] );
		if ( $id && ! isset( $attachment_urls[ $id ] ) ) {
			$url = wp_get_attachment_image_url( $id, 'medium' );
			if ( $url ) {
				$attachment_urls[ $id ] = $url;
			}
		}
	}

	wp_localize_script(
		'mi-cliente-customizer-hero-slides',
		'miClienteHeroSlidesCustomizer',
		array(
			'defaultSlides'  => wp_json_encode( mi_cliente_storefront_default_hero_slides() ),
			'sectionChoices' => mi_cliente_storefront_hero_section_choices(),
			'attachmentUrls' => $attachment_urls,
			'i18n'           => array(
				'slideLabel'     => __( 'Diapositiva', 'mi-cliente-theme' ),
				'image'          => __( 'Imagen de fondo', 'mi-cliente-theme' ),
				'selectImage'    => __( 'Elegir imagen', 'mi-cliente-theme' ),
				'clearImage'     => __( 'Quitar', 'mi-cliente-theme' ),
				'useImage'       => __( 'Usar imagen', 'mi-cliente-theme' ),
				'imageHint'      => __( 'Opcional: URL externa si no subes imagen a la biblioteca.', 'mi-cliente-theme' ),
				'badge'          => __( 'Etiqueta', 'mi-cliente-theme' ),
				'title'          => __( 'Título', 'mi-cliente-theme' ),
				'titleHint'      => __( 'Pulsa Enter para una segunda línea.', 'mi-cliente-theme' ),
				'text'           => __( 'Descripción', 'mi-cliente-theme' ),
				'primaryLabel'   => __( 'Texto botón principal', 'mi-cliente-theme' ),
				'linkMode'       => __( 'Enlace del botón', 'mi-cliente-theme' ),
				'linkSection'    => __( 'Sección de la portada', 'mi-cliente-theme' ),
				'linkUrl'        => __( 'URL personalizada', 'mi-cliente-theme' ),
				'primarySection' => __( 'Ir a sección', 'mi-cliente-theme' ),
				'primaryUrl'     => __( 'URL del botón', 'mi-cliente-theme' ),
				'secondary'      => __( 'Botón secundario (opcional)', 'mi-cliente-theme' ),
				'secondaryLabel' => __( 'Texto botón secundario', 'mi-cliente-theme' ),
				'secondaryUrl'   => __( 'URL botón secundario', 'mi-cliente-theme' ),
				'remove'         => __( 'Eliminar', 'mi-cliente-theme' ),
				'moveUp'         => __( 'Subir', 'mi-cliente-theme' ),
				'moveDown'       => __( 'Bajar', 'mi-cliente-theme' ),
				'minSlides'      => __( 'Debe haber al menos una diapositiva.', 'mi-cliente-theme' ),
			),
		)
	);

	wp_localize_script(
		'mi-cliente-customizer-home-sections',
		'miClienteHomeSectionsCustomizer',
		array(
			'defaultSections'    => wp_json_encode( mi_cliente_storefront_default_sections() ),
			'layoutChoices'      => mi_cliente_storefront_section_layout_choices(),
			'backgroundChoices'  => mi_cliente_storefront_section_background_choices(),
			'iconChoices'        => mi_cliente_storefront_section_icon_choices(),
			'productCategories'  => mi_cliente_storefront_product_category_choices(),
			'i18n'               => array(
				'sectionLabel'  => __( 'Sección', 'mi-cliente-theme' ),
				'menuLabel'     => __( 'Texto en menú', 'mi-cliente-theme' ),
				'showInNav'     => __( 'Mostrar en menú superior', 'mi-cliente-theme' ),
				'sectionId'     => __( 'ID de ancla (section_id)', 'mi-cliente-theme' ),
				'sectionIdHint' => __( 'Ancla en la portada (#calzado-mujer). Si está vacío, se usa el slug de categoría.', 'mi-cliente-theme' ),
				'category'      => __( 'Categoría WooCommerce (slug)', 'mi-cliente-theme' ),
				'menuIcon'      => __( 'Icono del menú', 'mi-cliente-theme' ),
				'title'         => __( 'Título de bloque', 'mi-cliente-theme' ),
				'subtitle'      => __( 'Subtítulo', 'mi-cliente-theme' ),
				'layout'        => __( 'Diseño de tarjetas', 'mi-cliente-theme' ),
				'limit'         => __( 'Cantidad de productos', 'mi-cliente-theme' ),
				'background'    => __( 'Fondo de sección', 'mi-cliente-theme' ),
				'viewMore'      => __( 'Texto enlace «ver más»', 'mi-cliente-theme' ),
				'remove'        => __( 'Eliminar', 'mi-cliente-theme' ),
				'moveUp'        => __( 'Subir', 'mi-cliente-theme' ),
				'moveDown'      => __( 'Bajar', 'mi-cliente-theme' ),
				'minSections'   => __( 'Debe haber al menos una sección.', 'mi-cliente-theme' ),
			),
		)
	);

	$quick_attachment_urls = array();
	$quick_raw             = get_theme_mod( 'mi_cliente_quick_categories', '' );
	$quick_items           = json_decode( (string) $quick_raw, true );
	if ( ! is_array( $quick_items ) || empty( $quick_items ) ) {
		$quick_items = mi_cliente_storefront_default_quick_categories();
	}
	foreach ( $quick_items as $quick_item ) {
		if ( ! is_array( $quick_item ) || empty( $quick_item['image'] ) ) {
			continue;
		}
		$qid = absint( $quick_item['image'] );
		if ( $qid && ! isset( $quick_attachment_urls[ $qid ] ) ) {
			$url = wp_get_attachment_image_url( $qid, 'thumbnail' );
			if ( $url ) {
				$quick_attachment_urls[ $qid ] = $url;
			}
		}
	}

	wp_localize_script(
		'mi-cliente-customizer-quick-categories',
		'miClienteQuickCategoriesCustomizer',
		array(
			'defaultItems'      => wp_json_encode( mi_cliente_storefront_default_quick_categories() ),
			'variantChoices'    => mi_cliente_storefront_quick_category_variant_choices(),
			'sectionChoices'    => mi_cliente_storefront_hero_section_choices(),
			'iconChoices'       => mi_cliente_storefront_section_icon_choices(),
			'productCategories' => mi_cliente_storefront_product_category_choices(),
			'attachmentUrls'    => $quick_attachment_urls,
			'i18n'              => array(
				'itemLabel'       => __( 'Categoría', 'mi-cliente-theme' ),
				'label'           => __( 'Nombre visible', 'mi-cliente-theme' ),
				'variant'         => __( 'Tipo de círculo', 'mi-cliente-theme' ),
				'sectionId'       => __( 'Enlace a sección', 'mi-cliente-theme' ),
				'url'             => __( 'URL alternativa (opcional)', 'mi-cliente-theme' ),
				'urlHint'         => __( 'Si se deja vacío, usa el enlace de la sección.', 'mi-cliente-theme' ),
				'category'        => __( 'Slug categoría WooCommerce', 'mi-cliente-theme' ),
				'image'           => __( 'Imagen del círculo', 'mi-cliente-theme' ),
				'selectImage'     => __( 'Elegir imagen', 'mi-cliente-theme' ),
				'clearImage'      => __( 'Quitar', 'mi-cliente-theme' ),
				'useImage'        => __( 'Usar imagen', 'mi-cliente-theme' ),
				'imageHint'       => __( 'O URL externa. Si hay slug, puede usarse la miniatura de WooCommerce.', 'mi-cliente-theme' ),
				'fallbackIcon'    => __( 'Icono si no hay imagen', 'mi-cliente-theme' ),
				'promoLine1'      => __( 'Línea promo 1', 'mi-cliente-theme' ),
				'promoLine2'      => __( 'Línea promo 2', 'mi-cliente-theme' ),
				'icon'            => __( 'Icono', 'mi-cliente-theme' ),
				'iconCaption'     => __( 'Texto bajo el icono', 'mi-cliente-theme' ),
				'iconCaptionHint' => __( 'Enter para segunda línea.', 'mi-cliente-theme' ),
				'remove'          => __( 'Eliminar', 'mi-cliente-theme' ),
				'moveUp'          => __( 'Subir', 'mi-cliente-theme' ),
				'moveDown'        => __( 'Bajar', 'mi-cliente-theme' ),
				'minItems'        => __( 'Debe haber al menos una categoría.', 'mi-cliente-theme' ),
			),
		)
	);

	wp_localize_script(
		'mi-cliente-customizer-nav-extra',
		'miClienteNavExtraCustomizer',
		array(
			'defaultItems'   => wp_json_encode( mi_cliente_storefront_default_nav_extra() ),
			'sectionChoices' => mi_cliente_storefront_nav_extra_anchor_choices(),
			'iconChoices'    => mi_cliente_storefront_section_icon_choices(),
			'i18n'           => array(
				'itemLabel'     => __( 'Ítem', 'mi-cliente-theme' ),
				'menuLabel'     => __( 'Texto en menú', 'mi-cliente-theme' ),
				'showInNav'     => __( 'Mostrar en menú superior', 'mi-cliente-theme' ),
				'sectionId'     => __( 'Ancla (section_id)', 'mi-cliente-theme' ),
				'sectionIdHint' => __( 'Debe existir un bloque con el mismo id en la portada (#entregas, #tienda, etc.).', 'mi-cliente-theme' ),
				'menuIcon'      => __( 'Icono', 'mi-cliente-theme' ),
				'remove'        => __( 'Eliminar', 'mi-cliente-theme' ),
				'moveUp'        => __( 'Subir', 'mi-cliente-theme' ),
				'moveDown'      => __( 'Bajar', 'mi-cliente-theme' ),
			),
		)
	);
}
add_action( 'customize_controls_enqueue_scripts', 'mi_cliente_storefront_customize_controls_enqueue' );

/**
 * Anclas disponibles para menú extra (sin leer home_nav_extra).
 *
 * @return array<string, string>
 */
function mi_cliente_storefront_nav_extra_anchor_choices() {
	$choices = array(
		'entregas' => __( 'Entregas / testimonios', 'mi-cliente-theme' ),
		'tienda'   => __( 'Categorías rápidas', 'mi-cliente-theme' ),
	);

	foreach ( mi_cliente_storefront_get_json_mod( 'home_sections', mi_cliente_storefront_default_sections() ) as $section ) {
		if ( ! is_array( $section ) || empty( $section['section_id'] ) ) {
			continue;
		}
		$id = sanitize_title( $section['section_id'] );
		$choices[ $id ] = ! empty( $section['menu_label'] )
			? (string) $section['menu_label']
			: ( ! empty( $section['title'] ) ? (string) $section['title'] : $id );
	}

	foreach ( mi_cliente_storefront_get_json_mod( 'quick_categories', mi_cliente_storefront_default_quick_categories() ) as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$sid = mi_cliente_storefront_quick_cat_section_id( $item );
		if ( $sid ) {
			$choices[ $sid ] = ! empty( $item['label'] ) ? (string) $item['label'] : $sid;
		}
	}

	return $choices;
}

/**
 * Sanitiza JSON del menú extra.
 *
 * @param string $value JSON.
 * @return string
 */
function mi_cliente_storefront_sanitize_nav_extra_json( $value ) {
	$decoded = json_decode( wp_unslash( $value ), true );
	if ( ! is_array( $decoded ) ) {
		return wp_json_encode( mi_cliente_storefront_default_nav_extra() );
	}

	$icons = array_keys( mi_cliente_storefront_section_icon_choices() );
	$out   = array();

	foreach ( $decoded as $row ) {
		if ( ! is_array( $row ) || empty( $row['menu_label'] ) ) {
			continue;
		}

		$section_id = ! empty( $row['section_id'] ) ? sanitize_title( $row['section_id'] ) : '';
		if ( ! $section_id ) {
			continue;
		}

		$icon = isset( $row['menu_icon'] ) ? sanitize_key( $row['menu_icon'] ) : 'category';
		if ( ! in_array( $icon, $icons, true ) ) {
			$icon = 'category';
		}

		$out[] = array(
			'menu_label'  => sanitize_text_field( $row['menu_label'] ),
			'section_id'  => $section_id,
			'menu_icon'   => $icon,
			'show_in_nav' => ! empty( $row['show_in_nav'] ),
		);
	}

	return wp_json_encode( $out );
}

/**
 * Tipos de círculo en categorías rápidas.
 *
 * @return array<string, string>
 */
function mi_cliente_storefront_quick_category_variant_choices() {
	return array(
		'image'     => __( 'Imagen (foto o icono)', 'mi-cliente-theme' ),
		'promo'     => __( 'Promo (círculo azul)', 'mi-cliente-theme' ),
		'clearance' => __( 'Liquidación (círculo rojo)', 'mi-cliente-theme' ),
		'icon'      => __( 'Icono + texto', 'mi-cliente-theme' ),
	);
}

/**
 * Sanitiza JSON de categorías rápidas.
 *
 * @param string $value JSON.
 * @return string
 */
function mi_cliente_storefront_sanitize_quick_categories_json( $value ) {
	$decoded = json_decode( wp_unslash( $value ), true );
	if ( ! is_array( $decoded ) ) {
		return wp_json_encode( mi_cliente_storefront_default_quick_categories() );
	}

	$variants = array_keys( mi_cliente_storefront_quick_category_variant_choices() );
	$icons    = array_keys( mi_cliente_storefront_section_icon_choices() );
	$out      = array();

	foreach ( $decoded as $row ) {
		if ( ! is_array( $row ) || empty( $row['label'] ) ) {
			continue;
		}

		$variant = isset( $row['variant'] ) ? sanitize_key( $row['variant'] ) : 'image';
		if ( ! in_array( $variant, $variants, true ) ) {
			$variant = 'image';
		}

		$item = array(
			'label'      => sanitize_text_field( $row['label'] ),
			'variant'    => $variant,
			'section_id' => ! empty( $row['section_id'] ) ? sanitize_title( $row['section_id'] ) : '',
		);

		if ( ! empty( $row['url'] ) ) {
			$item['url'] = esc_url_raw( $row['url'] );
		}

		if ( 'image' === $variant ) {
			if ( ! empty( $row['category'] ) ) {
				$item['category'] = sanitize_title( $row['category'] );
			}
			if ( ! empty( $row['image'] ) ) {
				$item['image'] = absint( $row['image'] );
			}
			if ( ! empty( $row['image_url'] ) ) {
				$item['image_url'] = esc_url_raw( $row['image_url'] );
			}
			if ( ! empty( $row['icon'] ) ) {
				$icon = sanitize_key( $row['icon'] );
				if ( in_array( $icon, $icons, true ) ) {
					$item['icon'] = $icon;
				}
			}
		} elseif ( 'promo' === $variant ) {
			if ( ! empty( $row['promo_line1'] ) ) {
				$item['promo_line1'] = sanitize_text_field( $row['promo_line1'] );
			}
			if ( ! empty( $row['promo_line2'] ) ) {
				$item['promo_line2'] = sanitize_text_field( $row['promo_line2'] );
			}
		} elseif ( 'clearance' === $variant ) {
			if ( ! empty( $row['category'] ) ) {
				$item['category'] = sanitize_title( $row['category'] );
			}
		} elseif ( 'icon' === $variant ) {
			$icon = isset( $row['icon'] ) ? sanitize_key( $row['icon'] ) : 'chat';
			$item['icon'] = in_array( $icon, $icons, true ) ? $icon : 'chat';
			if ( ! empty( $row['icon_caption'] ) ) {
				$item['icon_caption'] = sanitize_textarea_field( $row['icon_caption'] );
			}
		}

		if ( empty( $item['section_id'] ) && ! empty( $item['category'] ) ) {
			$item['section_id'] = $item['category'];
		}

		$out[] = $item;
	}

	if ( empty( $out ) ) {
		return wp_json_encode( mi_cliente_storefront_default_quick_categories() );
	}

	return wp_json_encode( $out );
}

/**
 * Sanitiza JSON de secciones/categorías.
 *
 * @param string $value JSON.
 * @return string
 */
function mi_cliente_storefront_sanitize_sections_json( $value ) {
	$decoded = json_decode( wp_unslash( $value ), true );
	if ( ! is_array( $decoded ) ) {
		return '[]';
	}

	$normalized = array();
	foreach ( $decoded as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		if ( ! empty( $row['section_id'] ) ) {
			$row['section_id'] = sanitize_title( $row['section_id'] );
		} elseif ( ! empty( $row['category'] ) ) {
			$row['section_id'] = sanitize_title( $row['category'] );
		}
		$normalized[] = $row;
	}

	return wp_json_encode( $normalized );
}

/**
 * Diseños de tarjeta permitidos en secciones de productos.
 *
 * @return array<string, string>
 */
function mi_cliente_storefront_section_layout_choices() {
	return array(
		'featured'  => __( 'Destacado (mujer / grid grande)', 'mi-cliente-theme' ),
		'hogar'     => __( 'Hogar (rejilla compacta)', 'mi-cliente-theme' ),
		'hombre'    => __( 'Hombre (rejilla compacta)', 'mi-cliente-theme' ),
		'clearance' => __( 'Liquidación', 'mi-cliente-theme' ),
	);
}

/**
 * Fondos de sección permitidos.
 *
 * @return array<string, string>
 */
function mi_cliente_storefront_section_background_choices() {
	return array(
		'soft-gray'   => __( 'Gris suave', 'mi-cliente-theme' ),
		'white'       => __( 'Blanco', 'mi-cliente-theme' ),
		'surface-low' => __( 'Superficie baja', 'mi-cliente-theme' ),
	);
}

/**
 * Iconos disponibles para el menú de navegación.
 *
 * @return array<string, string>
 */
function mi_cliente_storefront_section_icon_choices() {
	$choices = array();
	foreach ( array_keys( mi_cliente_storefront_icon_paths() ) as $icon ) {
		$choices[ $icon ] = $icon;
	}
	return $choices;
}

/**
 * Categorías WooCommerce (slug => nombre) para autocompletar.
 *
 * @return array<string, string>
 */
function mi_cliente_storefront_product_category_choices() {
	$choices = array();
	if ( ! mi_cliente_theme_is_woocommerce_active() ) {
		return $choices;
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
		)
	);

	if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
		return $choices;
	}

	foreach ( $terms as $term ) {
		if ( $term instanceof WP_Term ) {
			$choices[ $term->slug ] = $term->name;
		}
	}

	return $choices;
}

/**
 * Sanitiza JSON de secciones de productos en portada.
 *
 * @param string $value JSON.
 * @return string
 */
function mi_cliente_storefront_sanitize_home_sections_json( $value ) {
	$decoded = json_decode( wp_unslash( $value ), true );
	if ( ! is_array( $decoded ) ) {
		return wp_json_encode( mi_cliente_storefront_default_sections() );
	}

	$layouts      = array_keys( mi_cliente_storefront_section_layout_choices() );
	$backgrounds  = array_keys( mi_cliente_storefront_section_background_choices() );
	$icons        = array_keys( mi_cliente_storefront_section_icon_choices() );
	$normalized   = array();

	foreach ( $decoded as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$layout = isset( $row['layout'] ) ? sanitize_key( $row['layout'] ) : 'featured';
		if ( ! in_array( $layout, $layouts, true ) ) {
			$layout = 'featured';
		}

		$background = isset( $row['background'] ) ? sanitize_key( $row['background'] ) : 'soft-gray';
		if ( ! in_array( $background, $backgrounds, true ) ) {
			$background = 'soft-gray';
		}

		$icon = isset( $row['menu_icon'] ) ? sanitize_key( $row['menu_icon'] ) : 'category';
		if ( ! in_array( $icon, $icons, true ) ) {
			$icon = 'category';
		}

		$section = array(
			'section_id'      => ! empty( $row['section_id'] ) ? sanitize_title( $row['section_id'] ) : '',
			'menu_label'      => isset( $row['menu_label'] ) ? sanitize_text_field( $row['menu_label'] ) : '',
			'menu_icon'       => $icon,
			'show_in_nav'     => ! empty( $row['show_in_nav'] ),
			'category'        => isset( $row['category'] ) ? sanitize_title( $row['category'] ) : '',
			'title'           => isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '',
			'subtitle'        => isset( $row['subtitle'] ) ? sanitize_textarea_field( $row['subtitle'] ) : '',
			'limit'           => max( 1, min( 24, absint( $row['limit'] ?? 4 ) ) ),
			'layout'          => $layout,
			'view_more_label' => isset( $row['view_more_label'] ) ? sanitize_text_field( $row['view_more_label'] ) : '',
			'background'      => $background,
		);

		if ( ! $section['section_id'] && $section['category'] ) {
			$section['section_id'] = $section['category'];
		}

		if ( $section['category'] || $section['title'] || $section['menu_label'] ) {
			$normalized[] = $section;
		}
	}

	if ( empty( $normalized ) ) {
		return wp_json_encode( mi_cliente_storefront_default_sections() );
	}

	return wp_json_encode( $normalized );
}

/**
 * Diapositivas hero por defecto (3 ejemplos).
 *
 * @return array<int, array<string, mixed>>
 */
function mi_cliente_storefront_default_hero_slides() {
	return array(
		array(
			'image_url'        => 'https://lh3.googleusercontent.com/aida/ADBb0uhAODJWTcLAP-sDPxhpIReM3WXHeo3v6f4NXTjqB5VrZY8g_hk7jC4vnnBaYuLsK5HSG5oCJXO5nQEizcUFDJO2uQ-l8FAhzHvkItgMNUC-STx3hwNCHIwRzHc0maizuSX_oND-nGDrF_h9NCOAuf42ZWoVcRdCNCw9bEthpvd1tlH04-LUC4f_9jwrZS-2EPSn-Gu-xELrVyMNpLrGN9NOZymeDk3bWp8KxFd8wzNc9V960WDCgiag0CY',
			'badge'            => 'Oferta Exclusiva',
			'title'            => "Promo en\nZAPATILLAS 2x150",
			'text'             => 'Combina tus modelos favoritos y paga contra entrega en todo el Perú. ¡Envío gratis incluido!',
			'primary_url'      => '',
			'primary_label'    => 'VER CATÁLOGO',
			'secondary_url'    => '',
			'secondary_label'  => '',
			'primary_section'  => 'tienda',
		),
		array(
			'image_url'        => 'https://lh3.googleusercontent.com/aida/ADBb0ugWV3m6t12F0PikNkht6Ekq93qIkXyP6EHj9cH74JegC4RXj2XVqmXi-B_NCGo_1UV6CI7G0qX9Ng4OEM1Vg4lj06lXlm1OxOrjO3s0GSIag5uUD-VzMlQH8fRLx-06KT5vKWWlVGnX1BV4AT7S0N2YMyc5bqEK_plqw7hvIkX7YmZgQKDZwQXNgWRU6V6g1LofWLDQmxTR7-R6C3-7NIFkeycRw5jCm9Klx0KNXQX3IQx3GCUVOVwTqeQ',
			'badge'            => 'Hogar y Otros',
			'title'            => "Lleva 2 y ahorra\nen electrohogar",
			'text'             => 'Oferta: lleva 2 y ahorra S/10 o lleva 3 y ahorra S/18. Entrega contra reembolso a todo el Perú.',
			'primary_url'      => '',
			'primary_label'    => 'VER HOGAR',
			'secondary_url'    => '',
			'secondary_label'  => '',
			'primary_section'  => 'hogar',
		),
		array(
			'image_url'        => 'https://lh3.googleusercontent.com/aida/ADBb0uhTAyfkdHvDT4CKztoS_roD-XEx7ogdclyy8nL2Ke46SFkyFLrGtjCpvVZmNFhMcAUm10LojzsTv0v9Z5NJPm9well6EIY_cQj3LBKNtdee5WAVuAM7wO6ZNdSo50ZN48pOAqdkmwDRS5KzpU7HA22r0j171cmvZszq33QW9IkoB43I9vRosYVMmVeJ_MSgmr1OFN07eL1Erw8SGuMjJGdstCu3ReHkEEvjsbDqOE8s52yEDx5njvC08Cs',
			'badge'            => 'Liquidación',
			'title'            => "Hasta 50% OFF",
			'text'             => 'Precios especiales en modelos seleccionados. Stock limitado — aprovecha antes de que se agoten.',
			'primary_url'      => '',
			'primary_label'    => 'VER OFERTAS',
			'secondary_url'    => '',
			'secondary_label'  => '',
			'primary_section'  => 'liquidacion',
		),
	);
}

/**
 * Sanitiza JSON del slider hero.
 *
 * @param string $value JSON.
 * @return string
 */
function mi_cliente_storefront_sanitize_hero_slides_json( $value ) {
	$decoded = json_decode( wp_unslash( $value ), true );
	if ( ! is_array( $decoded ) ) {
		return wp_json_encode( mi_cliente_storefront_default_hero_slides() );
	}

	$normalized = array();
	foreach ( $decoded as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$slide = array(
			'image'            => isset( $row['image'] ) ? absint( $row['image'] ) : 0,
			'image_url'        => ! empty( $row['image_url'] ) ? esc_url_raw( $row['image_url'] ) : '',
			'badge'            => isset( $row['badge'] ) ? sanitize_text_field( $row['badge'] ) : '',
			'title'            => isset( $row['title'] ) ? sanitize_textarea_field( $row['title'] ) : '',
			'text'             => isset( $row['text'] ) ? sanitize_textarea_field( $row['text'] ) : '',
			'primary_url'      => ! empty( $row['primary_url'] ) ? esc_url_raw( $row['primary_url'] ) : '',
			'primary_label'    => isset( $row['primary_label'] ) ? sanitize_text_field( $row['primary_label'] ) : '',
			'secondary_url'    => ! empty( $row['secondary_url'] ) ? esc_url_raw( $row['secondary_url'] ) : '',
			'secondary_label'  => isset( $row['secondary_label'] ) ? sanitize_text_field( $row['secondary_label'] ) : '',
			'primary_section'  => ! empty( $row['primary_section'] ) ? sanitize_title( (string) $row['primary_section'] ) : '',
		);
		if ( $slide['badge'] || $slide['title'] || $slide['text'] || $slide['image'] || $slide['image_url'] ) {
			$normalized[] = $slide;
		}
	}

	if ( empty( $normalized ) ) {
		return wp_json_encode( mi_cliente_storefront_default_hero_slides() );
	}

	return wp_json_encode( $normalized );
}

/**
 * URL de fondo de una diapositiva.
 *
 * @param array<string, mixed> $slide Diapositiva.
 * @return string
 */
function mi_cliente_storefront_hero_slide_image_url( $slide ) {
	if ( ! empty( $slide['image'] ) ) {
		$url = wp_get_attachment_image_url( absint( $slide['image'] ), 'large' );
		if ( $url ) {
			return $url;
		}
	}
	if ( ! empty( $slide['image_url'] ) ) {
		return (string) $slide['image_url'];
	}
	return '';
}

/**
 * Normaliza una diapositiva para la plantilla.
 *
 * @param array<string, mixed> $slide Datos crudos.
 * @return array<string, string>
 */
function mi_cliente_storefront_normalize_hero_slide( $slide ) {
	$defaults = mi_cliente_storefront_defaults();
	$section  = ! empty( $slide['primary_section'] ) ? sanitize_title( $slide['primary_section'] ) : 'tienda';

	$primary_url = ! empty( $slide['primary_url'] ) ? (string) $slide['primary_url'] : mi_cliente_storefront_anchor_url( $section );
	if ( ! $primary_url ) {
		$primary_url = mi_cliente_storefront_anchor_url( 'tienda' );
	}

	return array(
		'image_url'       => mi_cliente_storefront_hero_slide_image_url( $slide ),
		'badge'           => isset( $slide['badge'] ) ? (string) $slide['badge'] : '',
		'title'           => isset( $slide['title'] ) ? (string) $slide['title'] : '',
		'text'            => isset( $slide['text'] ) ? (string) $slide['text'] : '',
		'primary_url'     => $primary_url,
		'primary_label'   => ! empty( $slide['primary_label'] ) ? (string) $slide['primary_label'] : $defaults['hero_primary_label'],
		'secondary_url'   => ! empty( $slide['secondary_url'] ) ? (string) $slide['secondary_url'] : '',
		'secondary_label' => ! empty( $slide['secondary_label'] ) ? (string) $slide['secondary_label'] : $defaults['hero_secondary_label'],
	);
}

/**
 * Primera diapositiva desde theme_mods hero_* (migración).
 *
 * @return array<string, mixed>
 */
function mi_cliente_storefront_legacy_hero_slide() {
	$defaults = mi_cliente_storefront_defaults();
	$primary_url = get_theme_mod( 'mi_cliente_hero_primary_url', '' );
	if ( ! $primary_url ) {
		$primary_url = mi_cliente_storefront_anchor_url( 'tienda' );
	}

	return array(
		'image'           => (int) get_theme_mod( 'mi_cliente_hero_image', 0 ),
		'image_url'       => mi_cliente_storefront_image_url( 'hero_image', 'large' ),
		'badge'           => mi_cliente_storefront_mod( 'hero_badge' ),
		'title'           => mi_cliente_storefront_mod( 'hero_title' ),
		'text'            => mi_cliente_storefront_mod( 'hero_text' ),
		'primary_url'     => $primary_url,
		'primary_label'   => mi_cliente_storefront_mod( 'hero_primary_label' ),
		'secondary_url'   => (string) get_theme_mod( 'mi_cliente_hero_secondary_url', '' ),
		'secondary_label' => mi_cliente_storefront_mod( 'hero_secondary_label' ),
		'primary_section' => 'tienda',
	);
}

/**
 * ¿Hay personalización guardada en campos hero_* legacy?
 *
 * @return bool
 */
function mi_cliente_storefront_has_legacy_hero_customization() {
	$defaults = mi_cliente_storefront_defaults();
	$keys     = array( 'hero_badge', 'hero_title', 'hero_text', 'hero_primary_label', 'hero_secondary_label' );

	foreach ( $keys as $key ) {
		$mod = get_theme_mod( 'mi_cliente_' . $key, null );
		if ( null !== $mod && (string) $mod !== (string) ( $defaults[ $key ] ?? '' ) ) {
			return true;
		}
	}
	if ( (int) get_theme_mod( 'mi_cliente_hero_image', 0 ) > 0 ) {
		return true;
	}
	if ( get_theme_mod( 'mi_cliente_hero_primary_url', '' ) || get_theme_mod( 'mi_cliente_hero_secondary_url', '' ) ) {
		return true;
	}
	return false;
}

/**
 * Diapositivas del hero listas para render.
 *
 * @return array<int, array<string, string>>
 */
function mi_cliente_storefront_get_hero_slides() {
	$raw = get_theme_mod( 'mi_cliente_hero_slides', null );

	if ( null !== $raw && '' !== $raw ) {
		$decoded = json_decode( (string) $raw, true );
		if ( is_array( $decoded ) && ! empty( $decoded ) ) {
			$slides = array();
			foreach ( $decoded as $row ) {
				if ( is_array( $row ) ) {
					$slides[] = mi_cliente_storefront_normalize_hero_slide( $row );
				}
			}
			if ( ! empty( $slides ) ) {
				return $slides;
			}
		}
	}

	$defaults = mi_cliente_storefront_default_hero_slides();
	if ( mi_cliente_storefront_has_legacy_hero_customization() ) {
		$defaults[0] = array_merge( $defaults[0], mi_cliente_storefront_legacy_hero_slide() );
	}

	$slides = array();
	foreach ( $defaults as $row ) {
		$slides[] = mi_cliente_storefront_normalize_hero_slide( $row );
	}
	return $slides;
}

/**
 * Secciones de productos por defecto.
 *
 * @return array<int, array<string, mixed>>
 */
function mi_cliente_storefront_default_sections() {
	return array(
		array(
			'section_id'      => 'calzado-mujer',
			'menu_label'      => 'Calzado Mujer',
			'menu_icon'       => 'woman',
			'show_in_nav'     => true,
			'category'        => 'mujer',
			'title'           => 'Mujer - Zapatillas 2x150',
			'subtitle'        => 'Puedes combinar cualquier modelo entre mujer y hombre',
			'limit'           => 4,
			'layout'          => 'featured',
			'view_more_label' => 'Ver más productos',
			'background'      => 'soft-gray',
		),
		array(
			'section_id'      => 'hogar',
			'menu_label'      => 'Hogar y Otros',
			'menu_icon'       => 'home',
			'show_in_nav'     => true,
			'category'        => 'hogar',
			'title'           => 'Hogar y Otros',
			'subtitle'        => 'Oferta: Lleva 2 y ahorra S/10 o lleva 3 y ahorra S/18.',
			'limit'           => 5,
			'layout'          => 'hogar',
			'view_more_label' => 'Ver más productos',
			'background'      => 'white',
		),
		array(
			'section_id'      => 'calzado-hombre',
			'menu_label'      => 'Calzado Hombre',
			'menu_icon'       => 'man',
			'show_in_nav'     => true,
			'category'        => 'hombre',
			'title'           => 'Hombre - Zapatillas 2x150',
			'subtitle'        => 'Modelos urbanos y deportivos exclusivos para caballeros.',
			'limit'           => 5,
			'layout'          => 'hombre',
			'view_more_label' => 'Ver más productos',
			'background'      => 'surface-low',
		),
		array(
			'section_id'      => 'liquidacion',
			'menu_label'      => 'Liquidación',
			'menu_icon'       => 'sell',
			'show_in_nav'     => false,
			'category'        => 'liquidacion',
			'title'           => 'Liquidación',
			'subtitle'        => 'HASTA 50% DE DESCUENTO',
			'limit'           => 5,
			'layout'          => 'clearance',
			'view_more_label' => 'Ver Liquidación',
			'background'      => 'white',
		),
	);
}

/**
 * Ítems de menú adicionales (anclas en la misma página de inicio).
 *
 * @return array<int, array<string, mixed>>
 */
function mi_cliente_storefront_default_nav_extra() {
	return array(
		array(
			'section_id'  => 'entregas',
			'menu_label'  => 'Entregas',
			'menu_icon'   => 'local_shipping',
			'show_in_nav' => true,
		),
		array(
			'section_id'  => 'tienda',
			'menu_label'  => 'Tienda',
			'menu_icon'   => 'storefront',
			'show_in_nav' => true,
		),
	);
}

/**
 * Secciones de inicio normalizadas (con section_id).
 *
 * @return array<int, array<string, mixed>>
 */
function mi_cliente_storefront_get_sections() {
	$sections  = mi_cliente_storefront_get_json_mod( 'home_sections', mi_cliente_storefront_default_sections() );
	$defaults  = mi_cliente_storefront_default_sections();
	$def_by_cat = array();
	foreach ( $defaults as $def ) {
		if ( ! empty( $def['category'] ) ) {
			$def_by_cat[ $def['category'] ] = $def;
		}
	}

	$normalized = array();

	foreach ( $sections as $section ) {
		if ( ! is_array( $section ) ) {
			continue;
		}

		$cat = $section['category'] ?? '';
		if ( $cat && isset( $def_by_cat[ $cat ] ) ) {
			$def = $def_by_cat[ $cat ];
			foreach ( array( 'section_id', 'menu_label', 'menu_icon', 'show_in_nav' ) as $key ) {
				if ( ! isset( $section[ $key ] ) && isset( $def[ $key ] ) ) {
					$section[ $key ] = $def[ $key ];
				}
			}
		}

		if ( empty( $section['section_id'] ) ) {
			$section['section_id'] = ! empty( $section['category'] )
				? sanitize_title( $section['category'] )
				: 'seccion-' . count( $normalized );
		}
		$section['section_id'] = sanitize_title( $section['section_id'] );

		// Compatibilidad JSON antiguo: layout "compact" → hogar / hombre.
		if ( isset( $section['layout'] ) && 'compact' === $section['layout'] ) {
			$cat = $section['category'] ?? '';
			if ( 'hogar' === $cat ) {
				$section['layout'] = 'hogar';
			} elseif ( 'hombre' === $cat ) {
				$section['layout'] = 'hombre';
			}
		}

		$normalized[] = $section;
	}

	return $normalized;
}

/**
 * Mapa category slug → section_id para enlaces y redirecciones.
 *
 * @return array<string, string>
 */
function mi_cliente_storefront_section_id_by_category() {
	$map = array();
	foreach ( mi_cliente_storefront_get_sections() as $section ) {
		if ( ! empty( $section['category'] ) && ! empty( $section['section_id'] ) ) {
			$map[ sanitize_title( $section['category'] ) ] = $section['section_id'];
		}
	}
	return $map;
}

/**
 * Ítems del menú principal (dinámico desde Customizer).
 *
 * @return array<int, array<string, string>>
 */
function mi_cliente_storefront_get_nav_items() {
	$items = array();

	foreach ( mi_cliente_storefront_get_sections() as $section ) {
		if ( empty( $section['show_in_nav'] ) ) {
			continue;
		}
		$items[] = array(
			'section_id' => $section['section_id'],
			'label'      => ! empty( $section['menu_label'] ) ? $section['menu_label'] : ( $section['title'] ?? '' ),
			'icon'       => ! empty( $section['menu_icon'] ) ? $section['menu_icon'] : 'category',
		);
	}

	$extras = mi_cliente_storefront_get_json_mod( 'home_nav_extra', mi_cliente_storefront_default_nav_extra() );
	foreach ( $extras as $extra ) {
		if ( ! is_array( $extra ) || empty( $extra['show_in_nav'] ) ) {
			continue;
		}
		if ( empty( $extra['section_id'] ) ) {
			continue;
		}
		$items[] = array(
			'section_id' => sanitize_title( $extra['section_id'] ),
			'label'      => $extra['menu_label'] ?? '',
			'icon'       => $extra['menu_icon'] ?? 'link',
		);
	}

	return $items;
}

/**
 * URL de ancla hacia una sección de la portada.
 *
 * @param string $section_id ID de sección (slug).
 * @return string
 */
function mi_cliente_storefront_anchor_url( $section_id ) {
	$section_id = sanitize_title( $section_id );
	$home       = trailingslashit( home_url( '/' ) );

	if ( is_front_page() ) {
		return '#' . $section_id;
	}

	return $home . '#' . $section_id;
}

/**
 * Categorías rápidas por defecto.
 *
 * @return array<int, array<string, mixed>>
 */
function mi_cliente_storefront_default_quick_categories() {
	return array(
		array( 'label' => 'Mujer', 'category' => 'mujer', 'section_id' => 'calzado-mujer', 'variant' => 'image' ),
		array(
			'label'       => 'Ofertas 2x150',
			'section_id'  => 'calzado-mujer',
			'variant'     => 'promo',
			'promo_line1' => '2x150 y 3x210',
			'promo_line2' => '+ ENVÍO GRATIS',
		),
		array( 'label' => 'Liquidación', 'category' => 'liquidacion', 'section_id' => 'liquidacion', 'variant' => 'clearance' ),
		array( 'label' => 'Hombre', 'category' => 'hombre', 'section_id' => 'calzado-hombre', 'variant' => 'image' ),
		array( 'label' => 'Hogar y Otros', 'category' => 'hogar', 'section_id' => 'hogar', 'variant' => 'image', 'icon' => 'home' ),
		array(
			'label'        => 'Testimonios',
			'section_id'   => 'entregas',
			'variant'      => 'icon',
			'icon'         => 'chat',
			'icon_caption' => "COMENTARIOS\nENTREGAS",
		),
	);
}

/**
 * Resuelve section_id para categoría rápida.
 *
 * @param array<string, mixed> $item Ítem de categoría rápida.
 * @return string
 */
function mi_cliente_storefront_quick_cat_section_id( $item ) {
	if ( ! empty( $item['section_id'] ) ) {
		return sanitize_title( $item['section_id'] );
	}
	if ( ! empty( $item['category'] ) ) {
		$map = mi_cliente_storefront_section_id_by_category();
		$slug = sanitize_title( $item['category'] );
		if ( isset( $map[ $slug ] ) ) {
			return $map[ $slug ];
		}
	}
	return '';
}

/**
 * Decodifica theme_mod JSON.
 *
 * @param string $mod_key Sin prefijo mi_cliente_.
 * @param array  $fallback Fallback.
 * @return array
 */
function mi_cliente_storefront_get_json_mod( $mod_key, $fallback ) {
	$raw = get_theme_mod( 'mi_cliente_' . $mod_key, '' );
	if ( empty( $raw ) ) {
		return $fallback;
	}
	$decoded = json_decode( $raw, true );
	return is_array( $decoded ) ? $decoded : $fallback;
}

/**
 * Líneas de la barra de anuncios.
 *
 * @return string[]
 */
function mi_cliente_storefront_announcement_lines() {
	$text  = mi_cliente_storefront_mod( 'announcement_lines' );
	$lines = array_filter( array_map( 'trim', explode( "\n", $text ) ) );
	return ! empty( $lines ) ? $lines : array_filter( array_map( 'trim', explode( "\n", mi_cliente_storefront_defaults()['announcement_lines'] ) ) );
}

/**
 * URL de imagen desde attachment ID en theme_mod.
 *
 * @param string $mod_key Clave theme_mod (sin prefijo).
 * @param string $size    Tamaño de imagen.
 * @return string
 */
function mi_cliente_storefront_image_url( $mod_key, $size = 'full' ) {
	$id = (int) get_theme_mod( 'mi_cliente_' . $mod_key, 0 );
	if ( $id ) {
		$url = wp_get_attachment_image_url( $id, $size );
		if ( $url ) {
			return $url;
		}
	}
	return '';
}

/**
 * Render: barra de anuncios.
 */
function mi_cliente_storefront_render_announcement_bar() {
	$lines = mi_cliente_storefront_announcement_lines();
	if ( empty( $lines ) ) {
		return;
	}
	// code.html duplica las líneas para el loop continuo de la marquesina.
	$duplicated = array_merge( $lines, $lines );
	?>
	<div class="announcement-bar" role="region" aria-label="<?php esc_attr_e( 'Anuncios', 'mi-cliente-theme' ); ?>">
		<div class="scrolling-text">
			<?php foreach ( $duplicated as $line ) : ?>
				<span class="announcement-item"><?php echo esc_html( $line ); ?></span>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

/**
 * Emite chrome una sola vez (wp_body_open + shortcode en plantilla).
 */
function mi_cliente_storefront_output_chrome() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	mi_cliente_storefront_render_chrome();
}
add_action( 'wp_body_open', 'mi_cliente_storefront_output_chrome', 1 );

/**
 * Barra de anuncios + cabecera (misma estructura que code.html).
 */
function mi_cliente_storefront_render_chrome() {
	?>
	<div id="dsam-chrome-root">
		<div class="dsam-chrome">
			<?php mi_cliente_storefront_render_announcement_bar(); ?>
		</div>
		<?php mi_cliente_storefront_render_header(); ?>
	</div>
	<div id="dsam-chrome-spacer" aria-hidden="true"></div>
	<?php
}

/**
 * Render: cabecera tienda — markup idéntico a code.html.
 */
function mi_cliente_storefront_render_header() {
	$shop_url   = mi_cliente_storefront_shop_url();
	$orders_url = mi_cliente_storefront_mod( 'orders_online_url' );
	$cart_url   = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : $shop_url;
	$cart_count = 0;
	$cart_total = 'S/0.00';
	if ( mi_cliente_theme_is_woocommerce_active() && function_exists( 'WC' ) && WC()->cart ) {
		$cart_count = WC()->cart->get_cart_contents_count();
		$cart_total = WC()->cart->get_cart_total();
	}
	$logo_url = mi_cliente_storefront_logo_url();
	?>
	<header class="header" id="main-header" data-dsam-header>
		<div class="dsam-container">
			<div class="header-top">
				<a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<?php if ( $logo_url ) : ?>
						<img alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" src="<?php echo esc_url( $logo_url ); ?>" />
					<?php else : ?>
						<span><?php bloginfo( 'name' ); ?></span>
					<?php endif; ?>
				</a>
				<form class="search-container" role="search" method="get" action="<?php echo esc_url( $shop_url ); ?>">
					<label class="screen-reader-text" for="dsam-search"><?php esc_html_e( 'Buscar productos', 'mi-cliente-theme' ); ?></label>
					<input class="search-input" id="dsam-search" type="search" name="s" placeholder="<?php esc_attr_e( 'Buscar por productos...', 'mi-cliente-theme' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" />
					<input type="hidden" name="post_type" value="product" />
					<button class="search-btn" type="submit" aria-label="<?php esc_attr_e( 'Buscar', 'mi-cliente-theme' ); ?>">
						<?php mi_cliente_storefront_icon( 'search', '', 22 ); ?>
					</button>
				</form>
				<div class="header-actions">
					<?php if ( $orders_url ) : ?>
						<a class="btn-online" href="<?php echo esc_url( $orders_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'PEDIDOS ONLINE', 'mi-cliente-theme' ); ?></a>
					<?php endif; ?>
					<a class="cart-widget" href="<?php echo esc_url( $cart_url ); ?>">
						<div class="cart-icon-box dsam-header__cart-icon-wrap">
							<?php mi_cliente_storefront_icon( 'shopping_cart', 'cart-icon', 32 ); ?>
							<span class="cart-count dsam-header__cart-count" aria-live="polite"><?php echo esc_html( (string) $cart_count ); ?></span>
						</div>
						<div class="cart-info dsam-header__cart-meta">
							<span class="cart-label dsam-header__cart-label"><?php esc_html_e( 'Carrito', 'mi-cliente-theme' ); ?></span>
							<span class="cart-total dsam-header__cart-total"><?php echo wp_kses_post( $cart_total ); ?></span>
						</div>
					</a>
				</div>
			</div>
			<nav class="nav" aria-label="<?php esc_attr_e( 'Menú principal', 'mi-cliente-theme' ); ?>" data-dsam-nav>
				<?php mi_cliente_storefront_render_primary_nav(); ?>
			</nav>
		</div>
	</header>
	<?php
}

/**
 * Menú principal dinámico (anclas en la portada) — clases idénticas a code.html.
 */
function mi_cliente_storefront_render_primary_nav() {
	$items = mi_cliente_storefront_get_nav_items();
	$first = true;

	foreach ( $items as $item ) {
		if ( empty( $item['section_id'] ) || empty( $item['label'] ) ) {
			continue;
		}
		$class = 'nav-item dsam-nav-link';
		if ( $first ) {
			$class .= ' active';
		}
		$icon_name = isset( $item['icon'] ) ? (string) $item['icon'] : 'storefront';
		$icon_html = '';
		ob_start();
		mi_cliente_storefront_icon( $icon_name, 'nav-item__icon', 18 );
		$icon_html = ob_get_clean();
		printf(
			'<a href="%1$s" class="%2$s" data-dsam-section="%3$s">%4$s %5$s</a>',
			esc_url( mi_cliente_storefront_anchor_url( $item['section_id'] ) ),
			esc_attr( $class ),
			esc_attr( $item['section_id'] ),
			$icon_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html( $item['label'] )
		);
		$first = false;
	}
}

/**
 * URL del logo del sitio (custom_logo) o vacío.
 *
 * @return string
 */
function mi_cliente_storefront_logo_url() {
	$logo_id = get_theme_mod( 'custom_logo' );
	if ( $logo_id ) {
		$url = wp_get_attachment_image_url( $logo_id, 'full' );
		if ( $url ) {
			return $url;
		}
	}
	return '';
}

/**
 * Render: hero slider.
 */
function mi_cliente_storefront_render_hero() {
	$slides = mi_cliente_storefront_get_hero_slides();
	if ( empty( $slides ) ) {
		return;
	}

	mi_cliente_storefront_template(
		'hero-slider',
		array(
			'slides' => $slides,
		)
	);
}

/**
 * Render: barra de envíos.
 */
function mi_cliente_storefront_render_shipping_bar() {
	$text = mi_cliente_storefront_mod( 'shipping_bar_text' );
	if ( ! $text ) {
		return;
	}

	mi_cliente_storefront_template( 'shipping-bar', array( 'text' => $text ) );
}

/**
 * Render: rejilla de categorías rápidas.
 */
function mi_cliente_storefront_render_quick_categories() {
	$items = mi_cliente_storefront_get_json_mod( 'quick_categories', mi_cliente_storefront_default_quick_categories() );
	if ( empty( $items ) ) {
		return;
	}

	mi_cliente_storefront_template(
		'quick-categories',
		array(
			'items' => $items,
			'title' => mi_cliente_storefront_mod( 'categories_title' ),
		)
	);
}

/**
 * Consulta productos WooCommerce por categoría.
 *
 * @param string $category_slug Slug product_cat.
 * @param int    $limit         Cantidad.
 * @return WP_Post[]
 */
function mi_cliente_storefront_query_products( $category_slug, $limit = 4 ) {
	if ( ! mi_cliente_theme_is_woocommerce_active() ) {
		return array();
	}
	$args = array(
		'post_type'      => 'product',
		'posts_per_page' => max( 1, (int) $limit ),
		'post_status'    => 'publish',
	);
	if ( $category_slug ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => sanitize_title( $category_slug ),
			),
		);
	}
	$query = new WP_Query( $args );
	return $query->posts;
}

/**
 * Render tarjeta de producto estilo tienda.
 *
 * @param WC_Product $product   Producto.
 * @param string     $layout    featured|hogar|hombre|clearance.
 */
function mi_cliente_storefront_render_product_card( $product, $layout = 'featured' ) {
	mi_cliente_storefront_template(
		'product-card',
		array(
			'product' => $product,
			'layout'  => $layout,
		)
	);
}

/**
 * Render sección de productos.
 *
 * @param array<string, mixed> $section Config de sección.
 */
function mi_cliente_storefront_render_product_section( $section ) {
	$category = isset( $section['category'] ) ? $section['category'] : '';
	$title    = isset( $section['title'] ) ? $section['title'] : '';
	$subtitle = isset( $section['subtitle'] ) ? $section['subtitle'] : '';
	$limit    = isset( $section['limit'] ) ? (int) $section['limit'] : 4;
	$layout   = isset( $section['layout'] ) ? $section['layout'] : 'featured';
	$bg       = isset( $section['background'] ) ? $section['background'] : 'white';
	$more     = isset( $section['view_more_label'] ) ? $section['view_more_label'] : __( 'Ver más', 'mi-cliente-theme' );
	$more_url    = mi_cliente_storefront_category_url( $category );
	$section_id  = ! empty( $section['section_id'] )
		? sanitize_title( $section['section_id'] )
		: sanitize_title( $category );

	$posts = mi_cliente_storefront_query_products( $category, $limit );
	if ( empty( $posts ) && ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}

	$is_clearance = ( 'clearance' === $layout );

	$section_style = '';
	if ( 'soft-gray' === $bg ) {
		$section_style = 'background: #f4f4f4;';
	} elseif ( 'surface-low' === $bg ) {
		$section_style = 'background: #f8f8f8;';
	} elseif ( 'white' === $bg ) {
		$section_style = 'background: var(--white);';
	}

	$grid_class = 'products-grid';
	if ( 'hogar' === $layout || 'hombre' === $layout || 'clearance' === $layout ) {
		$grid_class = 'products-grid-5';
	}

	$more_link_class = $is_clearance ? 'section-more-link section-more-link--navy' : 'section-more-link';
	?>
	<section
		id="<?php echo esc_attr( $section_id ); ?>"
		class="section-padding"
		style="<?php echo esc_attr( $section_style ); ?>"
		data-dsam-section="<?php echo esc_attr( $section_id ); ?>"
	>
		<div class="dsam-container">
			<?php if ( $is_clearance ) : ?>
				<div class="liq-header">
					<div class="liq-bar"></div>
					<div class="liq-title">
						<h2><?php echo esc_html( $title ); ?></h2>
						<?php if ( $subtitle ) : ?>
							<span><?php echo esc_html( $subtitle ); ?></span>
						<?php endif; ?>
					</div>
					<a class="<?php echo esc_attr( $more_link_class ); ?>" style="margin-left: auto;" href="<?php echo esc_url( $more_url ); ?>"><?php echo esc_html( $more ); ?></a>
				</div>
			<?php else : ?>
				<div class="section-head-row">
					<div class="section-title-wrap">
						<h2 class="section-title"><?php echo esc_html( $title ); ?></h2>
						<?php if ( $subtitle ) : ?>
							<p class="section-subtitle"><?php echo esc_html( $subtitle ); ?></p>
						<?php endif; ?>
					</div>
					<a class="<?php echo esc_attr( $more_link_class ); ?>" href="<?php echo esc_url( $more_url ); ?>"><?php echo esc_html( strtoupper( $more ) ); ?></a>
				</div>
			<?php endif; ?>

			<?php if ( empty( $posts ) ) : ?>
				<p style="text-align:center;color:var(--text-muted);font-size:14px;"><?php esc_html_e( 'No hay productos en esta categoría. Asigna productos en WooCommerce o ajusta el slug en Apariencia → Tienda D.Sam.', 'mi-cliente-theme' ); ?></p>
			<?php else : ?>
				<div class="<?php echo esc_attr( $grid_class ); ?>">
					<?php
					foreach ( $posts as $post ) {
						$product = wc_get_product( $post->ID );
						if ( $product ) {
							mi_cliente_storefront_render_product_card( $product, $layout );
						}
					}
					?>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<?php
}

/**
 * Render banner promo.
 */
function mi_cliente_storefront_render_promo_banner() {
	$text = mi_cliente_storefront_mod( 'promo_banner_text' );
	if ( ! $text ) {
		return;
	}
	?>
	<div class="dark-promo-bar">
		<div class="dsam-container"><?php echo esc_html( $text ); ?></div>
	</div>
	<?php
}

/**
 * Render CTA WhatsApp ancho.
 */
function mi_cliente_storefront_render_whatsapp_cta() {
	$url = mi_cliente_storefront_resolve_whatsapp_url();
	if ( ! $url ) {
		return;
	}

	mi_cliente_storefront_template(
		'whatsapp-cta',
		array(
			'url'   => $url,
			'text'  => mi_cliente_storefront_mod( 'whatsapp_cta_text' ),
			'phone' => mi_cliente_storefront_mod( 'whatsapp_cta_phone' ),
		)
	);
}

/**
 * Render badges de confianza.
 */
function mi_cliente_storefront_render_trust_badges() {
	$badges = array(
		array( 'icon' => 'credit_card', 'title' => __( 'Pagos Online', 'mi-cliente-theme' ), 'text' => __( 'Seguridad garantizada en tus transacciones.', 'mi-cliente-theme' ) ),
		array( 'icon' => 'support_agent', 'title' => __( 'Soporte Inmediato', 'mi-cliente-theme' ), 'text' => __( 'Atención personalizada vía WhatsApp.', 'mi-cliente-theme' ) ),
		array( 'icon' => 'local_shipping', 'title' => __( 'Envíos a todo Perú', 'mi-cliente-theme' ), 'text' => __( 'Llegamos a cada rincón del país.', 'mi-cliente-theme' ) ),
		array( 'icon' => 'verified_user', 'title' => __( 'Satisfacción Asegurada', 'mi-cliente-theme' ), 'text' => __( 'Más de 7 años cumpliendo entregas.', 'mi-cliente-theme' ) ),
	);

	mi_cliente_storefront_template( 'trust-badges', array( 'badges' => $badges ) );
}

/**
 * Render footer tienda.
 */
function mi_cliente_storefront_render_footer() {
	mi_cliente_storefront_template(
		'footer',
		array(
			'year'     => gmdate( 'Y' ),
			'tagline'  => mi_cliente_storefront_mod( 'footer_tagline' ),
			'logo_url' => mi_cliente_storefront_logo_url(),
		)
	);
}

/**
 * Iconos sociales por defecto (markup idéntico a code.html).
 */
function mi_cliente_storefront_footer_social_fallback() {
	$links = array(
		array( 'icon' => 'face_nod', 'label' => 'Facebook' ),
		array( 'icon' => 'camera_alt', 'label' => 'Instagram' ),
		array( 'icon' => 'video_library', 'label' => 'YouTube' ),
	);
	foreach ( $links as $link ) {
		ob_start();
		mi_cliente_storefront_icon( $link['icon'], '', 20 );
		$icon_html = ob_get_clean();
		printf(
			'<a class="footer-social-link" href="#" aria-label="%1$s">%2$s</a>',
			esc_attr( $link['label'] ),
			$icon_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}
}

/**
 * URL de WhatsApp: enlace configurado o wa.me desde teléfono.
 */
function mi_cliente_storefront_resolve_whatsapp_url() {
	$orders_url = mi_cliente_storefront_mod( 'orders_online_url' );
	if ( $orders_url ) {
		return $orders_url;
	}

	$phone = (string) get_option( 'mi_cliente_whatsapp_phone', '' );
	if ( ! $phone ) {
		$phone = mi_cliente_storefront_mod( 'whatsapp_cta_phone' );
	}

	$digits = preg_replace( '/\D+/', '', $phone );
	if ( strlen( $digits ) >= 9 ) {
		return 'https://wa.me/' . $digits;
	}

	return '';
}

/**
 * Botón flotante WhatsApp (muestra code.html).
 */
function mi_cliente_storefront_render_floating_whatsapp() {
	if ( is_admin() ) {
		return;
	}

	$url = mi_cliente_storefront_resolve_whatsapp_url();
	if ( ! $url ) {
		return;
	}

	mi_cliente_storefront_template( 'floating-whatsapp', array( 'url' => $url ) );
}
add_action( 'wp_footer', 'mi_cliente_storefront_render_floating_whatsapp', 98 );

/**
 * Fallback menú categorías footer.
 */
function mi_cliente_storefront_footer_categories_fallback() {
	$links = array(
		array( __( 'Calzado Mujer', 'mi-cliente-theme' ), mi_cliente_storefront_category_url( 'mujer' ) ),
		array( __( 'Calzado Hombre', 'mi-cliente-theme' ), mi_cliente_storefront_category_url( 'hombre' ) ),
		array( __( 'Hogar y Cocina', 'mi-cliente-theme' ), mi_cliente_storefront_category_url( 'hogar' ) ),
		array( __( 'Ofertas 2x150', 'mi-cliente-theme' ), mi_cliente_storefront_anchor_url( 'calzado-mujer' ) ),
		array( __( 'Liquidación', 'mi-cliente-theme' ), mi_cliente_storefront_category_url( 'liquidacion' ) ),
	);
	echo '<ul class="footer-links">';
	foreach ( $links as $link ) {
		printf(
			'<li><a href="%s">%s</a></li>',
			esc_url( $link[1] ),
			esc_html( $link[0] )
		);
	}
	echo '</ul>';
}

/**
 * Fallback menú servicio footer.
 */
function mi_cliente_storefront_footer_service_fallback() {
	$links = array(
		array( __( 'Tiempos de Entrega', 'mi-cliente-theme' ), home_url( '/tiempos-de-entrega/' ) ),
		array( __( 'Políticas de Cambio', 'mi-cliente-theme' ), home_url( '/cambios-y-devoluciones/' ) ),
		array( __( 'Preguntas Frecuentes', 'mi-cliente-theme' ), home_url( '/preguntas-frecuentes/' ) ),
		array( __( 'Contacto', 'mi-cliente-theme' ), home_url( '/contacto/' ) ),
		array( __( 'Sobre Nosotros', 'mi-cliente-theme' ), home_url( '/sobre-nosotros/' ) ),
	);
	echo '<ul class="footer-links">';
	foreach ( $links as $link ) {
		printf(
			'<li><a href="%s">%s</a></li>',
			esc_url( $link[1] ),
			esc_html( $link[0] )
		);
	}
	echo '</ul>';
}

/**
 * Render página de inicio completa.
 */
function mi_cliente_storefront_render_home() {
	echo '<div class="dsam-home">';
	mi_cliente_storefront_render_hero();
	mi_cliente_storefront_render_shipping_bar();
	mi_cliente_storefront_render_quick_categories();

	$sections = mi_cliente_storefront_get_sections();
	$index    = 0;
	foreach ( $sections as $section ) {
		mi_cliente_storefront_render_product_section( $section );
		// Banner promo después de la primera sección (mujer).
		if ( 0 === $index ) {
			mi_cliente_storefront_render_promo_banner();
		}
		// CTA WhatsApp después de hogar (segunda sección, índice 1).
		if ( 1 === $index ) {
			mi_cliente_storefront_render_whatsapp_cta();
		}
		++$index;
	}

	mi_cliente_storefront_render_trust_badges();
	echo '</div>';
}

/* ——— Shortcodes ——— */

function mi_cliente_sc_storefront_chrome() {
	ob_start();
	mi_cliente_storefront_output_chrome();
	return ob_get_clean();
}
add_shortcode( 'mi_cliente_storefront_chrome', 'mi_cliente_sc_storefront_chrome' );

function mi_cliente_sc_announcement_bar() {
	ob_start();
	mi_cliente_storefront_render_announcement_bar();
	return ob_get_clean();
}
add_shortcode( 'mi_cliente_announcement_bar', 'mi_cliente_sc_announcement_bar' );

function mi_cliente_sc_storefront_header() {
	ob_start();
	mi_cliente_storefront_render_header();
	return ob_get_clean();
}
add_shortcode( 'mi_cliente_storefront_header', 'mi_cliente_sc_storefront_header' );

function mi_cliente_sc_storefront_footer() {
	ob_start();
	mi_cliente_storefront_render_footer();
	return ob_get_clean();
}
add_shortcode( 'mi_cliente_storefront_footer', 'mi_cliente_sc_storefront_footer' );

function mi_cliente_sc_home() {
	ob_start();
	mi_cliente_storefront_render_home();
	return ob_get_clean();
}
add_shortcode( 'mi_cliente_home', 'mi_cliente_sc_home' );

/**
 * Registrar ubicaciones de menú.
 */
function mi_cliente_storefront_register_menus() {
	register_nav_menus(
		array(
			'storefront-primary'           => __( 'Tienda — Menú principal', 'mi-cliente-theme' ),
			'storefront-footer-categories' => __( 'Tienda — Footer categorías', 'mi-cliente-theme' ),
			'storefront-footer-service'    => __( 'Tienda — Footer servicio', 'mi-cliente-theme' ),
			'storefront-social'            => __( 'Tienda — Redes sociales', 'mi-cliente-theme' ),
		)
	);
}
add_action( 'after_setup_theme', 'mi_cliente_storefront_register_menus', 20 );

/**
 * Soporte logo personalizado.
 */
function mi_cliente_storefront_custom_logo_setup() {
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 48,
			'width'       => 200,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);
}
add_action( 'after_setup_theme', 'mi_cliente_storefront_custom_logo_setup', 20 );

/**
 * Crea la página «Inicio» y la asigna como portada (solo si aún no hay portada estática).
 *
 * front-page.html es una plantilla del tema, no un ítem del listado de páginas.
 * En Ajustes → Lectura debes elegir la página «Inicio»; WordPress aplicará front-page.html.
 *
 * @since 1.1.0
 */
function mi_cliente_storefront_setup_home_page() {
	if ( 'page' === get_option( 'show_on_front' ) && get_option( 'page_on_front' ) ) {
		update_option( 'mi_cliente_storefront_home_ready', 1 );
		return;
	}

	$existing = get_page_by_path( 'inicio', OBJECT, 'page' );
	if ( $existing ) {
		$page_id = (int) $existing->ID;
	} else {
		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'Inicio', 'mi-cliente-theme' ),
				'post_name'    => 'inicio',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '',
			),
			true
		);
		if ( is_wp_error( $page_id ) ) {
			return;
		}
	}

	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', (int) $page_id );
	update_option( 'mi_cliente_storefront_home_ready', 1 );
}
add_action( 'after_switch_theme', 'mi_cliente_storefront_setup_home_page' );

/**
 * Aviso en admin si la portada aún no está configurada.
 *
 * @since 1.1.0
 */
function mi_cliente_storefront_home_page_admin_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( 'page' === get_option( 'show_on_front' ) && get_option( 'page_on_front' ) ) {
		return;
	}
	$setup_url = wp_nonce_url(
		admin_url( 'themes.php?mi_cliente_setup_home=1' ),
		'mi_cliente_setup_home'
	);
	?>
	<div class="notice notice-info">
		<p>
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: %s: URL to run one-click homepage setup */
					__( '<strong>Mi Cliente Theme:</strong> La plantilla <code>front-page.html</code> no es una página del listado. Crea la portada con un clic o en Ajustes → Lectura elige la página «Inicio». <a href="%s">Configurar portada ahora</a>', 'mi-cliente-theme' ),
					esc_url( $setup_url )
				)
			);
			?>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'mi_cliente_storefront_home_page_admin_notice' );

/**
 * Ejecuta configuración de portada desde el enlace del aviso.
 *
 * @since 1.1.0
 */
function mi_cliente_storefront_handle_setup_home_request() {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( empty( $_GET['mi_cliente_setup_home'] ) ) {
		return;
	}
	check_admin_referer( 'mi_cliente_setup_home' );
	mi_cliente_storefront_setup_home_page();
	wp_safe_redirect( admin_url( 'options-reading.php?mi_cliente_home_created=1' ) );
	exit;
}
add_action( 'admin_init', 'mi_cliente_storefront_handle_setup_home_request' );

/**
 * Enqueue assets storefront.
 */
function mi_cliente_storefront_enqueue_assets() {
	$theme_version = wp_get_theme()->get( 'Version' );
	$css_file      = get_template_directory() . '/assets/css/dsam-storefront.css';
	$js_file       = get_template_directory() . '/assets/js/storefront.js';
	$css_version   = file_exists( $css_file ) ? (string) filemtime( $css_file ) : $theme_version;
	$js_version    = file_exists( $js_file ) ? (string) filemtime( $js_file ) : $theme_version;

	// Tipografía de texto (iconos vía SVG en inc/storefront-icons.php).
	wp_enqueue_style(
		'mi-cliente-fonts',
		'https://fonts.googleapis.com/css2?family=IBM+Plex+Serif:wght@600;700&family=Open+Sans:wght@400;600;700&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'mi-cliente-dsam-storefront',
		get_template_directory_uri() . '/assets/css/dsam-storefront.css',
		array( 'mi-cliente-fonts' ),
		$css_version
	);

	$hero_js_file    = get_template_directory() . '/assets/js/hero-slider.js';
	$hero_js_version = file_exists( $hero_js_file ) ? (string) filemtime( $hero_js_file ) : $theme_version;

	wp_enqueue_script(
		'mi-cliente-storefront',
		get_template_directory_uri() . '/assets/js/storefront.js',
		array(),
		$js_version,
		true
	);

	wp_enqueue_script(
		'mi-cliente-hero-slider',
		get_template_directory_uri() . '/assets/js/hero-slider.js',
		array(),
		$hero_js_version,
		true
	);

	$nav_sections = array();
	foreach ( mi_cliente_storefront_get_nav_items() as $nav_item ) {
		$nav_sections[] = $nav_item['section_id'];
	}

	wp_localize_script(
		'mi-cliente-storefront',
		'miClienteStorefront',
		array(
			'homeUrl'      => trailingslashit( home_url( '/' ) ),
			'isFrontPage'  => is_front_page(),
			'headerOffset' => 120,
			'navSections'  => $nav_sections,
		)
	);
}
add_action( 'wp_enqueue_scripts', 'mi_cliente_storefront_enqueue_assets' );

/**
 * Clases de body para estilos de portada y cabecera fija.
 *
 * @param string[] $classes Clases actuales.
 * @return string[]
 */
function mi_cliente_storefront_body_class( $classes ) {
	$classes[] = 'dsam-storefront';
	$classes[] = 'dsam-chrome-at-top';
	if ( is_front_page() ) {
		$classes[] = 'dsam-front-page';
	}
	return $classes;
}
add_filter( 'body_class', 'mi_cliente_storefront_body_class' );

/**
 * Redirige /shop/?product_cat=slug a la ancla en la portada (evita 404).
 */
function mi_cliente_storefront_redirect_legacy_category_links() {
	if ( is_admin() || ! mi_cliente_theme_is_woocommerce_active() ) {
		return;
	}

	$cat_slug = '';
	if ( is_product_category() ) {
		$term = get_queried_object();
		if ( $term && isset( $term->slug ) ) {
			$cat_slug = $term->slug;
		}
	} elseif ( is_shop() && ! empty( $_GET['product_cat'] ) ) {
		$cat_slug = sanitize_title( wp_unslash( $_GET['product_cat'] ) );
	}

	if ( ! $cat_slug ) {
		return;
	}

	$map = mi_cliente_storefront_section_id_by_category();
	if ( isset( $map[ $cat_slug ] ) ) {
		wp_safe_redirect( trailingslashit( home_url( '/' ) ) . '#' . $map[ $cat_slug ] );
		exit;
	}
}
add_action( 'template_redirect', 'mi_cliente_storefront_redirect_legacy_category_links', 5 );

/**
 * Fragmento de carrito para actualizar contador en cabecera.
 *
 * @param array $fragments Fragmentos WC.
 * @return array
 */
function mi_cliente_storefront_cart_fragments( $fragments ) {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return $fragments;
	}
	ob_start();
	?>
	<div class="cart-info dsam-header__cart-meta">
		<span class="cart-label dsam-header__cart-label"><?php esc_html_e( 'Carrito', 'mi-cliente-theme' ); ?></span>
		<span class="cart-total dsam-header__cart-total"><?php echo wp_kses_post( WC()->cart->get_cart_total() ); ?></span>
	</div>
	<?php
	$fragments['.dsam-header__cart-meta'] = ob_get_clean();

	ob_start();
	?>
	<div class="cart-icon-box dsam-header__cart-icon-wrap">
		<?php mi_cliente_storefront_icon( 'shopping_cart', 'cart-icon', 32 ); ?>
		<span class="cart-count dsam-header__cart-count" aria-live="polite"><?php echo esc_html( (string) WC()->cart->get_cart_contents_count() ); ?></span>
	</div>
	<?php
	$fragments['.dsam-header__cart-icon-wrap'] = ob_get_clean();

	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'mi_cliente_storefront_cart_fragments' );
