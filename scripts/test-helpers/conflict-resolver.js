/**
 * Conflict Resolver — pure JS simulation of the PHP ConflictResolver class.
 * Implements ERP-wins strategy with payment status exception.
 *
 * @module test-helpers/conflict-resolver
 */

/**
 * WooCommerce payment-related statuses that take precedence over ERP.
 */
const PAYMENT_STATUSES = ["wc-completed", "wc-processing", "wc-refunded"];

/**
 * Resolve a stock level conflict between WooCommerce and ERP.
 * Strategy: ERP always wins.
 *
 * @param {number} wcStock - WooCommerce stock level.
 * @param {number} erpStock - ERP stock level.
 * @param {string} _sku - Product SKU (for logging, unused in pure logic).
 * @returns {number} Resolved stock (always ERP value).
 */
export function resolveStockConflict(wcStock, erpStock, _sku) {
  return erpStock;
}

/**
 * Resolve a customer data conflict between WooCommerce and ERP.
 * Strategy: Merge (ERP base, WC may have newer contact data).
 *
 * @param {object} wcData - WooCommerce customer data.
 * @param {object} erpData - ERP customer data.
 * @returns {object} Merged customer data.
 */
export function resolveCustomerConflict(wcData, erpData) {
  const merged = { ...erpData };

  // Identity fields: ERP always wins
  const erpIdentityFields = ["erp_customer_id", "tax_id", "ruc", "dni", "business_name", "credit_limit"];
  for (const field of erpIdentityFields) {
    if (erpData[field] !== undefined) {
      merged[field] = erpData[field];
    }
  }

  // Contact fields: use most recently updated
  const contactFields = ["email", "phone", "mobile"];
  const wcUpdated = new Date(wcData.updated_at || "1970-01-01").getTime();
  const erpUpdated = new Date(erpData.updated_at || "1970-01-01").getTime();

  for (const field of contactFields) {
    if (wcUpdated > erpUpdated && wcData[field]) {
      merged[field] = wcData[field];
    } else if (erpData[field]) {
      merged[field] = erpData[field];
    }
  }

  // Storefront metadata: WC wins
  const wcMetaFields = ["marketing_consent", "preferred_language", "newsletter_subscribed", "account_notes"];
  for (const field of wcMetaFields) {
    if (wcData[field] !== undefined) {
      merged[field] = wcData[field];
    }
  }

  return merged;
}

/**
 * Resolve an order status conflict between WooCommerce and ERP.
 * Strategy: ERP wins EXCEPT for payment statuses from WC.
 *
 * @param {string} wcStatus - WooCommerce order status (with or without wc- prefix).
 * @param {string} erpStatus - ERP order status.
 * @param {string} _orderId - Order ID (for logging, unused in pure logic).
 * @returns {string} Resolved order status.
 */
export function resolveOrderConflict(wcStatus, erpStatus, _orderId) {
  const normalizedWcStatus = normalizeWcStatus(wcStatus);

  // If WC status is a payment-related status, WC wins
  if (PAYMENT_STATUSES.includes(normalizedWcStatus)) {
    return wcStatus;
  }

  // For all other statuses, ERP wins
  return erpStatus;
}

/**
 * Normalize a WooCommerce status to include the wc- prefix.
 *
 * @param {string} status
 * @returns {string}
 */
function normalizeWcStatus(status) {
  if (status.startsWith("wc-")) {
    return status;
  }
  return "wc-" + status;
}

/**
 * Check if a status is a payment status.
 *
 * @param {string} status
 * @returns {boolean}
 */
export function isPaymentStatus(status) {
  return PAYMENT_STATUSES.includes(normalizeWcStatus(status));
}
