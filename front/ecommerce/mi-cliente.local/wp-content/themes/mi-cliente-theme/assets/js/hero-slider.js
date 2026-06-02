/**
 * Hero slider — autoplay, flechas, puntos y reduced motion.
 *
 * @package Mi_Cliente_Theme
 */
(function () {
	"use strict";

	function prefersReducedMotion() {
		return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
	}

	function initHeroSlider(root) {
		var slides = root.querySelectorAll("[data-hero-slide]");
		var dots = root.querySelectorAll("[data-hero-dot]");
		var prevBtn = root.querySelector("[data-hero-prev]");
		var nextBtn = root.querySelector("[data-hero-next]");
		var count = slides.length;

		if (count < 2) {
			return;
		}

		var index = 0;
		var timer = null;
		var autoplayMs = parseInt(root.getAttribute("data-autoplay") || "6000", 10);
		if (isNaN(autoplayMs) || autoplayMs < 3000) {
			autoplayMs = 6000;
		}

		function setActive(nextIndex) {
			index = (nextIndex + count) % count;

			slides.forEach(function (slide, i) {
				var active = i === index;
				slide.classList.toggle("is-active", active);
				slide.setAttribute("aria-hidden", active ? "false" : "true");
			});

			dots.forEach(function (dot, i) {
				var active = i === index;
				dot.classList.toggle("is-active", active);
				dot.setAttribute("aria-selected", active ? "true" : "false");
			});
		}

		function stopAutoplay() {
			if (timer) {
				window.clearInterval(timer);
				timer = null;
			}
		}

		function startAutoplay() {
			stopAutoplay();
			if (prefersReducedMotion()) {
				return;
			}
			timer = window.setInterval(function () {
				setActive(index + 1);
			}, autoplayMs);
		}

		function go(delta) {
			setActive(index + delta);
			startAutoplay();
		}

		if (prevBtn) {
			prevBtn.addEventListener("click", function () {
				go(-1);
			});
		}
		if (nextBtn) {
			nextBtn.addEventListener("click", function () {
				go(1);
			});
		}

		dots.forEach(function (dot) {
			dot.addEventListener("click", function () {
				var target = parseInt(dot.getAttribute("data-hero-dot") || "0", 10);
				if (!isNaN(target)) {
					setActive(target);
					startAutoplay();
				}
			});
		});

		root.addEventListener("mouseenter", stopAutoplay);
		root.addEventListener("mouseleave", startAutoplay);
		root.addEventListener("focusin", stopAutoplay);
		root.addEventListener("focusout", startAutoplay);

		var touchStartX = 0;
		root.addEventListener(
			"touchstart",
			function (e) {
				touchStartX = e.changedTouches[0].screenX;
			},
			{ passive: true }
		);
		root.addEventListener(
			"touchend",
			function (e) {
				var diff = e.changedTouches[0].screenX - touchStartX;
				if (Math.abs(diff) < 50) {
					return;
				}
				go(diff < 0 ? 1 : -1);
			},
			{ passive: true }
		);

		startAutoplay();
	}

	function boot() {
		document.querySelectorAll(".hero-slider").forEach(initHeroSlider);
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", boot);
	} else {
		boot();
	}
})();
