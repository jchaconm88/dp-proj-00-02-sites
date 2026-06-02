<?php
/**
 * Barra de envíos — code.html (.trust-bar).
 *
 * @package Mi_Cliente_Theme
 * @var string $text
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="trust-bar">
	<div class="dsam-container">
		<?php mi_cliente_storefront_icon( 'local_shipping', 'trust-bar__icon', 18 ); ?>
		<?php echo esc_html( strtoupper( $text ) ); ?>
	</div>
</div>
