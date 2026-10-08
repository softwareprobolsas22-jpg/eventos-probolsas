# Cierre de fase — Sprint 2 «El gestor administra eventos sin errores»

| Campo | Valor |
|---|---|
| Fecha | 2026-10-08 |
| Revisó | QA |
| Rama | `develop` |
| Evidencia del CI | Run `37842056496` sobre `develop` (`157b185`): 10/10 trabajos en verde (PHP 8.3 y 8.4, JS Node 20 y 22, fechas en 4 zonas, integración y navegador en WordPress 7.1.3) |
| Veredicto | ✅ **Fase aprobada**. Se abre el PR `develop` → `main` (sin despliegue: producción al terminar v1, D-14) |

## 1. Historias

| Historia | Puntos | Informe de QA | Estado |
|---|---|---|---|
| H-201 Dominio y API de eventos | 8 | [2026-10-08-H-201](2026-10-08-H-201.md) | ✅ |
| H-202 Gateway de Medios | 3 | [2026-10-08-H-202](2026-10-08-H-202.md) | ✅ |
| H-203 Pantalla «Eventos» | 8 | [2026-10-08-H-203](2026-10-08-H-203.md) (corrigió QA-038, QA-039 y QA-040) | ✅ |
| H-204 Formulario de evento | 8 | [2026-10-08-H-204](2026-10-08-H-204.md) | ✅ |
| H-207 Pruebas del CRUD y en el navegador | 5 | [2026-10-08-H-207](2026-10-08-H-207.md) (detectó QA-043) | ✅ |
| H-205 Detalle del evento (reserva) | 3 | [2026-10-08-H-205](2026-10-08-H-205.md) | ✅ |
| **Total** | **35** | | **35 / 35** (32 comprometidos + 3 de reserva) |

H-206 (dashboard, 5 pts) pasó al Sprint 4 por decisión del PO en el planning.

## 2. Criterios de aceptación del sprint

| # | Criterio | Evidencia | Estado |
|---|---|---|---|
| 1 | Crear, editar y eliminar eventos; eliminar no borra el archivo (D-4, RL-07) | `EventApiTest` (archivo en el disco después de eliminar) + prueba en el navegador del flujo completo | ✅ |
| 2 | 7 de octubre a las 3:00 p. m. se guarda y se muestra como «07/10/2026» y «03:00 p. m.» en cualquier zona; sin hora = «Todo el día» (R-07, R-08) | Integración (sin desfase), pruebas de fechas en 4 zonas, navegador con zona de Bogotá («07/10/2026», «09:30 a. m.») | ✅ |
| 3 | Solo imágenes o PDF de la Biblioteca de Medios por su MIME real; obligatorio si el tipo lo exige (R-09) | `MediaGatewayTest` (`.exe` renombrado), `EventApiTest`, navegador con `wp.media` real (QA-041, QA-043) | ✅ |
| 4 | Fecha pasada permitida con aviso que no bloquea (D-3) | Pruebas de pantalla + `is_past` con la fecha de Colombia | ✅ |
| 5 | Tabla paginada en el servidor 25/50/100, búsqueda sin mayúsculas ni tildes, filtros por tipo y rango por solapamiento, 6 columnas con Acciones primero (R-04, R-05, R-06, R-19, R-23) | Integración (MySQL real) + pruebas de pantalla + capturas | ✅ |
| 6 | CSV con los filtros activos, `;` y BOM UTF-8 | Unitarias, integración y descarga real en el navegador | ✅ |
| 7 | Validación en tiempo real con las reglas del servidor; 422 por campo (R-24) | `epConfig.rules.event` + pruebas de pantalla | ✅ |
| 8 | Toasts en cada acción y tooltips en los botones de ícono (R-20, R-21) | Pruebas de pantalla + navegador (tooltip y toast dentro del panel) | ✅ |
| 9 | Visitante 401; sin `eventos_manage` 403 en las rutas de gestión (R-22) | `EventApiTest::test_permissions` | ✅ |

## 3. Indicadores al cierre

| Indicador | Valor |
|---|---|
| Pruebas unitarias PHP | 259 (1 omitida en local por falta de `intl`; corre en el CI) |
| Pruebas de integración en WordPress 7.1.3 | 47 en 7 archivos (58 casos con los proveedores de datos) — CI |
| Pruebas en el navegador (nuevo, H-207) | 5 sobre WordPress real — CI |
| Pruebas JS | 330 (32 archivos), cobertura 95,7 % de sentencias |
| Cobertura PHP `src/Domains` / `src/Shared` | Mínimo del 80 % verificado por el CI en cada push |
| PHPCS, PHPStan, ESLint, Stylelint | 0 errores |
| Peso inicial en wp-admin (gzip) | CSS 31 KB (máx. 45) · JS 16 KB (máx. 30); cada pantalla se descarga aparte |

## 4. Hallazgos de la fase

| ID | Sev. | Estado |
|---|---|---|
| QA-004 Mensaje de hora inválida | Baja | ✅ Cerrado en H-201 |
| QA-022 Menú raíz «Eventos» | Info | ✅ Cerrado en H-201/H-203 |
| QA-035 Archivos fuera del disco (CDN) | Info | Aceptado; revisar en H-404 si se instala una descarga a CDN |
| QA-036 Ícono en PHP cambia el `dist` | Baja | ✅ Cerrado; regla: Frontend regenera el `dist` en la misma rama |
| QA-037 «Cargando…» hasta H-203 | Info | ✅ Cerrado en H-203 |
| **QA-038** Las pantallas importaban `admin.js` y WordPress lo cargaba dos veces | **Alta** | ✅ Cerrado en H-203 (también afectaba a «Tipos de evento» en `main`, sin desplegar). Verificado en el navegador |
| QA-039 Orden del CSS de Bootstrap | Media | ✅ Cerrado en H-203 |
| QA-040 Página de 916 px a 360 px | Media | ✅ Cerrado en H-203 |
| QA-041 `wp.media` sin probar en WordPress real | Media | ✅ Cerrado por las pruebas en el navegador (H-207) |
| QA-042 Navegadores sin selector de fecha | Info | Aceptado |
| **QA-043** El adjunto elegido no llegaba al formulario | **Alta** | ✅ Cerrado en H-207 |
| QA-044 `upload-artifact@v4` con Node 20 | Info | ⏳ Actualizar cuando haya versión para Node 24 |
| QA-005 «Hoy» después de medianoche | Baja | ⏳ H-302 (Sprint 3) |
| QA-027 Tooltip de textos truncados con teclado | Info | ⏳ H-403 |

**Sin hallazgos críticos ni altos abiertos.** Pasan de fase: QA-005 (baja, H-302) y los informativos QA-027, QA-035 y QA-044.

## 5. Regresión del legado

| ID | Estado al cierre |
|---|---|
| RL-04 «Subir imagen» no asociaba el archivo | ✅ Navegador: se sube en la biblioteca, vuelve al formulario y el evento queda con su imagen |
| RL-05 No se podían crear eventos en una instalación limpia | ✅ Integración: un evento de cada tipo por la API |
| RL-07 Borrar un evento borraba el archivo | ✅ Integración: el adjunto y su archivo siguen después de eliminar |
| RL-01, RL-02, RL-03, RL-06, RL-08 | ⏳ Calendario, `.ics` y modal (Sprint 3) |

## 6. Para la Review y el Sprint 3

- Velocidad: Sprint 1 = 27, Sprint 2 = 35 (32 comprometidos + 3 de reserva).
- Las pruebas en el navegador sobre WordPress real encontraron dos defectos altos (QA-038 y QA-043) que las pruebas unitarias no podían ver: se mantienen en el CI y crecen con el calendario (Sprint 3).
- El PO hará sus comprobaciones manuales al finalizar la versión (decisión del 2026-10-08).
- Sprint 3: H-301 … H-305 (26 puntos), con QA-005 en H-302.
