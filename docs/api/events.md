# API · Eventos (`/events`)

> Contrato v1 (H-006). Implementación: Backend H-201/H-202/H-206/H-301 · Frontend H-203/H-204/H-205/H-302/H-303/H-304. Convenciones generales en [README.md](README.md).

## Representación

```json
{
  "id": 42,
  "title": "Cumpleaños de Ana María",
  "description": "Celebración en la sala de juntas.",
  "type": { "id": 1, "name": "Cumpleaños", "slug": "cumpleanos", "color": "#155728", "text_tone": "light", "icon": "cake-candles" },
  "start_date": "2026-10-07",
  "start_time": "15:00",
  "end_date": null,
  "end_time": null,
  "all_day": false,
  "is_past": false,
  "attachment": {
    "id": 315,
    "kind": "image",
    "mime": "image/jpeg",
    "url": "https://intranet.probolsas.com/wp-content/uploads/2026/10/ana.jpg",
    "thumbnail_url": "https://intranet.probolsas.com/wp-content/uploads/2026/10/ana-150x150.jpg",
    "title": "ana",
    "filename": "ana.jpg",
    "filesize": 182044
  },
  "created_by": { "id": 3, "name": "Talento Humano" },
  "updated_by": { "id": 3, "name": "Talento Humano" },
  "created_at": "2026-10-01T08:30:00-05:00",
  "updated_at": "2026-10-01T08:30:00-05:00"
}
```

| Campo | Notas |
|---|---|
| `start_date` | Fecha de calendario `YYYY-MM-DD`, sin zona (R-08). Se permite el pasado (D-3) |
| `start_time` | `HH:MM` 24 h sin zona, o `null` = todo el día. La interfaz lo muestra en 12 h (`03:00 p. m.`) |
| `end_date`, `end_time` | **Reservados para D-7 (v1.1)**: siempre `null` en v1; si llegan con valor, 422 |
| `all_day` | `true` si `start_time` es `null` |
| `is_past` | Calculado por el servidor con la fecha de Colombia (para el aviso «Esta fecha ya pasó») |
| `attachment` | `null` o el adjunto de la Biblioteca de Medios. `kind`: `image` o `pdf`. `thumbnail_url` es `null` para PDF |
| `created_by`, `updated_by` | Usuario de WordPress (`null` si fue eliminado) |

## Gestión (wp-admin, `eventos_manage`)

| Método | Ruta | Respuesta |
|---|---|---|
| GET | `/events` | `200` lista paginada (ver filtros) |
| POST | `/events` | `201` · `422` |
| PUT | `/events/{id}` | `200` · `404` · `422` |
| DELETE | `/events/{id}` | `200 { "deleted": true, "id": 42 }` · `404`. **Nunca borra el archivo de la Biblioteca de Medios** (D-4) |
| GET | `/events/export.csv` | Archivo CSV con los filtros activos (separador `;`, BOM UTF-8, fechas `dd/mm/aaaa`, horas en 12 h) |
| GET | `/dashboard` | `200 { "today": 2, "next_30_days": 9, "by_type": [ { "type_id": 1, "count": 4 } ], "total": 57 }` |

### Filtros de `GET /events` y del CSV

| Parámetro | Valores | Por defecto |
|---|---|---|
| `page` | ≥ 1 | 1 |
| `per_page` | `25` \| `50` \| `100` (R-23); otro valor → 422 | 25 |
| `search` | texto (máx. 100): busca en título y descripción, sin distinguir mayúsculas ni tildes; deben aparecer todas las palabras, en cualquier orden | — |
| `type` | ID de tipo | — |
| `date_from`, `date_to` | `YYYY-MM-DD` (por solapamiento: incluye eventos que ocupan el rango) | — |
| `orderby` | `start_date` \| `title` \| `type` \| `created_at` | `start_date` |
| `order` | `asc` \| `desc` (sin distinguir mayúsculas) | `asc` |

Cabeceras: `X-WP-Total`, `X-WP-TotalPages`. Una página fuera de rango devuelve `200` con lista vacía (la interfaz vuelve a la última página).

**CSV** (`/events/export.csv`): columnas `Evento; Tipo; Fecha; Hora; Descripción; Adjunto; Creado por; Actualizado`, en el orden de la tabla y sin paginar. «Hora» dice «Todo el día» si no hay hora; «Adjunto», «Sí» o «No». Las celdas que empiezan por `=`, `+`, `-` o `@` llevan un apóstrofo delante (protección contra fórmulas en Excel). El navegador lo descarga con el nonce en la dirección (`?_wpnonce=…`), porque un enlace no envía la cabecera `X-WP-Nonce`.

### Cuerpo de POST y PUT

```json
{
  "title": "Cumpleaños de Ana María",
  "type_id": 1,
  "start_date": "2026-10-07",
  "start_time": "15:00",
  "description": "Celebración en la sala de juntas.",
  "attachment_id": 315
}
```

### Reglas de validación (R-24)

Publicadas en `epConfig.rules.event`; la API las vuelve a aplicar.

| Campo | Reglas | Mensaje |
|---|---|---|
| `title` | obligatorio · 3 a 150 caracteres | «El campo «Título» es obligatorio.» · «…debe tener al menos 3 caracteres.» |
| `type_id` | obligatorio · tipo existente | «Selecciona una opción válida en el campo «Tipo».» |
| `start_date` | obligatorio · fecha real `YYYY-MM-DD` | «El campo «Fecha» debe ser una fecha válida.» |
| `start_time` | opcional · `HH:MM` | «El campo «Hora» debe ser una hora válida.» |
| `description` | máx. 2.000 | «El campo «Descripción» admite máximo 2000 caracteres.» |
| `attachment_id` | **obligatorio si el tipo tiene `requires_attachment`** · adjunto existente · imagen (`jpg`, `png`, `webp`, `gif`) o PDF según su tipo MIME real (R-09) | «Este tipo de evento requiere una imagen o un PDF.» · «El archivo debe ser una imagen o un PDF.» · «El archivo elegido ya no existe en la Biblioteca de Medios.» |
| `end_date`, `end_time` | no admitidos en v1 | «La hora de fin estará disponible en una próxima versión.» |

```json
"rules": {
  "event": {
    "title": { "required": true, "minLength": 3, "maxLength": 150 },
    "type_id": { "required": true, "oneOf": "event_types" },
    "start_date": { "required": true, "format": "date" },
    "start_time": { "format": "time" },
    "description": { "maxLength": 2000 },
    "attachment_id": { "requiredWhen": "type.requires_attachment", "mimes": "media.allowed_mimes" }
  }
}
```

Origen de los valores que las reglas nombran (QA-016):

| Regla | Valores válidos |
|---|---|
| `"oneOf": "event_types"` | Los `id` de la respuesta de `GET /event-types` (la pantalla ya la carga para el selector de tipo; no viajan en `epConfig`) |
| `"requiredWhen": "type.requires_attachment"` | `requires_attachment` del tipo elegido en el formulario |
| `"mimes": "media.allowed_mimes"` | `epConfig.media.allowed_mimes` |
| `"format": "date"` / `"time"` | `YYYY-MM-DD` real / `HH:MM` de 00:00 a 23:59 |

## Calendario (colaboradores con sesión, `eventos_view`)

| Método | Ruta | Respuesta |
|---|---|---|
| GET | `/calendar?start=YYYY-MM-DD&end=YYYY-MM-DD&types[]=1&types[]=2` | `200` lista de eventos con formato `EventInput` de FullCalendar (ver abajo), ordenados por fecha, hora (los de todo el día primero) e ID. `start` incluido y `end` exclusivo, como los entrega FullCalendar, **solo como fecha** (`2026-10-07T00:00:00Z` → 422). Por solapamiento (§5.5). Rango máximo: 62 días (la vista de mes ocupa 42) → 422 |
| GET | `/events/{id}` | `200` la representación completa más `same_day: [ { "id": 41, "title": "…", "start_time": "09:00" } ]` (navegación entre eventos del mismo día, ordenados por hora) · `404`. **Única definición de la ruta** (QA-014): la usan el modal del calendario y la página de detalle de wp-admin (H-205) |
| GET | `/events/{id}/ics` | Archivo `evento-{id}.ics` (`text/calendar`, ver abajo) · `404` |
| GET | `/upcoming?limit=5&types[]=1` | `200` próximos eventos desde hoy (Colombia, incluidos los de hoy que ya pasaron de hora), en el orden del feed, con la **misma representación de `GET /events/{id}` sin `same_day`**. `limit` de 1 a 20 (por defecto 5); otro valor → 422 |

`types[]` (feed y próximos): IDs de tipo; vacío = todos. Un solo valor puede llegar sin corchetes (`types=1`). Un valor que no es un ID → 422 en `types`.

### `EventInput` (FullCalendar)

```json
{
  "id": "42",
  "title": "Cumpleaños de Ana María",
  "start": "2026-10-07T15:00:00",
  "end": null,
  "allDay": false,
  "backgroundColor": "#155728",
  "borderColor": "#155728",
  "textColor": "#FFFFFF",
  "extendedProps": { "typeId": 1, "icon": "cake-candles", "hasAttachment": true }
}
```

- `start` **sin zona horaria**. FullCalendar se configura con `timeZone: 'UTC'` para que no convierta nada y muestre la hora de pared de Colombia sin importar la zona del equipo (R-08). Como con esa opción su «hoy» sería la fecha UTC (después de las 7:00 p. m. resaltaría mañana), se le pasa `now` con la hora actual de Bogotá calculada por `core/date.js` (sin zona, por ejemplo `2026-10-07T20:30:00`).
- Evento de día completo: `"start": "2026-10-07"`, `"allDay": true`.
- `textColor` se deriva de `text_tone` del tipo.
- `end`: `null` en v1; en v1.1 (D-7) llevará el fin, exclusivo en los eventos de día completo.
- `textColor`: `#FFFFFF` si `text_tone` es `light`, `#000000` si es `dark` (`ColorContrast::readable_color`).

### `.ics` (Añadir a mi calendario)

El navegador lo descarga con un enlace y el nonce en la dirección (`/events/42/ics?_wpnonce=…`, igual que el CSV). RFC 5545: líneas con CRLF plegadas a 75 octetos y texto escapado (`\\`, `\;`, `\,`, `\n`). Corrige RL-03 (el legado marcaba la hora de Colombia con `Z`).

```text
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Probolsas//Eventos Probolsas//ES
CALSCALE:GREGORIAN
METHOD:PUBLISH
BEGIN:VTIMEZONE
TZID:America/Bogota
BEGIN:STANDARD
DTSTART:19700101T000000
TZOFFSETFROM:-0500
TZOFFSETTO:-0500
TZNAME:-05
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
UID:evento-42@intranet.probolsas.com
DTSTAMP:20261008T013000Z
DTSTART;TZID=America/Bogota:20261007T150000
DTEND;TZID=America/Bogota:20261007T160000
SUMMARY:Cumpleaños de Ana María
DESCRIPTION:Celebración en la sala de juntas.
CATEGORIES:Cumpleaños
END:VEVENT
END:VCALENDAR
```

- Con hora y sin fin (v1): `DTEND` una hora después del inicio, la duración por defecto de Google Calendar y Outlook.
- Todo el día: `DTSTART;VALUE=DATE:20261007` y `DTEND;VALUE=DATE:20261008` (exclusivo), sin `VTIMEZONE`.

## Shortcodes de la intranet

Las clases PHP son del Backend (`CalendarShortcode`, `UpcomingShortcode`); los widgets, del Frontend. Un visitante sin sesión ve el aviso «Inicia sesión para ver el calendario de eventos.» con el enlace de inicio de sesión que vuelve a la página, y la página no carga el JS ni los estilos del plugin (D-1, R-17). Con sesión, `templates/public/widget.php` imprime el contenedor con las opciones ya resueltas en `data-ep-props`; los eventos los pide el widget a la API.

| Shortcode | Atributos | `data-ep-widget` | `data-ep-props` |
|---|---|---|---|
| `[eventos_calendario]` | `tipos`: *slugs* separados por comas (los desconocidos se ignoran) | `calendar` | `{ "types": [1, 2] }` (vacío = todos) |
| `[eventos_proximos]` | `limite` (1–20, por defecto 5; fuera de rango se ajusta al extremo) · `tipos` · `titulo` (por defecto «Próximos eventos»; vacío = sin título) | `upcoming` | `{ "limit": 5, "types": [], "title": "Próximos eventos" }` |

## Errores específicos

| Situación | Respuesta |
|---|---|
| Visitante sin sesión en cualquier ruta | `401 rest_forbidden` (D-1) |
| Usuario con sesión sin `eventos_manage` en una ruta de gestión | `403 rest_forbidden` |
| Adjunto inexistente o de otro tipo | `422`, error en `attachment_id` |
| Tipo inexistente | `422`, error en `type_id` |
| Rango de calendario inválido o mayor a 62 días | `422`, errores en `start` / `end` |
| Filtro de tipos con un valor que no es un ID | `422`, error en `types` |
| `limit` de próximos fuera de 1–20 | `422`, error en `limit` |
