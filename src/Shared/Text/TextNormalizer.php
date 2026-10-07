<?php
/**
 * Normalización de texto para búsquedas y comparaciones.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Shared\Text;

use Normalizer;

/**
 * Convierte un texto a su forma comparable: minúsculas, sin tildes ni diéresis y con los espacios colapsados.
 * La ñ se conserva porque en español es una letra propia (`ano` ≠ `año`).
 *
 * Es la versión PHP de `normalizeText()` (assets/src/js/core/search.js). Ambas comparten casos de prueba
 * (tests/fixtures/text-normalization.json) y deben dar siempre el mismo resultado.
 */
final class TextNormalizer {

	/**
	 * Marcador temporal (área de uso privado de Unicode) que protege la ñ al quitar los diacríticos.
	 */
	private const N_TILDE_PLACEHOLDER = "\u{E000}";

	/**
	 * Respaldo cuando la extensión intl no está disponible.
	 */
	private const ACCENT_MAP = [
		'á' => 'a',
		'à' => 'a',
		'ä' => 'a',
		'â' => 'a',
		'ã' => 'a',
		'å' => 'a',
		'é' => 'e',
		'è' => 'e',
		'ë' => 'e',
		'ê' => 'e',
		'í' => 'i',
		'ì' => 'i',
		'ï' => 'i',
		'î' => 'i',
		'ó' => 'o',
		'ò' => 'o',
		'ö' => 'o',
		'ô' => 'o',
		'õ' => 'o',
		'ú' => 'u',
		'ù' => 'u',
		'ü' => 'u',
		'û' => 'u',
		'ç' => 'c',
		'ý' => 'y',
		'ÿ' => 'y',
	];

	/**
	 * Crea el normalizador.
	 *
	 * @param bool $use_intl Usar la extensión intl si está disponible (se desactiva en pruebas del respaldo).
	 */
	public function __construct( private readonly bool $use_intl = true ) {}

	/**
	 * Forma comparable de un texto.
	 *
	 * @param string $text Texto original.
	 */
	public function normalize( string $text ): string {
		$text = $this->compose( $text );
		$text = mb_strtolower( $text, 'UTF-8' );
		$text = str_replace( 'ñ', self::N_TILDE_PLACEHOLDER, $text );
		$text = $this->strip_diacritics( $text );
		$text = str_replace( self::N_TILDE_PLACEHOLDER, 'ñ', $text );

		return trim( (string) preg_replace( '/[\s\p{Z}]+/u', ' ', $text ) );
	}

	/**
	 * Indica si el texto contiene todas las palabras de la búsqueda, en cualquier orden.
	 *
	 * @param string $text  Texto donde se busca.
	 * @param string $query Búsqueda escrita por el usuario.
	 */
	public function matches( string $text, string $query ): bool {
		$haystack = $this->normalize( $text );

		foreach ( explode( ' ', $this->normalize( $query ) ) as $token ) {
			if ( '' !== $token && ! str_contains( $haystack, $token ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Une los caracteres descompuestos (n + tilde combinable → ñ) para tratarlos igual que los compuestos.
	 *
	 * @param string $text Texto.
	 */
	private function compose( string $text ): string {
		if ( ! $this->intl_available() ) {
			return $text;
		}

		$composed = Normalizer::normalize( $text, Normalizer::FORM_C );

		return false === $composed ? $text : $composed;
	}

	/**
	 * Quita tildes, diéresis y demás marcas diacríticas.
	 *
	 * @param string $text Texto en minúsculas.
	 */
	private function strip_diacritics( string $text ): string {
		if ( $this->intl_available() ) {
			$decomposed = Normalizer::normalize( $text, Normalizer::FORM_D );

			if ( false !== $decomposed ) {
				return (string) preg_replace( '/\p{Mn}+/u', '', $decomposed );
			}
		}

		return strtr( $text, self::ACCENT_MAP );
	}

	/**
	 * Indica si se puede usar la extensión intl.
	 */
	private function intl_available(): bool {
		return $this->use_intl && class_exists( Normalizer::class );
	}
}
