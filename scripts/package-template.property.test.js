import { describe, it, expect } from 'vitest';
import * as fc from 'fast-check';
import { shouldIncludeFile } from './package-template-helpers.js';

/**
 * Property 3: Template Packaging Inclusion/Exclusion
 * Validates: Requirements 2.5
 */

// --- Generators ---

// Generate a valid filename stem (no dots, no slashes)
const fileNameStemArb = fc
  .stringMatching(/^[a-z][a-z0-9_-]{0,19}$/)
  .filter((s) => s.length > 0);

// Generate a subdirectory path (not partials/ or styles/)
const nonSpecialDirArb = fc
  .stringMatching(/^[a-z][a-z0-9_-]{0,9}$/)
  .filter((s) => s !== 'partials' && s !== 'styles' && s.length > 0);

// Generate an arbitrary extension that is NOT .html, .json, or .md
const otherExtensionArb = fc
  .constantFrom('.css', '.js', '.ts', '.png', '.jpg', '.svg', '.txt', '.xml', '.yaml', '.scss');

describe('Property 3: Template Packaging Inclusion/Exclusion', () => {
  it('HTML files are always included — any file with .html extension is included', () => {
    fc.assert(
      fc.property(
        fileNameStemArb,
        fc.option(nonSpecialDirArb, { nil: undefined }),
        (stem, dir) => {
          const path = dir ? `${dir}/${stem}.html` : `${stem}.html`;
          expect(shouldIncludeFile(path)).toBe(true);
        }
      ),
      { numRuns: 200 }
    );
  });

  it('JSON files are included (except .example.json) — regular .json files are included', () => {
    fc.assert(
      fc.property(
        fileNameStemArb.filter((s) => !s.endsWith('.example')),
        fc.option(nonSpecialDirArb, { nil: undefined }),
        (stem, dir) => {
          // Ensure the stem doesn't form an .example.json pattern
          const fileName = `${stem}.json`;
          if (fileName.endsWith('.example.json')) return; // skip edge case
          const path = dir ? `${dir}/${fileName}` : fileName;
          expect(shouldIncludeFile(path)).toBe(true);
        }
      ),
      { numRuns: 200 }
    );
  });

  it('.example.json files are always excluded — any file ending in .example.json is excluded', () => {
    fc.assert(
      fc.property(
        fileNameStemArb,
        fc.option(nonSpecialDirArb, { nil: undefined }),
        (stem, dir) => {
          const fileName = `${stem}.example.json`;
          const path = dir ? `${dir}/${fileName}` : fileName;
          expect(shouldIncludeFile(path)).toBe(false);
        }
      ),
      { numRuns: 200 }
    );
  });

  it('Markdown files are always excluded — any file with .md extension is excluded', () => {
    fc.assert(
      fc.property(
        fileNameStemArb,
        fc.option(nonSpecialDirArb, { nil: undefined }),
        (stem, dir) => {
          const path = dir ? `${dir}/${stem}.md` : `${stem}.md`;
          expect(shouldIncludeFile(path)).toBe(false);
        }
      ),
      { numRuns: 200 }
    );
  });

  it('Files in partials/ are always included — any file path starting with partials/ is included (unless excluded by another rule)', () => {
    fc.assert(
      fc.property(
        fileNameStemArb,
        otherExtensionArb,
        (stem, ext) => {
          // Files in partials/ with non-excluded extensions are included
          const path = `partials/${stem}${ext}`;
          expect(shouldIncludeFile(path)).toBe(true);
        }
      ),
      { numRuns: 200 }
    );
  });

  it('Files in partials/ with .md extension are excluded — exclusion rules take precedence over directory inclusion', () => {
    fc.assert(
      fc.property(fileNameStemArb, (stem) => {
        const path = `partials/${stem}.md`;
        expect(shouldIncludeFile(path)).toBe(false);
      }),
      { numRuns: 200 }
    );
  });

  it('Files in partials/ with .example.json are excluded — exclusion rules take precedence over directory inclusion', () => {
    fc.assert(
      fc.property(fileNameStemArb, (stem) => {
        const path = `partials/${stem}.example.json`;
        expect(shouldIncludeFile(path)).toBe(false);
      }),
      { numRuns: 200 }
    );
  });

  it('Files in styles/ are always included — any file path starting with styles/ is included (unless excluded by another rule)', () => {
    fc.assert(
      fc.property(
        fileNameStemArb,
        otherExtensionArb,
        (stem, ext) => {
          // Files in styles/ with non-excluded extensions are included
          const path = `styles/${stem}${ext}`;
          expect(shouldIncludeFile(path)).toBe(true);
        }
      ),
      { numRuns: 200 }
    );
  });

  it('Files in styles/ with .md extension are excluded — exclusion rules take precedence over directory inclusion', () => {
    fc.assert(
      fc.property(fileNameStemArb, (stem) => {
        const path = `styles/${stem}.md`;
        expect(shouldIncludeFile(path)).toBe(false);
      }),
      { numRuns: 200 }
    );
  });

  it('Files in styles/ with .example.json are excluded — exclusion rules take precedence over directory inclusion', () => {
    fc.assert(
      fc.property(fileNameStemArb, (stem) => {
        const path = `styles/${stem}.example.json`;
        expect(shouldIncludeFile(path)).toBe(false);
      }),
      { numRuns: 200 }
    );
  });
});
