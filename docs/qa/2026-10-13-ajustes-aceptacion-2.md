# Revisión de QA — Ajustes de la aceptación, lote 2 (H-406, H-410, D-19)

| Campo | Valor |
|---|---|
| Rama | `feature/ajustes-aceptacion-2` (`73fa684` API, `451bd7c` interfaz, `7a9c4da` QA-066) |
| CI | Run `38411360474` (`a196371`, intento 1): ❌ solo «Navegador», por QA-066 (la integración con wp-env pasó: no fue Docker Hub) · Run `38412554388` (`7a9c4da`, intento 1): ✅ 11/11 |
| Fecha | 2026-10-13 |
| Revisó | QA |
| Veredicto | ✅ **Aprobado** |

## 1. Verificaciones

| Historia | Verificación | Resultado | Evidencia |
|---|---|---|---|
| H-406 (B) | `GET /settings/shortcodes` devuelve `ShortcodeRegistry::guides()`: los dos shortcodes, en el orden en que se registran, con sus atributos y `values: "event_types"` en `tipos`. Solo gestores: visitante `401`, sin `eventos_manage` `403`. Contrato en `docs/api/settings.md` | ✅ | `SettingsTest` (unitaria), `SettingsApiTest` (integración, en el CI) |
| H-406 (F) | «Ajustes» tiene una tarjeta «Shortcodes»: título, descripción, ejemplo con botón «Copiar» (nombre accesible «Copiar el shortcode «…»»), atributos con «opcional/obligatorio», el valor por defecto y, en `tipos`, los slugs de los tipos con su nombre (de `GET /event-types`) | ✅ | `settings.test.js` + capturas a 1280 y 360 px sin desbordamiento |
| H-406 (F) | Copiar usa `navigator.clipboard` y, si no existe o falla (HTTP sin TLS, iframe), un `<textarea>` temporal con `execCommand( 'copy' )`; si nada funciona, selecciona el shortcode y pide Ctrl+C | ✅ | `clipboard.test.js`, `settings.test.js`, e2e `settings.spec.js` (lee el portapapeles) |
| H-406 (F) | Cada sección carga por su cuenta: si la guía falla, la desinstalación sigue disponible; si fallan los tipos, `tipos` remite a «Tipos de evento»; sin tipos, «Aún no hay tipos de evento.» | ✅ | `settings.test.js` |
| H-410 | Al crear, el panel muestra solo «Tipo» (con el foco y la ayuda «Al elegirlo aparecen los demás datos del evento.»); al elegir un tipo aparecen Título, Cuándo, Descripción y Adjunto. Volver a «Selecciona un tipo» no oculta lo escrito. Los campos ocultos no se validan: enviar sin tipo solo marca «Tipo». Al editar, todo visible desde el inicio y sin la ayuda | ✅ | `events.test.js` + capturas + e2e `events.spec.js` |
| D-19 | Con un tipo que no exige adjunto, la etiqueta es «Adjunto (opcional)» (también el nombre accesible del grupo); si lo exige, «Adjunto *» | ✅ | `media-field.test.js`, `events.test.js`, e2e |
| — | phpcs, PHPStan (código y pruebas), 333 pruebas PHP unitarias, ESLint, Stylelint, 412 pruebas JS (incluye las del build: chunks sin importar entradas QA-038, íconos `fa-copy` y `fa-code` en el CSS QA-036, presupuesto) | ✅ | local + CI |

## 2. Hallazgos

| ID | Sev. | Observación | Acción |
|---|---|---|---|
| QA-066 | Baja | La auditoría R-17 (`audit.spec.js`) esperaba que «Ajustes» pidiera solo `settings`; con H-406 pide además `settings/shortcodes` y `event-types`, una vez cada una. Además, la prueba cortaba la ruta en el primer segmento y contaba `settings/shortcodes` como `settings`. | ✅ Corregido en la rama: la prueba reconoce las subrutas y espera las tres llamadas |
| QA-065 | Media | Sigue abierto (límite de Docker Hub en el CI) | Si falla la integración o el navegador por Docker Hub, reejecutar los trabajos fallidos |

**Veredicto:** ✅ aprobado. Se integra en `develop`.
