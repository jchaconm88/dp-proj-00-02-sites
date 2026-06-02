/**
 * Tailwind config — réplica EXACTA de templates/mi-cliente.local/code.html
 *
 * Compila el CSS estático para el tema WordPress mi-cliente-theme.
 * Todo va bajo `extend` (igual que el mockup): preserva la escala por defecto
 * de Tailwind y añade los tokens del diseño D.Sam / Industrial Vitality.
 *
 * IMPORTANTE: `corePlugins.preflight` está deshabilitado para no resetear
 * estilos globales de WooCommerce / editor de bloques. El reset preflight se
 * aplica de forma acotada a los contenedores `.dsam-tw` en src.css.
 */

const THEME = "../../front/ecommerce/mi-cliente.local/wp-content/themes/mi-cliente-theme";

/** @type {import('tailwindcss').Config} */
module.exports = {
	darkMode: "class",
	corePlugins: {
		preflight: false,
	},
	content: [
		// Fuente de verdad de clases: el mockup original.
		"../../templates/mi-cliente.local/code.html",
		// Plantillas y render del tema (PHP + HTML de bloques).
		`${THEME}/**/*.php`,
		`${THEME}/**/*.html`,
	],
	theme: {
		extend: {
			colors: {
				"surface-tint": "#006e01",
				"tertiary-container": "#a3a3a3",
				"inverse-surface": "#2f3131",
				"surface-bright": "#f9f9f9",
				tertiary: "#5e5e5e",
				outline: "#6d7b66",
				"on-primary-fixed-variant": "#005301",
				"surface-container-highest": "#e2e2e2",
				"secondary-fixed": "#e1dfff",
				"on-primary": "#ffffff",
				"soft-gray": "#F8F9FA",
				secondary: "#585894",
				"on-primary-container": "#004400",
				"on-error": "#ffffff",
				"on-secondary-container": "#484983",
				"inverse-primary": "#4de33e",
				"on-tertiary-fixed": "#1b1b1b",
				"tertiary-fixed-dim": "#c6c6c6",
				"tertiary-fixed": "#e2e2e2",
				"on-tertiary-container": "#393939",
				"on-background": "#1a1c1c",
				"on-surface": "#1a1c1c",
				"surface-container": "#eeeeee",
				"on-secondary-fixed": "#13134d",
				"surface-container-high": "#e8e8e8",
				"on-tertiary-fixed-variant": "#474747",
				"outline-variant": "#bccbb3",
				surface: "#f9f9f9",
				"on-primary-fixed": "#002200",
				"primary-fixed": "#77ff62",
				"primary-fixed-dim": "#4de33e",
				"on-secondary": "#ffffff",
				"surface-dim": "#dadada",
				"surface-container-lowest": "#ffffff",
				"on-surface-variant": "#3d4a38",
				"secondary-fixed-dim": "#c1c1ff",
				"primary-container": "#19bd15",
				"error-container": "#ffdad6",
				"secondary-container": "#bbbbfe",
				"on-tertiary": "#ffffff",
				"vibrant-green": "#19BD15",
				"inverse-on-surface": "#f0f1f1",
				error: "#ba1a1a",
				"deep-navy": "#1B1B54",
				"surface-variant": "#e2e2e2",
				primary: "#006e01",
				"on-error-container": "#93000a",
				background: "#f9f9f9",
				"alert-red": "#F50000",
				"on-secondary-fixed-variant": "#40417a",
				"surface-container-low": "#f3f3f4",
			},
			borderRadius: {
				DEFAULT: "0.125rem",
				lg: "0.25rem",
				xl: "0.5rem",
				full: "0.75rem",
			},
			spacing: {
				"margin-mobile": "16px",
				"margin-desktop": "auto",
				"max-width": "1280px",
				gutter: "24px",
				base: "8px",
			},
			fontFamily: {
				"label-md": ["Open Sans"],
				"headline-md": ["IBM Plex Serif"],
				"label-lg": ["Open Sans"],
				"headline-lg": ["IBM Plex Serif"],
				"body-sm": ["Open Sans"],
				"headline-lg-mobile": ["IBM Plex Serif"],
				"body-md": ["Open Sans"],
				"body-lg": ["Open Sans"],
				"display-lg": ["IBM Plex Serif"],
			},
			fontSize: {
				"label-md": ["12px", { lineHeight: "16px", fontWeight: "600" }],
				"headline-md": ["28px", { lineHeight: "36px", fontWeight: "600" }],
				"label-lg": ["14px", { lineHeight: "16px", letterSpacing: "0.05em", fontWeight: "700" }],
				"headline-lg": ["40px", { lineHeight: "48px", fontWeight: "600" }],
				"body-sm": ["14px", { lineHeight: "20px", fontWeight: "400" }],
				"headline-lg-mobile": ["32px", { lineHeight: "40px", fontWeight: "600" }],
				"body-md": ["16px", { lineHeight: "24px", fontWeight: "400" }],
				"body-lg": ["18px", { lineHeight: "28px", fontWeight: "400" }],
				"display-lg": ["56px", { lineHeight: "64px", letterSpacing: "-0.02em", fontWeight: "700" }],
			},
		},
	},
	plugins: [
		require("@tailwindcss/forms"),
		require("@tailwindcss/container-queries"),
	],
};
