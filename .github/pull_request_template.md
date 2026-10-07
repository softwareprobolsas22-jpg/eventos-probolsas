<!-- Un PR por fase: develop → main, solo después de la revisión de QA de la fase. -->

## Fase

<!-- Ejemplo: Sprint 1 — «Los tipos de evento se gestionan y la UI tiene identidad». -->

## Historias incluidas

| Historia | Rama | Informe de QA |
|---|---|---|
| H-xxx | `feature/H-xxx-…` | `docs/qa/…` |

## Reglas transversales tocadas

<!-- R-01 … R-24 (docs/scrum/00-analisis-sm.md §7). -->

## Definition of Done

- [ ] Todas las historias tienen revisión de QA **aprobada** (informes enlazados arriba).
- [ ] Revisión de QA de la fase aprobada, con las pruebas de integración (`wp-env`) ejecutadas.
- [ ] CI en verde (PHP 8.3/8.4, JS Node 20/22, zonas horarias, integración).
- [ ] Cobertura ≥ 80 % en `src/Shared` y `src/Domains`.
- [ ] `assets/dist` corresponde a `assets/src`.
- [ ] Sin hallazgos críticos ni altos abiertos.
- [ ] Contrato (`docs/api/`) y documentación actualizados.
- [ ] Textos en es-CO y preparados para traducción.

## Hallazgos abiertos que pasan a la siguiente fase

<!-- ID, severidad, responsable y por qué no bloquea. -->

## Evidencia

<!-- Enlace a la ejecución del CI, capturas por breakpoint (360 / 768 / 1024 / 1440 px) si hay UI. -->
