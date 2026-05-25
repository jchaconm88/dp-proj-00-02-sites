/**
 * validate-tokens.js
 *
 * Detects discrepancies between design tokens defined in DESIGN.md (YAML frontmatter)
 * and the corresponding values in theme.json.
 *
 * Exports:
 *   validateTokens({ designMdPath, themeJsonPath }) → { result, discrepancies, totalTokens, matchedTokens }
 *
 * CLI:
 *   node scripts/validate-tokens.js <designMdPath> <themeJsonPath>
 *
 * Requirements: 11.4
 */

import { readFileSync } from "node:fs";
import { parse as parseYaml } from "yaml";

/**
 * Extract YAML frontmatter from a markdown file.
 * Frontmatter is delimited by --- at the start and end.
 */
function extractFrontmatter(content) {
  const match = content.match(/^---\r?\n([\s\S]*?)\r?\n---/);
  if (!match) return null;
  return parseYaml(match[1]);
}

/**
 * Validate design tokens from DESIGN.md against theme.json values.
 *
 * @param {{ designMdPath: string, themeJsonPath: string }} options
 * @returns {{ result: "PASS"|"FAIL", discrepancies: Array<{token: string, expected: string, actual: string|null}>, totalTokens: number, matchedTokens: number }}
 */
export function validateTokens({ designMdPath, themeJsonPath }) {
  const designContent = readFileSync(designMdPath, "utf-8");
  const themeContent = readFileSync(themeJsonPath, "utf-8");

  const frontmatter = extractFrontmatter(designContent);
  if (!frontmatter) {
    return {
      result: "FAIL",
      discrepancies: [{ token: "frontmatter", expected: "valid YAML frontmatter", actual: null }],
      totalTokens: 0,
      matchedTokens: 0,
    };
  }

  const themeJson = JSON.parse(themeContent);
  const discrepancies = [];
  let totalTokens = 0;

  // --- Colors ---
  if (frontmatter.colors && typeof frontmatter.colors === "object") {
    const palette = themeJson?.settings?.color?.palette ?? [];
    for (const [slug, expectedColor] of Object.entries(frontmatter.colors)) {
      totalTokens++;
      const entry = palette.find((p) => p.slug === slug);
      const actual = entry?.color ?? null;
      if (normalizeColor(actual) !== normalizeColor(expectedColor)) {
        discrepancies.push({
          token: `colors.${slug}`,
          expected: String(expectedColor),
          actual,
        });
      }
    }
  }

  // --- Typography: fontFamily ---
  if (frontmatter.typography && typeof frontmatter.typography === "object") {
    const fontFamilies = themeJson?.settings?.typography?.fontFamilies ?? [];
    const fontSizes = themeJson?.settings?.typography?.fontSizes ?? [];

    for (const [key, value] of Object.entries(frontmatter.typography)) {
      if (value && typeof value === "object") {
        // fontFamily
        if (value.fontFamily) {
          totalTokens++;
          const entry = fontFamilies.find(
            (f) => f.slug === key || f.name === key || normalizeFontFamily(f.fontFamily) === normalizeFontFamily(value.fontFamily)
          );
          const actual = entry?.fontFamily ?? null;
          if (normalizeFontFamily(actual) !== normalizeFontFamily(value.fontFamily)) {
            discrepancies.push({
              token: `typography.${key}.fontFamily`,
              expected: String(value.fontFamily),
              actual,
            });
          }
        }

        // fontSize
        if (value.fontSize) {
          totalTokens++;
          const entry = fontSizes.find(
            (f) => f.slug === key || f.name === key || normalizeFontSize(f.size) === normalizeFontSize(value.fontSize)
          );
          const actual = entry?.size ?? null;
          if (normalizeFontSize(actual) !== normalizeFontSize(value.fontSize)) {
            discrepancies.push({
              token: `typography.${key}.fontSize`,
              expected: String(value.fontSize),
              actual,
            });
          }
        }
      }
    }
  }

  // --- Spacing ---
  if (frontmatter.spacing && typeof frontmatter.spacing === "object") {
    const spacingSizes = themeJson?.settings?.spacing?.spacingSizes ?? [];
    for (const [key, expectedValue] of Object.entries(frontmatter.spacing)) {
      totalTokens++;
      const entry = spacingSizes.find((s) => s.slug === key || s.name === key);
      const actual = entry?.size ?? null;
      if (normalizeSize(actual) !== normalizeSize(expectedValue)) {
        discrepancies.push({
          token: `spacing.${key}`,
          expected: String(expectedValue),
          actual,
        });
      }
    }
  }

  // --- Rounded (border radius) ---
  if (frontmatter.rounded && typeof frontmatter.rounded === "object") {
    const blocks = themeJson?.styles?.blocks ?? {};
    for (const [key, expectedValue] of Object.entries(frontmatter.rounded)) {
      totalTokens++;
      // Look for the border radius in styles.blocks.*.border.radius
      let actual = null;
      // Try direct block match (e.g., "core/button" or just the key)
      const blockKey = key.includes("/") ? key : `core/${key}`;
      if (blocks[blockKey]?.border?.radius) {
        actual = blocks[blockKey].border.radius;
      } else {
        // Search all blocks for a matching radius value
        for (const block of Object.values(blocks)) {
          if (block?.border?.radius !== undefined) {
            // If we find any block with border radius, check it
            if (normalizeSize(block.border.radius) === normalizeSize(expectedValue)) {
              actual = block.border.radius;
              break;
            }
          }
        }
        // If still null, try to find any block with border.radius
        if (actual === null) {
          for (const block of Object.values(blocks)) {
            if (block?.border?.radius !== undefined) {
              actual = block.border.radius;
              break;
            }
          }
        }
      }
      if (normalizeSize(actual) !== normalizeSize(expectedValue)) {
        discrepancies.push({
          token: `rounded.${key}`,
          expected: String(expectedValue),
          actual,
        });
      }
    }
  }

  const matchedTokens = totalTokens - discrepancies.length;

  return {
    result: discrepancies.length === 0 ? "PASS" : "FAIL",
    discrepancies,
    totalTokens,
    matchedTokens,
  };
}

/**
 * Normalize a color value for comparison (lowercase, trim).
 */
function normalizeColor(value) {
  if (value == null) return null;
  return String(value).trim().toLowerCase();
}

/**
 * Normalize a font family string for comparison.
 * Removes extra whitespace and lowercases.
 */
function normalizeFontFamily(value) {
  if (value == null) return null;
  return String(value).trim().toLowerCase().replace(/\s+/g, " ");
}

/**
 * Normalize a font size value for comparison.
 * Handles string/number equivalence.
 */
function normalizeFontSize(value) {
  if (value == null) return null;
  return String(value).trim().toLowerCase();
}

/**
 * Normalize a size/spacing value for comparison.
 */
function normalizeSize(value) {
  if (value == null) return null;
  return String(value).trim().toLowerCase();
}

// --- CLI Mode ---
const isMainModule = process.argv[1] && (
  process.argv[1].endsWith("validate-tokens.js") ||
  process.argv[1].endsWith("validate-tokens")
);

if (isMainModule && process.argv.length >= 4) {
  const designMdPath = process.argv[2];
  const themeJsonPath = process.argv[3];

  try {
    const result = validateTokens({ designMdPath, themeJsonPath });
    console.log(`\nToken Validation Result: ${result.result}`);
    console.log(`Total tokens: ${result.totalTokens}`);
    console.log(`Matched: ${result.matchedTokens}`);
    console.log(`Discrepancies: ${result.discrepancies.length}`);

    if (result.discrepancies.length > 0) {
      console.log("\nDiscrepant tokens:");
      for (const d of result.discrepancies) {
        console.log(`  ${d.token}: expected "${d.expected}", actual "${d.actual}"`);
      }
    }

    process.exit(result.result === "PASS" ? 0 : 1);
  } catch (err) {
    console.error(`Error: ${err.message}`);
    process.exit(2);
  }
}
