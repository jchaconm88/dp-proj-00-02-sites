/**
 * Variable Product - Image gallery with zoom, color/size selectors.
 *
 * Updates main image on color selection, shows only sizes with stock
 * for selected color, marks out-of-stock variations as unavailable.
 *
 * @package Mi_Cliente_Theme
 * @since 1.0.0
 */
(function ($) {
    'use strict';

    if (typeof miClienteVariations === 'undefined') {
        return;
    }

    var variations = miClienteVariations.variations;
    var i18n = miClienteVariations.i18n;

    /**
     * Color swatch selection handler.
     * Updates main image and filters available sizes.
     */
    $(document).on('click', '.color-swatch', function (e) {
        e.preventDefault();
        var $swatch = $(this);
        var color = $swatch.data('color');
        var colorName = $swatch.data('color-name');

        // Update active state.
        $('.color-swatch').attr('aria-checked', 'false').removeClass('color-swatch--active');
        $swatch.attr('aria-checked', 'true').addClass('color-swatch--active');

        // Update selected color name display.
        $('.swatches-selected-name').text(colorName);

        // Update WooCommerce variation form.
        $('select[name="attribute_pa_color"], input[name="attribute_pa_color"]').val(color).trigger('change');

        // Update main product image for this color.
        if (variations.colors && variations.colors[color] && variations.colors[color].image) {
            var newImageUrl = variations.colors[color].image;
            if (newImageUrl) {
                $('#mi-cliente-main-product-image').attr('src', newImageUrl);
                $('.gallery-main__image-wrapper').attr('data-zoom-src', newImageUrl);
            }
        }

        // Update size availability for selected color.
        updateSizeAvailability(color);
    });

    /**
     * Update size buttons availability based on selected color.
     * Shows only sizes with stock, greys out unavailable ones.
     */
    function updateSizeAvailability(selectedColor) {
        var colorData = variations.colors ? variations.colors[selectedColor] : null;

        $('.size-option').each(function () {
            var $btn = $(this);
            var size = $btn.data('size');

            if (!colorData || !colorData.sizes || !colorData.sizes[size]) {
                // Size not available for this color.
                $btn.addClass('size-option--unavailable')
                    .attr('disabled', true)
                    .attr('aria-disabled', 'true')
                    .attr('title', i18n.unavailable);
            } else if (!colorData.sizes[size].in_stock) {
                // Size exists but out of stock.
                $btn.addClass('size-option--unavailable')
                    .attr('disabled', true)
                    .attr('aria-disabled', 'true')
                    .attr('title', i18n.outOfStock);
            } else {
                // Size available and in stock.
                $btn.removeClass('size-option--unavailable')
                    .attr('disabled', false)
                    .attr('aria-disabled', 'false')
                    .attr('title', i18n.inStock);
            }
        });

        // Clear size selection when color changes.
        $('.size-option').attr('aria-checked', 'false').removeClass('size-option--active');
        $('.size-availability').text('');
    }

    /**
     * Size option selection handler.
     */
    $(document).on('click', '.size-option:not([disabled])', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var size = $btn.data('size');

        // Update active state.
        $('.size-option').attr('aria-checked', 'false').removeClass('size-option--active');
        $btn.attr('aria-checked', 'true').addClass('size-option--active');

        // Update WooCommerce variation form.
        $('select[name="attribute_pa_talla"], input[name="attribute_pa_talla"]').val(size).trigger('change');

        // Show availability info.
        var selectedColor = $('.color-swatch--active').data('color');
        if (selectedColor && variations.colors[selectedColor] && variations.colors[selectedColor].sizes[size]) {
            var sizeData = variations.colors[selectedColor].sizes[size];
            if (sizeData.max_qty > 0 && sizeData.max_qty <= 3) {
                $('.size-availability').text(i18n.inStock + ' (' + sizeData.max_qty + ')');
            } else {
                $('.size-availability').text(i18n.inStock);
            }
        }
    });

    /**
     * Gallery thumbnail click handler.
     */
    $(document).on('click', '.gallery-thumbnail', function (e) {
        e.preventDefault();
        var $thumb = $(this);
        var imageUrl = $thumb.data('image-url');
        var zoomUrl = $thumb.data('zoom-url');

        // Update active thumbnail.
        $('.gallery-thumbnail').removeClass('gallery-thumbnail--active');
        $thumb.addClass('gallery-thumbnail--active');

        // Update main image.
        $('#mi-cliente-main-product-image').attr('src', imageUrl);
        $('.gallery-main__image-wrapper').attr('data-zoom-src', zoomUrl || imageUrl);
    });

    /**
     * Image zoom on hover.
     */
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
