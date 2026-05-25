/**
 * WhatsApp message generation — pure JS simulation of the PHP logic.
 * Generates contextual WhatsApp messages based on page type.
 *
 * @module test-helpers/whatsapp-message
 */

/**
 * Generate a WhatsApp message based on page context.
 *
 * @param {object} config - WhatsApp configuration.
 * @param {string} config.phone - Phone number (digits only). Empty = not configured.
 * @param {string} config.productMessageTemplate - Template with {product_name} and {product_url}.
 * @param {string} config.genericMessage - Generic consultation message.
 * @param {object} pageContext - Current page context.
 * @param {boolean} pageContext.isProduct - Whether current page is a product page.
 * @param {string} [pageContext.productName] - Product name (if product page).
 * @param {string} [pageContext.productUrl] - Product URL (if product page).
 * @returns {string} The formatted WhatsApp message, or empty string if phone not configured.
 */
export function generateWhatsAppMessage(config, pageContext) {
  // If phone not configured, return empty (button won't render)
  if (!config.phone) {
    return "";
  }

  // Product page: include product name and URL
  if (pageContext.isProduct) {
    let message = config.productMessageTemplate || "Hola, me interesa el producto: {product_name} ({product_url})";
    // Use split/join to avoid $ special character issues in String.replace
    message = message.split("{product_name}").join(pageContext.productName || "");
    message = message.split("{product_url}").join(pageContext.productUrl || "");
    return message;
  }

  // Non-product pages: generic message
  return config.genericMessage || "Hola, me gustaría hacer una consulta.";
}

/**
 * Build the full WhatsApp URL.
 *
 * @param {string} phone - Phone number (digits only).
 * @param {string} message - Message text.
 * @returns {string} Full wa.me URL.
 */
export function buildWhatsAppUrl(phone, message) {
  return `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
}
