/**
 * Active client listing — excludes archived clients from the registry.
 *
 * Usage as CLI:
 *   node scripts/list-active-clients.js [path-to-clients.json]
 *
 * Exports:
 *   - listActiveClients(clients) — returns only clients whose status is NOT "archivado"
 */

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * Filters out clients with status "archivado" and returns only active clients.
 * Preserves original order.
 * @param {object[]} clients - Array of client entries from clients.json
 * @returns {object[]} Active clients (status !== "archivado")
 */
export function listActiveClients(clients) {
  if (!Array.isArray(clients)) {
    return [];
  }

  return clients.filter(
    (client) => client && typeof client === 'object' && client.status !== 'archivado'
  );
}

// CLI mode: read clients.json and print active clients
const currentFile = new URL(import.meta.url).pathname.replace(/^\/([A-Z]:)/, '$1');
const isMainModule = process.argv[1] && resolve(process.argv[1]) === resolve(currentFile);

if (isMainModule) {
  const filePath = process.argv[2] || 'clients.json';
  const resolvedPath = resolve(filePath);

  try {
    const content = readFileSync(resolvedPath, 'utf-8');
    const data = JSON.parse(content);

    if (!data || !Array.isArray(data.clients)) {
      console.error('✗ Invalid registry: "clients" array not found');
      process.exit(1);
    }

    const activeClients = listActiveClients(data.clients);

    if (activeClients.length === 0) {
      console.log('No active clients found.');
      process.exit(0);
    }

    console.log(`Active clients (${activeClients.length}):\n`);
    console.log(
      'Hostname'.padEnd(30) +
      'Name'.padEnd(25) +
      'Category'.padEnd(18) +
      'Status'
    );
    console.log('-'.repeat(85));

    for (const client of activeClients) {
      console.log(
        (client.hostname || '').padEnd(30) +
        (client.name || '').padEnd(25) +
        (client.category || '').padEnd(18) +
        (client.status || '')
      );
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
