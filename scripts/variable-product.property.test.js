import { describe, it, expect } from "vitest";
import * as fc from "fast-check";
import {
  createVariableProduct,
  getAvailableSizesForColor,
  hasUniqueVariations,
} from "./test-helpers/variable-product.js";

/**
 * Property-based tests for variable product invariants.
 * Validates: Requirements 16.5, 16.6, 16.7
 *
 * Property 10: Variable Product Invariants
 * - Each variation has unique color+size combination
 * - Out-of-stock variations are marked unavailable
 * - Selecting a color shows only sizes available for that color
 * - Price is always positive
 */

// --- Generators ---

const colorArb = fc.constantFrom("Negro", "Blanco", "Azul", "Rojo", "Verde");
const sizeArb = fc.constantFrom("36", "37", "38", "39", "40", "41", "42");
const stockArb = fc.nat({ max: 50 }); // 0 = out of stock
const priceArb = fc.integer({ min: 1, max: 99999 }).map((p) => p / 100);

/**
 * Generate variations with unique color+size combinations.
 * Uses uniqueArray on the key to guarantee uniqueness.
 */
const variationsArb = fc
  .uniqueArray(
    fc.tuple(colorArb, sizeArb),
    { minLength: 1, maxLength: 15, comparator: (a, b) => a[0] === b[0] && a[1] === b[1] }
  )
  .chain((pairs) =>
    fc.tuple(
      ...pairs.map(() => fc.tuple(stockArb, priceArb))
    ).map((stockPrices) =>
      pairs.map(([color, size], i) => ({
        color,
        size,
        stock: stockPrices[i][0],
        price: stockPrices[i][1],
      }))
    )
  );

const productNameArb = fc.string({ minLength: 1, maxLength: 40 });

describe("Variable Product Invariants - Property Tests", () => {
  /**
   * **Validates: Requirements 16.5**
   * Property: Each variation has a unique color+size combination.
   */
  it("each variation has unique color+size combination", () => {
    fc.assert(
      fc.property(productNameArb, variationsArb, (name, variations) => {
        const product = createVariableProduct(name, variations);
        expect(hasUniqueVariations(product.variations)).toBe(true);
      }),
      { numRuns: 200 }
    );
  });

  /**
   * **Validates: Requirements 16.6**
   * Property: Out-of-stock variations are marked unavailable.
   */
  it("out-of-stock variations are marked unavailable", () => {
    fc.assert(
      fc.property(productNameArb, variationsArb, (name, variations) => {
        const product = createVariableProduct(name, variations);

        for (const variation of product.variations) {
          if (variation.stock === 0) {
            expect(variation.available).toBe(false);
          } else {
            expect(variation.available).toBe(true);
          }
        }
      }),
      { numRuns: 200 }
    );
  });

  /**
   * **Validates: Requirements 16.7**
   * Property: Selecting a color shows only sizes available for that color.
   */
  it("selecting a color shows only sizes with stock for that color", () => {
    fc.assert(
      fc.property(productNameArb, variationsArb, (name, variations) => {
        const product = createVariableProduct(name, variations);

        for (const color of product.colors) {
          const availableSizes = getAvailableSizesForColor(product, color);

          // Every returned size must have stock > 0 for this color
          for (const size of availableSizes) {
            const variation = product.variations.find(
              (v) => v.color === color && v.size === size
            );
            expect(variation).toBeDefined();
            expect(variation.stock).toBeGreaterThan(0);
          }

          // No in-stock variation for this color should be missing
          const inStockForColor = product.variations.filter(
            (v) => v.color === color && v.stock > 0
          );
          for (const v of inStockForColor) {
            expect(availableSizes).toContain(v.size);
          }
        }
      }),
      { numRuns: 200 }
    );
  });

  /**
   * **Validates: Requirements 16.5**
   * Property: Price is always positive for every variation.
   */
  it("price is always positive for every variation", () => {
    fc.assert(
      fc.property(productNameArb, variationsArb, (name, variations) => {
        const product = createVariableProduct(name, variations);

        for (const variation of product.variations) {
          expect(variation.price).toBeGreaterThan(0);
        }
      }),
      { numRuns: 200 }
    );
  });
});
