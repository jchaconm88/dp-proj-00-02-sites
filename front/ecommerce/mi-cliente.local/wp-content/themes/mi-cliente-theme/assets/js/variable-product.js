/**
 * Variable Product - Color swatches synced with WooCommerce (classic + blocks).
 *
 * @package Mi_Cliente_Theme
 * @since 1.0.0
 */
(function ($) {
    'use strict';

    if (typeof miClienteVariations === 'undefined') {
        return;
    }

    var i18n = miClienteVariations.i18n || {};
    var colorField = (miClienteVariations.attributeFields && miClienteVariations.attributeFields.color)
        ? miClienteVariations.attributeFields.color
        : 'attribute_pa_color';
    var sizeField = (miClienteVariations.attributeFields && miClienteVariations.attributeFields.size)
        ? miClienteVariations.attributeFields.size
        : '';

    function getVariationMap() {
        return miClienteVariations.variations || { colors: {}, sizes: [] };
    }

    function findField(name) {
        if (!name) {
            return $();
        }
        return $('select[name="' + name + '"], input[name="' + name + '"]');
    }

    function getVariationForm() {
        var $form = $('form.variations_form, form.cart, .wp-block-woocommerce-add-to-cart-form form, .wc-block-add-to-cart-form');
        return $form.length ? $form.first() : $();
    }

    function triggerVariationCheck() {
        var $form = getVariationForm();
        if ($form.length) {
            $form.trigger('check_variations');
            $form.trigger('woocommerce_variation_select_change');
        }
        $(document.body).trigger('woocommerce_update_variation_values');
    }

    function normalizeAttributeSlug(value) {
        if (!value) {
            return '';
        }
        value = String(value).trim().replace(',', '.');
        var decimalMatch = /^(\d+)\.(\d+)$/.exec(value);
        if (decimalMatch) {
            return 'v' + decimalMatch[1] + 'pt' + decimalMatch[2];
        }
        return value;
    }

    function canonicalizeField(fieldName) {
        var $field = findField(fieldName);
        if (!$field.length) {
            return '';
        }
        var raw = $field.val();
        var normalized = normalizeAttributeSlug(raw);
        if (normalized && normalized !== raw && $field.find('option[value="' + normalized + '"]').length) {
            $field.val(normalized);
        }
        return normalized || raw || '';
    }

    function lookupSizeEntry(sizes, sizeSlug) {
        if (!sizes || !sizeSlug) {
            return null;
        }
        if (sizes[sizeSlug]) {
            return sizes[sizeSlug];
        }
        var normalized = normalizeAttributeSlug(sizeSlug);
        if (sizes[normalized]) {
            return sizes[normalized];
        }
        var legacy = String(sizeSlug).replace('.', '-');
        if (sizes[legacy]) {
            return sizes[legacy];
        }
        return null;
    }

    function getSelectedColorSlug() {
        return normalizeAttributeSlug(
            findField(colorField).val() || $('.color-swatch--active').data('color') || ''
        );
    }

    function getSelectedSizeSlug() {
        if (!sizeField) {
            return '';
        }
        return canonicalizeField(sizeField);
    }

    function getSizeData(colorSlug, sizeSlug) {
        var map = getVariationMap();
        colorSlug = normalizeAttributeSlug(colorSlug);
        sizeSlug = normalizeAttributeSlug(sizeSlug);
        if (!map.colors || !map.colors[colorSlug] || !map.colors[colorSlug].sizes) {
            return null;
        }
        return lookupSizeEntry(map.colors[colorSlug].sizes, sizeSlug);
    }

    function getVariationIdInput() {
        return getVariationForm().find('input[name="variation_id"], input.variation_id');
    }

    function getSizeBoxes() {
        return $('.mi-cliente-size-boxes .size-box');
    }

    function getSizeBoxesSelectedName() {
        return $('.mi-cliente-size-boxes .size-boxes-selected-name');
    }

    /**
     * Mark size boxes available/unavailable for the currently selected color.
     * A size is unavailable when, for that color, it has no variation or no stock.
     */
    function refreshSizeBoxAvailability() {
        var $boxes = getSizeBoxes();
        if (!$boxes.length) {
            return;
        }
        var color = getSelectedColorSlug();

        $boxes.each(function () {
            var $box = $(this);
            var sizeSlug = $box.data('size');

            if (!color) {
                // No color chosen yet: everything selectable.
                $box.removeClass('is-unavailable').attr('aria-disabled', 'false');
                return;
            }

            var sizeData = getSizeData(color, sizeSlug);
            var available = !!(sizeData && sizeData.variation_id && sizeData.in_stock);
            $box.toggleClass('is-unavailable', !available);
            $box.attr('aria-disabled', available ? 'false' : 'true');

            // If the active size became unavailable for the new color, clear it.
            if (!available && $box.hasClass('size-box--active')) {
                clearSizeSelection();
            }
        });
    }

    function clearSizeSelection() {
        getSizeBoxes().removeClass('size-box--active').attr('aria-checked', 'false');
        getSizeBoxesSelectedName().text(i18n.selectSize || 'Selecciona una talla');
        if (sizeField) {
            setVariationField(sizeField, '');
        }
    }

    function selectSizeBox(slug, name) {
        var $boxes = getSizeBoxes();
        $boxes.removeClass('size-box--active').attr('aria-checked', 'false');
        $boxes.filter('[data-size="' + slug + '"]')
            .addClass('size-box--active')
            .attr('aria-checked', 'true');
        getSizeBoxesSelectedName().text(name || slug);
        if (sizeField) {
            setVariationField(sizeField, slug);
        }
        syncFromSelections();
    }

    function applyVariationAttributes(color, size, trigger) {
        if (color) {
            findField(colorField).val(color);
        }
        if (sizeField && size) {
            findField(sizeField).val(size);
        }
        if (trigger !== false) {
            findField(colorField).trigger('change');
            if (sizeField) {
                findField(sizeField).trigger('change');
            }
            triggerVariationCheck();
        }
    }

    function prepareVariationForCart() {
        var color = getSelectedColorSlug();
        var size = getSelectedSizeSlug();
        if (!color || !size) {
            return false;
        }

        applyVariationAttributes(color, size, false);

        var sizeData = getSizeData(color, size);
        if (!sizeData || !sizeData.variation_id || !sizeData.in_stock) {
            return false;
        }

        triggerVariationCheck();

        var variationId = getVariationIdInput().val();
        if (!variationId || variationId === '0') {
            applyVariationId(sizeData.variation_id);
        }

        return true;
    }

    function setVariationField(fieldName, value, trigger) {
        var $field = findField(fieldName);
        if (!$field.length) {
            return;
        }
        $field.val(value).trigger('change');
        if (trigger !== false) {
            triggerVariationCheck();
        }
    }

    function applyVariationId(variationId) {
        if (!variationId) {
            return;
        }
        var $input = getVariationIdInput();
        if ($input.length) {
            $input.val(variationId).trigger('change');
        }
    }

    function getAddToCartScope() {
        var $form = getVariationForm();
        var $scope = $form.closest('.wp-block-woocommerce-add-to-cart-form, .wc-block-add-to-cart-form');
        return $scope.length ? $scope : $form;
    }

    function getAddToCartButton() {
        return getAddToCartScope().find(
            '.single_add_to_cart_button, .wc-block-components-add-to-cart-button, button[name="add-to-cart"]'
        );
    }

    function getQuantityControls() {
        var $scope = getAddToCartScope();
        var $controls = $scope.find('.quantity, .wc-block-components-quantity-selector');
        if (!$controls.length) {
            $controls = $scope.find('input.qty, input[name="quantity"]').closest('div');
        }
        return $controls;
    }

    function setPurchaseControlsVisible(visible) {
        var $scope = getAddToCartScope();
        if (visible) {
            $scope.removeClass('mi-cliente-variation-oos');
            getQuantityControls().show();
            getAddToCartButton().show();
            return;
        }
        $scope.addClass('mi-cliente-variation-oos');
        getQuantityControls().hide();
        getAddToCartButton().hide();
    }

    function ensureStockElement() {
        var $form = getVariationForm();
        var $stock = $form.find('.mi-cliente-variation-stock');
        if ($stock.length) {
            return $stock;
        }
        $stock = $('<p class="mi-cliente-variation-stock stock" aria-live="polite"></p>');
        var $btn = getAddToCartButton();
        if ($btn.length) {
            $btn.first().before($stock);
        } else {
            $form.append($stock);
        }
        return $stock;
    }

    /**
     * @param {boolean|null} inStock true = disponible, false = agotado, null = sin selección completa.
     * @param {boolean} unavailable true cuando la combinación no existe.
     */
    function updateStockUI(inStock, unavailable) {
        var $stock = ensureStockElement();

        if (inStock === null) {
            $stock.empty().hide();
            setPurchaseControlsVisible(false);
            return;
        }

        if (unavailable) {
            $stock.removeClass('in-stock').addClass('out-of-stock')
                .html('<span>' + (i18n.unavailable || 'No disponible') + '</span>').show();
            setPurchaseControlsVisible(false);
            return;
        }

        if (inStock) {
            $stock.empty().hide();
            if (prepareVariationForCart()) {
                setPurchaseControlsVisible(true);
                getAddToCartButton().prop('disabled', false);
            } else {
                setPurchaseControlsVisible(false);
            }
            return;
        }

        $stock.removeClass('in-stock').addClass('out-of-stock')
            .html('<span>' + (i18n.outOfStock || 'Agotado') + '</span>').show();
        setPurchaseControlsVisible(false);
    }

    function syncFromSelections() {
        var color = getSelectedColorSlug();
        var size = getSelectedSizeSlug();
        if (!color || !size) {
            updateStockUI(null);
            return;
        }

        applyVariationAttributes(color, size, false);

        var sizeData = getSizeData(color, size);
        if (!sizeData) {
            updateStockUI(false, true);
            return;
        }

        triggerVariationCheck();

        if (sizeData.variation_id) {
            var variationId = getVariationIdInput().val();
            if (!variationId || variationId === '0') {
                applyVariationId(sizeData.variation_id);
            }
        }

        updateStockUI(!!sizeData.in_stock, false);
    }

    $(document).on('click', '.color-swatch', function (e) {
        e.preventDefault();
        var $swatch = $(this);
        var color = $swatch.data('color');
        var colorName = $swatch.data('color-name');

        $('.color-swatch').attr('aria-checked', 'false').removeClass('color-swatch--active');
        $swatch.attr('aria-checked', 'true').addClass('color-swatch--active');
        $('.swatches-selected-name').text(colorName || '');

        setVariationField(colorField, color);

        var map = getVariationMap();
        if (map.colors && map.colors[color] && map.colors[color].image) {
            var newImageUrl = map.colors[color].image;
            $('#mi-cliente-main-product-image').attr('src', newImageUrl);
            $('.gallery-main__image-wrapper').attr('data-zoom-src', newImageUrl);
        }

        if (sizeField) {
            setVariationField(sizeField, '');
        }

        // Update which size boxes are available for this color.
        refreshSizeBoxAvailability();
    });

    // Size box selection (boxes drive the hidden native size <select>).
    $(document).on('click', '.mi-cliente-size-boxes .size-box', function (e) {
        e.preventDefault();
        var $box = $(this);
        if ($box.hasClass('is-unavailable')) {
            return;
        }
        selectSizeBox($box.data('size'), $box.data('size-name'));
    });

    $(function () {
        var $colorSelect = findField(colorField);
        var $sizeSelect = findField(sizeField);

        $colorSelect.on('change', function () {
            var slug = $(this).val();
            if (!slug) {
                return;
            }
            $('.color-swatch').each(function () {
                var $swatch = $(this);
                var active = $swatch.data('color') === slug;
                $swatch.attr('aria-checked', active ? 'true' : 'false');
                $swatch.toggleClass('color-swatch--active', active);
                if (active) {
                    $('.swatches-selected-name').text($swatch.data('color-name'));
                }
            });
            // Re-evaluate which sizes are in stock for the newly chosen color.
            refreshSizeBoxAvailability();
        });

        $sizeSelect.on('change', syncFromSelections);
        $colorSelect.on('change', function () {
            if ($sizeSelect.val()) {
                syncFromSelections();
            }
        });

        // Reflect an already-selected size (e.g. browser restored it) on the boxes.
        if ($sizeSelect.val()) {
            var initialSize = normalizeAttributeSlug($sizeSelect.val());
            getSizeBoxes()
                .filter('[data-size="' + initialSize + '"]')
                .addClass('size-box--active')
                .attr('aria-checked', 'true');
        }

        // Initial availability pass (covers preselected color / single color).
        refreshSizeBoxAvailability();

        if ($colorSelect.val() && $sizeSelect.val()) {
            syncFromSelections();
        }

        var $form = getVariationForm();
        if ($form.length) {
            $form.on('found_variation', function (event, variation) {
                updateStockUI(!!variation.is_in_stock, false);
            });
            $form.on('reset_data hide_variation', function () {
                if (getSelectedColorSlug() && getSelectedSizeSlug()) {
                    syncFromSelections();
                    return;
                }
                updateStockUI(null);
            });

            $form.on('submit', function (e) {
                if (!prepareVariationForCart()) {
                    e.preventDefault();
                    window.alert(i18n.selectColor || 'Por favor selecciona las opciones del producto.');
                    return false;
                }
            });
        }

        $(document).on('click', '.single_add_to_cart_button, .wc-block-components-add-to-cart-button button, button[name="add-to-cart"]', function (e) {
            var $form = getVariationForm();
            if (!$form.length || !sizeField) {
                return;
            }
            if (!prepareVariationForCart()) {
                e.preventDefault();
                e.stopImmediatePropagation();
                window.alert(i18n.selectColor || 'Por favor selecciona las opciones del producto.');
                return false;
            }
        });
    });

    $(document).on('click', '.gallery-thumbnail', function (e) {
        e.preventDefault();
        var $thumb = $(this);
        var imageUrl = $thumb.data('image-url');
        var zoomUrl = $thumb.data('zoom-url');

        $('.gallery-thumbnail').removeClass('gallery-thumbnail--active');
        $thumb.addClass('gallery-thumbnail--active');

        $('#mi-cliente-main-product-image').attr('src', imageUrl);
        $('.gallery-main__image-wrapper').attr('data-zoom-src', zoomUrl || imageUrl);
    });

    if (miClienteVariations.galleryZoom) {
        $(document).on('mouseenter', '.gallery-main__image-wrapper[data-zoom="true"]', function () {
            var $wrapper = $(this);
            var zoomSrc = $wrapper.attr('data-zoom-src') || $wrapper.find('img').attr('src');
            $wrapper.find('.gallery-zoom-lens').css({
                'background-image': 'url(' + zoomSrc + ')',
                'display': 'block'
            });
        });

        $(document).on('mousemove', '.gallery-main__image-wrapper[data-zoom="true"]', function (e) {
            var $wrapper = $(this);
            var $lens = $wrapper.find('.gallery-zoom-lens');
            var offset = $wrapper.offset();
            var x = ((e.pageX - offset.left) / $wrapper.width()) * 100;
            var y = ((e.pageY - offset.top) / $wrapper.height()) * 100;
            $lens.css('background-position', x + '% ' + y + '%');
        });

        $(document).on('mouseleave', '.gallery-main__image-wrapper[data-zoom="true"]', function () {
            $(this).find('.gallery-zoom-lens').css('display', 'none');
        });
    }

})(jQuery);
