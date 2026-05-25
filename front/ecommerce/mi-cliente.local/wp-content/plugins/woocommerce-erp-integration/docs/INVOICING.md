# Guía de Facturación Electrónica (SUNAT)

## Resumen

La integración de facturación electrónica conecta WooCommerce con el ERP para la emisión de comprobantes electrónicos válidos ante SUNAT. El flujo es:

```
Pago confirmado → ERP genera comprobante → SUNAT valida → PDF/XML al cliente
```

## Tipos de Comprobante

| Tipo | Documento del cliente | Serie | Uso |
|---|---|---|---|
| Boleta | DNI (8 dígitos) | B001-xxx | Persona natural |
| Factura | RUC (11 dígitos) | F001-xxx | Empresa |

## Configuración

### Requisitos Previos

1. Certificado digital SUNAT vigente (cargado en el ERP)
2. Clave SOL del contribuyente
3. Serie de comprobantes asignada por SUNAT
4. ERP configurado como OSE o conexión directa a SUNAT

### Configuración en el ERP

```
ERP Admin → Facturación → Configuración
  - RUC del emisor: [RUC de la empresa]
  - Razón social: [Nombre de la empresa]
  - Dirección fiscal: [Dirección completa]
  - Certificado digital: [Subir .pfx o .p12]
  - Contraseña del certificado: [contraseña]
  - Clave SOL usuario: [usuario]
  - Clave SOL contraseña: [contraseña]
  - Ambiente: Beta (pruebas) / Producción
  - Series:
    - Boleta: B001
    - Factura: F001
    - Nota de crédito boleta: BC01
    - Nota de crédito factura: FC01
```

### Configuración en WordPress

Los campos de documento se capturan en el checkout:

```
WooCommerce → Configuración → ERP → Facturación
  - Habilitar facturación automática: Sí
  - Campo tipo documento: _billing_document_type
  - Campo número documento: _billing_document_number
  - Razón social (factura): _billing_company_name
```

### Webhook URL

Configurar en el ERP:
```
https://tu-sitio.com/wp-json/erp/v1/webhooks/invoice
```

Eventos:
- `invoice_generated`: Comprobante emitido exitosamente
- `invoice_rejected`: SUNAT rechazó el comprobante

## Flujo Detallado

### Emisión Exitosa

1. Pago confirmado en WooCommerce
2. Plugin determina tipo (boleta/factura) según documento
3. `POST /orders/{id}/invoice` al ERP con tipo
4. ERP genera XML según formato UBL 2.1
5. ERP firma con certificado digital
6. ERP envía a SUNAT (o al OSE)
7. SUNAT responde con CDR (Constancia de Recepción)
8. ERP genera PDF
9. Webhook `invoice_generated` → WordPress
10. Plugin descarga PDF/XML y los almacena
11. Email con PDF adjunto al cliente

### Rechazo SUNAT

1. SUNAT rechaza el comprobante (código de error)
2. ERP envía webhook `invoice_rejected`
3. Plugin marca pedido como "comprobante pendiente"
4. Se registra código y mensaje de error
5. Notificación email al administrador
6. Admin puede reintentar desde el registro de comprobantes

## Renovación de Certificado Digital

### Cuándo renovar

- El certificado SUNAT tiene validez de 2 años
- Renovar al menos 30 días antes del vencimiento
- Verificar fecha en: ERP Admin → Facturación → Certificado

### Procedimiento

1. Solicitar nuevo certificado en [SUNAT Operaciones en Línea](https://e-menu.sunat.gob.pe)
2. Descargar el archivo .pfx o .p12
3. En el ERP: Facturación → Certificado → Actualizar
4. Subir nuevo certificado con contraseña
5. Verificar emisión con una boleta de prueba (ambiente beta)
6. Activar en producción

### Verificación post-renovación

```
ERP Admin → Facturación → Diagnóstico
  - Estado del certificado: ✓ Vigente
  - Fecha de vencimiento: [nueva fecha]
  - Última emisión exitosa: [fecha/hora]
```

## Registro de Comprobantes (Admin)

Acceder en: **WooCommerce → Comprobantes**

### Filtros disponibles

- **Estado**: Pendiente, Generado, Rechazado, Error
- **Tipo**: Boleta, Factura
- **Fecha desde/hasta**: Rango de fechas

### Acciones

- **Reintentar**: Re-envía solicitud al ERP para comprobantes con error
- **PDF**: Descarga el comprobante en PDF
- **Ver pedido**: Enlace al detalle del pedido

## Troubleshooting

### Comprobante no se genera

1. Verificar que el pedido tiene `_erp_order_id`
2. Revisar logs: **WooCommerce → Estado → Logs → erp-invoicing**
3. Verificar conectividad con el ERP
4. Confirmar que el certificado está vigente
5. Revisar cola de sincronización

### Error "Certificado expirado"

1. Verificar fecha de vencimiento del certificado
2. Seguir procedimiento de renovación (sección anterior)
3. Si es urgente, contactar soporte SUNAT

### Error "RUC no habido"

1. Verificar el RUC del cliente en [consulta SUNAT](https://e-consultaruc.sunat.gob.pe)
2. Si el RUC está como "No habido", informar al cliente
3. Emitir boleta en su lugar (si el cliente acepta)

### Error "Serie no autorizada"

1. Verificar series configuradas en el ERP
2. Confirmar que la serie está autorizada en SUNAT
3. Solicitar nueva serie si es necesario

### PDF no llega al cliente

1. Verificar email del cliente en el pedido
2. Revisar cola de emails de WordPress
3. Verificar que el PDF se descargó correctamente
4. Revisar configuración SMTP

### Comprobante rechazado - Códigos comunes

| Código | Significado | Solución |
|---|---|---|
| 2017 | Documento del receptor no válido | Verificar DNI/RUC |
| 2800 | Tipo de documento no válido | Verificar tipo boleta/factura |
| 3105 | Monto total no coincide | Verificar cálculos de impuestos |
| 4000 | Contenido del XML no válido | Contactar soporte ERP |

## Notas Importantes

- Los comprobantes electrónicos son obligatorios para empresas en Perú
- SUNAT requiere envío dentro de las 72 horas del pago
- Mantener respaldo de todos los XML y CDR
- El ERP debe generar el resumen diario de boletas
- Las notas de crédito requieren referencia al comprobante original
