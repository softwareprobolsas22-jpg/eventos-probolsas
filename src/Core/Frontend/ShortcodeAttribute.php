<?php
/**
 * Atributo de un shortcode.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Core\Frontend;

/**
 * Un atributo admitido por un shortcode, tal como lo explica la pantalla «Shortcodes».
 */
final class ShortcodeAttribute {

	/**
	 * Valores válidos: los slugs de los tipos de evento.
	 */
	public const EVENT_TYPES = 'event_types';

	/**
	 * Crea el atributo.
	 *
	 * @param string      $name          Nombre, tal como se escribe en el shortcode.
	 * @param string      $description   Para qué sirve.
	 * @param bool        $required      Si es obligatorio.
	 * @param string      $default_value Valor por defecto, en texto para el usuario (vacío si no tiene).
	 * @param string|null $values        Lista de valores válidos que la pantalla muestra junto al atributo
	 *                                   (una de las constantes de esta clase), o null si es texto libre.
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $description,
		public readonly bool $required = false,
		public readonly string $default_value = '',
		public readonly ?string $values = null
	) {}

	/**
	 * Datos para el navegador.
	 *
	 * @return array{name: string, description: string, required: bool, default: string, values: string|null}
	 */
	public function to_array(): array {
		return [
			'name'        => $this->name,
			'description' => $this->description,
			'required'    => $this->required,
			'default'     => $this->default_value,
			'values'      => $this->values,
		];
	}
}
