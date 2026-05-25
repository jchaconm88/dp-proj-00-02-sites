/**
 * Cart logic — pure JS simulation of WooCommerce cart operations.
 * Handles add-to-cart validation, calculation, and state preservation.
 *
 * @module test-helpers/cart
 */

/**
 * Validate an add-to-cart request for a variable product.
 *
 * @param {object} request - Add-to-cart request.
 * @param {string} request.color - Selected color (empty string = not selected).
 * @param {string} request.size - Selected size (empty string = not selected).
 * @param {number} request.stock - Available stock for the variation.
 * @param {number} [request.quantity=1] - Quantity to add.
 * @returns {{success: boolean, error: string|null}}
 */
export function validateAddToCart(request) {
  // Require color selection
  if (!request.color) {
    return { success: false, error: "missing_color" };
  }

  // Require size selection
  if (!request.size) {
    return { success: false, error: "missing_size" };
  }

  // Verify stock > 0
  if (request.stock <= 0) {
    return { success: false, error: "out_of_stock" };
  }

  // Verify quantity doesn't exceed stock
  const quantity = request.quantity || 1;
  if (quantity > request.stock) {
    return { success: false, error: "exceeds_stock" };
  }

  return { success: true, error: null };
}

/**
 * Calculate cart totals.
 *
 * @param {Array<{unitPrice: number, quantity: number}>} items - Cart items.
 * @param {number} [taxRate=0] - Tax rate as decimal (e.g., 0.18 for 18%).
 * @param {number} [shipping=0] - Shipping cost.
 * @returns {{lineSubtotals: number[], subtotal: number, tax: number, shipping: number, total: number}}
 */
export function calculateCart(items, taxRate = 0, shipping = 0) {
  const lineSubtotals = items.map((item) => item.unitPrice * item.quantity);
  const subtotal = lineSubtotals.reduce((sum, ls) => sum + ls, 0);
  const tax = subtotal * taxRate;
  const total = subtotal + tax + shipping;

  return {
    lineSubtotals,
    subtotal,
    tax,
    shipping,
    total: Math.max(0, total),
  };
}

/**
 * Simulate payment failure and verify cart state preservation.
 *
 * @param {object} cartState - Current cart state before payment.
 * @param {Array} cartState.items - Cart items.
 * @param {object} cartState.formData - Checkout form data.
 * @param {number} cartState.total - Cart total.
 * @returns {object} Cart state after payment failure (should be identical).
 */
export function preserveCartOnPaymentFailure(cartState) {
  // Simulate payment failure — cart state is preserved exactly
  return {
    items: [...cartState.items],
    formData: { ...cartState.formData },
    total: cartState.total,
  };
}
