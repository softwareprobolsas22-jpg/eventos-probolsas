# Sprint 3 — «Los colaboradores ven el calendario sin desfases»

> Planning: 2026-10-09 · Velocidad: Sprint 1 = 27, Sprint 2 = 35 (32 + 3 de reserva) · Compromiso: 26 puntos · Contrato: [`docs/api/events.md`](../api/events.md) (sección «Calendario»).

## Objetivo

Al terminar el sprint, un colaborador con sesión abre la página de la intranet que tiene `[eventos_calendario]` y ve los eventos del mes en su día y a su hora de Colombia, aunque su equipo esté en otra zona horaria; filtra por tipo, abre el detalle de un evento, pasa a los demás eventos del mismo día y lo agrega a su calendario personal con un `.ics` que conserva la hora. `[eventos_proximos]` muestra lo que viene. Un visitante sin sesión solo ve el aviso para iniciar sesión.

## Alcance acordado

- Las 5 historias de EP-3 (26 puntos), por debajo de la velocidad de los dos sprints anteriores: el margen se reserva para lo que el calendario tiene de nuevo (FullCalendar, Tom Select, `.ics`) y para las pruebas en el navegador sobre WordPress real.
- **Las clases PHP de los shortcodes son del Backend** (§6.4: `src/` es suyo). H-301 entrega `[eventos_calendario]` y `[eventos_proximos]` registrados con `ShortcodeRegistry` y pintados con `WidgetRenderer` (que ya muestra el aviso de inicio de sesión a los anónimos, D-1); H-302 y H-304 montan los widgets `calendar` y `upcoming` en el navegador.
- **QA-005** se cierra en H-302: si la página queda abierta después de la medianoche, «hoy» se recalcula con `Intl` en la zona de Bogotá.
- Sin eventos relacionados (D-6). Producción solo al terminar v1 (D-14).

## Contrato que se completa en este sprint

`docs/api/events.md` ya define `/calendar`, `/events/{id}` con `same_day`, `/events/{id}/ics` y `/upcoming`. El Backend agrega en H-301, sin cambiar lo acordado:

| Tema | Propuesta del SM (la cierra el Backend en el contrato) |
|---|---|
| Respuesta de `/upcoming` | La misma representación de un evento que `GET /events/{id}`, sin `same_day` |
| Duración en el `.ics` de un evento con hora y sin fin (v1) | `DTEND` una hora después del inicio, la duración por defecto de Google Calendar y Outlook; día completo: `DTEND;VALUE=DATE` del día siguiente (§5.6) |
| Descarga del `.ics` | Enlace con `?_wpnonce=…`, igual que el CSV (un enlace no envía `X-WP-Nonce`) |
| Atributos de los shortcodes | `[eventos_calendario tipos="cumpleanos,capacitaciones"]` · `[eventos_proximos limite="5" tipos="…" titulo="…"]`. `tipos` usa los *slugs* (fijos aunque se renombre el tipo); los desconocidos se ignoran. Los widgets reciben los IDs ya resueltos en `data-ep-props` |

## Historias y tareas

| Orden | Historia | Rama | Tareas | Depende de |
|---|---|---|---|---|
| 1 | **H-301** Feed, `.ics`, próximos y shortcodes (B, 5) | `feature/H-301-calendario-backend` | `GET /calendar`: `start` y `end` (`YYYY-MM-DD`, fin exclusivo de FullCalendar) y `types[]`; rango por **solapamiento** (§5.5); más de 62 días, fin no posterior al inicio o fecha inválida → 422 en `start`/`end`; `EventInput` con `start`/`end` sin zona, `allDay`, color del tipo y `textColor` según `text_tone`, `extendedProps` (`typeId`, `icon`, `hasAttachment`) · `GET /events/{id}/ics`: `text/calendar`, `VTIMEZONE` de `America/Bogota`, `DTSTART;TZID=America/Bogota:…` con hora, `DTSTART;VALUE=DATE` sin hora, texto escapado y líneas plegadas (RFC 5545) · `GET /upcoming`: desde hoy en Colombia, `limit` 1–20 (5 por defecto, otro valor → 422), `types[]` · todas con `eventos_view` (anónimo 401) · `CalendarShortcode` y `UpcomingShortcode` con su guía · pruebas unitarias e integración (RL-03) | — |
| 2 | **H-302** `[eventos_calendario]` (F, 8) | `feature/H-302-calendario` | FullCalendar 6 (`core`, `daygrid`, `list`, `interaction`) **cargado bajo demanda** con el widget · vistas mes y lista (lista por defecto en móvil), `locale: 'es'`, `firstDay` de `epConfig` (RL-06) · `timeZone: 'UTC'` y `now` con la hora de Bogotá de `core/date.js` (R-08, RL-02) · **QA-005**: recalcular «hoy» al volver a la pestaña y al pasar la medianoche de Bogotá · color, ícono y `text_tone` por tipo · filtro de tipos con Tom Select (badges, teclado) · el número del día lleva a la lista de ese día (RL-01) · estados de carga, vacío y error con toast · una petición por rango y sin IDs repetidos con dos calendarios en la página (RL-08) · presupuesto de peso: entrada pública dentro del límite y presupuesto propio para el chunk del calendario (R-17) | H-301 (contrato) |
| 3 | **H-303** Modal de detalle (F, 5) | `feature/H-303-modal-evento` | Diálogo accesible (foco atrapado, `Esc`, devuelve el foco) con `GET /events/{id}`: tipo, fecha larga y hora es-CO, descripción con saltos de línea, imagen ampliable o PDF (abrir y descargar) · navegación anterior/siguiente entre los eventos del mismo día con `same_day` («2 de 3») · «Añadir a mi calendario» con el `.ics` · reutiliza las piezas del detalle de wp-admin (H-205) donde aplique · sin eventos relacionados (D-6) | H-301, H-302 |
| 4 | **H-304** `[eventos_proximos]` (F, 3) | `feature/H-304-proximos` | Lista con fecha (día y mes), hora o «Todo el día», título truncado con tooltip y badge del tipo · `limite`, `tipos` y `titulo` del shortcode · estado vacío · abre el mismo modal de H-303 | H-301, H-303 |
| 5 | **H-305** Pruebas del calendario (Q, 5) | revisión de QA de cada rama | Zonas horarias: UTC, Bogotá, Tokio y Kiritimati, y el cambio de día 23:59 → 00:00 en Bogotá con reloj simulado · pruebas en el navegador sobre WordPress real (página con los shortcodes, zona del navegador distinta a la de Colombia, modal, `.ics`, dos calendarios, visitante anónimo) · responsive 360/768/1024/1440 px, teclado y axe · regresión RL-01, RL-02, RL-03, RL-06 y RL-08 | H-301 a H-304 |

Frontend puede empezar H-302 en paralelo con H-301 contra el contrato (la API se simula en las pruebas JS y en las capturas). Las pruebas en el navegador del calendario necesitan los shortcodes de H-301 en `develop`.

## Criterios de aceptación del sprint

1. Un colaborador con sesión ve el calendario en vista de mes y de lista, en español, con la semana iniciando según WordPress; en móvil empieza en la lista (R-11, RL-06).
2. Un evento del 7 de octubre a las 3:00 p. m. aparece en la celda del 7 y dice «03:00 p. m.» con el navegador en UTC, Bogotá, Tokio o Kiritimati; el número del día 7 abre los eventos del 7 (R-07, R-08, RL-01).
3. «Hoy» es el día de Bogotá también después de las 7:00 p. m., y cambia solo al pasar la medianoche de Bogotá sin recargar la página (RL-02, QA-005).
4. Cada evento lleva el color y el ícono de su tipo con texto legible (R-02); el filtro de tipos usa Tom Select y vuelve a pedir el rango visible.
5. El modal muestra la fecha y hora es-CO, la descripción y el adjunto (imagen ampliable o PDF), permite pasar a los demás eventos del mismo día y descarga un `.ics` que abre a la hora correcta en Google Calendar y Outlook (RL-03, D-6).
6. `[eventos_proximos]` lista los próximos eventos desde hoy en Colombia, con `limite` de 1 a 20.
7. Un visitante sin sesión ve el aviso de inicio de sesión y la API le responde 401; el HTML no lleva datos de eventos (D-1, R-22).
8. Con dos shortcodes en la misma página, cada widget hace una sola petición por rango y no hay IDs repetidos (RL-08).
9. Los assets públicos se cargan solo donde hay un shortcode, la carga inicial respeta el presupuesto y FullCalendar se descarga aparte (R-17).
10. 360, 768, 1024 y 1440 px sin scroll horizontal de página; todo con teclado; animaciones de 150–250 ms que respetan la reducción de movimiento (R-12, R-13, R-16).

## Riesgos del sprint

| Riesgo | Mitigación |
|---|---|
| Con `timeZone: 'UTC'`, el «hoy» de FullCalendar sería la fecha UTC (mañana después de las 7:00 p. m.) | `now` con la hora de Bogotá de `core/date.js`, recalculado al volver a la pestaña y a medianoche (QA-005); pruebas en 4 zonas y con reloj simulado |
| FullCalendar envía `start`/`end` con hora y zona si se usa su fuente JSON | Fuente como función: el widget arma `start`/`end` en `YYYY-MM-DD` desde la fecha UTC que entrega FullCalendar; el Backend responde 422 a cualquier otro formato |
| Peso de FullCalendar y Tom Select en la intranet | Chunk cargado bajo demanda solo con el widget del calendario; presupuesto propio en `budget.test.js`; íconos de Font Awesome recortados (QA-033) |
| FullCalendar 6 inyecta su CSS en tiempo de ejecución y el tema de la intranet puede pisarlo | Variables `--fc-*` y reglas propias bajo `.ep-public`; revisión visual con capturas en Chrome sin interfaz y en el navegador del CI |
| Compatibilidad del `.ics` entre Google, Outlook y Apple | RFC 5545 estricto (CRLF, plegado a 75 octetos, escape de `,` `;` `\`), `VTIMEZONE` explícito; el PO lo comprueba en sus calendarios al cerrar v1 |
| Sin WordPress local (5,9 GB de RAM) | Las pruebas en el navegador corren en el CI; las capturas locales usan la API simulada |
