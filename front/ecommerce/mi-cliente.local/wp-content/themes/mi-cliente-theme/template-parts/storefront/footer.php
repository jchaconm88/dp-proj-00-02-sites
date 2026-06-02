<?php
/**
 * Pie de tienda — code.html (.footer).
 *
 * @package Mi_Cliente_Theme
 * @var string $year
 * @var string $tagline
 * @var string $logo_url
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dsam_logo_url = isset( $logo_url ) ? (string) $logo_url : '';
?>
<footer class="footer">
	<div class="dsam-container">
		<div class="footer-grid">
			<div class="footer-col footer-about">
				<?php if ( $dsam_logo_url ) : ?>
					<img alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" src="<?php echo esc_url( $dsam_logo_url ); ?>" style="height: 40px; margin-bottom: 20px; filter: brightness(0) invert(1);" />
				<?php else : ?>
					<span style="display:block;font-size:20px;font-weight:700;margin-bottom:20px;"><?php bloginfo( 'name' ); ?></span>
				<?php endif; ?>
				<p><?php echo esc_html( $tagline ); ?></p>
				<div style="display: flex; gap: 15px;">
					<?php
					if ( has_nav_menu( 'storefront-social' ) ) {
						wp_nav_menu(
							array(
								'theme_location' => 'storefront-social',
								'container'      => false,
								'menu_class'     => 'footer-social',
								'fallback_cb'    => false,
								'depth'          => 1,
							)
						);
					} else {
						mi_cliente_storefront_footer_social_fallback();
					}
					?>
				</div>
			</div>
			<div class="footer-col">
				<h4><?php esc_html_e( 'Categorías', 'mi-cliente-theme' ); ?></h4>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'storefront-footer-categories',
						'container'      => false,
						'menu_class'     => 'footer-links',
						'fallback_cb'    => 'mi_cliente_storefront_footer_categories_fallback',
						'depth'          => 1,
					)
				);
				?>
			</div>
			<div class="footer-col">
				<h4><?php esc_html_e( 'Servicio', 'mi-cliente-theme' ); ?></h4>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'storefront-footer-service',
						'container'      => false,
						'menu_class'     => 'footer-links',
						'fallback_cb'    => 'mi_cliente_storefront_footer_service_fallback',
						'depth'          => 1,
					)
				);
				?>
			</div>
			<div class="footer-col">
				<h4><?php esc_html_e( 'Tienda Segura', 'mi-cliente-theme' ); ?></h4>
				<div class="footer-trust-box">
					<div style="display: flex; align-items: center; gap: 10px;">
						<?php mi_cliente_storefront_icon( 'verified', '', 22 ); ?>
						<span class="label"><?php esc_html_e( 'PAGA AL RECIBIR', 'mi-cliente-theme' ); ?></span>
					</div>
				</div>
				<div class="footer-trust-box">
					<div style="display: flex; align-items: center; gap: 10px;">
						<?php mi_cliente_storefront_icon( 'lock', '', 22 ); ?>
						<span class="label"><?php esc_html_e( 'SSL ENCRYPTED', 'mi-cliente-theme' ); ?></span>
					</div>
				</div>
			</div>
		</div>
		<div class="footer-bottom">
			© <?php echo esc_html( $year ); ?> <?php bloginfo( 'name' ); ?>. <?php esc_html_e( 'TODOS LOS DERECHOS RESERVADOS.', 'mi-cliente-theme' ); ?>
		</div>
	</div>
</footer>
