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
