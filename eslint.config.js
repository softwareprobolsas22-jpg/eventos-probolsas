import js from '@eslint/js';
import globals from 'globals';

/**
 * R-08 (sin desfases de fecha): patrones que en el legado mostraban el día anterior o «hoy» = mañana
 * en Colombia. Las fechas de calendario se tratan como texto `YYYY-MM-DD`; «hoy» lo da el servidor
 * (`epConfig.today`) o `core/date.js` con la zona de `epConfig.ui.timezone`.
 */
const DATE_RULES = [
	{
		selector: "CallExpression[callee.property.name='toISOString']",
		message: 'R-08: toISOString() usa UTC (en Colombia, después de las 7:00 p. m. ya es mañana). Usa core/date.js.',
	},
	{
		selector: "NewExpression[callee.name='Date'] > Literal[value=/^\\d{4}-\\d{2}-\\d{2}$/]",
		message: "R-08: new Date('YYYY-MM-DD') se interpreta en UTC y muestra el día anterior en Colombia. Usa core/date.js.",
	},
	{
		selector: "CallExpression[callee.object.name='Date'][callee.property.name='parse']",
		message: 'R-08: Date.parse() depende de la zona del equipo. Usa core/date.js.',
	},
	{
		selector: "CallExpression[callee.property.name='getTimezoneOffset']",
		message: 'R-08: la zona es la de Colombia (epConfig.ui.timezone), no la del equipo del usuario.',
	},
];

/** R-14: sin HTML ni manejadores en línea; R-15: el contenido dinámico se inserta como texto. */
const DOM_RULES = [
	{
		selector: "AssignmentExpression[left.property.name='innerHTML']",
		message: 'R-15: usa textContent o los helpers de core/dom.js; innerHTML con datos permite XSS.',
	},
	{
		selector: "CallExpression[callee.property.name='insertAdjacentHTML']",
		message: 'R-15: usa los helpers de core/dom.js; insertAdjacentHTML con datos permite XSS.',
	},
];

export default [
	{
		ignores: [ 'assets/dist/**', 'vendor/**', 'node_modules/**', 'legacy/**', 'coverage/**' ],
	},
	js.configs.recommended,
	{
		files: [ 'assets/src/**/*.js' ],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'module',
			globals: { ...globals.browser },
		},
		rules: {
			'no-restricted-syntax': [ 'error', ...DATE_RULES, ...DOM_RULES ],
			// R-14: los avisos usan toasts y diálogos propios.
			'no-alert': 'error',
		},
	},
	{
		// Herramientas de línea de comandos (CI): escriben en la salida estándar.
		files: [ 'tools/**/*.mjs' ],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'module',
			globals: { ...globals.node },
		},
	},
	{
		files: [ '*.config.js', 'tests/e2e/**/*.js' ],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'module',
			globals: { ...globals.node },
		},
	},
	{
		// Las pruebas de componentes corren en un DOM simulado (happy-dom).
		files: [ 'tests/js/**/*.js' ],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'module',
			globals: { ...globals.node, ...globals.browser },
		},
	},
	{
		rules: {
			eqeqeq: 'error',
			'no-var': 'error',
			'prefer-const': 'error',
			'no-console': [ 'error', { allow: [ 'warn', 'error' ] } ],
		},
	},
	{
		// Va al final para prevalecer sobre la regla general de no-console.
		files: [ 'tools/**/*.mjs' ],
		rules: {
			'no-console': 'off',
		},
	},
];
