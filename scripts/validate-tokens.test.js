import { describe, it, expect, beforeEach, afterEach } from "vitest";
import { validateTokens } from "./validate-tokens.js";
import { writeFileSync, mkdirSync, rmSync } from "node:fs";
import { join } from "node:path";
import { tmpdir } from "node:os";

const TEST_DIR = join(tmpdir(), "validate-tokens-test-" + Date.now());

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
  if (tokens.rounded) {
    yamlLines.push("rounded:");
    for (const [k, v] of Object.entries(tokens.rounded)) {
      yamlLines.push(`  ${k}: "${v}"`);
    }
  }
  return `---\n${yamlLines.join("\n")}\n---\n\n# Design Document\n`;
}

function createThemeJson(settings = {}, styles = {}) {
  return JSON.stringify({ settings, styles }, null, 2);
}

beforeEach(() => {
  mkdirSync(TEST_DIR, { recursive: true });
});

afterEach(() => {
  rmSync(TEST_DIR, { recursive: true, force: true });
});

describe("validateTokens", () => {
  it("returns PASS when all color tokens match", () => {
    const designMdPath = join(TEST_DIR, "DESIGN.md");
    const themeJsonPath = join(TEST_DIR, "theme.json");

    writeFileSync(designMdPath, createDesignMd({
      colors: { primary: "#006e01", secondary: "#ff5500" },
    }));

    writeFileSync(themeJsonPath, createThemeJson({
      color: {
        palette: [
          { slug: "primary", color: "#006e01", name: "Primary" },
          { slug: "secondary", color: "#ff5500", name: "Secondary" },
        ],
      },
    }));

    const result = validateTokens({ designMdPath, themeJsonPath });
    expect(result.result).toBe("PASS");
    expect(result.discrepancies).toHaveLength(0);
    expect(result.totalTokens).toBe(2);
    expect(result.matchedTokens).toBe(2);
  });

  it("returns FAIL when a color token differs", () => {
    const designMdPath = join(TEST_DIR, "DESIGN.md");
    const themeJsonPath = join(TEST_DIR, "theme.json");

    writeFileSync(designMdPath, createDesignMd({
      colors: { primary: "#006e01", secondary: "#ff5500" },
    }));

    writeFileSync(themeJsonPath, createThemeJson({
      color: {
        palette: [
          { slug: "primary", color: "#000000", name: "Primary" },
          { slug: "secondary", color: "#ff5500", name: "Secondary" },
        ],
      },
    }));

    const result = validateTokens({ designMdPath, themeJsonPath });
    expect(result.result).toBe("FAIL");
    expect(result.discrepancies).toHaveLength(1);
    expect(result.discrepancies[0]).toEqual({
      token: "colors.primary",
      expected: "#006e01",
      actual: "#000000",
    });
    expect(result.totalTokens).toBe(2);
    expect(result.matchedTokens).toBe(1);
  });

  it("returns FAIL when a color slug is missing from theme.json", () => {
    const designMdPath = join(TEST_DIR, "DESIGN.md");
    const themeJsonPath = join(TEST_DIR, "theme.json");

    writeFileSync(designMdPath, createDesignMd({
      colors: { primary: "#006e01", accent: "#abcdef" },
    }));

    writeFileSync(themeJsonPath, createThemeJson({
      color: {
        palette: [
          { slug: "primary", color: "#006e01", name: "Primary" },
        ],
      },
    }));

    const result = validateTokens({ designMdPath, themeJsonPath });
    expect(result.result).toBe("FAIL");
    expect(result.discrepancies).toHaveLength(1);
    expect(result.discrepancies[0].token).toBe("colors.accent");
    expect(result.discrepancies[0].actual).toBeNull();
  });

  it("validates typography fontFamily tokens", () => {
    const designMdPath = join(TEST_DIR, "DESIGN.md");
    const themeJsonPath = join(TEST_DIR, "theme.json");

    writeFileSync(designMdPath, createDesignMd({
      typography: {
        heading: { fontFamily: "Inter, sans-serif" },
        body: { fontFamily: "Roboto, sans-serif" },
      },
    }));

    writeFileSync(themeJsonPath, createThemeJson({
      typography: {
        fontFamilies: [
          { slug: "heading", fontFamily: "Inter, sans-serif", name: "Heading" },
          { slug: "body", fontFamily: "Roboto, sans-serif", name: "Body" },
        ],
      },
    }));

    const result = validateTokens({ designMdPath, themeJsonPath });
    expect(result.result).toBe("PASS");
    expect(result.totalTokens).toBe(2);
    expect(result.matchedTokens).toBe(2);
  });

  it("detects fontFamily discrepancy", () => {
    const designMdPath = join(TEST_DIR, "DESIGN.md");
    const themeJsonPath = join(TEST_DIR, "theme.json");

    writeFileSync(designMdPath, createDesignMd({
      typography: {
        heading: { fontFamily: "Inter, sans-serif" },
      },
    }));

    writeFileSync(themeJsonPath, createThemeJson({
      typography: {
        fontFamilies: [
          { slug: "heading", fontFamily: "Arial, sans-serif", name: "Heading" },
        ],
      },
    }));

    const result = validateTokens({ designMdPath, themeJsonPath });
    expect(result.result).toBe("FAIL");
    expect(result.discrepancies[0].token).toBe("typography.heading.fontFamily");
    expect(result.discrepancies[0].expected).toBe("Inter, sans-serif");
    expect(result.discrepancies[0].actual).toBe("Arial, sans-serif");
  });

  it("validates typography fontSize tokens", () => {
    const designMdPath = join(TEST_DIR, "DESIGN.md");
    const themeJsonPath = join(TEST_DIR, "theme.json");

    writeFileSync(designMdPath, createDesignMd({
      typography: {
        small: { fontSize: "14px" },
        large: { fontSize: "24px" },
      },
    }));

    writeFileSync(themeJsonPath, createThemeJson({
      typography: {
        fontSizes: [
          { slug: "small", size: "14px", name: "Small" },
          { slug: "large", size: "24px", name: "Large" },
        ],
      },
    }));

    const result = validateTokens({ designMdPath, themeJsonPath });
    expect(result.result).toBe("PASS");
    expect(result.totalTokens).toBe(2);
  });

  it("detects fontSize discrepancy", () => {
    const designMdPath = join(TEST_DIR, "DESIGN.md");
    const themeJsonPath = join(TEST_DIR, "theme.json");

    writeFileSync(designMdPath, createDesignMd({
      typography: {
        small: { fontSize: "14px" },
      },
    }));

    writeFileSync(themeJsonPath, createThemeJson({
      typography: {
        fontSizes: [
          { slug: "small", size: "16px", name: "Small" },
        ],
      },
    }));

    const result = validateTokens({ designMdPath, themeJsonPath });
    expect(result.result).toBe("FAIL");
    expect(result.discrepancies[0].token).toBe("typography.small.fontSize");
  });

  it("validates spacing tokens", () => {
    const designMdPath = join(TEST_DIR, "DESIGN.md");
    const themeJsonPath = join(TEST_DIR, "theme.json");

    writeFileSync(designMdPath, createDesignMd({
      spacing: { small: "8px", medium: "16px", large: "32px" },
    }));

    writeFileSync(themeJsonPath, createThemeJson({
      spacing: {
        spacingSizes: [
          { slug: "small", size: "8px", name: "Small" },
          { slug: "medium", size: "16px", name: "Medium" },
          { slug: "large", size: "32px", name: "Large" },
        ],
      },
    }));

    const result = validateTokens({ designMdPath, themeJsonPath });
    expect(result.result).toBe("PASS");
    expect(result.totalTokens).toBe(3);
    expect(result.matchedTokens).toBe(3);
  });

  it("detects spacing discrepancy", () => {
    const designMdPath = join(TEST_DIR, "DESIGN.md");
    const themeJsonPath = join(TEST_DIR, "theme.json");

    writeFileSync(designMdPath, createDesignMd({
      spacing: { medium: "16px" },
    }));

    writeFileSync(themeJsonPath, createThemeJson({
      spacing: {
        spacingSizes: [
          { slug: "medium", size: "24px", name: "Medium" },
        ],
      },
    }));

    const result = validateTokens({ designMdPath, themeJsonPath });
    expect(result.result).toBe("FAIL");
    expect(result.discrepancies[0].token).toBe("spacing.medium");
    expect(result.discrepancies[0].expected).toBe("16px");
    expect(result.discrepancies[0].actual).toBe("24px");
  });

  it("validates rounded (border radius) tokens", () => {
    const designMdPath = join(TEST_DIR, "DESIGN.md");
    const themeJsonPath = join(TEST_DIR, "theme.json");

    writeFileSync(designMdPath, createDesignMd({
      rounded: { button: "8px" },
    }));

    writeFileSync(themeJsonPath, createThemeJson(
      {},
      {
        blocks: {
          "core/button": { border: { radius: "8px" } },
        },
      }
    ));

    const result = validateTokens({ designMdPath, themeJsonPath });
    expect(result.result).toBe("PASS");
    expect(result.totalTokens).toBe(1);
    expect(result.matchedTokens).toBe(1);
  });

  it("detects rounded discrepancy", () => {
    const designMdPath = join(TEST_DIR, "DESIGN.md");
    const themeJsonPath = join(TEST_DIR, "theme.json");

    writeFileSync(designMdPath, createDesignMd({
      rounded: { button: "8px" },
    }));

    writeFileSync(themeJsonPath, createThemeJson(
      {},
      {
        blocks: {
          "core/button": { border: { radius: "4px" } },
        },
      }
    ));

    const result = validateTokens({ designMdPath, themeJsonPath });
    expect(result.result).toBe("FAIL");
    expect(result.discrepancies[0].token).toBe("rounded.button");
    expect(result.discrepancies[0].expected).toBe("8px");
    expect(result.discrepancies[0].actual).toBe("4px");
  });

  it("returns FAIL when DESIGN.md has no frontmatter", () => {
    const designMdPath = join(TEST_DIR, "DESIGN.md");
    const themeJsonPath = join(TEST_DIR, "theme.json");

    writeFileSync(designMdPath, "# No frontmatter here\n");
    writeFileSync(themeJsonPath, createThemeJson({}));

    const result = validateTokens({ designMdPath, themeJsonPath });
    expect(result.result).toBe("FAIL");
    expect(result.totalTokens).toBe(0);
  });

  it("handles combined tokens — all matching", () => {
    const designMdPath = join(TEST_DIR, "DESIGN.md");
    const themeJsonPath = join(TEST_DIR, "theme.json");

    writeFileSync(designMdPath, createDesignMd({
      colors: { primary: "#111", secondary: "#222" },
      typography: {
        heading: { fontFamily: "Inter", fontSize: "32px" },
      },
      spacing: { base: "8px" },
      rounded: { button: "4px" },
    }));

    writeFileSync(themeJsonPath, createThemeJson(
      {
        color: {
          palette: [
            { slug: "primary", color: "#111" },
            { slug: "secondary", color: "#222" },
          ],
        },
        typography: {
          fontFamilies: [{ slug: "heading", fontFamily: "Inter" }],
          fontSizes: [{ slug: "heading", size: "32px" }],
        },
        spacing: {
          spacingSizes: [{ slug: "base", size: "8px" }],
        },
      },
      {
        blocks: {
          "core/button": { border: { radius: "4px" } },
        },
      }
    ));

    const result = validateTokens({ designMdPath, themeJsonPath });
    expect(result.result).toBe("PASS");
    expect(result.totalTokens).toBe(6);
    expect(result.matchedTokens).toBe(6);
    expect(result.discrepancies).toHaveLength(0);
  });

  it("handles combined tokens — multiple discrepancies", () => {
    const designMdPath = join(TEST_DIR, "DESIGN.md");
    const themeJsonPath = join(TEST_DIR, "theme.json");

    writeFileSync(designMdPath, createDesignMd({
      colors: { primary: "#111" },
      spacing: { base: "8px" },
    }));

    writeFileSync(themeJsonPath, createThemeJson({
      color: {
        palette: [{ slug: "primary", color: "#999" }],
      },
      spacing: {
        spacingSizes: [{ slug: "base", size: "16px" }],
      },
    }));

    const result = validateTokens({ designMdPath, themeJsonPath });
    expect(result.result).toBe("FAIL");
    expect(result.discrepancies).toHaveLength(2);
    expect(result.totalTokens).toBe(2);
    expect(result.matchedTokens).toBe(0);
  });
});
