/**
 * Paquete de release (H-404): tools/package.mjs arma el .zip con `git archive` y lo verifica.
 */
import { describe, expect, it } from 'vitest';
import { FORBIDDEN, REQUIRED, packagedFiles, problems, versions } from '../../../tools/package.mjs';

describe( 'paquete .zip de release (H-404)', () => {
	it( 'la versión es la misma en la cabecera del plugin, Plugin::VERSION y package.json', () => {
		const found = versions();

		expect( found.header ).toMatch( /^\d+\.\d+\.\d+$/ );
		expect( found.constant ).toBe( found.header );
		expect( found.npm ).toBe( found.header );
	} );

	it( 'el paquete del commit actual tiene lo necesario y nada de desarrollo', () => {
		const files = packagedFiles();

		expect( problems( files, versions() ) ).toEqual( [] );
		expect( files.some( ( file ) => file.startsWith( 'src/Domains/' ) ) ).toBe( true );
	} );

	it( 'detecta versiones distintas, archivos faltantes y archivos de desarrollo', () => {
		const found = problems( [ 'eventos-probolsas.php', 'tests/php/Unit/UnitTestCase.php', 'assets/src/js/pages/admin.js' ], { header: '2.0.0', constant: '2.0.1', npm: '2.0.0' } );

		expect( found[ 0 ] ).toMatch( /^Versiones distintas/ );
		expect( found ).toContain( 'Falta en el paquete: assets/dist/js/admin.js' );
		expect( found ).toContain( 'No debe ir en el paquete: tests/php/Unit/UnitTestCase.php' );
		expect( found ).toContain( 'No debe ir en el paquete: assets/src/js/pages/admin.js' );
		expect( REQUIRED ).toContain( 'assets/dist/.vite/manifest.json' );
		expect( FORBIDDEN ).toContain( 'legacy/' );
	} );
} );
