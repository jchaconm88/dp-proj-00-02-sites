<?php
/**
 * Hero slider — diapositivas desde Customizer (mi_cliente_hero_slides JSON).
 *
 * @package Mi_Cliente_Theme
 * @var array<int, array<string, string>> $slides Diapositivas normalizadas.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $slides ) ) {
	return;
}

$slide_count = count( $slides );
?>
<section
	class="hero-slider"
	aria-roledescription="carousel"
	aria-label="<?php esc_attr_e( 'Promociones destacadas', 'mi-cliente-theme' ); ?>"
	data-autoplay="6000"
>
	<div class="hero-slider__viewport">
		<div class="hero-slider__track" role="list">
			<?php
			foreach ( $slides as $index => $slide ) :
				$bg_style = $slide['image_url']
					? 'background-image: linear-gradient(rgba(0,0,0,0.45), rgba(0,0,0,0.45)), url(' . esc_url( $slide['image_url'] ) . ');'
					: 'background-color: #5a5a5a;';
				$is_active   = 0 === (int) $index;
				$primary_url = $slide['primary_url'] ?: '#';
				?>
				<article
					class="hero-slide<?php echo $is_active ? ' is-active' : ''; ?>"
					role="listitem"
					aria-roledescription="slide"
					aria-label="<?php echo esc_attr( sprintf( __( 'Diapositiva %1$d de %2$d', 'mi-cliente-theme' ), $index + 1, $slide_count ) ); ?>"
					<?php echo $is_active ? '' : ' aria-hidden="true"'; ?>
					style="<?php echo esc_attr( $bg_style ); ?>"
					data-hero-slide
				>
					<div class="dsam-container hero-slide__inner">
						<div class="hero-content">
							<?php if ( ! empty( $slide['badge'] ) ) : ?>
								<span class="hero-badge"><?php echo esc_html( strtoupper( $slide['badge'] ) ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $slide['title'] ) ) : ?>
								<h2 class="hero-title"><?php echo wp_kses( nl2br( esc_html( $slide['title'] ) ), array( 'br' => array() ) ); ?></h2>
							<?php endif; ?>
							<?php if ( ! empty( $slide['text'] ) ) : ?>
								<p class="hero-desc"><?php echo esc_html( $slide['text'] ); ?></p>
							<?php endif; ?>
							<div class="hero-btns">
								<?php if ( ! empty( $slide['primary_label'] ) ) : ?>
									<a class="btn-primary" href="<?php echo esc_url( $primary_url ); ?>">
										<span class="btn-primary__text"><?php echo esc_html( $slide['primary_label'] ); ?></span>
										<?php mi_cliente_storefront_icon( 'trending_flat', 'btn-primary__icon', 20 ); ?>
									</a>
								<?php endif; ?>
								<?php if ( ! empty( $slide['secondary_url'] ) && ! empty( $slide['secondary_label'] ) ) : ?>
									<a class="btn-secondary" href="<?php echo esc_url( $slide['secondary_url'] ); ?>">
										<?php echo esc_html( $slide['secondary_label'] ); ?>
									</a>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</article>
				<?php
			endforeach;
			?>
		</div>
	</div>

	<?php if ( $slide_count > 1 ) : ?>
		<button type="button" class="hero-slider__arrow hero-slider__arrow--prev" aria-label="<?php esc_attr_e( 'Diapositiva anterior', 'mi-cliente-theme' ); ?>" data-hero-prev>
			<?php mi_cliente_storefront_icon( 'chevron_left', '', 28 ); ?>
		</button>
		<button type="button" class="hero-slider__arrow hero-slider__arrow--next" aria-label="<?php esc_attr_e( 'Diapositiva siguiente', 'mi-cliente-theme' ); ?>" data-hero-next>
			<?php mi_cliente_storefront_icon( 'chevron_right', '', 28 ); ?>
		</button>
		<div class="hero-slider__dots" role="tablist" aria-label="<?php esc_attr_e( 'Elegir diapositiva', 'mi-cliente-theme' ); ?>">
			<?php for ( $i = 0; $i < $slide_count; $i++ ) : ?>
				<button
					type="button"
					class="hero-slider__dot<?php echo 0 === $i ? ' is-active' : ''; ?>"
					role="tab"
					aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>"
					aria-label="<?php echo esc_attr( sprintf( __( 'Ir a diapositiva %d', 'mi-cliente-theme' ), $i + 1 ) ); ?>"
					data-hero-dot="<?php echo esc_attr( (string) $i ); ?>"
				></button>
			<?php endfor; ?>
		</div>
	<?php endif; ?>
</section>
