/**
 * Customizer — repetidor de menú extra (portada).
 *
 * @package Mi_Cliente_Theme
 */
(function ($) {
	"use strict";

	var cfg = window.miClienteNavExtraCustomizer || {};
	cfg.sectionChoices = cfg.sectionChoices || {};
	cfg.iconChoices = cfg.iconChoices || {};
	var i18n = cfg.i18n || {};

	function parseItems(raw) {
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

	function emptyItem() {
		return {
			menu_label: "",
			section_id: "tienda",
			menu_icon: "storefront",
			show_in_nav: true,
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
		var found = false;
		$.each(map || {}, function (key, label) {
			if (key === selected) {
				found = true;
			}
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
		if (selected && !found) {
			html =
				'<option value="' +
				escapeHtml(selected) +
				'" selected>' +
				escapeHtml(selected) +
				"</option>" +
				html;
		}
		return html;
	}

	function checkedAttr(value) {
		return value === true || value === 1 || value === "1" || value === "true"
			? " checked"
			: "";
	}

	function collectItem($item) {
		return {
			menu_label: $item.find("[data-field=menu_label]").val() || "",
			section_id: $item.find("[data-field=section_id]").val() || "",
			menu_icon: $item.find("[data-field=menu_icon]").val() || "category",
			show_in_nav: $item.find("[data-field=show_in_nav]").is(":checked"),
		};
	}

	function syncJson($repeater) {
		var items = [];
		$repeater.find(".mi-cliente-nav-extra-item").each(function () {
			items.push(collectItem($(this)));
		});
		$repeater
			.closest(".customize-control")
			.find(".mi-cliente-nav-extra-json")
			.val(JSON.stringify(items))
			.trigger("change");
	}

	function buildItem($repeater, item, index, open) {
		var parts = [];
		parts.push('<div class="mi-cliente-nav-extra-item');
		if (open) {
			parts.push(" is-open");
		}
		parts.push('" data-index="');
		parts.push(index);
		parts.push('"><div class="mi-cliente-nav-extra-item__head"><strong>');
		parts.push(
			escapeHtml(
				(i18n.itemLabel || "Item") +
					" " +
					(index + 1) +
					(item.menu_label ? ": " + item.menu_label.substring(0, 32) : "")
			)
		);
		parts.push('</strong><div class="mi-cliente-nav-extra-item__head-actions">');
		parts.push(
			'<button type="button" class="button button-small" data-move="up" title="' +
				escapeHtml(i18n.moveUp || "Subir") +
				'">↑</button>'
		);
		parts.push(
			'<button type="button" class="button button-small" data-move="down" title="' +
				escapeHtml(i18n.moveDown || "Bajar") +
				'">↓</button>'
		);
		parts.push(
			'<button type="button" class="button button-small" data-remove title="' +
				escapeHtml(i18n.remove || "Eliminar") +
				'">×</button>'
		);
		parts.push('</div></div><div class="mi-cliente-nav-extra-item__body">');

		parts.push('<div class="mi-cliente-nav-extra-item__row"><label>');
		parts.push(escapeHtml(i18n.menuLabel || "Texto en menu"));
		parts.push('</label><input type="text" data-field="menu_label" value="');
		parts.push(escapeHtml(item.menu_label || ""));
		parts.push('" /></div>');

		parts.push('<div class="mi-cliente-nav-extra-item__row mi-cliente-nav-extra-item__row--inline">');
		parts.push('<input type="checkbox" data-field="show_in_nav" id="nav-extra-show-');
		parts.push(index);
		parts.push('"');
		parts.push(checkedAttr(item.show_in_nav));
		parts.push(" />");
		parts.push('<label for="nav-extra-show-');
		parts.push(index);
		parts.push('">');
		parts.push(escapeHtml(i18n.showInNav || "Mostrar en menu superior"));
		parts.push("</label></div>");

		parts.push('<div class="mi-cliente-nav-extra-item__row"><label>');
		parts.push(escapeHtml(i18n.sectionId || "Ancla (section_id)"));
		parts.push('</label><select data-field="section_id">');
		parts.push(optionsFromMap(cfg.sectionChoices, item.section_id || "tienda"));
		parts.push('</select><p class="mi-cliente-nav-extra-item__hint">');
		parts.push(
			escapeHtml(
				i18n.sectionIdHint ||
					"Debe existir un bloque con el mismo id en la portada (#entregas, #tienda, etc.)."
			)
		);
		parts.push("</p></div>");

		parts.push('<div class="mi-cliente-nav-extra-item__row"><label>');
		parts.push(escapeHtml(i18n.menuIcon || "Icono"));
		parts.push('</label><select data-field="menu_icon">');
		parts.push(optionsFromMap(cfg.iconChoices, item.menu_icon || "category"));
		parts.push("</select></div>");

		parts.push("</div></div>");

		var $el = $(parts.join(""));
		if (!$el.length) {
			return null;
		}
		$repeater.append($el);
		return $el;
	}

	function renderRepeater(control) {
		var $container = control.container;
		var $repeater = $container.find(".mi-cliente-nav-extra-repeater");
		var raw = $container.find(".mi-cliente-nav-extra-json").val();
		var items = parseItems(raw);
		if (!items.length) {
			items = parseItems(cfg.defaultItems || "[]");
		}
		$repeater.empty();
		if (!items.length) {
			return;
		}
		items.forEach(function (item, index) {
			buildItem($repeater, item, index, index === 0);
		});
	}

	function reindexTitles($repeater) {
		$repeater.find(".mi-cliente-nav-extra-item").each(function (index) {
			var $item = $(this);
			$item.attr("data-index", index);
			$item.find("[data-field=show_in_nav]").attr("id", "nav-extra-show-" + index);
			$item.find('label[for^="nav-extra-show-"]').attr("for", "nav-extra-show-" + index);
			var label = $item.find("[data-field=menu_label]").val() || "";
			var title =
				(i18n.itemLabel || "Item") +
				" " +
				(index + 1) +
				(label ? ": " + label.substring(0, 32) : "");
			$item.find(".mi-cliente-nav-extra-item__head strong").text(title);
		});
	}

	function bindControl(control) {
		if (control._miClienteNavExtraBound) {
			return;
		}
		var $container = control.container;
		var $repeater = $container.find(".mi-cliente-nav-extra-repeater");
		var $json = $container.find(".mi-cliente-nav-extra-json");
		if (!$repeater.length || !$json.length) {
			return;
		}

		renderRepeater(control);
		control._miClienteNavExtraBound = true;

		$container.on("click", ".mi-cliente-nav-extra-item__head", function (e) {
			if ($(e.target).closest("button").length) {
				return;
			}
			$(this).closest(".mi-cliente-nav-extra-item").toggleClass("is-open");
		});

		$container.on("click", ".mi-cliente-nav-extra-add", function (e) {
			e.preventDefault();
			var index = $repeater.find(".mi-cliente-nav-extra-item").length;
			buildItem($repeater, emptyItem(), index, true);
			syncJson($repeater);
			reindexTitles($repeater);
		});

		$container.on("click", "[data-remove]", function (e) {
			e.preventDefault();
			e.stopPropagation();
			$(this).closest(".mi-cliente-nav-extra-item").remove();
			reindexTitles($repeater);
			syncJson($repeater);
		});

		$container.on("click", "[data-move]", function (e) {
			e.preventDefault();
			e.stopPropagation();
			var $item = $(this).closest(".mi-cliente-nav-extra-item");
			if ($(this).data("move") === "up") {
				$item.prev(".mi-cliente-nav-extra-item").before($item);
			} else {
				$item.next(".mi-cliente-nav-extra-item").after($item);
			}
			reindexTitles($repeater);
			syncJson($repeater);
		});

		$container.on("input change", "[data-field]", function () {
			reindexTitles($repeater);
			syncJson($repeater);
		});
	}

	function initNavExtraControl() {
		var control = wp.customize.control("mi_cliente_home_nav_extra");
		if (control) {
			bindControl(control);
		}
	}

	if (wp.customize.Control) {
		wp.customize.controlConstructor.mi_cliente_home_nav_extra =
			wp.customize.Control.extend({
				ready: function () {
					var self = this;
					window.setTimeout(function () {
						bindControl(self);
					}, 0);
				},
			});
	}

	wp.customize.bind("ready", initNavExtraControl);
	wp.customize.bind("pane-ready", initNavExtraControl);

	wp.customize.bind("ready", function () {
		var section = wp.customize.section("mi_cliente_storefront");
		if (!section || !section.expanded) {
			return;
		}
		section.expanded.bind(function (isExpanded) {
			if (isExpanded) {
				initNavExtraControl();
			}
		});
		if (section.expanded.get()) {
			initNavExtraControl();
		}
	});
})(jQuery);
