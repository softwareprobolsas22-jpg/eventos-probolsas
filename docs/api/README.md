# API REST de Eventos Probolsas — convenciones

> Contrato entre Backend y Frontend (§6 de [`docs/scrum/00-analisis-sm.md`](../scrum/00-analisis-sm.md)). Ninguna rama cambia este documento sin acordarlo con la otra. Los endpoints de cada recurso se documentan en un archivo propio dentro de esta carpeta a medida que se construyen.

## Base

- **Namespace:** `/wp-json/eventos/v1/` (`RestApi::NAMESPACE_V1`). El navegador lo recibe ya armado en `epConfig.restUrl`.
- **Autenticación:** cookie de sesión de WordPress más la cabecera `X-WP-Nonce` con `epConfig.restNonce` (acción `wp_rest`). Sin un nonce válido WordPress trata la petición como anónima.
- **Formato:** JSON en el cuerpo de las peticiones (`Content-Type: application/json`) y en las respuestas.
- **Caché:** todas las respuestas llevan `Cache-Control: no-store, private` (y la señal equivalente para LiteSpeed): los datos cambian con cada guardado. En el servidor, el feed del calendario y los próximos se guardan ya armados (`Shared/Cache/ResponseCache`, H-401): la clave lleva el rango, los tipos (y «hoy» de Colombia en los próximos) y una versión que cambia al crear, editar o eliminar eventos o tipos, al reordenar tipos y al editar o borrar un adjunto en la Biblioteca de Medios. Las respuestas de error (422) no se guardan.

## Permisos

| Capability | Quién la tiene | Uso |
|---|---|---|
| `eventos_view` | Cualquier usuario con sesión (se concede de forma dinámica a quien tenga `read`) | Lectura: calendario, detalle, próximos, tipos |
| `eventos_manage` | Administradores (se asigna al activar; puede otorgarse a otros roles) | Escritura y pantallas de wp-admin |

Sin sesión → `401 rest_forbidden`. Con sesión y sin permiso → `403 rest_forbidden`. Los visitantes anónimos no reciben ningún dato de eventos (D-1, R-22).

## Respuesta exitosa

```json
{ "data": { "id": 7, "name": "Cumpleaños" } }
```

`data` es un objeto o una lista. Los listados paginados agregan las cabeceras `X-WP-Total` y `X-WP-TotalPages`; los tamaños de página válidos son los de `epConfig.ui.page_sizes` (25, 50, 100; por defecto 25).

## Errores

Formato estándar de WordPress:

```json
{
  "code": "eventos_validation_failed",
  "message": "Revisa los campos marcados.",
  "data": {
    "status": 422,
    "errors": { "name": ["El campo «Nombre» es obligatorio."] }
  }
}
```

- `message` siempre es apto para el usuario (español, claro) y se muestra en un toast.
- `data.errors` solo aparece en errores de validación: un mensaje por campo, que la interfaz pinta bajo cada campo (R-24). Tiene prioridad sobre la validación en tiempo real del navegador.

| Código | HTTP | Cuándo |
|---|---|---|
| `eventos_validation_failed` | 422 | Datos de entrada no válidos (`ValidationException`) |
| `eventos_not_found` | 404 | El recurso no existe o fue eliminado (`NotFoundException`) |
| `eventos_conflict` | 409 | La operación viola una regla de negocio, por ejemplo eliminar un tipo de evento que tiene eventos (`ConflictException`) |
| `eventos_server_error` | 500 | La base de datos rechazó la operación; el detalle técnico va al log, no al usuario (`PersistenceException`) |

La tabla la verifica `tests/php/Unit/Shared/Http/ErrorCodeTest.php`: un código nuevo en `ErrorCode` obliga a documentarlo aquí.

## Fechas

| Clase | Ejemplo en la API | Regla |
|---|---|---|
| Momento de auditoría (creado, actualizado) | `"2026-10-01T23:30:00-05:00"` | ISO 8601 con desplazamiento de Bogotá; se guarda en UTC |
| Fecha de calendario (día del evento) | `"2026-10-07"` | Sin zona y sin conversión |
| Hora de calendario (hora del evento) | `"15:00"` | 24 h sin zona; `null` = todo el día. La interfaz la muestra en 12 h (`03:00 p. m.`) |

El navegador nunca calcula «hoy»: usa `epConfig.today` (R-08).

## Configuración del navegador (`window.epConfig`)

La inyecta `Core\Assets\Assets::client_config()` antes del script de cada pantalla:

| Clave | Contenido |
|---|---|
| `version` | Versión del plugin |
| `restUrl`, `restNonce` | Base de la API y nonce |
| `ui` | `config/ui.php`: zona, locale, formatos de fecha y hora, a. m./p. m., tamaños de página, separador CSV |
| `icons` | `config/icons.php`: `[{ key, label, keywords }]` para el selector de íconos (Font Awesome Free 7, sólido) |
| `media` | `config/media.php`: MIME permitidos, tipos para `wp.media`, tamaño máximo sugerido |
| `today` | Fecha de hoy en Colombia (`Y-m-d`) |
| `firstDay` | Primer día de la semana configurado en WordPress (0 = domingo, 1 = lunes) |
| `can.manage` | Si el usuario puede gestionar eventos |
| `loginUrl` | Enlace de inicio de sesión |
| `rules` | *(lo agregan los dominios con el filtro `eventos_client_config`)* reglas de validación de cada formulario |

## Plantillas que el núcleo renderiza (propiedad del Frontend)

| Plantilla | La usa | Recibe en `$data` |
|---|---|---|
| `templates/public/notice.php` | `WidgetRenderer` (visitante sin sesión o sin permiso) | `icon` (clases FA), `message`, `link?` (`url`, `label`) |
| `templates/public/widget.php` | `WidgetRenderer` (usuario con `eventos_view`) | `widget` (`calendar` o `upcoming`), `props` (opciones ya saneadas) |
| `templates/admin/layout.php` | Pantallas de administración de cada dominio | `screen`, `icon`, `title`, `subtitle?`, `actions?` (HTML ya escapado), `content` (HTML ya escapado) |
| `templates/partials/mount.php` | Contenido de las pantallas con interfaz JS | `id` |
| `templates/partials/empty-state.php` | Estados vacíos renderizados en PHP | `icon`, `title`, `message` |

## Recursos (contrato v1)

| Archivo | Recurso | Historias |
|---|---|---|
| [`event-types.md`](event-types.md) | Tipos de evento: catálogo gestionable, reglas de validación y tipos iniciales | H-101, H-102, H-104 |
| [`events.md`](events.md) | Eventos: gestión, exportación, panel, calendario (FullCalendar), detalle, `.ics` y próximos | H-201 a H-206, H-301 a H-304 |
| [`settings.md`](settings.md) | Ajustes: conservar o borrar los datos al desinstalar (D-16) | H-402 |
