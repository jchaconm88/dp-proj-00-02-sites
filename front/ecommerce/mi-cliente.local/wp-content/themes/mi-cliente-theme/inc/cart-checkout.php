<?php
/**
 * Cart and Checkout - Validation, Cart UI, Checkout Form, Order Confirmation.
 *
 * Implements add-to-cart validation (size + color + stock), visual confirmation,
 * cart page with quantity controls, AJAX recalculation, checkout form fields,
 * order confirmation display, and payment failure handling.
 *
 * @package Mi_Cliente_Theme
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Validate add-to-cart for variable products.
 *
 * Requires valid size + color selection with stock > 0 before adding to cart.
 *
 * @since 1.0.0
 *
 * @param bool $passed     Whether validation passed.
 * @param int  $product_id The product ID.
 * @param int  $quantity   The quantity being added.
 * @param int  $variation_id Optional variation ID.
 * @param array $variations Optional variation attributes.
 * @return bool Whether validation passed.
 */
function mi_cliente_theme_validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0, $variations = array() ) {
    $product = wc_get_product( $product_id );

    if ( ! $product || ! $product->is_type( 'variable' ) ) {
        return $passed;
    }

    // Require color selection.
    if ( empty( $variations['attribute_pa_color'] ) ) {
        wc_add_notice(
            __( 'Por favor selecciona un color antes de agregar al carrito.', 'mi-cliente-theme' ),
            'error'
        );
        return false;
    }

    // Require size selection.
    if ( empty( $variations['attribute_pa_talla'] ) ) {
        wc_add_notice(
            __( 'Por favor selecciona una talla antes de agregar al carrito.', 'mi-cliente-theme' ),
            'error'
        );
        return false;
    }

    // Verify stock for the selected variation.
    if ( $variation_id ) {
        $variation = wc_get_product( $variation_id );
        if ( $variation && ! $variation->is_in_stock() ) {
            wc_add_notice(
                __( 'La combinación seleccionada no está disponible en stock.', 'mi-cliente-theme' ),
                'error'
            );
            return false;
        }

        if ( $variation && $variation->managing_stock() ) {
            $stock_qty = $variation->get_stock_quantity();
            if ( $quantity > $stock_qty ) {
                wc_add_notice(
                    sprintf(
                        /* translators: %d: available stock quantity */
                        __( 'Solo hay %d unidades disponibles para esta combinación.', 'mi-cliente-theme' ),
                        $stock_qty
                    ),
                    'error'
                );
                return false;
            }
        }
    }

    return $passed;
}
add_filter( 'woocommerce_add_to_cart_validation', 'mi_cliente_theme_validate_add_to_cart', 10, 5 );


/**
 * Enqueue cart and checkout scripts.
 *
 * Loads JS for mini-cart notification, AJAX quantity updates,
 * and checkout form handling.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_cart_checkout_assets() {
    if ( ! mi_cliente_theme_is_woocommerce_active() ) {
        return;
    }

    $theme_version = wp_get_theme()->get( 'Version' );

    wp_enqueue_script(
        'mi-cliente-cart-checkout',
        get_template_directory_uri() . '/assets/js/cart-checkout.js',
        array( 'jquery' ),
        $theme_version,
        true
    );

    wp_localize_script( 'mi-cliente-cart-checkout', 'miClienteCart', array(
        'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
        'nonce'     => wp_create_nonce( 'mi_cliente_cart_nonce' ),
        'cartUrl'   => wc_get_cart_url(),
        'i18n'      => array(
            'addedToCart'  => __( 'Producto agregado al carrito', 'mi-cliente-theme' ),
            'viewCart'     => __( 'Ver carrito', 'mi-cliente-theme' ),
            'continueShopping' => __( 'Seguir comprando', 'mi-cliente-theme' ),
            'updating'     => __( 'Actualizando...', 'mi-cliente-theme' ),
            'minQuantity'  => __( 'La cantidad mínima es 1.', 'mi-cliente-theme' ),
            'maxStock'     => __( 'Stock máximo alcanzado.', 'mi-cliente-theme' ),
        ),
    ) );
}
add_action( 'wp_enqueue_scripts', 'mi_cliente_theme_cart_checkout_assets' );


/**
 * Add-to-cart AJAX response with mini-cart notification data.
 *
 * Sends product details (image, name, color, size, price) in the AJAX response
 * for visual confirmation display.
 *
 * @since 1.0.0
 *
 * @param array $fragments Cart fragments for AJAX update.
 * @return array Modified fragments with mini-cart notification.
 */
function mi_cliente_theme_add_to_cart_fragments( $fragments ) {
    $cart = WC()->cart;

    // Mini-cart notification HTML.
    ob_start();
    mi_cliente_theme_render_mini_cart_notification();
    $fragments['.mi-cliente-mini-cart-notification'] = ob_get_clean();

    // Cart count badge.
    ob_start();
    ?>
    <span class="mi-cliente-cart-count"><?php echo esc_html( $cart->get_cart_contents_count() ); ?></span>
    <?php
    $fragments['.mi-cliente-cart-count'] = ob_get_clean();

    return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'mi_cliente_theme_add_to_cart_fragments' );

/**
 * Render mini-cart notification popup.
 *
 * Shows the last added product with image, name, selected options, and price.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_render_mini_cart_notification() {
    $cart_items = WC()->cart->get_cart();

    if ( empty( $cart_items ) ) {
        echo '<div class="mi-cliente-mini-cart-notification" style="display:none;"></div>';
        return;
    }

    // Get the last added item.
    $last_item = end( $cart_items );
    $product   = $last_item['data'];
    $quantity  = $last_item['quantity'];
    ?>
    <div class="mi-cliente-mini-cart-notification" role="alert" aria-live="polite">
        <div class="mini-cart-notification__content">
            <div class="mini-cart-notification__image">
                <?php echo wp_kses_post( $product->get_image( 'woocommerce_gallery_thumbnail' ) ); ?>
            </div>
            <div class="mini-cart-notification__details">
                <p class="mini-cart-notification__title"><?php echo esc_html( $product->get_name() ); ?></p>
                <?php if ( ! empty( $last_item['variation'] ) ) : ?>
                    <p class="mini-cart-notification__variation">
                        <?php
                        $variation_parts = array();
                        foreach ( $last_item['variation'] as $attr => $value ) {
                            $attr_label = wc_attribute_label( str_replace( 'attribute_', '', $attr ) );
                            $variation_parts[] = $attr_label . ': ' . $value;
                        }
                        echo esc_html( implode( ' | ', $variation_parts ) );
                        ?>
                    </p>
                <?php endif; ?>
                <p class="mini-cart-notification__price">
                    <?php echo wp_kses_post( $product->get_price_html() ); ?> × <?php echo esc_html( $quantity ); ?>
                </p>
            </div>
            <button type="button" class="mini-cart-notification__close" aria-label="<?php esc_attr_e( 'Cerrar', 'mi-cliente-theme' ); ?>">×</button>
        </div>
        <div class="mini-cart-notification__actions">
            <a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="btn btn--primary">
                <?php esc_html_e( 'Ver carrito', 'mi-cliente-theme' ); ?>
            </a>
            <button type="button" class="btn btn--secondary mini-cart-notification__continue">
                <?php esc_html_e( 'Seguir comprando', 'mi-cliente-theme' ); ?>
            </button>
        </div>
    </div>
    <?php
}


/**
 * AJAX handler for cart quantity update.
 *
 * Recalculates cart totals without full page reload when quantity changes.
 * Enforces min 1, max stock constraints.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_ajax_update_cart_quantity() {
    check_ajax_referer( 'mi_cliente_cart_nonce', 'nonce' );

    $cart_item_key = isset( $_POST['cart_item_key'] ) ? sanitize_text_field( wp_unslash( $_POST['cart_item_key'] ) ) : '';
    $quantity      = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;

    if ( empty( $cart_item_key ) ) {
        wp_send_json_error( array( 'message' => __( 'Item no encontrado.', 'mi-cliente-theme' ) ) );
    }

    // Enforce minimum quantity of 1.
    if ( $quantity < 1 ) {
        $quantity = 1;
    }

    $cart_item = WC()->cart->get_cart_item( $cart_item_key );

    if ( ! $cart_item ) {
        wp_send_json_error( array( 'message' => __( 'Item no encontrado en el carrito.', 'mi-cliente-theme' ) ) );
    }

    // Enforce max stock constraint.
    $product = $cart_item['data'];
    if ( $product->managing_stock() ) {
        $stock_qty = $product->get_stock_quantity();
        if ( $quantity > $stock_qty ) {
            $quantity = $stock_qty;
            wp_send_json_error( array(
                'message'  => sprintf(
                    /* translators: %d: max stock quantity */
                    __( 'Stock máximo disponible: %d unidades.', 'mi-cliente-theme' ),
                    $stock_qty
                ),
                'quantity' => $stock_qty,
            ) );
        }
    }

    WC()->cart->set_quantity( $cart_item_key, $quantity, true );
    WC()->cart->calculate_totals();

    // Build response with updated cart data.
    $line_subtotal = WC()->cart->get_cart_item( $cart_item_key );
    $response = array(
        'quantity'      => $quantity,
        'line_subtotal' => wc_price( $line_subtotal['line_subtotal'] ),
        'cart_subtotal' => WC()->cart->get_cart_subtotal(),
        'cart_total'    => WC()->cart->get_total(),
        'cart_count'    => WC()->cart->get_cart_contents_count(),
    );

    wp_send_json_success( $response );
}
add_action( 'wp_ajax_mi_cliente_update_cart_qty', 'mi_cliente_theme_ajax_update_cart_quantity' );
add_action( 'wp_ajax_nopriv_mi_cliente_update_cart_qty', 'mi_cliente_theme_ajax_update_cart_quantity' );


/**
 * Customize WooCommerce checkout fields.
 *
 * Sets up checkout form with: nombre completo, dirección, ciudad,
 * departamento, código postal, teléfono.
 *
 * @since 1.0.0
 *
 * @param array $fields The checkout fields.
 * @return array Modified checkout fields.
 */
function mi_cliente_theme_checkout_fields( $fields ) {
    // Billing fields customization.
    $fields['billing'] = array(
        'billing_first_name' => array(
            'type'        => 'text',
            'label'       => __( 'Nombre completo', 'mi-cliente-theme' ),
            'placeholder' => __( 'Tu nombre completo', 'mi-cliente-theme' ),
            'required'    => true,
            'class'       => array( 'form-row-wide' ),
            'priority'    => 10,
        ),
        'billing_address_1' => array(
            'type'        => 'text',
            'label'       => __( 'Dirección', 'mi-cliente-theme' ),
            'placeholder' => __( 'Calle, número, apartamento', 'mi-cliente-theme' ),
            'required'    => true,
            'class'       => array( 'form-row-wide' ),
            'priority'    => 20,
        ),
        'billing_city' => array(
            'type'        => 'text',
            'label'       => __( 'Ciudad', 'mi-cliente-theme' ),
            'placeholder' => __( 'Tu ciudad', 'mi-cliente-theme' ),
            'required'    => true,
            'class'       => array( 'form-row-first' ),
            'priority'    => 30,
        ),
        'billing_state' => array(
            'type'        => 'text',
            'label'       => __( 'Departamento', 'mi-cliente-theme' ),
            'placeholder' => __( 'Tu departamento', 'mi-cliente-theme' ),
            'required'    => true,
            'class'       => array( 'form-row-last' ),
            'priority'    => 40,
        ),
        'billing_postcode' => array(
            'type'        => 'text',
            'label'       => __( 'Código postal', 'mi-cliente-theme' ),
            'placeholder' => __( 'Código postal', 'mi-cliente-theme' ),
            'required'    => false,
            'class'       => array( 'form-row-first' ),
            'priority'    => 50,
        ),
        'billing_phone' => array(
            'type'        => 'tel',
            'label'       => __( 'Teléfono', 'mi-cliente-theme' ),
            'placeholder' => __( '+51 999 999 999', 'mi-cliente-theme' ),
            'required'    => true,
            'class'       => array( 'form-row-last' ),
            'priority'    => 60,
            'validate'    => array( 'phone' ),
        ),
        'billing_email' => array(
            'type'        => 'email',
            'label'       => __( 'Correo electrónico', 'mi-cliente-theme' ),
            'placeholder' => __( 'tu@email.com', 'mi-cliente-theme' ),
            'required'    => true,
            'class'       => array( 'form-row-wide' ),
            'priority'    => 70,
        ),
    );

    // Shipping fields mirror billing for physical delivery.
    $fields['shipping'] = array(
        'shipping_first_name' => array(
            'type'        => 'text',
            'label'       => __( 'Nombre completo', 'mi-cliente-theme' ),
            'placeholder' => __( 'Nombre del destinatario', 'mi-cliente-theme' ),
            'required'    => true,
            'class'       => array( 'form-row-wide' ),
            'priority'    => 10,
        ),
        'shipping_address_1' => array(
            'type'        => 'text',
            'label'       => __( 'Dirección', 'mi-cliente-theme' ),
            'placeholder' => __( 'Calle, número, apartamento', 'mi-cliente-theme' ),
            'required'    => true,
            'class'       => array( 'form-row-wide' ),
            'priority'    => 20,
        ),
        'shipping_city' => array(
            'type'        => 'text',
            'label'       => __( 'Ciudad', 'mi-cliente-theme' ),
            'placeholder' => __( 'Ciudad de destino', 'mi-cliente-theme' ),
            'required'    => true,
            'class'       => array( 'form-row-first' ),
            'priority'    => 30,
        ),
        'shipping_state' => array(
            'type'        => 'text',
            'label'       => __( 'Departamento', 'mi-cliente-theme' ),
            'placeholder' => __( 'Departamento', 'mi-cliente-theme' ),
            'required'    => true,
            'class'       => array( 'form-row-last' ),
            'priority'    => 40,
        ),
        'shipping_postcode' => array(
            'type'        => 'text',
            'label'       => __( 'Código postal', 'mi-cliente-theme' ),
            'placeholder' => __( 'Código postal', 'mi-cliente-theme' ),
            'required'    => false,
            'class'       => array( 'form-row-first' ),
            'priority'    => 50,
        ),
        'shipping_phone' => array(
            'type'        => 'tel',
            'label'       => __( 'Teléfono', 'mi-cliente-theme' ),
            'placeholder' => __( '+51 999 999 999', 'mi-cliente-theme' ),
            'required'    => true,
            'class'       => array( 'form-row-last' ),
            'priority'    => 60,
        ),
    );

    return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'mi_cliente_theme_checkout_fields' );


/**
 * Customize order confirmation (thank you) page.
 *
 * Displays: order number, product summary, shipping address,
 * shipping method, payment method, and total.
 *
 * @since 1.0.0
 *
 * @param int $order_id The order ID.
 */
function mi_cliente_theme_order_confirmation( $order_id ) {
    $order = wc_get_order( $order_id );

    if ( ! $order ) {
        return;
    }
    ?>
    <div class="mi-cliente-order-confirmation">
        <div class="order-confirmation__header">
            <h2><?php esc_html_e( '¡Pedido confirmado!', 'mi-cliente-theme' ); ?></h2>
            <p class="order-confirmation__number">
                <?php
                printf(
                    /* translators: %s: order number */
                    esc_html__( 'Número de pedido: %s', 'mi-cliente-theme' ),
                    '<strong>' . esc_html( $order->get_order_number() ) . '</strong>'
                );
                ?>
            </p>
        </div>

        <div class="order-confirmation__summary">
            <h3><?php esc_html_e( 'Resumen del pedido', 'mi-cliente-theme' ); ?></h3>
            <table class="order-confirmation__products" aria-label="<?php esc_attr_e( 'Productos del pedido', 'mi-cliente-theme' ); ?>">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Producto', 'mi-cliente-theme' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Cantidad', 'mi-cliente-theme' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Subtotal', 'mi-cliente-theme' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $order->get_items() as $item ) : ?>
                        <tr>
                            <td>
                                <?php echo esc_html( $item->get_name() ); ?>
                                <?php
                                $meta_data = $item->get_formatted_meta_data( '' );
                                if ( $meta_data ) :
                                    foreach ( $meta_data as $meta ) :
                                ?>
                                    <br><small><?php echo wp_kses_post( $meta->display_key . ': ' . $meta->display_value ); ?></small>
                                <?php
                                    endforeach;
                                endif;
                                ?>
                            </td>
                            <td><?php echo esc_html( $item->get_quantity() ); ?></td>
                            <td><?php echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="order-confirmation__shipping">
            <h3><?php esc_html_e( 'Dirección de envío', 'mi-cliente-theme' ); ?></h3>
            <address>
                <?php echo wp_kses_post( $order->get_formatted_shipping_address() ); ?>
            </address>
        </div>

        <div class="order-confirmation__methods">
            <div class="order-confirmation__shipping-method">
                <h4><?php esc_html_e( 'Método de envío', 'mi-cliente-theme' ); ?></h4>
                <p>
                    <?php
                    $shipping_methods = $order->get_shipping_methods();
                    if ( $shipping_methods ) {
                        foreach ( $shipping_methods as $method ) {
                            echo esc_html( $method->get_name() );
                        }
                    } else {
                        esc_html_e( 'No especificado', 'mi-cliente-theme' );
                    }
                    ?>
                </p>
            </div>

            <div class="order-confirmation__payment-method">
                <h4><?php esc_html_e( 'Método de pago', 'mi-cliente-theme' ); ?></h4>
                <p><?php echo esc_html( $order->get_payment_method_title() ); ?></p>
            </div>
        </div>

        <div class="order-confirmation__total">
            <table aria-label="<?php esc_attr_e( 'Totales del pedido', 'mi-cliente-theme' ); ?>">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Subtotal', 'mi-cliente-theme' ); ?></th>
                    <td><?php echo wp_kses_post( $order->get_subtotal_to_display() ); ?></td>
                </tr>
                <?php if ( $order->get_shipping_total() > 0 ) : ?>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Envío', 'mi-cliente-theme' ); ?></th>
                    <td><?php echo wp_kses_post( wc_price( $order->get_shipping_total() ) ); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ( $order->get_total_tax() > 0 ) : ?>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Impuestos', 'mi-cliente-theme' ); ?></th>
                    <td><?php echo wp_kses_post( wc_price( $order->get_total_tax() ) ); ?></td>
                </tr>
                <?php endif; ?>
                <tr class="order-confirmation__total-row">
                    <th scope="row"><?php esc_html_e( 'Total', 'mi-cliente-theme' ); ?></th>
                    <td><strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong></td>
                </tr>
            </table>
        </div>
    </div>
    <?php
}
add_action( 'woocommerce_thankyou', 'mi_cliente_theme_order_confirmation', 5 );


/**
 * Handle payment failure gracefully.
 *
 * Preserves cart items and checkout form data on payment failure.
 * Shows descriptive error message and allows retry.
 *
 * @since 1.0.0
 *
 * @param int $order_id The failed order ID.
 */
function mi_cliente_theme_payment_failure_handler( $order_id ) {
    $order = wc_get_order( $order_id );

    if ( ! $order ) {
        return;
    }

    // Store checkout form data in session for repopulation.
    $session_data = array(
        'billing_first_name' => $order->get_billing_first_name(),
        'billing_address_1'  => $order->get_billing_address_1(),
        'billing_city'       => $order->get_billing_city(),
        'billing_state'      => $order->get_billing_state(),
        'billing_postcode'   => $order->get_billing_postcode(),
        'billing_phone'      => $order->get_billing_phone(),
        'billing_email'      => $order->get_billing_email(),
    );

    WC()->session->set( 'mi_cliente_checkout_retry_data', $session_data );
}
add_action( 'woocommerce_order_status_failed', 'mi_cliente_theme_payment_failure_handler' );

/**
 * Repopulate checkout form fields after payment failure.
 *
 * Retrieves stored form data from session and pre-fills checkout fields.
 *
 * @since 1.0.0
 *
 * @param string $value The field value.
 * @param string $input The field key.
 * @return string The pre-filled value or original value.
 */
function mi_cliente_theme_repopulate_checkout_fields( $value, $input ) {
    if ( ! WC()->session ) {
        return $value;
    }

    $retry_data = WC()->session->get( 'mi_cliente_checkout_retry_data' );

    if ( $retry_data && isset( $retry_data[ $input ] ) && empty( $value ) ) {
        return $retry_data[ $input ];
    }

    return $value;
}
add_filter( 'woocommerce_checkout_get_value', 'mi_cliente_theme_repopulate_checkout_fields', 10, 2 );


/**
 * Display descriptive payment failure notice.
 *
 * Shows a user-friendly error message with retry instructions
 * when payment fails.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_payment_failure_notice() {
    if ( ! is_checkout() ) {
        return;
    }

    if ( ! WC()->session ) {
        return;
    }

    $retry_data = WC()->session->get( 'mi_cliente_checkout_retry_data' );

    if ( $retry_data ) {
        wc_add_notice(
            __( 'El pago no pudo ser procesado. Tus datos y carrito han sido preservados. Por favor verifica tu información de pago e intenta nuevamente.', 'mi-cliente-theme' ),
            'error'
        );

        // Clear retry data after displaying the notice.
        WC()->session->set( 'mi_cliente_checkout_retry_data', null );
    }
}
add_action( 'woocommerce_before_checkout_form', 'mi_cliente_theme_payment_failure_notice' );

/**
 * Ensure cart is preserved on payment failure.
 *
 * Prevents WooCommerce from emptying the cart when an order fails.
 *
 * @since 1.0.0
 *
 * @param bool $empty Whether to empty the cart.
 * @param WC_Order $order The order object.
 * @return bool Whether to empty the cart.
 */
function mi_cliente_theme_preserve_cart_on_failure( $empty, $order ) {
    if ( $order && $order->has_status( 'failed' ) ) {
        return false;
    }
    return $empty;
}
add_filter( 'woocommerce_order_again_cart_item_data', 'mi_cliente_theme_preserve_cart_on_failure', 10, 2 );

/**
 * Customize cart item display data.
 *
 * Ensures cart displays: product image, name, size, color, unit price,
 * line subtotal, and quantity controls.
 *
 * @since 1.0.0
 *
 * @param array $item_data The cart item data for display.
 * @param array $cart_item The cart item.
 * @return array Modified item data.
 */
function mi_cliente_theme_cart_item_display_data( $item_data, $cart_item ) {
    if ( ! empty( $cart_item['variation'] ) ) {
        // Ensure color and size are prominently displayed.
        foreach ( $cart_item['variation'] as $attr => $value ) {
            if ( empty( $value ) ) {
                continue;
            }
            $taxonomy = str_replace( 'attribute_', '', $attr );
            $term = get_term_by( 'slug', $value, $taxonomy );
            $label = wc_attribute_label( $taxonomy );
            $display_value = $term ? $term->name : $value;

            // Check if already in item_data to avoid duplicates.
            $exists = false;
            foreach ( $item_data as $data ) {
                if ( $data['key'] === $label ) {
                    $exists = true;
                    break;
                }
            }

            if ( ! $exists ) {
                $item_data[] = array(
                    'key'   => $label,
                    'value' => $display_value,
                );
            }
        }
    }

    return $item_data;
}
add_filter( 'woocommerce_get_item_data', 'mi_cliente_theme_cart_item_display_data', 10, 2 );
