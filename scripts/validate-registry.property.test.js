/**
 * Property-based tests for validate-registry.js
 *
 * **Validates: Requirements 6.2, 6.4**
 *
 * Tests validateRegistryEntry and validateRegistry using fast-check
 * to verify properties hold across all valid/invalid inputs.
 */

import { describe, expect } from 'vitest';
import { it, fc } from '@fast-check/vitest';
import { validateRegistryEntry, validateRegistry } from './validate-registry.js';

const ALLOWED_STATUSES = ['diseño', 'desarrollo', 'revisión', 'producción', 'archivado'];

// --- Generators ---

/**
 * Generates a valid hostname segment (lowercase alphanumeric + hyphens,
 * not starting/ending with hyphen, 1-63 chars).
 */
const hostnameSegmentArb = fc
  .tuple(
    fc.stringMatching(/^[a-z0-9]{1,5}$/),
    fc.stringMatching(/^[a-z0-9\-]{0,10}$/),
    fc.stringMatching(/^[a-z0-9]{1,5}$/)
  )
  .map(([start, middle, end]) => start + middle + end);

/**
 * Generates a valid TLD (2+ lowercase alpha characters).
 */
const tldArb = fc.stringMatching(/^[a-z]{2,6}$/);

/**
 * Generates a valid hostname (segment.tld or segment.segment.tld).
 */
const validHostnameArb = fc
  .tuple(
    fc.array(hostnameSegmentArb, { minLength: 1, maxLength: 2 }),
    tldArb
  )
  .map(([segments, tld]) => [...segments, tld].join('.'));

/**
 * Generates a valid category (lowercase, no spaces, non-empty).
 */
const validCategoryArb = fc.stringMatching(/^[a-z0-9\-]{1,20}$/);

/**
 * Generates a valid status from the allowed values.
 */
const validStatusArb = fc.constantFrom(...ALLOWED_STATUSES);

/**
 * Generates a non-empty string for name, templatePath, frontPath.
 */
const nonEmptyStringArb = fc.stringMatching(/^[a-zA-Z0-9\/_\-. ]{1,50}$/).filter(s => s.trim().length > 0);

/**
 * Generates a fully valid registry entry.
 */
const validEntryArb = fc
  .tuple(nonEmptyStringArb, validHostnameArb, validCategoryArb, validStatusArb, nonEmptyStringArb, nonEmptyStringArb)
  .map(([name, hostname, category, status, templatePath, frontPath]) => ({
    name,
    hostname,
    category,
    status,
    templatePath,
    frontPath,
  }));

describe('Property 6: Client Registry Entry Validation', () => {
  describe('validateRegistryEntry', () => {
    it.prop([validEntryArb])(
      'Property 1: Valid entries always pass validation',
      (entry) => {
        const result = validateRegistryEntry(entry);
        expect(result.valid).toBe(true);
      }
    );

    it.prop([
      validEntryArb,
      fc.constantFrom('name', 'hostname', 'category', 'status', 'templatePath', 'frontPath'),
    ])(
      'Property 2: Missing any required field always fails validation',
      (entry, fieldToRemove) => {
        const incomplete = { ...entry };
        delete incomplete[fieldToRemove];
        const result = validateRegistryEntry(incomplete);
        expect(result.valid).toBe(false);
      }
    );

    it.prop([
      validEntryArb,
      fc.string({ minLength: 1, maxLength: 30 }).filter(
        (s) => !ALLOWED_STATUSES.includes(s)
      ),
    ])(
      'Property 3: Invalid status always fails validation',
      (entry, invalidStatus) => {
        const modified = { ...entry, status: invalidStatus };
        const result = validateRegistryEntry(modified);
        expect(result.valid).toBe(false);
      }
    );

    it.prop([
      validEntryArb,
      fc.oneof(
        // Uppercase characters in hostname
        fc.stringMatching(/^[a-zA-Z0-9.\-]{3,20}$/).filter(s => s !== s.toLowerCase()),
        // Special characters (not alphanumeric, hyphens, or dots)
        fc.stringMatching(/^.{3,20}$/).filter(s => /[^a-z0-9.\-]/.test(s)),
        // No TLD (single segment, no dots)
        fc.stringMatching(/^[a-z0-9]{1,20}$/)
      ),
    ])(
      'Property 4: Invalid hostname always fails validation',
      (entry, invalidHostname) => {
        const modified = { ...entry, hostname: invalidHostname };
        const result = validateRegistryEntry(modified);
        expect(result.valid).toBe(false);
      }
    );
  });

  describe('validateRegistry', () => {
    it.prop([
      fc.array(validEntryArb, { minLength: 0, maxLength: 5 }),
    ])(
      'Property 5: Valid registry with valid entries always passes',
      (clients) => {
        const registry = {
          statusValues: ALLOWED_STATUSES,
          categories: ['ecommerce', 'portafolio', 'landing-page'],
          clients,
        };
        const result = validateRegistry(registry);
        expect(result.valid).toBe(true);
      }
    );
  });
});
