import { describe, it, expect } from "vitest";
import * as fc from "fast-check";
import { SyncQueue } from "./test-helpers/sync-queue.js";

/**
 * Property-based tests for sync queue FIFO ordering.
 * Validates: Requirements 15.8
 *
 * Property 22: Sync Queue FIFO Ordering
 * - Operations are processed in creation order
 * - Earlier items are always processed before later items
 */

// --- Generators ---

const operationTypeArb = fc.constantFrom(
  "push_order",
  "sync_customer",
  "confirm_payment",
  "update_order_status"
);

const payloadArb = fc.record({
  id: fc.uuid(),
  data: fc.string({ minLength: 1, maxLength: 50 }),
  timestamp: fc.integer({ min: 1704067200000, max: 1735689600000 }).map((ts) => new Date(ts).toISOString()),
});

const operationArb = fc.record({
  type: operationTypeArb,
  payload: payloadArb,
});

const operationsArb = fc.array(operationArb, { minLength: 2, maxLength: 30 });

describe("Sync Queue FIFO Ordering - Property Tests", () => {
  /**
   * **Validates: Requirements 15.8**
   * Property: Operations are processed in creation order (FIFO).
   */
  it("operations are processed in creation order", () => {
    fc.assert(
      fc.property(operationsArb, (operations) => {
        const queue = new SyncQueue();
        const enqueuedIds = [];

        for (const op of operations) {
          const id = queue.enqueue(op.type, op.payload);
          enqueuedIds.push(id);
        }

        // Get pending items — should be in FIFO order
        const pending = queue.getPending(operations.length);

        // Verify order matches enqueue order
        for (let i = 0; i < pending.length; i++) {
          expect(pending[i].id).toBe(enqueuedIds[i]);
        }
      }),
      { numRuns: 200 }
    );
  });

  /**
   * **Validates: Requirements 15.8**
   * Property: Earlier items are always processed before later items.
   */
  it("earlier items are always processed before later items", () => {
    fc.assert(
      fc.property(operationsArb, (operations) => {
        const queue = new SyncQueue();

        for (const op of operations) {
          queue.enqueue(op.type, op.payload);
        }

        const pending = queue.getPending(operations.length);

        // For any two items i < j in the pending list,
        // item i was created before item j
        for (let i = 0; i < pending.length - 1; i++) {
          const current = new Date(pending[i].createdAt).getTime();
          const next = new Date(pending[i + 1].createdAt).getTime();
          expect(current).toBeLessThanOrEqual(next);
        }
      }),
      { numRuns: 200 }
    );
  });
});
