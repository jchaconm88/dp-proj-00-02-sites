import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import * as fc from 'fast-check';
import { mkdirSync, writeFileSync, readFileSync, rmSync, existsSync, chmodSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { transferTokens } from './transfer-tokens.js';

/**
 * Property-based tests for token transfer round trip.
 *
 * Property 4: Token Transfer Round Trip
 * Validates: Requirements 3.4, 11.2
 */
describe('transferTokens - Property Tests', () => {
  let tempDir;

  beforeEach(() => {
    tempDir = join(tmpdir(), `transfer-tokens-pbt-${Date.now()}-${Math.random().toString(36).slice(2)}`);
    mkdirSync(tempDir, { recursive: true });
  });

  afterEach(() => {
    if (existsSync(tempDir)) {
      rmSync(tempDir, { recursive: true, force: true });
    }
  });

  // --- Generators ---

  /** Generator for valid hex color strings (#RRGGBB) */
  const hexColor = fc
    .array(fc.constantFrom(...'0123456789abcdef'.split('')), { minLength: 6, maxLength: 6 })
    .map((chars) => '#' + chars.join(''));

  /** Generator for valid color slug keys (lowercase, alphanumeric + hyphens, no leading/trailing hyphen) */
  const colorSlug = fc
    .array(fc.constantFrom(...'abcdefghijklmnopqrstuvwxyz0123456789'.split('')), { minLength: 1, maxLength: 12 })
    .map((chars) => chars.join(''));

  /** Generator for a colors object with 1-8 entries */
  const colorsArb = fc
    .array(fc.tuple(colorSlug, hexColor), { minLength: 1, maxLength: 8 })
    .map((entries) => Object.fromEntries(entries))
    .filter((obj) => Object.keys(obj).length >= 1);

  /** Generator for font family strings */
  const fontFamilyArb = fc.constantFrom(
    'Inter, sans-serif',
    'Open Sans, sans-serif',
    'IBM Plex Serif, serif',
    'Roboto, sans-serif',
    'Lato, sans-serif',
    'Montserrat, sans-serif'
  );

  /** Generator for font size strings (with units) */
  const fontSizeArb = fc.oneof(
    fc.integer({ min: 8, max: 96 }).map((n) => `${n}px`),
    fc.float({ min: 0.5, max: 6, noNaN: true }).map((n) => `${n.toFixed(2)}rem`)
  );

  /** Generator for typography slug */
  const typographySlug = fc.constantFrom(
    'display-lg', 'display-md', 'display-sm',
    'heading-lg', 'heading-md', 'heading-sm',
    'body', 'body-lg', 'body-sm',
    'caption', 'label'
  );

  /** Generator for a typography object with 1-5 entries (unique slugs) */
  const typographyArb = fc
    .uniqueArray(typographySlug, { minLength: 1, maxLength: 5 })
    .chain((slugs) =>
      fc.tuple(...slugs.map(() => fc.tuple(fontFamilyArb, fontSizeArb)))
        .map((values) =>
          Object.fromEntries(
            slugs.map((slug, i) => [slug, { fontFamily: values[i][0], fontSize: values[i][1] }])
          )
        )
    );

  /** Generator for spacing value strings */
  const spacingValueArb = fc.oneof(
    fc.integer({ min: 1, max: 128 }).map((n) => `${n}px`),
    fc.float({ min: 0.25, max: 8, noNaN: true }).map((n) => `${n.toFixed(2)}rem`)
  );

  /** Generator for spacing slug */
  const spacingSlug = fc.constantFrom(
    'base', 'gutter', 'margin-mobile', 'margin-desktop', 'max-width', 'sm', 'md', 'lg', 'xl'
  );

  /** Generator for a spacing object with 1-5 entries (unique slugs) */
  const spacingArb = fc
    .uniqueArray(spacingSlug, { minLength: 1, maxLength: 5 })
    .chain((slugs) =>
      fc.tuple(...slugs.map(() => spacingValueArb))
        .map((values) => Object.fromEntries(slugs.map((slug, i) => [slug, values[i]])))
    );

  /** Generator for rounded value strings */
  const roundedValueArb = fc.oneof(
    fc.integer({ min: 1, max: 64 }).map((n) => `${n}px`),
    fc.constant('9999px')
  );

  /** Generator for rounded slug */
  const roundedSlug = fc.constantFrom('sm', 'DEFAULT', 'md', 'lg', 'xl', 'full');

  /** Generator for a rounded object with 1-5 entries (unique slugs) */
  const roundedArb = fc
    .uniqueArray(roundedSlug, { minLength: 1, maxLength: 5 })
    .chain((slugs) =>
      fc.tuple(...slugs.map(() => roundedValueArb))
        .map((values) => Object.fromEntries(slugs.map((slug, i) => [slug, values[i]])))
    );

  /** Generator for a complete valid DESIGN.md token set */
  const validTokensArb = fc.record({
    colors: colorsArb,
    typography: typographyArb,
    spacing: spacingArb,
    rounded: roundedArb,
  });

  /**
   * Helper: builds DESIGN.md content from a tokens object.
   */
  function buildDesignMd(tokens) {
    let yaml = '---\n';
    yaml += 'name: "Generated Design"\n';

    // Colors
    yaml += 'colors:\n';
    for (const [key, value] of Object.entries(tokens.colors)) {
      yaml += `  ${key}: "${value}"\n`;
    }

    // Typography
    yaml += 'typography:\n';
    for (const [key, value] of Object.entries(tokens.typography)) {
      yaml += `  ${key}:\n`;
      yaml += `    fontFamily: "${value.fontFamily}"\n`;
      yaml += `    fontSize: "${value.fontSize}"\n`;
    }

    // Spacing
    yaml += 'spacing:\n';
    for (const [key, value] of Object.entries(tokens.spacing)) {
      yaml += `  ${key}: "${value}"\n`;
    }

    // Rounded
    yaml += 'rounded:\n';
    for (const [key, value] of Object.entries(tokens.rounded)) {
      yaml += `  ${key}: "${value}"\n`;
    }

    yaml += '---\n\n# Design System\n';
    return yaml;
  }

  // --- Property 1: Color round trip ---
  it('every color in DESIGN.md appears in theme.json palette with correct slug and value', () => {
    fc.assert(
      fc.property(validTokensArb, (tokens) => {
        const designMdPath = join(tempDir, 'DESIGN.md');
        const themeJsonPath = join(tempDir, 'theme.json');

        writeFileSync(designMdPath, buildDesignMd(tokens), 'utf-8');
        const result = transferTokens({ designMdPath, themeJsonPath, overwrite: true });

        expect(result.success).toBe(true);

        const themeJson = JSON.parse(readFileSync(themeJsonPath, 'utf-8'));
        const palette = themeJson.settings.color.palette;

        for (const [slug, color] of Object.entries(tokens.colors)) {
          const entry = palette.find((p) => p.slug === slug);
          expect(entry).toBeDefined();
          expect(entry.color).toBe(color);
        }
      }),
      { numRuns: 50 }
    );
  });

  // --- Property 2: Typography round trip ---
  it('every fontFamily appears in fontFamilies and every fontSize appears in fontSizes', () => {
    fc.assert(
      fc.property(validTokensArb, (tokens) => {
        const designMdPath = join(tempDir, 'DESIGN.md');
        const themeJsonPath = join(tempDir, 'theme.json');

        writeFileSync(designMdPath, buildDesignMd(tokens), 'utf-8');
        const result = transferTokens({ designMdPath, themeJsonPath, overwrite: true });

        expect(result.success).toBe(true);

        const themeJson = JSON.parse(readFileSync(themeJsonPath, 'utf-8'));
        const fontFamilies = themeJson.settings.typography.fontFamilies;
        const fontSizes = themeJson.settings.typography.fontSizes;

        // Every fontFamily from tokens should appear in fontFamilies
        const allFamiliesInTheme = fontFamilies.map((f) => f.fontFamily);
        for (const [, value] of Object.entries(tokens.typography)) {
          expect(allFamiliesInTheme).toContain(value.fontFamily);
        }

        // Every fontSize from tokens should appear in fontSizes
        for (const [slug, value] of Object.entries(tokens.typography)) {
          const entry = fontSizes.find((f) => f.slug === slug);
          expect(entry).toBeDefined();
          expect(entry.size).toBe(value.fontSize);
        }
      }),
      { numRuns: 50 }
    );
  });

  // --- Property 3: Spacing round trip ---
  it('every spacing value appears in spacingSizes', () => {
    fc.assert(
      fc.property(validTokensArb, (tokens) => {
        const designMdPath = join(tempDir, 'DESIGN.md');
        const themeJsonPath = join(tempDir, 'theme.json');

        writeFileSync(designMdPath, buildDesignMd(tokens), 'utf-8');
        const result = transferTokens({ designMdPath, themeJsonPath, overwrite: true });

        expect(result.success).toBe(true);

        const themeJson = JSON.parse(readFileSync(themeJsonPath, 'utf-8'));
        const spacingSizes = themeJson.settings.spacing.spacingSizes;

        // Every spacing value from tokens should appear in spacingSizes
        const allSizesInTheme = spacingSizes.map((s) => s.size);
        for (const [, value] of Object.entries(tokens.spacing)) {
          expect(allSizesInTheme).toContain(value);
        }

        // Count should match
        expect(spacingSizes.length).toBe(Object.keys(tokens.spacing).length);
      }),
      { numRuns: 50 }
    );
  });

  // --- Property 4: Transfer always succeeds for valid input ---
  it('valid DESIGN.md content always produces { success: true }', () => {
    fc.assert(
      fc.property(validTokensArb, (tokens) => {
        const designMdPath = join(tempDir, 'DESIGN.md');
        const themeJsonPath = join(tempDir, 'theme.json');

        writeFileSync(designMdPath, buildDesignMd(tokens), 'utf-8');
        const result = transferTokens({ designMdPath, themeJsonPath, overwrite: true });

        expect(result.success).toBe(true);
        expect(result.tokensTransferred).toBeGreaterThan(0);
        expect(result.errors).toHaveLength(0);
      }),
      { numRuns: 50 }
    );
  });

  // --- Property 5: No partial writes on failure ---
  it('if DESIGN.md is unreadable, theme.json is not modified', () => {
    fc.assert(
      fc.property(validTokensArb, (tokens) => {
        const designMdPath = join(tempDir, 'nonexistent-dir', 'DESIGN.md');
        const themeJsonPath = join(tempDir, 'theme.json');

        // Write an existing theme.json to verify it's not modified
        const existingContent = JSON.stringify({ existing: true, version: 2 }, null, 2);
        writeFileSync(themeJsonPath, existingContent, 'utf-8');

        const result = transferTokens({ designMdPath, themeJsonPath, overwrite: true });

        expect(result.success).toBe(false);

        // theme.json should remain unchanged
        const afterContent = readFileSync(themeJsonPath, 'utf-8');
        expect(afterContent).toBe(existingContent);
      }),
      { numRuns: 20 }
    );
  });
});
