<?php
/**
 * Adjuntos permitidos en los eventos (fuente única de verdad, R-09).
 *
 * Los archivos siempre se eligen o suben en la Biblioteca de Medios de WordPress; el plugin no tiene subida
 * propia. Se expone al navegador en `window.epConfig.media` para configurar el selector `wp.media`, y el
 * backend revalida el tipo MIME del adjunto elegido.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

return [
	// Tipos MIME aceptados: imágenes y PDF.
	'allowed_mimes' => [
		'image/jpeg',
		'image/png',
		'image/webp',
		'image/gif',
		'application/pdf',
	],
	// Filtro de la Biblioteca de Medios (`library.type` de wp.media).
	'library_types' => [ 'image', 'application/pdf' ],
	// Tamaño máximo sugerido para subir desde el selector, en MB (el límite real lo fija el servidor).
	'max_upload_mb' => 10,
];
