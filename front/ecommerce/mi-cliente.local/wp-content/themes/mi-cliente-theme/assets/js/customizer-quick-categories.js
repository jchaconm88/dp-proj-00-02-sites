/**
 * Customizer — repetidor de categorías rápidas (portada).
 *
 * @package Mi_Cliente_Theme
 */
(function ($) {
	"use strict";

	var cfg = window.miClienteQuickCategoriesCustomizer || {};
	cfg.variantChoices = cfg.variantChoices || {};
	cfg.sectionChoices = cfg.sectionChoices || {};
	cfg.iconChoices = cfg.iconChoices || {};
	cfg.productCategories = cfg.productCategories || {};
	cfg.attachmentUrls = cfg.attachmentUrls || {};
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
			label: "",
			variant: "image",
			section_id: "tienda",
			category: "",
			image: 0,
			image_url: "",
			icon: "category",
			promo_line1: "2x150 y 3x210",
			promo_line2: "+ ENVIO GRATIS",
			icon_caption: "COMENTARIOS\nENTREGAS",
			url: "",
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

	function collectItem($item) {
		var variant = $item.find("[data-field=variant]").val() || "image";
		var row = {
			label: $item.find("[data-field=label]").val() || "",
			variant: variant,
			section_id: $item.find("[data-field=section_id]").val() || "",
		};
		var url = $item.find("[data-field=url]").val() || "";
		if (url) {
			row.url = url;
		}
		if (variant === "image") {
			row.category = $item.find("[data-field=category]").val() || "";
			row.image = parseInt($item.find("[data-field=image]").val(), 10) || 0;
			var imageUrl = $item.find("[data-field=image_url]").val() || "";
			if (imageUrl) {
				row.image_url = imageUrl;
			}
			var icon = $item.find("[data-field=icon]").val() || "";
			if (icon) {
				row.icon = icon;
			}
		} else if (variant === "promo") {
			row.promo_line1 = $item.find("[data-field=promo_line1]").val() || "";
			row.promo_line2 = $item.find("[data-field=promo_line2]").val() || "";
		} else if (variant === "icon") {
			row.icon = $item.find("[data-field=icon]").val() || "chat";
			row.icon_caption = $item.find("[data-field=icon_caption]").val() || "";
		} else if (variant === "clearance") {
			row.category = $item.find("[data-field=category]").val() || "liquidacion";
		}
		return row;
	}

	function syncJson($repeater) {
		var items = [];
		$repeater.find(".mi-cliente-quick-cat").each(function () {
			items.push(collectItem($(this)));
		});
		$repeater
			.closest(".customize-control")
			.find(".mi-cliente-quick-categories-json")
			.val(JSON.stringify(items))
			.trigger("change");
	}

	function togglePanels($item) {
		var variant = $item.find("[data-field=variant]").val() || "image";
		$item.find("[data-variant-panel]").each(function () {
			var panels = ($(this).data("variant-panel") || "")
				.toString()
				.split(/\s+/);
			$(this).toggleClass(
				"is-visible",
				panels.indexOf(variant) !== -1 || panels.indexOf("all") !== -1
			);
		});
	}

	function updatePreview($item) {
		var id = parseInt($item.find("[data-field=image]").val(), 10) || 0;
		var urlField = $item.find("[data-field=image_url]").val() || "";
		var $img = $item.find(".mi-cliente-quick-cat__preview");
		var url = "";
		if (id && cfg.attachmentUrls[id]) {
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

	function buildItem($repeater, item, index, open) {
		var variant = item.variant || "image";
		var parts = [];
		parts.push('<div class="mi-cliente-quick-cat');
		if (open) {
			parts.push(" is-open");
		}
		parts.push('" data-index="');
		parts.push(index);
		parts.push('"><div class="mi-cliente-quick-cat__head"><strong>');
		parts.push(
			escapeHtml(
				(i18n.itemLabel || "Categoria") +
					" " +
					(index + 1) +
					(item.label ? ": " + item.label.substring(0, 32) : "")
			)
		);
		parts.push('</strong><div class="mi-cliente-quick-cat__head-actions">');
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
		parts.push('</div></div><div class="mi-cliente-quick-cat__body">');

		parts.push('<div class="mi-cliente-quick-cat__row"><label>');
		parts.push(escapeHtml(i18n.label || "Nombre visible"));
		parts.push('</label><input type="text" data-field="label" value="');
		parts.push(escapeHtml(item.label || ""));
		parts.push('" /></div>');

		parts.push('<div class="mi-cliente-quick-cat__row"><label>');
		parts.push(escapeHtml(i18n.variant || "Tipo de circulo"));
		parts.push('</label><select data-field="variant">');
		parts.push(optionsFromMap(cfg.variantChoices, variant));
		parts.push("</select></div>");

		parts.push('<div class="mi-cliente-quick-cat__row" data-variant-panel="all"><label>');
		parts.push(escapeHtml(i18n.sectionId || "Enlace a seccion"));
		parts.push('</label><select data-field="section_id">');
		parts.push(optionsFromMap(cfg.sectionChoices, item.section_id || "tienda"));
		parts.push("</select></div>");

		parts.push('<div class="mi-cliente-quick-cat__row" data-variant-panel="all"><label>');
		parts.push(escapeHtml(i18n.url || "URL alternativa (opcional)"));
		parts.push('</label><input type="url" data-field="url" placeholder="https://..." value="');
		parts.push(escapeHtml(item.url || ""));
		parts.push('" /><p class="mi-cliente-quick-cat__hint">');
		parts.push(escapeHtml(i18n.urlHint || "Si se deja vacio, usa el enlace de la seccion."));
		parts.push("</p></div>");

		parts.push('<div class="mi-cliente-quick-cat__panel" data-variant-panel="image clearance"><div class="mi-cliente-quick-cat__row"><label>');
		parts.push(escapeHtml(i18n.category || "Slug categoria WooCommerce"));
		parts.push('</label><input type="text" data-field="category" list="mi-cliente-wc-cats" value="');
		parts.push(escapeHtml(item.category || ""));
		parts.push('" /></div></div>');

		parts.push('<div class="mi-cliente-quick-cat__panel" data-variant-panel="image"><div class="mi-cliente-quick-cat__row"><label>');
		parts.push(escapeHtml(i18n.image || "Imagen del circulo"));
		parts.push('</label><div class="mi-cliente-quick-cat__media"><img class="mi-cliente-quick-cat__preview" alt="" /><input type="hidden" data-field="image" value="');
		parts.push(parseInt(item.image, 10) || 0);
		parts.push('" /><button type="button" class="button" data-select-image>');
		parts.push(escapeHtml(i18n.selectImage || "Elegir imagen"));
		parts.push('</button><button type="button" class="button" data-clear-image>');
		parts.push(escapeHtml(i18n.clearImage || "Quitar"));
		parts.push('</button></div><p class="mi-cliente-quick-cat__hint">');
		parts.push(escapeHtml(i18n.imageHint || "O URL externa. Si hay slug de categoria, puede usarse la miniatura de WooCommerce."));
		parts.push('</p><input type="url" data-field="image_url" placeholder="https://..." value="');
		parts.push(escapeHtml(item.image_url || ""));
		parts.push('" /></div><div class="mi-cliente-quick-cat__row"><label>');
		parts.push(escapeHtml(i18n.fallbackIcon || "Icono si no hay imagen"));
		parts.push('</label><select data-field="icon">');
		parts.push(optionsFromMap(cfg.iconChoices, item.icon || "category"));
		parts.push("</select></div></div>");

		parts.push('<div class="mi-cliente-quick-cat__panel" data-variant-panel="promo"><div class="mi-cliente-quick-cat__row"><label>');
		parts.push(escapeHtml(i18n.promoLine1 || "Linea promo 1"));
		parts.push('</label><input type="text" data-field="promo_line1" value="');
		parts.push(escapeHtml(item.promo_line1 || ""));
		parts.push('" /></div><div class="mi-cliente-quick-cat__row"><label>');
		parts.push(escapeHtml(i18n.promoLine2 || "Linea promo 2"));
		parts.push('</label><input type="text" data-field="promo_line2" value="');
		parts.push(escapeHtml(item.promo_line2 || ""));
		parts.push('" /></div></div>');

		parts.push('<div class="mi-cliente-quick-cat__panel" data-variant-panel="icon"><div class="mi-cliente-quick-cat__row"><label>');
		parts.push(escapeHtml(i18n.icon || "Icono"));
		parts.push('</label><select data-field="icon">');
		parts.push(optionsFromMap(cfg.iconChoices, item.icon || "chat"));
		parts.push('</select></div><div class="mi-cliente-quick-cat__row"><label>');
		parts.push(escapeHtml(i18n.iconCaption || "Texto bajo el icono"));
		parts.push('</label><textarea data-field="icon_caption" rows="2">');
		parts.push(escapeHtml(item.icon_caption || ""));
		parts.push("</textarea><p class=\"mi-cliente-quick-cat__hint\">");
		parts.push(escapeHtml(i18n.iconCaptionHint || "Enter para segunda linea."));
		parts.push("</p></div></div>");

		parts.push("</div></div>");

		var $el = $(parts.join(""));
		if (!$el.length) {
			return null;
		}
		$repeater.append($el);
		togglePanels($el);
		updatePreview($el);
		return $el;
	}

	function renderRepeater(control) {
		var $container = control.container;
		var $repeater = $container.find(".mi-cliente-quick-categories-repeater");
		var raw = $container.find(".mi-cliente-quick-categories-json").val();
		var items = parseItems(raw);
		if (!items.length) {
			items = parseItems(cfg.defaultItems || "[]");
		}
		if (!items.length) {
			items = [emptyItem()];
		}
		$repeater.empty();
		items.forEach(function (item, index) {
			buildItem($repeater, item, index, index === 0);
		});
	}

	function reindexTitles($repeater) {
		$repeater.find(".mi-cliente-quick-cat").each(function (index) {
			var $item = $(this);
			$item.attr("data-index", index);
			var label = $item.find("[data-field=label]").val() || "";
			var title =
				(i18n.itemLabel || "Categoria") +
				" " +
				(index + 1) +
				(label ? ": " + label.substring(0, 32) : "");
			$item.find(".mi-cliente-quick-cat__head strong").text(title);
		});
	}

	function bindControl(control) {
		if (control._miClienteQuickCatsBound) {
			return;
		}
		var $container = control.container;
		var $repeater = $container.find(".mi-cliente-quick-categories-repeater");
		var $json = $container.find(".mi-cliente-quick-categories-json");
		if (!$repeater.length || !$json.length) {
			return;
		}

		if (!$("#mi-cliente-wc-cats").length && cfg.productCategories) {
			var $datalist = $('<datalist id="mi-cliente-wc-cats"></datalist>');
			$.each(cfg.productCategories, function (slug, name) {
				$datalist.append($("<option>").attr("value", slug).text(name));
			});
			$("body").append($datalist);
		}

		renderRepeater(control);
		control._miClienteQuickCatsBound = true;

		$container.on("click", ".mi-cliente-quick-cat__head", function (e) {
			if ($(e.target).closest("button").length) {
				return;
			}
			$(this).closest(".mi-cliente-quick-cat").toggleClass("is-open");
		});

		$container.on("click", ".mi-cliente-quick-categories-add", function (e) {
			e.preventDefault();
			var index = $repeater.find(".mi-cliente-quick-cat").length;
			buildItem($repeater, emptyItem(), index, true);
			syncJson($repeater);
			reindexTitles($repeater);
		});

		$container.on("click", "[data-remove]", function (e) {
			e.preventDefault();
			e.stopPropagation();
			if ($repeater.find(".mi-cliente-quick-cat").length <= 1) {
				window.alert(i18n.minItems || "Debe haber al menos una categoria.");
				return;
			}
			$(this).closest(".mi-cliente-quick-cat").remove();
			reindexTitles($repeater);
			syncJson($repeater);
		});

		$container.on("click", "[data-move]", function (e) {
			e.preventDefault();
			e.stopPropagation();
			var $item = $(this).closest(".mi-cliente-quick-cat");
			if ($(this).data("move") === "up") {
				$item.prev(".mi-cliente-quick-cat").before($item);
			} else {
				$item.next(".mi-cliente-quick-cat").after($item);
			}
			reindexTitles($repeater);
			syncJson($repeater);
		});

		$container.on("input change", "[data-field]", function () {
			var $item = $(this).closest(".mi-cliente-quick-cat");
			if ($(this).is("[data-field=variant]")) {
				togglePanels($item);
			}
			if (
				$(this).is("[data-field=image]") ||
				$(this).is("[data-field=image_url]")
			) {
				updatePreview($item);
			}
			reindexTitles($repeater);
			syncJson($repeater);
		});

		$container.on("click", "[data-select-image]", function (e) {
			e.preventDefault();
			var $item = $(this).closest(".mi-cliente-quick-cat");
			var frame = wp.media({
				title: i18n.selectImage || "Elegir imagen",
				button: { text: i18n.useImage || "Usar imagen" },
				library: { type: "image" },
				multiple: false,
			});
			frame.on("select", function () {
				var attachment = frame.state().get("selection").first().toJSON();
				$item.find("[data-field=image]").val(attachment.id);
				cfg.attachmentUrls[attachment.id] =
					attachment.sizes && attachment.sizes.thumbnail
						? attachment.sizes.thumbnail.url
						: attachment.url;
				updatePreview($item);
				syncJson($repeater);
			});
			frame.open();
		});

		$container.on("click", "[data-clear-image]", function (e) {
			e.preventDefault();
			var $item = $(this).closest(".mi-cliente-quick-cat");
			$item.find("[data-field=image]").val("0");
			updatePreview($item);
			syncJson($repeater);
		});
	}

	function initQuickCategoriesControl() {
		var control = wp.customize.control("mi_cliente_quick_categories");
		if (control) {
			bindControl(control);
		}
	}

	if (wp.customize.Control) {
		wp.customize.controlConstructor.mi_cliente_quick_categories =
			wp.customize.Control.extend({
				ready: function () {
					var self = this;
					window.setTimeout(function () {
						bindControl(self);
					}, 0);
				},
			});
	}

	wp.customize.bind("ready", initQuickCategoriesControl);
	wp.customize.bind("pane-ready", initQuickCategoriesControl);

	wp.customize.bind("ready", function () {
		var section = wp.customize.section("mi_cliente_storefront");
		if (!section || !section.expanded) {
			return;
		}
		section.expanded.bind(function (isExpanded) {
			if (isExpanded) {
				initQuickCategoriesControl();
			}
		});
		if (section.expanded.get()) {
			initQuickCategoriesControl();
		}
	});
})(jQuery);
