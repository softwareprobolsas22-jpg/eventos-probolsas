<?php
/**
 * Guía de uso de un shortcode.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Core\Frontend;

/**
 * Lo que necesita quien arma una página de la intranet: para qué sirve el shortcode, sus atributos y un
 * ejemplo listo para copiar. Cada shortcode describe el suyo y la pantalla «Shortcodes» los muestra.
 */
final class ShortcodeGuide {

	/**
	 * Crea la guía.
	 *
	 * @param string               $title       Nombre corto, por ejemplo «Calendario de eventos».
	 * @param string               $description Qué muestra y cuándo usarlo.
	 * @param string               $example     Shortcode de ejemplo, listo para copiar.
	 * @param ShortcodeAttribute[] $attributes  Atributos admitidos.
	 * @phpstan-param list<ShortcodeAttribute> $attributes
	 */
	public function __construct(
		public readonly string $title,
		public readonly string $description,
		public readonly string $example,
		public readonly array $attributes = []
	) {}

	/**
	 * Datos para el navegador.
	 *
	 * @param string $tag Nombre del shortcode.
	 *
	 * @return array{tag: string, title: string, description: string, example: string, attributes: list<array{name: string, description: string, required: bool, default: string, values: string|null}>}
	 */
	public function to_array( string $tag ): array {
		return [
			'tag'         => $tag,
			'title'       => $this->title,
			'description' => $this->description,
			'example'     => $this->example,
			'attributes'  => array_map( static fn( ShortcodeAttribute $attribute ): array => $attribute->to_array(), $this->attributes ),
		];
	}
}
