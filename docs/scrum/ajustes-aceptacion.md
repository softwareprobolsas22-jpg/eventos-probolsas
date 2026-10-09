# Ajustes de la aceptación de la v1

> Observaciones del PO al revisar la v1 (2.0.0) en producción, 2026-10-09 (D-18: sin staging). Se atienden antes de dar por aceptada la v1; producción ya tiene la 2.0.0 instalada para las pruebas. Cada lote termina con revisión de QA, un `.zip` nuevo y la reinstalación por el PO.

## Viabilidad

| ID | Observación del PO | Causa / análisis | Viable | Rol | Pts | Lote |
|---|---|---|---|---|---|---|
| H-405 | Las tarjetas Hoy / Próximos 30 días / Total no bajan a varias filas en pantallas pequeñas | La grilla usaba `minmax(11rem, …)` y, por debajo de 576 px, tres columnas fijas (decisión de QA-060). Con el menú lateral de wp-admin, el área útil es más angosta que la ventana y las tarjetas quedaban apretadas en una fila | ✅ | F | 1 | 1 |
| H-406 | En «Ajustes» no está la sección de los shortcodes | `ShortcodeRegistry::guides()` ya describe cada shortcode (título, descripción, atributos y ejemplo), pero ninguna pantalla lo muestra | ✅ | B+F | 3 | 2 |
| H-407 | En el panel de «Nuevo tipo», al activar «Requiere adjunto» el contenido salta hacia arriba | **Defecto:** el `input` del interruptor es `position: absolute` y su etiqueta no es `position: relative`; queda anclado arriba del panel y, al recibir el foco con el clic, el navegador desplaza el panel hasta él | ✅ | F | 1 | 1 |
| H-408 | En la tabla de tipos, cambiar «Orden» por «Descripción» truncada con tooltip | El orden ya se ve en el orden de las filas y se cambia con «Ordenar»; la descripción ya llega en la API. Sigue en 5 columnas (R-19) | ✅ | F | 1 | 1 |
| H-409 | Filtro de fechas: «Desde» y «Hasta» como campos separados, tres campos por fila en escritorio, responsive | El rango era un solo grupo de dos columnas. Se separan en dos campos de la grilla y conservan el límite mutuo (uno no puede quedar después del otro) | ✅ | F | 2 | 1 |
| H-410 | El formulario de evento muestra todos los campos, incluido el adjunto, antes de elegir el tipo | Hoy el formulario es estático y solo marca el adjunto como obligatorio al elegir un tipo que lo exige. Se puede mostrar primero el tipo y después el resto según el tipo. **Decisión del PO pendiente:** en un tipo que no exige adjunto, ¿el adjunto se ofrece como opcional o no se muestra? (el modelo lo permite, R-09) | ✅ con decisión | F | 3 | 2 |
| H-411 | El campo de adjunto con arrastrar y soltar | Hoy se elige en `wp.media` (que ya permite arrastrar dentro de su ventana). Soltar el archivo directamente en el campo exige subirlo a la Biblioteca de Medios (`wp/v2/media`, el gestor necesita `upload_files`), validar imagen o PDF **antes** de guardarlo (D-4: el plugin nunca borra archivos, así que uno rechazado no debe quedar subido), mostrar el progreso y los errores | ✅ más grande | B+F | 5 | 3 |

**Lote 1 (este):** H-405, H-407, H-408 y H-409 — solo Frontend, cortos (5 pts).
**Lote 2:** H-406 y H-410 (6 pts), con la decisión del PO sobre el adjunto opcional.
**Lote 3:** H-411 (5 pts).

## Reglas que aplican

R-13 (responsive), R-16 (teclado), R-19 (≤ 6 columnas), R-21 (tooltip en textos truncados), R-24 (validación en tiempo real), R-09 y D-4 (adjuntos).
