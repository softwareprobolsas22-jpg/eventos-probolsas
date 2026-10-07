<?php
/**
 * Pruebas del ciclo de vida del plugin.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Integration;

use Probolsas\Eventos\Core\Database\Migrator;
use Probolsas\Eventos\Core\Plugin;
use Probolsas\Eventos\Core\Security\Capabilities;

/**
 * Activación, desactivación y desinstalación. La creación de tablas se prueba junto con las migraciones
 * de cada dominio (H-102).
 *
 * @coversNothing
 */
final class LifecycleTest extends IntegrationTestCase {

	public function test_activation_records_the_latest_schema_version(): void {
		$this->plugin()->activate();

		$this->assertSame( $this->latest_schema_version(), (int) get_option( Migrator::OPTION, 0 ) );
	}

	public function test_activation_grants_manage_only_to_administrators(): void {
		$this->plugin()->activate();

		$admin  = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$editor = self::factory()->user->create( [ 'role' => 'editor' ] );

		$this->assertTrue( user_can( $admin, Capabilities::MANAGE ) );
		$this->assertFalse( user_can( $editor, Capabilities::MANAGE ) );
	}

	public function test_any_logged_in_user_can_view_but_visitors_cannot(): void {
		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );

		$this->assertTrue( user_can( $subscriber, Capabilities::VIEW ) );
		$this->assertFalse( user_can( 0, Capabilities::VIEW ), 'Un visitante sin sesión no debe ver el calendario (D-1).' );
	}

	public function test_activation_is_idempotent(): void {
		$this->plugin()->activate();
		$this->plugin()->activate();

		$this->assertSame( $this->latest_schema_version(), (int) get_option( Migrator::OPTION, 0 ) );
		$this->assertTrue( get_role( 'administrator' )->has_cap( Capabilities::MANAGE ) );
	}

	public function test_deactivation_keeps_permissions(): void {
		$this->plugin()->activate();

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Simula el hook de desactivación de WordPress.
		do_action( 'deactivate_' . plugin_basename( $this->plugin_file() ) );

		$this->assertTrue( get_role( 'administrator' )->has_cap( Capabilities::MANAGE ) );
	}

	public function test_uninstall_removes_tables_option_and_capabilities(): void {
		$this->plugin()->activate();
		get_role( 'editor' )->add_cap( Capabilities::MANAGE );

		Plugin::uninstall( $this->plugin_file() );

		foreach ( $this->tables()->all_names() as $table ) {
			$this->assertFalse( $this->table_exists( $table ), "La tabla {$table} no se eliminó." );
		}
		$this->assertFalse( get_option( Migrator::OPTION ) );
		foreach ( wp_roles()->role_objects as $name => $role ) {
			$this->assertFalse( $role->has_cap( Capabilities::MANAGE ), "El rol {$name} conserva eventos_manage." );
		}
	}

	public function test_uninstall_keeps_media_library_files(): void {
		$this->plugin()->activate();
		$attachment = self::factory()->attachment->create_object(
			[
				'file'           => 'agenda.pdf',
				'post_mime_type' => 'application/pdf',
			]
		);

		Plugin::uninstall( $this->plugin_file() );

		$this->assertNotNull( get_post( $attachment ), 'Los archivos de la biblioteca los usan otras partes de la intranet (D-4).' );
	}
}
