/**
 * Customizer — repetidor de secciones de productos (portada).
 *
 * @package Mi_Cliente_Theme
 */
(function ($) {
	"use strict";

	var cfg = window.miClienteHomeSectionsCustomizer || {};
	cfg.layoutChoices = cfg.layoutChoices || {};
	cfg.backgroundChoices = cfg.backgroundChoices || {};
	cfg.iconChoices = cfg.iconChoices || {};
	cfg.productCategories = cfg.productCategories || {};
	var i18n = cfg.i18n || {};

	function parseSections(raw) {
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

	function emptySection() {
		return {
			section_id: "",
			menu_label: "",
			menu_icon: "category",
			show_in_nav: true,
			category: "",
			title: "",
			subtitle: "",
			limit: 4,
			layout: "featured",
			view_more_label: "Ver más productos",
			background: "soft-gray",
		};
	}

	function escapeHtml(str) {
		return String(str || "")
			.replace(/&/g, "&amp;")
			.replace(/</g, "&lt;")
			.replace(/>/g, "&gt;")
			.replace(/"/g, "&quot;");
	}

	function optionsFromMap(map, selected) {
		var html = "";
		$.each(map || {}, function (key, label) {
			var sel = key === selected ? " selected" : "";
			html +=
				'<option value="' +
				escapeHtml(key) +
				'"' +
				sel +
				">" +
				escapeHtml(label) +
				"</option>";
		});
		return html;
	}

	function checkedAttr(value) {
		return value === true || value === 1 || value === "1" || value === "true"
			? " checked"
			: "";
	}

	function collectSection($item) {
		return {
			section_id: $item.find("[data-field=section_id]").val() || "",
			menu_label: $item.find("[data-field=menu_label]").val() || "",
			menu_icon: $item.find("[data-field=menu_icon]").val() || "category",
			show_in_nav: $item.find("[data-field=show_in_nav]").is(":checked"),
			category: $item.find("[data-field=category]").val() || "",
			title: $item.find("[data-field=title]").val() || "",
			subtitle: $item.find("[data-field=subtitle]").val() || "",
			limit: parseInt($item.find("[data-field=limit]").val(), 10) || 4,
			layout: $item.find("[data-field=layout]").val() || "featured",
			view_more_label: $item.find("[data-field=view_more_label]").val() || "",
			background: $item.find("[data-field=background]").val() || "soft-gray",
		};
	}

	function syncJson($repeater) {
		var sections = [];
		$repeater.find(".mi-cliente-home-section").each(function () {
			sections.push(collectSection($(this)));
		});
		$repeater
			.closest(".customize-control")
			.find(".mi-cliente-home-sections-json")
			.val(JSON.stringify(sections))
			.trigger("change");
	}

	function buildSection($repeater, section, index, open) {
		var preview =
			section.menu_label || section.title || section.category || "";
		var $item = $(
			'<div class="mi-cliente-home-section' +
				(open ? " is-open" : "") +
				'" data-index="' +
				index +
				'">' +
				'<div class="mi-cliente-home-section__head">' +
				'<strong>' +
				escapeHtml(
					(i18n.sectionLabel || "Sección") +
						" " +
						(index + 1) +
						(preview ? ": " + preview.substring(0, 36) : "")
				) +
				"</strong>" +
				'<div class="mi-cliente-home-section__head-actions">' +
				'<button type="button" class="button button-small" data-move="up" title="' +
				escapeHtml(i18n.moveUp || "Subir") +
				'">↑</button>' +
				'<button type="button" class="button button-small" data-move="down" title="' +
				escapeHtml(i18n.moveDown || "Bajar") +
				'">↓</button>' +
				'<button type="button" class="button button-small" data-remove title="' +
				escapeHtml(i18n.remove || "Eliminar") +
				'">×</button>' +
				"</div></div>" +
				'<div class="mi-cliente-home-section__body">' +
				'<div class="mi-cliente-home-section__row">' +
				"<label>" +
				escapeHtml(i18n.menuLabel || "Texto en menú") +
				"</label>" +
				'<input type="text" data-field="menu_label" value="' +
				escapeHtml(section.menu_label || "") +
				'" />' +
				"</div>" +
				'<div class="mi-cliente-home-section__row mi-cliente-home-section__row--inline">' +
				'<input type="checkbox" data-field="show_in_nav" id="show-nav-' +
				index +
				'"' +
				checkedAttr(section.show_in_nav) +
				" />" +
				'<label for="show-nav-' +
				index +
				'">' +
				escapeHtml(i18n.showInNav || "Mostrar en menú superior") +
				"</label></div>" +
				'<div class="mi-cliente-home-section__row">' +
				"<label>" +
				escapeHtml(i18n.sectionId || "ID de ancla (section_id)") +
				"</label>" +
				'<input type="text" data-field="section_id" value="' +
				escapeHtml(section.section_id || "") +
				'" />' +
				'<p class="mi-cliente-home-section__hint">' +
				escapeHtml(
					i18n.sectionIdHint ||
						"Ancla en la portada (#calzado-mujer). Si está vacío, se usa el slug de categoría."
				) +
				"</p></div>" +
				'<div class="mi-cliente-home-section__row">' +
				"<label>" +
				escapeHtml(i18n.category || "Categoría WooCommerce (slug)") +
				"</label>" +
				'<input type="text" data-field="category" list="mi-cliente-wc-cats" value="' +
				escapeHtml(section.category || "") +
				'" />' +
				"</div>" +
				'<div class="mi-cliente-home-section__row">' +
				"<label>" +
				escapeHtml(i18n.menuIcon || "Icono del menú") +
				"</label>" +
				'<select data-field="menu_icon">' +
				optionsFromMap(cfg.iconChoices, section.menu_icon || "category") +
				"</select></div>" +
				'<div class="mi-cliente-home-section__row">' +
				"<label>" +
				escapeHtml(i18n.title || "Título de bloque") +
				"</label>" +
				'<input type="text" data-field="title" value="' +
				escapeHtml(section.title || "") +
				'" /></div>' +
				'<div class="mi-cliente-home-section__row">' +
				"<label>" +
				escapeHtml(i18n.subtitle || "Subtítulo") +
				"</label>" +
				'<textarea data-field="subtitle" rows="2">' +
				escapeHtml(section.subtitle || "") +
				"</textarea></div>" +
				'<div class="mi-cliente-home-section__row">' +
				"<label>" +
				escapeHtml(i18n.layout || "Diseño de tarjetas") +
				"</label>" +
				'<select data-field="layout">' +
				optionsFromMap(cfg.layoutChoices, section.layout || "featured") +
				"</select></div>" +
				'<div class="mi-cliente-home-section__row">' +
				"<label>" +
				escapeHtml(i18n.limit || "Cantidad de productos") +
				"</label>" +
				'<input type="number" min="1" max="24" data-field="limit" value="' +
				(parseInt(section.limit, 10) || 4) +
				'" /></div>' +
				'<div class="mi-cliente-home-section__row">' +
				"<label>" +
				escapeHtml(i18n.background || "Fondo de sección") +
				"</label>" +
				'<select data-field="background">' +
				optionsFromMap(
					cfg.backgroundChoices,
					section.background || "soft-gray"
				) +
				"</select></div>" +
				'<div class="mi-cliente-home-section__row">' +
				"<label>" +
				escapeHtml(i18n.viewMore || "Texto enlace «ver más»") +
				"</label>" +
				'<input type="text" data-field="view_more_label" value="' +
				escapeHtml(section.view_more_label || "") +
				'" /></div>' +
				"</div></div>"
		);

		if (!$item.length) {
			return null;
		}

		$repeater.append($item);
		return $item;
	}

	function renderRepeater(control) {
		var $container = control.container;
		var $repeater = $container.find(".mi-cliente-home-sections-repeater");
		var raw = $container.find(".mi-cliente-home-sections-json").val();
		var sections = parseSections(raw);

		if (!sections.length) {
			sections = parseSections(cfg.defaultSections || "[]");
		}
		if (!sections.length) {
			sections = [emptySection()];
		}

		$repeater.empty();
		sections.forEach(function (section, index) {
			buildSection($repeater, section, index, index === 0);
		});
	}

	function reindexTitles($repeater) {
		$repeater.find(".mi-cliente-home-section").each(function (index) {
			var $item = $(this);
			$item.attr("data-index", index);
			$item.find("[data-field=show_in_nav]").attr("id", "show-nav-" + index);
			$item
				.find('label[for^="show-nav-"]')
				.attr("for", "show-nav-" + index);
			var preview =
				$item.find("[data-field=menu_label]").val() ||
				$item.find("[data-field=title]").val() ||
				$item.find("[data-field=category]").val() ||
				"";
			var label =
				(i18n.sectionLabel || "Sección") +
				" " +
				(index + 1) +
				(preview ? ": " + preview.substring(0, 36) : "");
			$item.find(".mi-cliente-home-section__head strong").text(label);
		});
	}

	function bindControl(control) {
		if (control._miClienteHomeSectionsBound) {
			return;
		}

		var $container = control.container;
		var $repeater = $container.find(".mi-cliente-home-sections-repeater");
		var $json = $container.find(".mi-cliente-home-sections-json");

		if (!$repeater.length || !$json.length) {
			return;
		}

		if (!$("#mi-cliente-wc-cats").length && cfg.productCategories) {
			var $datalist = $('<datalist id="mi-cliente-wc-cats"></datalist>');
			$.each(cfg.productCategories, function (slug, label) {
				$datalist.append(
					$("<option>").attr("value", slug).text(label)
				);
			});
			$("body").append($datalist);
		}

		renderRepeater(control);
		control._miClienteHomeSectionsBound = true;

		$container.on("click", ".mi-cliente-home-section__head", function (e) {
			if ($(e.target).closest("button").length) {
				return;
			}
			$(this).closest(".mi-cliente-home-section").toggleClass("is-open");
		});

		$container.on("click", ".mi-cliente-home-sections-add", function (e) {
			e.preventDefault();
			var index = $repeater.find(".mi-cliente-home-section").length;
			buildSection($repeater, emptySection(), index, true);
			syncJson($repeater);
			reindexTitles($repeater);
		});

		$container.on("click", "[data-remove]", function (e) {
			e.preventDefault();
			e.stopPropagation();
			if ($repeater.find(".mi-cliente-home-section").length <= 1) {
				window.alert(
					i18n.minSections || "Debe haber al menos una sección."
				);
				return;
			}
			$(this).closest(".mi-cliente-home-section").remove();
			reindexTitles($repeater);
			syncJson($repeater);
		});

		$container.on("click", "[data-move]", function (e) {
			e.preventDefault();
			e.stopPropagation();
			var $item = $(this).closest(".mi-cliente-home-section");
			var dir = $(this).data("move");
			if (dir === "up") {
				$item.prev(".mi-cliente-home-section").before($item);
			} else {
				$item.next(".mi-cliente-home-section").after($item);
			}
			reindexTitles($repeater);
			syncJson($repeater);
		});

		$container.on(
			"input change",
			"[data-field]",
			function () {
				var $item = $(this).closest(".mi-cliente-home-section");
				if (
					$(this).is("[data-field=category]") &&
					!$item.find("[data-field=section_id]").val()
				) {
					var slug = String($(this).val() || "")
						.toLowerCase()
						.replace(/[^a-z0-9]+/g, "-")
						.replace(/^-+|-+$/g, "");
					if (slug) {
						$item.find("[data-field=section_id]").val(slug);
					}
				}
				reindexTitles($repeater);
				syncJson($repeater);
			}
		);
	}

	function initHomeSectionsControl() {
		var control = wp.customize.control("mi_cliente_home_sections");
		if (control) {
			bindControl(control);
		}
	}

	if (wp.customize.Control) {
		wp.customize.controlConstructor.mi_cliente_home_sections =
			wp.customize.Control.extend({
				ready: function () {
					var self = this;
					window.setTimeout(function () {
						bindControl(self);
					}, 0);
				},
			});
	}

	wp.customize.bind("ready", initHomeSectionsControl);
	wp.customize.bind("pane-ready", initHomeSectionsControl);

	wp.customize.bind("ready", function () {
		var section = wp.customize.section("mi_cliente_storefront");
		if (!section || !section.expanded) {
			return;
		}
		section.expanded.bind(function (isExpanded) {
			if (isExpanded) {
				initHomeSectionsControl();
			}
		});
		if (section.expanded.get()) {
			initHomeSectionsControl();
		}
	});
})(jQuery);
