<?php
/**
 * Control del Customizer: categorías rápidas de la portada.
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
 * UI amigable para mi_cliente_quick_categories (JSON en theme_mod).
 */
class Mi_Cliente_Quick_Categories_Control extends WP_Customize_Control {

	/**
	 * @var string
	 */
	public $type = 'mi_cliente_quick_categories';

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
			$value = wp_json_encode( mi_cliente_storefront_default_quick_categories() );
		}
		?>
		<input
			type="hidden"
			class="mi-cliente-quick-categories-json"
			<?php $this->link(); ?>
			value="<?php echo esc_attr( $value ); ?>"
		/>
		<div class="mi-cliente-quick-categories-repeater"></div>
		<p class="mi-cliente-cz-repeater-actions">
			<button type="button" class="button button-primary mi-cliente-quick-categories-add">
				<?php esc_html_e( '+ Añadir categoría', 'mi-cliente-theme' ); ?>
			</button>
		</p>
		<?php
	}
}
