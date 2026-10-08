# Sprint 1 — «Los tipos de evento se gestionan y la UI tiene identidad»

> Planning: 2026-10-07 · Capacidad: 27 puntos (velocidad del Sprint 0: 23) · Referencia funcional: dominio `Process` y kit de UI de SGP.

## Objetivo

Al terminar el sprint, un administrador entra a **wp-admin → Eventos → Tipos de evento** y puede crear, editar, reordenar y eliminar tipos con nombre, color, ícono y «requiere adjunto», con validación en tiempo real, toasts y tooltips, sobre el sistema de diseño de Probolsas.

## Historias y tareas

| Orden | Historia | Rama | Tareas | Depende de |
|---|---|---|---|---|
| 1 | **H-102** Migraciones y semilla (B, 3) | `feature/H-101-H-102-tipos-backend` | Migración 1: tabla `eventos_event_types` · Migración 2: semilla de los 4 tipos con el servicio (mismas reglas que el usuario) · `LifecycleTest` vuelve a verificar que la activación crea las tablas (pendiente desde H-002) | — |
| 2 | **H-101** Dominio y API de tipos (B, 8) | (misma rama) | `EventType`, `EventTypeRepository`, `EventTypeService` (unicidad por nombre normalizado, slug fijo, color en mayúsculas, ícono del catálogo, `text_tone` calculado con WCAG, conflicto al eliminar con eventos) · `WpdbEventTypeRepository` · `EventTypeRestController` según [`docs/api/event-types.md`](../api/event-types.md) · reglas publicadas en `epConfig.rules.event_type` · pantalla `EventTypesPage` · el menú de administración usa la primera pantalla como raíz mientras no exista la de eventos | H-102 |
| 3 | **H-103** Sistema de diseño (F, 8) | `feature/H-103-sistema-diseno` | Port del kit de SGP con prefijo `ep-`: `core/dom`, `core/api`, `core/color`, `core/pagination`, `core/search`; `ui/` button, badge (con `text_tone`), toast (Notyf), tooltip (Tippy), confirm-dialog, drawer, form (validación en tiempo real R-24 a partir de `epConfig.rules`), color-field, icon-picker, data-table (Acciones primero, fija, `th` centrados, truncado con tooltip, drag to scroll), pagination responsive 25/50/100 (R-23), load-state, empty-state · estilos de cada componente | Contrato (ya aprobado) |
| 4 | **H-104** Pantalla «Tipos de evento» (F, 5) | `feature/H-104-pantalla-tipos` | Tabla de 5 columnas (Acciones, Tipo, Requiere adjunto, Eventos, Orden) · formulario en drawer · reordenar · eliminar con confirmación · toasts en cada acción · vista previa del badge con el tono legible (`text_tone`) | H-101, H-103 |
| 5 | **H-105** Pruebas de tipos (Q, 3) | revisión de QA de cada rama | Integración de la API (unicidad, 409 al eliminar con eventos, permisos 401/403, reordenar) · componentes · exploratorio en 360/768/1024/1440 px, teclado y lector | H-101 a H-104 |

Backend (1 y 2) y Frontend (3) trabajan en paralelo: su único punto de contacto es el contrato ya aprobado.

## Criterios de aceptación del sprint

1. Al activar el plugin en un WordPress limpio se crean las tablas y los 4 tipos iniciales (RL-05).
2. Un administrador gestiona los tipos; un editor sin `eventos_manage` no ve el menú y la API le responde 403; un visitante recibe 401 (R-22).
3. No se puede crear un tipo con un nombre repetido, aunque cambien las mayúsculas o las tildes («Capacitación» = «CAPACITACION», «Cumpleaños» = «CUMPLEAÑOS»). La ñ es una letra propia, como en el resto de la intranet: «Cumpleanos» sería otro nombre.
4. No se puede eliminar un tipo que tenga eventos (409); el mensaje dice cuántos.
5. Los campos obligatorios se validan mientras se escribe y el servidor confirma con las mismas reglas (R-24).
6. Cada acción muestra un toast (R-20); los botones de solo ícono tienen tooltip y nombre accesible (R-21).
7. La tabla tiene Acciones como primera columna fija, `th` centrados, arrastre para desplazar y paginación 25/50/100 (R-04, R-05, R-06, R-19, R-23).
8. El badge de cada tipo es legible sobre cualquier color: el backend elige texto blanco o negro (`text_tone`) y con ese par todo color alcanza al menos 4,58:1 (R-02). Se descartó el aviso de contraste del formulario porque nunca se activaría.
9. Todo con la paleta de la marca, responsive y con animaciones ligeras que respetan «reducir movimiento» (R-01, R-12, R-13).

## Decisión pendiente del PO (en la Review)

Colores de los tipos iniciales. **Propuesta del SM** (todos con texto blanco legible, contraste ≥ 4,5:1, y distinguibles entre sí; el PO los puede cambiar desde la pantalla):

| Tipo | Ícono | Color propuesto | Contraste con blanco |
|---|---|---|---|
| Cumpleaños | `cake-candles` | `#9D174D` (frambuesa) | 7,9:1 |
| Capacitaciones | `graduation-cap` | `#155728` (verde de la marca) | 8,7:1 |
| Reuniones especiales | `star` | `#B45309` (ámbar oscuro) | 5,0:1 |
| Reuniones laborales | `briefcase` | `#1D4ED8` (azul) | 6,7:1 |

## Riesgos del sprint

| Riesgo | Mitigación |
|---|---|
| H-103 es grande (8 pts) y bloquea H-104 | Portar primero los componentes que usa H-104 (form, drawer, data-table, toast, tooltip, color-field, icon-picker) |
| Equipo local con poca memoria | Verificación pesada solo en el CI; en local, un comando a la vez |
| Bootstrap y los componentes propios se solapan (botones, formularios) | Los componentes del kit usan clases de Bootstrap encapsuladas donde aplica y clases `ep-` propias para lo que Bootstrap no cubre; se documenta en la revisión de H-103 |
