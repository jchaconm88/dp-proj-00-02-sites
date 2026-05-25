# Guía de Marketing Automation

## Resumen

Configuración de flujos de marketing automatizado para WooCommerce usando plugins de email marketing. Los flujos cubren: abandono de carrito, primera compra, cumpleaños y fidelización.

## Plugins Recomendados

| Plugin | Tipo | Precio | Mejor para |
|---|---|---|---|
| FluentCRM | Self-hosted | Licencia única | Control total, sin costos mensuales |
| Klaviyo | SaaS | Freemium + planes | Ecommerce avanzado, segmentación |
| Omnisend | SaaS | Freemium + planes | Omnicanal (email + SMS + push) |

## Instalación y Configuración

### FluentCRM (Recomendado para inicio)

#### Instalación

1. **WordPress → Plugins → Añadir nuevo** → Buscar "FluentCRM"
2. Instalar y activar **FluentCRM** (versión gratuita)
3. Instalar **FluentCRM Pro** para automatizaciones avanzadas
4. Activar licencia Pro

#### Configuración inicial

```
FluentCRM → Configuración → General
  - Nombre del remitente: [Nombre de la tienda]
  - Email del remitente: hola@tienda.com
  - Reply-to: soporte@tienda.com
  - Dirección física: [Dirección del negocio]
```

#### Integración con WooCommerce

```
FluentCRM → Configuración → Integraciones → WooCommerce
  - Habilitar: Sí
  - Sincronizar clientes: Sí
  - Etiquetar por compra: Sí
  - Segmentar por categoría: Sí
```

### Klaviyo

#### Instalación

1. Crear cuenta en [klaviyo.com](https://www.klaviyo.com)
2. **WordPress → Plugins → Añadir nuevo** → Buscar "Klaviyo"
3. Instalar y activar
4. Conectar con API Key desde panel de Klaviyo

#### Configuración

```
WooCommerce → Configuración → Integración → Klaviyo
  - Public API Key: [tu_public_key]
  - Private API Key: [tu_private_key]
  - Sincronizar catálogo: Sí
  - Tracking activo: Sí
```

### Omnisend

#### Instalación

1. Crear cuenta en [omnisend.com](https://www.omnisend.com)
2. **WordPress → Plugins → Añadir nuevo** → Buscar "Omnisend"
3. Instalar y activar
4. Conectar con la cuenta de Omnisend

#### Configuración

```
Omnisend → Configuración
  - Sincronizar contactos: Sí
  - Sincronizar pedidos: Sí
  - Tracking web: Sí
  - Formularios de captura: Configurar
```

## Flujos Automatizados

### 1. Abandono de Carrito

**Objetivo**: Recuperar ventas de carritos abandonados.

#### Configuración

| Parámetro | Valor |
|---|---|
| Trigger | Carrito abandonado (sin compra) |
| Delay inicial | 1 hora después del abandono |
| Número de emails | 1 a 3 (configurable) |
| Frecuencia | Email 1: 1h, Email 2: 24h, Email 3: 72h |
| Condición de salida | Cliente completa la compra |

#### Email 1 (1 hora después)

- **Asunto**: "¿Olvidaste algo? Tu carrito te espera"
- **Contenido**: Imagen de productos, botón "Completar compra"
- **Tono**: Amigable, recordatorio suave

#### Email 2 (24 horas después)

- **Asunto**: "Tus productos se están agotando"
- **Contenido**: Urgencia moderada, mostrar stock bajo si aplica
- **Incluir**: Enlace directo al checkout

#### Email 3 (72 horas después)

- **Asunto**: "Último recordatorio + descuento especial"
- **Contenido**: Cupón de descuento (5-10%), fecha de expiración
- **Cupón**: Generar automáticamente, uso único

#### Implementación en FluentCRM

```
FluentCRM → Automatizaciones → Nueva
  Trigger: WooCommerce → Carrito abandonado
  Esperar: 1 hora
  Acción: Enviar email (plantilla abandono 1)
  Condición: ¿Compró? → Sí: Terminar / No: Continuar
  Esperar: 23 horas
  Acción: Enviar email (plantilla abandono 2)
  Condición: ¿Compró? → Sí: Terminar / No: Continuar
  Esperar: 48 horas
  Acción: Generar cupón + Enviar email (plantilla abandono 3)
  Terminar
```

### 2. Primera Compra (Bienvenida + Cupón)

**Objetivo**: Fidelizar al nuevo cliente con un incentivo para la segunda compra.

#### Configuración

| Parámetro | Valor |
|---|---|
| Trigger | Primera compra completada |
| Delay | 5 minutos después del pago |
| Emails | 1 |
| Cupón | 10% descuento, válido 30 días |

#### Email

- **Asunto**: "¡Gracias por tu primera compra! Aquí tienes un regalo"
- **Contenido**: Agradecimiento, resumen del pedido, cupón para próxima compra
- **Cupón**: 10% descuento, uso único, expira en 30 días

#### Implementación

```
FluentCRM → Automatizaciones → Nueva
  Trigger: WooCommerce → Pedido completado (primera vez)
  Condición: Número de pedidos del cliente = 1
  Esperar: 5 minutos
  Acción: Generar cupón (10%, 30 días, uso único)
  Acción: Enviar email (plantilla bienvenida + cupón)
  Terminar
```

### 3. Saludo de Cumpleaños

**Objetivo**: Fortalecer relación con el cliente en su cumpleaños.

#### Configuración

| Parámetro | Valor |
|---|---|
| Trigger | Fecha de cumpleaños del contacto |
| Día de envío | Día del cumpleaños, 9:00 AM |
| Cupón | 15% descuento, válido 7 días |

#### Requisitos

- Campo de fecha de nacimiento en registro/checkout
- Contacto debe tener fecha de cumpleaños registrada

#### Email

- **Asunto**: "🎂 ¡Feliz cumpleaños, [Nombre]! Te tenemos un regalo"
- **Contenido**: Felicitación personalizada, cupón especial
- **Cupón**: 15% descuento, válido 7 días, uso único

#### Implementación

```
FluentCRM → Automatizaciones → Nueva
  Trigger: Fecha → Cumpleaños del contacto
  Hora: 9:00 AM
  Acción: Generar cupón (15%, 7 días, uso único)
  Acción: Enviar email (plantilla cumpleaños)
  Terminar
```

### 4. Flujo de Fidelización (Re-engagement)

**Objetivo**: Reactivar clientes que no han comprado en un período configurable.

#### Configuración

| Parámetro | Valor | Rango configurable |
|---|---|---|
| Trigger | Sin compra por X días | 7 - 90 días |
| Default | 30 días sin compra | — |
| Emails | 2-3 | — |
| Escalamiento | Descuento progresivo | — |

#### Segmentos

- **7 días**: Clientes frecuentes (compran semanalmente)
- **30 días**: Clientes regulares (default)
- **60 días**: Clientes ocasionales
- **90 días**: Clientes en riesgo de pérdida

#### Email 1 (Día X)

- **Asunto**: "Te extrañamos, [Nombre]"
- **Contenido**: Productos recomendados basados en historial
- **Sin descuento**: Solo recordatorio

#### Email 2 (Día X + 7)

- **Asunto**: "Tenemos algo especial para ti"
- **Contenido**: Cupón 10%, productos nuevos
- **Cupón**: 10%, válido 14 días

#### Email 3 (Día X + 21)

- **Asunto**: "Última oportunidad: 15% de descuento"
- **Contenido**: Urgencia, cupón mayor
- **Cupón**: 15%, válido 7 días

#### Implementación

```
FluentCRM → Automatizaciones → Nueva
  Trigger: WooCommerce → Sin compra por [X] días
  Acción: Enviar email (plantilla re-engagement 1)
  Esperar: 7 días
  Condición: ¿Compró? → Sí: Terminar / No: Continuar
  Acción: Generar cupón (10%) + Enviar email (plantilla 2)
  Esperar: 14 días
  Condición: ¿Compró? → Sí: Terminar / No: Continuar
  Acción: Generar cupón (15%) + Enviar email (plantilla 3)
  Etiquetar: "cliente_inactivo"
  Terminar
```

## Notificaciones de Error

### Configuración de alertas

```
Plugin de email → Configuración → Notificaciones
  - Email de errores: admin@tienda.com
  - Alertar en: Fallo de envío, Bounce rate > 5%, Spam complaints
  - Frecuencia: Inmediata para críticos, diaria para informativos
```

### Tipos de errores monitoreados

| Error | Severidad | Acción |
|---|---|---|
| Email no enviado | Alta | Notificación inmediata |
| Bounce rate > 5% | Alta | Revisar lista de contactos |
| Spam complaint | Crítica | Pausar flujo, revisar contenido |
| API desconectada | Alta | Verificar credenciales |
| Cupón no generado | Media | Revisar configuración WC |

## Acceso a Estadísticas

### Métricas principales

- **Tasa de apertura**: % de emails abiertos
- **Tasa de clic**: % de clics en enlaces
- **Tasa de conversión**: % que completó compra
- **Revenue atribuido**: Ventas generadas por cada flujo
- **Tasa de abandono recuperado**: % de carritos recuperados

### Dónde ver estadísticas

- **FluentCRM**: FluentCRM → Reportes → Automatizaciones
- **Klaviyo**: Panel → Analytics → Flows
- **Omnisend**: Reports → Automation

### KPIs objetivo

| Flujo | Tasa apertura | Tasa conversión |
|---|---|---|
| Abandono de carrito | > 40% | > 5% |
| Primera compra | > 60% | > 15% |
| Cumpleaños | > 50% | > 10% |
| Fidelización | > 25% | > 3% |

## Troubleshooting

### Emails no se envían

1. Verificar configuración SMTP (usar plugin como WP Mail SMTP)
2. Revisar cola de emails del plugin
3. Verificar que el contacto no está en lista de supresión
4. Confirmar que la automatización está activa
5. Revisar logs del plugin de email

### Cupones no se generan

1. Verificar que WooCommerce permite cupones (Configuración → General)
2. Revisar permisos del plugin para crear cupones
3. Confirmar que la plantilla incluye el shortcode/merge tag correcto
4. Verificar que no hay conflicto con otros plugins de cupones

### Carrito abandonado no se detecta

1. Verificar que el tracking está activo
2. Confirmar que el cliente tiene email registrado
3. Revisar configuración del trigger (tiempo de abandono)
4. Verificar que no hay caché agresivo interfiriendo con el tracking

### Tasa de spam alta

1. Verificar autenticación de email (SPF, DKIM, DMARC)
2. Revisar contenido por palabras spam
3. Incluir enlace de desuscripción visible
4. Reducir frecuencia de envío
5. Limpiar lista de contactos inactivos

### Estadísticas no se actualizan

1. Verificar que el tracking pixel se carga correctamente
2. Confirmar que no hay bloqueadores de tracking
3. Revisar integración con WooCommerce (pedidos sincronizados)
4. Esperar 24-48h para datos consolidados

## Mejores Prácticas

1. **Segmentar**: No enviar el mismo mensaje a todos
2. **Personalizar**: Usar nombre, historial de compras
3. **No saturar**: Máximo 2-3 emails por semana por contacto
4. **Testear**: A/B test en asuntos y contenido
5. **Medir**: Revisar métricas semanalmente
6. **Limpiar**: Eliminar contactos inactivos cada 3 meses
7. **Cumplir**: Respetar preferencias de desuscripción
