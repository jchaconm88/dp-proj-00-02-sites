import { describe, it, expect } from 'vitest';
import * as fc from 'fast-check';
import { listActiveClients } from './list-active-clients.js';

/**
 * Property-based tests for listActiveClients.
 * **Validates: Requirements 6.6**
 *
 * Property 8: Archived Client Exclusion
 */
describe('listActiveClients - Property Tests', () => {
  const statusValues = ['diseño', 'desarrollo', 'revisión', 'producción', 'archivado'];

  /**
   * Generator for a client object with a random status from the allowed values.
   */
  const clientArb = fc
    .record({
      name: fc.string({ minLength: 1, maxLength: 30 }),
      hostname: fc.string({ minLength: 3, maxLength: 20 }).map((s) => s.replace(/[^a-z0-9-]/g, 'x') + '.local'),
      category: fc.constantFrom('ecommerce', 'portafolio', 'landing-page'),
      status: fc.constantFrom(...statusValues),
    })
    .map((fields) => ({
      ...fields,
      templatePath: `templates/${fields.category}/${fields.hostname}/`,
      frontPath: `front/${fields.category}/${fields.hostname}/`,
    }));

  /**
   * Generator for an array of clients.
   */
  const clientsArb = fc.array(clientArb, { minLength: 0, maxLength: 50 });

  // Property 1: No archived clients in output
  it('no client in the result has status "archivado"', () => {
    fc.assert(
      fc.property(clientsArb, (clients) => {
        const result = listActiveClients(clients);
        for (const client of result) {
          expect(client.status).not.toBe('archivado');
        }
      }),
      { numRuns: 200 }
    );
  });

  // Property 2: All non-archived clients are included
  it('every client with status !== "archivado" appears in the result', () => {
    fc.assert(
      fc.property(clientsArb, (clients) => {
        const result = listActiveClients(clients);
        const nonArchived = clients.filter((c) => c.status !== 'archivado');
        expect(result).toHaveLength(nonArchived.length);
        for (const client of nonArchived) {
          expect(result).toContainEqual(client);
        }
      }),
      { numRuns: 200 }
    );
  });

  // Property 3: Order is preserved
  it('the relative order of clients in the output matches their relative order in the input', () => {
    fc.assert(
      fc.property(clientsArb, (clients) => {
        const result = listActiveClients(clients);
        // Build expected order by filtering input in order
        const expected = clients.filter((c) => c.status !== 'archivado');
        expect(result).toEqual(expected);
      }),
      { numRuns: 200 }
    );
  });

  // Property 4: Result length equals non-archived count
  it('the result length equals the count of non-archived clients in the input', () => {
    fc.assert(
      fc.property(clientsArb, (clients) => {
        const result = listActiveClients(clients);
        const nonArchivedCount = clients.filter((c) => c.status !== 'archivado').length;
        expect(result).toHaveLength(nonArchivedCount);
      }),
      { numRuns: 200 }
    );
  });
});
