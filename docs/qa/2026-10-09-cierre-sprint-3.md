# Cierre de fase — Sprint 3 «Los colaboradores ven el calendario sin desfases»

| Campo | Valor |
|---|---|
| Fecha | 2026-10-09 |
| Revisó | QA |
| Rama | `develop` |
| Evidencia del CI | Run `37954484848` sobre `develop` (`eaff8f7`): 10/10 trabajos en verde (PHP 8.3 y 8.4, JS Node 20 y 22, fechas y calendario en 4 zonas, integración y navegador en WordPress 7.1.3) |
| Veredicto | ✅ **Fase aprobada**. Se abre el PR `develop` → `main` (sin despliegue: producción al terminar v1, D-14) |

## 1. Historias

| Historia | Puntos | Informe de QA | Estado |
|---|---|---|---|
| H-301 Feed, `.ics`, próximos y shortcodes (B) | 5 | [2026-10-09-H-301](2026-10-09-H-301.md) (corrigió QA-045) | ✅ |
| H-302 `[eventos_calendario]` con FullCalendar 6 (F) | 8 | [2026-10-09-H-302](2026-10-09-H-302.md) (cerró QA-005; QA-049 a QA-053) | ✅ |
| H-303 Modal de detalle (F) | 5 | [2026-10-09-H-303](2026-10-09-H-303.md) | ✅ |
| H-304 `[eventos_proximos]` (F) | 3 | [2026-10-09-H-304](2026-10-09-H-304.md) | ✅ |
| H-305 Pruebas de zonas, responsive y accesibilidad (Q) | 5 | [2026-10-09-H-305](2026-10-09-H-305.md) (axe: QA-056 a QA-058) | ✅ |
| **Total** | **26** | | **26 / 26** |

## 2. Criterios de aceptación del sprint

| # | Criterio | Evidencia | Estado |
|---|---|---|---|
| 1 | Mes y lista, en español, semana según WordPress; en móvil empieza en la lista (R-11, RL-06) | e2e (360 px, `start_of_week` 1 y 0) | ✅ |
| 2 | El evento de las 3:00 p. m. está en su celda y dice «03:00 p. m.» en UTC, Bogotá, Tokio o Kiritimati; el día 7 abre los eventos del 7 (R-07, R-08, RL-01) | Unitarias en 4 zonas en el CI + e2e con el navegador en Asia/Tokyo | ✅ |
| 3 | «Hoy» es el de Bogotá después de las 7:00 p. m. y cambia a medianoche sin recargar (RL-02, QA-005) | `now` con la hora de Bogotá; reloj simulado 23:59 → 00:00:01; e2e | ✅ |
| 4 | Color, ícono y texto legible por tipo (R-02); filtro con Tom Select que vuelve a pedir el rango | Unitarias + e2e | ✅ |
| 5 | Modal con fecha y hora es-CO, descripción, imagen ampliable o PDF, eventos del mismo día y `.ics` a la hora correcta (RL-03, D-6) | Unitarias + e2e con la descarga real del `.ics` | ✅ (la apertura en Google Calendar y Outlook la comprueba el PO al cerrar v1) |
| 6 | `[eventos_proximos]` desde hoy en Colombia, `limite` 1–20 | Unitarias, integración y e2e | ✅ |
| 7 | Visitante sin sesión: aviso y 401; el HTML no lleva datos (D-1, R-22) | Integración + e2e (sin JS del calendario) | ✅ |
| 8 | Dos shortcodes: una petición por rango cada uno, sin IDs repetidos (RL-08) | e2e | ✅ |
| 9 | Assets solo donde hay shortcode; carga inicial en presupuesto; FullCalendar aparte (R-17) | `budget.test.js` + e2e | ✅ |
| 10 | 360–1440 px sin scroll horizontal; teclado; animaciones que respetan la reducción de movimiento (R-12, R-13, R-16) | Capturas, e2e y axe-core en el navegador | ✅ |

## 3. Indicadores al cierre

| Indicador | Valor |
|---|---|
| Pruebas unitarias PHP | 309 (50 nuevas) |
| Pruebas de integración en WordPress 7.1.3 | 53 en 8 archivos (6 nuevas: `CalendarApiTest`) — CI |
| Pruebas en el navegador | 14 (9 nuevas del calendario, con el navegador en Asia/Tokyo y axe-core) — CI |
| Pruebas JS | 379 en 36 archivos, cobertura 95,6 % de sentencias; 85 de ellas corren también en 4 zonas horarias |
| PHPCS, PHPStan, ESLint, Stylelint | 0 errores |
| Carga inicial de la intranet (gzip) | JS 18,1 KB (máx. 30) · CSS 29,2 KB (máx. 45) |
| Bajo demanda | Calendario 76 KB JS (máx. 85) · próximos ~3 KB (máx. 10) |

## 4. Hallazgos de la fase

| ID | Sev. | Estado |
|---|---|---|
| QA-005 «Hoy» después de medianoche | Baja | ✅ Cerrado en H-302 |
| QA-045 JS público encolado para visitantes sin sesión | Media | ✅ Cerrado en H-301 |
| QA-046 Prueba de integración con un tipo que exige adjunto | Baja | ✅ Cerrado en H-301 |
| QA-047 Duración de una hora en el `.ics` sin hora de fin | Info | ⏳ Validar con el PO en la Review |
| QA-048 `UID` del `.ics` sin dominio | Info | Aceptado |
| **QA-049** Evento de las 11:30 p. m. dibujado en dos días | **Alta** | ✅ Cerrado en H-302 |
| QA-050 a QA-053 Ancho a 360 px, reajuste de la grilla, bordes, `.ep-truncate` en la intranet | Media/Baja | ✅ Cerrados en H-302 |
| QA-054, QA-055 Foco inicial y márgenes del modal | Baja | ✅ Cerrados en H-303 |
| **QA-056 a QA-058** axe: «+N más», íconos sin nombre, contraste de días de otros meses | **Crítica/Alta** | ✅ Cerrados en H-305 |
| QA-059 axe durante la animación | Baja | ✅ Cerrado en H-305 |
| QA-027 Tooltip de textos truncados con teclado | Info | ⏳ H-403 |
| QA-035 Archivos en CDN | Info | ⏳ H-404 |
| QA-044 `upload-artifact@v4` con Node 20 | Info | ⏳ Sigue el aviso en el CI; actualizar cuando haya versión para Node 24 |

**Sin hallazgos críticos ni altos abiertos.**

## 5. Regresión del legado

| ID | Estado al cierre |
|---|---|
| RL-01 Clic en el día 7 mostraba el 6 | ✅ e2e en Tokio + unitarias en 4 zonas |
| RL-02 «Hoy» era mañana después de las 7:00 p. m. | ✅ |
| RL-03 `.ics` corrido 5 horas | ✅ unitarias, integración y descarga real |
| RL-04, RL-05, RL-07 | ✅ Siguen en verde (integración y navegador del Sprint 2) |
| RL-06 Grilla desalineada con semana desde el lunes | ✅ |
| RL-08 Calendario inicializado dos veces e IDs duplicados | ✅ |

Los 8 defectos del legado tienen su prueba automática.

## 6. Para la Review y el Sprint 4

- Velocidad: Sprint 2 = 35, Sprint 3 = 26 (compromiso completo).
- Decisión pendiente del PO: QA-047 (duración de una hora en el `.ics`).
- La revisión visual con Chrome sin interfaz y axe local encontró 9 defectos antes de llegar al CI; las pruebas en el navegador sobre WordPress real siguen creciendo con cada historia.
- Sprint 4 «Listo para producción»: H-401 … H-404 y H-206 (16 puntos), con QA-027 en H-403 y QA-035 en H-404.
