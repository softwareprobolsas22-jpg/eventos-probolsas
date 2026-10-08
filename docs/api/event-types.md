# API · Tipos de evento (`/event-types`)

> Contrato v1 (H-006). Implementación: Backend H-101/H-102 · Frontend H-104. Convenciones generales en [README.md](README.md).

Catálogo gestionable de tipos (D-2): cada evento pertenece a un tipo, que define su color, su ícono y si exige un adjunto. Se crean de entrada los 4 tipos del legado.

## Representación

```json
{
  "id": 1,
  "name": "Cumpleaños",
  "slug": "cumpleanos",
  "color": "#155728",
  "text_tone": "light",
  "icon": "cake-candles",
  "requires_attachment": true,
  "description": "",
  "sort_order": 1,
  "events_count": 12,
  "created_at": "2026-10-07T09:15:00-05:00",
  "updated_at": "2026-10-07T09:15:00-05:00"
}
```

| Campo | Tipo | Notas |
|---|---|---|
| `id` | entero | |
| `name` | texto (≤ 100) | Único sin distinguir mayúsculas ni tildes |
| `slug` | texto | Se genera al crear y **no cambia** al renombrar (lo usan los atributos de los shortcodes) |
| `color` | `#RRGGBB` | Lo elige el usuario (excepción de R-01). Se guarda en mayúsculas |
| `text_tone` | `light` \| `dark` | Calculado por el backend (`Shared/Ui/ColorContrast`): `light` = texto blanco `#FFFFFF`, `dark` = texto negro `#000000`, el de mejor contraste sobre `color`. Con ese par, **cualquier** color alcanza al menos 4,58:1, así que el badge siempre cumple AA (R-02) y no hace falta avisar. La interfaz usa este valor; para la vista previa del formulario aplica la misma fórmula (`core/color.js`, casos en `tests/fixtures/color-contrast.json`) |
| `icon` | texto | Clave de `config/icons.php` (`fa-solid fa-<icon>`) |
| `requires_attachment` | booleano | Si los eventos de este tipo exigen imagen o PDF |
| `description` | texto (≤ 500) | Opcional |
| `sort_order` | entero | Orden en filtros, leyenda y selector |
| `events_count` | entero | Eventos que usan el tipo |
| `created_at`, `updated_at` | ISO 8601 con desplazamiento | Momentos de auditoría |

## Endpoints

| Método | Ruta | Permiso | Respuesta |
|---|---|---|---|
| GET | `/event-types` | `eventos_view` | `200` lista completa ordenada por `sort_order` (sin paginar: son pocos; la tabla pagina en el cliente, R-23) |
| GET | `/event-types/{id}` | `eventos_view` | `200` un tipo · `404 eventos_not_found` |
| POST | `/event-types` | `eventos_manage` | `201` el tipo creado · `422` |
| PUT | `/event-types/{id}` | `eventos_manage` | `200` el tipo actualizado · `404` · `422` |
| DELETE | `/event-types/{id}` | `eventos_manage` | `200 { "deleted": true, "id": 3 }` · `404` · `409 eventos_conflict` si tiene eventos |
| PUT | `/event-types/order` | `eventos_manage` | `200` lista en el nuevo orden · `422` si `ids` no incluye cada tipo exactamente una vez |

### Cuerpo de POST y PUT

```json
{
  "name": "Capacitaciones",
  "color": "#669F30",
  "icon": "graduation-cap",
  "requires_attachment": true,
  "description": "Formación interna y externa",
  "sort_order": 2
}
```

`sort_order` es opcional: al crear, el tipo va al final; al editar, conserva su posición.

### Reglas de validación (R-24)

Las publica el backend en `epConfig.rules.event_type` con la forma de la tabla siguiente, y la API las vuelve a aplicar al guardar.

| Campo | Reglas | Mensaje |
|---|---|---|
| `name` | obligatorio · máx. 100 · único | «El campo «Nombre» es obligatorio.» · «Ya existe un tipo de evento llamado «X».» |
| `color` | obligatorio · `#RRGGBB` | «El campo «Color» debe ser un color hexadecimal, por ejemplo #155728.» |
| `icon` | obligatorio · uno de `config/icons.php` | «Selecciona una opción válida en el campo «Ícono».» |
| `requires_attachment` | booleano | — |
| `description` | máx. 500 | «El campo «Descripción» admite máximo 500 caracteres.» |

```json
"rules": {
  "event_type": {
    "name": { "required": true, "maxLength": 100 },
    "color": { "required": true, "pattern": "^#[0-9A-Fa-f]{6}$" },
    "icon": { "required": true, "oneOf": "icons" },
    "description": { "maxLength": 500 }
  }
}
```

`"oneOf": "icons"` indica que los valores válidos son las claves de `epConfig.icons`. La unicidad del nombre solo la puede confirmar el servidor (422).

## Tipos iniciales (migración semilla)

| Orden | Nombre | Ícono | Requiere adjunto | Color |
|---|---|---|---|---|
| 1 | Cumpleaños | `cake-candles` | Sí | `#9D174D` |
| 2 | Capacitaciones | `graduation-cap` | Sí | `#155728` |
| 3 | Reuniones especiales | `star` | Sí | `#B45309` |
| 4 | Reuniones laborales | `briefcase` | No | `#1D4ED8` |

Colores aprobados por el PO en la Review del Sprint 1 (D-13); se pueden cambiar desde la pantalla. Todos llevan texto blanco con contraste ≥ 4,5:1.
