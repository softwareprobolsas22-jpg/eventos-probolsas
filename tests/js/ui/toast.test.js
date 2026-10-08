import { beforeEach, describe, expect, it, vi } from 'vitest';

const notyf = vi.hoisted( () => ( { options: null, open: vi.fn(), instances: 0 } ) );

vi.mock( 'notyf', () => ( {
	Notyf: class {
		constructor( options ) {
			notyf.options = options;
			notyf.instances++;
		}

		open( options ) {
			notyf.open( options );
		}
	},
} ) );

const { showToast, toast } = await import( '../../../assets/src/js/ui/toast.js' );

beforeEach( () => notyf.open.mockClear() );

describe( 'toasts (D-11)', () => {
	it( 'crea una sola instancia con los cuatro tipos, colores de los tokens y cierre manual', () => {
		toast.success( 'Tipo de evento guardado.' );
		toast.info( 'Info' );

		expect( notyf.instances ).toBe( 1 );
		expect( notyf.options.dismissible ).toBe( true );
		expect( notyf.options.types.map( ( type ) => [ type.type, type.background ] ) ).toEqual( [
			[ 'success', 'var(--ep-color-success)' ],
			[ 'info', 'var(--ep-color-info)' ],
			[ 'warning', 'var(--ep-color-warning)' ],
			[ 'error', 'var(--ep-color-error)' ],
		] );
		expect( notyf.options.types.find( ( type ) => 'error' === type.type ).duration ).toBeGreaterThan( notyf.options.types[ 0 ].duration );
	} );

	it( 'escapa el mensaje: Notyf lo inserta como HTML (R-15)', () => {
		toast.error( '<img src=x onerror=alert(1)>' );

		expect( notyf.open ).toHaveBeenCalledWith( { type: 'error', message: '&lt;img src=x onerror=alert(1)&gt;' } );
	} );

	it( 'un tipo desconocido se muestra como información', () => {
		toast.warning( 'Atención' );
		showToast( 'otro', 'Mensaje' );

		expect( notyf.open.mock.calls.map( ( [ options ] ) => options.type ) ).toEqual( [ 'warning', 'info' ] );
	} );
} );
