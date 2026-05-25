# Requirements Document

## Introduction

Este documento define los requisitos para estructurar un proyecto de agencia web dedicado a la venta de páginas web construidas con WordPress. El proyecto debe organizar eficientemente múltiples clientes/sitios web en dos áreas principales: plantillas de diseño (mockups HTML) y temas WordPress listos para despliegue. Se recomienda una arquitectura **monorepo** con separación clara por carpetas, ya que permite compartir configuraciones, scripts de build, y mantener consistencia entre diseños y sus implementaciones WordPress correspondientes, sin la complejidad de gestionar múltiples repositorios independientes.

## Glossary

- **Proyecto_Agencia**: El monorepo principal que contiene toda la estructura del negocio de venta de páginas web
- **Módulo_Templates**: Carpeta/proyecto dedicado a almacenar las plantillas de diseño HTML, capturas de pantalla y documentación de diseño de cada cliente
- **Módulo_Front**: Carpeta/proyecto dedicado a los temas WordPress completamente funcionales y listos para despliegue en producción
- **Cliente**: Cada sitio web individual que se desarrolla, identificado por su hostname o nombre de dominio
- **Design_System**: Conjunto de tokens de diseño (colores, tipografía, espaciado) definidos en DESIGN.md que guían la implementación visual
- **Tema_WordPress**: Tema hijo o tema personalizado basado en un starter theme compatible con WooCommerce y las mejores prácticas de WordPress
- **WooCommerce**: Plugin de comercio electrónico para WordPress que habilita funcionalidad de tienda virtual
- **Starter_Theme**: Tema base de WordPress (como Starter Theme de flavor Gutenberg/FSE) sobre el cual se construyen los temas personalizados de cada cliente
- **Motor_Automatización**: Servicio externo de automatización de marketing (FluentCRM, Klaviyo u Omnisend) que se integra con WooCommerce mediante plugin oficial o API para ejecutar flujos automáticos basados en eventos del cliente; el proyecto configura y documenta la integración, no desarrolla el motor de automatización
- **Flujo_Automático**: Secuencia de acciones predefinidas (envío de correos, aplicación de cupones, notificaciones) configurada en el servicio externo de automatización que se ejecuta automáticamente al cumplirse una condición o evento en WooCommerce
- **Integración_Logística**: Capa de conexión entre WooCommerce y servicios externos de envío (APIs de Olva Courier, Scharff, PedidosYa, Rappi) implementada mediante plugins de shipping o desarrollo de adaptadores API; el proyecto configura, conecta y documenta los contratos de integración sin desarrollar la lógica de cálculo de tarifas ni el sistema de tracking
- **API_Envíos**: Interfaz de programación de aplicaciones provista por empresas de envío externas (Olva Courier, Scharff) o plataformas de delivery (PedidosYa, Rappi) que expone endpoints para cotizar tarifas, generar guías y consultar estados de envío
- **Contrato_Integración**: Documento técnico que especifica los endpoints, métodos HTTP, formatos de request/response, autenticación, códigos de error y SLAs esperados de una API externa con la que el proyecto se integra
- **Plugin_Pasarela**: Plugin oficial o certificado de WooCommerce que conecta la tienda con un servicio externo de procesamiento de pagos (Yape/Plin, Mercado Pago, Culqi, Kushki); el proyecto instala, configura y documenta la integración sin desarrollar la lógica de procesamiento de pagos
- **Plugin_Facturación**: Plugin o servicio externo (Nubefact, Efact, OpenFactura o similar) que se integra con WooCommerce para generar y transmitir comprobantes electrónicos a SUNAT; el proyecto configura la integración y documenta el flujo sin desarrollar el motor de facturación
- **SUNAT**: Superintendencia Nacional de Aduanas y de Administración Tributaria del Perú, entidad que recibe los comprobantes electrónicos a través de su API oficial
- **Sistema_POS_Externo**: Sistema de punto de venta físico de terceros (Vend/Lightspeed, Square, o POS local compatible) que expone una API o plugin de sincronización para conectarse con WooCommerce
- **Integración_Inventario**: Capa de conexión entre WooCommerce y el Sistema_POS_Externo implementada mediante plugins oficiales o APIs; el proyecto configura, conecta y documenta la sincronización de inventario sin desarrollar el sistema POS ni la lógica de gestión de stock del punto de venta físico
- **Catálogo_Ropa_Zapatillas**: Catálogo de productos de la tienda inicial especializado en ropa, zapatillas y accesorios, organizado por categorías, con soporte para productos variables (múltiples tallas y colores por producto)
- **Producto_Variable**: Producto de WooCommerce que tiene múltiples variaciones definidas por atributos combinados (talla y color), donde cada variación puede tener su propio precio, stock e imagen
- **Flujo_Venta**: Proceso completo de compra desde la selección de producto hasta la confirmación del pedido, incluyendo: agregar al carrito, revisión del carrito, checkout con datos de envío y pago, y confirmación con notificación por correo
- **Botón_WhatsApp**: Elemento flotante visible en todas las páginas del sitio que permite al visitante iniciar una conversación de WhatsApp con el negocio, con mensaje pre-configurado contextual según la página visitada

## Requirements

### Requisito 1: Estructura Monorepo del Proyecto

**Historia de Usuario:** Como desarrollador de la agencia, quiero tener un monorepo bien organizado con separación clara entre diseños y temas WordPress, para poder gestionar múltiples clientes de forma eficiente y escalable.

#### Criterios de Aceptación

1. THE Proyecto_Agencia SHALL organizar el código fuente en un monorepo con dos módulos principales: `templates/` para plantillas de diseño y `front/` para temas WordPress, donde cada módulo contiene al menos una carpeta de cliente con su estructura interna correspondiente
2. THE Proyecto_Agencia SHALL incluir un archivo README.md en la raíz que contenga como mínimo las siguientes secciones: descripción del proyecto, estructura de carpetas del monorepo, convención de nombres para clientes, flujo de trabajo entre módulos, e instrucciones para agregar un nuevo cliente
3. THE Proyecto_Agencia SHALL incluir en la raíz del monorepo archivos de configuración compartida (`.editorconfig` y `.gitignore`) que apliquen reglas consistentes de formato y exclusión a ambos módulos sin requerir configuración adicional por módulo
4. WHEN se agrega un nuevo cliente, THE Proyecto_Agencia SHALL permitir crear su carpeta en el Módulo_Templates usando como nombre el hostname del dominio en formato lowercase con guiones y TLD (ejemplo: `mi-cliente.local/`), conteniendo como mínimo la subcarpeta `design/`
5. WHEN se agrega un nuevo cliente que requiere tema WordPress, THE Proyecto_Agencia SHALL permitir crear su carpeta correspondiente en el Módulo_Front usando el mismo hostname que en el Módulo_Templates, asegurando una correspondencia 1:1 entre ambos módulos para ese cliente
6. IF el nombre de carpeta de un nuevo cliente no cumple el formato de hostname válido (lowercase, caracteres alfanuméricos y guiones, máximo 63 caracteres por segmento, con TLD), THEN THE Proyecto_Agencia SHALL rechazar la creación e indicar el formato esperado

### Requisito 2: Módulo de Plantillas de Diseño (Templates)

**Historia de Usuario:** Como diseñador de la agencia, quiero un espacio dedicado para almacenar los mockups HTML, capturas de pantalla y documentación de diseño de cada cliente, para tener una referencia visual clara antes de implementar en WordPress.

#### Criterios de Aceptación

1. THE Módulo_Templates SHALL organizar cada cliente en una carpeta nombrada con su hostname en formato de dominio válido, conteniendo solo caracteres alfanuméricos, guiones y puntos, con una longitud máxima de 253 caracteres (ejemplo: `templates/mi-cliente.local/`)
2. THE Módulo_Templates SHALL requerir que cada cliente contenga una subcarpeta `design/` con al menos: un archivo HTML del mockup (`code.html`) con contenido HTML válido no vacío, un archivo de documentación del sistema de diseño (`DESIGN.md`) que defina los tokens de diseño (colores, tipografía, espaciado), y una captura de pantalla del diseño (`screen.png`) en formato PNG
3. IF la subcarpeta `design/` de un cliente no contiene alguno de los archivos requeridos (`code.html`, `DESIGN.md`, `screen.png`), THEN THE Módulo_Templates SHALL reportar un error de validación indicando los archivos faltantes
4. WHEN se crea un nuevo proyecto de cliente en el Módulo_Templates, THE Módulo_Templates SHALL proveer una estructura de carpetas base que incluya `design/` con archivos plantilla para `code.html`, `DESIGN.md` y `screen.png`, y opcionalmente `components/` para plantillas HTML empaquetables
5. IF la carpeta `components/` del cliente existe y contiene archivos, THEN THE Módulo_Templates SHALL generar un archivo `template.zip` mediante un script de empaquetado, incluyendo los archivos de plantilla (HTML, manifesto, partials, estilos) y excluyendo archivos de ejemplo y documentación interna
6. IF se ejecuta el script de empaquetado y la carpeta `components/` no existe o está vacía, THEN THE Módulo_Templates SHALL reportar un error indicando que no hay contenido empaquetable

### Requisito 3: Módulo de Temas WordPress (Front)

**Historia de Usuario:** Como desarrollador WordPress de la agencia, quiero un espacio dedicado para los temas WordPress funcionales de cada cliente, para poder desarrollar, probar y desplegar sitios completos con WooCommerce.

#### Criterios de Aceptación

1. THE Módulo_Front SHALL organizar cada cliente en una carpeta nombrada con su hostname (ejemplo: `front/mi-cliente.local/`)
2. THE Módulo_Front SHALL utilizar WordPress 6.x con soporte para Full Site Editing (FSE) y el editor de bloques Gutenberg, especificando la versión mínima requerida en un archivo de configuración del proyecto
3. THE Módulo_Front SHALL basar cada tema de cliente en un starter theme compatible con WooCommerce que pase la validación del plugin WordPress Theme Check sin errores críticos y siga la estructura definida en el WordPress Theme Handbook
4. WHEN se crea un nuevo tema de cliente, THE Módulo_Front SHALL incluir la configuración base de `theme.json` mapeando los tokens de diseño del `DESIGN.md` correspondiente del Módulo_Templates, incluyendo como mínimo: paleta de colores, familias tipográficas con sus escalas de tamaño, valores de espaciado y radios de borde
5. THE Módulo_Front SHALL incluir compatibilidad con WooCommerce en cada tema declarando soporte mediante `add_theme_support('woocommerce')` y proporcionando templates personalizados para: página de tienda (archive-product), producto individual (single-product), carrito (cart) y checkout
6. IF el `DESIGN.md` correspondiente no existe en el Módulo_Templates al momento de crear un nuevo tema de cliente, THEN THE Módulo_Front SHALL generar el `theme.json` con valores por defecto documentados y registrar una advertencia indicando la ausencia del archivo de diseño

### Requisito 4: Compatibilidad con WooCommerce y Tiendas Virtuales

**Historia de Usuario:** Como cliente final, quiero que mi sitio web incluya una tienda virtual funcional con WooCommerce, para poder vender productos en línea con carrito de compras y proceso de pago.

#### Criterios de Aceptación

1. THE Tema_WordPress SHALL declarar soporte para WooCommerce en el archivo `functions.php` o `theme.json`
2. THE Tema_WordPress SHALL incluir templates personalizados para: página de tienda (`archive-product.php` o bloque equivalente), página de producto individual, página de carrito y página de checkout
3. THE Tema_WordPress SHALL aplicar los estilos del Design_System del cliente a los siguientes componentes de WooCommerce como mínimo: botones de acción (agregar al carrito, proceder al pago), cards de producto en catálogo, formularios de checkout, mensajes de notificación, badges de precio y descuento, y paginación de productos
4. WHEN WooCommerce está activo, THE Tema_WordPress SHALL mostrar el catálogo de productos sin errores de layout ni elementos desbordados, con filtros de categoría funcionales que actualicen la lista de productos visibles y un campo de búsqueda que devuelva resultados coincidentes con el término ingresado
5. IF WooCommerce está desactivado o no instalado, THEN THE Tema_WordPress SHALL renderizar las páginas del sitio sin errores fatales de PHP y sin mostrar elementos vacíos o rotos relacionados con la tienda virtual

### Requisito 5: Sistema de Diseño y Plugins de Vanguardia

**Historia de Usuario:** Como desarrollador de la agencia, quiero utilizar plugins y herramientas de diseño modernas en WordPress, para ofrecer sitios web con diseño de vanguardia y alto rendimiento.

#### Criterios de Aceptación

1. THE Módulo_Front SHALL utilizar el sistema de Full Site Editing (FSE) de WordPress con `theme.json` como fuente única de definición de tokens de diseño (colores, tipografía, espaciado y layout), sin duplicar estos valores en hojas de estilo independientes
2. THE Módulo_Front SHALL documentar en un archivo `PLUGINS.md` la lista de plugins recomendados para cada tipo de sitio (e-commerce, portafolio, landing page), incluyendo por cada plugin: nombre, propósito, compatibilidad verificada con la versión de WordPress utilizada y tipo de licencia (gratuita o premium), con un mínimo de 3 plugins documentados por categoría de sitio
3. THE Tema_WordPress SHALL registrar como mínimo 4 block patterns reutilizables correspondientes a las secciones: hero, catálogo de productos, testimonios y footer, visibles en el insertador de bloques del editor de WordPress
4. THE Tema_WordPress SHALL implementar lazy loading nativo en todas las imágenes fuera del viewport inicial, carga diferida de scripts no críticos mediante atributo `defer` o `async`, y servir imágenes en formato WebP o AVIF con fallback a JPEG/PNG para navegadores sin soporte
5. THE Tema_WordPress SHALL obtener una puntuación mínima de 85 en la categoría Performance de Google Lighthouse en páginas que contengan al menos 10 imágenes y 3 block patterns

### Requisito 6: Sistema de Agrupación y Organización Multi-Cliente

**Historia de Usuario:** Como gerente de la agencia, quiero un sistema de organización que permita agrupar y categorizar los proyectos de múltiples clientes, para poder escalar el negocio sin perder el control de los proyectos.

#### Criterios de Aceptación

1. THE Proyecto_Agencia SHALL permitir agrupar clientes por categoría de negocio mediante subcarpetas opcionales dentro de cada módulo, donde cada categoría se nombra en minúsculas y sin espacios (ejemplo: `templates/ecommerce/mi-cliente.local/`, `templates/portafolio/otro-cliente.local/`, `front/ecommerce/mi-cliente.local/`)
2. THE Proyecto_Agencia SHALL incluir un archivo de registro central (`CLIENTS.md` o `clients.json`) en la raíz del monorepo que liste todos los clientes con los siguientes campos por entrada: nombre del cliente, hostname, categoría de negocio, estado actual del proyecto y rutas relativas a sus carpetas en el Módulo_Templates y el Módulo_Front
3. WHILE el número de clientes registrados es mayor a 10, THE Proyecto_Agencia SHALL mantener un índice en el archivo de registro central que permita localizar cualquier cliente por nombre, categoría o estado en no más de una búsqueda textual, agrupando las entradas por categoría y ordenándolas alfabéticamente por hostname
4. THE Proyecto_Agencia SHALL registrar el estado de proyecto de cada cliente en el archivo de registro central, limitado a uno de los siguientes valores: `diseño`, `desarrollo`, `revisión`, `producción` o `archivado`
5. WHEN el estado de un cliente cambia, THE Proyecto_Agencia SHALL reflejar el nuevo estado en el archivo de registro central antes de considerar el cambio completado
6. IF un cliente tiene estado `archivado`, THEN THE Proyecto_Agencia SHALL mantener sus carpetas en ambos módulos sin modificación pero excluirlo de los listados de clientes activos en el archivo de registro central

### Requisito 7: Pipelines de Despliegue Automático

**Historia de Usuario:** Como desarrollador de la agencia, quiero pipelines de CI/CD que automaticen el despliegue de los temas WordPress a los servidores de cada cliente, para reducir errores manuales y acelerar la entrega de sitios.

#### Criterios de Aceptación

1. THE Proyecto_Agencia SHALL incluir configuración de pipeline CI/CD (GitHub Actions, GitLab CI o similar) que permita desplegar temas WordPress de forma automatizada
2. WHEN se realiza un push a la rama `main` de un cliente específico, THE Pipeline SHALL ejecutar validaciones de código (linting PHP con PHP_CodeSniffer y compatibilidad con PHP 8.1+) antes del despliegue
3. THE Pipeline SHALL soportar múltiples entornos de despliegue por cliente: `staging` (pruebas) y `production` (producción)
4. WHEN el despliegue a staging es exitoso y se aprueba manualmente, THE Pipeline SHALL permitir promover el tema a producción
5. THE Pipeline SHALL empaquetar el tema WordPress como archivo ZIP y desplegarlo al servidor del cliente mediante SFTP, SSH o la API REST de WordPress
6. IF el despliegue falla, THEN THE Pipeline SHALL notificar al equipo mediante un canal configurado (email, Slack o webhook) e incluir los logs del error
7. THE Pipeline SHALL ejecutar un health check posterior al despliegue que verifique que el sitio responde correctamente con código HTTP 200 dentro de un plazo máximo de 30 segundos
8. IF las validaciones de código (linting o compatibilidad PHP) fallan, THEN THE Pipeline SHALL abortar el despliegue, reportar los errores encontrados en el log del pipeline y notificar al equipo sin modificar el entorno de destino

### Requisito 8: Soporte para Diseños Complejos y Responsive

**Historia de Usuario:** Como cliente final, quiero que mi sitio web soporte diseños complejos y se vea correctamente en todos los dispositivos, para ofrecer una experiencia profesional a mis visitantes sin importar cómo accedan.

#### Criterios de Aceptación

1. THE Tema_WordPress SHALL implementar diseño responsive con breakpoints definidos para móvil (< 768px), tablet (768px–1024px) y escritorio (> 1024px)
2. THE Tema_WordPress SHALL soportar layouts complejos incluyendo: grids de 2 a 12 columnas, secciones con parallax, sliders/carruseles, mega menús y animaciones de scroll
3. THE Tema_WordPress SHALL utilizar un enfoque mobile-first en la implementación de estilos CSS, donde los estilos base se apliquen al viewport más pequeño y las media queries utilicen exclusivamente min-width para adaptar a viewports mayores
4. WHEN el sitio se visualiza en dispositivos con viewport menor a 768px, THE Tema_WordPress SHALL adaptar la navegación a un menú hamburguesa con transiciones de apertura y cierre de entre 200ms y 400ms de duración, y área de toque mínima de 44x44 píxeles en todos los elementos interactivos
5. THE Tema_WordPress SHALL obtener resultado "Page is usable on mobile" en la validación de Google Mobile-Friendly Test, sin elementos que provoquen scroll horizontal ni texto ilegible sin zoom
6. THE Tema_WordPress SHALL soportar componentes interactivos avanzados (acordeones, tabs, modales, filtros dinámicos de productos) utilizando un máximo de 3 bibliotecas JavaScript externas con un peso combinado no superior a 150 KB (sin comprimir)
7. IF un componente interactivo falla al cargar o ejecutar su JavaScript, THEN THE Tema_WordPress SHALL mantener el contenido accesible en formato estático legible sin pérdida de información
8. THE Tema_WordPress SHALL renderizar animaciones de scroll y transiciones CSS a un mínimo de 30 fotogramas por segundo, y mantener un Cumulative Layout Shift (CLS) inferior a 0.1 según medición de Google Lighthouse

### Requisito 9: Optimización SEO

**Historia de Usuario:** Como cliente final, quiero que mi sitio web esté optimizado para motores de búsqueda, para posicionarme orgánicamente y atraer más clientes potenciales.

#### Criterios de Aceptación

1. THE Tema_WordPress SHALL generar markup HTML semántico utilizando las etiquetas correctas (header, nav, main, article, section, footer) en todas las páginas
2. THE Tema_WordPress SHALL incluir datos estructurados en formato JSON-LD (Schema.org) en páginas de producto (tipo Product), organización (tipo Organization en la página de inicio) y breadcrumbs (tipo BreadcrumbList), validables sin errores críticos mediante la herramienta Google Rich Results Test
3. THE Tema_WordPress SHALL generar automáticamente meta tags Open Graph (og:title, og:description, og:image, og:url, og:type) y Twitter Cards (twitter:card, twitter:title, twitter:description, twitter:image) en todas las páginas públicas al momento de renderizar el HTML
4. THE Tema_WordPress SHALL implementar una jerarquía de encabezados con exactamente un elemento H1 por página y sin saltos de nivel (no pasar de H2 a H4 sin H3 intermedio)
5. WHEN se instala un plugin SEO (Yoast SEO o Rank Math), THE Tema_WordPress SHALL desactivar la generación propia de meta tags y datos estructurados para evitar duplicados, y no aplicar estilos CSS que sobreescriban la interfaz del plugin en el panel de administración
6. THE Tema_WordPress SHALL obtener una puntuación mínima de 90 en la categoría SEO de Google Lighthouse en las siguientes páginas: inicio, archivo de productos, producto individual y página estática de contenido
7. THE Tema_WordPress SHALL generar URLs legibles en formato lowercase separadas por guiones, sin parámetros de query para contenido principal, y soportar breadcrumbs navegables con markup estructurado BreadcrumbList de Schema.org
8. IF no se encuentra activo un plugin SEO, THEN THE Tema_WordPress SHALL generar meta tags básicos (title, description) a partir del título y extracto del contenido de cada página, asegurando que ninguna página pública se renderice sin meta description

### Requisito 10: CMS Intuitivo para Mantenimiento por el Cliente

**Historia de Usuario:** Como cliente final sin conocimientos técnicos, quiero poder mantener y actualizar el contenido de mi sitio web de forma autónoma, para no depender del desarrollador para cambios cotidianos.

#### Criterios de Aceptación

1. THE Tema_WordPress SHALL utilizar el editor de bloques Gutenberg con un mínimo de 5 block patterns predefinidos que el cliente pueda insertar y modificar sin conocimientos de código
2. THE Tema_WordPress SHALL incluir documentación de usuario en formato PDF o página interna que explique cómo realizar tareas comunes: agregar productos, cambiar imágenes, editar textos y gestionar menús
3. THE Tema_WordPress SHALL limitar las opciones del panel de administración para el rol de cliente, mostrando únicamente las secciones: Entradas, Páginas, Productos, Medios y dentro de Apariencia solo Menús y Widgets
4. WHEN el cliente edita contenido mediante el editor de bloques, THE Tema_WordPress SHALL restringir las opciones de estilo a las definidas en el Design_System deshabilitando colores personalizados, tamaños de fuente personalizados y valores de espaciado arbitrarios, y limitando el inserter de bloques únicamente a los bloques registrados por el tema
5. THE Tema_WordPress SHALL proveer al menos 5 bloques personalizados con opciones preconfiguradas (paleta de colores del brand y tipografías definidas en theme.json) para que el cliente no pueda aplicar estilos fuera del sistema de diseño
6. IF el cliente intenta eliminar una página principal (inicio, tienda, contacto), modificar una plantilla de página o cambiar la configuración de página de inicio, THEN THE Tema_WordPress SHALL mostrar un diálogo de advertencia que describa el impacto de la acción y requiera confirmación explícita del usuario para proceder o cancelar la operación

### Requisito 11: Flujo de Trabajo entre Diseño e Implementación

**Historia de Usuario:** Como equipo de la agencia, quiero un flujo de trabajo claro que conecte el diseño con la implementación WordPress, para asegurar que los sitios finales sean fieles a los mockups aprobados.

#### Criterios de Aceptación

1. THE Proyecto_Agencia SHALL documentar el flujo de trabajo en un archivo markdown ubicado en la raíz del monorepo que describa al menos las siguientes etapas: creación del diseño, aprobación del diseño, transferencia de tokens, implementación del tema y validación de fidelidad, incluyendo para cada etapa los artefactos de entrada, salida y responsable
2. WHEN un diseño es marcado como aprobado en el Módulo_Templates mediante un indicador documentado en el archivo de registro de clientes (campo de estado en `CLIENTS.md` o `clients.json` con valor `desarrollo` o superior), THE Proyecto_Agencia SHALL proveer un script ejecutable que lea los tokens de diseño (secciones colors, typography y spacing) del archivo DESIGN.md del cliente y genere o actualice la sección correspondiente del `theme.json` en el Módulo_Front del mismo cliente
3. THE Proyecto_Agencia SHALL incluir un script o herramienta documentada que genere una captura de pantalla del tema WordPress implementado y la presente junto a la captura `screen.png` del Módulo_Templates, produciendo un reporte que indique las dimensiones comparadas y las diferencias detectadas entre ambas imágenes
4. IF los tokens de diseño definidos en las secciones colors, typography o spacing del DESIGN.md no coinciden con los valores correspondientes en el theme.json del mismo cliente, THEN THE Proyecto_Agencia SHALL producir un reporte de validación que liste cada token discrepante con su valor esperado (DESIGN.md) y su valor actual (theme.json), indicando el resultado global como "PASS" si no hay discrepancias o "FAIL" si existe al menos una
5. IF el script de transferencia de tokens falla al leer el DESIGN.md o al escribir el theme.json, THEN THE Proyecto_Agencia SHALL interrumpir la operación sin modificar archivos parcialmente y mostrar un mensaje de error indicando el archivo problemático y la causa de la falla


### Requisito 12: Integración con Servicio Externo de Automatización de Marketing

**Historia de Usuario:** Como dueño de una tienda online, quiero integrar un servicio externo de automatización de marketing (FluentCRM, Klaviyo u Omnisend) con mi tienda WooCommerce, para ejecutar automatizaciones de recuperación de carritos, cupones y fidelización sin desarrollar un motor de automatización propio.

#### Alcance de la Integración

- **Servicio externo provee:** Motor de automatización, plantillas de correo, lógica de flujos, envío de emails, panel de estadísticas, gestión de contactos
- **El proyecto provee:** Instalación y configuración del plugin de integración, conexión con WooCommerce, configuración de flujos iniciales, documentación del Contrato_Integración y guía de mantenimiento para el cliente

#### Criterios de Aceptación

1. THE Proyecto_Agencia SHALL instalar y configurar un plugin oficial de integración del Motor_Automatización seleccionado (FluentCRM, Klaviyo u Omnisend) que se conecte con WooCommerce sin conflictos con el Tema_WordPress y sin generar errores fatales de PHP
2. THE Proyecto_Agencia SHALL documentar el Contrato_Integración del Motor_Automatización incluyendo: plugin utilizado, versión, credenciales requeridas (API keys, webhooks), eventos de WooCommerce que disparan flujos (carrito abandonado, compra completada, registro de usuario), datos enviados al servicio externo y datos recibidos del servicio externo
3. WHEN se configura la integración, THE Proyecto_Agencia SHALL configurar en el Motor_Automatización los siguientes Flujos_Automáticos iniciales: recuperación de carrito abandonado (activado tras 1 hora configurable, secuencia de 1 a 3 correos), cupón de primera compra (enviado dentro de 5 minutos post-confirmación) y felicitación de cumpleaños (enviado el mismo día o 24 horas antes)
4. THE Proyecto_Agencia SHALL configurar un Flujo_Automático de fidelización en el Motor_Automatización que envíe correos periódicos (frecuencia configurable entre 7 y 90 días) a clientes inactivos, utilizando las capacidades de segmentación y recomendación del servicio externo
5. THE Proyecto_Agencia SHALL documentar el procedimiento de configuración de cada Flujo_Automático para que el cliente o un administrador pueda modificar intervalos, contenido de correos y condiciones de activación directamente en el panel del Motor_Automatización sin intervención del desarrollador
6. IF el Motor_Automatización pierde conexión con WooCommerce o el plugin de integración reporta errores, THEN THE Proyecto_Agencia SHALL configurar notificaciones automáticas al administrador del sitio mediante el mecanismo provisto por el plugin (email, webhook o log en panel de WordPress)
7. THE Proyecto_Agencia SHALL verificar que el panel de estadísticas provisto por el Motor_Automatización (carritos recuperados, tasa de apertura, cupones generados/canjeados) sea accesible desde el administrador de WordPress o desde el dashboard externo del servicio, y documentar la ruta de acceso para el cliente
8. THE Proyecto_Agencia SHALL incluir en la documentación de entrega una guía de troubleshooting que cubra los escenarios comunes de fallo de integración: API key expirada, plugin desactualizado, conflicto con otros plugins, y límites de envío del servicio externo

### Requisito 13: Integración con APIs Externas de Logística y Envíos

**Historia de Usuario:** Como dueño de una tienda online, quiero integrar servicios externos de envío (Olva Courier, Scharff, PedidosYa, Rappi) con mi tienda WooCommerce, para ofrecer cálculo de tarifas en tiempo real y seguimiento de pedidos sin desarrollar un sistema logístico propio.

#### Alcance de la Integración

- **Servicios externos proveen:** Cálculo de tarifas de envío, generación de guías/etiquetas, tracking de paquetes, notificaciones de estado, cobertura geográfica
- **El proyecto provee:** Instalación y configuración de plugins de shipping o desarrollo de adaptadores API ligeros, configuración de zonas de envío en WooCommerce, mapeo de reglas de selección de transportista, documentación del Contrato_Integración con cada API externa y guía de mantenimiento

#### Criterios de Aceptación

1. THE Proyecto_Agencia SHALL integrar WooCommerce con al menos 2 API_Envíos externas (Olva Courier, Scharff, PedidosYa o Rappi) mediante plugins de shipping oficiales o adaptadores API documentados, configurando las credenciales de acceso (API keys, tokens) y verificando la conectividad
2. THE Proyecto_Agencia SHALL documentar el Contrato_Integración de cada API_Envíos conectada, incluyendo: endpoints utilizados (cotización, generación de guía, consulta de estado), métodos HTTP, formato de request/response (JSON schemas), autenticación requerida, códigos de error esperados, rate limits y SLA de respuesta declarado por el proveedor
3. WHEN el cliente ingresa su dirección de envío en el checkout, THE Integración_Logística SHALL invocar las API_Envíos configuradas y mostrar al menos una opción de envío con tarifa calculada por el servicio externo, nombre del transportista y tiempo estimado de entrega, dentro de un plazo máximo de 5 segundos
4. IF una API_Envíos no responde dentro de 5 segundos o devuelve un error, THEN THE Integración_Logística SHALL utilizar una tarifa de envío de respaldo configurable por zona geográfica (definida en WooCommerce) y mostrar un indicador al cliente de que la tarifa es estimada
5. THE Proyecto_Agencia SHALL configurar en WooCommerce las zonas de envío con tarifas diferenciadas por departamento, provincia y distrito, definiendo zonas de cobertura y zonas donde el envío no está disponible, como fallback cuando las APIs externas no estén disponibles
6. WHEN el estado de un pedido cambia en el servicio externo de envío (procesando, enviado, en tránsito, entregado), THE Integración_Logística SHALL recibir la actualización mediante webhook o polling configurado y reflejar el nuevo estado en WooCommerce, enviando notificación automática al cliente por correo electrónico con número de seguimiento cuando esté disponible
7. THE Proyecto_Agencia SHALL configurar la integración con un servicio externo de mensajería (API de WhatsApp Business o Twilio) para envío opcional de notificaciones de estado de envío por WhatsApp, documentando el Contrato_Integración del servicio de mensajería
8. THE Proyecto_Agencia SHALL configurar reglas de selección automática de transportista en WooCommerce basándose en: zona de destino, peso del paquete y preferencia del administrador (costo más bajo o tiempo de entrega más rápido), utilizando los datos provistos por las API_Envíos
9. IF el peso o dimensiones del pedido exceden los límites aceptados por todos los transportistas configurados según sus API_Envíos, THEN THE Integración_Logística SHALL marcar el pedido como "requiere cotización especial", notificar al administrador y mostrar al cliente un mensaje indicando que será contactado para coordinar el envío
10. THE Proyecto_Agencia SHALL incluir en la documentación de entrega: guía de configuración de cada API_Envíos, procedimiento para agregar nuevos transportistas, troubleshooting de errores comunes de conexión (API key expirada, cambio de endpoints, límites de rate) y procedimiento de actualización de plugins de shipping

### Requisito 14: Integración con Pasarelas de Pago Externas y Servicio de Facturación Electrónica

**Historia de Usuario:** Como dueño de una tienda online en Perú, quiero integrar pasarelas de pago externas y un servicio de facturación electrónica con mi tienda WooCommerce, para aceptar múltiples métodos de pago y cumplir con las obligaciones tributarias mediante servicios especializados ya existentes, sin desarrollar lógica de procesamiento de pagos ni motor de facturación propio.

#### Alcance de la Integración

- **Servicios externos de pago proveen:** Procesamiento seguro de transacciones, tokenización de datos sensibles, entorno PCI-DSS compliant, confirmación de pagos, gestión de reembolsos
- **Servicio externo de facturación provee:** Generación de comprobantes electrónicos (XML UBL 2.1), transmisión a SUNAT, recepción de CDR, generación de PDF, correlativo de comprobantes
- **El proyecto provee:** Instalación y configuración de plugins oficiales de pasarelas de pago, instalación y configuración del Plugin_Facturación, mapeo de datos de WooCommerce al servicio de facturación, documentación de cada Contrato_Integración y guía de mantenimiento

#### Criterios de Aceptación

1. THE Proyecto_Agencia SHALL instalar y configurar plugins oficiales de al menos 3 pasarelas de pago externas: una billetera digital local (Yape o Plin), una pasarela regional (Mercado Pago) y una pasarela con soporte de tarjetas de crédito/débito (Culqi o Kushki), documentando el Contrato_Integración de cada una
2. THE Proyecto_Agencia SHALL documentar el Contrato_Integración de cada Plugin_Pasarela incluyendo: plugin utilizado, versión, credenciales requeridas (API keys, merchant IDs, webhooks de confirmación), flujo de pago (redirect vs inline), datos enviados al proveedor, datos recibidos (confirmación, rechazo, pendiente) y configuración de entorno sandbox vs producción
3. WHEN el cliente selecciona un método de pago en el checkout, THE Plugin_Pasarela SHALL procesar la transacción delegando al servicio externo de pago correspondiente, utilizando tokenización y redirección segura provista por el proveedor, sin almacenar datos de tarjeta en el servidor del sitio
4. THE Plugin_Pasarela SHALL recibir la confirmación de estado de la transacción (aprobada, rechazada o pendiente) del servicio externo y actualizar automáticamente el estado del pedido en WooCommerce dentro de los 30 segundos posteriores a la respuesta del proveedor
5. IF una transacción de pago es rechazada o falla por timeout del servicio externo, THEN THE Plugin_Pasarela SHALL mostrar al cliente un mensaje descriptivo del error sin exponer datos técnicos internos, y permitir reintentar el pago o seleccionar un método alternativo sin perder los datos del pedido
6. THE Proyecto_Agencia SHALL instalar y configurar un Plugin_Facturación (Nubefact, Efact, OpenFactura o similar) que se integre con WooCommerce para generar automáticamente comprobantes electrónicos (boleta o factura según tipo de documento del cliente) y transmitirlos a SUNAT mediante la API del servicio externo de facturación
7. THE Proyecto_Agencia SHALL documentar el Contrato_Integración del Plugin_Facturación incluyendo: servicio externo utilizado, credenciales requeridas (token de API, RUC emisor, certificado digital), mapeo de datos de pedido WooCommerce a campos del comprobante, serie y correlativo configurados, y flujo de transmisión a SUNAT (directo o vía OSE)
8. WHEN un pedido es marcado como pagado exitosamente, THE Plugin_Facturación SHALL invocar al servicio externo de facturación para generar el comprobante electrónico y transmitirlo a SUNAT, almacenando la respuesta (CDR) asociada al pedido en WooCommerce
9. WHEN el comprobante electrónico es aceptado por SUNAT (confirmado por el servicio externo), THE Plugin_Facturación SHALL enviar automáticamente al cliente por correo electrónico el comprobante en formato PDF y XML dentro de los 5 minutos posteriores a la confirmación
10. IF la transmisión del comprobante a SUNAT falla o es rechazada (reportado por el servicio externo de facturación), THEN THE Plugin_Facturación SHALL registrar el error con el código de respuesta en el panel de administración, marcar el comprobante como pendiente y reintentar según la política del servicio externo, notificando al administrador si se agotan los reintentos
11. THE Proyecto_Agencia SHALL verificar que el Plugin_Facturación provea un registro correlativo de comprobantes emitidos accesible desde el panel de administración, con filtros por tipo (boleta/factura), rango de fechas, estado de envío a SUNAT y cliente, y documentar el acceso para el usuario final
12. THE Proyecto_Agencia SHALL incluir en la documentación de entrega: guía de configuración de cada pasarela de pago (sandbox y producción), guía de configuración del servicio de facturación, procedimiento de renovación de certificados digitales, troubleshooting de errores comunes (webhook no recibido, timeout de API, rechazo de SUNAT) y procedimiento de actualización de plugins

### Requisito 15: Integración con Sistema POS Externo para Control de Inventario Omnicanal

**Historia de Usuario:** Como dueño de un negocio con tienda física y tienda online, quiero integrar mi sistema POS existente con WooCommerce mediante plugins o APIs, para sincronizar automáticamente el inventario entre ambos canales sin desarrollar un sistema de gestión de stock propio.

#### Alcance de la Integración

- **Sistema POS externo provee:** Gestión de inventario en punto de venta físico, registro de ventas presenciales, control de stock en tienda física, reportes de movimientos
- **El proyecto provee:** Instalación y configuración del plugin de sincronización entre WooCommerce y el Sistema_POS_Externo, configuración de reglas de sincronización (umbrales, frecuencia, comportamiento ante desconexión), documentación del Contrato_Integración y guía de mantenimiento

#### Criterios de Aceptación

1. THE Proyecto_Agencia SHALL integrar WooCommerce con al menos un Sistema_POS_Externo (Vend/Lightspeed, Square o POS local compatible) mediante plugin oficial de sincronización o API documentada, configurando credenciales de acceso y verificando la conectividad bidireccional
2. THE Proyecto_Agencia SHALL documentar el Contrato_Integración del Sistema_POS_Externo incluyendo: plugin o API utilizada, versión, credenciales requeridas (API keys, OAuth tokens), endpoints de sincronización, formato de datos de inventario (SKU, stock, precio), frecuencia de sincronización, dirección del flujo de datos (bidireccional) y comportamiento ante conflictos de stock
3. WHEN se realiza una venta en cualquier canal (tienda online WooCommerce o punto de venta físico), THE Integración_Inventario SHALL recibir la actualización del canal origen y reflejar el cambio de stock en el otro canal dentro de un plazo máximo de 60 segundos, utilizando el mecanismo de sincronización provisto por el plugin o API del Sistema_POS_Externo
4. THE Proyecto_Agencia SHALL configurar en WooCommerce alertas automáticas de stock bajo que envíen notificación al administrador por correo electrónico cuando el stock de un producto alcance un umbral mínimo configurable (por defecto 5 unidades), indicando nombre del producto, stock actual y umbral configurado
5. IF el stock de un producto llega a 0 unidades según la sincronización con el Sistema_POS_Externo, THEN THE Integración_Inventario SHALL marcar automáticamente el producto como "agotado" en WooCommerce y opcionalmente ocultarlo del catálogo visible (configurable), utilizando los hooks estándar de WooCommerce
6. THE Proyecto_Agencia SHALL configurar el registro de historial de movimientos de inventario utilizando las capacidades del plugin de sincronización o un plugin complementario, incluyendo: fecha y hora, tipo de movimiento (venta, devolución, ajuste manual, reposición), canal de origen y stock resultante
7. WHEN se recibe mercadería nueva y se registra en el Sistema_POS_Externo, THE Integración_Inventario SHALL recibir la actualización de stock y reflejarla en WooCommerce, reactivando automáticamente los productos que estaban marcados como agotados si el nuevo stock supera el umbral mínimo
8. IF la conexión entre WooCommerce y el Sistema_POS_Externo se interrumpe por más de 5 minutos, THEN THE Integración_Inventario SHALL registrar la desconexión en un log accesible desde el panel de WordPress, encolar las operaciones de sincronización pendientes según la capacidad del plugin utilizado y notificar al administrador, ejecutando la sincronización completa al restablecerse la conexión
9. THE Proyecto_Agencia SHALL verificar que el plugin de sincronización provea un panel de control accesible desde el administrador de WordPress que muestre: estado de conexión con el Sistema_POS_Externo (conectado/desconectado), última sincronización exitosa, productos con stock bajo y productos agotados, y documentar el acceso para el usuario final
10. THE Proyecto_Agencia SHALL incluir en la documentación de entrega: guía de configuración del plugin de sincronización con el Sistema_POS_Externo, procedimiento para resolver conflictos de stock, troubleshooting de errores de conexión (token expirado, API no disponible, timeout), procedimiento de sincronización manual forzada y guía para agregar nuevos productos al flujo de sincronización

### Requisito 16: Catálogo de Productos de Ropa y Zapatillas

**Historia de Usuario:** Como visitante de la tienda online, quiero navegar un catálogo completo de ropa y zapatillas con filtros avanzados y páginas de producto detalladas, para encontrar fácilmente los productos que busco y ver toda la información necesaria antes de comprar.

#### Criterios de Aceptación

1. THE Catálogo_Ropa_Zapatillas SHALL organizar los productos en categorías jerárquicas que incluyan como mínimo: Ropa, Zapatillas y Accesorios, permitiendo subcategorías dentro de cada una (ejemplo: Ropa > Camisetas, Ropa > Pantalones, Zapatillas > Running, Zapatillas > Casual)
2. THE Catálogo_Ropa_Zapatillas SHALL proveer filtros funcionales que permitan al visitante filtrar productos simultáneamente por los siguientes parámetros: tipo de producto (ropa, zapatillas, accesorios), talla (con opciones específicas por tipo de producto), categoría de género (hombre, mujer, unisex), color, marca y rango de precio (mínimo y máximo)
3. WHEN el visitante aplica uno o más filtros, THE Catálogo_Ropa_Zapatillas SHALL actualizar la lista de productos visibles mostrando únicamente los productos que cumplan todos los filtros seleccionados, indicando el número total de resultados encontrados y permitiendo remover filtros individuales sin recargar la página completa
4. THE Catálogo_Ropa_Zapatillas SHALL mostrar en cada página de producto individual: una galería de imágenes con al menos una imagen principal y soporte para imágenes adicionales navegables (mínimo zoom o lightbox), selector de talla con indicación de disponibilidad por talla, selector de color con muestra visual del color, descripción del producto, precio (incluyendo precio de oferta cuando aplique) y botón de agregar al carrito
5. THE Catálogo_Ropa_Zapatillas SHALL soportar Productos_Variables donde un mismo producto tenga múltiples variaciones definidas por combinación de talla y color, cada variación con su propio stock, precio opcional diferenciado e imagen asociada al color seleccionado
6. WHEN el visitante selecciona un color en la página de un Producto_Variable, THE Catálogo_Ropa_Zapatillas SHALL actualizar la imagen principal del producto para mostrar la imagen correspondiente al color seleccionado y actualizar las tallas disponibles mostrando únicamente las que tengan stock para ese color
7. IF una variación específica (combinación de talla y color) de un Producto_Variable tiene stock 0, THEN THE Catálogo_Ropa_Zapatillas SHALL mostrar esa opción como no disponible (visualmente diferenciada con tachado o gris) e impedir su selección para agregar al carrito
8. THE Catálogo_Ropa_Zapatillas SHALL mostrar en la vista de catálogo (listado de productos) cada producto con: imagen principal, nombre, precio, badge de descuento cuando aplique, y opciones de colores disponibles representadas como círculos de color clicables

### Requisito 17: Flujo Completo de Venta (Carrito, Checkout y Confirmación)

**Historia de Usuario:** Como comprador, quiero un flujo de compra completo y sin fricciones desde agregar productos al carrito hasta recibir la confirmación de mi pedido, para completar mis compras de forma rápida y segura.

#### Criterios de Aceptación

1. WHEN el visitante hace clic en el botón de agregar al carrito desde una página de Producto_Variable, THE Flujo_Venta SHALL agregar el producto al carrito únicamente si el visitante ha seleccionado una talla y un color válidos con stock disponible, mostrando una confirmación visual (mini-carrito, notificación o animación) con el nombre del producto, talla, color y precio unitario
2. IF el visitante intenta agregar al carrito un Producto_Variable sin haber seleccionado talla o color, THEN THE Flujo_Venta SHALL mostrar un mensaje de validación indicando los campos faltantes sin navegar fuera de la página de producto
3. THE Flujo_Venta SHALL mostrar una página de carrito de compras que incluya: lista de productos agregados con imagen miniatura, nombre, talla, color, precio unitario y subtotal por línea; controles para modificar la cantidad de cada item (incrementar, decrementar con mínimo de 1); botón para eliminar items individuales; subtotal general, impuestos aplicables, costo de envío estimado y total a pagar
4. WHEN el visitante modifica la cantidad de un item en el carrito, THE Flujo_Venta SHALL recalcular automáticamente el subtotal de la línea y el total general del carrito sin recargar la página completa, validando que la cantidad solicitada no exceda el stock disponible de esa variación
5. IF el visitante solicita una cantidad mayor al stock disponible de una variación, THEN THE Flujo_Venta SHALL limitar la cantidad al máximo disponible y mostrar un mensaje indicando el stock máximo para esa combinación de talla y color
6. THE Flujo_Venta SHALL proveer una página de checkout que solicite: datos de envío (nombre completo, dirección, ciudad, departamento, código postal, teléfono), selección de método de envío (mostrando opciones disponibles con costo y tiempo estimado según la Integración_Logística configurada) y selección de método de pago (mostrando las pasarelas configuradas según el Requisito 14)
7. WHEN el visitante completa todos los campos requeridos del checkout y confirma el pedido, THE Flujo_Venta SHALL procesar el pago mediante la pasarela seleccionada, crear el pedido en WooCommerce con estado correspondiente al resultado del pago y reducir el stock de cada variación comprada
8. WHEN el pedido es creado exitosamente con pago confirmado, THE Flujo_Venta SHALL mostrar una página de confirmación de pedido con: número de pedido, resumen de productos comprados (nombre, talla, color, cantidad, precio), dirección de envío, método de envío seleccionado, método de pago utilizado y total pagado
9. WHEN el pedido es confirmado exitosamente, THE Flujo_Venta SHALL enviar un correo electrónico de confirmación al comprador dentro de los 5 minutos posteriores, conteniendo: número de pedido, detalle de productos (nombre, talla, color, cantidad, precio unitario), dirección de envío, total pagado y enlace para consultar el estado del pedido
10. IF el pago es rechazado o falla durante el checkout, THEN THE Flujo_Venta SHALL mantener los datos del carrito y del formulario de checkout intactos, mostrar un mensaje descriptivo del error al comprador y permitir reintentar el pago o seleccionar un método de pago alternativo

### Requisito 18: Botón Flotante de WhatsApp para Consultas

**Historia de Usuario:** Como visitante de la tienda, quiero poder contactar rápidamente al negocio por WhatsApp desde cualquier página del sitio, para hacer consultas sobre productos o pedidos de forma directa e inmediata.

#### Criterios de Aceptación

1. THE Botón_WhatsApp SHALL mostrarse como un elemento flotante visible en todas las páginas públicas del sitio, posicionado en la esquina inferior derecha con un z-index suficiente para permanecer sobre el contenido de la página sin obstruir elementos críticos de navegación o el botón de agregar al carrito
2. THE Botón_WhatsApp SHALL utilizar el ícono oficial de WhatsApp con un tamaño mínimo de área de toque de 48x48 píxeles y un contraste visual suficiente respecto al fondo de la página para cumplir con WCAG 2.1 nivel AA (ratio de contraste mínimo 4.5:1)
3. WHEN el visitante hace clic en el Botón_WhatsApp desde una página de producto, THE Botón_WhatsApp SHALL abrir WhatsApp (aplicación nativa o WhatsApp Web según el dispositivo) con un mensaje pre-configurado que incluya: un saludo, el nombre del producto que está viendo y la URL de la página del producto
4. WHEN el visitante hace clic en el Botón_WhatsApp desde cualquier página que no sea una página de producto, THE Botón_WhatsApp SHALL abrir WhatsApp con un mensaje pre-configurado genérico que incluya un saludo y una indicación de que el visitante desea hacer una consulta
5. THE Botón_WhatsApp SHALL ser configurable desde el panel de administración de WordPress permitiendo al administrador modificar: el número de teléfono de destino (formato internacional con código de país), el texto del mensaje pre-configurado para páginas de producto, el texto del mensaje genérico para otras páginas y la posibilidad de ocultar el botón en páginas específicas
6. WHILE el sitio se visualiza en dispositivos con viewport menor a 768px, THE Botón_WhatsApp SHALL mantener su posición flotante sin superponerse al menú de navegación móvil ni a los botones de acción principales (agregar al carrito, proceder al pago), ajustando su posición o tamaño si es necesario para evitar conflictos de interacción
7. IF el número de WhatsApp no está configurado en el panel de administración, THEN THE Botón_WhatsApp SHALL ocultarse automáticamente en el frontend sin generar errores de JavaScript ni elementos HTML vacíos visibles
