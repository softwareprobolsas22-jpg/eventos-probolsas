# Cierre de fase — Sprint 4 «Listo para producción»

| Campo | Valor |
|---|---|
| Fecha | 2026-10-09 |
| Revisó | QA |
| Rama | `develop` |
| Evidencia del CI | Run `37985893742` sobre `develop` (`9da53d4`): 11/11 trabajos en verde (PHP 8.3 y 8.4, JS Node 20 y 22, fechas y calendario en 4 zonas, integración y navegador en WordPress 7.1.3, paquete de release) |
| Veredicto | ✅ **Fase aprobada**. Se abre el PR `develop` → `main`. **Pendiente del PO:** la prueba de aceptación de la v1 ([`aceptacion-v1.md`](aceptacion-v1.md)); el despliegue en Hostinger va después (D-14) |

## 1. Historias

| Historia | Puntos | Informe de QA | Estado |
|---|---|---|---|
| H-206 Dashboard: tarjetas de resumen en «Eventos» (B+F) | 5 | [2026-10-09-H-206](2026-10-09-H-206.md) (QA-060) | ✅ |
| H-401 Caché del feed y de los próximos (B) | 3 | [2026-10-09-H-401](2026-10-09-H-401.md) (la integración detectó QA-061) | ✅ |
| H-402 Desinstalación que conserva los datos y «Ajustes» (B+F) | 3 | [2026-10-09-H-402](2026-10-09-H-402.md) | ✅ |
| H-403 Auditoría de accesibilidad y rendimiento (F) | 3 | [2026-10-09-H-403](2026-10-09-H-403.md) (QA-063, cerró QA-027) | ✅ |
| H-404 Paquete de release y aceptación (SM+Q) | 3 | [2026-10-09-H-404](2026-10-09-H-404.md) | ✅ lo técnico · ⏳ aceptación del PO |
| **Total** | **17** | | **17 / 17** (la aceptación del PO es la Review de la v1) |

## 2. Criterios de aceptación del sprint

| # | Criterio | Evidencia | Estado |
|---|---|---|---|
| 1 | Al entrar a «Eventos» se ven hoy, próximos 30 días, total y por tipo, con la fecha de Colombia; un clic filtra la tabla (D-17) | Unitarias, integración y e2e con el navegador en Asia/Tokyo | ✅ |
| 2 | El feed y los próximos responden desde caché y los cambios se ven de inmediato (R-17) | `ResponseCacheTest`, integración (escribir invalida, color del tipo, eliminar) | ✅ |
| 3 | Desinstalar sin la casilla conserva los datos; con ella los borra; nunca los archivos (D-4, D-16) | `LifecycleTest` en WordPress real con y sin la casilla | ✅ |
| 4 | axe sin problemas graves en wp-admin y en la intranet; textos cortados con tooltip también con teclado (QA-027) | `audit.spec.js` y `calendar.spec.js` en el navegador | ✅ |
| 5 | Presupuestos de peso en verde y cada pantalla pide solo lo necesario (R-17) | `budget.test.js` + e2e de peticiones | ✅ |
| 6 | El `.zip` instala sin herramientas de desarrollo y el PO tiene su lista de aceptación | Trabajo «Paquete de release» del CI + `aceptacion-v1.md` | ✅ (la instalación real la hace el PO en la aceptación) |

## 3. Indicadores al cierre (v1 completa)

| Indicador | Valor |
|---|---|
| Pruebas unitarias PHP | 332 |
| Pruebas de integración en WordPress 7.1.3 | 59 en 10 archivos — CI |
| Pruebas en el navegador | 19 (calendario en Asia/Tokyo, resumen, ajustes, auditoría axe de wp-admin y de la intranet) — CI |
| Pruebas JS | 398 en 38 archivos, cobertura 95,5 % de sentencias; las del calendario corren también en 4 zonas horarias |
| PHPCS, PHPStan, ESLint, Stylelint | 0 errores |
| Carga inicial (gzip) | wp-admin: JS 16,9 KB y CSS 32,4 KB · intranet: JS 18,1 KB y CSS 29,2 KB (máx. 30 y 45) |
| Paquete 2.0.0 | 126 archivos, 416 KB, verificado en cada push |

## 4. Hallazgos de la fase

| ID | Sev. | Estado |
|---|---|---|
| QA-047 Duración del `.ics` | Info | ✅ Cerrado por el PO (D-15: 1 hora) |
| QA-060 Tarjetas del resumen muy altas en móvil | Media | ✅ H-206 |
| **QA-061** El presentador recordaba un tipo editado entre peticiones REST del mismo proceso | **Alta** | ✅ H-401 (lo detectó la integración del CI) |
| QA-062 Prueba del interruptor en el navegador | Baja | ✅ H-402 |
| **QA-063** Texto con 4,41:1 sobre el fondo de wp-admin | **Alta** (axe) | ✅ H-403 |
| QA-027 Tooltip de textos cortados con teclado | Info | ✅ H-403 |
| QA-044 `upload-artifact` con Node 20 | Info | ✅ H-404 (v7) |
| QA-064 `playwright.config.js` en el paquete | Baja | ✅ H-404 |
| QA-035 Archivos en CDN | Info | ⏳ Aceptación del PO (punto 5 de la lista) |

**Sin hallazgos críticos, altos ni medios abiertos.**

## 5. Regresión del legado

Los 8 defectos del plugin anterior (RL-01 a RL-08) siguen con su prueba automática en verde en este CI.

## 6. Siguiente paso: la v1 en producción

1. El PO decide dónde hacer la aceptación (staging o producción con respaldo) y completa [`aceptacion-v1.md`](aceptacion-v1.md) con el `.zip` del CI (artefacto `eventos-probolsas-zip`).
2. Si hay observaciones, se corrigen en una fase corta y se repite lo necesario.
3. Con la aceptación, se despliega la 2.0.0 en Hostinger (D-14) y empieza el roadmap v1.1 (D-7: hora de fin y eventos de varios días, H-501 y H-502).
