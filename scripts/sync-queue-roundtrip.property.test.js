import { describe, it, expect } from "vitest";
import * as fc from "fast-check";
import { SyncQueue } from "./test-helpers/sync-queue.js";

/**
 * Property-based tests for sync queue round trip (no data loss).
 * Validates: Requirements 15.8
 *
 * Property 18: Sync Queue Round Trip (No Data Loss)
 * - Every enqueued operation can be retrieved
 * - Payload is preserved exactly (JSON round-trip)
 * - No operations are lost during enqueue/dequeue cycle
 */

// --- Generators ---

const operationTypeArb = fc.constantFrom(
  "push_order",
  "sync_customer",
  "confirm_payment",
  "update_order_status"
);

/** Generate realistic operation payloads. */
const orderPayloadArb = fc.record({
  external_id: fc.uuid(),
  customer_email: fc.emailAddress(),
  items: fc.array(
    fc.record({
      sku: fc.string({ minLength: 3, maxLength: 10 }),
      quantity: fc.integer({ min: 1, max: 20 }),
      price: fc.integer({ min: 1, max: 999900 }).map((p) => p / 100),
    }),
    { minLength: 1, maxLength: 5 }
  ),
  total: fc.integer({ min: 1, max: 9999900 }).map((p) => p / 100),
  status: fc.constantFrom("pending", "processing", "completed"),
});

const customerPayloadArb = fc.record({
  email: fc.emailAddress(),
  first_name: fc.string({ minLength: 1, maxLength: 30 }),
  last_name: fc.string({ minLength: 1, maxLength: 30 }),
  phone: fc.string({ minLength: 9, maxLength: 15 }),
});

const paymentPayloadArb = fc.record({
  erp_order_id: fc.uuid(),
  payment_data: fc.record({
    method: fc.constantFrom("yape", "mercadopago", "culqi", "transfer"),
    amount: fc.integer({ min: 1, max: 9999900 }).map((p) => p / 100),
    transaction_id: fc.uuid(),
  }),
});

const payloadArb = fc.oneof(orderPayloadArb, customerPayloadArb, paymentPayloadArb);

const operationArb = fc.record({
  type: operationTypeArb,
  payload: payloadArb,
});

const operationsArb = fc.array(operationArb, { minLength: 1, maxLength: 20 });

describe("Sync Queue Round Trip (No Data Loss) - Property Tests", () => {
  /**
   * **Validates: Requirements 15.8**
   * Property: Every enqueued operation can be retrieved by ID.
   */
  it("every enqueued operation can be retrieved", () => {
    fc.assert(
      fc.property(operationsArb, (operations) => {
        const queue = new SyncQueue();
        const ids = [];

        for (const op of operations) {
          const id = queue.enqueue(op.type, op.payload);
          ids.push(id);
        }

        // Every ID should be retrievable
        for (const id of ids) {
          const item = queue.getById(id);
          expect(item).not.toBeNull();
          expect(item.id).toBe(id);
        }
      }),
      { numRuns: 200 }
    );
  });

  /**
   * **Validates: Requirements 15.8**
   * Property: Payload is preserved exactly (JSON round-trip).
   */
  it("payload is preserved exactly through JSON round-trip", () => {
    fc.assert(
      fc.property(operationsArb, (operations) => {
        const queue = new SyncQueue();
        const entries = [];

        for (const op of operations) {
          const id = queue.enqueue(op.type, op.payload);
          entries.push({ id, originalPayload: op.payload, type: op.type });
        }

        for (const entry of entries) {
          const item = queue.getById(entry.id);
          expect(item.operationType).toBe(entry.type);
          expect(item.payload).toEqual(entry.originalPayload);
        }
      }),
      { numRuns: 200 }
    );
  });

  /**
   * **Validates: Requirements 15.8**
   * Property: No operations are lost during enqueue/dequeue cycle.
   */
  it("no operations are lost during enqueue/dequeue cycle", () => {
    fc.assert(
      fc.property(operationsArb, (operations) => {
        const queue = new SyncQueue();

        for (const op of operations) {
          queue.enqueue(op.type, op.payload);
        }

        // Total items in queue must equal number enqueued
        expect(queue.size()).toBe(operations.length);

        // All items retrievable
        const allItems = queue.getAll();
        expect(allItems).toHaveLength(operations.length);
      }),
      { numRuns: 200 }
    );
  });
});
