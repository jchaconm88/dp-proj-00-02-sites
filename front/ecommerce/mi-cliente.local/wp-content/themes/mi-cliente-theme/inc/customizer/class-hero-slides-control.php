<?php
/**
 * Control del Customizer: repetidor de diapositivas del hero.
 *
 * @package Mi_Cliente_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_Customize_Control' ) ) {
	return;
}

/**
 * UI amigable para mi_cliente_hero_slides (JSON en theme_mod).
 */
class Mi_Cliente_Hero_Slides_Control extends WP_Customize_Control {

	/**
	 * Tipo de control.
	 *
	 * @var string
	 */
	public $type = 'mi_cliente_hero_slides';

	/**
	 * Render del control.
	 */
	protected function render_content() {
		if ( ! empty( $this->label ) ) {
			echo '<span class="customize-control-title">' . esc_html( $this->label ) . '</span>';
		}
		if ( ! empty( $this->description ) ) {
			echo '<span class="description customize-control-description">' . esc_html( $this->description ) . '</span>';
		}

		$value = $this->value();
		if ( ! is_string( $value ) || '' === $value ) {
			$value = wp_json_encode( mi_cliente_storefront_default_hero_slides() );
		}
		?>
		<input
			type="hidden"
			class="mi-cliente-hero-slides-json"
			<?php $this->link(); ?>
			value="<?php echo esc_attr( $value ); ?>"
		/>
		<div class="mi-cliente-hero-slides-repeater" data-repeater></div>
		<p class="mi-cliente-hero-slides-actions">
			<button type="button" class="button button-primary mi-cliente-hero-slides-add">
				<?php esc_html_e( '+ Añadir diapositiva', 'mi-cliente-theme' ); ?>
			</button>
		</p>
		<?php
	}
}
