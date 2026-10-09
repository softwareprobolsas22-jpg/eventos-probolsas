# Sprint 4 — «Listo para producción»

> Planning: 2026-10-09 · Velocidad: Sprint 2 = 35, Sprint 3 = 26 · Compromiso: 17 puntos · Contratos: [`docs/api/events.md`](../api/events.md) (`GET /dashboard`) y el nuevo de ajustes (H-402).

## Objetivo

Al terminar el sprint, la v1 está lista para salir: el gestor ve un resumen de los eventos al entrar a **Eventos**, el feed del calendario responde desde caché, desinstalar no borra los datos salvo que el gestor lo pida, la interfaz pasa una auditoría de accesibilidad y rendimiento, y existe un paquete `.zip` de release con su lista de verificación para la prueba de aceptación del PO (H-404).

## Decisiones del PO en el planning (2026-10-09)

| # | Decisión | Consecuencia |
|---|---|---|
| D-15 | Un evento con hora y sin hora de fin dura **1 hora** en el `.ics` (cierra QA-047). | Se mantiene lo construido en H-301 |
| D-16 | Desinstalar **conserva los datos por defecto**: una pantalla «Ajustes» tiene la casilla «Borrar todos los datos al desinstalar», desmarcada. Los archivos de la Biblioteca de Medios nunca se borran (D-4). | H-402 crece de 2 a **3 puntos** (Backend 2 + Frontend 1: la pantalla) |
| D-17 | El **dashboard** son tarjetas arriba de la tabla de **Eventos**; un clic en «Hoy», «Próximos 30 días» o en un tipo filtra la tabla. | Sin pantalla nueva; H-206 reutiliza los filtros de H-203 |
| — | **Staging** para H-404: por definir. | H-404 va al final; si no hay staging, la lista de verificación se usa en producción después de un respaldo |

## Historias y tareas

| Orden | Historia | Rama | Tareas | Depende de |
|---|---|---|---|---|
| 1 | **H-206** Dashboard — Backend (B, 2 de 5) | `feature/H-206-dashboard-backend` | `GET /dashboard` según el contrato: `today` (eventos que ocupan hoy en Colombia), `next_30_days` (de hoy a hoy + 29, por solapamiento), `by_type` (todos los tipos, también los de 0), `total` · `eventos_manage` (anónimo 401, sin permiso 403) · una consulta agrupada, no una por tipo | — |
| 2 | **H-206** Dashboard — Frontend (F, 3 de 5) | `feature/H-206-dashboard` | Tarjetas arriba de la tabla de «Eventos» (D-17): Hoy, Próximos 30 días, Total y desglose por tipo con su badge · clic aplica el filtro de la tabla (rango o tipo) · estados de carga y error sin bloquear la tabla · se actualiza al crear, editar o eliminar · responsive y con teclado | H-206 Backend |
| 3 | **H-401** Caché del feed (B, 3) | `feature/H-401-cache-feed` | Caché del feed por rango y tipos y de los próximos (transients u object cache), invalidada al crear, editar o eliminar eventos y al editar o eliminar tipos (versión de caché) · «hoy» de Colombia en la clave de los próximos · la respuesta HTTP sigue sin caché (`no-store`) · pruebas unitarias e integración (escribir invalida) | — |
| 4 | **H-402** Desinstalación y ajustes (B 2 + F 1) | `feature/H-402-ajustes-backend` · `feature/H-402-ajustes` | Opción `eventos_delete_data_on_uninstall` (falsa por defecto) · `GET/PUT /settings` con `eventos_manage` y contrato en `docs/api/` · `Uninstaller` conserva tablas y tipos si no está marcada, pero siempre quita capacidades y opciones propias que no son datos · pantalla «Ajustes» con un interruptor, texto de ayuda y toast al guardar · integración: desinstalar con y sin la casilla | — |
| 5 | **H-403** Auditoría de accesibilidad y rendimiento (F, 3) | `feature/H-403-auditoria` | axe-core en el navegador sobre «Eventos» (con tarjetas), «Tipos de evento», «Ajustes», drawer, detalle y diálogo de confirmación · **QA-027**: textos truncados con tooltip también con el teclado · revisión de presupuestos de peso y de las peticiones de cada pantalla · ajustes que salgan de la auditoría | H-206, H-402 (pantallas) |
| 6 | **H-404** Aceptación y release (SM+Q, 3) | `feature/H-404-release` | Paquete `.zip` reproducible (`git archive` con `export-ignore`, con `assets/dist` y sin herramientas de desarrollo) y su verificación en el CI · lista de verificación de aceptación para el PO (instalación limpia, permisos, gestión, calendario en varias zonas, `.ics` en Google Calendar y Outlook, desinstalación) · **QA-035** revisado · notas de la versión · staging o producción con respaldo según lo que decida el PO | Todas |

Backend (1, 3 y la parte B de 4) y Frontend (2, la parte F de 4 y 5) trabajan en paralelo contra los contratos. Frontend puede empezar las tarjetas con la API simulada.

## Criterios de aceptación del sprint

1. Al entrar a **Eventos**, el gestor ve cuántos eventos hay hoy, en los próximos 30 días, en total y por tipo, con la fecha de Colombia (R-08); un clic filtra la tabla (D-17).
2. El feed del calendario y los próximos responden desde caché y cualquier cambio en eventos o tipos se ve de inmediato (R-17).
3. Desinstalar sin marcar la casilla conserva eventos y tipos; marcándola, se borran tablas y opciones. Nunca se borran archivos de la Biblioteca de Medios (D-4, D-16).
4. axe-core sin problemas graves en todas las pantallas de wp-admin y en la intranet; los textos truncados muestran el completo también con el teclado (QA-027, R-16, R-21).
5. Los presupuestos de peso siguen en verde y cada pantalla hace solo las peticiones que necesita (R-17).
6. El `.zip` de release instala en WordPress 7.1.3 con PHP 8.3 sin herramientas de desarrollo, y el PO tiene la lista de verificación de aceptación (H-404).

## Riesgos del sprint

| Riesgo | Mitigación |
|---|---|
| Caché que muestra datos viejos | Versión de caché que cambia con cada escritura; integración que escribe y vuelve a leer; la respuesta HTTP sigue con `no-store` |
| «Hoy» del dashboard o de los próximos en caché después de medianoche | La fecha de Colombia forma parte de la clave |
| Desinstalar borra datos por error | Opción falsa por defecto; integración con y sin la casilla; D-4 verificado otra vez |
| Staging sin definir | H-404 al final del sprint; la lista de verificación sirve también para producción con respaldo |
| Tarjetas del dashboard que empujan la tabla en móvil | Tarjetas compactas en grilla responsive; revisión a 360 px |
