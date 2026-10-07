# ADR-0001 · Assets compilados en el repositorio y Bootstrap encapsulado

- **Estado:** aceptada
- **Fecha:** 2026-10-07
- **Historia:** H-004 (Frontend) · resuelve QA-006 y QA-007

## Contexto

1. El plugin se publica en Hostinger, que puede desplegar directamente desde GitHub. El servidor no tiene Node ni debe compilar nada (D-8).
2. SGP versiona `assets/dist` y sus pruebas PHP y JS revisan lo que realmente se publica (íconos, presupuesto de peso, reglas de maquetación).
3. El PO pidió mantener Bootstrap. Sus estilos globales (reboot, `.card`, `.btn`, `.table`, variables en `:root`) chocarían con wp-admin, que también define `.card`, y con el tema de la intranet.
4. El Node local es 20.20. Vitest 5 (el que usa SGP) exige Node 22.12 o superior.

## Decisión

### 1. `assets/dist` se versiona

- `npm run build` genera `assets/dist` (JS sin hash por entrada, CSS compartido con hash, fuente `fa-solid-900.woff2` y el manifest de Vite) y se sube al repositorio junto con el código fuente.
- `assets/src` no forma parte del paquete del plugin (`.gitattributes`: `export-ignore`).
- El CI (H-005) compila y falla si `assets/dist` no coincide con lo subido, para que nunca se publique un build desactualizado.

### 2. Bootstrap 5.3 encapsulado

- Se compila en una hoja propia (`assets/src/scss/vendor/bootstrap.scss`) con las variables tomadas de los tokens (`#155728` como `primary`, sin modo oscuro, sombras ni degradados).
- `postcss-prefix-selector` (configurado en `vite.config.js`) antepone `:is(.ep-app, .ep-public)` a cada selector de esa hoja; `:root`, `html` y `body` pasan a ser el propio contenedor. Bootstrap solo actúa dentro de la interfaz del plugin y gana a las reglas genéricas de wp-admin sin `!important`.
- El plugin `ep-keep-container-box` (también en `vite.config.js`) quita `margin`, `padding` y `background` de las reglas que apuntan solo al contenedor: el `body` del reboot anulaba los márgenes de `.wrap` en wp-admin y el fondo del tema en la intranet (QA-009). El contenedor conserva la tipografía y el color de texto.
- Solo se incluyen los módulos que usa el plugin: reboot, tipografía, grid, tablas, formularios, botones, tarjetas, badges, alertas, botón de cierre, helpers y utilidades.
- Modal, toast y tooltip de Bootstrap **no** se usan: insertan elementos en `<body>`, fuera de los contenedores. Las capas flotantes las aporta el sistema de diseño propio (Notyf y Tippy, como en SGP; H-103).
- El reboot de Bootstrap declara `textarea { resize: vertical }` y `th { text-align: inherit }`. Se corrigen en la misma hoja (R-03, R-05) para no depender del orden de carga.
- `tests/js/build/layout-css.test.js` verifica sobre el CSS compilado que ningún selector de Bootstrap quede fuera de los contenedores y que las variables `--bs-*` se declaren en el contenedor con el color de la marca.

### 3. Herramientas de JS

- Vite 8, Sass, ESLint 10, Stylelint 17, happy-dom y axe-core, como SGP.
- **Vitest 4.1** en lugar de la v5 de SGP, por compatibilidad con Node 20.19+ (mismo API para estas pruebas). Se puede subir a la v5 cuando el equipo actualice a Node 22.12+.
- ESLint traduce R-08 (sin `toISOString`, `new Date('AAAA-MM-DD')`, `Date.parse`, `getTimezoneOffset`), R-14 (`no-alert`) y R-15 (sin `innerHTML` ni `insertAdjacentHTML`) en errores; `tests/js/lint/date-rules.test.js` comprueba que las reglas siguen activas.
- Stylelint traduce R-01 (sin colores fuera de `tokens/`) y R-03 (`resize` solo admite `none`).

## Consecuencias

- **Positivas:** se despliega lo que se probó; la interfaz no depende de la versión de Bootstrap que tenga el tema; las reglas transversales se verifican de forma automática.
- **A vigilar:** la hoja compartida pesa ≈ 39 KB con gzip, de los cuales Font Awesome aporta la mayor parte porque incluye la tabla de todos sus íconos. Presupuesto actual: 45 KB de CSS inicial (`budget.test.js`). Si hace falta, se puede generar un subconjunto con solo los íconos de `config/icons.php` (R-17).
- **Flujo:** quien cambie `assets/src` debe ejecutar `npm run build` y subir `assets/dist` en el mismo commit.
