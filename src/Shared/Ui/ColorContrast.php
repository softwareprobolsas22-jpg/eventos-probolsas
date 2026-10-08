<?php
/**
 * Contraste de colores (WCAG 2.1).
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Shared\Ui;

use InvalidArgumentException;

/**
 * Calcula, para un color de fondo elegido por el usuario (el de cada tipo de evento), el tono de texto
 * más legible (R-02). Es la versión PHP de `core/color.js`; ambas comparten casos de prueba
 * (tests/fixtures/color-contrast.json).
 */
final class ColorContrast {

	public const TONE_LIGHT = 'light';
	public const TONE_DARK  = 'dark';

	/**
	 * Contraste mínimo de WCAG 2.1 AA para texto normal.
	 */
	public const AA_TEXT = 4.5;

	private const WHITE = '#FFFFFF';
	private const BLACK = '#000000';

	/**
	 * Tono de texto más legible sobre un fondo: blanco (`light`) u oscuro (`dark`).
	 *
	 * @param string $background Color `#RRGGBB`.
	 *
	 * @throws InvalidArgumentException Si el color no es válido.
	 */
	public function readable_tone( string $background ): string {
		return $this->ratio( $background, self::WHITE ) >= $this->ratio( $background, self::BLACK ) ? self::TONE_LIGHT : self::TONE_DARK;
	}

	/**
	 * Contraste del texto más legible sobre el fondo (el mejor de los dos tonos).
	 *
	 * @param string $background Color `#RRGGBB`.
	 *
	 * @throws InvalidArgumentException Si el color no es válido.
	 */
	public function best_ratio( string $background ): float {
		return max( $this->ratio( $background, self::WHITE ), $this->ratio( $background, self::BLACK ) );
	}

	/**
	 * Relación de contraste entre dos colores (de 1 a 21).
	 *
	 * @param string $first  Color `#RRGGBB`.
	 * @param string $second Color `#RRGGBB`.
	 *
	 * @throws InvalidArgumentException Si algún color no es válido.
	 */
	public function ratio( string $first, string $second ): float {
		$luminances = [ $this->luminance( $first ), $this->luminance( $second ) ];
		rsort( $luminances );

		return ( $luminances[0] + 0.05 ) / ( $luminances[1] + 0.05 );
	}

	/**
	 * Luminancia relativa (0 = negro, 1 = blanco).
	 *
	 * @param string $hex Color `#RRGGBB`.
	 *
	 * @throws InvalidArgumentException Si el color no es válido.
	 */
	private function luminance( string $hex ): float {
		if ( 1 !== preg_match( '/^#[0-9A-Fa-f]{6}$/', $hex ) ) {
			throw new InvalidArgumentException( 'Color no válido.' );
		}

		$channels = array_map(
			static function ( int $start ) use ( $hex ): float {
				$channel = hexdec( substr( $hex, $start, 2 ) ) / 255;
				return $channel <= 0.04045 ? $channel / 12.92 : ( ( $channel + 0.055 ) / 1.055 ) ** 2.4;
			},
			[ 1, 3, 5 ]
		);

		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}
}
