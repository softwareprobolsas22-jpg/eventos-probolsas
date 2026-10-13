<?php
/**
 * API de los ajustes.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Domains\Settings\Presentation;

use Probolsas\Eventos\Core\Frontend\ShortcodeRegistry;
use Probolsas\Eventos\Core\Settings\PluginSettings;
use Probolsas\Eventos\Shared\Http\RestController;
use Probolsas\Eventos\Shared\Validation\Validator;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * `GET/PUT eventos/v1/settings` y `GET eventos/v1/settings/shortcodes` (docs/api/settings.md). Solo
 * gestores.
 */
final class SettingsRestController extends RestController {

	/**
	 * Valores que se aceptan como «sí» o «no» (JSON o formulario).
	 */
	private const BOOLEANS = [
		'true'  => true,
		'1'     => true,
		'false' => false,
		'0'     => false,
	];

	/**
	 * Crea el controlador.
	 *
	 * @param PluginSettings    $settings   Ajustes.
	 * @param ShortcodeRegistry $shortcodes Shortcodes del plugin, para su guía (H-406).
	 */
	public function __construct(
		private readonly PluginSettings $settings,
		private readonly ShortcodeRegistry $shortcodes
	) {}

	/**
	 * Segmento base.
	 */
	protected function rest_base(): string {
		return 'settings';
	}

	/**
	 * Rutas del recurso.
	 */
	public function register_routes(): void {
		$this->add_route(
			'',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => [ $this, 'can_manage' ],
					'callback'            => [ $this, 'show' ],
				],
				[
					'methods'             => 'PUT',
					'permission_callback' => [ $this, 'can_manage' ],
					'callback'            => [ $this, 'update' ],
				],
			]
		);

		$this->add_route(
			'/shortcodes',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => [ $this, 'can_manage' ],
					'callback'            => [ $this, 'shortcodes' ],
				],
			]
		);
	}

	/**
	 * Guía de cada shortcode del plugin (H-406): título, descripción, atributos y ejemplo.
	 */
	public function shortcodes(): WP_REST_Response|WP_Error {
		return $this->handle( fn(): WP_REST_Response => $this->ok( $this->shortcodes->guides() ) );
	}

	/**
	 * Ajustes actuales.
	 */
	public function show(): WP_REST_Response|WP_Error {
		return $this->handle( fn(): WP_REST_Response => $this->ok( $this->settings->to_array() ) );
	}

	/**
	 * Guarda los ajustes.
	 *
	 * @param WP_REST_Request $request Petición.
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->handle(
			function () use ( $request ): WP_REST_Response {
				$value = $request->get_param( 'delete_data_on_uninstall' );
				$key   = is_bool( $value ) ? ( $value ? 'true' : 'false' ) : ( is_scalar( $value ) ? strtolower( (string) $value ) : '' );

				if ( ! array_key_exists( $key, self::BOOLEANS ) ) {
					$validator = new Validator( [] );
					/* translators: %s: nombre del campo. */
					$validator->add_error( 'delete_data_on_uninstall', sprintf( __( 'El campo «%s» debe ser sí o no.', 'eventos-probolsas' ), __( 'Borrar todos los datos al desinstalar', 'eventos-probolsas' ) ) );
					$validator->validate();
				}

				$this->settings->set_delete_data_on_uninstall( self::BOOLEANS[ $key ] );

				return $this->ok( $this->settings->to_array() );
			}
		);
	}
}
