import { describe, it, expect } from "vitest";
import * as fc from "fast-check";
import { validateAddToCart } from "./test-helpers/cart.js";

/**
 * Property-based tests for cart addition validation.
 * Validates: Requirements 17.1, 17.2
 *
 * Property 11: Cart Addition Validation
 * - Valid requests (color + size + stock > 0) always succeed
 * - Missing color always fails
 * - Missing size always fails
 * - Zero stock always fails
 */

// --- Generators ---

const colorArb = fc.constantFrom("Negro", "Blanco", "Azul", "Rojo", "Verde");
const sizeArb = fc.constantFrom("36", "37", "38", "39", "40", "41", "42", "S", "M", "L", "XL");
const positiveStockArb = fc.integer({ min: 1, max: 100 });
const quantityArb = fc.integer({ min: 1, max: 10 });

describe("Cart Addition Validation - Property Tests", () => {
  /**
   * **Validates: Requirements 17.1, 17.2**
   * Property: Valid requests (color + size + stock > 0) always succeed.
   */
  it("valid requests with color, size, and stock > 0 always succeed", () => {
    fc.assert(
      fc.property(colorArb, sizeArb, positiveStockArb, quantityArb, (color, size, stock, quantity) => {
        // Ensure quantity doesn't exceed stock for a valid request
        const validQuantity = Math.min(quantity, stock);
        const result = validateAddToCart({
          color,
          size,
          stock,
          quantity: validQuantity,
        });

        expect(result.success).toBe(true);
        expect(result.error).toBeNull();
      }),
      { numRuns: 200 }
    );
  });

  /**
   * **Validates: Requirements 17.1, 17.2**
   * Property: Missing color always fails with missing_color error.
   */
  it("missing color always fails", () => {
    fc.assert(
      fc.property(sizeArb, positiveStockArb, (size, stock) => {
        const result = validateAddToCart({
          color: "",
          size,
          stock,
          quantity: 1,
        });

        expect(result.success).toBe(false);
        expect(result.error).toBe("missing_color");
      }),
      { numRuns: 200 }
    );
  });

  /**
   * **Validates: Requirements 17.1, 17.2**
   * Property: Missing size always fails with missing_size error.
   */
  it("missing size always fails", () => {
    fc.assert(
      fc.property(colorArb, positiveStockArb, (color, stock) => {
        const result = validateAddToCart({
          color,
          size: "",
          stock,
          quantity: 1,
        });

        expect(result.success).toBe(false);
        expect(result.error).toBe("missing_size");
      }),
      { numRuns: 200 }
    );
  });

  /**
   * **Validates: Requirements 17.1, 17.2**
   * Property: Zero stock always fails with out_of_stock error.
   */
  it("zero stock always fails", () => {
    fc.assert(
      fc.property(colorArb, sizeArb, (color, size) => {
        const result = validateAddToCart({
          color,
          size,
          stock: 0,
          quantity: 1,
        });

        expect(result.success).toBe(false);
        expect(result.error).toBe("out_of_stock");
      }),
      { numRuns: 200 }
    );
  });
});
