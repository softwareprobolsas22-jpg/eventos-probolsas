# API · Subida del adjunto (`POST /wp/v2/media`)

> Contrato de H-411 (adjunto con arrastrar y soltar). Implementación: Backend `Domains/Media/Presentation/AttachmentUpload` · Frontend campo de adjunto del formulario de evento. Convenciones generales en [README.md](README.md).

El plugin **no tiene endpoint de subida propio**: el archivo que se suelta en el formulario se guarda con la API de WordPress, `POST /wp/v2/media` (permiso `upload_files`), y queda en la Biblioteca de Medios como cualquier otro (R-09). El plugin solo agrega una revisión **antes** de que WordPress lo guarde.

## La marca

| Cabecera | Valor |
|---|---|
| `X-Eventos-Upload` | `event-attachment` |

Solo las peticiones `POST /wp/v2/media` con esta cabecera pasan por la revisión. Las demás subidas (la Biblioteca de Medios, otros plugins) no cambian.

## Revisión (antes de guardar)

| Condición | Respuesta |
|---|---|
| No llega el campo `file` (multipart) | `422` «No se recibió ningún archivo.» |
| Pesa más que `wp_max_upload_size()` | `422` «El archivo supera el tamaño máximo permitido (10 MB).» |
| El contenido real (`finfo`) no está en `media.allowed_mimes` | `422` «El archivo debe ser una imagen o un PDF.» |
| La extensión no corresponde al contenido (por ejemplo, un PDF llamado `.png`) | `422` «La extensión del archivo no corresponde a su contenido.» |
| PHP no pudo recibir el archivo (`error` ≠ 0) | Lo informa WordPress con su propio mensaje |

El `422` usa el formato de la API del plugin: `code` `eventos_validation_failed`, el mensaje y `data.errors.file`. Como la revisión ocurre antes de guardar, **un archivo rechazado nunca queda en la Biblioteca de Medios** (D-4: el plugin no borra archivos).

Si pasa la revisión, responde WordPress: `201` con el adjunto (`id`, `mime_type`, `source_url`, `media_details.sizes`, `title.rendered`…). El formulario usa ese `id` como `attachment_id` del evento, que el servidor revalida al guardar (ver [events.md](events.md)).

## Configuración para el navegador (`epConfig`)

| Clave | Valor |
|---|---|
| `media.upload_url` | Dirección de `wp/v2/media` (respeta los enlaces permanentes del sitio) |
| `media.upload_header`, `media.upload_context` | La marca: `X-Eventos-Upload` y `event-attachment` |
| `media.max_upload_bytes` | `wp_max_upload_size()`: el navegador rechaza antes de subir un archivo más grande |
| `can.upload` | Si el usuario tiene `upload_files`. Sin él, el campo no acepta archivos soltados y sigue disponible «Elegir archivo» |
