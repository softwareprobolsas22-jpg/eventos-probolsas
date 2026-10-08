<?php
/**
 * Pruebas del ciclo de vida del plugin.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Integration;

use Probolsas\Eventos\Core\Database\Migrator;
use Probolsas\Eventos\Core\Database\Tables;
use Probolsas\Eventos\Core\Plugin;
use Probolsas\Eventos\Core\Security\Capabilities;

/**
 * Activación, desactivación y desinstalación. La creación de tablas se prueba junto con las migraciones
 * de cada dominio (H-102).
 *
 * @coversNothing
 */
final class LifecycleTest extends IntegrationTestCase {

	public function test_activation_creates_tables_and_records_the_schema_version(): void {
		$this->plugin()->activate();

		foreach ( $this->tables()->all_names() as $table ) {
			$this->assertTrue( $this->table_exists( $table ), "Falta la tabla {$table}." );
		}
		$this->assertSame( 2, $this->latest_schema_version() );
		$this->assertSame( $this->latest_schema_version(), (int) get_option( Migrator::OPTION, 0 ) );
	}

	public function test_a_clean_install_can_store_events_of_every_initial_type(): void {
		global $wpdb;
		$this->plugin()->activate();

		// RL-05: en el legado no se podían crear eventos en una instalación limpia (columna inexistente).
		$types = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i ORDER BY sort_order', $this->tables()->name( Tables::EVENT_TYPES ) ) );
		$this->assertCount( 4, $types );

		foreach ( $types as $type_id ) {
			$now = gmdate( 'Y-m-d H:i:s' );
			$this->assertSame(
				1,
				$wpdb->insert(
					$this->tables()->name( Tables::EVENTS ),
					[
						'type_id'        => (int) $type_id,
						'title'          => 'Evento de prueba',
						'start_date'     => '2026-10-07',
						'start_time'     => '15:00:00',
						'attachment_id'  => 10,
						'created_at_gmt' => $now,
						'updated_at_gmt' => $now,
					]
				),
				(string) $wpdb->last_error
			);
		}
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
