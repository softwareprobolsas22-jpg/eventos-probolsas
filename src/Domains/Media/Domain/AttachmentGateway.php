<?php
/**
 * Acceso a la Biblioteca de Medios.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Media\Domain;

/**
 * Lectura de adjuntos de la Biblioteca de Medios. Solo lee: el plugin nunca crea ni borra archivos (D-4);
 * subir y elegir se hace con el selector de WordPress (`wp.media`).
 */
interface AttachmentGateway {

	/**
	 * Adjunto por ID con su tipo MIME real, o null si no existe o no es un adjunto. No aplica la política
	 * de tipos permitidos: eso lo decide MediaPolicy.
	 *
	 * @param int $id ID del adjunto.
	 */
	public function find( int $id ): ?Attachment;
}
