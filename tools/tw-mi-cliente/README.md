# tw-mi-cliente — Build de Tailwind para mi-cliente-theme

Compila un CSS estático **idéntico** al mockup `templates/mi-cliente.local/code.html`
usando el motor real de Tailwind con la **misma configuración** del mockup.

## Por qué

El tema renderiza el markup con las **mismas clases Tailwind** que `code.html`.
Al compilar con la misma config, el navegador aplica exactamente los mismos
estilos que el mockup → fidelidad 1:1, sin reinterpretar utilidades a mano.

## Entradas y salidas

| | Ruta |
|---|---|
| Config (réplica de code.html) | `tailwind.config.js` |
| CSS fuente (reset acotado + keyframes + FSE) | `src.css` |
| Fuentes de clases (content) | `code.html` + `mi-cliente-theme/**/*.{php,html}` |
| **Salida** | `mi-cliente-theme/assets/css/tailwind.css` |

## Uso

```bash
cd dp-proj-00-02-sites/tools/tw-mi-cliente
npm install --no-workspaces      # primera vez (fuera del workspace del monorepo)
npm run build                    # genera tailwind.css minificado
npm run watch                    # recompila al editar plantillas
node verify-classes.mjs          # verifica paridad de clases con code.html
```

> Se instala con `--no-workspaces` para no alterar el `package-lock.json` del
> monorepo (regla de lockfiles por subrepo).

## Reglas de oro para mantener la fidelidad

1. **No** escribir CSS `.dsam-*` a mano para layout: usar clases Tailwind en el markup.
2. Si agregas clases nuevas en las plantillas PHP, vuelve a ejecutar `npm run build`.
3. El reset (preflight) está **desactivado globalmente** y se aplica acotado a
   `.dsam-tw` para no romper WooCommerce ni el editor de bloques.
4. Tras editar `code.html` (nuevo diseño), reconstruir y revisar `verify-classes.mjs`.

## Notas

- `corePlugins.preflight = false`: evita resetear estilos globales del sitio.
- Plugins: `@tailwindcss/forms` y `@tailwindcss/container-queries` (igual que el CDN del mockup).
- El header se reubica a `<body>` vía JS para que `position: sticky` funcione
  pese a los wrappers FSE (ver `assets/js/storefront.js`).
