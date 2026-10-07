# Eventos Probolsas

Plugin de WordPress con el calendario de eventos de la intranet de Probolsas: cumpleaños, capacitaciones y reuniones. Solo lo ven los usuarios con sesión iniciada y solo lo gestionan, desde wp-admin, quienes tienen el permiso `eventos_manage`.

- **Análisis, decisiones, reglas y backlog:** [`docs/scrum/00-analisis-sm.md`](docs/scrum/00-analisis-sm.md)
- **Contrato de la API:** [`docs/api/README.md`](docs/api/README.md)
- **Decisiones de arquitectura:** [`docs/adr/`](docs/adr/)
- **Revisiones de QA:** [`docs/qa/`](docs/qa/)

## Requisitos

| Herramienta | Versión | Para qué |
|---|---|---|
| PHP | 8.3 (producción) | Ejecutar el plugin y las herramientas de PHP |
| Composer | 2 | Herramientas de PHP (el plugin no tiene dependencias PHP en producción) |
| Node | 20.19 o superior (22 recomendado) | Compilar los assets y las pruebas JS |
| Docker Desktop | — | Solo para las pruebas de integración (`wp-env`), que se corren al cierre de cada fase |

## Instalación

```bash
composer install
npm ci
npm run build
```

### Ajustes locales conocidos

**PHP local más antiguo que 8.3.** El proyecto exige PHP 8.3 y Composer bloquea la ejecución si la versión local es menor. Para correr las pruebas unitarias mientras se actualiza el PHP local:

```bash
composer dump-autoload --ignore-platform-req=php
```

Solo modifica `vendor/` (que no se versiona). La verificación oficial en 8.3 y 8.4 la hace el CI.

**PHPCS no reconoce el estándar WordPress.** Si `vendor/bin/phpcs -i` no lista `WordPress-Extra` (puede pasar en Windows si `composer install` se interrumpe), regístralos a mano:

```bash
vendor/bin/phpcs --config-set installed_paths "../../phpcsstandards/phpcsutils,../../phpcsstandards/phpcsextra,../../wp-coding-standards/wpcs,../../phpcompatibility/php-compatibility,../../phpcompatibility/phpcompatibility-paragonie,../../phpcompatibility/phpcompatibility-wp"
```

**Zonas horarias en Windows.** Node solo respeta `TZ=UTC`; las pruebas de fechas en otras zonas (Tokio, Kiritimati) las corre el CI.

## Comandos

| Comando | Qué hace |
|---|---|
| `composer lint` | PHPCS (WordPress-Extra y compatibilidad con PHP 8.3) |
| `composer analyse` | PHPStan nivel 6 sobre el código y nivel 2 sobre las pruebas de integración |
| `composer test` | Pruebas unitarias de PHP (sin WordPress, con Brain Monkey) |
| `npm run lint` | ESLint (R-08, R-14, R-15) y Stylelint (R-01, R-03) |
| `npm run build` | Compila `assets/src` en `assets/dist` con Vite |
| `npm run dev` | Compilación continua con sourcemaps |
| `npm test` | Pruebas JS (Vitest): fechas, configuración, widgets, CSS compilado, contraste y peso |
| `npm run test:coverage` | Pruebas JS con cobertura (reporte en `coverage/`) |
| `npm run env:start` / `env:stop` | Inicia o detiene WordPress en Docker (`wp-env`, PHP 8.3) |
| `npm run test:php:integration` | Pruebas de integración dentro de WordPress (requiere `env:start`) |

## Reglas para contribuir

1. **Una rama por historia:** `feature/H-xxx-descripcion`, creada desde `develop`.
2. **Backend y Frontend no se cruzan:** Backend trabaja en `src/`, `config/` y `tests/php/`; Frontend en `templates/`, `assets/` y `tests/js/`. El único punto de contacto es el contrato de `docs/api/`.
3. **`assets/dist` se versiona** ([ADR-0001](docs/adr/0001-assets-compilados-y-bootstrap-encapsulado.md)): quien cambie `assets/src` ejecuta `npm run build` y sube el resultado en el mismo commit. El CI falla si no coinciden.
4. **Antes de pedir revisión:** `composer lint`, `composer analyse`, `composer test`, `npm run lint` y `npm test` sin errores.
5. **Revisión de QA:** cada historia la revisa QA (informe en `docs/qa/`). Si se aprueba, se integra en `develop` sin PR.
6. **Un PR por fase:** al cierre de cada fase, QA revisa la fase completa (incluidas las pruebas de integración) y se abre un único PR `develop` → `main`. Nunca se abre un PR sin revisión de QA aprobada.

## Publicación

El plugin se publica desde GitHub en Hostinger; el servidor no compila nada. El paquete contiene solo lo necesario (ver `.gitattributes`): `eventos-probolsas.php`, `uninstall.php`, `config/`, `src/`, `templates/` y `assets/dist/`. Para revisarlo:

```bash
git archive HEAD | tar -t
```
