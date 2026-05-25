/**
 * Client registry lookup function for clients.json.
 * Supports search by name, category, or status with exact matching.
 * Results are grouped by category and ordered alphabetically by hostname.
 *
 * Usage as CLI:
 *   node scripts/lookup-client.js [--name <name>] [--category <cat>] [--status <status>]
 *
 * Exports:
 *   - lookupClients(clients, filters) — filter clients with exact match on provided fields
 *   - groupByCategory(clients) — group clients by category, sorted alphabetically by hostname
 */

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * Filters clients based on provided filter criteria.
 * All provided filters must match (AND logic).
 * - name: case-sensitive exact match
 * - category: case-insensitive exact match
 * - status: case-insensitive exact match
 *
 * @param {Array<object>} clients - Array of client entries from clients.json
 * @param {{ name?: string, category?: string, status?: string }} filters - Filter criteria
 * @returns {Array<object>} Matching clients grouped by category, ordered alphabetically by hostname
 */
export function lookupClients(clients, filters = {}) {
  if (!Array.isArray(clients)) {
    return [];
  }

  const filtered = clients.filter((client) => {
    if (filters.name !== undefined && filters.name !== null) {
      if (client.name !== filters.name) {
        return false;
      }
    }

    if (filters.category !== undefined && filters.category !== null) {
      if (typeof client.category !== 'string' || typeof filters.category !== 'string') {
        return false;
      }
      if (client.category.toLowerCase() !== filters.category.toLowerCase()) {
        return false;
      }
    }

    if (filters.status !== undefined && filters.status !== null) {
      if (typeof client.status !== 'string' || typeof filters.status !== 'string') {
        return false;
      }
      if (client.status.toLowerCase() !== filters.status.toLowerCase()) {
        return false;
      }
    }

    return true;
  });

  // Return results grouped by category and sorted by hostname within each group
  return flattenGrouped(groupByCategory(filtered));
}

/**
 * Groups clients by category and sorts alphabetically by hostname within each group.
 * Categories are also sorted alphabetically.
 *
 * @param {Array<object>} clients - Array of client entries
 * @returns {Object<string, Array<object>>} Object keyed by category with sorted client arrays
 */
export function groupByCategory(clients) {
  if (!Array.isArray(clients)) {
    return {};
  }

  const groups = {};

  for (const client of clients) {
    const category = client.category || 'uncategorized';
    if (!groups[category]) {
      groups[category] = [];
    }
    groups[category].push(client);
  }

  // Sort clients within each category alphabetically by hostname
  for (const category of Object.keys(groups)) {
    groups[category].sort((a, b) => {
      const hostnameA = (a.hostname || '').toLowerCase();
      const hostnameB = (b.hostname || '').toLowerCase();
      return hostnameA.localeCompare(hostnameB);
    });
  }

  // Return with categories sorted alphabetically
  const sortedGroups = {};
  const sortedKeys = Object.keys(groups).sort((a, b) => a.localeCompare(b));
  for (const key of sortedKeys) {
    sortedGroups[key] = groups[key];
  }

  return sortedGroups;
}

/**
 * Flattens grouped results back into a single array while preserving
 * the category grouping order and hostname sort within each group.
 *
 * @param {Object<string, Array<object>>} grouped - Grouped clients object
 * @returns {Array<object>} Flattened array maintaining group order
 */
function flattenGrouped(grouped) {
  const result = [];
  for (const category of Object.keys(grouped)) {
    for (const client of grouped[category]) {
      result.push(client);
    }
  }
  return result;
}

// CLI mode
const currentFile = new URL(import.meta.url).pathname.replace(/^\/([A-Z]:)/, '$1');
const isMainModule = process.argv[1] && resolve(process.argv[1]) === resolve(currentFile);

if (isMainModule) {
  const args = process.argv.slice(2);
  const filters = {};

  for (let i = 0; i < args.length; i++) {
    if (args[i] === '--name' && i + 1 < args.length) {
      filters.name = args[++i];
    } else if (args[i] === '--category' && i + 1 < args.length) {
      filters.category = args[++i];
    } else if (args[i] === '--status' && i + 1 < args.length) {
      filters.status = args[++i];
    }
  }

  // Read clients.json from project root
  const clientsPath = resolve(process.cwd(), 'clients.json');

  try {
    const content = readFileSync(clientsPath, 'utf-8');
    const data = JSON.parse(content);

    if (!Array.isArray(data.clients)) {
      console.error('✗ clients.json does not contain a "clients" array');
      process.exit(1);
    }

    const results = lookupClients(data.clients, filters);
    const grouped = groupByCategory(results);

    if (results.length === 0) {
      console.log('No clients found matching the given filters.');
      process.exit(0);
    }

    console.log(`Found ${results.length} client(s):\n`);

    for (const [category, clients] of Object.entries(grouped)) {
      console.log(`[${category}]`);
      for (const client of clients) {
        console.log(`  ${client.hostname} — ${client.name} (${client.status})`);
      }
      console.log('');
    }
  } catch (err) {
    if (err.code === 'ENOENT') {
      console.error(`✗ File not found: ${clientsPath}`);
    } else if (err instanceof SyntaxError) {
      console.error(`✗ Invalid JSON in: ${clientsPath}`);
      console.error(`  ${err.message}`);
    } else {
      console.error(`✗ Error: ${err.message}`);
    }
    process.exit(1);
  }
}
