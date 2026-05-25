/**
 * Client scaffolding script for the WordPress Web Agency monorepo.
 *
 * Creates the directory structure for a new client in templates/ (and optionally front/),
 * validates the hostname, supports optional category subfolders, and registers the client
 * in clients.json.
 *
 * Usage:
 *   node scripts/scaffold-client.js <hostname> [options]
 *
 * Options:
 *   --category <category>  Optional category subfolder (e.g., ecommerce, portafolio, landing-page)
 *   --with-theme           Also create the front/ directory with WordPress theme structure
 *   --name <name>          Client display name (defaults to hostname)
 *
 * @module scaffold-client
 */

import { validateHostname } from './validate-hostname.js';
import { existsSync, mkdirSync, writeFileSync, readFileSync } from 'node:fs';
import { resolve, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const PROJECT_ROOT = resolve(__filename, '..', '..');

/**
 * Parses CLI arguments into a structured options object.
 *
 * @param {string[]} args - process.argv.slice(2)
 * @returns {{ hostname: string|undefined, category: string|undefined, withTheme: boolean, name: string|undefined }}
 */
export function parseArgs(args) {
  const options = {
    hostname: undefined,
    category: undefined,
    withTheme: false,
    name: undefined,
  };

  let i = 0;
  while (i < args.length) {
    const arg = args[i];

    if (arg === '--category' && i + 1 < args.length) {
      options.category = args[i + 1];
      i += 2;
    } else if (arg === '--with-theme') {
      options.withTheme = true;
      i += 1;
    } else if (arg === '--name' && i + 1 < args.length) {
      options.name = args[i + 1];
      i += 2;
    } else if (!arg.startsWith('--') && options.hostname === undefined) {
      options.hostname = arg;
      i += 1;
    } else {
      i += 1;
    }
  }

  return options;
}

/**
 * Generates the DESIGN.md template content with YAML frontmatter placeholders.
 *
 * @param {string} hostname - The client hostname
 * @param {string} displayName - The client display name
 * @returns {string}
 */
export function generateDesignMd(hostname, displayName) {
  return `---
name: "${displayName} Design System"
colors:
  primary: "#000000"
  on-primary: "#ffffff"
  secondary: "#666666"
  on-secondary: "#ffffff"
  background: "#ffffff"
  on-background: "#1a1a1a"
typography:
  display-lg:
    fontFamily: "Inter, sans-serif"
    fontSize: "3rem"
    fontWeight: "700"
    lineHeight: "1.2"
  body:
    fontFamily: "Inter, sans-serif"
    fontSize: "1rem"
    fontWeight: "400"
    lineHeight: "1.6"
spacing:
  base: "8px"
  gutter: "16px"
  margin-mobile: "16px"
  margin-desktop: "32px"
  max-width: "1280px"
rounded:
  sm: "4px"
  DEFAULT: "8px"
  md: "12px"
  lg: "16px"
  xl: "24px"
  full: "9999px"
---

# ${displayName} — Design System

> Replace the placeholder tokens above with the actual design values for this client.

## Overview

This file documents the design system tokens for **${hostname}**.

## Usage

The tokens defined in the YAML frontmatter are used by the \`transfer-tokens.js\` script
to generate the corresponding \`theme.json\` for the WordPress theme.
`;
}

/**
 * Generates the code.html template placeholder.
 *
 * @param {string} hostname - The client hostname
 * @param {string} displayName - The client display name
 * @returns {string}
 */
export function generateCodeHtml(hostname, displayName) {
  return `<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>${displayName} — Design Mockup</title>
  <style>
    /* Design mockup styles go here */
    body { font-family: sans-serif; margin: 0; padding: 2rem; }
  </style>
</head>
<body>
  <header>
    <h1>${displayName}</h1>
    <p>Hostname: ${hostname}</p>
  </header>
  <main>
    <!-- Design mockup content goes here -->
  </main>
</body>
</html>
`;
}

/**
 * Generates a 1x1 transparent PNG as a Buffer (placeholder for screen.png).
 *
 * @returns {Buffer}
 */
export function generatePlaceholderPng() {
  // Minimal valid 1x1 transparent PNG (67 bytes)
  const pngBytes = Buffer.from([
    0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a, // PNG signature
    0x00, 0x00, 0x00, 0x0d, 0x49, 0x48, 0x44, 0x52, // IHDR chunk
    0x00, 0x00, 0x00, 0x01, 0x00, 0x00, 0x00, 0x01, // 1x1
    0x08, 0x06, 0x00, 0x00, 0x00, 0x1f, 0x15, 0xc4, // RGBA, 8-bit
    0x89, 0x00, 0x00, 0x00, 0x0a, 0x49, 0x44, 0x41, // IDAT chunk
    0x54, 0x78, 0x9c, 0x62, 0x00, 0x00, 0x00, 0x02,
    0x00, 0x01, 0xe5, 0x27, 0xde, 0xfc, 0x00, 0x00, // compressed data
    0x00, 0x00, 0x49, 0x45, 0x4e, 0x44, 0xae, 0x42, // IEND chunk
    0x60, 0x82,
  ]);
  return pngBytes;
}

/**
 * Generates the WordPress theme style.css header.
 *
 * @param {string} themeName - The theme slug
 * @param {string} displayName - The client display name
 * @returns {string}
 */
export function generateStyleCss(themeName, displayName) {
  return `/*
Theme Name: ${displayName}
Theme URI: https://${themeName}
Author: Agencia Web
Author URI: https://agencia.local
Description: Custom WordPress theme for ${displayName}
Version: 1.0.0
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 8.1
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: ${themeName}
*/
`;
}

/**
 * Generates the WordPress theme functions.php with basic setup.
 *
 * @param {string} themeName - The theme slug
 * @param {string} displayName - The client display name
 * @returns {string}
 */
export function generateFunctionsPhp(themeName, displayName) {
  const textDomain = themeName;
  return `<?php
/**
 * ${displayName} Theme Functions
 *
 * @package ${themeName}
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Theme setup.
 */
function ${themeName.replace(/-/g, '_')}_setup() {
    add_theme_support( 'woocommerce' );
    add_theme_support( 'wp-block-styles' );
    add_theme_support( 'editor-styles' );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' ) );
}
add_action( 'after_setup_theme', '${themeName.replace(/-/g, '_')}_setup' );

/**
 * Enqueue theme styles.
 */
function ${themeName.replace(/-/g, '_')}_enqueue_styles() {
    wp_enqueue_style( '${textDomain}-style', get_stylesheet_uri(), array(), wp_get_theme()->get( 'Version' ) );
}
add_action( 'wp_enqueue_scripts', '${themeName.replace(/-/g, '_')}_enqueue_styles' );
`;
}

/**
 * Generates the WordPress theme.json with default tokens.
 *
 * @param {string} displayName - The client display name
 * @returns {string}
 */
export function generateThemeJson(displayName) {
  const themeJson = {
    $schema: 'https://schemas.wp.org/wp/6.5/theme.json',
    version: 2,
    settings: {
      color: {
        custom: false,
        palette: [
          { slug: 'primary', color: '#000000', name: 'Primary' },
          { slug: 'on-primary', color: '#ffffff', name: 'On Primary' },
          { slug: 'secondary', color: '#666666', name: 'Secondary' },
          { slug: 'background', color: '#ffffff', name: 'Background' },
        ],
      },
      typography: {
        customFontSize: false,
        fontFamilies: [
          { fontFamily: 'Inter, sans-serif', slug: 'body', name: 'Body' },
        ],
        fontSizes: [
          { slug: 'small', size: '14px', name: 'Small' },
          { slug: 'medium', size: '16px', name: 'Medium' },
          { slug: 'large', size: '18px', name: 'Large' },
        ],
      },
      spacing: {
        customSpacingSize: false,
        spacingSizes: [
          { slug: '10', size: '8px', name: 'Base' },
          { slug: '20', size: '16px', name: 'Small' },
          { slug: '30', size: '24px', name: 'Medium' },
        ],
      },
      layout: {
        contentSize: '1280px',
        wideSize: '1440px',
      },
    },
    styles: {
      color: { background: '#ffffff', text: '#1a1a1a' },
      typography: { fontFamily: 'var(--wp--preset--font-family--body)' },
    },
  };

  return JSON.stringify(themeJson, null, 2) + '\n';
}

/**
 * Derives a theme name slug from the hostname.
 *
 * @param {string} hostname - The client hostname
 * @returns {string} Theme slug (e.g., "mi-cliente-local-theme")
 */
export function deriveThemeName(hostname) {
  return hostname.replace(/\./g, '-') + '-theme';
}

/**
 * Builds the relative path for a client within a module.
 *
 * @param {string} module - The module name ("templates" or "front")
 * @param {string} hostname - The client hostname
 * @param {string|undefined} category - Optional category subfolder
 * @returns {string} Relative path (e.g., "templates/ecommerce/mi-cliente.local/")
 */
export function buildClientPath(module, hostname, category) {
  if (category) {
    return `${module}/${category}/${hostname}/`;
  }
  return `${module}/${hostname}/`;
}

/**
 * Reads and parses the clients.json registry file.
 *
 * @param {string} registryPath - Absolute path to clients.json
 * @returns {object} Parsed registry object
 */
export function readRegistry(registryPath) {
  const content = readFileSync(registryPath, 'utf-8');
  return JSON.parse(content);
}

/**
 * Writes the registry object back to clients.json.
 *
 * @param {string} registryPath - Absolute path to clients.json
 * @param {object} registry - The registry object to write
 */
export function writeRegistry(registryPath, registry) {
  writeFileSync(registryPath, JSON.stringify(registry, null, 2) + '\n', 'utf-8');
}

/**
 * Creates the template directory structure for a new client.
 *
 * @param {string} projectRoot - Absolute path to the project root
 * @param {string} hostname - The client hostname
 * @param {string} displayName - The client display name
 * @param {string|undefined} category - Optional category subfolder
 * @returns {string[]} List of created paths
 */
export function createTemplateStructure(projectRoot, hostname, displayName, category) {
  const createdPaths = [];
  const basePath = category
    ? join(projectRoot, 'templates', category, hostname)
    : join(projectRoot, 'templates', hostname);

  const designDir = join(basePath, 'design');
  mkdirSync(designDir, { recursive: true });
  createdPaths.push(designDir);

  // code.html
  const codeHtmlPath = join(designDir, 'code.html');
  writeFileSync(codeHtmlPath, generateCodeHtml(hostname, displayName), 'utf-8');
  createdPaths.push(codeHtmlPath);

  // DESIGN.md
  const designMdPath = join(designDir, 'DESIGN.md');
  writeFileSync(designMdPath, generateDesignMd(hostname, displayName), 'utf-8');
  createdPaths.push(designMdPath);

  // screen.png (1x1 transparent PNG placeholder)
  const screenPngPath = join(designDir, 'screen.png');
  writeFileSync(screenPngPath, generatePlaceholderPng());
  createdPaths.push(screenPngPath);

  return createdPaths;
}

/**
 * Creates the front/ directory structure with WordPress theme for a new client.
 *
 * @param {string} projectRoot - Absolute path to the project root
 * @param {string} hostname - The client hostname
 * @param {string} displayName - The client display name
 * @param {string|undefined} category - Optional category subfolder
 * @returns {string[]} List of created paths
 */
export function createFrontStructure(projectRoot, hostname, displayName, category) {
  const createdPaths = [];
  const themeName = deriveThemeName(hostname);
  const basePath = category
    ? join(projectRoot, 'front', category, hostname)
    : join(projectRoot, 'front', hostname);

  const themeDir = join(basePath, 'wp-content', 'themes', themeName);
  mkdirSync(themeDir, { recursive: true });
  createdPaths.push(themeDir);

  // style.css
  const styleCssPath = join(themeDir, 'style.css');
  writeFileSync(styleCssPath, generateStyleCss(themeName, displayName), 'utf-8');
  createdPaths.push(styleCssPath);

  // functions.php
  const functionsPhpPath = join(themeDir, 'functions.php');
  writeFileSync(functionsPhpPath, generateFunctionsPhp(themeName, displayName), 'utf-8');
  createdPaths.push(functionsPhpPath);

  // theme.json
  const themeJsonPath = join(themeDir, 'theme.json');
  writeFileSync(themeJsonPath, generateThemeJson(displayName), 'utf-8');
  createdPaths.push(themeJsonPath);

  // Empty directories: templates/, parts/, patterns/, assets/
  const emptyDirs = ['templates', 'parts', 'patterns', 'assets'];
  for (const dir of emptyDirs) {
    const dirPath = join(themeDir, dir);
    mkdirSync(dirPath, { recursive: true });
    // Add .gitkeep to preserve empty directories in git
    writeFileSync(join(dirPath, '.gitkeep'), '', 'utf-8');
    createdPaths.push(dirPath);
  }

  return createdPaths;
}

/**
 * Adds a new client entry to the clients.json registry.
 *
 * @param {string} registryPath - Absolute path to clients.json
 * @param {object} clientEntry - The client entry to add
 * @returns {object} The added client entry
 */
export function addClientToRegistry(registryPath, clientEntry) {
  const registry = readRegistry(registryPath);
  registry.clients.push(clientEntry);
  writeRegistry(registryPath, registry);
  return clientEntry;
}

/**
 * Main scaffolding function. Orchestrates validation, directory creation, and registry update.
 *
 * @param {object} options - Parsed CLI options
 * @param {string} options.hostname - The client hostname
 * @param {string|undefined} options.category - Optional category subfolder
 * @param {boolean} options.withTheme - Whether to create front/ structure
 * @param {string|undefined} options.name - Client display name
 * @param {string} [projectRoot] - Override project root (for testing)
 * @returns {{ success: boolean, error?: string, createdPaths?: string[], clientEntry?: object }}
 */
export function scaffoldClient(options, projectRoot = PROJECT_ROOT) {
  const { hostname, category, withTheme, name } = options;

  // Validate hostname
  if (!hostname) {
    return { success: false, error: 'Hostname is required. Usage: node scripts/scaffold-client.js <hostname> [options]' };
  }

  const validation = validateHostname(hostname);
  if (!validation.valid) {
    return { success: false, error: `Invalid hostname "${hostname}": ${validation.error}` };
  }

  const displayName = name || hostname;
  const registryPath = join(projectRoot, 'clients.json');

  // Check if client already exists in registry
  if (existsSync(registryPath)) {
    const registry = readRegistry(registryPath);
    const existing = registry.clients.find((c) => c.hostname === hostname);
    if (existing) {
      return { success: false, error: `Client "${hostname}" already exists in the registry.` };
    }
  }

  // Check if template directory already exists
  const templatePath = buildClientPath('templates', hostname, category);
  const absoluteTemplatePath = join(projectRoot, templatePath);
  if (existsSync(absoluteTemplatePath)) {
    return { success: false, error: `Template directory already exists: ${templatePath}` };
  }

  const createdPaths = [];

  // Create template structure
  const templatePaths = createTemplateStructure(projectRoot, hostname, displayName, category);
  createdPaths.push(...templatePaths);

  // Optionally create front structure
  let frontPath = '';
  if (withTheme) {
    frontPath = buildClientPath('front', hostname, category);
    const frontPaths = createFrontStructure(projectRoot, hostname, displayName, category);
    createdPaths.push(...frontPaths);
  }

  // Build client entry
  const today = new Date().toISOString().split('T')[0]; // YYYY-MM-DD
  const clientEntry = {
    name: displayName,
    hostname,
    category: category || '',
    status: 'diseño',
    templatePath,
    frontPath: withTheme ? frontPath : '',
    createdAt: today,
    updatedAt: today,
  };

  // Add to registry
  addClientToRegistry(registryPath, clientEntry);
  createdPaths.push(registryPath);

  return { success: true, createdPaths, clientEntry };
}

// CLI execution
const args = process.argv.slice(2);

// Only run CLI if this is the main module
if (args.length > 0 || process.argv[1]?.includes('scaffold-client')) {
  const options = parseArgs(args);
  const result = scaffoldClient(options);

  if (!result.success) {
    console.error(`✗ Error: ${result.error}`);
    process.exit(1);
  }

  console.log(`✓ Client "${result.clientEntry.hostname}" scaffolded successfully!`);
  console.log('');
  console.log('Created paths:');
  for (const p of result.createdPaths) {
    // Show relative paths for readability
    const relative = p.replace(PROJECT_ROOT, '').replace(/^[\\/]/, '');
    console.log(`  • ${relative}`);
  }
  console.log('');
  console.log('Registry entry:');
  console.log(`  Name:     ${result.clientEntry.name}`);
  console.log(`  Hostname: ${result.clientEntry.hostname}`);
  console.log(`  Category: ${result.clientEntry.category || '(none)'}`);
  console.log(`  Status:   ${result.clientEntry.status}`);
  console.log(`  Template: ${result.clientEntry.templatePath}`);
  if (result.clientEntry.frontPath) {
    console.log(`  Front:    ${result.clientEntry.frontPath}`);
  }
}
