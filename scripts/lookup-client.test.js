import { describe, it, expect } from 'vitest';
import { lookupClients, groupByCategory } from './lookup-client.js';

const sampleClients = [
  {
    name: 'Mi Cliente',
    hostname: 'mi-cliente.local',
    category: 'ecommerce',
    status: 'desarrollo',
    templatePath: 'templates/ecommerce/mi-cliente.local/',
    frontPath: 'front/ecommerce/mi-cliente.local/',
  },
  {
    name: 'Otro Cliente',
    hostname: 'otro-cliente.com',
    category: 'portafolio',
    status: 'producción',
    templatePath: 'templates/portafolio/otro-cliente.com/',
    frontPath: 'front/portafolio/otro-cliente.com/',
  },
  {
    name: 'Tienda ABC',
    hostname: 'abc-store.local',
    category: 'ecommerce',
    status: 'diseño',
    templatePath: 'templates/ecommerce/abc-store.local/',
    frontPath: 'front/ecommerce/abc-store.local/',
  },
  {
    name: 'Landing Pro',
    hostname: 'landing-pro.com',
    category: 'landing-page',
    status: 'archivado',
    templatePath: 'templates/landing-page/landing-pro.com/',
    frontPath: 'front/landing-page/landing-pro.com/',
  },
  {
    name: 'Zapatos Online',
    hostname: 'zapatos-online.pe',
    category: 'ecommerce',
    status: 'producción',
    templatePath: 'templates/ecommerce/zapatos-online.pe/',
    frontPath: 'front/ecommerce/zapatos-online.pe/',
  },
];

describe('lookupClients', () => {
  it('returns all clients when no filters provided', () => {
    const result = lookupClients(sampleClients, {});
    expect(result).toHaveLength(5);
  });

  it('filters by name (case-sensitive)', () => {
    const result = lookupClients(sampleClients, { name: 'Mi Cliente' });
    expect(result).toHaveLength(1);
    expect(result[0].hostname).toBe('mi-cliente.local');
  });

  it('does not match name case-insensitively', () => {
    const result = lookupClients(sampleClients, { name: 'mi cliente' });
    expect(result).toHaveLength(0);
  });

  it('filters by category (case-insensitive)', () => {
    const result = lookupClients(sampleClients, { category: 'Ecommerce' });
    expect(result).toHaveLength(3);
    result.forEach((c) => expect(c.category).toBe('ecommerce'));
  });

  it('filters by status (case-insensitive)', () => {
    const result = lookupClients(sampleClients, { status: 'Producción' });
    expect(result).toHaveLength(2);
    result.forEach((c) => expect(c.status).toBe('producción'));
  });

  it('applies AND logic when multiple filters provided', () => {
    const result = lookupClients(sampleClients, { category: 'ecommerce', status: 'producción' });
    expect(result).toHaveLength(1);
    expect(result[0].hostname).toBe('zapatos-online.pe');
  });

  it('returns empty array when no matches', () => {
    const result = lookupClients(sampleClients, { name: 'Nonexistent' });
    expect(result).toHaveLength(0);
  });

  it('returns results ordered alphabetically by hostname within category', () => {
    const result = lookupClients(sampleClients, { category: 'ecommerce' });
    expect(result[0].hostname).toBe('abc-store.local');
    expect(result[1].hostname).toBe('mi-cliente.local');
    expect(result[2].hostname).toBe('zapatos-online.pe');
  });

  it('returns results grouped by category (categories in alphabetical order)', () => {
    const result = lookupClients(sampleClients, {});
    // Categories should be: ecommerce, landing-page, portafolio
    const categories = result.map((c) => c.category);
    const ecommerceIdx = categories.indexOf('ecommerce');
    const landingIdx = categories.indexOf('landing-page');
    const portafolioIdx = categories.indexOf('portafolio');
    expect(ecommerceIdx).toBeLessThan(landingIdx);
    expect(landingIdx).toBeLessThan(portafolioIdx);
  });

  it('returns empty array for non-array input', () => {
    expect(lookupClients(null, {})).toEqual([]);
    expect(lookupClients(undefined, {})).toEqual([]);
    expect(lookupClients('not an array', {})).toEqual([]);
  });

  it('handles empty clients array', () => {
    expect(lookupClients([], {})).toEqual([]);
    expect(lookupClients([], { name: 'test' })).toEqual([]);
  });
});

describe('groupByCategory', () => {
  it('groups clients by category', () => {
    const grouped = groupByCategory(sampleClients);
    expect(Object.keys(grouped)).toContain('ecommerce');
    expect(Object.keys(grouped)).toContain('portafolio');
    expect(Object.keys(grouped)).toContain('landing-page');
    expect(grouped['ecommerce']).toHaveLength(3);
    expect(grouped['portafolio']).toHaveLength(1);
    expect(grouped['landing-page']).toHaveLength(1);
  });

  it('sorts clients alphabetically by hostname within each group', () => {
    const grouped = groupByCategory(sampleClients);
    const ecommerceHostnames = grouped['ecommerce'].map((c) => c.hostname);
    expect(ecommerceHostnames).toEqual(['abc-store.local', 'mi-cliente.local', 'zapatos-online.pe']);
  });

  it('returns categories sorted alphabetically', () => {
    const grouped = groupByCategory(sampleClients);
    const keys = Object.keys(grouped);
    expect(keys).toEqual(['ecommerce', 'landing-page', 'portafolio']);
  });

  it('returns empty object for non-array input', () => {
    expect(groupByCategory(null)).toEqual({});
    expect(groupByCategory(undefined)).toEqual({});
  });

  it('returns empty object for empty array', () => {
    expect(groupByCategory([])).toEqual({});
  });

  it('uses "uncategorized" for clients without category', () => {
    const clients = [{ hostname: 'test.local', name: 'Test' }];
    const grouped = groupByCategory(clients);
    expect(grouped['uncategorized']).toHaveLength(1);
  });
});
