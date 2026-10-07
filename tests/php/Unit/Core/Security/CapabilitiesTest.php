<?php
/**
 * Pruebas de las capabilities del plugin.
 *
 * @package Probolsas\Eventos\Tests
 */

declare( strict_types=1 );

namespace Probolsas\Eventos\Tests\Unit\Core\Security;

use Brain\Monkey\Functions;
use Mockery;
use Probolsas\Eventos\Core\Security\Capabilities;
use Probolsas\Eventos\Tests\Unit\UnitTestCase;

/**
 * @covers \Probolsas\Eventos\Core\Security\Capabilities
 */
final class CapabilitiesTest extends UnitTestCase {

	public function test_any_reader_gets_view_capability(): void {
		$caps = ( new Capabilities() )->grant_view_capability( [ 'read' => true ] );

		$this->assertTrue( $caps[ Capabilities::VIEW ] );
	}

	public function test_managers_get_view_capability(): void {
		$caps = ( new Capabilities() )->grant_view_capability( [ Capabilities::MANAGE => true ] );

		$this->assertTrue( $caps[ Capabilities::VIEW ] );
	}

	public function test_users_without_read_do_not_get_view_capability(): void {
		$caps = ( new Capabilities() )->grant_view_capability( [ 'read' => false ] );

		$this->assertArrayNotHasKey( Capabilities::VIEW, $caps );
	}

	public function test_install_grants_manage_to_administrators(): void {
		$role = Mockery::mock( 'WP_Role' );
		$role->shouldReceive( 'add_cap' )->once()->with( Capabilities::MANAGE );
		Functions\expect( 'get_role' )->once()->with( 'administrator' )->andReturn( $role );

		( new Capabilities() )->install();
	}

	public function test_install_tolerates_missing_administrator_role(): void {
		Functions\expect( 'get_role' )->once()->with( 'administrator' )->andReturn( null );

		( new Capabilities() )->install();
	}

	public function test_uninstall_removes_manage_from_every_role(): void {
		$admin  = Mockery::mock( 'WP_Role' );
		$editor = Mockery::mock( 'WP_Role' );
		$admin->shouldReceive( 'remove_cap' )->once()->with( Capabilities::MANAGE );
		$editor->shouldReceive( 'remove_cap' )->once()->with( Capabilities::MANAGE );

		$roles               = Mockery::mock( 'WP_Roles' );
		$roles->role_objects = [
			'administrator' => $admin,
			'editor'        => $editor,
		];
		Functions\when( 'wp_roles' )->justReturn( $roles );

		( new Capabilities() )->uninstall();
	}
}
