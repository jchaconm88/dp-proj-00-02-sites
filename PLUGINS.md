# Plugins Recomendados por Tipo de Sitio

Este documento lista los plugins recomendados para cada tipo de sitio web desarrollado por la agencia. Cada plugin incluye su propósito, compatibilidad verificada y tipo de licencia.

---

## E-commerce

Plugins recomendados para tiendas virtuales con WooCommerce.

| # | Nombre | Propósito | Compatibilidad | Tipo de Licencia |
|---|--------|-----------|----------------|------------------|
| 1 | WooCommerce | Plataforma principal de comercio electrónico. Gestión de productos, carrito de compras, checkout y pedidos. | WordPress 6.4+ / PHP 8.1+ | Gratuita (GPLv3) |
| 2 | Mercado Pago for WooCommerce | Pasarela de pago para el mercado latinoamericano. Soporta tarjetas, transferencias y pagos en cuotas con Mercado Pago. | WordPress 6.4+ / WooCommerce 8.0+ | Gratuita |
| 3 | Yape/Plin Payment Gateway | Pasarela de pago local para pagos móviles con Yape y Plin, los métodos de pago más populares en Perú. | WordPress 6.4+ / WooCommerce 8.0+ | Premium |
| 4 | Culqi WooCommerce | Pasarela de pago peruana que soporta tarjetas de crédito/débito, billeteras digitales y pagos en efectivo (PagoEfectivo). | WordPress 6.4+ / WooCommerce 8.0+ | Gratuita |
| 5 | FluentCRM | Automatización de marketing por email: carritos abandonados, cupones de primera compra, segmentación de clientes y secuencias de email. | WordPress 6.4+ / WooCommerce 8.0+ | Premium (FluentCRM Pro) |
| 6 | WooCommerce PDF Invoices & Packing Slips | Generación automática de comprobantes PDF (boletas y facturas) adjuntos a los emails de confirmación de pedido. | WordPress 6.4+ / WooCommerce 8.0+ | Gratuita (extensiones premium disponibles) |

---

## Portafolio

Plugins recomendados para sitios de portafolio profesional y presentación de trabajos.

| # | Nombre | Propósito | Compatibilidad | Tipo de Licencia |
|---|--------|-----------|----------------|------------------|
| 1 | Elementor | Constructor visual de páginas con drag-and-drop. Permite crear layouts creativos, galerías y secciones personalizadas sin código. | WordPress 6.4+ / PHP 8.0+ | Gratuita (versión Pro premium) |
| 2 | Advanced Custom Fields (ACF) | Creación de campos personalizados para organizar proyectos del portafolio: cliente, fecha, categoría, galería de imágenes, enlace externo. | WordPress 6.4+ / PHP 8.0+ | Gratuita (versión PRO premium) |
| 3 | Envira Gallery | Galerías de imágenes con lightbox integrado, layouts tipo masonry y grid para mostrar trabajos visuales de forma atractiva y profesional. | WordPress 6.4+ / PHP 8.0+ | Gratuita (versión Pro premium) |
| 4 | Yoast SEO | Optimización SEO on-page: meta tags, Open Graph, sitemaps XML, breadcrumbs y análisis de contenido para mejorar visibilidad del portafolio. | WordPress 6.4+ / PHP 8.0+ | Gratuita (versión Premium disponible) |
| 5 | WP Rocket | Optimización de rendimiento: caché de página, lazy loading de imágenes, minificación de CSS/JS y precarga para tiempos de carga rápidos. | WordPress 6.4+ / PHP 8.0+ | Premium |

---

## Landing Page

Plugins recomendados para páginas de aterrizaje orientadas a conversión y captación de leads.

| # | Nombre | Propósito | Compatibilidad | Tipo de Licencia |
|---|--------|-----------|----------------|------------------|
| 1 | Contact Form 7 | Formularios de contacto flexibles y personalizables. Soporta validación, AJAX, CAPTCHA y múltiples destinatarios de email. | WordPress 6.4+ / PHP 8.0+ | Gratuita (GPLv2) |
| 2 | Yoast SEO | Optimización SEO on-page: meta títulos, descripciones, Open Graph para redes sociales y análisis de legibilidad del contenido. | WordPress 6.4+ / PHP 8.0+ | Gratuita (versión Premium disponible) |
| 3 | Google Site Kit | Integración oficial de Google Analytics, Search Console y PageSpeed Insights directamente en el dashboard de WordPress para medir conversiones. | WordPress 6.4+ / PHP 8.0+ | Gratuita |
| 4 | Fluent Forms | Constructor de formularios avanzado con lógica condicional, formularios multi-paso y integración con CRMs para captura de leads. | WordPress 6.4+ / PHP 8.0+ | Gratuita (versión Pro premium) |
| 5 | Perfmatters | Optimización de rendimiento: desactivación de scripts innecesarios, lazy loading, preconnect y eliminación de bloat para maximizar velocidad de carga. | WordPress 6.4+ / PHP 8.0+ | Premium |

---

## Notas Generales

- **Versión de WordPress**: Todos los plugins han sido verificados con WordPress 6.4 o superior.
- **PHP**: Se requiere PHP 8.0+ como mínimo; se recomienda PHP 8.1+ para compatibilidad óptima con WooCommerce.
- **Actualizaciones**: Verificar compatibilidad antes de actualizar plugins en producción. Realizar pruebas en staging primero.
- **Licencias Premium**: Los plugins premium requieren licencia activa para recibir actualizaciones y soporte. Renovar anualmente.
- **Full Site Editing (FSE)**: Se prioriza el uso del sistema nativo de bloques de WordPress y `theme.json` como fuente única de tokens de diseño. Los plugins de constructor visual (como Elementor) se recomiendan solo cuando el proyecto lo requiera explícitamente.
