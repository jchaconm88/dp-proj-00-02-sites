/**
 * Product filter logic — pure JS simulation of WooCommerce product filtering.
 * Implements AND (conjunction) logic for multiple simultaneous filters.
 *
 * @module test-helpers/product-filter
 */

/**
 * Filter products using AND logic across all active filters.
 *
 * @param {Array<object>} products - Array of product objects.
 * @param {object} filters - Active filters (keys: type, size, color, brand, priceMin, priceMax).
 * @returns {Array<object>} Filtered products matching ALL active filters.
 */
export function filterProducts(products, filters) {
  if (!products || !Array.isArray(products)) return [];
  if (!filters || Object.keys(filters).length === 0) return [...products];

  return products.filter((product) => {
    // Type filter
    if (filters.type && product.type !== filters.type) return false;

    // Size filter
    if (filters.size && !product.sizes?.includes(filters.size)) return false;

    // Color filter
    if (filters.color && !product.colors?.includes(filters.color)) return false;

    // Brand filter
    if (filters.brand && product.brand !== filters.brand) return false;

    // Price range filter
    if (filters.priceMin != null && product.price < filters.priceMin) return false;
    if (filters.priceMax != null && product.price > filters.priceMax) return false;

    return true;
  });
}

/**
 * Check if a single product matches all given filters.
 *
 * @param {object} product - A product object.
 * @param {object} filters - Active filters.
 * @returns {boolean} True if product matches all filters.
 */
export function productMatchesFilters(product, filters) {
  if (!filters || Object.keys(filters).length === 0) return true;

  if (filters.type && product.type !== filters.type) return false;
  if (filters.size && !product.sizes?.includes(filters.size)) return false;
  if (filters.color && !product.colors?.includes(filters.color)) return false;
  if (filters.brand && product.brand !== filters.brand) return false;
  if (filters.priceMin != null && product.price < filters.priceMin) return false;
  if (filters.priceMax != null && product.price > filters.priceMax) return false;

  return true;
}
