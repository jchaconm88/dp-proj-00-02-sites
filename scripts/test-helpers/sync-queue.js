/**
 * Sync Queue — pure JS simulation of the PHP SyncQueue class.
 * Implements FIFO queue with payload preservation for ERP operations.
 *
 * @module test-helpers/sync-queue
 */

/**
 * In-memory sync queue for testing purposes.
 * Simulates the database-backed queue from the PHP implementation.
 */
export class SyncQueue {
  constructor() {
    this.items = [];
    this.nextId = 1;
  }

  /**
   * Enqueue an operation with its payload.
   *
   * @param {string} operationType - Type of operation (e.g., 'push_order').
   * @param {object} payload - Full operation payload to preserve.
   * @returns {number} Queue item ID.
   */
  enqueue(operationType, payload) {
    const item = {
      id: this.nextId++,
      operationType,
      payload: JSON.stringify(payload),
      status: "pending",
      createdAt: new Date().toISOString(),
    };
    this.items.push(item);
    return item.id;
  }

  /**
   * Dequeue (retrieve) an item by ID.
   *
   * @param {number} id - Queue item ID.
   * @returns {object|null} The queue item or null if not found.
   */
  getById(id) {
    const item = this.items.find((i) => i.id === id);
    if (!item) return null;
    return {
      ...item,
      payload: JSON.parse(item.payload),
    };
  }

  /**
   * Get all pending items in FIFO order (by creation time).
   *
   * @param {number} [batchSize=50] - Max items to return.
   * @returns {Array<object>} Pending items ordered by creation.
   */
  getPending(batchSize = 50) {
    return this.items
      .filter((i) => i.status === "pending")
      .sort((a, b) => a.createdAt.localeCompare(b.createdAt))
      .slice(0, batchSize)
      .map((item) => ({
        ...item,
        payload: JSON.parse(item.payload),
      }));
  }

  /**
   * Get all items (for verification).
   *
   * @returns {Array<object>} All queue items with parsed payloads.
   */
  getAll() {
    return this.items.map((item) => ({
      ...item,
      payload: JSON.parse(item.payload),
    }));
  }

  /**
   * Get count of all items.
   *
   * @returns {number}
   */
  size() {
    return this.items.length;
  }
}
