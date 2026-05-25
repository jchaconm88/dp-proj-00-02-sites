/**
 * Unit tests for transfer-tokens.js
 */

import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { mkdirSync, writeFileSync, readFileSync, rmSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import {
  parseFrontmatter,
  slugToName,
  mapColors,
  mapTypography,
  mapSpacing,
  mapRounded,
  buildThemeJson,
  transferTokens,
} from './transfer-tokens.js';

describe('parseFrontmatter', () => {
  it('parses valid YAML frontmatter', () => {
    const content = `---
name: "Test Design"
colors:
  primary: "#ff0000"
  secondary: "#00ff00"
---

# Some markdown content
`;
    const { tokens, error } = parseFrontmatter(content);
    expect(error).toBeNull();
    expect(tokens).toEqual({
      name: 'Test Design',
      colors: { primary: '#ff0000', secondary: '#00ff00' },
    });
  });

  it('returns error when no frontmatter delimiters found', () => {
    const content = '# Just markdown\nNo frontmatter here.';
    const { tokens, error } = parseFrontmatter(content);
    expect(tokens).toBeNull();
    expect(error).toContain('No YAML frontmatter found');
  });

  it('returns error for invalid YAML', () => {
    const content = `---
invalid: [unclosed
---`;
    const { tokens, error } = parseFrontmatter(content);
    expect(tokens).toBeNull();
    expect(error).toContain('YAML parse error');
  });
});

describe('slugToName', () => {
  it('converts simple slug to title case', () => {
    expect(slugToName('primary')).toBe('Primary');
  });

  it('converts hyphenated slug to title case words', () => {
    expect(slugToName('on-primary')).toBe('On Primary');
  });

  it('converts multi-part slug', () => {
    expect(slugToName('display-lg')).toBe('Display Lg');
  });
});

describe('mapColors', () => {
  it('maps color entries to palette format', () => {
    const colors = { primary: '#ff0000', secondary: '#00ff00' };
    const result = mapColors(colors);
    expect(result.palette).toEqual([
      { slug: 'primary', color: '#ff0000', name: 'Primary' },
      { slug: 'secondary', color: '#00ff00', name: 'Secondary' },
    ]);
    expect(result.count).toBe(2);
    expect(result.warnings).toHaveLength(0);
  });

  it('skips non-string values with warning', () => {
    const colors = { primary: '#ff0000', invalid: 123 };
    const result = mapColors(colors);
    expect(result.palette).toHaveLength(1);
    expect(result.warnings).toHaveLength(1);
    expect(result.warnings[0]).toContain('invalid');
  });

  it('returns empty palette for null/undefined', () => {
    const result = mapColors(null);
    expect(result.palette).toHaveLength(0);
    expect(result.count).toBe(0);
    expect(result.warnings).toHaveLength(1);
  });
});

describe('mapTypography', () => {
  it('maps typography entries to fontFamilies and fontSizes', () => {
    const typography = {
      'display-lg': { fontFamily: 'Inter, sans-serif', fontSize: '3rem', fontWeight: '700', lineHeight: '1.2' },
      body: { fontFamily: 'Open Sans, sans-serif', fontSize: '1rem', fontWeight: '400', lineHeight: '1.6' },
    };
    const result = mapTypography(typography);
    expect(result.fontFamilies).toEqual([
      { fontFamily: 'Inter, sans-serif', slug: 'display-lg', name: 'Display Lg' },
      { fontFamily: 'Open Sans, sans-serif', slug: 'body', name: 'Body' },
    ]);
    expect(result.fontSizes).toEqual([
      { slug: 'display-lg', size: '3rem', name: 'Display Lg' },
      { slug: 'body', size: '1rem', name: 'Body' },
    ]);
    expect(result.count).toBe(4);
  });

  it('deduplicates font families', () => {
    const typography = {
      'display-lg': { fontFamily: 'Inter, sans-serif', fontSize: '3rem' },
      'display-md': { fontFamily: 'Inter, sans-serif', fontSize: '2rem' },
    };
    const result = mapTypography(typography);
    expect(result.fontFamilies).toHaveLength(1);
    expect(result.fontSizes).toHaveLength(2);
  });

  it('returns empty for null/undefined', () => {
    const result = mapTypography(null);
    expect(result.fontFamilies).toHaveLength(0);
    expect(result.fontSizes).toHaveLength(0);
    expect(result.warnings).toHaveLength(1);
  });
});

describe('mapSpacing', () => {
  it('maps spacing entries with incremental slugs', () => {
    const spacing = { base: '8px', gutter: '16px', 'margin-mobile': '16px' };
    const result = mapSpacing(spacing);
    expect(result.spacingSizes).toEqual([
      { slug: '10', size: '8px', name: 'Base' },
      { slug: '20', size: '16px', name: 'Gutter' },
      { slug: '30', size: '16px', name: 'Margin Mobile' },
    ]);
    expect(result.count).toBe(3);
  });

  it('returns empty for null/undefined', () => {
    const result = mapSpacing(undefined);
    expect(result.spacingSizes).toHaveLength(0);
    expect(result.warnings).toHaveLength(1);
  });
});

describe('mapRounded', () => {
  it('maps rounded entries to border radius object', () => {
    const rounded = { sm: '4px', DEFAULT: '8px', lg: '16px' };
    const result = mapRounded(rounded);
    expect(result.borderRadius).toEqual({ sm: '4px', DEFAULT: '8px', lg: '16px' });
    expect(result.count).toBe(3);
  });

  it('returns empty for null/undefined', () => {
    const result = mapRounded(null);
    expect(result.borderRadius).toEqual({});
    expect(result.warnings).toHaveLength(1);
  });
});

describe('buildThemeJson', () => {
  it('builds complete theme.json with all token types', () => {
    const result = buildThemeJson({
      palette: [{ slug: 'primary', color: '#ff0000', name: 'Primary' }],
      fontFamilies: [{ fontFamily: 'Inter, sans-serif', slug: 'body', name: 'Body' }],
      fontSizes: [{ slug: 'medium', size: '16px', name: 'Medium' }],
      spacingSizes: [{ slug: '10', size: '8px', name: 'Base' }],
      borderRadius: { sm: '4px', lg: '16px' },
      existingThemeJson: null,
      overwrite: true,
    });

    expect(result.$schema).toBe('https://schemas.wp.org/wp/6.5/theme.json');
    expect(result.version).toBe(2);
    expect(result.settings.color.custom).toBe(false);
    expect(result.settings.color.palette).toHaveLength(1);
    expect(result.settings.typography.customFontSize).toBe(false);
    expect(result.settings.typography.fontFamilies).toHaveLength(1);
    expect(result.settings.typography.fontSizes).toHaveLength(1);
    expect(result.settings.spacing.customSpacingSize).toBe(false);
    expect(result.settings.spacing.spacingSizes).toHaveLength(1);
    expect(result.settings.custom.borderRadius).toEqual({ sm: '4px', lg: '16px' });
  });

  it('sets custom=false, customFontSize=false, customSpacingSize=false', () => {
    const result = buildThemeJson({
      palette: [],
      fontFamilies: [],
      fontSizes: [],
      spacingSizes: [],
      borderRadius: {},
      existingThemeJson: null,
      overwrite: true,
    });

    expect(result.settings.color.custom).toBe(false);
    expect(result.settings.typography.customFontSize).toBe(false);
    expect(result.settings.spacing.customSpacingSize).toBe(false);
  });
});

describe('transferTokens (integration)', () => {
  let tempDir;

  beforeEach(() => {
    tempDir = join(tmpdir(), `transfer-tokens-test-${Date.now()}-${Math.random().toString(36).slice(2)}`);
    mkdirSync(tempDir, { recursive: true });
  });

  afterEach(() => {
    if (existsSync(tempDir)) {
      rmSync(tempDir, { recursive: true, force: true });
    }
  });

  it('transfers tokens from DESIGN.md to new theme.json', () => {
    const designMdPath = join(tempDir, 'DESIGN.md');
    const themeJsonPath = join(tempDir, 'theme.json');

    writeFileSync(designMdPath, `---
name: "Test Client"
colors:
  primary: "#006e01"
  on-primary: "#ffffff"
  secondary: "#4a6350"
typography:
  display-lg:
    fontFamily: "IBM Plex Serif, serif"
    fontSize: "3rem"
    fontWeight: "700"
    lineHeight: "1.2"
  body:
    fontFamily: "Open Sans, sans-serif"
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

# Test Design System
`, 'utf-8');

    const result = transferTokens({ designMdPath, themeJsonPath, overwrite: true });

    expect(result.success).toBe(true);
    expect(result.tokensTransferred).toBeGreaterThan(0);
    expect(result.errors).toHaveLength(0);

    // Verify theme.json was written
    const themeJson = JSON.parse(readFileSync(themeJsonPath, 'utf-8'));
    expect(themeJson.settings.color.custom).toBe(false);
    expect(themeJson.settings.color.palette).toContainEqual({
      slug: 'primary',
      color: '#006e01',
      name: 'Primary',
    });
    expect(themeJson.settings.typography.customFontSize).toBe(false);
    expect(themeJson.settings.typography.fontFamilies).toContainEqual({
      fontFamily: 'IBM Plex Serif, serif',
      slug: 'display-lg',
      name: 'Display Lg',
    });
    expect(themeJson.settings.spacing.customSpacingSize).toBe(false);
    expect(themeJson.settings.spacing.spacingSizes.length).toBe(5);
    expect(themeJson.settings.custom.borderRadius.sm).toBe('4px');
  });

  it('returns error when DESIGN.md is unreadable', () => {
    const designMdPath = join(tempDir, 'nonexistent', 'DESIGN.md');
    const themeJsonPath = join(tempDir, 'theme.json');

    const result = transferTokens({ designMdPath, themeJsonPath });

    expect(result.success).toBe(false);
    expect(result.errors.length).toBeGreaterThan(0);
    expect(result.errors[0]).toContain('Cannot read DESIGN.md');
    // theme.json should NOT be created
    expect(existsSync(themeJsonPath)).toBe(false);
  });

  it('returns error when theme.json directory is not writable', () => {
    const designMdPath = join(tempDir, 'DESIGN.md');
    const themeJsonPath = join(tempDir, 'nonexistent-dir', 'deep', 'theme.json');

    writeFileSync(designMdPath, `---
colors:
  primary: "#ff0000"
---

# Test
`, 'utf-8');

    const result = transferTokens({ designMdPath, themeJsonPath });

    expect(result.success).toBe(false);
    expect(result.errors.length).toBeGreaterThan(0);
  });

  it('returns error when DESIGN.md has no tokens', () => {
    const designMdPath = join(tempDir, 'DESIGN.md');
    const themeJsonPath = join(tempDir, 'theme.json');

    writeFileSync(designMdPath, `---
name: "Empty Design"
---

# No tokens here
`, 'utf-8');

    const result = transferTokens({ designMdPath, themeJsonPath });

    expect(result.success).toBe(false);
    expect(result.errors[0]).toContain('No tokens found');
  });

  it('overwrites existing theme.json when overwrite=true', () => {
    const designMdPath = join(tempDir, 'DESIGN.md');
    const themeJsonPath = join(tempDir, 'theme.json');

    // Write existing theme.json
    writeFileSync(themeJsonPath, JSON.stringify({
      $schema: 'https://schemas.wp.org/wp/6.5/theme.json',
      version: 2,
      settings: {
        color: { palette: [{ slug: 'old', color: '#000', name: 'Old' }] },
      },
    }, null, 2), 'utf-8');

    writeFileSync(designMdPath, `---
colors:
  primary: "#ff0000"
  secondary: "#00ff00"
---

# Test
`, 'utf-8');

    const result = transferTokens({ designMdPath, themeJsonPath, overwrite: true });

    expect(result.success).toBe(true);
    const themeJson = JSON.parse(readFileSync(themeJsonPath, 'utf-8'));
    // Old palette should be replaced
    expect(themeJson.settings.color.palette).toHaveLength(2);
    expect(themeJson.settings.color.palette[0].slug).toBe('primary');
  });
});
