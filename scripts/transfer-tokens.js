/**
 * Token transfer script: DESIGN.md → theme.json
 *
 * Reads YAML frontmatter from a DESIGN.md file and maps design tokens
 * to the WordPress theme.json structure.
 *
 * Mapping:
 *   colors.*                → settings.color.palette[].color
 *   typography.*.fontFamily → settings.typography.fontFamilies[].fontFamily
 *   typography.*.fontSize   → settings.typography.fontSizes[].size
 *   spacing.*               → settings.spacing.spacingSizes[].size
 *   rounded.*               → styles.blocks.*.border.radius (custom property)
 *
 * Usage:
 *   node scripts/transfer-tokens.js <designMdPath> <themeJsonPath> [--overwrite]
 *
 * @module transfer-tokens
 */

import { readFileSync, writeFileSync, accessSync, constants } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { parse as parseYaml } from 'yaml';

/**
 * Parses YAML frontmatter from a markdown file content string.
 *
 * @param {string} content - The full markdown file content
 * @returns {{ tokens: object|null, error: string|null }}
 */
export function parseFrontmatter(content) {
  const match = content.match(/^---\r?\n([\s\S]*?)\r?\n---/);
  if (!match) {
    return { tokens: null, error: 'No YAML frontmatter found (expected --- delimiters)' };
  }

  try {
    const tokens = parseYaml(match[1]);
    if (!tokens || typeof tokens !== 'object') {
      return { tokens: null, error: 'YAML frontmatter parsed but is not an object' };
    }
    return { tokens, error: null };
  } catch (err) {
    return { tokens: null, error: `YAML parse error: ${err.message}` };
  }
}

/**
 * Converts a slug key to a human-readable name.
 * e.g., "on-primary" → "On Primary", "display-lg" → "Display Lg"
 *
 * @param {string} slug - The slug to convert
 * @returns {string}
 */
export function slugToName(slug) {
  return slug
    .split('-')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

/**
 * Maps color tokens from DESIGN.md to theme.json palette entries.
 *
 * @param {object} colors - The colors object from YAML frontmatter
 * @returns {{ palette: Array<{slug: string, color: string, name: string}>, count: number, warnings: string[] }}
 */
export function mapColors(colors) {
  const palette = [];
  const warnings = [];

  if (!colors || typeof colors !== 'object') {
    return { palette, count: 0, warnings: ['No colors section found in DESIGN.md'] };
  }

  for (const [key, value] of Object.entries(colors)) {
    if (typeof value !== 'string') {
      warnings.push(`Color "${key}" has non-string value, skipping`);
      continue;
    }
    palette.push({
      slug: key,
      color: value,
      name: slugToName(key),
    });
  }

  return { palette, count: palette.length, warnings };
}

/**
 * Maps typography tokens from DESIGN.md to theme.json fontFamilies and fontSizes.
 *
 * @param {object} typography - The typography object from YAML frontmatter
 * @returns {{ fontFamilies: Array, fontSizes: Array, count: number, warnings: string[] }}
 */
export function mapTypography(typography) {
  const fontFamilies = [];
  const fontSizes = [];
  const warnings = [];
  const seenFamilies = new Set();

  if (!typography || typeof typography !== 'object') {
    return { fontFamilies, fontSizes, count: 0, warnings: ['No typography section found in DESIGN.md'] };
  }

  for (const [key, value] of Object.entries(typography)) {
    if (!value || typeof value !== 'object') {
      warnings.push(`Typography entry "${key}" is not an object, skipping`);
      continue;
    }

    // Extract fontFamily (deduplicate)
    if (value.fontFamily && typeof value.fontFamily === 'string') {
      if (!seenFamilies.has(value.fontFamily)) {
        seenFamilies.add(value.fontFamily);
        fontFamilies.push({
          fontFamily: value.fontFamily,
          slug: key,
          name: slugToName(key),
        });
      }
    }

    // Extract fontSize
    if (value.fontSize && typeof value.fontSize === 'string') {
      fontSizes.push({
        slug: key,
        size: value.fontSize,
        name: slugToName(key),
      });
    }
  }

  return {
    fontFamilies,
    fontSizes,
    count: fontFamilies.length + fontSizes.length,
    warnings,
  };
}

/**
 * Maps spacing tokens from DESIGN.md to theme.json spacingSizes.
 *
 * @param {object} spacing - The spacing object from YAML frontmatter
 * @returns {{ spacingSizes: Array<{slug: string, size: string, name: string}>, count: number, warnings: string[] }}
 */
export function mapSpacing(spacing) {
  const spacingSizes = [];
  const warnings = [];

  if (!spacing || typeof spacing !== 'object') {
    return { spacingSizes, count: 0, warnings: ['No spacing section found in DESIGN.md'] };
  }

  let index = 10;
  for (const [key, value] of Object.entries(spacing)) {
    if (typeof value !== 'string') {
      warnings.push(`Spacing "${key}" has non-string value, skipping`);
      continue;
    }
    spacingSizes.push({
      slug: String(index),
      size: value,
      name: slugToName(key),
    });
    index += 10;
  }

  return { spacingSizes, count: spacingSizes.length, warnings };
}

/**
 * Maps rounded tokens from DESIGN.md to theme.json custom properties for border radius.
 *
 * @param {object} rounded - The rounded object from YAML frontmatter
 * @returns {{ borderRadius: object, count: number, warnings: string[] }}
 */
export function mapRounded(rounded) {
  const borderRadius = {};
  const warnings = [];

  if (!rounded || typeof rounded !== 'object') {
    return { borderRadius, count: 0, warnings: ['No rounded section found in DESIGN.md'] };
  }

  for (const [key, value] of Object.entries(rounded)) {
    if (typeof value !== 'string') {
      warnings.push(`Rounded "${key}" has non-string value, skipping`);
      continue;
    }
    borderRadius[key] = value;
  }

  return { borderRadius, count: Object.keys(borderRadius).length, warnings };
}

/**
 * Builds the complete theme.json object from mapped tokens.
 *
 * @param {object} params
 * @param {Array} params.palette - Color palette entries
 * @param {Array} params.fontFamilies - Font family entries
 * @param {Array} params.fontSizes - Font size entries
 * @param {Array} params.spacingSizes - Spacing size entries
 * @param {object} params.borderRadius - Border radius map
 * @param {object|null} params.existingThemeJson - Existing theme.json to merge with (if overwrite)
 * @param {boolean} params.overwrite - Whether to overwrite existing values
 * @returns {object} The complete theme.json object
 */
export function buildThemeJson({ palette, fontFamilies, fontSizes, spacingSizes, borderRadius, existingThemeJson, overwrite }) {
  let themeJson;

  if (existingThemeJson && !overwrite) {
    // Merge with existing — only add new values, don't overwrite
    themeJson = JSON.parse(JSON.stringify(existingThemeJson));
  } else {
    // Start fresh or overwrite
    themeJson = {
      $schema: 'https://schemas.wp.org/wp/6.5/theme.json',
      version: 2,
    };
  }

  // Ensure settings structure exists
  if (!themeJson.settings) themeJson.settings = {};

  // Color settings
  if (!themeJson.settings.color) themeJson.settings.color = {};
  themeJson.settings.color.custom = false;
  if (palette.length > 0) {
    themeJson.settings.color.palette = palette;
  }

  // Typography settings
  if (!themeJson.settings.typography) themeJson.settings.typography = {};
  themeJson.settings.typography.customFontSize = false;
  if (fontFamilies.length > 0) {
    themeJson.settings.typography.fontFamilies = fontFamilies;
  }
  if (fontSizes.length > 0) {
    themeJson.settings.typography.fontSizes = fontSizes;
  }

  // Spacing settings
  if (!themeJson.settings.spacing) themeJson.settings.spacing = {};
  themeJson.settings.spacing.customSpacingSize = false;
  if (spacingSizes.length > 0) {
    themeJson.settings.spacing.spacingSizes = spacingSizes;
  }

  // Border radius — store as custom properties in settings.custom
  if (Object.keys(borderRadius).length > 0) {
    if (!themeJson.settings.custom) themeJson.settings.custom = {};
    themeJson.settings.custom.borderRadius = borderRadius;
  }

  return themeJson;
}

/**
 * Main token transfer function.
 *
 * @param {object} input
 * @param {string} input.designMdPath - Path to the DESIGN.md file
 * @param {string} input.themeJsonPath - Path to the theme.json destination
 * @param {boolean} [input.overwrite=true] - Whether to overwrite existing theme.json values
 * @returns {{ success: boolean, tokensTransferred: number, warnings: string[], errors: string[] }}
 */
export function transferTokens({ designMdPath, themeJsonPath, overwrite = true }) {
  const warnings = [];
  const errors = [];

  // 1. Read DESIGN.md
  let designContent;
  try {
    designContent = readFileSync(designMdPath, 'utf-8');
  } catch (err) {
    errors.push(`Cannot read DESIGN.md at "${designMdPath}": ${err.message}`);
    return { success: false, tokensTransferred: 0, warnings, errors };
  }

  // 2. Parse YAML frontmatter
  const { tokens, error: parseError } = parseFrontmatter(designContent);
  if (parseError) {
    errors.push(`Failed to parse DESIGN.md frontmatter: ${parseError}`);
    return { success: false, tokensTransferred: 0, warnings, errors };
  }

  // 3. Check theme.json writability before making changes
  //    If the file exists, check write access. If it doesn't exist, check parent dir.
  let existingThemeJson = null;
  try {
    const existingContent = readFileSync(themeJsonPath, 'utf-8');
    existingThemeJson = JSON.parse(existingContent);
    // Verify we can write to it
    accessSync(themeJsonPath, constants.W_OK);
  } catch (err) {
    if (err.code === 'ENOENT') {
      // File doesn't exist — that's fine, we'll create it
      // Check if we can write to the directory
      try {
        accessSync(dirname(themeJsonPath), constants.W_OK);
      } catch (dirErr) {
        errors.push(`Cannot write theme.json — directory not writable: "${dirname(themeJsonPath)}": ${dirErr.message}`);
        return { success: false, tokensTransferred: 0, warnings, errors };
      }
    } else if (err.code === 'EACCES') {
      errors.push(`Cannot write to theme.json at "${themeJsonPath}": Permission denied`);
      return { success: false, tokensTransferred: 0, warnings, errors };
    } else if (err instanceof SyntaxError) {
      // JSON parse error — existing file is malformed
      warnings.push(`Existing theme.json has invalid JSON, will be overwritten`);
      existingThemeJson = null;
    }
    // Other read errors for non-existent file are fine
  }

  // 4. Map tokens
  const colorResult = mapColors(tokens.colors);
  const typographyResult = mapTypography(tokens.typography);
  const spacingResult = mapSpacing(tokens.spacing);
  const roundedResult = mapRounded(tokens.rounded);

  warnings.push(...colorResult.warnings);
  warnings.push(...typographyResult.warnings);
  warnings.push(...spacingResult.warnings);
  warnings.push(...roundedResult.warnings);

  const totalTokens = colorResult.count + typographyResult.count + spacingResult.count + roundedResult.count;

  if (totalTokens === 0) {
    errors.push('No tokens found in DESIGN.md frontmatter (expected colors, typography, spacing, or rounded sections)');
    return { success: false, tokensTransferred: 0, warnings, errors };
  }

  // 5. Build theme.json
  const themeJson = buildThemeJson({
    palette: colorResult.palette,
    fontFamilies: typographyResult.fontFamilies,
    fontSizes: typographyResult.fontSizes,
    spacingSizes: spacingResult.spacingSizes,
    borderRadius: roundedResult.borderRadius,
    existingThemeJson,
    overwrite,
  });

  // 6. Write theme.json atomically (all or nothing)
  try {
    writeFileSync(themeJsonPath, JSON.stringify(themeJson, null, 2) + '\n', 'utf-8');
  } catch (err) {
    errors.push(`Failed to write theme.json at "${themeJsonPath}": ${err.message}`);
    return { success: false, tokensTransferred: 0, warnings, errors };
  }

  return { success: true, tokensTransferred: totalTokens, warnings, errors };
}

// CLI support: node scripts/transfer-tokens.js <designMdPath> <themeJsonPath> [--overwrite]
const __filename = fileURLToPath(import.meta.url);
const scriptArg = process.argv[1] ? resolve(process.argv[1]) : '';

if (__filename === scriptArg) {
  const args = process.argv.slice(2);
  const designMdPath = args[0];
  const themeJsonPath = args[1];
  const overwrite = args.includes('--overwrite') || !args.includes('--no-overwrite');

  if (!designMdPath || !themeJsonPath) {
    console.error('Usage: node scripts/transfer-tokens.js <designMdPath> <themeJsonPath> [--no-overwrite]');
    process.exit(1);
  }

  const result = transferTokens({
    designMdPath: resolve(designMdPath),
    themeJsonPath: resolve(themeJsonPath),
    overwrite,
  });

  if (result.success) {
    console.log(`✓ Token transfer complete: ${result.tokensTransferred} tokens transferred`);
  } else {
    console.error('✗ Token transfer failed:');
    for (const err of result.errors) {
      console.error(`  • ${err}`);
    }
  }

  if (result.warnings.length > 0) {
    console.log('Warnings:');
    for (const warn of result.warnings) {
      console.log(`  ⚠ ${warn}`);
    }
  }

  process.exit(result.success ? 0 : 1);
}
