# Cierre de fase — Sprint 1 «Los tipos de evento se gestionan y la UI tiene identidad»

| Campo | Valor |
|---|---|
| Fecha | 2026-10-08 |
| Revisó | QA |
| Rama | `develop` |
| Evidencia del CI | Run `37797576812` sobre `develop` (`0f20d07`): 9/9 trabajos en verde (PHP 8.3 y 8.4, JS Node 20 y 22, fechas en 4 zonas, integración en WordPress 7.1.3) |
| Veredicto | ✅ **Fase aprobada**. Se abre el PR `develop` → `main` |

## 1. Historias

| Historia | Puntos | Informe de QA | Estado |
|---|---|---|---|
| H-101 Dominio y API de tipos | 8 | [2026-10-08-H-101-H-102](2026-10-08-H-101-H-102.md) | ✅ |
| H-102 Migraciones y semilla | 3 | ″ | ✅ |
| H-103 Sistema de diseño | 8 | [2026-10-08-H-103](2026-10-08-H-103.md) (aprobada en la segunda revisión) | ✅ |
| H-104 Pantalla «Tipos de evento» | 5 | [2026-10-08-H-104](2026-10-08-H-104.md) | ✅ |
| H-105 Pruebas de tipos | 3 | [2026-10-08-H-105](2026-10-08-H-105.md) (detectó y cerró QA-032) | ✅ |
| **Total** | **27** | | **27 / 27** |

## 2. Criterios de aceptación del sprint

| # | Criterio | Evidencia | Estado |
|---|---|---|---|
| 1 | En un WordPress limpio se crean las tablas y los 4 tipos (RL-05) | `LifecycleTest`, `EventTypeApiTest` (integración) | ✅ |
| 2 | Administrador gestiona; sin `eventos_manage` no ve el menú y recibe 403; visitante 401 (R-22) | `EventTypeAcceptanceTest::test_permissions_on_every_route` (6 rutas × 4 perfiles), `EventTypesPageAndProviderTest` | ✅ |
| 3 | Nombre único sin distinguir mayúsculas ni tildes; la ñ es letra propia | `EventTypeAcceptanceTest` (9 casos). Falló en el CI por el collation (QA-032) y se corrigió antes del cierre | ✅ |
| 4 | No se elimina un tipo con eventos (409) y el mensaje dice cuántos | Integración (singular y plural) + pantalla (aviso sin llamar al servidor y 409 con recarga) | ✅ |
| 5 | Validación mientras se escribe y el servidor confirma con las mismas reglas (R-24) | `epConfig.rules.event_type` + `form`, `pickers` y pruebas de pantalla; los errores 422 se mantienen hasta editar (QA-025) | ✅ |
| 6 | Toast en cada acción; botones de ícono con tooltip y nombre accesible (R-20, R-21) | Pruebas de pantalla; tooltips y toasts visibles dentro de drawers y diálogos (QA-024, verificado en Edge) | ✅ |
| 7 | Acciones primera y fija, `th` centrados, arrastre para desplazar, paginación 25/50/100 (R-04, R-05, R-06, R-19, R-23) | `data-table`, `drag-scroll`, `layout-css` y pruebas de pantalla | ✅ |
| 8 | Badge legible sobre cualquier color con `text_tone` (R-02) | Fixture compartido PHP/JS + integración con 7 colores extremos | ✅ |
| 9 | Paleta de la marca, responsive, animaciones ligeras con «reducir movimiento» (R-01, R-12, R-13) | Stylelint; capturas a 360, 768, 1024, 1280 y 1440 px sin scroll horizontal de página; revisión de `transform`/`opacity` | ✅ |

## 3. Indicadores al cierre

| Indicador | Valor |
|---|---|
| Pruebas unitarias PHP | 197 (1 omitida en local por falta de `intl`; corre en el CI) |
| Pruebas de integración en WordPress 7.1.3 | 33 pruebas en 5 archivos (con los proveedores de datos de H-105, 44 casos) — CI |
| Pruebas JS | 263 (26 archivos) |
| Cobertura PHP `src/Domains` / `src/Shared` | Mínimo del 80 % verificado por el CI; última medición publicada: 99,3 % / 99,2 % |
| Cobertura JS | 95,2 % de sentencias, 90,2 % de ramas (mínimo 80 %) |
| PHPCS, PHPStan, ESLint, Stylelint | 0 errores |
| `assets/dist` corresponde a `assets/src` | ✅ (CI y build local sin cambios) |
| Peso inicial en wp-admin (gzip) | CSS 44,9 KB (máx. 45, ver QA-033) · JS 16,8 KB (máx. 30). La pantalla de tipos se descarga aparte: 10,4 KB |

## 4. Hallazgos de la fase

| ID | Sev. | Estado |
|---|---|---|
| QA-021 / QA-022 / QA-023 (H-101/H-102) | Info | Aceptados (QA-022 se resuelve con H-203) |
| **QA-024** Tooltips y toasts invisibles dentro de drawers y diálogos | **Alta** | ✅ Cerrado en la segunda revisión de H-103 |
| QA-025 El error 422 desaparecía al salir del campo | Media | ✅ Cerrado (H-103) |
| QA-026 `icon-picker.setValue()` aceptaba íconos fuera del catálogo | Baja | ✅ Cerrado (H-103) |
| QA-027 Tooltip de textos truncados solo con mouse | Info | ⏳ H-403 (auditoría de accesibilidad) |
| QA-028 Exclusión de Tippy y Notyf por prefijo | Info | ✅ Cerrado (H-103) |
| QA-029 En móvil el pie de la tabla muestra también el selector y el rango | Info | ✅ Cerrado después de la Review ([ajustes](2026-10-08-ajustes-sprint-1.md)) |
| QA-030 Vista previa del badge con un color incompleto | Info | ✅ Cerrado (H-104) |
| QA-031 Sin `epConfig.rules` la validación local se reduce | Info | Aceptado (el backend siempre publica las reglas) |
| **QA-032** La ñ se trataba como n en la unicidad del nombre (collation) | **Alta** | ✅ Cerrado en H-105 (`93ba95f`) |
| QA-033 El CSS inicial de wp-admin usa 44,9 de 45 KB del presupuesto | Baja | ✅ Cerrado después de la Review: 30,9 KB ([ajustes](2026-10-08-ajustes-sprint-1.md)) |
| QA-004 Mensaje «Fecha no válida» para horas inválidas | Baja | ⏳ H-201 (Sprint 2) |
| QA-005 «Hoy» fijo si la página queda abierta después de medianoche | Baja | ⏳ H-302 (Sprint 3): recalcularlo con `Intl` en la zona de Bogotá |
| QA-019 Detalle técnico escapado en el log | Info | Aceptado |

**Sin hallazgos críticos ni altos abiertos.** Pasan de fase: QA-004, QA-005 y QA-033 (bajos, con historia asignada) y los informativos QA-027 y QA-029.

## 5. Regresión del legado

| ID | Estado al cierre |
|---|---|
| RL-05 | ✅ En una instalación limpia se crean las tablas y los 4 tipos, y se guarda un evento de cada tipo (`LifecycleTest`). La creación desde la API llega con H-201 |
| RL-07 | ✅ Desinstalación (Sprint 0) · eliminar un evento ⏳ H-201 |
| RL-01, RL-02, RL-06 | Base ✅ · calendario ⏳ Sprint 3 |
| RL-03, RL-04, RL-08 | ⏳ H-201, H-202, H-301 y H-302 |

## 6. Para la Review y el Sprint 2

- Velocidad del Sprint 1: 27 puntos (Sprint 0: 23).
- **Decisión pendiente del PO en la Review:** colores de los tipos iniciales (`docs/api/event-types.md`, propuesta del SM en `docs/scrum/sprint-1.md`). Se pueden cambiar desde la pantalla sin otra versión.
- Validar QA-029 (pie de la tabla en móvil).
- El Sprint 2 (40 puntos) supera la velocidad medida; según el plan, H-206 (dashboard) pasa al Sprint 4 si no se ajusta el alcance.
