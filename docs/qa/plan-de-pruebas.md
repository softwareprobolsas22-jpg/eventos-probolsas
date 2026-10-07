# Plan de pruebas — Eventos Probolsas v2

> Responsable: QA · H-007 · Versión 1 (2026-10-07). Se actualiza al cerrar cada fase. Reglas R-xx y decisiones D-x en [`docs/scrum/00-analisis-sm.md`](../scrum/00-analisis-sm.md).

## 1. Alcance y niveles

| Nivel | Herramienta | Qué cubre | Cuándo corre |
|---|---|---|---|
| Unitario PHP | PHPUnit 9 + Brain Monkey | Dominio, servicios, validación, fechas, núcleo | Local en cada cambio y CI en cada push |
| Unitario y de componentes JS | Vitest + happy-dom | `core/` (fechas, configuración, i18n), componentes de UI, pantallas | Local y CI |
| CSS compilado | Vitest sobre `assets/dist` | Reglas transversales que el DOM simulado no aplica: R-03, R-05, encapsulación de Bootstrap, contraste, peso | Local y CI |
| Estático | PHPCS, PHPStan, ESLint, Stylelint | Seguridad, estilo, compatibilidad, R-01, R-03, R-08, R-14, R-15 | Local y CI |
| Integración | PHPUnit dentro de WordPress 7.1.3 (`wp-env`, versión de producción fijada) | API REST real, permisos, migraciones, ciclo de vida | CI en cada push; en local **al cierre de cada fase** (decisión del PO) |
| Exploratorio y visual | Navegador real (Chrome, Edge, Firefox; Safari iOS para móvil) | Flujos completos, responsive (360/768/1024/1440 px), accesibilidad con teclado y lector, axe | En la revisión de cada historia con UI y al cierre de fase |
| Aceptación | PO en staging (WP 7.1.3, PHP 8.3) | Criterios de aceptación de las historias | Review de cada sprint y H-404 |

**Fuera de alcance v1:** pruebas de carga y E2E automatizadas en navegador. Se reevalúan en H-403 si el exploratorio no alcanza.

## 2. Entornos

| Entorno | Datos | Uso |
|---|---|---|
| Local (Windows, PHP 8.2 con ajuste de plataforma, Node 20) | Fixtures | Desarrollo y revisión de QA por historia |
| CI (Ubuntu 24.04, PHP 8.3/8.4, Node 20/22/24) | Fixtures + WordPress de `wp-env` | Verificación oficial de la DoD |
| `wp-env` local (Docker, PHP 8.3) | WordPress limpio | Cierre de fase |
| Staging (Hostinger) | Copia de la intranet | Aceptación del PO |

## 3. Severidad de los hallazgos

| Severidad | Criterio | Efecto |
|---|---|---|
| Crítica | Pérdida de datos, fallo de seguridad o permisos, el plugin no carga | Bloquea la historia y la fase |
| Alta | Una regla R-xx o un criterio de aceptación no se cumple en un caso común; defecto del legado que reaparece | Bloquea la historia |
| Media | Funciona, pero con una carencia de calidad (pruebas, documentación, caso poco común) | Se corrige antes de cerrar la fase |
| Baja | Detalle menor sin impacto en el uso | Se agenda en una historia posterior |
| Info | Observación o riesgo a vigilar | Se registra |

## 4. Criterios de entrada y salida

**Entrada a revisión de una historia:** rama publicada, CI en verde, criterios de aceptación y reglas R-xx identificados.

**Salida (aprobación de la historia):** criterios de aceptación verificados, reglas R-xx aplicables verificadas, sin hallazgos críticos ni altos abiertos, informe en `docs/qa/`.

**Cierre de fase:** todas las historias aprobadas; pruebas de integración ejecutadas en `wp-env` local; hallazgos medios cerrados o aceptados por el PO; regresión del legado (§6) en verde para lo que ya existe; informe de fase y PR único `develop` → `main` con la plantilla.

## 5. Matriz de trazabilidad de las reglas

Estado: ✅ verificación automática activa · 🟡 parcial (falta el componente o la pantalla que la usa) · ⏳ pendiente de la historia indicada.

| Regla | Verificación | Dónde | Estado |
|---|---|---|---|
| R-01 Paleta | Stylelint `color-no-hex` fuera de `tokens/`; `contrast.test.js` lee los tokens; Bootstrap con `--bs-primary: #155728` | `.stylelintrc.json`, `tests/js/a11y/`, `tests/js/build/layout-css.test.js` | ✅ |
| R-02 Contraste | Pares de tokens ≥ 4,5:1 y ≥ 3:1; `#669F30` solo en íconos; `text_tone` del tipo | `contrast.test.js`; aviso de contraste en el formulario de tipos | 🟡 H-101/H-104 |
| R-03 `resize: none` | Stylelint `resize` solo `none`; CSS compilado en las tres hojas | `layout-css.test.js` | ✅ |
| R-04 Drag to scroll | Prueba de componente (umbral, clic cancelado, campos ignorados) | `tests/js/ui/drag-scroll.test.js` | ⏳ H-103 |
| R-05 `th` centrados | CSS compilado en las tres hojas | `layout-css.test.js` | ✅ |
| R-06 Acciones primero y fija | Prueba de componente `data-table` | `tests/js/ui/data-table.test.js` | ⏳ H-103 |
| R-07 Formato es-CO | Fixtures compartidos PHP/JS (auditoría, calendario, 12 h, «hoy») | `DateFormatterTest.php`, `date.test.js` | ✅ |
| R-08 Sin desfases | ESLint (4 patrones) con prueba de la regla; fechas en 4 zonas en el CI; `today` del servidor | `date-rules.test.js`, CI `timezones`, `AssetsTest.php` | ✅ (FullCalendar: ⏳ H-302) |
| R-09 Imagen o PDF desde la biblioteca | `config/media.php`; validación del MIME real en el servidor; `wp.media` filtrado | `ConfigTest.php`; integración de la API | 🟡 H-202/H-204 |
| R-10 Font Awesome | Los 56 íconos de `config/icons.php` existen en el CSS compilado; solo la fuente sólida | `IconCatalogTest.php`, `budget.test.js` | ✅ |
| R-11 FullCalendar | Revisión de código y exploratorio | — | ⏳ H-302 |
| R-12 Animaciones ligeras | `prefersReducedMotion`; tokens de duración 150–250 ms | `timing.test.js`; exploratorio con reducción de movimiento | 🟡 H-103 |
| R-13 Responsive | Exploratorio en 360/768/1024/1440 px | Informe de cada historia con UI | ⏳ H-103 en adelante |
| R-14 Sin `<script>`/`<style>`/`alert` | ESLint `no-alert`; búsqueda en plantillas | `date-rules.test.js`; revisión | ✅ |
| R-15 Seguridad | PHPCS (escape, sanitización, `prepare`); ESLint sin `innerHTML`; permisos en integración | `RestControllerTest.php`, `LifecycleTest.php` | ✅ base · 🟡 por endpoint |
| R-16 Accesibilidad | axe-core sobre los componentes; teclado y lector en exploratorio | `tests/js/a11y/axe.test.js` | ⏳ H-103 |
| R-17 Rendimiento | Presupuesto de CSS y JS iniciales; feed por rango; paginación en servidor | `budget.test.js`; integración de la API | ✅ base |
| R-18 Diseño sobrio | Revisión SM + PO en la Review | Review del sprint | ⏳ H-103 |
| R-19 ≤ 6 columnas | Prueba de cada pantalla con tabla | `tests/js/screens/*.test.js` | ⏳ H-104/H-203 |
| R-20 Toast en cada acción | Prueba de cada pantalla | `tests/js/screens/*.test.js` | ⏳ H-103 en adelante |
| R-21 Tooltips y truncado | Prueba de componente | `tests/js/ui/tooltip.test.js` | ⏳ H-103 |
| R-22 Acceso | Anónimo sin datos (401), sin permiso (403), `eventos_view` dinámico | `CapabilitiesTest.php`, `LifecycleTest.php`, integración de cada endpoint | ✅ base |
| R-23 Paginación 25/50/100 | `config/ui.php`; API rechaza otros tamaños; componente responsive | `ConfigTest.php`; `pagination.test.js` | 🟡 H-103/H-201 |
| R-24 Validación en tiempo real | Reglas publicadas por el backend; componente de formulario | `epConfig.rules`; `form.test.js` | ⏳ H-101/H-103 |

## 6. Regresión de los defectos del legado

Cada caso se automatiza en la historia indicada y se vuelve a ejecutar al cierre de cada fase.

| ID | Defecto del legado | Caso de prueba | Historia | Estado |
|---|---|---|---|---|
| RL-01 | Clic en el día 7 mostraba los eventos del día 6 | Con el proceso en `Pacific/Kiritimati` y en `America/Bogota`, un evento del 2026-10-07 aparece en la celda del 7 y su detalle dice «07/10/2026» | H-302, H-305 | Base ✅ (`date.test.js`) · calendario ⏳ |
| RL-02 | «Hoy» era mañana después de las 7:00 p. m. | Con la hora simulada 2026-10-07 20:30 en Bogotá: `epConfig.today`, el filtro «Hoy» y el resaltado de FullCalendar marcan el 7 | H-302, H-305 | Base ✅ (`AssetsTest.php`, `date.test.js`) · calendario ⏳ |
| RL-03 | `.ics` corrido 5 horas y descarga rota | Un evento a las 3:00 p. m. produce `DTSTART;TZID=America/Bogota:20261007T150000`; la descarga funciona con sesión | H-301 | ⏳ |
| RL-04 | «Subir imagen» no asociaba el archivo | Elegir o subir en la biblioteca → el evento guarda `attachment_id` y lo muestra | H-202, H-204 | ⏳ |
| RL-05 | No se podían crear eventos en una instalación limpia | Activar en WordPress limpio → crear un evento de cada tipo | H-102, H-201 (integración) | ⏳ |
| RL-06 | Grilla desalineada con la semana iniciando en lunes | Con `start_of_week` = 1 y = 0, el encabezado y las celdas coinciden | H-302 | Base ✅ (`epConfig.firstDay`) · calendario ⏳ |
| RL-07 | Borrar un evento borraba el archivo de la biblioteca | Eliminar un evento con adjunto → el adjunto sigue en la biblioteca; desinstalar → también | H-201 | Desinstalación ✅ (`LifecycleTest.php`) · evento ⏳ |
| RL-08 | Calendario inicializado dos veces e IDs duplicados | Dos shortcodes en una página → cada uno hace una sola petición y no hay IDs repetidos | H-302 | ⏳ |

## 7. Lista de verificación exploratoria (historias con UI)

1. Recorrer los criterios de aceptación con teclado solamente (Tab, Enter, Esc, flechas).
2. Repetir en 360, 768, 1024 y 1440 px: sin scroll horizontal de página; las tablas se arrastran.
3. Activar «reducir movimiento» en el sistema: sin animaciones.
4. Lector de pantalla (NVDA): nombres de botones de solo ícono, mensajes de error asociados a cada campo, toasts anunciados.
5. Datos límite: títulos de 150 caracteres, descripciones de 2.000, tipos con colores claros y oscuros, eventos sin hora, eventos de hoy y del pasado.
6. Sesiones: visitante anónimo, suscriptor, editor sin permiso, administrador.
7. Errores de red: desconectar la red al guardar → toast de error y datos del formulario conservados.

## 8. Registro

| Fase | Informe de cierre | PR |
|---|---|---|
| Sprint 0 | `docs/qa/` (al cerrar) | `develop` → `main` |
