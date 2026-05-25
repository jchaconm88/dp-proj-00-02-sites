import { describe, it, expect } from 'vitest';
import * as fc from 'fast-check';
import { validateDesignDirectory, REQUIRED_FILES } from './validate-structure-helpers.js';

/**
 * Property 2: Design Directory Structure Validation
 * Validates: Requirements 2.2, 2.3
 */

// Generator for arbitrary extra filenames that are NOT one of the required files
const extraFileArb = fc
  .string({ minLength: 1, maxLength: 50 })
  .filter((s) => !REQUIRED_FILES.includes(s) && s.length > 0);

const extraFilesArb = fc.array(extraFileArb, { minSize: 0, maxSize: 20 });

describe('Property 2: Design Directory Structure Validation', () => {
  it('complete directories always pass — any file array containing all 3 required files returns valid: true', () => {
    fc.assert(
      fc.property(extraFilesArb, (extras) => {
        const files = [...REQUIRED_FILES, ...extras];
        const result = validateDesignDirectory(files);
        expect(result).toEqual({ valid: true });
      }),
      { numRuns: 200 }
    );
  });

  it('missing any required file always fails — file arrays missing at least one required file return valid: false', () => {
    // Generate a non-empty subset of required files to remove
    const subsetToRemoveArb = fc
      .subarray(REQUIRED_FILES, { minLength: 1, maxLength: REQUIRED_FILES.length })
      .filter((arr) => arr.length > 0);

    fc.assert(
      fc.property(subsetToRemoveArb, extraFilesArb, (removed, extras) => {
        const remaining = REQUIRED_FILES.filter((f) => !removed.includes(f));
        const files = [...remaining, ...extras];
        const result = validateDesignDirectory(files);
        expect(result.valid).toBe(false);
        expect(result.missingFiles.length).toBeGreaterThan(0);
      }),
      { numRuns: 200 }
    );
  });

  it('missing files are correctly identified — missingFiles contains exactly the removed required files', () => {
    const subsetToRemoveArb = fc
      .subarray(REQUIRED_FILES, { minLength: 1, maxLength: REQUIRED_FILES.length })
      .filter((arr) => arr.length > 0);

    fc.assert(
      fc.property(subsetToRemoveArb, extraFilesArb, (removed, extras) => {
        const remaining = REQUIRED_FILES.filter((f) => !removed.includes(f));
        const files = [...remaining, ...extras];
        const result = validateDesignDirectory(files);
        expect(result.valid).toBe(false);
        // missingFiles should contain exactly the removed files (order-independent)
        expect(result.missingFiles.sort()).toEqual([...removed].sort());
      }),
      { numRuns: 200 }
    );
  });

  it('extra files do not affect validation — adding arbitrary extra filenames to a complete set does not change the result', () => {
    fc.assert(
      fc.property(extraFilesArb, (extras) => {
        const withoutExtras = validateDesignDirectory([...REQUIRED_FILES]);
        const withExtras = validateDesignDirectory([...REQUIRED_FILES, ...extras]);
        expect(withoutExtras).toEqual({ valid: true });
        expect(withExtras).toEqual({ valid: true });
      }),
      { numRuns: 200 }
    );
  });
});
