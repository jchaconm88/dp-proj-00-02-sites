/**
 * Shop sidebar: dual range price slider synced with number inputs.
 */
(function () {
    'use strict';

    function initPriceFilter(form) {
        var minRange = form.querySelector('.mi-cliente-price-filter__range--min');
        var maxRange = form.querySelector('.mi-cliente-price-filter__range--max');
        var minInput = form.querySelector('input[name="min_price"]');
        var maxInput = form.querySelector('input[name="max_price"]');

        if (!minRange || !maxRange || !minInput || !maxInput) {
            return;
        }

        var minBound = parseFloat(minRange.min, 10);
        var maxBound = parseFloat(maxRange.max, 10);
        var step = parseFloat(minRange.step, 10) || 1;

        function clamp(value) {
            return Math.max(minBound, Math.min(maxBound, value));
        }

        function syncFromInputs() {
            var minVal = clamp(parseFloat(minInput.value, 10) || minBound);
            var maxVal = clamp(parseFloat(maxInput.value, 10) || maxBound);

            if (minVal > maxVal) {
                if (document.activeElement === minInput) {
                    maxVal = minVal;
                } else {
                    minVal = maxVal;
                }
            }

            minInput.value = String(minVal);
            maxInput.value = String(maxVal);
            minRange.value = String(minVal);
            maxRange.value = String(maxVal);
            updateTrack(form, minVal, maxVal);
        }

        function syncFromRanges() {
            var minVal = clamp(parseFloat(minRange.value, 10));
            var maxVal = clamp(parseFloat(maxRange.value, 10));

            if (minVal > maxVal) {
                if (document.activeElement === minRange) {
                    maxRange.value = String(minVal);
                    maxVal = minVal;
                } else {
                    minRange.value = String(maxVal);
                    minVal = maxVal;
                }
            }

            minInput.value = String(minVal);
            maxInput.value = String(maxVal);
            updateTrack(form, minVal, maxVal);
        }

        minRange.addEventListener('input', syncFromRanges);
        maxRange.addEventListener('input', syncFromRanges);
        minInput.addEventListener('change', syncFromInputs);
        maxInput.addEventListener('change', syncFromInputs);
        minInput.addEventListener('input', syncFromInputs);
        maxInput.addEventListener('input', syncFromInputs);

        syncFromInputs();
    }

    function updateTrack(form, minVal, maxVal) {
        var slider = form.querySelector('.mi-cliente-price-filter__slider');
        if (!slider) {
            return;
        }

        var minBound = parseFloat(form.querySelector('.mi-cliente-price-filter__range--min').min, 10);
        var maxBound = parseFloat(form.querySelector('.mi-cliente-price-filter__range--max').max, 10);
        var span = maxBound - minBound || 1;
        var left = ((minVal - minBound) / span) * 100;
        var right = ((maxVal - minBound) / span) * 100;

        slider.style.setProperty('--range-left', left + '%');
        slider.style.setProperty('--range-right', right + '%');
    }

    document.querySelectorAll('.mi-cliente-price-filter__form').forEach(initPriceFilter);
})();
