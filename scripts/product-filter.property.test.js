import { describe, it, expect } from "vitest";
import * as fc from "fast-check";
import { filterProducts, productMatchesFilters } from "./test-helpers/product-filter.js";

/**
 * Property-based tests for product filter conjunction logic.
 * Validates: Requirements 16.2, 16.3
 *
 * Property 9: Product Filter Conjunction
 * - Apply multiple filters simultaneously (AND logic)
 * - Every returned product matches ALL active filters
 * - No matching product is excluded
 * - Result count ≤ input count
 */

// --- Generators ---

const productTypeArb = fc.constantFrom("Ropa", "Zapatillas", "Accesorios");
const sizeArb = fc.constantFrom("36", "37", "38", "39", "40", "41", "42", "S", "M", "L", "XL");
const colorArb = fc.constantFrom("Negro", "Blanco", "Azul", "Rojo", "Verde", "Gris");
const brandArb = fc.constantFrom("Nike", "Adidas", "Puma", "Reebok", "New Balance");
const priceArb = fc.integer({ min: 1, max: 99900 }).map((p) => p / 100);

const productArb = fc.record({
  id: fc.uuid(),
  name: fc.string({ minLength: 1, maxLength: 30 }),
  type: productTypeArb,
  sizes: fc.uniqueArray(sizeArb, { minLength: 1, maxLength: 4 }),
  colors: fc.uniqueArray(colorArb, { minLength: 1, maxLength: 3 }),
  brand: brandArb,
  price: priceArb,
});

const productsArb = fc.array(productArb, { minLength: 0, maxLength: 20 });

const filtersArb = fc.record(
  {
    type: productTypeArb,
    size: sizeArb,
    color: colorArb,
    brand: brandArb,
    priceMin: fc.integer({ min: 1, max: 50000 }).map((p) => p / 100),
    priceMax: fc.integer({ min: 50000, max: 99900 }).map((p) => p / 100),
  },
  { requiredKeys: [] }
);

describe("Product Filter Conjunction - Property Tests", () => {
  /**
   * **Validates: Requirements 16.2, 16.3**
   * Property: Every returned product matches ALL active filters.
   */
  it("every returned product matches ALL active filters", () => {
    fc.assert(
      fc.property(productsArb, filtersArb, (products, filters) => {
        const result = filterProducts(products, filters);

        for (const product of result) {
          expect(productMatchesFilters(product, filters)).toBe(true);
        }
      }),
      { numRuns: 200 }
    );
  });

  /**
   * **Validates: Requirements 16.2, 16.3**
   * Property: No matching product is excluded from results.
   */
  it("no matching product is excluded from results", () => {
    fc.assert(
      fc.property(productsArb, filtersArb, (products, filters) => {
        const result = filterProducts(products, filters);
        const resultIds = new Set(result.map((p) => p.id));

        for (const product of products) {
          if (productMatchesFilters(product, filters)) {
            expect(resultIds.has(product.id)).toBe(true);
          }
        }
      }),
      { numRuns: 200 }
    );
  });

  /**
   * **Validates: Requirements 16.2, 16.3**
   * Property: Result count is always ≤ input count.
   */
  it("result count is always <= input count", () => {
    fc.assert(
      fc.property(productsArb, filtersArb, (products, filters) => {
        const result = filterProducts(products, filters);
        expect(result.length).toBeLessThanOrEqual(products.length);
      }),
      { numRuns: 200 }
    );
  });
});
