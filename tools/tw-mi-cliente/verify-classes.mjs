/**
 * Verifica paridad de utilidades Tailwind:
 *   1) TODAS las clases de code.html (fuente de verdad del diseño) están en el CSS.
 *   2) Las clases de los template-parts de storefront están en el CSS.
 *
 * Uso: node verify-classes.mjs
 */
import { readFileSync, readdirSync } from "fs";
import { join } from "path";

const THEME = "../../front/ecommerce/mi-cliente.local/wp-content/themes/mi-cliente-theme";
const CSS_PATH = `${THEME}/assets/css/tailwind.css`;
const CODE_HTML = "../../templates/mi-cliente.local/code.html";
const PARTS_DIR = `${THEME}/template-parts/storefront`;

const css = readFileSync(CSS_PATH, "utf8");

// Clases no-Tailwind (markers de estado/JS) — se ignoran.
const IGNORE = new Set([
	"dsam-tw", "dsam-chrome", "dsam-home", "dsam-home-tw", "dsam-footer",
	"dsam-header-spacer", "dsam-chrome-part", "dsam-footer-part",
	"dsam-nav-link", "dsam-scroll-link", "dsam-tw-header", "dsam-floating-wa",
	"dsam-price", "dsam-price--clearance", "dsam-header__cart-icon-wrap",
	"dsam-header__cart-count", "dsam-header__cart-meta", "dsam-header__cart-label",
	"dsam-header__cart-total", "material-symbols-outlined", "scrolling-banner",
	"scrolling-content", "product-card", "product-action", "glass-nav",
]);

function escapeForCss(cls) {
	return cls.replace(/[:/\[\].%#(),]/g, (ch) => "\\" + ch);
}

function classesFromHtml(src) {
	const out = new Set();
	const re = /class="([^"]*)"/g;
	let m;
	while ((m = re.exec(src)) !== null) {
		for (const raw of m[1].split(/\s+/)) {
			const c = raw.trim();
			if (!c) continue;
			// Descarta fragmentos PHP.
			if (/[<>${}()?;']/.test(c) || c.includes("php")) continue;
			out.add(c);
		}
	}
	return out;
}

function check(label, classes) {
	const missing = [];
	for (const cls of classes) {
		if (IGNORE.has(cls) || cls.startsWith("dsam-") || cls.startsWith("wp-")) continue;
		if (!css.includes("." + escapeForCss(cls))) missing.push(cls);
	}
	missing.sort();
	console.log(`\n[${label}] clases: ${classes.size}, faltantes: ${missing.length}`);
	for (const c of missing) console.log("   - " + c);
	return missing.length;
}

let fails = 0;

// 1) code.html
fails += check("code.html", classesFromHtml(readFileSync(CODE_HTML, "utf8")));

// 2) template-parts/storefront
const partClasses = new Set();
for (const name of readdirSync(PARTS_DIR)) {
	if (!name.endsWith(".php")) continue;
	for (const c of classesFromHtml(readFileSync(join(PARTS_DIR, name), "utf8"))) {
		partClasses.add(c);
	}
}
fails += check("template-parts/storefront", partClasses);

if (fails === 0) {
	console.log("\n✓ Paridad completa: todas las utilidades del diseño están compiladas.");
	process.exit(0);
}
console.log(`\n✗ Faltan ${fails} utilidades.`);
process.exit(1);
