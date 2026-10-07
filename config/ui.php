<?php
/**
 * Configuración de interfaz compartida entre PHP y JS (fuente única de verdad).
 *
 * Se expone al navegador en `window.epConfig.ui`. El JS nunca redefine estos valores.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

return [
	'timezone'          => 'America/Bogota',
	'locale'            => 'es-CO',
	// Formatos con la sintaxis de PHP (d, m, Y, h, H, i). El JS interpreta los mismos códigos.
	'date_format'       => 'd/m/Y',
	// 12 h (decisión del PO): `03:00 p. m.`.
	'time_format'       => 'h:i',
	// a. m. / p. m. explícitos: el formato no depende del idioma configurado en WordPress.
	'meridiem'          => [
		'am' => 'a. m.',
		'pm' => 'p. m.',
	],
	// Registros por página de todas las tablas (R-23).
	'page_sizes'        => [ 25, 50, 100 ],
	'default_page_size' => 25,
	// Separador del CSV exportado: Excel en español (Colombia) usa «;» como separador de listas,
	// porque la coma es el separador decimal. Con «,» abriría todo en una sola columna.
	'csv_separator'     => ';',
];
