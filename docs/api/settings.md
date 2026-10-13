# API · Ajustes (`/settings`)

> Contrato v1 (H-402, decisión del PO D-16). Implementación: Backend `Core/Settings/PluginSettings` y `Domains/Settings` · Frontend pantalla «Ajustes». Convenciones generales en [README.md](README.md).

## Representación

```json
{ "delete_data_on_uninstall": false }
```

| Campo | Notas |
|---|---|
| `delete_data_on_uninstall` | Si desinstalar el plugin borra las tablas de eventos y tipos, la versión del esquema, la caché y los ajustes. **Falso por defecto** (D-16): desinstalar conserva los datos para una reinstalación. Los archivos de la Biblioteca de Medios nunca se borran (D-4). Las capacidades (`eventos_manage`) se quitan siempre; al reinstalar se vuelven a dar a los administradores |

## Endpoints (`eventos_manage`)

| Método | Ruta | Respuesta |
|---|---|---|
| GET | `/settings` | `200` la representación |
| PUT | `/settings` | `200` la representación guardada · `422` si `delete_data_on_uninstall` no es sí o no |

Cuerpo de PUT: `{ "delete_data_on_uninstall": true }`. También se aceptan `"true"`, `"false"`, `"1"`, `"0"`, `1` y `0`. Cualquier otro valor (o si falta) → `422` con el error en `delete_data_on_uninstall`: «El campo «Borrar todos los datos al desinstalar» debe ser sí o no.».

Visitante → `401`; usuario con sesión sin `eventos_manage` → `403`.

## Guía de los shortcodes (H-406)

| Método | Ruta | Respuesta |
|---|---|---|
| GET | `/settings/shortcodes` | `200` la guía de cada shortcode del plugin, en el orden en que se registran |

Solo lectura y con los mismos permisos (`eventos_manage`). Cada shortcode describe la suya (`Shortcode::guide()`), así que un shortcode nuevo aparece aquí sin tocar la API.

```json
[
  {
    "tag": "eventos_proximos",
    "title": "Próximos eventos",
    "description": "Lista de los eventos que vienen, desde hoy. Solo la ven los usuarios con sesión iniciada.",
    "example": "[eventos_proximos limite=\"5\"]",
    "attributes": [
      { "name": "limite", "description": "Cuántos eventos mostrar, de 1 a 20.", "required": false, "default": "5", "values": null },
      { "name": "tipos", "description": "Muestra solo estos tipos de evento, separados por comas.", "required": false, "default": "Todos", "values": "event_types" }
    ]
  }
]
```

| Campo | Notas |
|---|---|
| `example` | Shortcode listo para copiar |
| `attributes[].default` | Valor por defecto en texto para el usuario; vacío si no tiene |
| `attributes[].values` | `null` si el atributo es texto libre. `"event_types"`: los valores válidos son los *slugs* de los tipos de evento, que la pantalla toma de `GET /event-types` |
