// @vitest-environment happy-dom
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createTypeFilter } from '../../../assets/src/js/public/type-filter.js';

const TYPES = [
	{ id: 1, name: 'Cumpleaños', color: '#9D174D', icon: 'cake-candles', text_tone: 'light' },
	{ id: 2, name: 'Pausas', color: '#FDE68A', icon: 'mug-hot', text_tone: 'dark' },
];

/**
 * Tom Select simulado: guarda el select y las opciones.
 */
class FakeTomSelect {
	static last = null;

	constructor( select, settings ) {
		this.select = select;
		this.settings = settings;
		this.destroyed = false;
		FakeTomSelect.last = this;
	}

	destroy() {
		this.destroyed = true;
	}
}

describe( 'filtro de tipos (Tom Select)', () => {
	afterEach( () => {
		document.body.replaceChildren();
	} );

	it( 'crea un select múltiple con su etiqueta visible', () => {
		const host = document.createElement( 'div' );
		createTypeFilter( host, TYPES, () => {}, { TomSelectClass: FakeTomSelect } );

		const select = host.querySelector( 'select' );
		const label = host.querySelector( 'label' );
		expect( select.multiple ).toBe( true );
		expect( label.textContent ).toBe( 'Tipos de evento' );
		expect( label.htmlFor ).toBe( select.id );
		expect( [ ...select.options ].map( ( option ) => [ option.value, option.textContent ] ) ).toEqual( [
			[ '1', 'Cumpleaños' ],
			[ '2', 'Pausas' ],
		] );
		expect( FakeTomSelect.last.settings.placeholder ).toBe( 'Todos los tipos' );
	} );

	it( 'las opciones y los elegidos se ven como el badge del tipo, con su texto legible (R-02)', () => {
		createTypeFilter( document.createElement( 'div' ), TYPES, () => {}, { TomSelectClass: FakeTomSelect } );
		const { render } = FakeTomSelect.last.settings;

		const option = render.option( { value: '2', text: 'Pausas' } );
		expect( option.querySelector( '.ep-badge--dark-text' ).textContent ).toBe( 'Pausas' );
		expect( option.querySelector( '.fa-mug-hot' ) ).not.toBeNull();
		expect( render.item( { value: '1', text: 'Cumpleaños' } ).querySelector( '.ep-badge--light-text' ) ).not.toBeNull();
		expect( render.item( { value: '9', text: 'Otro' } ).textContent ).toBe( 'Otro' );
		expect( render.no_results().textContent ).toBe( 'No hay tipos con ese nombre.' );
	} );

	it( 'entrega los IDs elegidos como números (vacío = todos)', () => {
		const onChange = vi.fn();
		createTypeFilter( document.createElement( 'div' ), TYPES, onChange, { TomSelectClass: FakeTomSelect } );
		const { settings } = FakeTomSelect.last;

		settings.onChange( [ '2', '1' ] );
		settings.onChange( '' );

		expect( onChange.mock.calls ).toEqual( [ [ [ 2, 1 ] ], [ [] ] ] );
	} );

	it( 'funciona con Tom Select real: elegir un tipo avisa el cambio', () => {
		const host = document.createElement( 'div' );
		document.body.append( host );
		const onChange = vi.fn();

		const filter = createTypeFilter( host, TYPES, onChange );
		filter.select.tomselect.addItem( '2' );

		expect( onChange ).toHaveBeenLastCalledWith( [ 2 ] );
		expect( host.querySelector( '.ts-control .ep-badge' ).textContent ).toContain( 'Pausas' );

		filter.destroy();
		expect( filter.select.tomselect ).toBeUndefined();
	} );
} );
