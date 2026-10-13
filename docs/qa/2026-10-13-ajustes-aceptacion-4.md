# Revisión de QA — Ajustes de la aceptación, lote 4 (H-412, H-413)

| Campo | Valor |
|---|---|
| Rama | `feature/ajustes-aceptacion-4` |
| Origen | Observaciones del PO al probar los lotes 2 y 3 en producción (2026-10-13): el resto funciona como se esperaba (formulario por tipo, adjunto con arrastrar y soltar, guía de shortcodes, calendario y listado) |
| CI | Pendiente: la rama no se ha subido |
| Fecha | 2026-10-13 |
| Revisó | QA |
| Veredicto | ⏳ **Aprobado en local, pendiente del CI** |

## 1. Verificaciones

| Historia | Verificación | Resultado | Evidencia |
|---|---|---|---|
| H-412 | La «Descripción» del formulario de evento muestra el ejemplo «Ej.: Nos reunimos en la sala de juntas del segundo piso. Habrá torta y café.»; la ayuda «Opcional.» y el contador no cambian | ✅ | `events.test.js` + captura |
| H-413 | `.ep-settings-screen` ya no tiene `max-width`: las tarjetas «Desinstalación» y «Shortcodes» ocupan todo el ancho del área de contenido, como las demás pantallas; sin desbordamiento a 1280 px | ✅ | `layout-css.test.js` (sobre el CSS compilado) + captura |
| — | ESLint, Stylelint, 423 pruebas JS (incluye las del build), `assets/dist` recompilado | ✅ | local |

**Veredicto:** ⏳ aprobado en local; se integra en `develop` cuando el CI de la rama esté en verde.
