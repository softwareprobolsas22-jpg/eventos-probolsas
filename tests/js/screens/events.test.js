// @vitest-environment happy-dom
import { readFileSync } from 'node:fs';
import { URL as NodeURL } from 'node:url';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError } from '../../../assets/src/js/core/api.js';

vi.mock( '../../../assets/src/js/ui/toast.js', () => ( {
	showToast: vi.fn(),
	toast: { success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() },
} ) );

const { toast } = await import( '../../../assets/src/js/ui/toast.js' );
const { filterParams, mount } = await import( '../../../assets/src/js/screens/events.js' );

const config = {
	restUrl: 'https://intranet.test/wp-json/eventos/v1/',
	restNonce: 'nonce-123',
	ui: {
		timezone: 'America/Bogota',
		date_format: 'd/m/Y',
		time_format: 'h:i',
		meridiem: { am: 'a. m.', pm: 'p. m.' },
		page_sizes: [ 25, 50, 100 ],
		default_page_size: 25,
	},
};

const TYPES = [
	{ id: 1, name: 'Cumpleaños', slug: 'cumpleanos', color: '#9D174D', text_tone: 'light', icon: 'cake-candles' },
	{ id: 2, name: 'Reuniones laborales', slug: 'reuniones-laborales', color: '#1D4ED8', text_tone: 'light', icon: 'briefcase' },
];

const event = ( overrides ) => ( {
	id: 42,
	title: 'Cumpleaños de Ana María',
	description: '',
	type: TYPES[ 0 ],
	start_date: '2026-10-07',
	start_time: '15:00',
	all_day: false,
	is_past: false,
	attachment: { id: 315, kind: 'image' },
	...overrides,
} );

let screen;
let api;
let download;
let store;

const flush = async () => {
	for ( let i = 0; i < 5; i++ ) {
		await new Promise( ( resolve ) => setTimeout( resolve ) );
	}
};
const rows = () => [ ...screen.querySelectorAll( 'tbody tr' ) ];
const dialog = () => document.querySelector( 'dialog.ep-dialog' );
const buttonIn = ( container, text ) => [ ...container.querySelectorAll( 'button' ) ].find( ( button ) => button.textContent === text );

/**
 * API simulada: pagina y filtra por tipo y búsqueda, como el servidor.
 *
 * @param {Object[]} events Eventos guardados.
 * @returns {Object} API.
 */
function fakeApi( events ) {
	store = [ ...events ];
	return {
		get: vi.fn( async () => TYPES ),
		getPage: vi.fn( async ( path ) => {
			const params = new URLSearchParams( path.split( '?' )[ 1 ] );
			const page = Number( params.get( 'page' ) );
			const size = Number( params.get( 'per_page' ) );
			const matches = store.filter(
				( item ) => ( ! params.get( 'type' ) || String( item.type.id ) === params.get( 'type' ) ) && ( ! params.get( 'search' ) || item.title.toLowerCase().includes( params.get( 'search' ).toLowerCase() ) )
			);
			return { items: matches.slice( ( page - 1 ) * size, page * size ), total: matches.length, totalPages: Math.ceil( matches.length / size ) };
		} ),
		del: vi.fn( async ( path ) => {
			store = store.filter( ( item ) => `events/${ item.id }` !== path );
			return { deleted: true };
		} ),
	};
}

async function mountWith( events, overrides = {} ) {
	document.body.innerHTML = `
		<div class="wrap ep-app" data-ep-screen="eventos-probolsas">
			<div data-ep-header-actions></div>
			<div id="ep-events" class="ep-mount" aria-busy="true"></div>
		</div>`;
	screen = document.querySelector( '[data-ep-screen]' );
	api = { ...fakeApi( events ), ...overrides };
	download = vi.fn();
	await mount( screen, config, { api, download } );
}

beforeEach( async () => {
	vi.clearAllMocks();
	await mountWith( [
		event(),
		event( { id: 43, title: 'Reunión de planeación', type: TYPES[ 1 ], start_date: '2026-10-08', start_time: null, attachment: null } ),
		event( { id: 44, title: 'Acta del comité', type: TYPES[ 1 ], start_date: '2026-10-09', start_time: '09:30', attachment: { id: 316, kind: 'pdf' } } ),
	] );
} );

afterEach( () => vi.useRealTimers() );

describe( 'pantalla Eventos: tabla', () => {
	it( 'tiene 6 columnas con Acciones primero (R-06, R-19) y pagina en el servidor (R-23)', () => {
		const headers = [ ...screen.querySelectorAll( 'thead th' ) ].map( ( th ) => th.textContent );
		expect( headers ).toEqual( [ 'Acciones', 'Evento', 'Tipo', 'Fecha', 'Hora', 'Adjunto' ] );
		expect( api.getPage ).toHaveBeenCalledWith( 'events?page=1&per_page=25' );
		expect( screen.querySelector( '#ep-events' ).hasAttribute( 'aria-busy' ) ).toBe( false );
	} );

	it( 'muestra fecha es-CO, hora en 12 h o «Todo el día» y el tipo de adjunto (R-07)', () => {
		const cells = ( row ) => [ ...row.querySelectorAll( 'td' ) ].slice( 1 ).map( ( td ) => td.textContent );

		expect( cells( rows()[ 0 ] ) ).toEqual( [ 'Cumpleaños de Ana María', 'Cumpleaños', '07/10/2026', '03:00 p. m.', 'Imagen' ] );
		expect( cells( rows()[ 1 ] ) ).toEqual( [ 'Reunión de planeación', 'Reuniones laborales', '08/10/2026', 'Todo el día', '—' ] );
		expect( cells( rows()[ 2 ] )[ 4 ] ).toBe( 'PDF' );
		expect( rows()[ 0 ].querySelector( '.ep-truncate' ) ).not.toBeNull();
		expect( rows()[ 0 ].querySelector( '.ep-attachment-kind' ).getAttribute( 'data-ep-tooltip' ) ).toBe( 'Imagen' );
	} );

	it( 'resume el total y habilita la exportación', () => {
		expect( screen.querySelector( '.ep-table-toolbar__summary' ).textContent ).toBe( '3 eventos' );
		expect( buttonIn( screen, 'Exportar CSV' ).disabled ).toBe( false );
	} );

	it( 'sin eventos muestra el estado vacío y deshabilita exportar', async () => {
		await mountWith( [] );

		expect( screen.querySelector( '.ep-empty-state__title' ).textContent ).toBe( 'Todavía no hay eventos' );
		expect( screen.querySelector( '.ep-table-toolbar__summary' ).textContent ).toBe( '0 eventos' );
		expect( buttonIn( screen, 'Exportar CSV' ).disabled ).toBe( true );
	} );

	it( 'si la primera carga falla, muestra el error de carga', async () => {
		await mountWith( [], { getPage: vi.fn( async () => Promise.reject( new ApiError( 'Sin conexión' ) ) ) } );

		expect( screen.querySelector( '.ep-empty-state__title' ).textContent ).toBe( 'No se pudo cargar la información' );
	} );

	it( 'los tipos del filtro salen de la API; si fallan, queda «Todos los tipos»', async () => {
		expect( [ ...screen.querySelectorAll( '.ep-filter-bar select option' ) ].map( ( option ) => option.textContent ) ).toEqual( [ 'Todos los tipos', 'Cumpleaños', 'Reuniones laborales' ] );

		await mountWith( [ event() ], { get: vi.fn( async () => Promise.reject( new ApiError( 'x' ) ) ) } );
		expect( screen.querySelectorAll( '.ep-filter-bar select option' ) ).toHaveLength( 1 );
		expect( rows() ).toHaveLength( 1 );
	} );
} );

describe( 'pantalla Eventos: filtros, exportación y eliminación', () => {
	it( 'filtrar por tipo pide la primera página con el filtro y distingue «sin resultados»', async () => {
		const select = screen.querySelector( '.ep-filter-bar select' );
		select.value = '2';
		select.dispatchEvent( new Event( 'change' ) );
		await flush();

		expect( api.getPage ).toHaveBeenLastCalledWith( 'events?type=2&page=1&per_page=25' );
		expect( rows() ).toHaveLength( 2 );

		const search = screen.querySelector( '.ep-filter-bar input[type="search"]' );
		search.value = 'zzz';
		search.dispatchEvent( new Event( 'input' ) );
		await new Promise( ( resolve ) => setTimeout( resolve, 350 ) );
		await flush();

		expect( screen.querySelector( '.ep-empty-state__title' ).textContent ).toBe( 'Ningún evento coincide con los filtros' );
	} );

	it( 'solo muestra la respuesta de la última búsqueda', async () => {
		let resolveSlow;
		api.getPage.mockImplementationOnce( () => new Promise( ( resolve ) => ( resolveSlow = resolve ) ) );
		const select = screen.querySelector( '.ep-filter-bar select' );

		select.value = '1';
		select.dispatchEvent( new Event( 'change' ) );
		select.value = '2';
		select.dispatchEvent( new Event( 'change' ) );
		await flush();
		resolveSlow( { items: [ event( { title: 'Respuesta vieja' } ) ], total: 1, totalPages: 1 } );
		await flush();

		expect( rows().map( ( row ) => row.querySelector( 'td:nth-child(2)' ).textContent ) ).not.toContain( 'Respuesta vieja' );
		expect( rows() ).toHaveLength( 2 );
	} );

	it( 'exporta con los filtros activos y el nonce en la dirección', async () => {
		const select = screen.querySelector( '.ep-filter-bar select' );
		select.value = '2';
		select.dispatchEvent( new Event( 'change' ) );
		await flush();

		buttonIn( screen, 'Exportar CSV' ).click();

		expect( download ).toHaveBeenCalledWith( 'https://intranet.test/wp-json/eventos/v1/events/export.csv?type=2&_wpnonce=nonce-123' );
		expect( toast.success ).toHaveBeenCalledWith( 'Exportando 2 eventos a CSV.' );
	} );

	it( 'elimina tras confirmar, avisa que el archivo se conserva y recarga la página', async () => {
		rows()[ 0 ].querySelector( 'button[aria-label="Eliminar"]' ).click();
		expect( dialog().textContent ).toContain( '¿Eliminar el evento «Cumpleaños de Ana María»?' );
		expect( dialog().textContent ).toContain( 'el archivo se conserva en la Biblioteca de Medios' );

		buttonIn( dialog(), 'Eliminar' ).click();
		await flush();

		expect( api.del ).toHaveBeenCalledWith( 'events/42', { silent: true } );
		expect( toast.success ).toHaveBeenCalledWith( 'Evento «Cumpleaños de Ana María» eliminado.' );
		expect( rows() ).toHaveLength( 2 );
		expect( screen.querySelector( '.ep-table-toolbar__summary' ).textContent ).toBe( '2 eventos' );
	} );

	it( 'si eliminar falla, muestra el error y recarga igual', async () => {
		api.del.mockRejectedValueOnce( new ApiError( 'El evento no existe o fue eliminado.', { status: 404 } ) );
		const calls = api.getPage.mock.calls.length;

		rows()[ 0 ].querySelector( 'button[aria-label="Eliminar"]' ).click();
		buttonIn( dialog(), 'Eliminar' ).click();
		await flush();

		expect( toast.error ).toHaveBeenCalledWith( 'El evento no existe o fue eliminado.' );
		expect( api.getPage.mock.calls.length ).toBe( calls + 1 );
	} );

	it( 'cancelar no elimina', async () => {
		rows()[ 0 ].querySelector( 'button[aria-label="Eliminar"]' ).click();
		buttonIn( dialog(), 'Cancelar' ).click();
		await flush();

		expect( api.del ).not.toHaveBeenCalled();
	} );

	it( 'los parámetros omiten los filtros vacíos', () => {
		expect( String( filterParams( { search: ' ana ', type: '', dates: { from: '2026-10-01', to: '' } } ) ) ).toBe( 'search=ana&date_from=2026-10-01' );
		expect( String( filterParams( {} ) ) ).toBe( '' );
	} );
} );

describe( 'pantalla Eventos: contrato con la página PHP', () => {
	const read = ( path ) => readFileSync( new NodeURL( `../../../${ path }`, import.meta.url ), 'utf8' );

	it( 'admin.js monta esta pantalla con el slug y el punto de montaje de EventsPage', () => {
		const page = read( 'src/Domains/Event/Presentation/EventsPage.php' );
		const mountId = /'id' => '([^']+)'/.exec( page )[ 1 ];

		expect( read( 'src/Core/Admin/AdminMenu.php' ) ).toContain( "ROOT_SLUG = 'eventos-probolsas'" );
		expect( read( 'assets/src/js/pages/admin.js' ) ).toContain( "'eventos-probolsas': () => import( '../screens/events.js' )" );
		expect( read( 'assets/src/js/screens/events.js' ) ).toContain( `querySelector( '#${ mountId }' )` );
	} );
} );
