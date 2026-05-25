import { describe, it, expect } from "vitest";
import * as fc from "fast-check";
import { preserveCartOnPaymentFailure, calculateCart } from "./test-helpers/cart.js";

/**
 * Property-based tests for cart state preservation on payment failure.
 * Validates: Requirements 17.10
 *
 * Property 13: Cart State Preservation on Payment Failure
 * - Cart items are preserved after failure
 * - Checkout form data is preserved
 * - Cart total remains unchanged
 */

// --- Generators ---

const priceArb = fc.integer({ min: 1, max: 999999 }).map((p) => p / 100);
const quantityArb = fc.integer({ min: 1, max: 20 });

const cartItemArb = fc.record({
  id: fc.uuid(),
  name: fc.string({ minLength: 1, maxLength: 30 }),
  color: fc.constantFrom("Negro", "Blanco", "Azul", "Rojo"),
  size: fc.constantFrom("S", "M", "L", "XL", "38", "40", "42"),
  unitPrice: priceArb,
  quantity: quantityArb,
});

const formDataArb = fc.record({
  billing_first_name: fc.string({ minLength: 1, maxLength: 50 }),
  billing_address_1: fc.string({ minLength: 1, maxLength: 100 }),
  billing_city: fc.string({ minLength: 1, maxLength: 30 }),
  billing_state: fc.string({ minLength: 1, maxLength: 30 }),
  billing_postcode: fc.string({ minLength: 0, maxLength: 10 }),
  billing_phone: fc.string({ minLength: 9, maxLength: 15 }),
  billing_email: fc.emailAddress(),
});

const cartStateArb = fc
  .tuple(
    fc.array(cartItemArb, { minLength: 1, maxLength: 8 }),
    formDataArb
  )
  .map(([items, formData]) => {
    const calcItems = items.map((i) => ({ unitPrice: i.unitPrice, quantity: i.quantity }));
    const { total } = calculateCart(calcItems, 0.18, 10);
    return { items, formData, total };
  });

describe("Cart State Preservation on Payment Failure - Property Tests", () => {
  /**
   * **Validates: Requirements 17.10**
   * Property: Cart items are preserved after payment failure.
   */
  it("cart items are preserved after payment failure", () => {
    fc.assert(
      fc.property(cartStateArb, (cartState) => {
        const afterFailure = preserveCartOnPaymentFailure(cartState);

        expect(afterFailure.items).toHaveLength(cartState.items.length);
        for (let i = 0; i < cartState.items.length; i++) {
          expect(afterFailure.items[i].id).toBe(cartState.items[i].id);
          expect(afterFailure.items[i].name).toBe(cartState.items[i].name);
          expect(afterFailure.items[i].color).toBe(cartState.items[i].color);
          expect(afterFailure.items[i].size).toBe(cartState.items[i].size);
          expect(afterFailure.items[i].unitPrice).toBe(cartState.items[i].unitPrice);
          expect(afterFailure.items[i].quantity).toBe(cartState.items[i].quantity);
        }
      }),
      { numRuns: 200 }
    );
  });

  /**
   * **Validates: Requirements 17.10**
   * Property: Checkout form data is preserved after payment failure.
   */
  it("checkout form data is preserved after payment failure", () => {
    fc.assert(
      fc.property(cartStateArb, (cartState) => {
        const afterFailure = preserveCartOnPaymentFailure(cartState);

        expect(afterFailure.formData.billing_first_name).toBe(cartState.formData.billing_first_name);
        expect(afterFailure.formData.billing_address_1).toBe(cartState.formData.billing_address_1);
        expect(afterFailure.formData.billing_city).toBe(cartState.formData.billing_city);
        expect(afterFailure.formData.billing_state).toBe(cartState.formData.billing_state);
        expect(afterFailure.formData.billing_postcode).toBe(cartState.formData.billing_postcode);
        expect(afterFailure.formData.billing_phone).toBe(cartState.formData.billing_phone);
        expect(afterFailure.formData.billing_email).toBe(cartState.formData.billing_email);
      }),
      { numRuns: 200 }
    );
  });

  /**
   * **Validates: Requirements 17.10**
   * Property: Cart total remains unchanged after payment failure.
   */
  it("cart total remains unchanged after payment failure", () => {
    fc.assert(
      fc.property(cartStateArb, (cartState) => {
        const afterFailure = preserveCartOnPaymentFailure(cartState);
        expect(afterFailure.total).toBe(cartState.total);
      }),
      { numRuns: 200 }
    );
  });
});
