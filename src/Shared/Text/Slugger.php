<?php
/**
 * Generación de slugs.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Shared\Text;

use LengthException;

/**
 * Genera slugs a partir del nombre que escribe el usuario. El usuario nunca escribe el slug.
 *
 * Ejemplos: «Gestión de Calidad» → `gestion-de-calidad`; si ya existe → `gestion-de-calidad-2`.
 */
final class Slugger {

	/**
	 * Slug usado cuando el nombre no contiene letras ni números.
	 */
	public const FALLBACK = 'sin-nombre';

	/**
	 * Intentos máximos para encontrar un slug libre (protege contra un bucle infinito).
	 */
	private const MAX_ATTEMPTS = 1000;

	/**
	 * Crea el generador.
	 *
	 * @param TextNormalizer $normalizer Normalizador de texto.
	 * @param int            $max_length Longitud máxima (la de la columna `slug`).
	 */
	public function __construct(
		private readonly TextNormalizer $normalizer,
		private readonly int $max_length = 170
	) {}

	/**
	 * Slug ASCII de un texto: minúsculas, sin tildes, ñ → n y guiones como separador.
	 *
	 * @param string $text Texto original.
	 */
	public function slugify( string $text ): string {
		$slug = str_replace( 'ñ', 'n', $this->normalizer->normalize( $text ) );
		$slug = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', $slug ), '-' );
		$slug = rtrim( substr( $slug, 0, $this->max_length ), '-' );

		return '' === $slug ? self::FALLBACK : $slug;
	}

	/**
	 * Slug que todavía no existe, agregando `-2`, `-3`, … si hace falta.
	 *
	 * @param string                 $text   Texto original.
	 * @param callable(string): bool $exists Indica si un slug ya está en uso.
	 *
	 * @throws LengthException Si no se encuentra un slug libre tras muchos intentos.
	 */
	public function unique( string $text, callable $exists ): string {
		$base = $this->slugify( $text );

		if ( ! $exists( $base ) ) {
			return $base;
		}

		for ( $number = 2; $number <= self::MAX_ATTEMPTS; $number++ ) {
			$suffix    = '-' . $number;
			$candidate = rtrim( substr( $base, 0, $this->max_length - strlen( $suffix ) ), '-' ) . $suffix;

			if ( ! $exists( $candidate ) ) {
				return $candidate;
			}
		}

		throw new LengthException( 'No se encontró un slug disponible.' );
	}
}
