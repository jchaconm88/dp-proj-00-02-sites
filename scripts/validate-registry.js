/**
 * Client registry entry validation for clients.json.
 * Validates individual entries and the overall registry structure.
 *
 * Usage as CLI:
 *   node scripts/validate-registry.js [path-to-clients.json]
 *
 * Exports:
 *   - validateRegistryEntry(entry) — validates a single client entry
 *   - validateRegistry(registryData) — validates the entire clients.json structure
 */

import { validateHostname } from './validate-hostname.js';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const ALLOWED_STATUSES = ['diseño', 'desarrollo', 'revisión', 'producción', 'archivado'];
const REQUIRED_FIELDS = ['name', 'hostname', 'category', 'status', 'templatePath', 'frontPath'];

/**
 * Validates a single client registry entry.
 * @param {object} entry - The client entry to validate
 * @returns {{ valid: boolean, errors?: string[] }} Validation result
 */
export function validateRegistryEntry(entry) {
  const errors = [];

  if (entry === null || typeof entry !== 'object' || Array.isArray(entry)) {
    return { valid: false, errors: ['Entry must be a non-null object'] };
  }

  // Check required fields are present
  for (const field of REQUIRED_FIELDS) {
    if (!(field in entry) || entry[field] === undefined || entry[field] === null) {
      errors.push(`Missing required field: "${field}"`);
    }
  }

  // Validate name: non-empty string
  if ('name' in entry && entry.name !== undefined && entry.name !== null) {
    if (typeof entry.name !== 'string' || entry.name.trim().length === 0) {
      errors.push('Field "name" must be a non-empty string');
    }
  }

  // Validate hostname format
  if ('hostname' in entry && entry.hostname !== undefined && entry.hostname !== null) {
    if (typeof entry.hostname !== 'string') {
      errors.push('Field "hostname" must be a string');
    } else {
      const hostnameResult = validateHostname(entry.hostname);
      if (!hostnameResult.valid) {
        errors.push(`Invalid hostname: ${hostnameResult.error}`);
      }
    }
  }

  // Validate category: non-empty lowercase string without spaces
  if ('category' in entry && entry.category !== undefined && entry.category !== null) {
    if (typeof entry.category !== 'string' || entry.category.trim().length === 0) {
      errors.push('Field "category" must be a non-empty string');
    } else if (entry.category !== entry.category.toLowerCase()) {
      errors.push('Field "category" must be lowercase');
    } else if (/\s/.test(entry.category)) {
      errors.push('Field "category" must not contain spaces');
    }
  }

  // Validate status: must be one of allowed values
  if ('status' in entry && entry.status !== undefined && entry.status !== null) {
    if (typeof entry.status !== 'string') {
      errors.push('Field "status" must be a string');
    } else if (!ALLOWED_STATUSES.includes(entry.status)) {
      errors.push(`Field "status" must be one of: ${ALLOWED_STATUSES.join(', ')} (got "${entry.status}")`);
    }
  }

  // Validate templatePath: non-empty string
  if ('templatePath' in entry && entry.templatePath !== undefined && entry.templatePath !== null) {
    if (typeof entry.templatePath !== 'string' || entry.templatePath.trim().length === 0) {
      errors.push('Field "templatePath" must be a non-empty string');
    }
  }

  // Validate frontPath: non-empty string
  if ('frontPath' in entry && entry.frontPath !== undefined && entry.frontPath !== null) {
    if (typeof entry.frontPath !== 'string' || entry.frontPath.trim().length === 0) {
      errors.push('Field "frontPath" must be a non-empty string');
    }
  }

  if (errors.length === 0) {
    return { valid: true };
  }

  return { valid: false, errors };
}

/**
 * Validates the entire clients.json registry structure.
 * @param {object} registryData - The parsed clients.json content
 * @returns {{ valid: boolean, errors?: string[] }} Validation result
 */
export function validateRegistry(registryData) {
  const errors = [];

  if (registryData === null || typeof registryData !== 'object' || Array.isArray(registryData)) {
    return { valid: false, errors: ['Registry data must be a non-null object'] };
  }

  // Check statusValues array exists
  if (!Array.isArray(registryData.statusValues)) {
    errors.push('Registry must contain a "statusValues" array');
  }

  // Check categories array exists
  if (!Array.isArray(registryData.categories)) {
    errors.push('Registry must contain a "categories" array');
  }

  // Check clients array exists
  if (!Array.isArray(registryData.clients)) {
    errors.push('Registry must contain a "clients" array');
  } else {
    // Validate each client entry
    for (let i = 0; i < registryData.clients.length; i++) {
      const entryResult = validateRegistryEntry(registryData.clients[i]);
      if (!entryResult.valid) {
        for (const error of entryResult.errors) {
          errors.push(`Client [${i}]: ${error}`);
        }
      }
    }
  }

  if (errors.length === 0) {
    return { valid: true };
  }

  return { valid: false, errors };
}

// CLI mode: validate a clients.json file
const currentFile = new URL(import.meta.url).pathname.replace(/^\/([A-Z]:)/, '$1');
const isMainModule = process.argv[1] && resolve(process.argv[1]) === resolve(currentFile);

if (isMainModule) {
  const filePath = process.argv[2] || 'clients.json';
  const resolvedPath = resolve(filePath);

  try {
    const content = readFileSync(resolvedPath, 'utf-8');
    const data = JSON.parse(content);
    const result = validateRegistry(data);

    if (result.valid) {
      console.log(`✓ Registry validation passed: ${resolvedPath}`);
      process.exit(0);
    } else {
      console.error(`✗ Registry validation failed: ${resolvedPath}`);
      for (const error of result.errors) {
        console.error(`  - ${error}`);
      }
      process.exit(1);
    }
  } catch (err) {
    if (err.code === 'ENOENT') {
      console.error(`✗ File not found: ${resolvedPath}`);
    } else if (err instanceof SyntaxError) {
      console.error(`✗ Invalid JSON in: ${resolvedPath}`);
      console.error(`  ${err.message}`);
    } else {
      console.error(`✗ Error reading file: ${err.message}`);
    }
    process.exit(1);
  }
}
