import { describe, it, expect } from "vitest";
import * as fc from "fast-check";
import { generateWhatsAppMessage } from "./test-helpers/whatsapp-message.js";

/**
 * Property-based tests for WhatsApp message generation.
 * Validates: Requirements 18.3, 18.4
 *
 * Property 14: WhatsApp Message Generation
 * - Product page message contains product name
 * - Product page message contains product URL
 * - Non-product pages use generic message
 * - Message is never empty when phone is configured
 */

// --- Generators ---

const phoneArb = fc
  .array(fc.constantFrom(..."0123456789".split("")), { minLength: 9, maxLength: 12 })
  .map((digits) => "51" + digits.join(""));

const productNameArb = fc.string({ minLength: 1, maxLength: 60 }).filter((s) => s.trim().length > 0);
const productUrlArb = fc.webUrl();

const productTemplateArb = fc.constantFrom(
  "Hola, me interesa el producto: {product_name} ({product_url})",
  "Quiero información sobre {product_name} - {product_url}",
  "Consulta sobre {product_name}: {product_url}"
);

const genericMessageArb = fc.string({ minLength: 1, maxLength: 100 }).filter((s) => s.trim().length > 0);

const configWithPhoneArb = fc.record({
  phone: phoneArb,
  productMessageTemplate: productTemplateArb,
  genericMessage: genericMessageArb,
});

describe("WhatsApp Message Generation - Property Tests", () => {
  /**
   * **Validates: Requirements 18.3**
   * Property: Product page message contains product name.
   */
  it("product page message contains product name", () => {
    fc.assert(
      fc.property(configWithPhoneArb, productNameArb, productUrlArb, (config, productName, productUrl) => {
        const message = generateWhatsAppMessage(config, {
          isProduct: true,
          productName,
          productUrl,
        });

        expect(message).toContain(productName);
      }),
      { numRuns: 200 }
    );
  });

  /**
   * **Validates: Requirements 18.3**
   * Property: Product page message contains product URL.
   */
  it("product page message contains product URL", () => {
    fc.assert(
      fc.property(configWithPhoneArb, productNameArb, productUrlArb, (config, productName, productUrl) => {
        const message = generateWhatsAppMessage(config, {
          isProduct: true,
          productName,
          productUrl,
        });

        expect(message).toContain(productUrl);
      }),
      { numRuns: 200 }
    );
  });

  /**
   * **Validates: Requirements 18.4**
   * Property: Non-product pages use generic message.
   */
  it("non-product pages use generic message", () => {
    fc.assert(
      fc.property(configWithPhoneArb, (config) => {
        const message = generateWhatsAppMessage(config, {
          isProduct: false,
        });

        expect(message).toBe(config.genericMessage);
      }),
      { numRuns: 200 }
    );
  });

  /**
   * **Validates: Requirements 18.3, 18.4**
   * Property: Message is never empty when phone is configured.
   */
  it("message is never empty when phone is configured", () => {
    fc.assert(
      fc.property(
        configWithPhoneArb,
        fc.boolean(),
        productNameArb,
        productUrlArb,
        (config, isProduct, productName, productUrl) => {
          const message = generateWhatsAppMessage(config, {
            isProduct,
            productName,
            productUrl,
          });

          expect(message.length).toBeGreaterThan(0);
        }
      ),
      { numRuns: 200 }
    );
  });
});
