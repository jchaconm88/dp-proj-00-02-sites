<?php
/**
 * Control del Customizer: secciones de productos en la portada.
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
 * UI amigable para mi_cliente_home_sections (JSON en theme_mod).
 */
class Mi_Cliente_Home_Sections_Control extends WP_Customize_Control {

	/**
	 * @var string
	 */
	public $type = 'mi_cliente_home_sections';

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
			$value = wp_json_encode( mi_cliente_storefront_default_sections() );
		}
		?>
		<input
			type="hidden"
			class="mi-cliente-home-sections-json"
			<?php $this->link(); ?>
			value="<?php echo esc_attr( $value ); ?>"
		/>
		<div class="mi-cliente-home-sections-repeater"></div>
		<p class="mi-cliente-cz-repeater-actions">
			<button type="button" class="button button-primary mi-cliente-home-sections-add">
				<?php esc_html_e( '+ Añadir sección', 'mi-cliente-theme' ); ?>
			</button>
		</p>
		<?php
	}
}
