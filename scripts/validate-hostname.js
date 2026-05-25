/**
 * Hostname format validation utility.
 * Validates hostnames according to RFC 952/1123 rules:
 * - Lowercase only
 * - Alphanumeric characters and hyphens
 * - Dots as segment separators
 * - Max 63 characters per segment
 * - Max 253 characters total
 * - Valid TLD (at least 2 alpha characters)
 * - Segments cannot start or end with hyphens
 *
 * @module validate-hostname
 */

import { fileURLToPath } from 'url';
import { resolve } from 'path';

/**
 * Validates a hostname string.
 * @param {string} hostname - The hostname to validate
 * @returns {{ valid: true } | { valid: false, error: string }} Validation result
 */
export function validateHostname(hostname) {
  if (typeof hostname !== 'string' || hostname.length === 0) {
    return { valid: false, error: 'Hostname must be a non-empty string' };
  }

  if (hostname !== hostname.toLowerCase()) {
    return { valid: false, error: 'Hostname must be lowercase' };
  }

  if (hostname.length > 253) {
    return { valid: false, error: `Hostname exceeds maximum length of 253 characters (got ${hostname.length})` };
  }

  const segments = hostname.split('.');

  if (segments.length < 2) {
    return { valid: false, error: 'Hostname must have at least two segments (e.g., "example.local")' };
  }

  for (const segment of segments) {
    if (segment.length === 0) {
      return { valid: false, error: 'Hostname segments cannot be empty (consecutive dots or leading/trailing dot)' };
    }

    if (segment.length > 63) {
      return { valid: false, error: `Segment "${segment}" exceeds maximum length of 63 characters (got ${segment.length})` };
    }

    if (!/^[a-z0-9-]+$/.test(segment)) {
      return { valid: false, error: `Segment "${segment}" contains invalid characters (only lowercase alphanumeric and hyphens allowed)` };
    }

    if (segment.startsWith('-') || segment.endsWith('-')) {
      return { valid: false, error: `Segment "${segment}" cannot start or end with a hyphen` };
    }
  }

  // Validate TLD: must be at least 2 alphabetic characters
  const tld = segments[segments.length - 1];
  if (!/^[a-z]{2,}$/.test(tld)) {
    return { valid: false, error: `TLD "${tld}" is invalid (must be at least 2 alphabetic characters)` };
  }

  return { valid: true };
}

// CLI support: node scripts/validate-hostname.js <hostname>
const __filename = fileURLToPath(import.meta.url);
const scriptArg = process.argv[1] ? resolve(process.argv[1]) : '';

if (__filename === scriptArg) {
  const hostname = process.argv[2];

  if (!hostname) {
    console.error('Usage: node scripts/validate-hostname.js <hostname>');
    process.exit(1);
  }

  const result = validateHostname(hostname);

  if (result.valid) {
    console.log(`\u2713 "${hostname}" is a valid hostname`);
    process.exit(0);
  } else {
    console.error(`\u2717 "${hostname}" is not a valid hostname: ${result.error}`);
    process.exit(1);
  }
}
