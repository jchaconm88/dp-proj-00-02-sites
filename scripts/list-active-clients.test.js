import { describe, it, expect } from 'vitest';
import { listActiveClients } from './list-active-clients.js';

describe('listActiveClients', () => {
  const makeClient = (hostname, status) => ({
    name: `Client ${hostname}`,
    hostname,
    category: 'ecommerce',
    status,
    templatePath: `templates/ecommerce/${hostname}/`,
    frontPath: `front/ecommerce/${hostname}/`,
  });

  it('returns all clients when none are archived', () => {
    const clients = [
      makeClient('a.local', 'desarrollo'),
      makeClient('b.local', 'producción'),
      makeClient('c.local', 'diseño'),
    ];
    const result = listActiveClients(clients);
    expect(result).toHaveLength(3);
    expect(result).toEqual(clients);
  });

  it('excludes clients with status "archivado"', () => {
    const clients = [
      makeClient('a.local', 'desarrollo'),
      makeClient('b.local', 'archivado'),
      makeClient('c.local', 'producción'),
    ];
    const result = listActiveClients(clients);
    expect(result).toHaveLength(2);
    expect(result.map((c) => c.hostname)).toEqual(['a.local', 'c.local']);
  });

  it('returns empty array when all clients are archived', () => {
    const clients = [
      makeClient('a.local', 'archivado'),
      makeClient('b.local', 'archivado'),
    ];
    const result = listActiveClients(clients);
    expect(result).toHaveLength(0);
  });

  it('returns empty array for empty input', () => {
    expect(listActiveClients([])).toEqual([]);
  });

  it('returns empty array for non-array input', () => {
    expect(listActiveClients(null)).toEqual([]);
    expect(listActiveClients(undefined)).toEqual([]);
    expect(listActiveClients('string')).toEqual([]);
    expect(listActiveClients(42)).toEqual([]);
  });

  it('preserves original order of clients', () => {
    const clients = [
      makeClient('z.local', 'diseño'),
      makeClient('a.local', 'desarrollo'),
      makeClient('m.local', 'archivado'),
      makeClient('b.local', 'revisión'),
    ];
    const result = listActiveClients(clients);
    expect(result.map((c) => c.hostname)).toEqual(['z.local', 'a.local', 'b.local']);
  });

  it('includes all non-archivado statuses', () => {
    const statuses = ['diseño', 'desarrollo', 'revisión', 'producción'];
    const clients = statuses.map((s, i) => makeClient(`client-${i}.local`, s));
    const result = listActiveClients(clients);
    expect(result).toHaveLength(4);
  });

  it('skips null or non-object entries in the array', () => {
    const clients = [
      makeClient('a.local', 'desarrollo'),
      null,
      undefined,
      'not-an-object',
      makeClient('b.local', 'producción'),
    ];
    const result = listActiveClients(clients);
    expect(result).toHaveLength(2);
    expect(result.map((c) => c.hostname)).toEqual(['a.local', 'b.local']);
  });
});
