# Guía de Logística y Envíos

## Configuración de Transportistas

### Requisitos Previos

- WooCommerce 7.0+ activo
- Plugin WooCommerce ERP Integration activo
- Credenciales de API del ERP configuradas
- Al menos una zona de envío configurada en WooCommerce

### Configuración del Método de Envío ERP

1. Ir a **WooCommerce → Configuración → Envío → Zonas de envío**
2. Seleccionar o crear una zona
3. Agregar método de envío → **Envío ERP**
4. Configurar opciones:
   - **Título**: Nombre visible para el cliente
   - **Tarifas de respaldo**: Activar/desactivar
   - **Transportista preferido**: Automático o específico
   - **Mensaje sobredimensionado**: Texto para paquetes grandes

### APIs de Transportistas Soportados

| Transportista | Endpoint ERP | Cobertura |
|---|---|---|
| Olva Courier | `/shipping/olva` | Nacional |
| Shalom | `/shipping/shalom` | Lima + Provincia |
| Cruz del Sur Cargo | `/shipping/cruz` | Nacional terrestre |
| Rappi | `/shipping/rappi` | Lima metropolitana |

### Configuración en el ERP

Las credenciales de cada transportista se configuran directamente en el ERP:

```
ERP Admin → Configuración → Transportistas → [Transportista]
  - API Key
  - API Secret
  - Modo: sandbox / producción
  - Webhook URL: https://tu-sitio.com/wp-json/erp/v1/webhooks/shipping
```

## Agregar un Nuevo Transportista

### Paso 1: Configurar en el ERP

1. Acceder al panel de administración del ERP
2. Ir a **Configuración → Transportistas → Agregar nuevo**
3. Completar datos de la API del transportista
4. Configurar mapeo de zonas y servicios
5. Activar el transportista

### Paso 2: Actualizar opciones en WordPress

Agregar el nuevo transportista al filtro de opciones:

```php
add_filter( 'erp_shipping_carrier_options', function( $options ) {
    $options['nuevo_carrier'] = 'Nombre del Transportista';
    return $options;
} );
```

### Paso 3: Configurar reglas de selección

En **WooCommerce → ERP → Envíos → Reglas de selección**:

- Zona de destino → Transportista asignado
- Peso máximo por transportista
- Prioridad (1 = más alta)

### Paso 4: Probar en sandbox

1. Activar modo sandbox en el ERP para el nuevo transportista
2. Realizar pedidos de prueba
3. Verificar que las tarifas se calculan correctamente
4. Verificar webhooks de seguimiento

## Tarifas de Respaldo

Las tarifas de respaldo se activan cuando:

- El ERP no responde en 8 segundos
- El ERP devuelve un error
- No hay transportistas disponibles para la zona

### Formato de configuración

```
zona:precio:días_estimados
```

### Ejemplo

```
lima:10.00:2-3
provincia:25.00:5-7
selva:45.00:7-10
```

### Zonas predefinidas

- **lima**: Lima Metropolitana y Callao
- **provincia**: Costa y Sierra (excepto Lima)
- **selva**: Loreto, Ucayali, Madre de Dios, San Martín, Amazonas

## Webhooks de Envío

### shipment_created

Se dispara cuando el ERP crea un envío:

```json
{
  "event": "shipment_created",
  "wc_order_id": 1234,
  "tracking_number": "OLV-2024-001234",
  "carrier": "Olva Courier",
  "estimated_delivery": "2024-01-20"
}
```

**Acciones automáticas:**
- Almacena número de tracking en el pedido
- Cambia estado a "Enviado"
- Envía email de notificación al cliente

### shipment_updated

Se dispara cuando hay actualizaciones de seguimiento:

```json
{
  "event": "shipment_updated",
  "wc_order_id": 1234,
  "shipment_status": "in_transit|delivered|returned",
  "details": "Paquete en tránsito - Centro de distribución Lima"
}
```

**Acciones automáticas:**
- Actualiza meta del pedido
- Si `delivered`: marca pedido como completado
- Envía notificación al cliente

## Paquetes Sobredimensionados

Un paquete se marca como sobredimensionado cuando:

- Peso total > 30 kg
- Cualquier dimensión > 150 cm

**Comportamiento:**
- No se calculan tarifas automáticas
- Se muestra mensaje "Requiere cotización especial"
- El equipo comercial contacta al cliente

## Troubleshooting

### El ERP no devuelve tarifas

1. Verificar conectividad: **WooCommerce → ERP → Estado → Health Check**
2. Revisar logs: **WooCommerce → Estado → Logs → erp-shipping**
3. Verificar que la zona de destino está configurada en el ERP
4. Confirmar que el peso y dimensiones del producto están completos

### Las tarifas de respaldo no aparecen

1. Verificar que "Habilitar tarifas de respaldo" está activo
2. Revisar formato de configuración (zona:precio:días)
3. Confirmar que la zona del destino coincide con una configurada

### Webhook no actualiza el pedido

1. Verificar URL del webhook en el ERP
2. Revisar que el `wc_order_id` es correcto
3. Consultar logs en **WooCommerce → Estado → Logs → erp-shipping**
4. Verificar permisos de la API REST

### Tracking no se muestra al cliente

1. Confirmar que el meta `_erp_tracking_number` existe en el pedido
2. Verificar que el email de notificación se envió (revisar cola de emails)
3. Revisar configuración de emails en **WooCommerce → Configuración → Emails**

### Timeout frecuentes del ERP

1. Verificar latencia: **WooCommerce → ERP → Estado**
2. Si latencia > 5s consistentemente, contactar soporte del ERP
3. Considerar aumentar el uso de tarifas de respaldo
4. Revisar si hay problemas de red entre el servidor y el ERP
