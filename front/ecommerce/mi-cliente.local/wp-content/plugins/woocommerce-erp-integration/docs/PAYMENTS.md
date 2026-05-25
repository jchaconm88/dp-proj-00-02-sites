# Guía de Pasarelas de Pago

## Resumen de Pasarelas Soportadas

| Pasarela | Plugin | Versión Mínima | Tipo | Auto-confirmación |
|---|---|---|---|---|
| Yape / Plin | woocommerce-yape-plin-gateway | 1.2.0 | Billetera móvil | No (manual) |
| Mercado Pago | woocommerce-mercadopago | 6.0.0 | Redirect | Sí |
| Culqi | woocommerce-culqi | 3.0.0 | Directo (tarjeta) | Sí |
| Kushki | woocommerce-kushki | 2.0.0 | Directo (tarjeta) | Sí |

## Yape / Plin

### Plugin

- **Nombre**: WooCommerce Yape Plin Gateway
- **Slug**: `woocommerce-yape-plin-gateway`
- **Versión recomendada**: 1.2.0+

### Credenciales

| Campo | Descripción | Dónde obtener |
|---|---|---|
| Número de celular | Número asociado a Yape/Plin | Cuenta bancaria del negocio |
| Nombre del titular | Nombre que ve el cliente | Configuración de la cuenta |
| QR Code | Imagen QR para pagos | App Yape/Plin del negocio |

### Flujo de Pago

1. Cliente selecciona Yape/Plin en checkout
2. Se muestra QR y número de celular
3. Cliente realiza transferencia desde su app
4. Cliente sube comprobante o ingresa número de operación
5. Admin verifica manualmente el pago
6. Se confirma el pedido → se notifica al ERP

### Configuración

```
WooCommerce → Configuración → Pagos → Yape/Plin
  - Habilitar: Sí
  - Título: "Yape o Plin"
  - Descripción: "Paga con Yape o Plin escaneando el QR"
  - Número: [número del negocio]
  - Titular: [nombre del titular]
  - QR: [subir imagen]
  - Instrucciones: "Realiza la transferencia y sube tu comprobante"
```

### Sandbox vs Producción

Yape/Plin no tiene modo sandbox. Para pruebas:
- Usar montos pequeños (S/ 1.00)
- Verificar manualmente
- Reembolsar después de la prueba

---

## Mercado Pago

### Plugin

- **Nombre**: Mercado Pago payments for WooCommerce
- **Slug**: `woocommerce-mercadopago`
- **Versión recomendada**: 6.0.0+

### Credenciales

| Campo | Descripción | Dónde obtener |
|---|---|---|
| Public Key | Clave pública | [Mercado Pago Developers](https://www.mercadopago.com.pe/developers) |
| Access Token | Token de acceso | Panel de desarrolladores |
| Client ID | ID de aplicación | Credenciales de la app |
| Client Secret | Secreto de aplicación | Credenciales de la app |

### Flujo de Pago

1. Cliente selecciona Mercado Pago en checkout
2. Redirect a página de Mercado Pago
3. Cliente elige método (tarjeta, transferencia, efectivo)
4. Mercado Pago procesa el pago
5. Redirect de vuelta a la tienda
6. Webhook de MP confirma el pago → WooCommerce actualiza → ERP notificado

### Configuración

```
WooCommerce → Configuración → Pagos → Mercado Pago
  - Habilitar: Sí
  - Título: "Mercado Pago"
  - Public Key: [tu_public_key]
  - Access Token: [tu_access_token]
  - Tipo de checkout: Redirect
  - Pagos binarios: Sí (aprobado/rechazado inmediato)
  - Webhook URL: https://tu-sitio.com/?wc-api=WC_MercadoPago_Gateway
  - IPN URL: Configurar en panel de MP
```

### Sandbox vs Producción

**Sandbox:**
- Usar credenciales de prueba del panel de desarrolladores
- Tarjetas de prueba: ver documentación de MP
- Usuarios de prueba: crear en panel de desarrolladores

**Producción:**
- Cambiar a credenciales de producción
- Verificar webhook URL accesible públicamente
- Activar pagos binarios para confirmación inmediata

---

## Culqi

### Plugin

- **Nombre**: WooCommerce Culqi
- **Slug**: `woocommerce-culqi`
- **Versión recomendada**: 3.0.0+

### Credenciales

| Campo | Descripción | Dónde obtener |
|---|---|---|
| Código de comercio | ID del comercio | [Panel Culqi](https://panel.culqi.com) |
| Llave pública | Para tokenización | Panel → Desarrollo → API Keys |
| Llave secreta | Para cargos | Panel → Desarrollo → API Keys |

### Flujo de Pago

1. Cliente ingresa datos de tarjeta en formulario embebido
2. Culqi.js tokeniza la tarjeta (PCI compliant)
3. Token se envía al servidor
4. Servidor crea el cargo con la llave secreta
5. Culqi confirma o rechaza → WooCommerce actualiza → ERP notificado

### Configuración

```
WooCommerce → Configuración → Pagos → Culqi
  - Habilitar: Sí
  - Título: "Tarjeta de crédito/débito"
  - Código de comercio: [tu_codigo]
  - Llave pública: [pk_live_xxx o pk_test_xxx]
  - Llave secreta: [sk_live_xxx o sk_test_xxx]
  - Moneda: PEN
  - Multipago: No
  - Logo personalizado: Opcional
```

### Sandbox vs Producción

**Sandbox:**
- Usar llaves con prefijo `pk_test_` y `sk_test_`
- Tarjeta de prueba: 4111 1111 1111 1111
- CVV: 123, Fecha: cualquier futura

**Producción:**
- Cambiar a llaves con prefijo `pk_live_` y `sk_live_`
- Verificar certificado SSL activo
- Confirmar que el comercio está aprobado por Culqi

---

## Kushki

### Plugin

- **Nombre**: WooCommerce Kushki
- **Slug**: `woocommerce-kushki`
- **Versión recomendada**: 2.0.0+

### Credenciales

| Campo | Descripción | Dónde obtener |
|---|---|---|
| Public Key | Clave pública | [Panel Kushki](https://panel.kushki.com) |
| Private Key | Clave privada | Panel → Configuración → API |
| Merchant ID | ID del comercio | Panel → Mi cuenta |

### Flujo de Pago

1. Cliente ingresa datos de tarjeta en formulario Kushki.js
2. Kushki tokeniza los datos
3. Servidor procesa el cargo con clave privada
4. Kushki confirma → WooCommerce actualiza → ERP notificado

### Configuración

```
WooCommerce → Configuración → Pagos → Kushki
  - Habilitar: Sí
  - Título: "Pago con tarjeta"
  - Public Key: [tu_public_key]
  - Private Key: [tu_private_key]
  - Ambiente: Test / Producción
  - Moneda: PEN / USD
```

### Sandbox vs Producción

**Sandbox:**
- Seleccionar ambiente "Test" en configuración
- Tarjeta de prueba: 4242 4242 4242 4242
- Usar panel de pruebas de Kushki

**Producción:**
- Cambiar ambiente a "Producción"
- Verificar que el comercio está activo en Kushki
- Confirmar webhook URL configurada

---

## Flujo General de Confirmación

```
[Gateway confirma pago]
        ↓
[WooCommerce: payment_complete hook]
        ↓
[PaymentIntegration: on_payment_complete()]
        ↓
[Construir payment_data]
        ↓
[POST al ERP: /orders/{id}/payments]
        ↓ (máximo 30 segundos)
[ERP confirma recepción]
        ↓
[Actualizar meta del pedido]
```

## Manejo de Errores

### Para el cliente

- Mensajes descriptivos sin detalles técnicos
- Opción de reintentar sin perder datos del carrito
- Sugerencia de método alternativo

### Para el administrador

- Logs detallados en WooCommerce → Estado → Logs → erp-payments
- Notificación por email en fallos repetidos
- Dashboard de transacciones fallidas

## Troubleshooting

### Pago confirmado pero ERP no actualiza

1. Verificar logs: `erp-payments`
2. Revisar cola de sincronización: **WooCommerce → ERP → Cola**
3. Verificar que el pedido tiene `_erp_order_id`
4. Forzar re-sincronización manual

### Cliente reporta cobro pero pedido en "Pendiente"

1. Verificar en panel de la pasarela si el pago fue exitoso
2. Revisar webhook/IPN de la pasarela
3. Confirmar manualmente si es necesario
4. Verificar que el webhook URL es accesible

### Error "Fondos insuficientes" frecuente

1. Verificar que el monto es correcto (sin duplicación)
2. Revisar configuración de moneda
3. Confirmar que no hay cargos adicionales inesperados
