<?php
/**
 * Harness de previsualización: stubea WordPress/WooCommerce lo justo para
 * renderizar el markup de la portada (header + home + footer) y volcarlo a
 * preview.html, enlazando el tailwind.css compilado. Sirve para validar la
 * estructura HTML sin levantar WordPress.
 *
 * Uso:  php render-preview.php
 * Salida: preview.html  (abrir en el navegador)
 */

error_reporting( E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING );

define( 'ABSPATH', __DIR__ . '/' );
$THEME = realpath( __DIR__ . '/../../front/ecommerce/mi-cliente.local/wp-content/themes/mi-cliente-theme' );

/* ───────── Stubs mínimos de WordPress ───────── */
function __( $t, $d = null ) { return $t; }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_html__( $t, $d = null ) { return esc_html( $t ); }
function esc_html_e( $t, $d = null ) { echo esc_html( $t ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_attr__( $t, $d = null ) { return esc_attr( $t ); }
function esc_attr_e( $t, $d = null ) { echo esc_attr( $t ); }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES ); }
function esc_url_raw( $u ) { return $u; }
function esc_textarea( $t ) { return esc_html( $t ); }
function wp_kses_post( $t ) { return $t; }
function wp_kses( $t, $a ) { return $t; }
function sanitize_title( $t ) { return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', trim( (string) $t ) ) ); }
function sanitize_html_class( $t ) { return preg_replace( '/[^a-z0-9_-]/i', '', (string) $t ); }
function sanitize_text_field( $t ) { return trim( (string) $t ); }
function home_url( $p = '/' ) { return 'https://mi-cliente.local' . $p; }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
function get_bloginfo( $k = '' ) { return 'D.Sam'; }
function bloginfo( $k = '' ) { echo get_bloginfo( $k ); }
function get_theme_mod( $k, $d = '' ) { return $d; }
function get_option( $k, $d = '' ) { return $d; }
function is_front_page() { return true; }
function current_user_can( $c ) { return false; }
function has_custom_logo() { return false; }
function the_custom_logo() {}
function get_search_query() { return ''; }
function wp_get_attachment_image_url( $id, $size = 'full' ) { return ''; }
function get_term_by( $f, $v, $t ) { return false; }
function get_term_meta( $id, $k, $s = false ) { return ''; }
function str_starts_with_poly( $h, $n ) { return 0 === strpos( $h, $n ); }
function mi_cliente_theme_is_woocommerce_active() { return false; }
function add_action() {}
function add_filter() {}
function add_shortcode() {}
function register_nav_menus() {}
function add_theme_support() {}
function has_nav_menu( $l ) { return false; }
function wp_nav_menu( $args ) { if ( ! empty( $args['fallback_cb'] ) && function_exists( $args['fallback_cb'] ) ) { call_user_func( $args['fallback_cb'] ); } }
function wc_get_page_permalink( $p ) { return home_url( '/tienda/' ); }
function wc_get_cart_url() { return home_url( '/carrito/' ); }
function get_template_directory() { global $THEME; return $THEME; }
function get_template_directory_uri() { return '../../front/ecommerce/mi-cliente.local/wp-content/themes/mi-cliente-theme'; }

require $THEME . '/inc/storefront.php';

/* Render del markup completo: chrome (anuncio+header) + home + footer. */
ob_start();
mi_cliente_storefront_render_chrome();
mi_cliente_storefront_render_home();
mi_cliente_storefront_render_footer();
$body = ob_get_clean();

$css_href = '../../front/ecommerce/mi-cliente.local/wp-content/themes/mi-cliente-theme/assets/css/tailwind.css';
$fonts = 'https://fonts.googleapis.com/css2?family=IBM+Plex+Serif:wght@600;700&family=Open+Sans:wght@400;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap';

$html = '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
	. '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
	. '<title>Preview — mi-cliente.local</title>'
	. '<link rel="stylesheet" href="' . $fonts . '">'
	. '<link rel="stylesheet" href="' . $css_href . '">'
	. '</head><body class="dsam-storefront dsam-chrome-at-top">'
	. '<div class="wp-site-blocks">' . $body . '</div>'
	. '</body></html>';

file_put_contents( __DIR__ . '/preview.html', $html );
echo "preview.html generado (" . strlen( $html ) . " bytes)\n";
