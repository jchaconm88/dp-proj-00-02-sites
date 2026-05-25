import { describe, it, expect } from 'vitest';
import { validateRegistryEntry, validateRegistry } from './validate-registry.js';

describe('validateRegistryEntry', () => {
  const validEntry = {
    name: 'Mi Cliente',
    hostname: 'mi-cliente.local',
    category: 'ecommerce',
    status: 'desarrollo',
    templatePath: 'templates/ecommerce/mi-cliente.local/',
    frontPath: 'front/ecommerce/mi-cliente.local/',
  };

  it('returns valid for a correct entry', () => {
    const result = validateRegistryEntry(validEntry);
    expect(result).toEqual({ valid: true });
  });

  it('reports all missing required fields for empty object', () => {
    const result = validateRegistryEntry({});
    expect(result.valid).toBe(false);
    expect(result.errors).toContain('Missing required field: "name"');
    expect(result.errors).toContain('Missing required field: "hostname"');
    expect(result.errors).toContain('Missing required field: "category"');
    expect(result.errors).toContain('Missing required field: "status"');
    expect(result.errors).toContain('Missing required field: "templatePath"');
    expect(result.errors).toContain('Missing required field: "frontPath"');
  });

  it('rejects non-object entries', () => {
    expect(validateRegistryEntry(null).valid).toBe(false);
    expect(validateRegistryEntry([]).valid).toBe(false);
    expect(validateRegistryEntry('string').valid).toBe(false);
  });

  it('validates name is a non-empty string', () => {
    const result = validateRegistryEntry({ ...validEntry, name: '' });
    expect(result.valid).toBe(false);
    expect(result.errors).toContain('Field "name" must be a non-empty string');
  });

  it('validates hostname format', () => {
    const result = validateRegistryEntry({ ...validEntry, hostname: 'INVALID' });
    expect(result.valid).toBe(false);
    expect(result.errors.some(e => e.includes('Invalid hostname'))).toBe(true);
  });

  it('validates category is lowercase without spaces', () => {
    const upper = validateRegistryEntry({ ...validEntry, category: 'Ecommerce' });
    expect(upper.valid).toBe(false);
    expect(upper.errors).toContain('Field "category" must be lowercase');

    const spaces = validateRegistryEntry({ ...validEntry, category: 'e commerce' });
    expect(spaces.valid).toBe(false);
    expect(spaces.errors).toContain('Field "category" must not contain spaces');
  });

  it('validates status is one of allowed values', () => {
    const result = validateRegistryEntry({ ...validEntry, status: 'invalid' });
    expect(result.valid).toBe(false);
    expect(result.errors.some(e => e.includes('must be one of'))).toBe(true);
  });

  it('accepts all 5 valid status values', () => {
    const statuses = ['diseño', 'desarrollo', 'revisión', 'producción', 'archivado'];
    for (const status of statuses) {
      const result = validateRegistryEntry({ ...validEntry, status });
      expect(result.valid).toBe(true);
    }
  });

  it('validates templatePath is a non-empty string', () => {
    const result = validateRegistryEntry({ ...validEntry, templatePath: '' });
    expect(result.valid).toBe(false);
    expect(result.errors).toContain('Field "templatePath" must be a non-empty string');
  });

  it('validates frontPath is a non-empty string', () => {
    const result = validateRegistryEntry({ ...validEntry, frontPath: '' });
    expect(result.valid).toBe(false);
    expect(result.errors).toContain('Field "frontPath" must be a non-empty string');
  });
});

describe('validateRegistry', () => {
  const validRegistry = {
    statusValues: ['diseño', 'desarrollo', 'revisión', 'producción', 'archivado'],
    categories: ['ecommerce', 'portafolio', 'landing-page'],
    clients: [
      {
        name: 'Mi Cliente',
        hostname: 'mi-cliente.local',
        category: 'ecommerce',
        status: 'desarrollo',
        templatePath: 'templates/ecommerce/mi-cliente.local/',
        frontPath: 'front/ecommerce/mi-cliente.local/',
      },
    ],
  };

  it('returns valid for a correct registry', () => {
    const result = validateRegistry(validRegistry);
    expect(result).toEqual({ valid: true });
  });

  it('rejects non-object input', () => {
    expect(validateRegistry(null).valid).toBe(false);
    expect(validateRegistry([]).valid).toBe(false);
  });

  it('requires statusValues array', () => {
    const { statusValues, ...rest } = validRegistry;
    const result = validateRegistry(rest);
    expect(result.valid).toBe(false);
    expect(result.errors).toContain('Registry must contain a "statusValues" array');
  });

  it('requires categories array', () => {
    const { categories, ...rest } = validRegistry;
    const result = validateRegistry(rest);
    expect(result.valid).toBe(false);
    expect(result.errors).toContain('Registry must contain a "categories" array');
  });

  it('requires clients array', () => {
    const { clients, ...rest } = validRegistry;
    const result = validateRegistry(rest);
    expect(result.valid).toBe(false);
    expect(result.errors).toContain('Registry must contain a "clients" array');
  });

  it('validates each client entry and reports index', () => {
    const registry = {
      ...validRegistry,
      clients: [{ name: 'Bad Entry' }],
    };
    const result = validateRegistry(registry);
    expect(result.valid).toBe(false);
    expect(result.errors.some(e => e.startsWith('Client [0]:'))).toBe(true);
  });

  it('accepts empty clients array', () => {
    const result = validateRegistry({ ...validRegistry, clients: [] });
    expect(result).toEqual({ valid: true });
  });
});
