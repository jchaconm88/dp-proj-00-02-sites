/**
 * Variable product logic — pure JS simulation of WooCommerce variable products.
 * Handles color × size variations with stock tracking.
 *
 * @module test-helpers/variable-product
 */

/**
 * @typedef {object} Variation
 * @property {string} color
 * @property {string} size
 * @property {number} stock
 * @property {number} price
 * @property {boolean} available
 */

/**
 * Create a variable product from a list of variations.
 *
 * @param {string} name - Product name.
 * @param {Array<Variation>} variations - Array of variation objects.
 * @returns {object} Variable product with computed metadata.
 */
export function createVariableProduct(name, variations) {
  // Mark availability based on stock
  const processedVariations = variations.map((v) => ({
    ...v,
    available: v.stock > 0,
  }));

  return {
    name,
    type: "variable",
    variations: processedVariations,
    colors: [...new Set(processedVariations.map((v) => v.color))],
    sizes: [...new Set(processedVariations.map((v) => v.size))],
  };
}

/**
 * Get available sizes for a given color selection.
 * Only returns sizes that have stock > 0 for the selected color.
 *
 * @param {object} product - Variable product.
 * @param {string} color - Selected color.
 * @returns {string[]} Available sizes for that color.
 */
export function getAvailableSizesForColor(product, color) {
  return product.variations
    .filter((v) => v.color === color && v.stock > 0)
    .map((v) => v.size);
}

/**
 * Check if all variations have unique color+size combinations.
 *
 * @param {Array<Variation>} variations
 * @returns {boolean}
 */
export function hasUniqueVariations(variations) {
  const keys = variations.map((v) => `${v.color}|${v.size}`);
  return new Set(keys).size === keys.length;
}
