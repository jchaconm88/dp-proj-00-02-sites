import { describe, it, expect } from "vitest";
import * as fc from "fast-check";
import {
  resolveStockConflict,
  resolveOrderConflict,
  isPaymentStatus,
} from "./test-helpers/conflict-resolver.js";

/**
 * Property-based tests for ERP conflict resolution determinism.
 * Validates: Requirements 15.3, 15.8
 *
 * Property 19: ERP Conflict Resolution Determinism
 * - Stock resolution always returns ERP value
 * - Same inputs always produce same output (deterministic)
 * - Order status: payment statuses from WC always win
 * - Order status: non-payment statuses always return ERP value
 */

// --- Generators ---

const stockArb = fc.integer({ min: 0, max: 10000 });
const skuArb = fc.string({ minLength: 3, maxLength: 15 });
const orderIdArb = fc.uuid();

const paymentStatusArb = fc.constantFrom("wc-completed", "wc-processing", "wc-refunded");
const nonPaymentWcStatusArb = fc.constantFrom(
  "wc-pending",
  "wc-on-hold",
  "wc-cancelled",
  "wc-failed"
);
const erpStatusArb = fc.constantFrom(
  "confirmed",
  "shipped",
  "delivered",
  "cancelled",
  "returned",
  "pending_erp"
);

describe("ERP Conflict Resolution Determinism - Property Tests", () => {
  /**
   * **Validates: Requirements 15.3**
   * Property: Stock resolution always returns ERP value.
   */
  it("stock resolution always returns ERP value", () => {
    fc.assert(
      fc.property(stockArb, stockArb, skuArb, (wcStock, erpStock, sku) => {
        const result = resolveStockConflict(wcStock, erpStock, sku);
        expect(result).toBe(erpStock);
      }),
      { numRuns: 200 }
    );
  });

  /**
   * **Validates: Requirements 15.3, 15.8**
   * Property: Same inputs always produce same output (deterministic).
   */
  it("same inputs always produce same output (deterministic)", () => {
    fc.assert(
      fc.property(stockArb, stockArb, skuArb, (wcStock, erpStock, sku) => {
        const result1 = resolveStockConflict(wcStock, erpStock, sku);
        const result2 = resolveStockConflict(wcStock, erpStock, sku);
        const result3 = resolveStockConflict(wcStock, erpStock, sku);

        expect(result1).toBe(result2);
        expect(result2).toBe(result3);
      }),
      { numRuns: 200 }
    );
  });

  /**
   * **Validates: Requirements 15.3, 15.8**
   * Property: Payment statuses from WC always win over ERP.
   */
  it("payment statuses from WC always win", () => {
    fc.assert(
      fc.property(paymentStatusArb, erpStatusArb, orderIdArb, (wcStatus, erpStatus, orderId) => {
        const result = resolveOrderConflict(wcStatus, erpStatus, orderId);
        expect(result).toBe(wcStatus);
        expect(isPaymentStatus(wcStatus)).toBe(true);
      }),
      { numRuns: 200 }
    );
  });

  /**
   * **Validates: Requirements 15.3, 15.8**
   * Property: Non-payment statuses always return ERP value.
   */
  it("non-payment statuses always return ERP value", () => {
    fc.assert(
      fc.property(nonPaymentWcStatusArb, erpStatusArb, orderIdArb, (wcStatus, erpStatus, orderId) => {
        const result = resolveOrderConflict(wcStatus, erpStatus, orderId);
        expect(result).toBe(erpStatus);
        expect(isPaymentStatus(wcStatus)).toBe(false);
      }),
      { numRuns: 200 }
    );
  });
});
