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
	today: '2026-10-07',
	media: { allowed_mimes: [ 'image/jpeg', 'image/png', 'application/pdf' ], library_types: [ 'image', 'application/pdf' ] },
	rules: {
		event: {
			title: { required: true, minLength: 3, maxLength: 150 },
			type_id: { required: true, oneOf: 'event_types' },
			start_date: { required: true, format: 'date' },
			start_time: { format: 'time' },
			description: { maxLength: 2000 },
			attachment_id: { requiredWhen: 'type.requires_attachment', mimes: 'media.allowed_mimes' },
		},
	},
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
	{ id: 1, name: 'Cumpleaños', slug: 'cumpleanos', color: '#9D174D', text_tone: 'light', icon: 'cake-candles', requires_attachment: true },
	{ id: 2, name: 'Reuniones laborales', slug: 'reuniones-laborales', color: '#1D4ED8', text_tone: 'light', icon: 'briefcase', requires_attachment: false },
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
	attachment: { id: 315, kind: 'image', mime: 'image/jpeg', url: 'https://intranet.test/uploads/ana.jpg', thumbnail_url: 'https://intranet.test/uploads/ana-150x150.jpg', filename: 'ana.jpg' },
	...overrides,
} );

let screen;
let api;
let download;
let openLibrary;
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
		post: vi.fn( async ( path, body ) => ( { id: 99, ...body } ) ),
		put: vi.fn( async ( path, body ) => ( { id: 42, ...body } ) ),
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
	openLibrary = vi.fn( async () => ( { id: 400, mime: 'application/pdf', url: 'https://intranet.test/uploads/acta.pdf', thumbnail_url: null, filename: 'acta.pdf' } ) );
	await mount( screen, config, { api, download, openLibrary } );
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

describe( 'pantalla Eventos: formulario (H-204)', () => {
	const drawer = () => document.querySelector( 'dialog.ep-drawer' );
	const field = ( name ) => drawer().querySelector( `[name="${ name }"]` );
	const errorOf = ( name ) => field( name ).closest( '.ep-field' ).querySelector( '.ep-field__error' );
	const mediaError = () => drawer().querySelector( '.ep-media-field .ep-field__error' );
	const header = ( text ) => buttonIn( screen.querySelector( '[data-ep-header-actions]' ), text );
	const submit = () => drawer().querySelector( 'form' ).dispatchEvent( new Event( 'submit', { cancelable: true } ) );
	const type = ( name, value, eventName = 'input' ) => {
		field( name ).value = value;
		field( name ).dispatchEvent( new Event( eventName ) );
	};

	it( 'crear: reglas de epConfig.rules.event, fecha de hoy del servidor y bloque «Cuándo»', () => {
		header( 'Añadir evento' ).click();

		expect( drawer().querySelector( '.ep-drawer__title' ).textContent ).toBe( 'Nuevo evento' );
		expect( field( 'title' ).maxLength ).toBe( 150 );
		expect( field( 'start_date' ).value ).toBe( '2026-10-07' );
		expect( field( 'start_time' ).type ).toBe( 'time' );
		expect( drawer().querySelector( 'fieldset legend' ).textContent ).toBe( 'Cuándo' );
		expect( [ ...field( 'type_id' ).options ].map( ( option ) => option.textContent ) ).toEqual( [ 'Selecciona un tipo', 'Cumpleaños', 'Reuniones laborales' ] );
		expect( field( 'description' ).tagName ).toBe( 'TEXTAREA' );
	} );

	it( 'no envía si faltan datos: título corto, tipo y adjunto que el tipo exige', () => {
		header( 'Añadir evento' ).click();
		type( 'title', 'Ab' );
		submit();

		expect( api.post ).not.toHaveBeenCalled();
		expect( errorOf( 'title' ).textContent ).toBe( 'El campo «Título» debe tener al menos 3 caracteres.' );
		expect( errorOf( 'type_id' ).textContent ).toBe( 'El campo «Tipo» es obligatorio.' );

		type( 'type_id', '1', 'change' );
		submit();
		expect( mediaError().textContent ).toBe( 'Este tipo de evento requiere una imagen o un PDF.' );

		type( 'type_id', '2', 'change' );
		expect( mediaError().hidden ).toBe( true );
	} );

	it( 'el aviso de fecha pasada no bloquea (D-3) y compara con «hoy» del servidor', () => {
		header( 'Añadir evento' ).click();
		const notice = drawer().querySelector( '.ep-notice' );
		expect( notice.hidden ).toBe( true );

		type( 'start_date', '2026-10-06' );
		expect( notice.hidden ).toBe( false );
		expect( notice.textContent ).toContain( 'Esta fecha ya pasó' );

		type( 'start_date', '2026-10-07' );
		expect( notice.hidden ).toBe( true );
	} );

	it( 'crea un evento con adjunto de la biblioteca, confirma con un toast y recarga la tabla', async () => {
		header( 'Añadir evento' ).click();
		type( 'title', '  Cumpleaños de Carlos ' );
		type( 'type_id', '1', 'change' );
		type( 'start_time', '15:00' );
		buttonIn( drawer(), 'Elegir archivo' ).click();
		await flush();
		const calls = api.getPage.mock.calls.length;

		submit();
		await flush();

		expect( api.post ).toHaveBeenCalledWith(
			'events',
			{ title: 'Cumpleaños de Carlos', type_id: '1', start_date: '2026-10-07', start_time: '15:00', description: '', attachment_id: '400' },
			{ silent: true }
		);
		expect( drawer() ).toBeNull();
		expect( toast.success ).toHaveBeenCalledWith( 'Evento «Cumpleaños de Carlos» creado.' );
		expect( api.getPage.mock.calls.length ).toBe( calls + 1 );
	} );

	it( 'edita con los datos actuales, incluida la vista previa del adjunto', async () => {
		rows()[ 0 ].querySelector( 'button[aria-label="Editar"]' ).click();

		expect( drawer().querySelector( '.ep-drawer__title' ).textContent ).toBe( 'Editar evento' );
		expect( field( 'title' ).value ).toBe( 'Cumpleaños de Ana María' );
		expect( field( 'type_id' ).value ).toBe( '1' );
		expect( field( 'start_time' ).value ).toBe( '15:00' );
		expect( drawer().querySelector( '.ep-media-field img' ).getAttribute( 'src' ) ).toBe( 'https://intranet.test/uploads/ana-150x150.jpg' );

		type( 'title', 'Cumpleaños de Ana' );
		submit();
		await flush();

		expect( api.put ).toHaveBeenCalledWith( 'events/42', expect.objectContaining( { title: 'Cumpleaños de Ana', attachment_id: '315' } ), { silent: true } );
		expect( toast.success ).toHaveBeenCalledWith( 'Cambios guardados en «Cumpleaños de Ana».' );
	} );

	it( 'muestra los errores 422 bajo cada campo y el mensaje en un toast', async () => {
		api.post.mockRejectedValueOnce(
			new ApiError( 'Revisa los campos marcados.', { status: 422, fieldErrors: { attachment_id: [ 'El archivo debe ser una imagen o un PDF.' ], start_date: [ 'El campo «Fecha» debe ser una fecha válida.' ] } } )
		);
		header( 'Añadir evento' ).click();
		type( 'title', 'Reunión de planeación' );
		type( 'type_id', '2', 'change' );
		submit();
		await flush();

		expect( toast.error ).toHaveBeenCalledWith( 'Revisa los campos marcados.' );
		expect( errorOf( 'start_date' ).textContent ).toBe( 'El campo «Fecha» debe ser una fecha válida.' );
		expect( mediaError().textContent ).toBe( 'El archivo debe ser una imagen o un PDF.' );
		expect( drawer() ).not.toBeNull();
	} );

	it( 'si el evento ya no existe al guardar, cierra el panel y recarga', async () => {
		api.put.mockRejectedValueOnce( new ApiError( 'El evento no existe o fue eliminado.', { status: 404 } ) );
		rows()[ 0 ].querySelector( 'button[aria-label="Editar"]' ).click();
		const calls = api.getPage.mock.calls.length;
		submit();
		await flush();

		expect( toast.error ).toHaveBeenCalledWith( 'El evento no existe o fue eliminado.' );
		expect( drawer() ).toBeNull();
		expect( api.getPage.mock.calls.length ).toBe( calls + 1 );
	} );

	it( 'pide confirmación antes de cerrar con cambios sin guardar', async () => {
		header( 'Añadir evento' ).click();
		type( 'title', 'Borrador' );

		drawer().querySelector( 'button[aria-label="Cerrar"]' ).click();
		await flush();
		expect( dialog().textContent ).toContain( '¿Descartar los cambios?' );

		buttonIn( dialog(), 'Descartar' ).click();
		await flush();
		expect( drawer() ).toBeNull();
	} );
} );

