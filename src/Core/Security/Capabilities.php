<?php
/**
 * Capabilities del plugin.
 *
 * @package Probolsas\Eventos
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Core\Security;

/**
 * Fuente única de verdad de los permisos del plugin.
 *
 * - `eventos_manage`: gestionar eventos y tipos de evento desde wp-admin. Se asigna a los administradores al activar
 *   y puede otorgarse a otros roles.
 * - `eventos_view`: ver el calendario de eventos. La tiene cualquier usuario con sesión (capability `read`) y no se
 *   guarda en los roles: se concede de forma dinámica.
 */
final class Capabilities {

	public const MANAGE = 'eventos_manage';
	public const VIEW   = 'eventos_view';

	/**
	 * Roles que reciben `eventos_manage` al activar el plugin.
	 */
	private const MANAGER_ROLES = [ 'administrator' ];

	/**
	 * Conecta la concesión dinámica de `eventos_view`.
	 */
	public function register(): void {
		add_filter( 'user_has_cap', [ $this, 'grant_view_capability' ] );
	}

	/**
	 * Concede `eventos_view` a los usuarios con sesión y a los gestores.
	 *
	 * @param array<string, bool> $allcaps Capabilities del usuario.
	 *
	 * @return array<string, bool>
	 */
	public function grant_view_capability( array $allcaps ): array {
		if ( ! empty( $allcaps['read'] ) || ! empty( $allcaps[ self::MANAGE ] ) ) {
			$allcaps[ self::VIEW ] = true;
		}

		return $allcaps;
	}

	/**
	 * Asigna `eventos_manage` a los roles gestores.
	 */
	public function install(): void {
		foreach ( self::MANAGER_ROLES as $role_name ) {
			$role = get_role( $role_name );

			if ( null !== $role ) {
				$role->add_cap( self::MANAGE );
			}
		}
	}

	/**
	 * Retira `eventos_manage` de todos los roles, incluidos los que la recibieron manualmente.
	 */
	public function uninstall(): void {
		foreach ( wp_roles()->role_objects as $role ) {
			$role->remove_cap( self::MANAGE );
		}
	}
}
