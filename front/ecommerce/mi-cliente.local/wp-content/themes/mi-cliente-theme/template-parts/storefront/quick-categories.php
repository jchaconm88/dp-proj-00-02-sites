<?php
/**
 * Categorías rápidas — code.html (.categories-grid, círculos 140px).
 *
 * @package Mi_Cliente_Theme
 * @var array<int, array<string, mixed>> $items
 * @var string                          $title
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="section-padding" id="tienda" data-dsam-section="tienda">
	<div class="dsam-container">
		<div class="section-title-wrap">
			<h2 class="section-title"><?php echo esc_html( $title ); ?></h2>
			<div class="section-divider"></div>
		</div>
		<div class="categories-grid">
			<?php
			foreach ( $items as $item ) :
				$label      = isset( $item['label'] ) ? (string) $item['label'] : '';
				$variant    = isset( $item['variant'] ) ? (string) $item['variant'] : 'image';
				$section_id = mi_cliente_storefront_quick_cat_section_id( $item );

				if ( $section_id ) {
					$url = mi_cliente_storefront_anchor_url( $section_id );
				} elseif ( ! empty( $item['url'] ) ) {
					$url = (string) $item['url'];
					if ( str_starts_with( $url, '/' ) ) {
						$url = home_url( $url );
					}
				} else {
					$url = mi_cliente_storefront_anchor_url( 'tienda' );
				}

				$image_url = isset( $item['image_url'] ) ? (string) $item['image_url'] : '';
				$image_id  = isset( $item['image'] ) ? absint( $item['image'] ) : 0;
				if ( ! $image_id && ! empty( $item['category'] ) ) {
					$term = get_term_by( 'slug', $item['category'], 'product_cat' );
					if ( $term ) {
						$thumb_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
						if ( $thumb_id ) {
							$image_id = (int) $thumb_id;
						}
					}
				}
				if ( ! $image_url && $image_id ) {
					$image_url = (string) wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' );
				}

				$section_attr = $section_id ? ' data-dsam-section="' . esc_attr( $section_id ) . '"' : '';
				?>
				<a class="category-item dsam-scroll-link" href="<?php echo esc_url( $url ); ?>"<?php echo $section_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php if ( 'promo' === $variant ) : ?>
						<div class="category-circle blue">
							<span style="font-size: 14px; font-weight: 700;"><?php echo esc_html( strtoupper( $item['promo_line1'] ?? '2x150 Y 3x210' ) ); ?></span>
							<?php if ( ! empty( $item['promo_line2'] ) ) : ?>
								<span style="font-size: 9px; color: var(--primary-green);"><?php echo esc_html( strtoupper( $item['promo_line2'] ) ); ?></span>
							<?php endif; ?>
						</div>
					<?php elseif ( 'clearance' === $variant ) : ?>
						<div class="category-circle red">
							<span><?php esc_html_e( 'LIQUIDACIÓN', 'mi-cliente-theme' ); ?></span>
						</div>
					<?php elseif ( 'icon' === $variant ) : ?>
						<div class="category-circle category-circle--icon">
							<?php mi_cliente_storefront_icon( $item['icon'] ?? 'chat', 'category-circle__icon', 36 ); ?>
							<span class="category-circle__caption"><?php echo esc_html( str_replace( "\n", ' ', $item['icon_caption'] ?? 'COMENTARIOS ENTREGAS' ) ); ?></span>
						</div>
					<?php elseif ( $image_url ) : ?>
						<div class="category-circle">
							<img alt="<?php echo esc_attr( $label ); ?>" src="<?php echo esc_url( $image_url ); ?>" loading="lazy" decoding="async" />
						</div>
					<?php else : ?>
						<div class="category-circle">
							<?php mi_cliente_storefront_icon( $item['icon'] ?? 'category', 'category-circle__icon', 36 ); ?>
						</div>
					<?php endif; ?>
					<span class="category-name"><?php echo esc_html( $label ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
