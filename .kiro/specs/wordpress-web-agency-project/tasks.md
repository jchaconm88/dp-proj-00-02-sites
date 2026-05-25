# Implementation Plan: WordPress Web Agency Project

## Overview

This implementation plan covers the complete WordPress web agency monorepo project, including: monorepo structure and validation scripts, template module with design system, WordPress theme with WooCommerce and FSE, ERP integration layer, CI/CD pipelines, and all supporting integrations (payments, invoicing, logistics, POS, marketing automation, WhatsApp). The implementation uses TypeScript/Node.js for automation scripts, PHP for WordPress theme and plugins, PowerShell for Windows-specific scripts, and GitHub Actions YAML for CI/CD.

## Tasks

- [x] 1. Set up monorepo structure and shared configuration
  - [x] 1.1 Create root monorepo directory structure with `templates/`, `front/`, `scripts/`, `.github/workflows/` directories, and root configuration files (`.editorconfig`, `.gitignore`, `package.json` with workspaces)
    - Initialize npm project with TypeScript support for scripts
    - Create `.editorconfig` with consistent formatting rules for PHP, JS/TS, CSS, HTML
    - Create `.gitignore` excluding node_modules, vendor, .env, build artifacts, ZIP outputs
    - _Requirements: 1.1, 1.3_

  - [x] 1.2 Create `README.md` with project description, folder structure, naming conventions, workflow between modules, and instructions for adding a new client
    - Include sections: Descripción, Estructura de Carpetas, Convención de Nombres, Flujo de Trabajo, Instrucciones para Nuevo Cliente
    - _Requirements: 1.2_

  - [x] 1.3 Create `CLIENTS.md` (or `clients.json`) registry file with schema for tracking all clients
    - Define JSON schema with fields: name, hostname, category, status, templatePath, frontPath, createdAt, updatedAt
    - Include statusValues enum and categories list
    - _Requirements: 6.2, 6.4_

  - [x] 1.4 Create `WORKFLOW.md` documenting the design-to-implementation workflow stages
    - Document stages: creación del diseño, aprobación, transferencia de tokens, implementación del tema, validación de fidelidad
    - Include artifacts, inputs/outputs, and responsible party for each stage
    - _Requirements: 11.1_

  - [x] 1.5 Create `PLUGINS.md` documenting recommended plugins by site type
    - Include categories: e-commerce, portafolio, landing page
    - Document at least 3 plugins per category with name, purpose, compatibility, license type
    - _Requirements: 5.2_


- [x] 2. Implement hostname validation and structure validation scripts
  - [x] 2.1 Implement `scripts/validate-hostname.js` — hostname format validation function
    - Validate: lowercase, alphanumeric + hyphens + dots, max 63 chars per segment, max 253 total, valid TLD
    - Return pass/fail with descriptive error message on failure
    - Export as reusable module
    - _Requirements: 1.4, 1.6, 2.1, 3.1_

  - [x] 2.2 Write property test for hostname validation
    - **Property 1: Hostname Validation Correctness**
    - **Validates: Requirements 1.4, 1.6, 2.1, 3.1**

  - [x] 2.3 Implement `scripts/validate-structure.ps1` — monorepo structure validation script
    - Validate required files in `design/` directory (`code.html`, `DESIGN.md`, `screen.png`)
    - Validate hostname format for client folder names
    - Validate 1:1 correspondence between templates/ and front/ for clients with themes
    - Report exactly which files are missing
    - _Requirements: 2.2, 2.3, 1.1_

  - [x] 2.4 Write property test for design directory structure validation
    - **Property 2: Design Directory Structure Validation**
    - **Validates: Requirements 2.2, 2.3**

  - [x] 2.5 Implement `scripts/validate-registry.js` — client registry entry validation
    - Validate all required fields present (name, hostname, category, status, templatePath, frontPath)
    - Validate status is one of 5 allowed values
    - Validate hostname format
    - _Requirements: 6.2, 6.4_

  - [x] 2.6 Write property test for client registry entry validation
    - **Property 6: Client Registry Entry Validation**
    - **Validates: Requirements 6.2, 6.4**


- [x] 3. Implement client management and registry operations
  - [x] 3.1 Implement `scripts/lookup-client.js` — client registry lookup function
    - Support search by name, category, or status
    - Return exact matching set (no false positives/negatives)
    - Group entries by category, order alphabetically by hostname
    - _Requirements: 6.3_

  - [x] 3.2 Write property test for client registry lookup
    - **Property 7: Client Registry Lookup Correctness**
    - **Validates: Requirements 6.3**

  - [x] 3.3 Implement `scripts/list-active-clients.js` — active client listing (excludes archived)
    - Filter out clients with status `archivado`
    - Return only active clients for display
    - _Requirements: 6.6_

  - [x] 3.4 Write property test for archived client exclusion
    - **Property 8: Archived Client Exclusion**
    - **Validates: Requirements 6.6**

  - [x] 3.5 Implement `scripts/scaffold-client.js` — new client scaffolding script
    - Create client folder in templates/ with `design/` subdirectory and template files (`code.html`, `DESIGN.md`, `screen.png`)
    - Optionally create corresponding folder in front/ with WordPress theme structure
    - Validate hostname before creation, reject invalid names with error message
    - Support optional category subfolder
    - Add entry to clients.json registry
    - _Requirements: 1.4, 1.5, 1.6, 2.4, 6.2, 6.5_

- [~] 4. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.


- [x] 5. Implement token transfer and design validation scripts
  - [x] 5.1 Implement `scripts/transfer-tokens.js` — DESIGN.md to theme.json token transfer
    - Parse YAML frontmatter from DESIGN.md (colors, typography, spacing, rounded)
    - Map tokens to theme.json structure (settings.color.palette, settings.typography.fontFamilies/fontSizes, settings.spacing.spacingSizes, styles.blocks.*.border.radius)
    - Abort without partial modification if DESIGN.md unreadable or theme.json unwritable
    - Return transfer result with count, warnings, errors
    - _Requirements: 3.4, 11.2, 11.5_

  - [x] 5.2 Write property test for token transfer round trip
    - **Property 4: Token Transfer Round Trip**
    - **Validates: Requirements 3.4, 11.2**

  - [x] 5.3 Implement `scripts/validate-tokens.js` — token discrepancy detection between DESIGN.md and theme.json
    - Compare each token from DESIGN.md against corresponding theme.json value
    - Report each discrepant token with expected vs actual value
    - Return "PASS" if all match, "FAIL" if any differ
    - _Requirements: 11.4_

  - [x] 5.4 Write property test for token discrepancy detection
    - **Property 5: Token Discrepancy Detection**
    - **Validates: Requirements 11.4**

  - [x] 5.5 Implement `scripts/compare-screenshots.js` — visual comparison tool
    - Compare design screenshot (screen.png) with implementation screenshot
    - Use pixelmatch for pixel-level comparison
    - Generate diff image highlighting differences
    - Report dimensions and diff percentage
    - _Requirements: 11.3_


- [x] 6. Implement template packaging script
  - [x] 6.1 Implement `scripts/package-template.ps1` — template ZIP packaging
    - Package contents of `components/` directory into `template.zip`
    - Include: HTML, manifest, partials, styles
    - Exclude: `.example.json`, `README.md`, documentation files
    - Error if `components/` doesn't exist or is empty
    - _Requirements: 2.5, 2.6_

  - [x] 6.2 Write property test for template packaging inclusion/exclusion
    - **Property 3: Template Packaging Inclusion/Exclusion**
    - **Validates: Requirements 2.5**

- [~] 7. Checkpoint - Ensure all scripts and tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 8. Implement WordPress theme base structure (FSE + theme.json)
  - [x] 8.1 Create WordPress theme directory structure for initial client under `front/ecommerce/mi-cliente.local/wp-content/themes/mi-cliente-theme/`
    - Create: `style.css` (theme header), `functions.php`, `theme.json`, `templates/`, `parts/`, `patterns/`, `assets/`
    - Declare WordPress 6.x FSE support
    - _Requirements: 3.1, 3.2_

  - [x] 8.2 Implement `theme.json` with full design token configuration
    - Map tokens from DESIGN.md: color palette, font families, font sizes, spacing sizes, layout (contentSize, wideSize)
    - Disable custom colors (`"custom": false`), custom font sizes (`"customFontSize": false`), custom spacing (`"customSpacingSize": false`)
    - Define border radius presets
    - Use theme.json as single source of truth (no duplicate values in CSS)
    - Generate with default values and warning if DESIGN.md not found
    - _Requirements: 3.4, 3.6, 5.1, 10.4_

  - [x] 8.3 Implement `functions.php` with theme support declarations and core setup
    - Declare: `woocommerce`, `wp-block-styles`, `editor-styles`, `responsive-embeds`
    - Register block patterns (minimum 4: hero, product-catalog, testimonials, footer)
    - Implement block restriction for client role
    - Implement conditional SEO output (disable if Yoast/RankMath active)
    - Implement lazy loading for images, defer/async for non-critical scripts
    - _Requirements: 3.3, 3.5, 4.1, 5.3, 5.4, 9.5, 10.1, 10.4_


- [x] 9. Implement WordPress theme templates and WooCommerce support
  - [~] 9.1 Create FSE templates for core pages
    - `templates/index.html` — main template
    - `templates/archive-product.html` — shop/product archive page
    - `templates/single-product.html` — individual product page
    - `templates/page-cart.html` — cart page
    - `templates/page-checkout.html` — checkout page
    - `templates/page.html` — generic page
    - `templates/404.html` — not found page
    - _Requirements: 3.5, 4.2_

  - [~] 9.2 Create template parts for reusable sections
    - `parts/header.html` — site header with navigation
    - `parts/footer.html` — site footer
    - `parts/sidebar.html` — optional sidebar
    - Implement responsive navigation (hamburger menu for mobile < 768px)
    - Ensure touch targets minimum 44x44px, transitions 200-400ms
    - _Requirements: 8.1, 8.4_

  - [~] 9.3 Implement WooCommerce template overrides with Design System styling
    - Style: buttons (add to cart, proceed to payment), product cards, checkout forms, notifications, price/discount badges, product pagination
    - Apply mobile-first CSS with min-width media queries
    - Breakpoints: mobile (< 768px), tablet (768-1024px), desktop (> 1024px)
    - _Requirements: 4.3, 4.4, 8.1, 8.3_

  - [~] 9.4 Implement graceful degradation when WooCommerce is deactivated
    - Render pages without fatal PHP errors
    - Hide WooCommerce-related elements cleanly (no empty containers)
    - _Requirements: 4.5_


- [x] 10. Implement block patterns and custom blocks for client CMS
  - [~] 10.1 Register minimum 5 block patterns (hero, product-catalog, testimonials, footer, CTA/features)
    - Each pattern uses only theme-defined colors and typography from theme.json
    - Patterns visible in block inserter
    - _Requirements: 5.3, 10.1_

  - [~] 10.2 Implement 5 custom blocks with preconfigurated style options
    - Blocks use only brand palette and typography from theme.json
    - Prevent client from applying styles outside design system
    - _Requirements: 10.5_

  - [~] 10.3 Implement admin panel restrictions for client role
    - Show only: Entradas, Páginas, Productos, Medios, Apariencia > Menús y Widgets
    - Restrict block inserter to theme-registered blocks only
    - Disable custom colors, custom font sizes, arbitrary spacing
    - _Requirements: 10.3, 10.4_

  - [~] 10.4 Implement protection dialog for critical page operations
    - Show warning dialog when client attempts to delete main pages (inicio, tienda, contacto)
    - Show warning when attempting to modify page templates or homepage settings
    - Require explicit confirmation to proceed
    - _Requirements: 10.6_

  - [~] 10.5 Create user documentation (PDF or internal page) for common client tasks
    - Cover: adding products, changing images, editing text, managing menus
    - _Requirements: 10.2_


- [x] 11. Implement SEO features and semantic markup
  - [~] 11.1 Implement semantic HTML structure across all templates
    - Use correct tags: header, nav, main, article, section, footer
    - Implement heading hierarchy: exactly one H1 per page, no level skips
    - Generate clean URLs (lowercase, hyphens, no query params for main content)
    - _Requirements: 9.1, 9.4, 9.7_

  - [~] 11.2 Implement JSON-LD structured data (Schema.org)
    - Product pages: type Product
    - Homepage: type Organization
    - All pages with breadcrumbs: type BreadcrumbList
    - Validate with Google Rich Results Test (no critical errors)
    - _Requirements: 9.2, 9.7_

  - [~] 11.3 Implement Open Graph and Twitter Card meta tags
    - Generate og:title, og:description, og:image, og:url, og:type on all public pages
    - Generate twitter:card, twitter:title, twitter:description, twitter:image
    - Conditional: disable if Yoast SEO or Rank Math is active
    - Fallback: generate basic title/description from content if no SEO plugin active
    - _Requirements: 9.3, 9.5, 9.8_

  - [~] 11.4 Implement breadcrumb navigation with structured markup
    - Navigable breadcrumbs on all pages
    - BreadcrumbList Schema.org markup
    - _Requirements: 9.7_

- [x] 12. Implement responsive design and advanced interactive components
  - [~] 12.1 Implement complex layouts: grids (2-12 columns), parallax sections, sliders/carousels, mega menus
    - Use mobile-first approach with min-width media queries
    - Maximum 3 external JS libraries, combined weight ≤ 150KB uncompressed
    - _Requirements: 8.2, 8.3, 8.6_

  - [~] 12.2 Implement interactive components: accordions, tabs, modals, dynamic product filters
    - Graceful degradation: content accessible in static format if JS fails
    - Animations at minimum 30 FPS, CLS < 0.1
    - _Requirements: 8.6, 8.7, 8.8_

  - [~] 12.3 Implement performance optimizations for images and scripts
    - Lazy loading for all images outside initial viewport
    - Defer/async for non-critical scripts
    - Serve images in WebP/AVIF with JPEG/PNG fallback
    - Target: Lighthouse Performance ≥ 85
    - _Requirements: 5.4, 5.5, 8.8_


- [~] 13. Checkpoint - Ensure WordPress theme tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 14. Implement product catalog with variable products and filters
  - [~] 14.1 Implement product catalog page with hierarchical categories and filter UI
    - Categories: Ropa, Zapatillas, Accesorios with subcategories
    - Filters: type, size, gender, color, brand, price range
    - Show result count, allow removing individual filters without full page reload
    - _Requirements: 16.1, 16.2, 16.3_

  - [~] 14.2 Write property test for product filter conjunction
    - **Property 9: Product Filter Conjunction**
    - **Validates: Requirements 16.2, 16.3**

  - [~] 14.3 Implement single product page with variable product support
    - Image gallery with zoom/lightbox, color selector with visual swatches, size selector with availability
    - Update main image on color selection, show only sizes with stock for selected color
    - Mark out-of-stock variations as unavailable (greyed out, unselectable)
    - Display: description, price (regular + sale), add to cart button
    - _Requirements: 16.4, 16.5, 16.6, 16.7_

  - [~] 14.4 Write property test for variable product invariants
    - **Property 10: Variable Product Invariants**
    - **Validates: Requirements 16.5, 16.6, 16.7**

  - [~] 14.5 Implement catalog list view with product cards
    - Show: main image, name, price, discount badge, color swatches (clickable circles)
    - _Requirements: 16.8_


- [x] 15. Implement shopping cart and checkout flow
  - [~] 15.1 Implement add-to-cart logic with validation for variable products
    - Require valid size + color selection with stock > 0
    - Show visual confirmation (mini-cart/notification) with product details
    - Show validation message if size or color not selected
    - _Requirements: 17.1, 17.2_

  - [~] 15.2 Write property test for cart addition validation
    - **Property 11: Cart Addition Validation**
    - **Validates: Requirements 17.1, 17.2**

  - [~] 15.3 Implement cart page with quantity management
    - Display: product image, name, size, color, unit price, line subtotal
    - Controls: increment/decrement quantity (min 1), remove item
    - Show: subtotal, taxes, estimated shipping, total
    - Recalculate without full page reload on quantity change
    - Cap quantity at max available stock
    - _Requirements: 17.3, 17.4, 17.5_

  - [~] 15.4 Write property test for cart calculation correctness
    - **Property 12: Cart Calculation Correctness**
    - **Validates: Requirements 17.4, 17.5**

  - [~] 15.5 Implement checkout page with shipping and payment selection
    - Form fields: nombre completo, dirección, ciudad, departamento, código postal, teléfono
    - Shipping method selection (from ERP integration)
    - Payment method selection (configured gateways)
    - _Requirements: 17.6_

  - [~] 15.6 Implement order confirmation page and email notification
    - Confirmation page: order number, product summary, shipping address, shipping method, payment method, total
    - Email: sent within 5 minutes with full order details and tracking link
    - _Requirements: 17.8, 17.9_

  - [~] 15.7 Implement payment failure handling with state preservation
    - Preserve cart items and checkout form data on payment failure
    - Show descriptive error message, allow retry or alternative payment method
    - _Requirements: 17.10_

  - [~] 15.8 Write property test for cart state preservation on payment failure
    - **Property 13: Cart State Preservation on Payment Failure**
    - **Validates: Requirements 17.10**


- [x] 16. Implement WhatsApp floating button
  - [~] 16.1 Implement WhatsApp button component with contextual messaging
    - Floating button: fixed position bottom-right, z-index 9999, min touch area 48x48px
    - Product pages: message includes product name + URL
    - Other pages: generic consultation message
    - Use official WhatsApp icon, WCAG 2.1 AA contrast (4.5:1 minimum)
    - Mobile: no overlap with navigation or action buttons
    - _Requirements: 18.1, 18.2, 18.3, 18.4, 18.6_

  - [~] 16.2 Implement WhatsApp admin configuration panel
    - Settings: phone number (international format), product message template, generic message, excluded pages
    - Hide button if phone number not configured (no JS errors, no empty HTML)
    - _Requirements: 18.5, 18.7_

  - [~] 16.3 Write property test for WhatsApp message generation
    - **Property 14: WhatsApp Message Generation**
    - **Validates: Requirements 18.3, 18.4**

- [~] 17. Checkpoint - Ensure storefront and cart tests pass
  - Ensure all tests pass, ask the user if questions arise.


- [x] 18. Implement ERP Integration Layer plugin — core infrastructure
  - [~] 18.1 Create ERP Integration Layer plugin structure (`woocommerce-erp-integration/`)
    - Plugin header, autoloader, main plugin class
    - Define interfaces: `ERPClientInterface`, `SyncService`, `ConflictResolver`, `SyncQueue`
    - Implement admin settings page with all configuration options (API URL, keys, sync intervals, retry settings, log level)
    - Store credentials encrypted
    - _Requirements: 15.1, 15.2_

  - [~] 18.2 Implement `ERPClient` class with authentication and health check
    - Implement `authenticate()` with token refresh on 401
    - Implement `healthCheck()` endpoint call
    - Handle timeouts, rate limits (429), and server errors (500/503)
    - Implement backoff strategy (30s → 120s → 600s)
    - _Requirements: 15.1, 15.8_

  - [~] 18.3 Implement `SyncQueue` — retry queue for failed operations
    - Enqueue failed operations with payload preservation
    - Process queue in FIFO order with configurable batch size
    - Retry with exponential backoff (max 3 attempts)
    - Mark as "failed" after max retries, notify admin
    - _Requirements: 15.8_

  - [~] 18.4 Write property test for sync queue round trip (no data loss)
    - **Property 18: Sync Queue Round Trip (No Data Loss)**
    - **Validates: Requirements 15.8**

  - [~] 18.5 Write property test for sync queue FIFO ordering
    - **Property 22: Sync Queue FIFO Ordering**
    - **Validates: Requirements 15.8**

  - [~] 18.6 Implement `ConflictResolver` with ERP-wins strategy
    - Stock conflicts: always return ERP value
    - Customer conflicts: merge (WC may have newer contact data)
    - Order status conflicts: ERP wins (except payment from WC)
    - _Requirements: 15.3, 15.8_

  - [~] 18.7 Write property test for ERP conflict resolution determinism
    - **Property 19: ERP Conflict Resolution Determinism**
    - **Validates: Requirements 15.3, 15.8**


- [x] 19. Implement ERP Integration — product and stock synchronization
  - [~] 19.1 Implement product catalog sync (ERP → WooCommerce)
    - `getProducts()` with delta sync (by updated_at)
    - Transform ERP product format to WooCommerce format (including variable products with N variations)
    - Create/update products via WC REST API
    - Cron job for periodic sync (configurable interval, default 1 hour)
    - _Requirements: 15.1, 15.3, 16.5_

  - [~] 19.2 Write property test for ERP product sync data preservation
    - **Property 20: ERP Product Sync Data Preservation**
    - **Validates: Requirements 15.1, 16.5**

  - [~] 19.3 Implement stock sync (ERP → WooCommerce)
    - Handle `stock_updated` webhook from ERP
    - Polling fallback (every 5 minutes)
    - Map SKU ERP → Product ID WooCommerce
    - Update stock_quantity per variation
    - Mark as out-of-stock if stock = 0, reactivate if stock > threshold
    - _Requirements: 15.3, 15.5, 15.7_

  - [~] 19.4 Write property test for ERP stock sync convergence
    - **Property 16: ERP Stock Sync Convergence (ERP-Wins)**
    - **Validates: Requirements 15.3, 15.5, 15.7**

  - [~] 19.5 Implement price sync (ERP → WooCommerce)
    - Handle `price_updated` webhook from ERP
    - Polling fallback (every 30 minutes)
    - Update regular_price and sale_price per SKU
    - _Requirements: 16.4, 16.5_

  - [~] 19.6 Write property test for ERP price sync consistency
    - **Property 17: ERP Price Sync Consistency**
    - **Validates: Requirements 16.4, 16.5**


- [x] 20. Implement ERP Integration — orders, customers, and webhook handling
  - [~] 20.1 Implement order push (WooCommerce → ERP)
    - Hook into `woocommerce_new_order` and `woocommerce_payment_complete`
    - Map WC order data to ERP OrderPayload format (external_id, customer, items, totals, payment)
    - Store ERP order_id as order meta
    - Handle 409 Conflict (duplicate order)
    - _Requirements: 15.3, 17.7_

  - [~] 20.2 Write property test for ERP order mapping completeness
    - **Property 15: ERP Order Mapping Completeness**
    - **Validates: Requirements 15.3, 17.7**

  - [~] 20.3 Implement customer sync (WooCommerce → ERP)
    - Hook into `woocommerce_created_customer` and `woocommerce_update_customer`
    - Push customer data to ERP
    - _Requirements: 15.3_

  - [~] 20.4 Implement webhook endpoint for receiving ERP updates
    - Register REST route `erp-integration/v1/webhook`
    - Validate HMAC signature on incoming webhooks
    - Handle events: stock_updated, order_status_changed, shipment_created, shipment_updated, price_updated, invoice_generated
    - _Requirements: 15.3, 15.8_

  - [~] 20.5 Write property test for webhook signature validation
    - **Property 21: Webhook Signature Validation**
    - **Validates: Requirements 15.1, 15.8**

  - [~] 20.6 Implement ERP connection monitoring and degraded mode
    - Detect disconnection > 5 minutes
    - Log disconnection, enqueue pending operations
    - Notify admin
    - Execute full sync on reconnection
    - Use cached data when ERP unavailable
    - _Requirements: 15.8_


- [~] 21. Checkpoint - Ensure ERP integration tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 22. Implement ERP Shipping Method and logistics integration
  - [~] 22.1 Implement `ERPShippingMethod` class extending `WC_Shipping_Method`
    - Register as WooCommerce shipping method (`erp_shipping`)
    - `calculate_shipping()`: call ERP `getShippingRates()` with origin, destination, weight, dimensions
    - Display carrier options with name, service, price, estimated days
    - Implement fallback rates by zone when ERP times out (> 8 seconds)
    - _Requirements: 13.1, 13.3, 13.4, 13.5_

  - [~] 22.2 Implement shipping zone configuration with fallback rates
    - Configure zones by departamento, provincia, distrito
    - Define coverage zones and unavailable zones
    - Store fallback rates per zone for when APIs are unavailable
    - _Requirements: 13.5_

  - [~] 22.3 Implement shipment tracking updates via ERP webhooks
    - Handle `shipment_created` webhook: store carrier, tracking number, label URL
    - Handle `shipment_updated` webhook: update order status, send customer notification email with tracking number
    - _Requirements: 13.6_

  - [~] 22.4 Implement carrier selection rules
    - Rules based on: destination zone, package weight, admin preference (lowest cost or fastest delivery)
    - Pass preferred_carrier to ERP when creating shipment
    - Handle oversized packages: mark as "requiere cotización especial", notify admin, show message to customer
    - _Requirements: 13.8, 13.9_

  - [~] 22.5 Implement WhatsApp shipping notifications (optional)
    - Configure integration with WhatsApp Business API or Twilio
    - Send shipping status updates via WhatsApp
    - Document integration contract
    - _Requirements: 13.7_

  - [~] 22.6 Create logistics integration documentation
    - Configuration guide for each carrier API
    - Procedure to add new carriers
    - Troubleshooting: expired API keys, endpoint changes, rate limits
    - Plugin update procedures
    - _Requirements: 13.2, 13.10_


- [x] 23. Implement payment gateway integrations
  - [~] 23.1 Install and configure payment gateway plugins (Yape/Plin, Mercado Pago, Culqi/Kushki)
    - Configure credentials (API keys, merchant IDs, webhooks)
    - Set up sandbox and production environments
    - Verify tokenized payment flow (no card data stored on server)
    - _Requirements: 14.1, 14.3_

  - [~] 23.2 Implement payment confirmation and order status updates
    - Receive transaction status from gateway (approved, rejected, pending)
    - Update WooCommerce order status within 30 seconds of provider response
    - Push payment confirmation to ERP
    - _Requirements: 14.4_

  - [~] 23.3 Implement payment failure handling
    - Show descriptive error message (no technical internals exposed)
    - Allow retry or alternative payment method without losing order data
    - _Requirements: 14.5_

  - [~] 23.4 Create payment integration documentation
    - Document each gateway: plugin, version, credentials, flow (redirect vs inline), sandbox vs production config
    - _Requirements: 14.2, 14.12_

- [x] 24. Implement invoicing integration (ERP → SUNAT)
  - [~] 24.1 Implement invoice request flow (WooCommerce → ERP → SUNAT)
    - On payment confirmed: request invoice generation from ERP (`POST /orders/{id}/invoice`)
    - Support boleta and factura based on customer document type (DNI vs RUC)
    - _Requirements: 14.6, 14.8_

  - [~] 24.2 Implement invoice webhook handling and document storage
    - Handle `invoice_generated` webhook from ERP
    - Fetch PDF/XML documents from ERP
    - Store documents associated with WooCommerce order
    - Send email to customer with PDF attachment within 5 minutes of SUNAT acceptance
    - _Requirements: 14.9_

  - [~] 24.3 Implement invoice error handling and admin panel
    - Handle SUNAT rejection: log error code, mark as pending, notify admin
    - Provide admin view of invoice registry with filters (type, date range, SUNAT status, customer)
    - _Requirements: 14.10, 14.11_

  - [~] 24.4 Create invoicing integration documentation
    - Configuration guide: service, credentials, RUC, digital certificate, series/correlativo
    - Certificate renewal procedure
    - Troubleshooting: webhook failures, API timeouts, SUNAT rejections
    - _Requirements: 14.7, 14.12_


- [x] 25. Implement POS integration for omnichannel inventory
  - [~] 25.1 Configure POS plugin integration (Vend/Lightspeed, Square, or compatible local POS)
    - Install and configure sync plugin with credentials (API keys, OAuth tokens)
    - Verify bidirectional connectivity
    - Configure sync frequency and conflict behavior
    - _Requirements: 15.1, 15.2_

  - [~] 25.2 Implement stock sync between WooCommerce and POS
    - Bidirectional sync within 60 seconds of sale in either channel
    - Mark products as out-of-stock when stock = 0 (optionally hide from catalog)
    - Reactivate products when new stock exceeds minimum threshold
    - _Requirements: 15.3, 15.5, 15.7_

  - [~] 25.3 Implement stock alerts and inventory movement logging
    - Low stock alerts: email to admin when stock reaches configurable threshold (default 5 units)
    - Log movements: date/time, type (sale, return, adjustment, restock), channel, resulting stock
    - _Requirements: 15.4, 15.6_

  - [~] 25.4 Implement disconnection handling and admin dashboard
    - Detect disconnection > 5 minutes, log, enqueue operations, notify admin
    - Full sync on reconnection
    - Admin panel: connection status, last successful sync, low stock products, out-of-stock products
    - _Requirements: 15.8, 15.9_

  - [~] 25.5 Create POS integration documentation
    - Configuration guide, conflict resolution procedures, troubleshooting (expired tokens, API unavailable, timeouts)
    - Manual force sync procedure, guide for adding new products to sync flow
    - _Requirements: 15.10_


- [x] 26. Implement marketing automation integration
  - [~] 26.1 Install and configure marketing automation plugin (FluentCRM, Klaviyo, or Omnisend)
    - Connect with WooCommerce without conflicts with theme
    - Configure API keys, webhooks
    - Verify no fatal PHP errors
    - _Requirements: 12.1_

  - [~] 26.2 Configure initial automation flows
    - Cart abandonment recovery: triggered after 1 hour (configurable), sequence of 1-3 emails
    - First purchase coupon: sent within 5 minutes post-confirmation
    - Birthday greeting: sent same day or 24 hours before
    - Customer reactivation: periodic emails (7-90 days configurable) to inactive customers
    - _Requirements: 12.3, 12.4_

  - [~] 26.3 Configure error notifications and statistics access
    - Auto-notify admin on connection loss or plugin errors
    - Verify statistics panel accessible (recovered carts, open rates, coupons generated/redeemed)
    - Document access path for client
    - _Requirements: 12.6, 12.7_

  - [~] 26.4 Create marketing automation documentation
    - Integration contract: plugin, version, credentials, WooCommerce events that trigger flows, data sent/received
    - Flow configuration procedures for client self-service
    - Troubleshooting: expired API key, outdated plugin, plugin conflicts, sending limits
    - _Requirements: 12.2, 12.5, 12.8_

- [~] 27. Checkpoint - Ensure all integration tests pass
  - Ensure all tests pass, ask the user if questions arise.


- [x] 28. Implement CI/CD pipeline with GitHub Actions
  - [~] 28.1 Create `.github/workflows/deploy-staging.yml` pipeline
    - Trigger on push to main with paths `front/**`
    - Validate step: PHP_CodeSniffer linting + PHP 8.1+ compatibility check
    - Package step: create ZIP of WordPress theme
    - Deploy step: SFTP/SSH to staging server
    - Health check: verify HTTP 200 within 30 seconds post-deploy
    - Abort and notify on lint/compatibility failure without modifying target environment
    - _Requirements: 7.1, 7.2, 7.3, 7.5, 7.7, 7.8_

  - [~] 28.2 Create `.github/workflows/deploy-production.yml` pipeline
    - Require manual approval after successful staging deploy
    - Deploy to production environment
    - Health check post-deploy
    - Notify team on failure (email, Slack, or webhook)
    - _Requirements: 7.3, 7.4, 7.6_

  - [~] 28.3 Configure multi-client pipeline support
    - Support deploying specific client themes based on changed paths
    - Per-client environment variables (server credentials, URLs)
    - _Requirements: 7.1, 7.3_

- [x] 29. Wire all components together and final integration
  - [~] 29.1 Wire ERP Integration Layer with all WooCommerce hooks
    - Connect order creation → ERP push
    - Connect payment confirmation → ERP + invoice request
    - Connect stock webhooks → WooCommerce product updates
    - Connect shipment webhooks → order status + customer notifications
    - Connect invoice webhooks → document storage + customer email
    - _Requirements: 13.6, 14.4, 14.8, 15.3, 17.7_

  - [~] 29.2 Wire shipping method with checkout flow
    - ERP shipping rates displayed in checkout
    - Selected carrier passed to ERP on order creation
    - Tracking info displayed in order details
    - _Requirements: 13.3, 17.6_

  - [~] 29.3 Wire payment gateways with invoicing flow
    - Payment confirmed → trigger invoice generation via ERP
    - Invoice generated → attach PDF to order, email to customer
    - _Requirements: 14.8, 14.9_

  - [~] 29.4 Wire POS sync with stock management
    - POS sale → stock update in WooCommerce
    - WooCommerce sale → stock update via ERP → POS
    - Low stock alerts triggered from either channel
    - _Requirements: 15.3, 15.4_


- [~] 30. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation at key milestones
- Property tests validate universal correctness properties defined in the design document
- Unit tests validate specific examples and edge cases
- The ERP is the source of truth for inventory, prices, and order status — WooCommerce is a sales channel
- Scripts use TypeScript/Node.js (validation, token transfer, comparison) and PowerShell (structure validation, packaging)
- WordPress theme uses PHP with FSE (Full Site Editing) and theme.json
- CI/CD uses GitHub Actions with multi-environment support
- All integration documentation (contracts) should follow the format defined in the design document

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2", "1.3", "1.4", "1.5"] },
    { "id": 1, "tasks": ["2.1", "2.3", "2.5", "3.5"] },
    { "id": 2, "tasks": ["2.2", "2.4", "2.6", "3.1", "3.3"] },
    { "id": 3, "tasks": ["3.2", "3.4", "5.1", "5.3", "5.5"] },
    { "id": 4, "tasks": ["5.2", "5.4", "6.1"] },
    { "id": 5, "tasks": ["6.2", "8.1"] },
    { "id": 6, "tasks": ["8.2", "8.3"] },
    { "id": 7, "tasks": ["9.1", "9.2", "9.3", "9.4"] },
    { "id": 8, "tasks": ["10.1", "10.2", "10.3", "10.4", "10.5", "11.1", "11.2", "11.3", "11.4"] },
    { "id": 9, "tasks": ["12.1", "12.2", "12.3", "14.1", "14.3", "14.5"] },
    { "id": 10, "tasks": ["14.2", "14.4", "15.1", "15.3", "15.5", "15.6", "15.7"] },
    { "id": 11, "tasks": ["15.2", "15.4", "15.8", "16.1", "16.2"] },
    { "id": 12, "tasks": ["16.3", "18.1"] },
    { "id": 13, "tasks": ["18.2", "18.3", "18.6"] },
    { "id": 14, "tasks": ["18.4", "18.5", "18.7", "19.1", "19.3", "19.5"] },
    { "id": 15, "tasks": ["19.2", "19.4", "19.6", "20.1", "20.3", "20.4"] },
    { "id": 16, "tasks": ["20.2", "20.5", "20.6", "22.1", "22.2"] },
    { "id": 17, "tasks": ["22.3", "22.4", "22.5", "22.6", "23.1"] },
    { "id": 18, "tasks": ["23.2", "23.3", "23.4", "24.1"] },
    { "id": 19, "tasks": ["24.2", "24.3", "24.4", "25.1"] },
    { "id": 20, "tasks": ["25.2", "25.3", "25.4", "25.5", "26.1"] },
    { "id": 21, "tasks": ["26.2", "26.3", "26.4"] },
    { "id": 22, "tasks": ["28.1", "28.2", "28.3"] },
    { "id": 23, "tasks": ["29.1", "29.2", "29.3", "29.4"] }
  ]
}
```
