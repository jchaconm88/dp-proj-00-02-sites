/**
 * Cart & Checkout - Mini-cart notification, AJAX quantity updates.
 *
 * Handles:
 * - Visual confirmation (mini-cart notification) on add-to-cart
 * - Cart quantity controls (min 1, max stock) with AJAX recalculation
 * - Payment failure form preservation
 *
 * @package Mi_Cliente_Theme
 * @since 1.0.0
 */
(function ($) {
    'use strict';

    if (typeof miClienteCart === 'undefined') {
        return;
    }

    var ajaxUrl = miClienteCart.ajaxUrl;
    var nonce = miClienteCart.nonce;
    var i18n = miClienteCart.i18n;

    /**
     * Show mini-cart notification after successful add-to-cart.
     */
    $(document.body).on('added_to_cart', function () {
        var $notification = $('.mi-cliente-mini-cart-notification');
        if ($notification.length) {
            $notification.addClass('mi-cliente-mini-cart-notification--visible')
                .attr('aria-hidden', 'false')
                .show();

            // Auto-hide after 5 seconds.
            setTimeout(function () {
                closeMiniCartNotification();
            }, 5000);
        }
    });

    /**
     * Close mini-cart notification.
     */
    $(document).on('click', '.mini-cart-notification__close, .mini-cart-notification__continue', function (e) {
        e.preventDefault();
        closeMiniCartNotification();
    });

    function closeMiniCartNotification() {
        $('.mi-cliente-mini-cart-notification')
            .removeClass('mi-cliente-mini-cart-notification--visible')
            .attr('aria-hidden', 'true');
    }

    /**
     * Cart quantity controls - AJAX update without full page reload.
     * Enforces min 1, max stock constraints.
     */
    $(document).on('click', '.mi-cliente-qty-btn', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $input = $btn.closest('.mi-cliente-qty-controls').find('.mi-cliente-qty-input');
        var currentQty = parseInt($input.val(), 10) || 1;
        var maxQty = parseInt($input.attr('max'), 10) || 999;
        var minQty = 1;
        var newQty = currentQty;

        if ($btn.hasClass('mi-cliente-qty-btn--minus')) {
            newQty = Math.max(minQty, currentQty - 1);
        } else if ($btn.hasClass('mi-cliente-qty-btn--plus')) {
            newQty = Math.min(maxQty, currentQty + 1);
        }

        if (newQty === currentQty) {
            if (newQty >= maxQty) {
                showCartMessage(i18n.maxStock, 'warning');
            }
            return;
        }

        $input.val(newQty);
        updateCartQuantity($btn.data('cart-item-key'), newQty, $btn.closest('tr'));
    });

    /**
     * Handle manual quantity input change.
     */
    $(document).on('change', '.mi-cliente-qty-input', function () {
        var $input = $(this);
        var newQty = parseInt($input.val(), 10);
        var maxQty = parseInt($input.attr('max'), 10) || 999;
        var cartItemKey = $input.data('cart-item-key');

        if (isNaN(newQty) || newQty < 1) {
            newQty = 1;
            $input.val(1);
            showCartMessage(i18n.minQuantity, 'warning');
        }

        if (newQty > maxQty) {
            newQty = maxQty;
            $input.val(maxQty);
            showCartMessage(i18n.maxStock, 'warning');
        }

        updateCartQuantity(cartItemKey, newQty, $input.closest('tr'));
    });

    /**
     * AJAX cart quantity update.
     */
    function updateCartQuantity(cartItemKey, quantity, $row) {
        if (!cartItemKey) {
            return;
        }

        // Show loading state.
        $row.addClass('mi-cliente-cart-row--updating');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'mi_cliente_update_cart_qty',
                nonce: nonce,
                cart_item_key: cartItemKey,
                quantity: quantity
            },
            success: function (response) {
                $row.removeClass('mi-cliente-cart-row--updating');

                if (response.success) {
                    // Update line subtotal.
                    $row.find('.mi-cliente-line-subtotal').html(response.data.line_subtotal);
                    // Update cart totals.
                    $('.mi-cliente-cart-subtotal-value').html(response.data.cart_subtotal);
                    $('.mi-cliente-cart-total-value').html(response.data.cart_total);
                    // Update cart count badge.
                    $('.mi-cliente-cart-count').text(response.data.cart_count);
                } else if (response.data && response.data.message) {
                    showCartMessage(response.data.message, 'error');
                    if (response.data.quantity) {
                        $row.find('.mi-cliente-qty-input').val(response.data.quantity);
                    }
                }
            },
            error: function () {
                $row.removeClass('mi-cliente-cart-row--updating');
                showCartMessage(i18n.updating, 'error');
            }
        });
    }

    /**
     * Show a temporary cart message/notification.
     */
    function showCartMessage(message, type) {
        var $msg = $('<div class="mi-cliente-cart-message mi-cliente-cart-message--' + type + '" role="alert">' + message + '</div>');
        $('.woocommerce-notices-wrapper, .mi-cliente-cart-messages').first().append($msg);
        setTimeout(function () {
            $msg.fadeOut(300, function () { $(this).remove(); });
        }, 3000);
    }

})(jQuery);
