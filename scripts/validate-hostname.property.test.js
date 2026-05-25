import { describe, it, expect } from 'vitest';
import * as fc from 'fast-check';
import { validateHostname } from './validate-hostname.js';

/**
 * Property-based tests for hostname validation.
 * Validates: Requirements 1.4, 1.6, 2.1, 3.1
 */
describe('validateHostname - Property Tests', () => {
  const alphanumChars = 'abcdefghijklmnopqrstuvwxyz0123456789'.split('');
  const alphaChars = 'abcdefghijklmnopqrstuvwxyz'.split('');

  /**
   * Generator for valid hostname segments (lowercase alphanumeric, 1-63 chars).
   * Segments don't start/end with hyphen — using only alphanumeric keeps it simple.
   */
  const validSegment = (minLen = 1, maxLen = 10) =>
    fc.array(fc.constantFrom(...alphanumChars), { minLength: Math.max(1, minLen), maxLength: maxLen })
      .map((chars) => chars.join(''));

  /**
   * Generator for valid TLDs (2+ lowercase alpha chars).
   */
  const validTld = fc.array(fc.constantFrom(...alphaChars), { minLength: 2, maxLength: 6 })
    .map((chars) => chars.join(''));

  /**
   * Generator for valid hostnames: 2+ segments joined by dots,
   * TLD is 2+ alpha chars, each segment 1-63 chars, total ≤ 253.
   */
  const validHostnameArb = fc
    .tuple(
      fc.array(validSegment(1, 10), { minLength: 1, maxLength: 4 }),
      validTld
    )
    .map(([segments, tld]) => [...segments, tld].join('.'))
    .filter((h) => h.length <= 253);

  // Property 1: Valid hostnames always pass
  it('valid hostnames always pass validation', () => {
    fc.assert(
      fc.property(validHostnameArb, (hostname) => {
        const result = validateHostname(hostname);
        expect(result).toEqual({ valid: true });
      }),
      { numRuns: 200 }
    );
  });

  // Property 2: Uppercase hostnames always fail
  it('uppercase hostnames always fail validation', () => {
    const uppercasedHostname = validHostnameArb.chain((hostname) =>
      fc.nat({ max: hostname.length - 1 }).map((idx) => {
        const chars = hostname.split('');
        // Uppercase at least one alpha character
        for (let i = idx; i < chars.length; i++) {
          if (/[a-z]/.test(chars[i])) {
            chars[i] = chars[i].toUpperCase();
            return chars.join('');
          }
        }
        // Wrap around if no alpha found after idx
        for (let i = 0; i < idx; i++) {
          if (/[a-z]/.test(chars[i])) {
            chars[i] = chars[i].toUpperCase();
            return chars.join('');
          }
        }
        return hostname; // fallback (shouldn't happen with valid hostnames)
      })
    ).filter((h) => h !== h.toLowerCase());

    fc.assert(
      fc.property(uppercasedHostname, (hostname) => {
        const result = validateHostname(hostname);
        expect(result.valid).toBe(false);
      }),
      { numRuns: 200 }
    );
  });

  // Property 3: Hostnames without TLD (single-segment) always fail
  it('hostnames without TLD (single segment) always fail validation', () => {
    const singleSegment = fc
      .array(fc.constantFrom(...'abcdefghijklmnopqrstuvwxyz0123456789-'.split('')), { minLength: 1, maxLength: 63 })
      .map((chars) => chars.join(''))
      .filter((s) => !s.includes('.'));

    fc.assert(
      fc.property(singleSegment, (hostname) => {
        const result = validateHostname(hostname);
        expect(result.valid).toBe(false);
      }),
      { numRuns: 200 }
    );
  });

  // Property 4: Hostnames with invalid characters always fail
  it('hostnames with invalid characters always fail validation', () => {
    const invalidChars = '_!@#$%^&*()+=[]{}|\\:;"\'<>,?/ ';
    const hostnameWithInvalidChar = fc
      .tuple(
        validHostnameArb,
        fc.nat().map((n) => n),
        fc.constantFrom(...invalidChars.split(''))
      )
      .map(([hostname, pos, invalidChar]) => {
        const idx = pos % hostname.length;
        return hostname.slice(0, idx) + invalidChar + hostname.slice(idx);
      })
      .filter((h) => h === h.toLowerCase()); // keep lowercase to isolate invalid char property

    fc.assert(
      fc.property(hostnameWithInvalidChar, (hostname) => {
        const result = validateHostname(hostname);
        expect(result.valid).toBe(false);
      }),
      { numRuns: 200 }
    );
  });

  // Property 5: Segments starting or ending with hyphen always fail
  it('segments starting or ending with hyphen always fail validation', () => {
    const hostnameWithHyphenEdge = fc
      .tuple(
        validHostnameArb,
        fc.boolean() // true = prepend hyphen, false = append hyphen
      )
      .map(([hostname, prepend]) => {
        const segments = hostname.split('.');
        // Pick a non-TLD segment to modify (or the first segment)
        const targetIdx = 0;
        if (prepend) {
          segments[targetIdx] = '-' + segments[targetIdx];
        } else {
          segments[targetIdx] = segments[targetIdx] + '-';
        }
        return segments.join('.');
      })
      .filter((h) => h === h.toLowerCase() && h.length <= 253);

    fc.assert(
      fc.property(hostnameWithHyphenEdge, (hostname) => {
        const result = validateHostname(hostname);
        expect(result.valid).toBe(false);
      }),
      { numRuns: 200 }
    );
  });

  // Property 6: Hostnames exceeding 253 characters always fail
  it('hostnames exceeding 253 characters always fail validation', () => {
    // Generate a hostname that is guaranteed to be > 253 chars
    const longHostname = fc
      .array(validSegment(10, 50), { minLength: 6, maxLength: 12 })
      .chain((segments) =>
        validTld.map((tld) => [...segments, tld].join('.'))
      )
      .filter((h) => h.length > 253 && h === h.toLowerCase());

    fc.assert(
      fc.property(longHostname, (hostname) => {
        const result = validateHostname(hostname);
        expect(result.valid).toBe(false);
      }),
      { numRuns: 100 }
    );
  });
});
