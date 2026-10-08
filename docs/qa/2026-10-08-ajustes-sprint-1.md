# Revisión de QA — Ajustes de la Review del Sprint 1 (QA-033, QA-029)

| Campo | Valor |
|---|---|
| Rama | `feature/S1-ajustes-qa` (commit `1a9990f`, sobre `main` = `904d135`) |
| CI | Pendiente del push. El cambio solo toca `assets/`, `tools/`, `vite.config.js` y `tests/js/`; las verificaciones locales cubren los trabajos de JS y la prueba PHP de íconos |
| Fecha | 2026-10-08 |
| Revisó | QA |
| Veredicto | ✅ **Aprobado** |

## 1. Hallazgos atendidos

| ID | Corrección | Verificación | Estado |
|---|---|---|---|
| QA-033 | El build conserva de la tabla de Font Awesome (2.001 reglas `.fa-<ícono> { --fa: … }`) solo los íconos del código y de `config/icons.php` (`tools/used-icons.mjs`). Las utilidades que declaran otras variables (`.fa-fw`, `.fa-width-auto`, `.fa-spin-reverse`) se conservan; la primera versión del plugin las quitaba y se corrigió antes del commit. | CSS compartido 40,1 → 26,1 KB y CSS inicial de wp-admin **44,9 → 30,9 KB** gzip (presupuesto 45). `tests/js/build/icons.test.js`: no falta ninguno de los 80 íconos usados ni sobra ninguno; las clases de estilo y utilidad siguen. `IconCatalogTest.php`: los 56 íconos del catálogo están en el CSS. Capturas: íconos de acciones, paginación, badges, selector y encabezado visibles | ✅ Cerrado |
| QA-029 | Por debajo de 576 px, «Mostrando x–y de z» se oculta a la vista con el patrón `visually-hidden` y sigue siendo la región `aria-live` que anuncia el cambio de página. Queda el selector 25/50/100 y anterior / «Página x de y» / siguiente (R-23). | `layout-css.test.js` (oculto a la vista, nunca `display: none`); captura a 360 px | ✅ Cerrado |

## 2. Verificaciones

| Verificación | Resultado |
|---|---|
| Suite JS | ✅ 27 archivos, 267 pruebas |
| ESLint y Stylelint | ✅ 0 errores |
| Build y `assets/dist` en el commit | ✅ |
| `IconCatalogTest.php` | ✅ 3 pruebas |

## 3. Observación

| ID | Sev. | Observación | Acción |
|---|---|---|---|
| QA-034 | Info | Un ícono nuevo escrito en el código no aparece hasta volver a compilar. El CI lo detecta porque compara `assets/dist` con un build limpio, y `icons.test.js` falla si falta. | Ninguna: es el mismo flujo de ADR-0001 |

**Veredicto:** ✅ aprobado. Se integra en `develop`.
