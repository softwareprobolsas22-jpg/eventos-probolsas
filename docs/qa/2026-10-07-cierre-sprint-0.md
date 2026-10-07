# Cierre de fase — Sprint 0 «Podemos trabajar con seguridad»

| Campo | Valor |
|---|---|
| Fecha | 2026-10-07 |
| Revisó | QA |
| Rama | `develop` |
| Evidencia del CI | Run `37686966981` sobre `develop` (`cb56792`): todo en verde, incluidas las pruebas de integración en WordPress 7.1.3 |
| Veredicto | ✅ **Fase aprobada**. Se abre el PR `develop` → `main` |

## 1. Historias

| Historia | Puntos | Informe de QA | Estado |
|---|---|---|---|
| H-001 Repositorio, flujo y plantillas | 2 | [2026-10-07-H-005-H-001-H-006-H-007](2026-10-07-H-005-H-001-H-006-H-007.md) | ✅ |
| H-002 Núcleo `Core` + `Shared` | 8 | [2026-10-07-H-002-H-003](2026-10-07-H-002-H-003.md) | ✅ |
| H-003 Herramientas de PHP | 3 | [2026-10-07-H-002-H-003](2026-10-07-H-002-H-003.md) | ✅ |
| H-004 Herramientas de Frontend y Bootstrap encapsulado | 3 | [2026-10-07-H-004](2026-10-07-H-004.md) (aprobada en la segunda revisión) | ✅ |
| H-005 Integración continua | 2 | [2026-10-07-H-005-H-001-H-006-H-007](2026-10-07-H-005-H-001-H-006-H-007.md) | ✅ |
| H-006 Contrato de la API v1 | 2 | ″ | ✅ |
| H-007 Plan de pruebas | 3 | ″ | ✅ |
| **Total** | **23** | | **23 / 23** |

## 2. Indicadores al cierre

| Indicador | Valor |
|---|---|
| Pruebas unitarias PHP | 129 (1 omitida en local por falta de `intl`; corre en el CI) |
| Pruebas de integración en WordPress 7.1.3 | 17 / 17 (CI) |
| Pruebas JS | 94 |
| Cobertura `src/Shared` | 99,2 % (mínimo 80 %) |
| Cobertura JS | 98 % (mínimo 80 %) |
| PHPCS, PHPStan, ESLint, Stylelint | 0 errores, 0 avisos |
| Fechas en Bogotá, UTC, Tokio y Kiritimati | ✅ |
| `assets/dist` reproducible en Windows y Linux | ✅ |
| Peso inicial (gzip) | CSS ≈ 41 KB (máx. 45) · JS < 2 KB (máx. 30) |

## 3. Hallazgos de la fase

| ID | Sev. | Estado |
|---|---|---|
| QA-001 Guía de desarrollo | Media | ✅ Cerrado (README) |
| QA-002 Cobertura no medible | Media | ✅ Cerrado (pcov en el CI) |
| QA-003 Registro de PHPCS | Media | ✅ Cerrado (verificado en el CI) |
| QA-004 Mensaje «Fecha no válida» para horas inválidas | Baja | ⏳ Pasa a H-201 (Sprint 2) |
| QA-005 «Hoy» fijo si la página queda abierta después de medianoche | Baja | ⏳ Pasa a H-302 (Sprint 3) |
| QA-006 / QA-007 Plantillas y `package.json` | Info | ✅ Cerrados (H-004) |
| QA-008 Pruebas de integración pendientes | Info | ✅ Cerrado (17/17 en el CI) |
| QA-009 Bootstrap anulaba los márgenes de `.wrap` | **Alta** | ✅ Cerrado en la segunda revisión de H-004 |
| QA-010 / QA-011 Pruebas de `i18n`/`timing` y widgets desconocidos | Media / Baja | ✅ Cerrados |
| QA-012 / QA-013 Zonas horarias y `wp-env` con Node 24 | Info | ✅ Cerrados (CI) |
| QA-014 / QA-015 Permiso de `GET /events/{id}` y CI en el README | Media | ✅ Cerrados |
| QA-016 / QA-017 / QA-018 Reglas del contrato, WordPress 7.1.3 fijo, umbral de JS | Baja | ✅ Cerrados |
| QA-019 Detalle técnico escapado en el log | Info | Aceptado |
| **QA-020** Docker no es viable en el equipo local | Info | Decisión del PO: integración **solo en el CI** (ver §4) |

**Sin hallazgos críticos ni altos abiertos.** Abiertos que pasan de fase: QA-004 y QA-005 (bajos, con historia asignada).

## 4. QA-020 · Pruebas de integración en el equipo local

Se intentó ejecutar `wp-env` en el equipo de desarrollo como cierre de fase:

1. Node usa `127.0.0.1` como servidor DNS y la conexión es rechazada: `wp-env` cree que no hay red. Se resolvió solo para ese proceso con una corrección temporal, sin tocar la configuración del equipo.
2. La construcción de la imagen de `wp-env` falló dentro de Docker (`apk update`), por el mismo problema de DNS en su máquina virtual.
3. Con Docker activo, el equipo (5,9 GB de RAM) quedó con 0,5 GB libres y respondía con lentitud.

Se detuvieron los contenedores, Docker Desktop y la máquina virtual de WSL (memoria libre: 0,5 GB → 1,6 GB). **Decisión del PO:** las pruebas de integración se ejecutan solo en el CI, en cada push, sobre WordPress 7.1.3. Actualizados la DoD, el plan de pruebas, el README y la plantilla del PR.

## 5. Regresión del legado

| ID | Base disponible al cierre |
|---|---|
| RL-01, RL-02 | ✅ `today` del servidor, fechas de calendario sin `Date`, ESLint R-08 y pruebas en 4 zonas |
| RL-05 | ✅ Migraciones con versión de esquema (las tablas llegan en H-102) |
| RL-06 | ✅ `epConfig.firstDay` |
| RL-07 | ✅ La desinstalación conserva la Biblioteca de Medios (prueba de integración) |
| RL-03, RL-04, RL-08 | ⏳ Dependen de H-201, H-202, H-301 y H-302 |

## 6. Para el Sprint 1

- Velocidad del Sprint 0: 23 puntos.
- El Sprint 1 (27 puntos) arranca con el contrato de `event-types` ya acordado y los colores de los tipos iniciales pendientes de aprobación del PO en su Review.
