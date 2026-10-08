// @vitest-environment happy-dom
import { beforeEach, describe, expect, it, vi } from 'vitest';

const notyf = vi.hoisted( () => ( { options: null, open: vi.fn(), instances: 0 } ) );

vi.mock( 'notyf', () => ( {
	Notyf: class {
		constructor( options ) {
			notyf.options = options;
			notyf.instances++;
			// Igual que Notyf 3: el contenedor y la región aria-live se crean en <body>.
			this.view = { container: document.createElement( 'div' ), a11yContainer: document.createElement( 'div' ) };
			document.body.append( this.view.container, this.view.a11yContainer );
			notyf.view = this.view;
		}

		open( options ) {
			notyf.open( options );
		}
	},
} ) );

const { showToast, toast } = await import( '../../../assets/src/js/ui/toast.js' );
const { releaseFloating } = await import( '../../../assets/src/js/core/dom.js' );

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

	it( 'con un drawer o diálogo abierto, los toasts se muestran dentro de él y vuelven a <body> al cerrarlo (QA-024)', () => {
		toast.info( 'Sin diálogo' );
		const { container, a11yContainer } = notyf.view;
		expect( container.parentElement ).toBe( document.body );
		expect( container.hasAttribute( 'data-ep-floating' ) ).toBe( true );

		const drawer = document.createElement( 'dialog' );
		const confirm = document.createElement( 'dialog' );
		document.body.append( drawer, confirm );
		drawer.setAttribute( 'open', '' );
		confirm.setAttribute( 'open', '' );

		toast.error( 'No se pudo guardar.' );
		expect( container.parentElement ).toBe( confirm );
		expect( a11yContainer.parentElement ).toBe( confirm );

		// Al cerrar el diálogo de confirmación, los toasts pasan al drawer, que sigue abierto.
		confirm.removeAttribute( 'open' );
		releaseFloating( confirm );
		confirm.remove();
		expect( container.parentElement ).toBe( drawer );

		drawer.removeAttribute( 'open' );
		releaseFloating( drawer );
		drawer.remove();
		expect( container.parentElement ).toBe( document.body );
		expect( a11yContainer.parentElement ).toBe( document.body );
	} );
} );
