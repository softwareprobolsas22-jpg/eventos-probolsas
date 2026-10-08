# Sprint 2 — «El gestor administra eventos sin errores»

> Planning: 2026-10-08 · Velocidad: Sprint 0 = 23, Sprint 1 = 27 · Compromiso: 32 puntos + 3 de reserva · Contrato: [`docs/api/events.md`](../api/events.md).

## Objetivo

Al terminar el sprint, un gestor entra a **wp-admin → Eventos** y puede buscar, filtrar, crear, editar, eliminar y exportar eventos, con su tipo, fecha y hora de Colombia sin desfases y un adjunto (imagen o PDF) de la Biblioteca de Medios. La validación es en tiempo real y el servidor confirma con las mismas reglas.

## Alcance acordado con el PO

- **H-206 (dashboard, 5 pts) pasa al Sprint 4** (decisión del PO en la Review del Sprint 1): el Sprint 2 sumaba 40 puntos frente a una velocidad de 27.
- **H-205 (detalle, 3 pts) queda como reserva.** Si al terminar H-204 no hay holgura, pasa al Sprint 3, donde el modal del calendario (H-303) usa la misma ruta `GET /events/{id}`.
- Producción solo al terminar v1 (D-14): el PR de esta fase va a `main` sin desplegarse.

## Historias y tareas

| Orden | Historia | Rama | Tareas | Depende de |
|---|---|---|---|---|
| 1 | **H-201** Dominio y API de eventos (B, 8) | `feature/H-201-eventos-backend` | `Event`, `EventSchedule` preparado para D-7 (fin reservado y rechazado en v1), `CalendarDate`/`CalendarTime` · `EventService` (validación única publicada en `epConfig.rules.event`; `is_past` con la fecha de Colombia) · `EventQuery` con búsqueda sin tildes, filtros por tipo y rango (por **solapamiento**), orden y paginación 25/50/100 con `X-WP-Total` · `WpdbEventRepository` · `EventRestController`: CRUD, `GET /events/{id}` (QA-014) y exportación CSV (`;`, BOM UTF-8, fechas `dd/mm/aaaa`, 12 h) · el menú usa la pantalla de eventos como raíz (QA-022) · **QA-004**: el error de una hora inválida dice «Hora», no «Fecha» | — |
| 2 | **H-202** Gateway de Medios (B, 3) | `feature/H-202-medios` | `Attachment`, `MediaPolicy` (imagen o PDF según el MIME real, R-09), `WpAttachmentGateway` (URL, miniatura, tipo, nombre y tamaño) · nunca borra archivos (D-4) · `epConfig.media` | — |
| 3 | **H-203** Pantalla «Eventos» (F, 8) | `feature/H-203-pantalla-eventos` | Tabla paginada **en servidor** con las 6 columnas de §6.3 (Acciones, Evento truncado con tooltip, Tipo, Fecha, Hora, Adjunto) · búsqueda con *debounce* · filtros por tipo y rango de fechas · exportar con los filtros activos · estado vacío y sin resultados | H-201 (contrato) |
| 4 | **H-204** Formulario de evento (F, 8) | `feature/H-204-formulario-evento` | Drawer con validación en tiempo real (R-24) desde `epConfig.rules.event` · selector de tipo con badge · bloque «Cuándo» (fecha, hora opcional, espacio reservado para D-7) · aviso no bloqueante «Esta fecha ya pasó» (D-3) · `media-field` con `wp.media` (`library.type = ['image', 'application/pdf']`) y vista previa · adjunto obligatorio según el tipo · `textarea` sin resize · errores por campo desde la API | H-201, H-202 |
| 5 | **H-207** Pruebas del CRUD (Q, 5) | revisión de QA de cada rama | Integración: CRUD, permisos 401/403, filtros y paginación, solapamiento de rangos, adjunto no permitido (`.exe` renombrado a `.pdf`), borrar un evento no borra el archivo (RL-07), exportación · regresión RL-04 y RL-05 · exploratorio en 360/768/1024/1440 px, teclado y lector | H-201 a H-204 |
| Reserva | **H-205** Detalle del evento (F, 3) | `feature/H-205-detalle-evento` | Lo que no cabe en la tabla: descripción, vista previa del adjunto, creado y actualizado por quién y cuándo | H-201 |

Backend (1 y 2) y Frontend (3 y 4) trabajan en paralelo contra el contrato aprobado. Frontend empieza por los componentes que no dependen de datos reales (`media-field`, bloque «Cuándo», filtros).

## Criterios de aceptación del sprint

1. Crear, editar y eliminar eventos; eliminar no borra el archivo de la Biblioteca de Medios (D-4, RL-07).
2. Un evento del 7 de octubre a las 3:00 p. m. se guarda y se muestra como «07/10/2026» y «03:00 p. m.» en cualquier zona horaria del equipo (R-07, R-08). Sin hora = «Todo el día».
3. Solo se aceptan imágenes o PDF elegidos en la Biblioteca de Medios, verificados por su MIME real; si el tipo lo exige, el adjunto es obligatorio (R-09).
4. Se permite una fecha pasada, con un aviso que no bloquea (D-3).
5. La tabla pagina en el servidor (25/50/100), con búsqueda sin distinguir mayúsculas ni tildes, filtros por tipo y rango de fechas por solapamiento, y como máximo 6 columnas con Acciones primero (R-04, R-05, R-06, R-19, R-23).
6. Exportar CSV respeta los filtros activos y abre bien en Excel (separador `;`, BOM UTF-8).
7. Validación en tiempo real con las mismas reglas del servidor; los errores 422 se ven por campo (R-24).
8. Toasts en cada acción y tooltips en los botones de solo ícono (R-20, R-21).
9. Visitante 401; usuario sin `eventos_manage` 403 en todas las rutas de gestión (R-22).

## Riesgos del sprint

| Riesgo | Mitigación |
|---|---|
| Compromiso (32) por encima de la velocidad (27) | H-205 como reserva; QA revisa cada historia al terminarla, no al final |
| `wp.media` no existe en las pruebas JS (happy-dom) | `media-field` recibe el selector de medios por inyección; la integración real se verifica en la revisión exploratoria con WordPress del CI |
| Presupuesto de CSS (QA-033 cerrado: 30,9 de 45 KB) | El CI falla si se supera; los estilos nuevos reutilizan los componentes del sistema de diseño |
| Fechas y horas con desfase (defecto principal del legado) | Fixtures compartidos PHP/JS, pruebas en 4 zonas horarias en el CI y regla ESLint R-08 |
