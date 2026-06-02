<?php
/**
 * Tarjeta de producto — code.html (.product-card).
 *
 * @package Mi_Cliente_Theme
 * @var WC_Product $product
 * @var string     $layout featured|hogar|hombre|clearance
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! $product instanceof WC_Product ) {
	return;
}

$permalink  = get_permalink( $product->get_id() );
$on_sale    = $product->is_on_sale();
$is_variable = $product->is_type( 'variable' );

if ( 'clearance' === $layout ) {
	$cta_label = __( 'Ver Oferta', 'mi-cliente-theme' );
	$btn_class = 'btn-action btn-action--red';
	$name_class = 'product-name';
	$price_class = 'product-price product-price--sale';
	$badge_text = '-50%';
} elseif ( 'hogar' === $layout ) {
	$cta_label = __( 'Añadir', 'mi-cliente-theme' );
	$btn_class = 'btn-action btn-action--outline';
	$name_class = 'product-name product-name--sm';
	$price_class = 'product-price product-price--sm';
	$badge_text = '';
} elseif ( 'hombre' === $layout ) {
	$cta_label = $is_variable ? __( 'Ver Opciones', 'mi-cliente-theme' ) : __( 'Añadir', 'mi-cliente-theme' );
	$btn_class = 'btn-action';
	$name_class = 'product-name product-name--sm';
	$price_class = 'product-price';
	$badge_text = '';
} else {
	$cta_label = $is_variable ? __( 'Seleccionar Opciones', 'mi-cliente-theme' ) : __( 'Añadir', 'mi-cliente-theme' );
	$btn_class = 'btn-action';
	$name_class = 'product-name';
	$price_class = 'product-price';
	$badge_text = $on_sale ? __( 'OFERTA', 'mi-cliente-theme' ) : '';
}
?>
<div class="product-card">
	<?php if ( $badge_text ) : ?>
		<div class="product-badge"><?php echo esc_html( $badge_text ); ?></div>
	<?php endif; ?>
	<a href="<?php echo esc_url( $permalink ); ?>" tabindex="-1" aria-hidden="true">
		<?php echo $product->get_image( 'woocommerce_thumbnail', array( 'class' => 'product-img' ) ); ?>
	</a>
	<div class="product-info">
		<h3 class="<?php echo esc_attr( $name_class ); ?>"><?php echo esc_html( $product->get_name() ); ?></h3>
		<div class="<?php echo esc_attr( $price_class ); ?> dsam-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>
		<a class="<?php echo esc_attr( $btn_class ); ?>" href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $cta_label ); ?></a>
	</div>
</div>
