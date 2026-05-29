# Proyecto Agencia Web — WordPress

## Descripción

Monorepo para una agencia web dedicada a la venta de sitios web construidos con WordPress + WooCommerce, orientado al mercado peruano. El proyecto organiza múltiples clientes en dos módulos principales: plantillas de diseño (mockups HTML, capturas y documentación visual) y temas WordPress listos para despliegue en producción. La arquitectura monorepo permite compartir configuraciones, scripts de automatización y mantener correspondencia directa entre diseños e implementaciones.

## Modo local (Docker) — probar cambios de tema y plugin

Este repo incluye un `docker-compose.yml` para levantar **WordPress + MySQL** en local y montar directamente desde el filesystem:

- **Tema**: `front/ecommerce/mi-cliente.local/wp-content/themes/mi-cliente-theme`
- **Plugin**: `front/ecommerce/mi-cliente.local/wp-content/plugins/woocommerce-erp-integration`

Así, cualquier cambio que hagas en PHP/CSS/plantillas se refleja inmediatamente al recargar el navegador (sin rebuild).

### Requisitos

- Docker Desktop corriendo.
- Node 18+ (para levantar el backend del ERP del monorepo principal).

### Levantar WordPress

Desde `dp-proj-00-02-sites/`:

```bash
docker compose up -d
```

- WordPress: `http://localhost:8888`
- MySQL: `localhost:3306` (user `wp`, password `wp`, db `wordpress`)

Para detener:

```bash
docker compose down
```

Para borrar datos (reset total):

```bash
docker compose down -v
```

### Levantar el ERP (backend) en local

El plugin consume la API del ERP (`/api/v1`). En este workspace el backend suele correr en **`PORT=3001`** (ver `dp-proj-00-02-backend/.env`).

Desde la raíz del monorepo `dp-proj-00-02/`:

```bash
npm run dev:backend
```

Health check (desde tu PC):

```bash
curl -i http://localhost:3001/api/v1/health
```

### Configurar el plugin “ERP Integration” (en WordPress)

En WP Admin:

- Menú: **ERP Integration**
- Sección: **Conexión API**

Valores típicos en local:

- **URL Base del API**: `http://host.docker.internal:3001/api/v1`
  - Importante: WordPress corre dentro de Docker, por eso se usa `host.docker.internal` (no `localhost`).
- **API Key / API Secret**: generados en **Admin → Web → Credenciales de integración** del proyecto principal.

Luego usa:

- **Diagnóstico → Verificar Conexión (health)**: prueba `GET /health`.
- **Diagnóstico → Probar API Key / Secret**: valida `POST /auth/token` con lo guardado en WP (misma ruta que usa la sync).

### Probar cambios del plugin/tema

- **Plugin**: edita archivos en `.../woocommerce-erp-integration/` y recarga `http://localhost:8888/wp-admin/`.
- **Tema**: edita el tema y recarga el frontend (`http://localhost:8888/`).

Si cambias lógica de rutas/registración (hooks, endpoints REST WP), puede ser necesario:

- Desactivar/activar el plugin en WP Admin, o
- Reiniciar contenedor de WordPress:

```bash
docker compose restart wordpress
```

### Problemas comunes

- **En tu PC `localhost:8080` responde IIS**: no uses 8080 para el backend local si está ocupado; usa el puerto real del backend (p. ej. `3001`).
- **El plugin da `degraded` con pocos ms**: significa “HTTP ≠ 200”, no latencia alta. Revisa URL base (`.../api/v1`) y el puerto correcto.

## Estructura de Carpetas

```
dp-proj-00-04/
├── templates/                        # Módulo de plantillas de diseño
│   └── [hostname]/                   # Carpeta por cliente (ej: mi-cliente.local)
│       ├── design/                   # Archivos de diseño (requerido)
│       │   ├── code.html             # Mockup HTML del diseño
│       │   ├── DESIGN.md             # Tokens de diseño (colores, tipografía, espaciado)
│       │   └── screen.png            # Captura de pantalla del diseño
│       └── components/               # Opcional: plantillas HTML empaquetables
├── front/                            # Módulo de temas WordPress
│   └── [hostname]/                   # Carpeta por cliente (mismo hostname que templates/)
│       └── wp-content/
│           └── themes/
│               └── [theme-name]/     # Tema WordPress FSE
│                   ├── style.css
│                   ├── functions.php
│                   ├── theme.json
│                   ├── templates/
│                   ├── parts/
│                   ├── patterns/
│                   └── assets/
├── scripts/                          # Scripts de automatización
│   ├── validate-hostname.js          # Validación de formato de hostname
│   ├── validate-structure.ps1        # Validación de estructura del monorepo
│   ├── transfer-tokens.js            # Transferencia de tokens diseño → theme.json
│   ├── compare-screenshots.js        # Comparación visual diseño vs implementación
│   └── package-template.ps1          # Empaquetado de templates en ZIP
├── .github/
│   └── workflows/                    # Pipelines CI/CD
│       ├── deploy-staging.yml
│       └── deploy-production.yml
├── .editorconfig                     # Reglas de formato compartidas
├── .gitignore                        # Exclusiones globales
├── CLIENTS.md                        # Registro central de clientes
├── WORKFLOW.md                       # Flujo de trabajo documentado
├── PLUGINS.md                        # Plugins recomendados por tipo de sitio
├── package.json                      # Configuración npm con workspaces
└── README.md                         # Este archivo
```

## Convención de Nombres

Las carpetas de cliente en `templates/` y `front/` se nombran usando el **hostname del dominio** del cliente. El formato debe cumplir:

- Solo caracteres en **minúsculas** (lowercase)
- Solo caracteres **alfanuméricos**, **guiones** (`-`) y **puntos** (`.`)
- Máximo **63 caracteres** por segmento (cada parte separada por puntos)
- Máximo **253 caracteres** en total
- Debe incluir un **TLD** (top-level domain), por ejemplo `.local`, `.com`, `.pe`
- No puede comenzar ni terminar con guión en ningún segmento

**Ejemplos válidos:**

- `mi-cliente.local`
- `tienda-ropa.com.pe`
- `zapatillas-peru.local`

**Ejemplos inválidos:**

- `Mi-Cliente.local` (contiene mayúsculas)
- `-mi-cliente.local` (segmento inicia con guión)
- `mi_cliente.local` (contiene guión bajo)
- `micliente` (sin TLD)

## Flujo de Trabajo

El flujo de trabajo entre módulos sigue estas etapas:

```
Diseño → Aprobación → Transferencia de Tokens → Implementación → Validación
```

1. **Diseño en Templates**: El diseñador crea el mockup HTML (`code.html`), documenta los tokens de diseño en `DESIGN.md` y genera la captura de referencia (`screen.png`) dentro de `templates/[hostname]/design/`.

2. **Aprobación del diseño**: El cliente o el equipo revisa y aprueba el diseño. Si no se aprueba, se itera sobre el diseño.

3. **Transferencia de tokens**: Se ejecuta el script `transfer-tokens.js` que lee los tokens definidos en `DESIGN.md` (colores, tipografía, espaciado, bordes) y los mapea al archivo `theme.json` del tema WordPress correspondiente en `front/`.

4. **Implementación del tema**: El desarrollador construye el tema WordPress en `front/[hostname]/` utilizando los tokens transferidos, creando templates FSE, patterns y estilos que replican fielmente el diseño aprobado.

5. **Validación de fidelidad**: Se ejecuta el script `compare-screenshots.js` para comparar visualmente la captura del diseño original contra la implementación. Si no coincide dentro del umbral aceptable, se itera sobre la implementación.

6. **Despliegue**: Una vez validado, se hace push a `main` y el pipeline CI/CD (GitHub Actions) despliega automáticamente a staging, y tras aprobación manual, a producción.

## Instrucciones para Nuevo Cliente

### 1. Crear carpeta en el módulo de templates

```bash
# Reemplazar [hostname] con el dominio del cliente en formato válido
mkdir -p templates/[hostname]/design
```

Agregar los archivos base requeridos dentro de `design/`:
- `code.html` — Mockup HTML del diseño
- `DESIGN.md` — Documentación de tokens de diseño (colores, tipografía, espaciado)
- `screen.png` — Captura de pantalla del diseño

### 2. (Opcional) Crear carpeta en el módulo front

Si el cliente requiere un tema WordPress:

```bash
mkdir -p front/[hostname]/wp-content/themes/[theme-name]
```

El hostname en `front/` debe ser **idéntico** al usado en `templates/` para mantener la correspondencia 1:1.

### 3. Validar el nombre del cliente

Ejecutar el script de validación para confirmar que el hostname cumple el formato:

```bash
node scripts/validate-hostname.js [hostname]
```

### 4. Registrar el cliente

Agregar la entrada correspondiente en `CLIENTS.md` con los datos del nuevo cliente (nombre, hostname, categoría, estado, rutas, fecha de creación).

### 5. Verificar la estructura

Ejecutar la validación de estructura completa del monorepo:

```powershell
.\scripts\validate-structure.ps1 -MonorepoRoot . -Hostname [hostname]
```
