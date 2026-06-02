/**
 * Customizer — repetidor de diapositivas hero.
 *
 * @package Mi_Cliente_Theme
 */
(function ($) {
	"use strict";

	var cfg = window.miClienteHeroSlidesCustomizer || {};
	var i18n = cfg.i18n || {};
	var sectionChoices = cfg.sectionChoices || {};

	function parseSlides(raw) {
		if (!raw) {
			return [];
		}
		try {
			var data = JSON.parse(raw);
			return Array.isArray(data) ? data : [];
		} catch (e) {
			return [];
		}
	}

	function emptySlide() {
		return {
			image: 0,
			image_url: "",
			badge: "",
			title: "",
			text: "",
			primary_url: "",
			primary_label: "VER CATÁLOGO",
			secondary_url: "",
			secondary_label: "",
			primary_section: "tienda",
		};
	}

	function escapeHtml(str) {
		return String(str || "")
			.replace(/&/g, "&amp;")
			.replace(/</g, "&lt;")
			.replace(/>/g, "&gt;")
			.replace(/"/g, "&quot;");
	}

	function sectionOptions(selected) {
		var html = "";
		$.each(sectionChoices, function (value, label) {
			var sel = value === selected ? " selected" : "";
			html +=
				'<option value="' +
				escapeHtml(value) +
				'"' +
				sel +
				">" +
				escapeHtml(label) +
				"</option>";
		});
		return html;
	}

	function collectSlide($item) {
		var useCustomUrl = $item.find("[data-link-mode]").val() === "url";
		return {
			image: parseInt($item.find("[data-field=image]").val(), 10) || 0,
			image_url: $item.find("[data-field=image_url]").val() || "",
			badge: $item.find("[data-field=badge]").val() || "",
			title: $item.find("[data-field=title]").val() || "",
			text: $item.find("[data-field=text]").val() || "",
			primary_label: $item.find("[data-field=primary_label]").val() || "",
			primary_section: useCustomUrl
				? ""
				: $item.find("[data-field=primary_section]").val() || "tienda",
			primary_url: useCustomUrl
				? $item.find("[data-field=primary_url]").val() || ""
				: "",
			secondary_label: $item.find("[data-field=secondary_label]").val() || "",
			secondary_url: $item.find("[data-field=secondary_url]").val() || "",
		};
	}

	function syncJson($repeater) {
		var slides = [];
		$repeater.find(".mi-cliente-hero-slide").each(function () {
			slides.push(collectSlide($(this)));
		});
		$repeater
			.closest(".customize-control")
			.find(".mi-cliente-hero-slides-json")
			.val(JSON.stringify(slides))
			.trigger("change");
	}

	function updatePreview($item) {
		var id = parseInt($item.find("[data-field=image]").val(), 10) || 0;
		var urlField = $item.find("[data-field=image_url]").val() || "";
		var $img = $item.find(".mi-cliente-hero-slide__preview");
		var url = "";

		if (id && cfg.attachmentUrls && cfg.attachmentUrls[id]) {
			url = cfg.attachmentUrls[id];
		} else if (urlField) {
			url = urlField;
		}

		if (url) {
			$img.attr("src", url).addClass("is-visible");
		} else {
			$img.removeAttr("src").removeClass("is-visible");
		}
	}

	function toggleLinkFields($item) {
		var custom = $item.find("[data-link-mode]").val() === "url";
		$item.find("[data-link-section]").toggle(!custom);
		$item.find("[data-link-url]").toggle(custom);
	}

	function buildSlide($root, slide, index, open) {
		var useCustomUrl = !!(slide.primary_url && String(slide.primary_url).length);
		var linkMode = useCustomUrl ? "url" : "section";
		var titlePreview = (slide.title || slide.badge || "").split("\n")[0];
		var $item = $(
			'<div class="mi-cliente-hero-slide' +
				(open ? " is-open" : "") +
				'" data-index="' +
				index +
				'">' +
				'<div class="mi-cliente-hero-slide__head">' +
				'<strong>' +
				escapeHtml(
					(i18n.slideLabel || "Diapositiva") + " " + (index + 1)
				) +
				(titlePreview
					? ": " + escapeHtml(titlePreview.substring(0, 40))
					: "") +
				"</strong>" +
				'<div class="mi-cliente-hero-slide__head-actions">' +
				'<button type="button" class="button button-small" data-move="up" title="' +
				escapeHtml(i18n.moveUp || "Subir") +
				'">↑</button>' +
				'<button type="button" class="button button-small" data-move="down" title="' +
				escapeHtml(i18n.moveDown || "Bajar") +
				'">↓</button>' +
				'<button type="button" class="button button-small" data-remove title="' +
				escapeHtml(i18n.remove || "Eliminar") +
				'">×</button>' +
				"</div>" +
				"</div>" +
				'<div class="mi-cliente-hero-slide__body">' +
				'<div class="mi-cliente-hero-slide__row">' +
				"<label>" +
				escapeHtml(i18n.image || "Imagen de fondo") +
				"</label>" +
				'<div class="mi-cliente-hero-slide__media">' +
				'<img class="mi-cliente-hero-slide__preview" alt="" />' +
				'<input type="hidden" data-field="image" value="' +
				(parseInt(slide.image, 10) || 0) +
				'" />' +
				'<button type="button" class="button" data-select-image>' +
				escapeHtml(i18n.selectImage || "Elegir imagen") +
				"</button>" +
				'<button type="button" class="button" data-clear-image>' +
				escapeHtml(i18n.clearImage || "Quitar") +
				"</button>" +
				"</div>" +
				'<p class="mi-cliente-hero-slide__hint">' +
				escapeHtml(
					i18n.imageHint ||
						"O pega una URL externa si no usas la biblioteca de medios."
				) +
				"</p>" +
				'<input type="url" data-field="image_url" placeholder="https://..." value="' +
				escapeHtml(slide.image_url || "") +
				'" />' +
				"</div>" +
				'<div class="mi-cliente-hero-slide__row">' +
				"<label>" +
				escapeHtml(i18n.badge || "Etiqueta") +
				"</label>" +
				'<input type="text" data-field="badge" value="' +
				escapeHtml(slide.badge || "") +
				'" />' +
				"</div>" +
				'<div class="mi-cliente-hero-slide__row">' +
				"<label>" +
				escapeHtml(i18n.title || "Título") +
				"</label>" +
				'<textarea data-field="title" rows="2">' +
				escapeHtml(slide.title || "") +
				"</textarea>" +
				'<p class="mi-cliente-hero-slide__hint">' +
				escapeHtml(i18n.titleHint || "Usa Enter para una segunda línea.") +
				"</p>" +
				"</div>" +
				'<div class="mi-cliente-hero-slide__row">' +
				"<label>" +
				escapeHtml(i18n.text || "Descripción") +
				"</label>" +
				'<textarea data-field="text" rows="3">' +
				escapeHtml(slide.text || "") +
				"</textarea>" +
				"</div>" +
				'<div class="mi-cliente-hero-slide__row">' +
				"<label>" +
				escapeHtml(i18n.primaryLabel || "Texto botón principal") +
				"</label>" +
				'<input type="text" data-field="primary_label" value="' +
				escapeHtml(slide.primary_label || "") +
				'" />' +
				"</div>" +
				'<div class="mi-cliente-hero-slide__row">' +
				"<label>" +
				escapeHtml(i18n.linkMode || "Enlace del botón") +
				"</label>" +
				'<select data-link-mode>' +
				'<option value="section"' +
				(linkMode === "section" ? " selected" : "") +
				">" +
				escapeHtml(i18n.linkSection || "Sección de la portada") +
				"</option>" +
				'<option value="url"' +
				(linkMode === "url" ? " selected" : "") +
				">" +
				escapeHtml(i18n.linkUrl || "URL personalizada") +
				"</option>" +
				"</select>" +
				"</div>" +
				'<div class="mi-cliente-hero-slide__row" data-link-section>' +
				"<label>" +
				escapeHtml(i18n.primarySection || "Ir a sección") +
				"</label>" +
				'<select data-field="primary_section">' +
				sectionOptions(slide.primary_section || "tienda") +
				"</select>" +
				"</div>" +
				'<div class="mi-cliente-hero-slide__row" data-link-url>' +
				"<label>" +
				escapeHtml(i18n.primaryUrl || "URL del botón") +
				"</label>" +
				'<input type="url" data-field="primary_url" value="' +
				escapeHtml(slide.primary_url || "") +
				'" />' +
				"</div>" +
				'<details class="mi-cliente-hero-slide__optional">' +
				"<summary>" +
				escapeHtml(i18n.secondary || "Botón secundario (opcional)") +
				"</summary>" +
				'<div class="mi-cliente-hero-slide__row">' +
				"<label>" +
				escapeHtml(i18n.secondaryLabel || "Texto botón secundario") +
				"</label>" +
				'<input type="text" data-field="secondary_label" value="' +
				escapeHtml(slide.secondary_label || "") +
				'" />' +
				"</div>" +
				'<div class="mi-cliente-hero-slide__row">' +
				"<label>" +
				escapeHtml(i18n.secondaryUrl || "URL botón secundario") +
				"</label>" +
				'<input type="url" data-field="secondary_url" value="' +
				escapeHtml(slide.secondary_url || "") +
				'" />' +
				"</div>" +
				"</details>" +
				"</div>" +
				"</div>"
		);

		$root.append($item);
		toggleLinkFields($item);
		updatePreview($item);
		return $item;
	}

	function renderRepeater($control) {
		var $container = $control.container;
		var $root = $container.find(".mi-cliente-hero-slides-repeater");
		var raw = $container.find(".mi-cliente-hero-slides-json").val();
		var slides = parseSlides(raw);

		if (!slides.length) {
			slides = parseSlides(cfg.defaultSlides || "[]");
		}
		if (!slides.length) {
			slides = [emptySlide()];
		}

		$root.empty();
		slides.forEach(function (slide, index) {
			buildSlide($root, slide, index, index === 0);
		});
	}

	function reindexTitles($root) {
		$root.find(".mi-cliente-hero-slide").each(function (index) {
			var $item = $(this);
			$item.attr("data-index", index);
			var titlePreview =
				($item.find("[data-field=title]").val() ||
					$item.find("[data-field=badge]").val() ||
					"")
					.split("\n")[0];
			var label =
				(i18n.slideLabel || "Diapositiva") +
				" " +
				(index + 1) +
				(titlePreview ? ": " + titlePreview.substring(0, 40) : "");
			$item.find(".mi-cliente-hero-slide__head strong").text(label);
		});
	}

	function bindControl(control) {
		if (control._miClienteHeroBound) {
			return;
		}
		control._miClienteHeroBound = true;

		var $container = control.container;
		var $root = $container.find(".mi-cliente-hero-slides-repeater");

		if (!$root.length) {
			return;
		}

		renderRepeater(control);

		$container.on("click", ".mi-cliente-hero-slide__head", function (e) {
			if ($(e.target).closest("button").length) {
				return;
			}
			$(this).closest(".mi-cliente-hero-slide").toggleClass("is-open");
		});

		$container.on("click", ".mi-cliente-hero-slides-add", function (e) {
			e.preventDefault();
			var index = $root.find(".mi-cliente-hero-slide").length;
			buildSlide($root, emptySlide(), index, true);
			syncJson($root);
		});

		$container.on("click", "[data-remove]", function (e) {
			e.preventDefault();
			e.stopPropagation();
			if ($root.find(".mi-cliente-hero-slide").length <= 1) {
				window.alert(i18n.minSlides || "Debe haber al menos una diapositiva.");
				return;
			}
			$(this).closest(".mi-cliente-hero-slide").remove();
			reindexTitles($root);
			syncJson($root);
		});

		$container.on("click", "[data-move]", function (e) {
			e.preventDefault();
			e.stopPropagation();
			var $item = $(this).closest(".mi-cliente-hero-slide");
			var dir = $(this).data("move");
			if (dir === "up") {
				$item.prev(".mi-cliente-hero-slide").before($item);
			} else {
				$item.next(".mi-cliente-hero-slide").after($item);
			}
			reindexTitles($root);
			syncJson($root);
		});

		$container.on(
			"input change",
			"[data-field], [data-link-mode]",
			function () {
				var $item = $(this).closest(".mi-cliente-hero-slide");
				if ($(this).is("[data-link-mode]")) {
					toggleLinkFields($item);
				}
				if (
					$(this).is("[data-field=image]") ||
					$(this).is("[data-field=image_url]")
				) {
					updatePreview($item);
				}
				reindexTitles($root);
				syncJson($root);
			}
		);

		$container.on("click", "[data-select-image]", function (e) {
			e.preventDefault();
			var $item = $(this).closest(".mi-cliente-hero-slide");
			var frame = wp.media({
				title: i18n.selectImage || "Elegir imagen",
				button: { text: i18n.useImage || "Usar imagen" },
				library: { type: "image" },
				multiple: false,
			});

			frame.on("select", function () {
				var attachment = frame.state().get("selection").first().toJSON();
				$item.find("[data-field=image]").val(attachment.id);
				if (!cfg.attachmentUrls) {
					cfg.attachmentUrls = {};
				}
				cfg.attachmentUrls[attachment.id] =
					attachment.sizes && attachment.sizes.medium
						? attachment.sizes.medium.url
						: attachment.url;
				updatePreview($item);
				syncJson($root);
			});

			frame.open();
		});

		$container.on("click", "[data-clear-image]", function (e) {
			e.preventDefault();
			var $item = $(this).closest(".mi-cliente-hero-slide");
			$item.find("[data-field=image]").val("0");
			updatePreview($item);
			syncJson($root);
		});
	}

	function initHeroSlidesControl() {
		var control = wp.customize.control("mi_cliente_hero_slides");
		if (control) {
			bindControl(control);
		}
	}

	if (wp.customize.Control) {
		wp.customize.controlConstructor.mi_cliente_hero_slides =
			wp.customize.Control.extend({
				ready: function () {
					bindControl(this);
				},
			});
	}

	wp.customize.bind("ready", initHeroSlidesControl);
	wp.customize.bind("pane-ready", initHeroSlidesControl);
})(jQuery);
