<?php
/**
 * Biblioteca de Medios en memoria para las pruebas unitarias.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Support;

use Probolsas\Eventos\Domains\Media\Domain\Attachment;
use Probolsas\Eventos\Domains\Media\Domain\AttachmentGateway;

/**
 * Adjuntos de prueba por ID.
 */
final class InMemoryAttachmentGateway implements AttachmentGateway {

	/**
	 * Adjuntos por ID.
	 *
	 * @var array<int, Attachment>
	 */
	public array $attachments = [];

	/**
	 * Agrega un adjunto.
	 *
	 * @param int    $id   ID.
	 * @param string $mime Tipo MIME real.
	 */
	public function add( int $id, string $mime ): Attachment {
		$extension                = 'application/pdf' === $mime ? 'pdf' : 'jpg';
		$this->attachments[ $id ] = new Attachment( $id, $mime, "https://intranet.test/uploads/archivo-{$id}.{$extension}", null, "archivo-{$id}", "archivo-{$id}.{$extension}", 1024 );

		return $this->attachments[ $id ];
	}

	/**
	 * Adjunto por ID.
	 *
	 * @param int $id ID.
	 */
	public function find( int $id ): ?Attachment {
		return $this->attachments[ $id ] ?? null;
	}
}
