// @vitest-environment happy-dom
import { afterEach, describe, expect, it, vi } from 'vitest';
import { mountWidgets, propsOf } from '../../../assets/src/js/public/mount-widgets.js';

/**
 * Contenedor como el que imprime templates/public/widget.php.
 *
 * @param {string} name Nombre del widget.
 * @param {string} [props] JSON de opciones.
 * @returns {HTMLElement} Contenedor.
 */
function widget( name, props = '{}' ) {
	const element = document.createElement( 'div' );
	element.className = 'ep-public';
	element.dataset.epWidget = name;
	element.dataset.epProps = props;
	element.setAttribute( 'aria-busy', 'true' );
	const loading = document.createElement( 'p' );
	loading.className = 'ep-public__loading';
	loading.textContent = 'Cargando…';
	element.append( loading );
	document.body.append( element );
	return element;
}

describe( 'mountWidgets', () => {
	afterEach( () => {
		document.body.replaceChildren();
		vi.restoreAllMocks();
	} );

	it( 'monta el widget con sus opciones y el contexto compartido', async () => {
		const mount = vi.fn();
		const element = widget( 'calendar', '{"tipos":"cumpleanos"}' );
		const ctx = { config: { today: '2026-10-07' } };

		await mountWidgets( [ element ], { calendar: () => Promise.resolve( { mount } ) }, ctx );

		expect( mount ).toHaveBeenCalledWith( element, { tipos: 'cumpleanos' }, ctx );
	} );

	it( 'un widget desconocido no se queda en «Cargando…» (QA-011)', async () => {
		const warn = vi.spyOn( console, 'warn' ).mockImplementation( () => {} );
		const element = widget( 'no-existe' );

		await mountWidgets( [ element ], {}, {} );

		expect( element.hasAttribute( 'aria-busy' ) ).toBe( false );
		expect( element.querySelector( '[role="alert"]' ).textContent ).toMatch( /No se pudo cargar este contenido/ );
		expect( warn ).toHaveBeenCalledWith( expect.stringContaining( 'no-existe' ) );
	} );

	it( 'no confunde propiedades heredadas de Object con widgets', async () => {
		vi.spyOn( console, 'warn' ).mockImplementation( () => {} );
		const element = widget( 'toString' );

		await mountWidgets( [ element ], {}, {} );

		expect( element.querySelector( '[role="alert"]' ) ).not.toBeNull();
	} );

	it( 'si la descarga o el montaje fallan, muestra el aviso y deja de anunciarse como cargando', async () => {
		vi.spyOn( console, 'error' ).mockImplementation( () => {} );
		const failedLoad = widget( 'calendar' );
		const failedMount = widget( 'upcoming' );

		await mountWidgets(
			[ failedLoad, failedMount ],
			{
				calendar: () => Promise.reject( new Error( 'red caída' ) ),
				upcoming: () => Promise.resolve( { mount: () => Promise.reject( new Error( 'API' ) ) } ),
			},
			{}
		);

		for ( const element of [ failedLoad, failedMount ] ) {
			expect( element.hasAttribute( 'aria-busy' ) ).toBe( false );
			expect( element.querySelector( '[role="alert"]' ) ).not.toBeNull();
		}
	} );

	it( 'un widget que falla no impide montar los demás', async () => {
		vi.spyOn( console, 'error' ).mockImplementation( () => {} );
		const mount = vi.fn();

		await mountWidgets(
			[ widget( 'calendar' ), widget( 'upcoming' ) ],
			{ calendar: () => Promise.reject( new Error( 'x' ) ), upcoming: () => Promise.resolve( { mount } ) },
			{}
		);

		expect( mount ).toHaveBeenCalledOnce();
	} );
} );

describe( 'propsOf', () => {
	it.each( [
		[ 'JSON válido', '{"limite":5}', { limite: 5 } ],
		[ 'JSON roto', '{limite', {} ],
		[ 'un arreglo', '[1,2]', {} ],
		[ 'null', 'null', {} ],
	] )( 'con %s', ( _label, json, expected ) => {
		const element = document.createElement( 'div' );
		element.dataset.epProps = json;

		expect( propsOf( element ) ).toEqual( expected );
	} );
} );
