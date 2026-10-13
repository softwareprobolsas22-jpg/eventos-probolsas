# Revisión de QA — Ajustes de la aceptación, lote 1 (H-405, H-407, H-408, H-409)

| Campo | Valor |
|---|---|
| Rama | `feature/ajustes-aceptacion-1` (`3de812f` ajustes, `bf69ef6` CI) |
| CI | Run `37990302332` (`3de812f`, 2 intentos) y run `37991075158` (`bf69ef6`, intento 1): ❌ solo integración y navegador, Docker Hub rechazó las imágenes de wp-env (QA-065) · Run `37991075158` intento 2 (2026-10-13): ✅ 11/11 |
| Fecha | 2026-10-13 |
| Revisó | QA |
| Veredicto | ✅ **Aprobado** |

## 1. Verificaciones

| Historia | Verificación | Resultado | Evidencia |
|---|---|---|---|
| H-405 | Las tarjetas Hoy / Próximos 30 días / Total pasan a varias filas según el ancho del área (`minmax(14rem, 1fr)`); en móvil, dos por fila y la última completa | ✅ | Capturas a 1280, 700 y 360 px |
| H-407 | **Causa medida:** sin el arreglo, la casilla oculta del interruptor quedaba en y = 680 px, fuera del panel (0-505) y anclada al `<dialog>`; al recibir el foco con un clic, el navegador desplazaba el panel. Con `position: relative` en la etiqueta queda dentro de ella (y = 368) | ✅ | Medición en Chrome sin interfaz + prueba sobre el CSS compilado (`layout-css.test.js`) |
| H-408 | La tabla de tipos muestra «Descripción» truncada con tooltip (y enfocable si está cortada, QA-027) en lugar de «Orden»; sin descripción, «—»; sigue en 5 columnas (R-19) | ✅ | `event-types.test.js` + captura |
| H-409 | «Fecha desde» y «Fecha hasta» son dos campos de la grilla: tres por fila en escritorio, dos en tablet, uno en móvil; conservan el límite mutuo y el valor `{ from, to }` (las tarjetas y la exportación siguen funcionando) | ✅ | `filter-bar.test.js` + capturas + e2e del resumen |
| — | ESLint, Stylelint, 399 pruebas JS, `dist` igual a un build limpio | ✅ | local + CI |

## 2. Hallazgos

| ID | Sev. | Observación | Acción |
|---|---|---|---|
| QA-065 | Media | El CI depende de descargas anónimas de Docker Hub (imágenes de wp-env) y los runners comparten IP: con el límite agotado, la integración y el navegador no arrancan. El espejo `mirror.gcr.io` (`bf69ef6`) no lo evitó. | ✅ Cerrado el 2026-10-13 (`feature/QA-065-docker-login`): `docker/login-action` con el token de Docker Hub guardado como secreto del repositorio; run `38427773494` en verde con el login ejecutado |

**Veredicto:** ✅ aprobado. Se integra en `develop`.
