# Guía de Integración POS

## Resumen

La integración POS permite sincronización bidireccional de stock entre WooCommerce y el sistema de Punto de Venta (POS) del cliente. Los cambios de stock se reflejan en ambos sistemas en menos de 60 segundos.

## Sistemas POS Soportados

| Sistema | Tipo | API | Notas |
|---|---|---|---|
| Vend (Lightspeed) | Cloud | REST API v2 | Recomendado para retail |
| Square | Cloud | Square API v2 | Incluye pagos |
| POS Local | On-premise | API personalizada | Requiere configuración custom |

## Configuración

### Requisitos Previos

1. WooCommerce con gestión de stock habilitada
2. Productos con SKU asignado (obligatorio para sincronización)
3. Acceso API al sistema POS
4. Servidor con cron de WordPress funcional (o cron externo)

### Configuración en WordPress

```
WooCommerce → Configuración → ERP → POS
  - URL API del POS: https://api.pos-sistema.com/v1
  - API Key: [clave de acceso]
  - Umbral stock bajo: 5 (default)
  - Umbral reactivación: 1
  - Email alertas: admin@tienda.com
  - Sincronización completa: Diaria (cron)
  - Health check: Cada 5 minutos
```

### Configuración del Webhook en el POS

Configurar webhook en el POS para notificar cambios de stock:

```
URL: https://tu-sitio.com/wp-json/erp/v1/webhooks/pos-stock
Método: POST
Eventos: stock_updated, sale_completed
Headers:
  Authorization: Bearer [webhook_secret]
  Content-Type: application/json
```

### Payload del Webhook

```json
{
  "event": "stock_updated",
  "sku": "PROD-001",
  "quantity": 15,
  "channel": "pos_tienda_principal",
  "timestamp": "2024-01-15T10:30:00-05:00"
}
```

## Flujo de Sincronización

### WooCommerce → POS

```
[Venta online / Ajuste manual]
        ↓
[woocommerce_product_set_stock hook]
        ↓
[POSIntegration::on_wc_stock_change()]
        ↓
[Log movimiento en BD]
        ↓
[POST /inventory/update al POS]
        ↓ (< 60 segundos)
[POS actualiza stock]
```

### POS → WooCommerce

```
[Venta en tienda / Ajuste POS]
        ↓
[POS envía webhook]
        ↓
[POSIntegration::handle_pos_stock_update()]
        ↓
[Log movimiento en BD]
        ↓
[Actualizar stock WC (sin re-trigger)]
        ↓
[Verificar visibilidad producto]
        ↓
[Verificar alerta stock bajo]
```

## Gestión de Stock

### Producto sin stock (0 unidades)

- Se marca automáticamente como "Agotado" en WooCommerce
- No se muestra botón "Agregar al carrito"
- Se mantiene visible en catálogo (configurable)

### Reactivación de producto

- Cuando el stock supera el umbral de reactivación (default: 1)
- Se marca como "En stock" automáticamente
- Vuelve a ser comprable

### Alertas de stock bajo

Se envía email cuando el stock llega al umbral configurado (default: 5):

- Solo una alerta cada 24 horas por producto
- Email configurable (default: admin del sitio)
- Incluye: nombre del producto, SKU, stock actual, umbral

## Registro de Movimientos

Cada cambio de stock se registra con:

| Campo | Descripción |
|---|---|
| SKU | Identificador del producto |
| Tipo | wc_update, pos_update, full_sync |
| Canal | woocommerce, pos, pos_tienda_principal |
| Stock anterior | Cantidad antes del cambio |
| Stock nuevo | Cantidad después del cambio |
| Stock resultante | Stock final del producto |
| Fecha | Timestamp del movimiento |

### Consultar movimientos

Los movimientos se almacenan en la tabla `wp_erp_stock_movements` y son consultables desde:

- **WooCommerce → ERP → Movimientos de Stock**
- Filtros: SKU, canal, tipo, rango de fechas

## Manejo de Desconexión

### Detección

- Health check cada 5 minutos al endpoint `/health` del POS
- Si falla: marca conexión como "disconnected"

### Comportamiento durante desconexión

1. Se registra la desconexión con timestamp
2. Se envía email de alerta al admin
3. Los cambios de stock en WC se registran localmente
4. Los pushes al POS se omiten (evitar errores)
5. Las ventas online continúan normalmente

### Reconexión

1. Health check detecta que el POS responde
2. Se marca conexión como "connected"
3. Se dispara sincronización completa automática
4. Se envía email informando la reconexión
5. Todos los stocks se reconcilian

## Troubleshooting

### Stock no se sincroniza

1. Verificar que el producto tiene SKU asignado
2. Revisar logs: **WooCommerce → Estado → Logs → erp-pos**
3. Verificar estado de conexión: **WooCommerce → ERP → POS → Estado**
4. Confirmar que el webhook del POS está configurado correctamente
5. Verificar que el cron de WordPress está funcionando

### Discrepancia de stock entre WC y POS

1. Ir a **WooCommerce → ERP → POS → Sincronización**
2. Ejecutar "Sincronización completa" manualmente
3. Revisar log de movimientos para identificar la discrepancia
4. Verificar que no hay procesos externos modificando stock

### Alertas de stock bajo no llegan

1. Verificar email configurado en opciones POS
2. Revisar que el umbral está configurado correctamente
3. Verificar cola de emails de WordPress
4. Confirmar que no se envió alerta en las últimas 24h (anti-spam)

### POS reporta "conexión perdida" frecuentemente

1. Verificar estabilidad de red del servidor POS
2. Revisar timeout de la API (default: 10s para health check)
3. Verificar que el endpoint `/health` del POS responde rápido
4. Considerar aumentar intervalo de health check

### Error "SKU not found"

1. Verificar que el SKU existe en WooCommerce
2. Confirmar que el SKU es idéntico (case-sensitive)
3. Verificar que no hay espacios extra en el SKU
4. Revisar si el producto fue eliminado o está en papelera

## Tabla de Base de Datos

La tabla `wp_erp_stock_movements` se crea automáticamente al activar el plugin:

```sql
CREATE TABLE wp_erp_stock_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT,
    sku VARCHAR(100) NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    movement_type VARCHAR(50) NOT NULL,
    channel VARCHAR(50) NOT NULL,
    quantity_before INT DEFAULT 0,
    quantity_after INT DEFAULT 0,
    resulting_stock INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sku (sku),
    KEY idx_product_id (product_id),
    KEY idx_created_at (created_at),
    KEY idx_channel (channel)
);
```
