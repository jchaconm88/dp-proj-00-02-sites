import { describe, it, expect } from "vitest";
import * as fc from "fast-check";
import { calculateCart } from "./test-helpers/cart.js";

/**
 * Property-based tests for cart calculation correctness.
 * Validates: Requirements 17.4, 17.5
 *
 * Property 12: Cart Calculation Correctness
 * - Line subtotal = unit_price × quantity
 * - Cart total = sum of all line subtotals + tax + shipping
 * - Quantity change updates total correctly
 * - Total is always ≥ 0
 */

// --- Generators ---

const priceArb = fc.integer({ min: 1, max: 999999 }).map((p) => p / 100);
const quantityArb = fc.integer({ min: 1, max: 50 });
const taxRateArb = fc.integer({ min: 0, max: 25 }).map((r) => r / 100);
const shippingArb = fc.integer({ min: 0, max: 10000 }).map((s) => s / 100);

const cartItemArb = fc.record({
  unitPrice: priceArb,
  quantity: quantityArb,
});

const cartItemsArb = fc.array(cartItemArb, { minLength: 1, maxLength: 10 });

describe("Cart Calculation Correctness - Property Tests", () => {
  /**
   * **Validates: Requirements 17.4**
   * Property: Line subtotal = unit_price × quantity for each item.
   */
  it("line subtotal equals unit_price × quantity", () => {
    fc.assert(
      fc.property(cartItemsArb, taxRateArb, shippingArb, (items, taxRate, shipping) => {
        const result = calculateCart(items, taxRate, shipping);

        for (let i = 0; i < items.length; i++) {
          const expected = items[i].unitPrice * items[i].quantity;
          expect(result.lineSubtotals[i]).toBeCloseTo(expected, 2);
        }
      }),
      { numRuns: 200 }
    );
  });

  /**
   * **Validates: Requirements 17.5**
   * Property: Cart total = sum of all line subtotals + tax + shipping.
   */
  it("cart total equals sum of line subtotals + tax + shipping", () => {
    fc.assert(
      fc.property(cartItemsArb, taxRateArb, shippingArb, (items, taxRate, shipping) => {
        const result = calculateCart(items, taxRate, shipping);

        const expectedSubtotal = result.lineSubtotals.reduce((sum, ls) => sum + ls, 0);
        const expectedTax = expectedSubtotal * taxRate;
        const expectedTotal = Math.max(0, expectedSubtotal + expectedTax + shipping);

        expect(result.subtotal).toBeCloseTo(expectedSubtotal, 2);
        expect(result.tax).toBeCloseTo(expectedTax, 2);
        expect(result.total).toBeCloseTo(expectedTotal, 2);
      }),
      { numRuns: 200 }
    );
  });

  /**
   * **Validates: Requirements 17.5**
   * Property: Quantity change updates total correctly.
   */
  it("quantity change updates total correctly", () => {
    fc.assert(
      fc.property(cartItemsArb, taxRateArb, shippingArb, quantityArb, (items, taxRate, shipping, newQty) => {
        // Calculate original
        const original = calculateCart(items, taxRate, shipping);

        // Change first item's quantity
        const modifiedItems = [...items];
        modifiedItems[0] = { ...modifiedItems[0], quantity: newQty };
        const modified = calculateCart(modifiedItems, taxRate, shipping);

        // The difference in subtotal should equal the price difference
        const priceDiff = modifiedItems[0].unitPrice * newQty - items[0].unitPrice * items[0].quantity;
        expect(modified.subtotal - original.subtotal).toBeCloseTo(priceDiff, 2);
      }),
      { numRuns: 200 }
    );
  });

  /**
   * **Validates: Requirements 17.4, 17.5**
   * Property: Total is always ≥ 0.
   */
  it("total is always >= 0", () => {
    fc.assert(
      fc.property(cartItemsArb, taxRateArb, shippingArb, (items, taxRate, shipping) => {
        const result = calculateCart(items, taxRate, shipping);
        expect(result.total).toBeGreaterThanOrEqual(0);
      }),
      { numRuns: 200 }
    );
  });
});
