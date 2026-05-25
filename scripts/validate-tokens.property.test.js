import { describe, it, expect, beforeEach, afterEach } from "vitest";
import * as fc from "fast-check";
import { validateTokens } from "./validate-tokens.js";
import { writeFileSync, mkdirSync, rmSync } from "node:fs";
import { join } from "node:path";
import { tmpdir } from "node:os";

/**
 * Property-based tests for token discrepancy detection.
 * Validates: Requirements 11.4
 */

let testDir;

beforeEach(() => {
  testDir = join(tmpdir(), "validate-tokens-pbt-" + Date.now() + "-" + Math.random().toString(36).slice(2));
  mkdirSync(testDir, { recursive: true });
});

afterEach(() => {
  rmSync(testDir, { recursive: true, force: true });
});

/**
 * Helper: Generate DESIGN.md content from token objects.
 */
function createDesignMd(tokens) {
  const yamlLines = [];
  if (tokens.colors) {
    yamlLines.push("colors:");
    for (const [k, v] of Object.entries(tokens.colors)) {
      yamlLines.push(`  ${k}: "${v}"`);
    }
  }
  if (tokens.typography) {
    yamlLines.push("typography:");
    for (const [k, v] of Object.entries(tokens.typography)) {
      yamlLines.push(`  ${k}:`);
      if (v.fontFamily) yamlLines.push(`    fontFamily: "${v.fontFamily}"`);
      if (v.fontSize) yamlLines.push(`    fontSize: "${v.fontSize}"`);
    }
  }
  if (tokens.spacing) {
    yamlLines.push("spacing:");
    for (const [k, v] of Object.entries(tokens.spacing)) {
      yamlLines.push(`  ${k}: "${v}"`);
    }
  }
  return `---\n${yamlLines.join("\n")}\n---\n\n# Design Document\n`;
}

/**
 * Helper: Build a theme.json that exactly matches the given tokens.
 */
function buildMatchingThemeJson(tokens) {
  const settings = {};
  if (tokens.colors) {
    settings.color = {
      palette: Object.entries(tokens.colors).map(([slug, color]) => ({
        slug,
        color,
        name: slug,
      })),
    };
  }
  if (tokens.typography) {
    const fontFamilies = [];
    const fontSizes = [];
    for (const [key, value] of Object.entries(tokens.typography)) {
      if (value.fontFamily) {
        fontFamilies.push({ slug: key, fontFamily: value.fontFamily, name: key });
      }
      if (value.fontSize) {
        fontSizes.push({ slug: key, size: value.fontSize, name: key });
      }
    }
    settings.typography = {};
    if (fontFamilies.length > 0) settings.typography.fontFamilies = fontFamilies;
    if (fontSizes.length > 0) settings.typography.fontSizes = fontSizes;
  }
  if (tokens.spacing) {
    settings.spacing = {
      spacingSizes: Object.entries(tokens.spacing).map(([slug, size]) => ({
        slug,
        size,
        name: slug,
      })),
    };
  }
  return JSON.stringify({ settings }, null, 2);
}

// --- Generators ---

/** Generate a valid hex color string (6-digit). */
const hexColorArb = fc
  .array(fc.constantFrom(..."0123456789abcdef".split("")), { minLength: 6, maxLength: 6 })
  .map((chars) => "#" + chars.join(""));

/** Generate a valid CSS slug (lowercase alpha, 2-10 chars, no reserved YAML chars). */
const slugArb = fc
  .array(fc.constantFrom(..."abcdefghijklmnopqrstuvwxyz".split("")), { minLength: 2, maxLength: 10 })
  .map((chars) => chars.join(""));

/** Generate a font family string. */
const fontFamilyArb = fc.constantFrom(
  "Inter, sans-serif",
  "Roboto, sans-serif",
  "Open Sans, sans-serif",
  "Lato, sans-serif",
  "Montserrat, sans-serif",
  "Playfair Display, serif",
  "IBM Plex Serif, serif",
  "Merriweather, serif"
);

/** Generate a font size string. */
const fontSizeArb = fc.constantFrom(
  "12px", "14px", "16px", "18px", "20px", "24px", "28px", "32px", "36px", "48px",
  "0.75rem", "0.875rem", "1rem", "1.125rem", "1.25rem", "1.5rem", "2rem", "3rem"
);

/** Generate a spacing value string. */
const spacingValueArb = fc.constantFrom(
  "4px", "8px", "12px", "16px", "20px", "24px", "32px", "40px", "48px", "64px",
  "0.25rem", "0.5rem", "1rem", "1.5rem", "2rem", "3rem", "4rem"
);

/** Generate a non-empty color tokens map (1-5 entries with unique slugs). */
const colorTokensArb = fc
  .uniqueArray(slugArb, { minLength: 1, maxLength: 5 })
  .chain((slugs) =>
    fc.tuple(...slugs.map(() => hexColorArb)).map((colors) => {
      const obj = {};
      slugs.forEach((s, i) => { obj[s] = colors[i]; });
      return obj;
    })
  );

/** Generate a non-empty typography tokens map (1-3 entries with unique slugs). */
const typographyTokensArb = fc
  .uniqueArray(slugArb, { minLength: 1, maxLength: 3 })
  .chain((slugs) =>
    fc.tuple(
      ...slugs.map(() => fc.tuple(fontFamilyArb, fontSizeArb))
    ).map((pairs) => {
      const obj = {};
      slugs.forEach((s, i) => {
        obj[s] = { fontFamily: pairs[i][0], fontSize: pairs[i][1] };
      });
      return obj;
    })
  );

/** Generate a non-empty spacing tokens map (1-4 entries with unique slugs). */
const spacingTokensArb = fc
  .uniqueArray(slugArb, { minLength: 1, maxLength: 4 })
  .chain((slugs) =>
    fc.tuple(...slugs.map(() => spacingValueArb)).map((values) => {
      const obj = {};
      slugs.forEach((s, i) => { obj[s] = values[i]; });
      return obj;
    })
  );

/** Generate a combined token set with at least one category populated. */
const tokenSetArb = fc
  .tuple(
    fc.option(colorTokensArb, { nil: undefined }),
    fc.option(typographyTokensArb, { nil: undefined }),
    fc.option(spacingTokensArb, { nil: undefined })
  )
  .filter(([colors, typography, spacing]) => colors || typography || spacing)
  .map(([colors, typography, spacing]) => {
    const tokens = {};
    if (colors) tokens.colors = colors;
    if (typography) tokens.typography = typography;
    if (spacing) tokens.spacing = spacing;
    return tokens;
  });

/** Count total tokens in a token set. */
function countTokens(tokens) {
  let count = 0;
  if (tokens.colors) count += Object.keys(tokens.colors).length;
  if (tokens.typography) {
    for (const v of Object.values(tokens.typography)) {
      if (v.fontFamily) count++;
      if (v.fontSize) count++;
    }
  }
  if (tokens.spacing) count += Object.keys(tokens.spacing).length;
  return count;
}

describe("validateTokens - Property Tests", () => {
  // Property 1: Matching tokens always return PASS
  it("matching tokens always return PASS with 0 discrepancies", () => {
    fc.assert(
      fc.property(tokenSetArb, (tokens) => {
        const designMdPath = join(testDir, "DESIGN.md");
        const themeJsonPath = join(testDir, "theme.json");

        writeFileSync(designMdPath, createDesignMd(tokens));
        writeFileSync(themeJsonPath, buildMatchingThemeJson(tokens));

        const result = validateTokens({ designMdPath, themeJsonPath });

        expect(result.result).toBe("PASS");
        expect(result.discrepancies).toHaveLength(0);
        expect(result.totalTokens).toBe(countTokens(tokens));
        expect(result.matchedTokens).toBe(countTokens(tokens));
      }),
      { numRuns: 100 }
    );
  });

  // Property 2: Mismatched tokens always return FAIL with at least 1 discrepancy
  it("mismatched tokens always return FAIL with at least 1 discrepancy", () => {
    // Generate tokens and then mutate at least one value in theme.json
    const mismatchedArb = tokenSetArb.chain((tokens) => {
      // Pick a category to mutate
      const categories = [];
      if (tokens.colors) categories.push("colors");
      if (tokens.typography) categories.push("typography");
      if (tokens.spacing) categories.push("spacing");

      return fc.tuple(
        fc.constant(tokens),
        fc.constantFrom(...categories),
        hexColorArb // use as a replacement color
      );
    });

    fc.assert(
      fc.property(mismatchedArb, ([tokens, category, altColor]) => {
        const designMdPath = join(testDir, "DESIGN.md");
        const themeJsonPath = join(testDir, "theme.json");

        writeFileSync(designMdPath, createDesignMd(tokens));

        // Build a theme.json with at least one mismatch
        const themeObj = JSON.parse(buildMatchingThemeJson(tokens));

        if (category === "colors" && themeObj.settings.color) {
          const palette = themeObj.settings.color.palette;
          // Mutate the first color to something different
          const original = palette[0].color;
          // Ensure the replacement is actually different
          palette[0].color = original === altColor ? "#000000" : altColor;
        } else if (category === "typography" && themeObj.settings.typography) {
          if (themeObj.settings.typography.fontFamilies && themeObj.settings.typography.fontFamilies.length > 0) {
            const entry = themeObj.settings.typography.fontFamilies[0];
            entry.fontFamily = entry.fontFamily === "Comic Sans, cursive" ? "Papyrus, fantasy" : "Comic Sans, cursive";
          } else if (themeObj.settings.typography.fontSizes && themeObj.settings.typography.fontSizes.length > 0) {
            const entry = themeObj.settings.typography.fontSizes[0];
            entry.size = entry.size === "999px" ? "998px" : "999px";
          }
        } else if (category === "spacing" && themeObj.settings.spacing) {
          const sizes = themeObj.settings.spacing.spacingSizes;
          if (sizes && sizes.length > 0) {
            sizes[0].size = sizes[0].size === "999px" ? "998px" : "999px";
          }
        }

        writeFileSync(themeJsonPath, JSON.stringify(themeObj, null, 2));

        const result = validateTokens({ designMdPath, themeJsonPath });

        expect(result.result).toBe("FAIL");
        expect(result.discrepancies.length).toBeGreaterThanOrEqual(1);
      }),
      { numRuns: 100 }
    );
  });

  // Property 3: Discrepancy count equals totalTokens - matchedTokens
  it("discrepancy count equals totalTokens minus matchedTokens", () => {
    // Generate tokens and randomly mismatch some of them
    const partialMismatchArb = tokenSetArb.chain((tokens) => {
      return fc.tuple(
        fc.constant(tokens),
        // For each color, decide if it should mismatch
        fc.array(fc.boolean(), { minLength: 20, maxLength: 20 })
      );
    });

    fc.assert(
      fc.property(partialMismatchArb, ([tokens, mismatchFlags]) => {
        const designMdPath = join(testDir, "DESIGN.md");
        const themeJsonPath = join(testDir, "theme.json");

        writeFileSync(designMdPath, createDesignMd(tokens));

        const themeObj = JSON.parse(buildMatchingThemeJson(tokens));
        let flagIdx = 0;

        // Randomly mismatch some color entries
        if (themeObj.settings.color && themeObj.settings.color.palette) {
          for (const entry of themeObj.settings.color.palette) {
            if (mismatchFlags[flagIdx++ % mismatchFlags.length]) {
              entry.color = entry.color === "#ffffff" ? "#000000" : "#ffffff";
            }
          }
        }

        // Randomly mismatch some typography entries
        if (themeObj.settings.typography) {
          if (themeObj.settings.typography.fontFamilies) {
            for (const entry of themeObj.settings.typography.fontFamilies) {
              if (mismatchFlags[flagIdx++ % mismatchFlags.length]) {
                entry.fontFamily = "MISMATCHED FONT, fantasy";
              }
            }
          }
          if (themeObj.settings.typography.fontSizes) {
            for (const entry of themeObj.settings.typography.fontSizes) {
              if (mismatchFlags[flagIdx++ % mismatchFlags.length]) {
                entry.size = "999px";
              }
            }
          }
        }

        // Randomly mismatch some spacing entries
        if (themeObj.settings.spacing && themeObj.settings.spacing.spacingSizes) {
          for (const entry of themeObj.settings.spacing.spacingSizes) {
            if (mismatchFlags[flagIdx++ % mismatchFlags.length]) {
              entry.size = "999px";
            }
          }
        }

        writeFileSync(themeJsonPath, JSON.stringify(themeObj, null, 2));

        const result = validateTokens({ designMdPath, themeJsonPath });

        // Core invariant: discrepancies.length === totalTokens - matchedTokens
        expect(result.discrepancies.length).toBe(result.totalTokens - result.matchedTokens);
      }),
      { numRuns: 100 }
    );
  });

  // Property 4: Each discrepancy has correct expected value from DESIGN.md
  it("each discrepancy expected field matches the value from DESIGN.md", () => {
    // Generate tokens and create a theme.json with all values mismatched
    fc.assert(
      fc.property(tokenSetArb, (tokens) => {
        const designMdPath = join(testDir, "DESIGN.md");
        const themeJsonPath = join(testDir, "theme.json");

        writeFileSync(designMdPath, createDesignMd(tokens));

        // Build a theme.json where ALL values are wrong
        const themeObj = JSON.parse(buildMatchingThemeJson(tokens));

        if (themeObj.settings.color && themeObj.settings.color.palette) {
          for (const entry of themeObj.settings.color.palette) {
            entry.color = "#000001"; // guaranteed different from any generated hex
          }
        }
        if (themeObj.settings.typography) {
          if (themeObj.settings.typography.fontFamilies) {
            for (const entry of themeObj.settings.typography.fontFamilies) {
              entry.fontFamily = "WRONG FONT, fantasy";
            }
          }
          if (themeObj.settings.typography.fontSizes) {
            for (const entry of themeObj.settings.typography.fontSizes) {
              entry.size = "0.001px";
            }
          }
        }
        if (themeObj.settings.spacing && themeObj.settings.spacing.spacingSizes) {
          for (const entry of themeObj.settings.spacing.spacingSizes) {
            entry.size = "0.001px";
          }
        }

        writeFileSync(themeJsonPath, JSON.stringify(themeObj, null, 2));

        const result = validateTokens({ designMdPath, themeJsonPath });

        // Build a lookup of expected values from the tokens
        const expectedValues = {};
        if (tokens.colors) {
          for (const [slug, color] of Object.entries(tokens.colors)) {
            expectedValues[`colors.${slug}`] = color;
          }
        }
        if (tokens.typography) {
          for (const [key, value] of Object.entries(tokens.typography)) {
            if (value.fontFamily) {
              expectedValues[`typography.${key}.fontFamily`] = value.fontFamily;
            }
            if (value.fontSize) {
              expectedValues[`typography.${key}.fontSize`] = value.fontSize;
            }
          }
        }
        if (tokens.spacing) {
          for (const [slug, size] of Object.entries(tokens.spacing)) {
            expectedValues[`spacing.${slug}`] = size;
          }
        }

        // Every discrepancy's expected field must match the DESIGN.md value
        for (const disc of result.discrepancies) {
          expect(expectedValues).toHaveProperty(disc.token);
          expect(disc.expected).toBe(expectedValues[disc.token]);
        }
      }),
      { numRuns: 100 }
    );
  });
});
