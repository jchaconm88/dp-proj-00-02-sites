# Flujo de Trabajo: Diseño a Implementación

Este documento describe el flujo de trabajo que conecta el diseño con la implementación WordPress, asegurando que los sitios finales sean fieles a los mockups aprobados.

---

## Etapas del Flujo

### 1. Creación del Diseño

Creación de los artefactos de diseño dentro del directorio `templates/[categoria]/[hostname]/design/`.

| | Detalle |
|---|---|
| **Artefactos de entrada** | Brief del cliente, identidad de marca, referencias visuales |
| **Artefactos de salida** | `code.html` (mockup HTML), `DESIGN.md` (tokens de diseño en YAML frontmatter: colors, typography, spacing, rounded), `screen.png` (captura del diseño) |
| **Responsable** | Diseñador |

**Descripción**: El diseñador crea el mockup HTML estático (`code.html`), documenta los tokens del sistema de diseño en `DESIGN.md` (colores, tipografía, espaciado, bordes redondeados) y genera una captura de pantalla de referencia (`screen.png`). Todos los archivos se ubican en `templates/[categoria]/[hostname]/design/`.

---

### 2. Aprobación del Diseño

Revisión y aprobación formal del diseño por parte del equipo o cliente antes de proceder a la implementación.

| | Detalle |
|---|---|
| **Artefactos de entrada** | `code.html`, `DESIGN.md`, `screen.png` (diseño completo en `templates/`) |
| **Artefactos de salida** | Registro actualizado en `clients.json` con campo `status` cambiado a `"desarrollo"` (o superior) |
| **Responsable** | Director de proyecto / Cliente |

**Descripción**: El diseño es revisado y, una vez aprobado, se actualiza el estado del cliente en el archivo de registro (`clients.json`) al valor `"desarrollo"`. Este cambio de estado es el indicador documentado que habilita la siguiente etapa del flujo. Solo los diseños con status `"desarrollo"` o superior pueden avanzar a la transferencia de tokens.

---

### 3. Transferencia de Tokens

Ejecución del script automatizado que mapea los tokens de diseño definidos en `DESIGN.md` hacia el archivo `theme.json` del tema WordPress.

| | Detalle |
|---|---|
| **Artefactos de entrada** | `DESIGN.md` del cliente (secciones: colors, typography, spacing), `clients.json` con status `"desarrollo"` o superior |
| **Artefactos de salida** | `theme.json` generado o actualizado en `front/[categoria]/[hostname]/wp-content/themes/[theme-name]/theme.json` |
| **Responsable** | Script automatizado (`scripts/transfer-tokens.js`) |

**Descripción**: El script `transfer-tokens.js` lee los tokens de diseño del archivo `DESIGN.md` (colores, tipografía, espaciado) y genera o actualiza la sección correspondiente del `theme.json` en el módulo Front. El mapeo sigue la tabla definida en el documento de diseño:

| DESIGN.md (YAML) | theme.json path |
|---|---|
| `colors.*` | `settings.color.palette[].color` |
| `typography.*.fontFamily` | `settings.typography.fontFamilies[].fontFamily` |
| `typography.*.fontSize` | `settings.typography.fontSizes[].size` |
| `spacing.base` | `settings.spacing.units` |
| `rounded.*` | `styles.blocks.*.border.radius` |

Si el script falla al leer `DESIGN.md` o al escribir `theme.json`, interrumpe la operación sin modificar archivos parcialmente y muestra un mensaje de error indicando el archivo problemático y la causa de la falla.

---

### 4. Implementación del Tema

Desarrollo del tema WordPress FSE en el directorio `front/[categoria]/[hostname]/wp-content/themes/[theme-name]/`.

| | Detalle |
|---|---|
| **Artefactos de entrada** | `theme.json` (con tokens transferidos), `code.html` (referencia visual), `screen.png` (referencia de diseño) |
| **Artefactos de salida** | Tema WordPress completo: `style.css`, `functions.php`, `theme.json`, `templates/`, `parts/`, `patterns/`, `assets/` |
| **Responsable** | Desarrollador |

**Descripción**: El desarrollador implementa el tema WordPress utilizando Full Site Editing (FSE), tomando como base el `theme.json` generado por el script de transferencia y el mockup HTML como referencia visual. El tema debe respetar fielmente los tokens de diseño, implementar los block patterns requeridos y cumplir con los requisitos de rendimiento, accesibilidad y SEO definidos en la especificación.

---

### 5. Validación de Fidelidad

Comparación visual entre el diseño original y la implementación WordPress para verificar que el resultado final es fiel al mockup aprobado.

| | Detalle |
|---|---|
| **Artefactos de entrada** | `screen.png` (captura del diseño original en `templates/`), tema WordPress implementado (en `front/`) |
| **Artefactos de salida** | Reporte de comparación visual (dimensiones comparadas, diferencias detectadas, imagen diff), reporte de validación de tokens (PASS/FAIL por cada token comparado entre `DESIGN.md` y `theme.json`) |
| **Responsable** | Script automatizado (`scripts/compare-screenshots.js`) + Desarrollador (revisión manual) |

**Descripción**: Se ejecuta el script de comparación visual que genera una captura del tema implementado y la compara con `screen.png` del diseño original. El reporte indica las dimensiones comparadas y las diferencias detectadas. Adicionalmente, se valida que los tokens en `theme.json` coincidan con los definidos en `DESIGN.md`, generando un reporte con resultado global PASS o FAIL. Si la validación falla, el flujo regresa a la etapa de implementación para corregir las discrepancias.

---

## Diagrama del Flujo

```
┌─────────────────────┐
│ 1. Creación del     │
│    Diseño           │
│    (Diseñador)      │
└────────┬────────────┘
         │
         ▼
┌─────────────────────┐     No
│ 2. Aprobación del   │────────┐
│    Diseño           │        │
│    (Director/Cliente)│        │
└────────┬────────────┘        │
         │ Sí                  │
         ▼                     ▼
┌─────────────────────┐   Volver a etapa 1
│ 3. Transferencia    │
│    de Tokens        │
│    (Script)         │
└────────┬────────────┘
         │
         ▼
┌─────────────────────┐
│ 4. Implementación   │◄──────┐
│    del Tema         │       │
│    (Desarrollador)  │       │
└────────┬────────────┘       │
         │                    │
         ▼                    │
┌─────────────────────┐  No  │
│ 5. Validación de    │───────┘
│    Fidelidad        │
│    (Script + Dev)   │
└────────┬────────────┘
         │ Sí
         ▼
┌─────────────────────┐
│    ✓ Listo para     │
│      Deploy         │
└─────────────────────┘
```

---

## Resumen de Responsables

| Etapa | Responsable |
|---|---|
| Creación del Diseño | Diseñador |
| Aprobación del Diseño | Director de proyecto / Cliente |
| Transferencia de Tokens | Script automatizado |
| Implementación del Tema | Desarrollador |
| Validación de Fidelidad | Script automatizado + Desarrollador |
