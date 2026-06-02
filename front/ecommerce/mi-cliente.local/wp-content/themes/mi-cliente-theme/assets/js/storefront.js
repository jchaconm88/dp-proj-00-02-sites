/**
 * Storefront D.Sam — chrome fijo, scroll a secciones y menú activo.
 *
 * @package Mi_Cliente_Theme
 */
(function () {
	"use strict";

	var config = window.miClienteStorefront || {};
	var header = document.getElementById("main-header");
	var chromeRoot = document.getElementById("dsam-chrome-root");
	var chromeSpacer = document.getElementById("dsam-chrome-spacer");

	function ensureChromeSpacer() {
		if (!chromeSpacer) {
			chromeSpacer = document.createElement("div");
			chromeSpacer.id = "dsam-chrome-spacer";
			chromeSpacer.setAttribute("aria-hidden", "true");
		}
		if (chromeRoot && chromeSpacer.parentNode !== document.body) {
			if (chromeRoot.nextSibling) {
				document.body.insertBefore(chromeSpacer, chromeRoot.nextSibling);
			} else {
				document.body.appendChild(chromeSpacer);
			}
		}
	}

	function updateChromeSpacer() {
		if (!chromeSpacer || !chromeRoot) {
			return;
		}
		var height = chromeRoot.offsetHeight;
		if (height > 0) {
			chromeSpacer.style.height = height + "px";
			document.documentElement.style.setProperty(
				"--dsam-chrome-height",
				height + "px"
			);
		}
	}

	function relocateChromeToBody() {
		if (!chromeRoot) {
			return;
		}

		ensureChromeSpacer();

		var adminBar = document.getElementById("wpadminbar");
		if (chromeRoot.parentNode !== document.body) {
			if (adminBar) {
				document.body.insertBefore(chromeRoot, adminBar.nextSibling);
			} else {
				document.body.insertBefore(chromeRoot, document.body.firstChild);
			}
		}

		if (header) {
			header.dataset.dsamRelocated = "1";
		}

		var part = document.querySelector(".dsam-chrome-part");
		if (part) {
			part.setAttribute("hidden", "hidden");
			part.setAttribute("aria-hidden", "true");
		}

		updateChromeSpacer();
	}

	function getAdminBarOffset() {
		var bar = document.getElementById("wpadminbar");
		return bar ? bar.offsetHeight || 0 : 0;
	}

	function updateHeaderOnScroll() {
		if (!header) {
			return;
		}
		header.classList.toggle("header-scrolled", window.pageYOffset > 50);
	}

	function getHeaderOffset() {
		var chromeH = chromeRoot ? chromeRoot.offsetHeight : 0;
		if (chromeH) {
			return chromeH + 8;
		}
		var headH = header ? header.offsetHeight : 120;
		return getAdminBarOffset() + headH + 8;
	}

	function initHeader() {
		relocateChromeToBody();
		updateHeaderOnScroll();

		window.addEventListener("scroll", updateHeaderOnScroll, { passive: true });
		window.addEventListener("resize", updateChromeSpacer, { passive: true });
		window.addEventListener("load", updateChromeSpacer);

		// Re-medir tras fuentes/imágenes del logo.
		window.setTimeout(updateChromeSpacer, 100);
		window.setTimeout(updateChromeSpacer, 500);
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", initHeader);
	} else {
		initHeader();
	}

	document.querySelectorAll(".product-card").forEach(function (card) {
		card.addEventListener("mouseenter", function () {
			card.style.transform = "translateY(-4px)";
		});
		card.addEventListener("mouseleave", function () {
			card.style.transform = "translateY(0)";
		});
	});

	var navLinks = document.querySelectorAll(".dsam-nav-link[data-dsam-section]");
	var sections = document.querySelectorAll("[data-dsam-section]");

	function setActiveNav(sectionId) {
		if (!sectionId) {
			return;
		}
		navLinks.forEach(function (link) {
			link.classList.toggle("active", link.getAttribute("data-dsam-section") === sectionId);
		});
	}

	function scrollToSection(sectionId, pushHash) {
		var target = document.getElementById(sectionId);
		if (!target) {
			return false;
		}
		var top =
			target.getBoundingClientRect().top + window.pageYOffset - getHeaderOffset();
		window.scrollTo({ top: Math.max(0, top), behavior: "smooth" });
		if (pushHash !== false) {
			history.replaceState(null, "", "#" + sectionId);
		}
		setActiveNav(sectionId);
		return true;
	}

	function isOnHome() {
		if (config.isFrontPage) {
			return true;
		}
		try {
			return (
				window.location.pathname ===
				new URL(config.homeUrl || "/", window.location.origin).pathname
			);
		} catch (e) {
			return false;
		}
	}

	function handleAnchorClick(event) {
		var link = event.currentTarget;
		var sectionId = link.getAttribute("data-dsam-section");
		if (!sectionId) {
			return;
		}
		if (isOnHome() && document.getElementById(sectionId)) {
			event.preventDefault();
			scrollToSection(sectionId, true);
		}
	}

	navLinks.forEach(function (link) {
		link.addEventListener("click", handleAnchorClick);
	});

	document
		.querySelectorAll(".dsam-scroll-link[data-dsam-section]")
		.forEach(function (link) {
			link.addEventListener("click", handleAnchorClick);
		});

	function scrollSpy() {
		if (!sections.length || !navLinks.length) {
			return;
		}
		var scrollPos = window.pageYOffset + getHeaderOffset() + 40;
		var current = "";
		sections.forEach(function (section) {
			var id = section.getAttribute("data-dsam-section") || section.id;
			if (id && section.offsetTop <= scrollPos) {
				current = id;
			}
		});
		if (current) {
			setActiveNav(current);
		}
	}

	var spyTicking = false;
	window.addEventListener(
		"scroll",
		function () {
			if (!spyTicking) {
				window.requestAnimationFrame(function () {
					scrollSpy();
					spyTicking = false;
				});
				spyTicking = true;
			}
		},
		{ passive: true }
	);

	function openHashOnLoad() {
		var hash = window.location.hash.replace(/^#/, "");
		if (!hash) {
			scrollSpy();
			return;
		}
		window.setTimeout(function () {
			updateChromeSpacer();
			if (scrollToSection(hash, false)) {
				scrollSpy();
			}
		}, 250);
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", openHashOnLoad);
	} else {
		openHashOnLoad();
	}
})();
