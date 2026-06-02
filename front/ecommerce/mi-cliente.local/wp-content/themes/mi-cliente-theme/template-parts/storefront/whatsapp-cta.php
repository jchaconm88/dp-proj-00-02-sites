<?php
/**
 * CTA WhatsApp — code.html (.whatsapp-bar).
 *
 * @package Mi_Cliente_Theme
 * @var string $url
 * @var string $text
 * @var string $phone
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="whatsapp-bar" id="entregas" data-dsam-section="entregas">
	<div class="dsam-container">
		<div class="whatsapp-content">
			<h2 class="whatsapp-title"><?php echo esc_html( $text ); ?></h2>
			<a class="whatsapp-number" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php mi_cliente_storefront_icon( 'call', '', 22 ); ?>
				<?php echo esc_html( $phone ); ?>
			</a>
		</div>
	</div>
</section>
