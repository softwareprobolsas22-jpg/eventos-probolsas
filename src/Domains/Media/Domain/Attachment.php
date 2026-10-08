<?php
/**
 * Adjunto de la Biblioteca de Medios.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Media\Domain;

/**
 * Archivo de la Biblioteca de Medios asociado a un evento. El plugin no guarda archivos propios: solo el ID
 * del adjunto, y nunca lo borra (D-4).
 */
final class Attachment {

	public const KIND_IMAGE = 'image';
	public const KIND_PDF   = 'pdf';
	public const KIND_OTHER = 'other';

	/**
	 * Crea el adjunto.
	 *
	 * @param int         $id            ID del adjunto en WordPress.
	 * @param string      $mime          Tipo MIME real (según el contenido del archivo cuando se puede leer).
	 * @param string      $url           Dirección del archivo.
	 * @param string|null $thumbnail_url Miniatura (solo imágenes).
	 * @param string      $title         Título en la biblioteca.
	 * @param string      $filename      Nombre del archivo.
	 * @param int         $filesize      Tamaño en bytes (0 si no se conoce).
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $mime,
		public readonly string $url,
		public readonly ?string $thumbnail_url,
		public readonly string $title,
		public readonly string $filename,
		public readonly int $filesize
	) {}

	/**
	 * Clase de archivo: `image`, `pdf` u `other`.
	 */
	public function kind(): string {
		if ( MediaPolicy::PDF_MIME === $this->mime ) {
			return self::KIND_PDF;
		}

		return str_starts_with( $this->mime, 'image/' ) ? self::KIND_IMAGE : self::KIND_OTHER;
	}

	/**
	 * Representación de la API (`attachment` en docs/api/events.md).
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'id'            => $this->id,
			'kind'          => $this->kind(),
			'mime'          => $this->mime,
			'url'           => $this->url,
			'thumbnail_url' => $this->thumbnail_url,
			'title'         => $this->title,
			'filename'      => $this->filename,
			'filesize'      => $this->filesize,
		];
	}
}
