<?php
/**
 * Badges de confianza — code.html (.features-section).
 *
 * @package Mi_Cliente_Theme
 * @var array<int, array{icon: string, title: string, text: string}> $badges
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="features-section">
	<div class="dsam-container">
		<div class="features-grid">
			<?php foreach ( $badges as $badge ) : ?>
				<div class="feature-item">
					<div class="feature-item__icon" aria-hidden="true">
						<?php mi_cliente_storefront_icon( $badge['icon'], 'feature-icon', 32 ); ?>
					</div>
					<div class="feature-text">
						<p class="feature-title"><?php echo esc_html( $badge['title'] ); ?></p>
						<p class="feature-desc"><?php echo esc_html( $badge['text'] ); ?></p>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
