<?php
/**
 * Hero — code.html (sección .hero con imagen de fondo).
 *
 * @package Mi_Cliente_Theme
 * @var string $image_url
 * @var string $primary_url
 * @var string $secondary_url
 * @var string $badge
 * @var string $title
 * @var string $text
 * @var string $primary_label
 * @var string $secondary_label
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dsam_hero_bg = $image_url ? $image_url : 'https://lh3.googleusercontent.com/aida/ADBb0uhAODJWTcLAP-sDPxhpIReM3WXHeo3v6f4NXTjqB5VrZY8g_hk7jC4vnnBaYuLsK5HSG5oCJXO5nQEizcUFDJO2uQ-l8FAhzHvkItgMNUC-STx3hwNCHIwRzHc0maizuSX_oND-nGDrF_h9NCOAuf42ZWoVcRdCNCw9bEthpvd1tlH04-LUC4f_9jwrZS-2EPSn-Gu-xELrVyMNpLrGN9NOZymeDk3bWp8KxFd8wzNc9V960WDCgiag0CY';
$dsam_hero_style = 'background-image: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url(' . esc_url( $dsam_hero_bg ) . ');';
?>
<section class="hero" style="<?php echo esc_attr( $dsam_hero_style ); ?>">
	<div class="dsam-container">
		<div class="hero-content">
			<?php if ( $badge ) : ?>
				<span class="hero-badge"><?php echo esc_html( strtoupper( $badge ) ); ?></span>
			<?php endif; ?>
			<?php if ( $title ) : ?>
				<h1 class="hero-title"><?php echo wp_kses( nl2br( esc_html( $title ) ), array( 'br' => array() ) ); ?></h1>
			<?php endif; ?>
			<?php if ( $text ) : ?>
				<p class="hero-desc"><?php echo esc_html( $text ); ?></p>
			<?php endif; ?>
			<div class="hero-btns">
				<a class="btn-primary" href="<?php echo esc_url( $primary_url ); ?>">
					<span class="btn-primary__text"><?php echo esc_html( $primary_label ); ?></span>
					<?php mi_cliente_storefront_icon( 'trending_flat', 'btn-primary__icon', 20 ); ?>
				</a>
				<?php if ( $secondary_url ) : ?>
					<a class="btn-secondary" href="<?php echo esc_url( $secondary_url ); ?>"><?php echo esc_html( $secondary_label ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
