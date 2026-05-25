import { describe, it, expect } from 'vitest';
import * as fc from 'fast-check';
import { lookupClients, groupByCategory } from './lookup-client.js';

/**
 * Property-based tests for client registry lookup.
 * Validates: Requirements 6.3
 */
describe('lookupClients & groupByCategory - Property Tests', () => {
  const categories = ['ecommerce', 'portafolio', 'landing-page', 'blog', 'corporativo'];
  const statuses = ['diseño', 'desarrollo', 'revisión', 'producción', 'archivado'];

  /**
   * Generator for a valid client entry with realistic fields.
   */
  const clientArb = fc.record({
    name: fc.string({ minLength: 1, maxLength: 30 }).filter((s) => s.trim().length > 0),
    hostname: fc
      .tuple(
        fc.stringMatching(/^[a-z][a-z0-9]{0,9}$/),
        fc.constantFrom('.local', '.com', '.pe', '.net', '.org')
      )
      .map(([seg, tld]) => seg + tld),
    category: fc.constantFrom(...categories),
    status: fc.constantFrom(...statuses),
    templatePath: fc.constant('templates/'),
    frontPath: fc.constant('front/'),
  });

  /**
   * Generator for a non-empty list of clients.
   */
  const clientsArb = fc.array(clientArb, { minLength: 1, maxLength: 30 });

  /**
   * Generator for filter criteria (each field optional).
   */
  const filtersArb = fc.record({
    name: fc.option(fc.string({ minLength: 1, maxLength: 30 }).filter((s) => s.trim().length > 0), { nil: undefined }),
    category: fc.option(fc.constantFrom(...categories), { nil: undefined }),
    status: fc.option(fc.constantFrom(...statuses), { nil: undefined }),
  });

  // Property 1: No false positives — every returned client matches ALL provided filters
  it('no false positives: every returned client matches all provided filters', () => {
    fc.assert(
      fc.property(clientsArb, filtersArb, (clients, filters) => {
        const results = lookupClients(clients, filters);

        for (const client of results) {
          if (filters.name !== undefined) {
            expect(client.name).toBe(filters.name);
          }
          if (filters.category !== undefined) {
            expect(client.category.toLowerCase()).toBe(filters.category.toLowerCase());
          }
          if (filters.status !== undefined) {
            expect(client.status.toLowerCase()).toBe(filters.status.toLowerCase());
          }
        }
      }),
      { numRuns: 200 }
    );
  });

  // Property 2: No false negatives — no client that matches all filters is excluded from results
  it('no false negatives: no matching client is excluded from results', () => {
    fc.assert(
      fc.property(clientsArb, filtersArb, (clients, filters) => {
        const results = lookupClients(clients, filters);

        for (const client of clients) {
          const matchesName =
            filters.name === undefined || client.name === filters.name;
          const matchesCategory =
            filters.category === undefined ||
            (typeof client.category === 'string' &&
              typeof filters.category === 'string' &&
              client.category.toLowerCase() === filters.category.toLowerCase());
          const matchesStatus =
            filters.status === undefined ||
            (typeof client.status === 'string' &&
              typeof filters.status === 'string' &&
              client.status.toLowerCase() === filters.status.toLowerCase());

          if (matchesName && matchesCategory && matchesStatus) {
            expect(results).toContainEqual(client);
          }
        }
      }),
      { numRuns: 200 }
    );
  });

  // Property 3: Results are sorted by hostname within category
  it('results are sorted by hostname within each category', () => {
    fc.assert(
      fc.property(clientsArb, filtersArb, (clients, filters) => {
        const results = lookupClients(clients, filters);

        // Group results by category to check ordering within each group
        const byCategory = {};
        for (const client of results) {
          const cat = client.category || 'uncategorized';
          if (!byCategory[cat]) byCategory[cat] = [];
          byCategory[cat].push(client);
        }

        for (const cat of Object.keys(byCategory)) {
          const group = byCategory[cat];
          for (let i = 1; i < group.length; i++) {
            const prev = (group[i - 1].hostname || '').toLowerCase();
            const curr = (group[i].hostname || '').toLowerCase();
            expect(prev.localeCompare(curr)).toBeLessThanOrEqual(0);
          }
        }
      }),
      { numRuns: 200 }
    );
  });

  // Property 4: Category grouping is correct — groupByCategory produces groups where every client has the matching category
  it('groupByCategory: every client in a group has the matching category', () => {
    fc.assert(
      fc.property(clientsArb, (clients) => {
        const grouped = groupByCategory(clients);

        for (const [category, groupClients] of Object.entries(grouped)) {
          for (const client of groupClients) {
            const clientCategory = client.category || 'uncategorized';
            expect(clientCategory).toBe(category);
          }
        }
      }),
      { numRuns: 200 }
    );
  });

  // Property 5: Empty filters return all clients
  it('empty filters return all clients', () => {
    fc.assert(
      fc.property(clientsArb, (clients) => {
        const results = lookupClients(clients, {});
        expect(results).toHaveLength(clients.length);

        // Every original client should be present in results
        for (const client of clients) {
          expect(results).toContainEqual(client);
        }
      }),
      { numRuns: 200 }
    );
  });
});
