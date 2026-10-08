# Eventos Probolsas — Análisis del Scrum Master (Sprint 0)

> **Versión:** 2 · **Fecha:** 2026-10-07 · **Alcance:** reconstrucción del plugin a partir de `legacy/` (v19.8.9), usando como referencia de arquitectura y diseño el plugin **SGP** (`Proyecto SGC Intranet/Sistema-de-Gestion-por-Procesos-SGP`).
>
> Este documento es la **fuente única de verdad (SSOT)** del equipo Scrum: diagnóstico, decisiones del PO, arquitectura, reglas transversales, contrato entre ramas, backlog y plan de sprints. Todo cambio de alcance se registra aquí (§11 Historial).

---

## 1. Resumen ejecutivo

El plugin legado funcionaba de forma parcial y **no era mantenible ni confiable**:

| Indicador | Valor |
|---|---|
| Líneas totales | **17.198** (clases PHP 5.145 · plantillas con CSS/JS embebido 5.193 · CSS 3.340 · JS 3.273 · SVG 247) |
| Bloques `<script>`/`<style>` embebidos en PHP | **10** (en 6 archivos) |
| `!important` en CSS | **173** |
| Colores hex distintos | **105** (ninguno de la paleta corporativa) |
| Lugares donde se definen los tipos de evento | **7** (con valores contradictorios) |
| Reglas de validación de fecha distintas | **5** (contradictorias entre sí) |
| Acciones AJAX registradas dos veces | **3** (gana la que se registra primero) |
| Acciones AJAX llamadas desde JS que **no existen** | **2** (`eventos_export`, `eventos_import`) |
| Pruebas automatizadas | **0** · Control de versiones: **ninguno** |

**Defectos verificados del legado** (sirven como casos de regresión: el plugin nuevo **no** debe reproducirlos):

1. **Desfase de un día al hacer clic en el calendario.** En `America/Bogota`, clic en el día 7 abre los eventos del día 6 (`new Date('YYYY-MM-DD')` se interpreta en UTC) — `legacy/assets/js/frontend.js:572-576`.
2. **«Hoy» es mañana después de las 7:00 p. m.** Los filtros rápidos usan `toISOString()` (UTC) — `frontend.js:519-559`, `frontend-calendar.php:1045-1073`, `admin-list.php:503-505`, `admin.js:115`.
3. **El .ics queda corrido 5 horas** (hora local marcada con `Z`) — `class-eventos-probolsas-frontend.php:1079-1080`; y «Añadir a calendario» **siempre falla** por nonce incorrecto — `frontend.js:1256-1258`.
4. **«Subir nueva imagen» no asocia la imagen al evento** (dos handlers para la misma acción; responde el que no devuelve `attachment_id`) — `class-eventos-probolsas-admin.php:22` vs `class-eventos-probolsas-ajax.php:41`.
5. **En instalación limpia no se podían crear eventos**: se escribía `image_attachment_id`, columna inexistente en el `CREATE TABLE`, sin migraciones — `class-eventos-probolsas-activator.php:48-63` vs `class-eventos-probolsas-db.php:302`.
6. **Grilla desalineada** si la semana inicia en lunes (encabezado fijo «Dom…Sáb») — `frontend-calendar.php:218-226`, `frontend.js:442-445`.
7. **Borrar un evento podía borrar un archivo de la Biblioteca de Medios usado en otra parte de la intranet** — `class-eventos-probolsas-db.php:447-459`.
8. **Cada calendario se inicializaba dos veces** y generaba dos `div` con el mismo id — `class-eventos-probolsas-frontend.php:260,269-301` y `frontend-calendar.php:20,885-916`.

**Conclusión del SM:** reconstrucción desde cero. El legado **ya fue retirado de producción** (D-9), así que se arranca **en blanco**: sin migración de datos ni compatibilidad con tablas o shortcodes antiguos. `legacy/` queda de solo lectura como referencia funcional y como fuente de casos de regresión.

---

## 2. Inventario funcional del legado y destino

| Funcionalidad legada | Destino en el plugin nuevo |
|---|---|
| CRUD de eventos (título, tipo, fecha, hora opcional, descripción, imagen) | **Se conserva**, una sola ruta (REST); adjunto = imagen **o PDF** |
| 4 tipos fijos en código | **Pasan a ser gestionables** (CRUD con nombre, color, ícono, adjunto obligatorio), sembrando los 4 actuales (D-2) |
| Imagen obligatoria según tipo | **Se conserva** como propiedad del tipo («requiere adjunto») |
| Calendario mensual público | **Reescrito con FullCalendar**, solo para usuarios con sesión (D-1) |
| `[eventos_lista]` / `[eventos_proximos]` | **Se conserva** `[eventos_proximos]` (lista de próximos) — ver D-6 |
| `[eventos_widget]`, `[eventos_probolsas]` | **Se eliminan** |
| Modal de detalle + navegación entre eventos del mismo día | **Se conserva** (un componente) |
| Descarga .ics | **Se conserva y corrige** (`TZID=America/Bogota`) |
| Exportar CSV | **Se conserva** (separador `;` como SGP, compatible con Excel es-CO) |
| Importar CSV | **Se elimina por completo** (D-6) |
| Autoguardado, atajos de teclado, eventos relacionados, `[eventos_widget]` | **Se eliminan por completo**, junto con su código, textos y estilos (D-6) |
| Estadísticas | **Se conservan** en el dashboard admin |
| SVG `assets/img/icon-*.svg` (sin uso) | **Se eliminan** → Font Awesome |

---

## 3. Diagnóstico del legado por principio (lecciones aprendidas)

### 3.1 SOLID
- **SRP:** `Eventos_Probolsas_Frontend` (1.105 líneas) mezclaba shortcodes, AJAX, meta OG, iCal y estilos inline. `DB` mezclaba persistencia, formateo y enriquecimiento de adjuntos.
- **OCP/DIP:** cada clase hacía `new Eventos_Probolsas_DB()` y llamaba estáticos de `Helpers`; imposible de probar.
- **ISP:** `Helpers` era una «clase dios» (colores, fechas, uploads, breadcrumbs, logging, SQL).

### 3.2 DRY
- Sanitización/validación duplicada en 3 clases PHP y 2 archivos JS.
- Formateo de evento en 4 funciones distintas.
- Filtros copiados en `get_events` y `get_events_count`, **desincronizados** → paginación incorrecta (`db.php:162-223`).
- `window.showMultipleEventsForDay` definida 3 veces.

### 3.3 SSOT
- Colores de tipos distintos por archivo (`#FFE082`, `#FFF3CD`, degradados en CSS); `color`/`icon` copiados en cada fila.
- Clases `reunion-especial` vs tipo `reunion_especial` → estilos que nunca se aplicaban.
- Regla de fecha mínima en 5 versiones; Font Awesome en 2 versiones.

### 3.4 Eficiencia
- Listado admin sin paginación; N+1 al enriquecer adjuntos; «relacionados» cargaba todos los eventos futuros; 3 autorrefrescos simultáneos; CDN sin SRI.

### 3.5 Seguridad y privacidad
- Cumpleaños de empleados expuestos a anónimos (`wp_ajax_nopriv_*`).
- `innerHTML` con datos interpolados sin escapar; validación de subida con `$_FILES['type']` (controlado por el cliente).
- `uninstall.php` borraba datos sin opción de conservarlos.

### 3.6 UX/UI
- Paleta corporativa ausente; 34 apariciones de `z-index: 999999`; `alert/confirm/prompt` nativos; textarea con auto-resize; Acciones al final; `es-ES` en vez de `es-CO`; lista móvil que no se actualizaba al cambiar de mes.

---

## 4. Decisiones del PO

| # | Decisión | Estado | Consecuencia técnica |
|---|---|---|---|
| D-1 | El calendario **solo se ve con sesión iniciada**. | ✅ Cerrada | Capacidad `eventos_view` = cualquier usuario con `read`. Visitantes anónimos ven un aviso con enlace de inicio de sesión. REST de lectura exige sesión. |
| D-2 | Los 4 tipos se mantienen, pero **deben poder gestionarse** (color, ícono y demás), como en SGP. | ✅ Cerrada | Nuevo dominio `EventType` con CRUD, siguiendo `Process` de SGP (`badge_color` hex + `icon` del catálogo). Migración semilla con los 4 tipos. No se elimina un tipo con eventos (409 Conflict). |
| D-3 | Se **permiten eventos con fecha pasada** (criterio operativo). | ✅ Cerrada | Validación sin fecha mínima; el formulario muestra un aviso no bloqueante «Esta fecha ya pasó». |
| D-4 | Al borrar un evento, **el archivo se queda en la biblioteca**. | ✅ Cerrada | El plugin nunca llama a `wp_delete_attachment`; solo desvincula. |
| D-5 | Hora en **12 h con a. m./p. m.** | ✅ Cerrada | `config/ui.php` igual que SGP: `time_format = 'h:i'`, `meridiem = ['am' => 'a. m.', 'pm' => 'p. m.']` → `03:00 p. m.` |
| D-6 | **Se elimina por completo todo lo del punto**: importar CSV, autoguardado, eventos relacionados, atajos de teclado y `[eventos_widget]`. | ✅ Cerrada | Ni endpoints, ni UI, ni textos, ni opciones. Se conservan (no estaban en el punto): navegación entre eventos del mismo día dentro del modal y `[eventos_proximos]`. |
| D-7 | Eventos con **hora de fin y de varios días**: buena mejora, pero **después** de que v1 esté probada en producción. | ✅ Cerrada (roadmap v1.1) | v1 se diseña pensando en ello: el modelo, el dominio (`EventSchedule`), la consulta por rango y el feed ya manejan inicio y fin; v1 solo deja el fin vacío. Plan de v1.1 en §5.6. |
| D-8 | Producción: **PHP 8.3** (actualizado por el PO desde 8.1 el 2026-10-07) · **WordPress 7.1.3** (Hostinger). Código en **GitHub**. | ✅ Cerrada | `Requires PHP: 8.3`; PHPCompatibility `testVersion 8.3-`; Composer `platform.php 8.3`; wp-env con PHP 8.3 y WP 7.1. Repo en GitHub con CI en GitHub Actions. Diferencia con SGP (8.1) registrada en `docs/adr/`. |
| D-9 | El plugin legado **se eliminó**: se parte en blanco. | ✅ Cerrada | Sin migración de datos; esquema nuevo con prefijo propio; sin compatibilidad de shortcodes. |
| D-10 | El sistema **solo lo gestiona el usuario con acceso al panel de WordPress**. | ✅ Cerrada | Capacidad `eventos_manage` otorgada a `administrator` (igual que `sgp_manage`), asignable a otros roles por filtro. Toda la gestión vive en wp-admin. |
| D-11 | **Toasts** con librería ligera y **tooltips** en botones de acción y textos largos, siguiendo SGP. | ✅ Cerrada | **Notyf** (toasts) y **Tippy.js** (tooltips, con `data-ep-tooltip` y `.ep-truncate` que muestra el texto completo solo si está cortado). |
| D-12 | Las tablas tienen **máximo 6 columnas, contando Acciones** (confirmado: columnas). Lo demás va en la página del evento o en el modal de detalle. | ✅ Cerrada | Regla R-19. Columnas definidas en §6.3. |

---

## 5. Arquitectura objetivo (alineada con SGP)

### 5.1 Principios
- **Misma arquitectura que SGP** para que los plugins de la intranet se mantengan igual: `Core` (infraestructura del plugin), `Shared` (piezas reutilizables) y `Domains/<Dominio>/{Domain, Application, Infrastructure, Presentation}` con un `ServiceProvider` por dominio y un `Container` de inyección de dependencias.
- **REST API** (`eventos/v1`) con `permission_callback`, errores tipados (`ValidationException` → 422 con `fields`, `NotFoundException` → 404, `ConflictException` → 409).
- **Configuración compartida PHP↔JS** en `config/*.php`, expuesta como `window.epConfig` (el JS nunca redefine valores).
- **Fechas:** `created_at`/`updated_at` en UTC (`*_gmt`) y mostradas en Bogotá con `DateFormatter`; `event_date` + `event_time` son **fecha y hora de calendario** (hora de pared en Colombia), que se guardan y muestran sin conversión. `Clock` inyectable para pruebas.
- **Cero lógica de negocio en plantillas.** Cero `<script>`/`<style>` embebidos (la UI se monta en JS sobre `templates/partials/mount.php`, como SGP).

### 5.2 Diferencias deliberadas con SGP

| Tema | SGP | Eventos | Motivo |
|---|---|---|---|
| Base de componentes | SCSS propio | **Bootstrap 5.3** (importado por módulos desde SCSS y encapsulado bajo `.ep-app`) + componentes propios | Pedido del PO |
| Calendario | — | **FullCalendar 6** (núcleo + `daygrid` + `list` + `interaction`), locale `es` | Pedido del PO |
| Prefijos | `sgp_`, `--sgp-*`, `.sgp-*` | PHP (hooks, opciones, capabilities, códigos de error, tablas): `eventos_` (WPCS rechaza prefijos de menos de 4 caracteres como `ep_`) · CSS/JS: `--ep-*`, `.ep-*`, `epConfig` · text domain `eventos-probolsas` | Evitar colisiones entre plugins |
| Adjuntos | `Document/Attachment` | Imagen o PDF desde la Biblioteca de Medios, sin subida propia | R-09 |

### 5.3 Estructura de carpetas

```
eventos-probolsas/
├── eventos-probolsas.php            # Cabecera + Autoloader::register + Plugin::init (como SGP)
├── uninstall.php                    # Plugin::uninstall (borra tablas/opciones/caps; respeta "conservar datos")
├── config/
│   ├── ui.php                       # timezone, locale es-CO, d/m/Y, h:i, meridiem, page_sizes [25, 50, 100], default_page_size 25, csv_separator
│   ├── icons.php                    # Catálogo de íconos FA Free 7 permitidos para tipos de evento
│   └── media.php                    # MIME permitidos (jpg, png, webp, gif, pdf) y tamaño máximo
├── src/                                                ← BACKEND
│   ├── Core/        Plugin, Container, ServiceProvider, Config, Autoloader, Assets, View,
│   │                Admin/{AdminMenu, AdminPage}, Database/{Migrator, Migration, Tables},
│   │                Frontend/{ShortcodeRegistry, Shortcode}, Lifecycle/{Activator, Uninstaller},
│   │                Security/Capabilities (eventos_manage, eventos_view)
│   ├── Shared/      Errors/, Http/{RestApi, RestController, ErrorCode}, Persistence/WpdbRepository,
│   │                Time/{Clock, SystemClock, DateFormatter}, Validation/{Validator, FieldRules},
│   │                Text/{TextNormalizer, Slugger}, Ui/IconCatalog, Ordering/Reordering
│   └── Domains/
│       ├── EventType/   Domain/{EventType, EventTypeRepository, EventTypeSummary}
│       │                Application/EventTypeService
│       │                Infrastructure/{WpdbEventTypeRepository, CreateEventTypesTable, SeedDefaultEventTypes}
│       │                Presentation/{EventTypesPage, EventTypeRestController}
│       ├── Event/       Domain/{Event, EventData, EventRepository, CalendarDate, CalendarTime}
│       │                Application/{EventService, EventQuery}
│       │                Infrastructure/{WpdbEventRepository, CreateEventsTable}
│       │                Presentation/{EventsPage, EventRestController, CalendarFeedController,
│       │                              IcsController, CsvExportController, CalendarShortcode, UpcomingShortcode}
│       ├── Media/       Domain/{Attachment, AttachmentGateway, MediaPolicy}
│       │                Infrastructure/WpAttachmentGateway
│       └── Dashboard/   Application/DashboardService · Presentation/{DashboardPage, DashboardRestController}
├── templates/                                          ← FRONTEND
│   ├── admin/layout.php
│   ├── partials/{mount.php, empty-state.php}
│   └── public/{login-notice.php, calendar.php, upcoming.php}
├── assets/                                             ← FRONTEND
│   ├── src/js/   core/ (api, config, date, dom, i18n, color, pagination, search, timing)
│   │             ui/   (toast, tooltip, data-table, drag-scroll, confirm-dialog, drawer, form,
│   │                    color-field, icon-picker, media-field, badge, load-state, filter-bar)
│   │             screens/ (dashboard, events, event-types) · public/ (calendar, upcoming, event-modal)
│   │             pages/ (admin.js, public.js)
│   ├── src/scss/ tokens/ (colors, typography, spacing, elevation, motion), vendor/bootstrap,
│   │             components/, layers/ (dialog, drawer, toast, tooltip), pages/
│   └── dist/     (generado por Vite; se entrega compilado)
├── languages/eventos-probolsas.pot
├── tests/        php/{Unit, Integration} · js/{core, ui, screens, a11y, build} · fixtures/ (compartidos PHP/JS)
├── docs/         scrum/ · api/ · qa/ · adr/
├── .github/workflows/ci.yml
└── legacy/       (solo lectura; excluido de lint, build y paquete)
```

### 5.4 Stack (versiones fijadas en `package-lock.json` / `composer.lock`)

| Capa | Herramienta |
|---|---|
| PHP | PHP ≥ 8.3, PSR-4 `Probolsas\Eventos\`, PHPUnit 9.6 + Brain Monkey (unit), wp-phpunit (integración), PHPStan 2 nivel 6 + `phpstan-wordpress`, PHPCS WordPress-Extra + PHPCompatibilityWP |
| JS/CSS | Node ≥ 20.19, Vite 8, Sass, ESLint (R-08, R-14, R-15), Stylelint (R-01, R-03), Vitest 4.1 + happy-dom, axe-core (ver ADR-0001) |
| UI | Bootstrap 5.3, FullCalendar 6, Font Awesome Free 7 (npm, fuentes locales), Notyf, Tippy.js, Tom Select (filtro múltiple de tipos) |
| Entorno | `@wordpress/env` (PHP 8.3, WP 7.1) |
| CI | GitHub Actions: lint PHP/JS/CSS → PHPStan → PHPUnit → Vitest → build → presupuesto de tamaño |

### 5.5 Modelo de datos (esquema nuevo)

**`{prefix}eventos_event_types`**

| Columna | Tipo | Regla |
|---|---|---|
| `id` | bigint unsigned PK | |
| `name` / `name_key` | varchar(100) | obligatorio, único sin distinguir mayúsculas ni tildes |
| `slug` | varchar(100) único | generado al crear, no cambia al renombrar |
| `color` | char(7) | `#RRGGBB` obligatorio (con colores sugeridos derivados de la paleta) |
| `icon` | varchar(64) | clave de `config/icons.php` |
| `requires_attachment` | tinyint(1) | si el evento exige imagen o PDF |
| `description` | varchar(500) | opcional |
| `sort_order` | int | orden en filtros y leyenda (reordenable como en SGP) |
| `created_at_gmt`, `updated_at_gmt` | datetime | UTC |

**`{prefix}eventos_events`**

| Columna | Tipo | Regla |
|---|---|---|
| `id` | bigint unsigned PK | |
| `type_id` | bigint FK lógica | tipo existente; índice `(type_id)` |
| `title` | varchar(150) | obligatorio, 3–150 caracteres |
| `description` | text | opcional, ≤ 2.000 caracteres |
| `start_date` | date | obligatorio; se permite el pasado (D-3) |
| `start_time` | time NULL | NULL = todo el día |
| `end_date` | date NULL | **D-7 (v1.1)**: en v1 siempre NULL (= mismo día) |
| `end_time` | time NULL | **D-7 (v1.1)**: en v1 siempre NULL |

Índice `(start_date, end_date)` para la consulta por rango, que **desde v1** busca eventos que se solapan con el rango pedido: `start_date <= :hasta AND COALESCE(end_date, start_date) >= :desde`. Así, cuando lleguen los eventos de varios días, aparecerán en cada día que ocupan sin cambiar la consulta.
| `attachment_id` | bigint NULL | adjunto de la Biblioteca de Medios (imagen o PDF) |
| `created_by`, `updated_by` | bigint | auditoría |
| `created_at_gmt`, `updated_at_gmt` | datetime | UTC |

Migraciones numeradas con `Migrator` (como SGP): 1 `CreateEventTypesTable`, 2 `CreateEventsTable`, 3 `SeedDefaultEventTypes` (Cumpleaños `cake-candles`, Capacitaciones `graduation-cap`, Reuniones especiales `star`, Reuniones laborales `briefcase`; los colores los aprueba el PO en el Sprint 1).

### 5.6 Preparación para D-7 (hora de fin y varios días, v1.1)

Objetivo: que v1.1 sea **solo exponer campos y ajustar la UI**, sin migraciones de datos ni cambios de consultas.

| Capa | En v1 (ya preparado) | En v1.1 (por hacer) |
|---|---|---|
| Datos | Columnas `end_date` y `end_time` creadas, siempre NULL | Ninguna migración |
| Dominio | `EventSchedule` (inicio obligatorio, fin opcional) con sus invariantes probadas: el fin no es anterior al inicio; con hora de fin hay hora de inicio; «todo el día» = sin horas | Quitar la restricción v1 «fin vacío» |
| Validación | Las reglas de fin existen en el validador pero el servicio las rechaza (`end_* no soportado`) | Activarlas |
| API | El DTO de respuesta ya incluye `end_date` y `end_time` (null); el feed calcula `end` para FullCalendar (fin exclusivo en eventos de día completo) | Aceptarlos en POST/PUT |
| Feed | Consulta por solapamiento de rango (§5.5) | Sin cambios |
| UI | El formulario agrupa fecha y hora en un bloque «Cuándo» con espacio para el fin; el modal y la tabla ya usan un formateador de rango (`formatSchedule`) | Mostrar los campos de fin (interruptor «Termina otro día / a otra hora») |
| .ics | `DTEND` calculado desde `EventSchedule` | Sin cambios |

---

## 6. Contrato Backend ↔ Frontend

Es el **único punto de contacto** entre las ramas. Se detalla en `docs/api/` durante el Sprint 0; ningún cambio se hace sin acordarlo en la Daily y actualizar el documento.

### 6.1 Endpoints (`/wp-json/eventos/v1`)

| Método | Ruta | Permiso | Uso |
|---|---|---|---|
| GET | `/calendar?start=&end=&types[]=` | `eventos_view` | Feed de FullCalendar (`EventInput[]`: `id`, `title`, `start` y `end` sin zona, `allDay`, `backgroundColor`, `extendedProps.icon/typeId`) |
| GET | `/events/{id}` | `eventos_view` | Modal de detalle (incluye eventos del mismo día para navegar) |
| GET | `/events/{id}/ics` | `eventos_view` | iCalendar con `VTIMEZONE` de `America/Bogota` |
| GET | `/upcoming?limit=&types[]=` | `eventos_view` | `[eventos_proximos]` |
| GET | `/event-types` | `eventos_view` | Catálogo para filtros y leyenda |
| POST · PUT · DELETE | `/event-types[/{id}]` | `eventos_manage` | CRUD de tipos (DELETE → 409 si tiene eventos) |
| PUT | `/event-types/order` | `eventos_manage` | Reordenar tipos |
| GET | `/events?page=&per_page=(25|50|100)&search=&type=&date_from=&date_to=&orderby=&order=` | `eventos_manage` | Tabla admin paginada (`X-WP-Total`, `X-WP-TotalPages`) |
| POST · PUT · DELETE | `/events[/{id}]` | `eventos_manage` | CRUD de eventos (DELETE no borra el archivo, D-4) |
| GET | `/events/export.csv` | `eventos_manage` | Exportar con los filtros activos (separador `;`, BOM UTF-8) |
| GET | `/dashboard` | `eventos_manage` | Tarjetas de estadísticas |

**Error estándar** (igual que SGP): `{ code, message, data: { status, errors?: { campo: ["mensaje"] } } }`. Convenciones completas en [`docs/api/README.md`](../api/README.md).

### 6.2 Configuración inyectada (`window.epConfig`)

`restUrl`, `restNonce` (`wp_rest`), `ui` (de `config/ui.php`), `icons` (de `config/icons.php`), `media` (MIME y tamaño), `rules` (reglas de validación de cada formulario: obligatorio, longitudes, formatos; las publica el backend desde las constantes de los servicios), `today` (`Y-m-d` calculado en servidor con zona Bogotá), `firstDay` (`start_of_week`), `can.manage`, `loginUrl`.

**Validación en tiempo real (R-24) sin duplicar reglas:** el frontend valida con `epConfig.rules` mientras el usuario escribe, y el backend vuelve a validar con las mismas constantes al guardar. Los mensajes del servidor (422 `data.errors`) tienen prioridad y se muestran en el mismo lugar.

### 6.3 Columnas de las tablas (R-19: máximo 6, Acciones primero)

| Tabla | 1 | 2 | 3 | 4 | 5 | 6 | Se ve en el detalle |
|---|---|---|---|---|---|---|---|
| **Eventos** | Acciones (ver, editar, eliminar) | Evento (título truncado + tooltip) | Tipo (badge con ícono y color) | Fecha (`dd/mm/aaaa`) | Hora (`03:00 p. m.` / «Todo el día») | Adjunto (ícono imagen/PDF) | descripción, vista previa del adjunto, creado/actualizado por y cuándo |
| **Tipos de evento** | Acciones (editar, eliminar) | Tipo (badge con ícono y color) | Requiere adjunto (Sí/No) | Eventos (conteo) | Orden | — | descripción, fechas de creación/modificación |

### 6.4 Propiedad de archivos

- **Backend Senior:** `eventos-probolsas.php`, `uninstall.php`, `config/`, `src/`, `composer.*`, `phpcs.xml.dist`, `phpstan*.neon.dist`, `phpunit*.xml.dist`, `tests/php/`, `docs/api/`.
- **Frontend Senior:** `templates/`, `assets/`, `package*.json`, `vite.config.js`, `eslint.config.js`, `.stylelintrc.json`, `tests/js/`.
- **Compartido (cambio con acuerdo de ambos):** `tests/fixtures/` (casos de fecha comunes PHP/JS), `config/ui.php` (lo lee el frontend, lo publica el backend).
- **QA:** `docs/qa/` y revisión de todos los PR. No edita código de producción; reporta bugs con pasos, resultado esperado y regla R-xx afectada.
- **SM:** `docs/scrum/`, plantilla de PR, tablero.

---

## 7. Reglas transversales (criterios verificables por QA)

| ID | Regla | Cómo se verifica |
|---|---|---|
| **R-01** | Paleta de la interfaz: `#155728` (primario), `#669F30` (secundario), `#FFFFFF`; neutros con matiz verde y estados como en `tokens/_colors.scss` de SGP. Ningún hex fuera de `assets/src/scss/tokens/`. **Excepción:** el color de cada tipo de evento es un dato que elige el usuario (D-2). | Stylelint `color-no-hex` |
| **R-02** | Contraste AA: `#669F30` solo para acentos, bordes, íconos, foco y texto ≥ 18,66 px en negrita. En badges y eventos con el color del tipo, el texto es blanco o negro según `text_tone` (lo calcula el backend): con ese par cualquier color alcanza al menos 4,58:1. | `tests/js/a11y/contrast.test.js`, `ColorContrastTest.php` + axe-core |
| **R-03** | `textarea { resize: none; }` en todo el plugin. | Stylelint + prueba de componente |
| **R-04** | Las tablas se desplazan arrastrando con el mouse (*drag to scroll*): umbral de 5 px, cancela el clic tras arrastrar, no actúa sobre campos. En pantallas táctiles se usa el desplazamiento nativo. | `drag-scroll.test.js` |
| **R-05** | `th` con texto centrado. | Prueba de componente `data-table` |
| **R-06** | Acciones es la **primera** columna y queda fija al desplazar en horizontal. | Prueba de componente |
| **R-07** | Fechas `es-CO`: `dd/mm/aaaa`, hora `hh:mm a. m./p. m.`, nombres de día y mes en español; un solo formateador PHP (`DateFormatter`) y su gemelo JS (`core/date.js`) con fixtures compartidos. | `tests/fixtures/date-formatting.json` en PHPUnit y Vitest |
| **R-08** | **Sin desfases:** prohibido `new Date('YYYY-MM-DD')`, `toISOString()` para fechas de calendario, `date()` sin zona y el sufijo `Z` en horas locales. «Hoy» lo da `epConfig.today`. FullCalendar recibe fechas sin zona. | Regla ESLint `no-restricted-syntax` + pruebas con `TZ=UTC`, `America/Bogota` y `Asia/Tokyo` |
| **R-09** | Adjuntos: solo imagen (`jpg`, `jpeg`, `png`, `webp`, `gif`) o PDF, **elegidos en la Biblioteca de Medios** (`wp.media` con `library.type = ['image', 'application/pdf']`); el backend revalida el `post_mime_type` del adjunto. | Pruebas de integración + caso «.exe renombrado a .pdf» |
| **R-10** | Íconos solo de Font Awesome Free 7 (local). Los íconos de tipos salen de `config/icons.php`. Sin SVG sueltos ni emojis. | Revisión de PR |
| **R-11** | Calendario solo con FullCalendar (mes y lista; en móvil, lista por defecto). | Revisión de PR |
| **R-12** | Animaciones ligeras: 150–250 ms, solo `transform`/`opacity`, respetan `prefers-reduced-motion`. | Prueba con reducción de movimiento |
| **R-13** | Responsive: 360, 768, 1024 y 1440 px sin scroll horizontal de página (solo las tablas, con R-04). | Revisión por breakpoint |
| **R-14** | Sin `<script>`/`<style>` en plantillas, sin `onclick=` y sin `alert`/`confirm`/`prompt` (se usan `confirm-dialog` y toasts). | grep en CI |
| **R-15** | Seguridad: escape en salida (`esc_*`; `textContent` o `escapeHtml` en JS), sanitización en entrada, `permission_callback` en toda ruta y nonce `wp_rest`. | PHPCS + pruebas de permisos (anónimo, suscriptor, administrador) |
| **R-16** | Accesibilidad WCAG 2.2 AA: foco visible, teclado en modal/tabla/calendario/selectores, `aria-*` correctos. | axe-core + prueba manual |
| **R-17** | Rendimiento: assets solo donde se usan; feed por rango; paginación en servidor; presupuesto de tamaño de JS/CSS en CI. | `tests/js/build/budget.test.js` |
| **R-18** | Diseño sobrio, sin «AI slop»: sin degradados genéricos, emojis decorativos ni sombras exageradas; jerarquía tipográfica clara. | Revisión SM + PO en la Review |
| **R-19** | Tablas con **máximo 6 columnas contando Acciones** (§6.3); el resto, en el detalle. | Prueba de componente por pantalla |
| **R-23** | **Paginación en todas las tablas:** 25 registros por defecto y selector de 25 / 50 / 100 (valores de `config/ui.php`). Responsive: en escritorio, «Mostrando 1–25 de 240», selector y números de página con elipsis; en móvil (< 576 px), solo anterior / «Página 3 de 10» / siguiente, con botones de al menos 44 px. Cambiar el tamaño vuelve a la página 1. Eventos se pagina en servidor; tipos de evento, en cliente con el mismo componente. | Prueba de componente `pagination` + revisión por breakpoint |
| **R-20** | Toda acción del usuario (crear, editar, eliminar, exportar, error) se confirma con **toast** (Notyf). Mensajes del servidor siempre escapados. | Prueba de pantalla |
| **R-21** | Botones de solo ícono con **tooltip** (Tippy) y `aria-label`. Textos largos con `.ep-truncate` (`text-overflow: ellipsis`) y tooltip con el texto completo solo si está cortado. | Prueba de componente |
| **R-22** | Acceso: anónimos no ven eventos ni reciben datos de la API; la gestión exige `eventos_manage` y ocurre solo en wp-admin. | Pruebas de integración de permisos |
| **R-24** | **Validación en tiempo real** de los campos obligatorios y con formato (evento: título, tipo, fecha, adjunto si el tipo lo exige; tipo de evento: nombre, color, ícono). Se valida al salir del campo y, una vez marcado con error, en cada cambio, para que el error desaparezca apenas se corrige. Mensaje bajo el campo con `aria-describedby` y `aria-invalid`; el botón Guardar lleva al primer campo con error. Reglas tomadas de `epConfig.rules` (SSOT con el backend). | Pruebas de componente `form` + prueba de pantalla |

---

## 8. Equipo, ceremonias y artefactos

| Rol | Responsabilidad | Límite |
|---|---|---|
| **PO** (usuario) | Prioriza, decide (§4), acepta historias en la Review | — |
| **Scrum Master** | Análisis, facilitación, mantiene este documento, vigila DoD y R-xx, elimina impedimentos | No escribe código de producción |
| **Backend Senior** | Dominios, persistencia, migraciones, REST, seguridad, pruebas PHP | No toca `templates/` ni `assets/` |
| **Frontend Senior** | Design system, plantillas, JS/SCSS, FullCalendar, accesibilidad, pruebas JS | No toca `src/` ni `config/` |
| **QA** | Plan de pruebas, verificación de R-xx y principios, regresión de los 8 defectos del legado, revisión de PR | No corrige código; reporta |

**Cadencia:** sprints de 2 semanas (Sprint 0 de 1 semana). Planning, Daily asíncrona (avance / siguiente paso / impedimento), Review con demo al PO, Retrospectiva; refinamiento a mitad de sprint.

**Flujo en GitHub (decisión del PO, 2026-10-07):** `main` (lo que está en producción) · `develop` (integración) · ramas `feature/H-xxx-descripcion`. Cada historia: desarrollo en su rama → revisión de QA (`docs/qa/`) → si se aprueba, se integra en `develop` **sin PR**. Al cierre de cada fase: revisión de QA de la fase (con la ejecución del CI sobre `develop` en verde, incluidas las pruebas de integración) → **un único PR `develop` → `main`** con la plantilla (historias, reglas R-xx tocadas, informes de QA, evidencia de pruebas). Nunca se abre un PR sin revisión de QA aprobada.

**Definition of Ready:** criterios de aceptación, R-xx aplicables, endpoint del contrato identificado, diseño aprobado (si es UI), estimación.

**Definition of Done:**
1. PR revisado y aprobado por QA; CI verde.
2. PHPCS y PHPStan (nivel 6) sin errores; ESLint y Stylelint sin errores.
3. Pruebas unitarias del dominio ≥ 80 % de cobertura (medida en el CI) y pruebas de componentes de UI tocados. Las **pruebas de integración** (WordPress 7.1.3 con `wp-env`) se escriben en cada historia y se ejecutan **solo en el CI**, en cada push (decisión del PO, 2026-10-07: el equipo de desarrollo tiene 5,9 GB de RAM y Docker no es viable en local).
4. Criterios de aceptación y R-xx verificados; sin bugs críticos o altos abiertos.
5. Sin avisos PHP con `WP_DEBUG` ni errores en consola.
6. Textos en es-CO, preparados para traducción (`eventos-probolsas.pot`).
7. Contrato y documentación actualizados.

---

## 9. Product Backlog

Puntos en Fibonacci. **B** = Backend · **F** = Frontend · **Q** = QA.

### EP-0 · Fundaciones (Sprint 0)
| ID | Historia | Rama | Pts |
|---|---|---|---|
| H-001 | `git init`, `.gitignore`, `.gitattributes` (excluir `legacy/`, `tests/`, `node_modules/` del paquete), ramas `main`/`develop`, plantilla de PR y de issue, repo en GitHub (`softwareprobolsas22-jpg/eventos-probolsas`) — *✅ aprobada por QA ([informe](../qa/2026-10-07-H-005-H-001-H-006-H-007.md)); integrada en `develop`* | SM | 2 |
| H-002 | Esqueleto `Core` + `Shared` portado de SGP (Container, Config, Migrator, Assets, View, RestController, Validator, DateFormatter, Clock, Capabilities) con sus pruebas  — *✅ aprobada por QA con observaciones ([informe](../qa/2026-10-07-H-002-H-003.md)); integrada en `develop`* | B | 8 |
| H-003 | Composer, PHPCS, PHPStan, PHPUnit (unit + integración) y wp-env (PHP 8.3, WP 7.1)  — *✅ aprobada por QA; QA-001 cerrado con el README* | B | 3 |
| H-004 | Vite, Sass, Bootstrap encapsulado, ESLint (con R-08), Stylelint (R-01, R-03), Vitest + happy-dom, axe-core  — *en `feature/H-004-frontend-tooling` (ADR-0001); ✅ aprobada por QA en la segunda revisión ([informe](../qa/2026-10-07-H-004.md)); integrada en `develop`* | F | 3 |
| H-005 | GitHub Actions (CI) — *✅ aprobada por QA (run `37685703899` en verde, [informe](../qa/2026-10-07-H-005-H-001-H-006-H-007.md)); integrada en `develop`* | B+F | 2 |
| H-006 | Contrato API v1 en `docs/api/` — *✅ aprobada por QA ([informe](../qa/2026-10-07-H-005-H-001-H-006-H-007.md)); integrada en `develop`. Pendiente QA-014 (permiso de `GET /events/{id}`) antes del cierre* | B+F | 2 |
| H-007 | Plan de pruebas, matriz R-xx ↔ casos, casos de regresión de los 8 defectos del legado — *✅ [plan](../qa/plan-de-pruebas.md) aprobado; integrada en `develop`* | Q | 3 |

### EP-1 · Tipos de evento (gestionables, D-2)
| ID | Historia | Rama | Pts |
|---|---|---|---|
| H-101 | Como gestor, quiero crear, editar, reordenar y eliminar tipos de evento con nombre, color, ícono y «requiere adjunto», para clasificar los eventos sin depender de un desarrollador  — *✅ aprobada por QA ([informe](../qa/2026-10-08-H-101-H-102.md)); integrada en `develop`* | B | 8 |
| H-102 | Migraciones de tablas y semilla con los 4 tipos actuales  — *✅ aprobada por QA ([informe](../qa/2026-10-08-H-101-H-102.md)); integrada en `develop`* | B | 3 |
| H-103 | Design system: tokens, tema Bootstrap, botones, badge de tipo con contraste automático, toasts (Notyf), tooltips (Tippy), confirm-dialog, drawer, estados vacío/cargando | F | 8 |
| H-104 | Pantalla «Tipos de evento»: tabla (R-19, R-23), formulario en drawer con `color-field` e `icon-picker` y validación en tiempo real (R-24) | F | 5 |
| H-105 | Pruebas de tipos: unicidad, conflicto al borrar con eventos, contraste, permisos | Q | 3 |

### EP-2 · Gestión de eventos (wp-admin)
| ID | Historia | Rama | Pts |
|---|---|---|---|
| H-201 | Dominio y API de eventos: `EventSchedule` preparado para D-7, validación única publicada en `epConfig.rules`, búsqueda/filtros/conteo compartidos, consulta por solapamiento de rango, paginación, export CSV | B | 8 |
| H-202 | Gateway de Medios: valida imagen/PDF, entrega URL, miniatura y tipo; nunca borra archivos | B | 3 |
| H-203 | Pantalla «Eventos»: tabla paginada en servidor (R-04/05/06/19/23), búsqueda con *debounce*, filtros (tipo, rango de fechas), exportar | F | 8 |
| H-204 | Formulario de evento (drawer o página): validación en tiempo real (R-24), `media-field` imagen/PDF con vista previa, `textarea` sin resize, aviso de fecha pasada, bloque «Cuándo» preparado para D-7, errores por campo desde la API | F | 8 |
| H-205 | Detalle del evento (todo lo que no cabe en la tabla) | F | 3 |
| H-206 | Dashboard: estadísticas (hoy, próximos 30 días, por tipo) | B+F | 5 |
| H-207 | Pruebas del CRUD, permisos, adjuntos no permitidos y exportación | Q | 5 |

### EP-3 · Calendario para colaboradores
| ID | Historia | Rama | Pts |
|---|---|---|---|
| H-301 | Feed por rango, detalle con eventos del mismo día, `.ics`, próximos | B | 5 |
| H-302 | `[eventos_calendario]` con FullCalendar: mes/lista, `es`, `firstDay` de WP, color e ícono por tipo, filtro de tipos, «hoy» desde el servidor; aviso de inicio de sesión para anónimos | F | 8 |
| H-303 | Modal de detalle: fecha/hora es-CO, descripción, imagen ampliable o PDF (abrir/descargar), navegación entre eventos del mismo día, «Añadir a mi calendario» (sin eventos relacionados, D-6) | F | 5 |
| H-304 | `[eventos_proximos]` | F | 3 |
| H-305 | Pruebas de zona horaria (3 zonas y cambio de día 23:59 → 00:00 en Bogotá), responsive y accesibilidad | Q | 5 |

### EP-4 · Endurecimiento y entrega
| ID | Historia | Rama | Pts |
|---|---|---|---|
| H-401 | Caché del feed por rango con invalidación al escribir | B | 3 |
| H-402 | Desinstalación con opción «conservar datos»; limpieza de capacidades | B | 2 |
| H-403 | Auditoría de accesibilidad y rendimiento; ajustes | F | 3 |
| H-404 | Prueba de aceptación con el PO en staging (WP 7.1.3 / PHP 8.3) y paquete `.zip` de release | SM+Q | 3 |

### EP-5 · Roadmap v1.1 (después de validar v1 en producción)
| ID | Historia | Rama | Pts |
|---|---|---|---|
| H-501 | Como gestor, quiero indicar hora de fin y eventos de varios días (D-7), según §5.6 | B+F | 5 |
| H-502 | Pruebas de eventos de varios días en el calendario (cruce de semana y de mes) y en el .ics | Q | 3 |

---

## 10. Plan de sprints

| Sprint | Meta | Historias | Pts |
|---|---|---|---|
| **0** (1 semana) | «Podemos trabajar con seguridad» — ✅ cerrado, 23/23 | H-001 … H-007 | 23 |
| **1** | «Los tipos de evento se gestionan y la UI tiene identidad» — 🚧 en curso ([plan](sprint-1.md)) | H-101 … H-105 | 27 |
| **2** | «El gestor administra eventos sin errores» | H-201 … H-207 | 40 |
| **3** | «Los colaboradores ven el calendario sin desfases» | H-301 … H-305 | 26 |
| **4** | «Listo para producción» | H-401 … H-404 | 11 |

El Sprint 2 está por encima de la capacidad estimada (30–35 pts): si la velocidad del Sprint 1 no lo respalda, H-206 (dashboard) pasa al Sprint 4.

---

## 11. Riesgos

| Riesgo | Prob. | Impacto | Mitigación |
|---|---|---|---|
| Soporte de PHP en el servidor | Baja | Medio | Producción ya está en PHP 8.3 (soporte de seguridad hasta el 31/12/2027); CI probará también PHP 8.4 para anticipar la próxima actualización |
| Colores de tipo elegidos por el usuario con poco contraste | Alta | Medio | Texto automático blanco/oscuro + aviso en el formulario (R-02); distinguir tipos también por ícono |
| CSS de Bootstrap choca con wp-admin u otros plugins | Media | Medio | Importar solo los módulos usados, bajo `.ep-app`; sin estilos globales |
| FullCalendar aumenta el peso de la página pública | Media | Bajo | Solo los plugins usados, cargados solo donde hay shortcode; presupuesto en CI |
| Divergencia con SGP en piezas compartidas (`Core`, `Shared`, UI kit) | Media | Medio | Portar con pruebas; registrar diferencias en `docs/adr/`; a futuro, evaluar paquete común |

---

## 12. Historial

| Versión | Fecha | Cambio |
|---|---|---|
| 1 | 2026-10-07 | Análisis inicial del legado y propuesta |
| 2 | 2026-10-07 | Decisiones del PO D-1…D-12; arquitectura y stack alineados con SGP; tipos de evento gestionables; inicio en blanco (sin migración); tablas ≤ 6 columnas; toasts y tooltips; reglas R-19…R-22 |
| 2.1 | 2026-10-07 | Repositorio GitHub definido por el PO; H-001 iniciada |
| 2.2 | 2026-10-07 | Producción actualizada a PHP 8.3 (D-8); primer commit publicado en GitHub (`main` y `develop`) |
| 2.3 | 2026-10-07 | D-6 cerrada sin supuestos (se elimina todo lo del punto, incluidos eventos relacionados); D-12 confirmada como columnas; nueva regla R-23 de paginación responsive 25/50/100 |
| 2.4 | 2026-10-07 | D-7 pasa al roadmap v1.1 con el modelo preparado desde v1 (§5.6, EP-5); regla R-24 de validación en tiempo real con reglas publicadas por el backend (`epConfig.rules`) |
| 2.5 | 2026-10-07 | H-002/H-003 (Backend): prefijo PHP `eventos_` por exigencia de WPCS; contrato alineado con SGP (`restNonce`, `data.errors`); convenciones de la API en `docs/api/README.md` |
| 2.6 | 2026-10-07 | Revisión de QA de H-002/H-003 (aprobada con 8 observaciones); pruebas de integración al cierre de cada fase (decisión del PO) |
| 2.7 | 2026-10-07 | H-004 (Frontend): tooling, Bootstrap encapsulado y `assets/dist` versionado (ADR-0001) |
| 2.8 | 2026-10-07 | Revisión de QA de H-004: cambios requeridos (QA-009, alta). Regla del PO: no se crea PR sin revisión de QA aprobada |
| 2.9 | 2026-10-07 | H-004: correcciones de QA-009, QA-010 y QA-011; aprobada por QA en la segunda revisión |
| 2.10 | 2026-10-07 | Flujo: las historias aprobadas por QA se integran en `develop` sin PR; un único PR `develop` → `main` por fase |
| 2.11 | 2026-10-07 | H-001, H-005, H-006 y H-007 aprobadas por QA e integradas; CI con integración en WordPress real en verde |
| 2.12 | 2026-10-07 | Cierre del Sprint 0 (23/23 puntos, [informe](../qa/2026-10-07-cierre-sprint-0.md)). Decisión del PO: pruebas de integración solo en el CI (QA-020) |
| 2.13 | 2026-10-07 | Planning del Sprint 1 ([plan](sprint-1.md)); propuesta de colores de los tipos iniciales para la Review |
