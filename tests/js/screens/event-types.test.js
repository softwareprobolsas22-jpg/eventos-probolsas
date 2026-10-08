// @vitest-environment happy-dom
import { readFileSync } from 'node:fs';
import { URL as NodeURL } from 'node:url';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError } from '../../../assets/src/js/core/api.js';

vi.mock( '../../../assets/src/js/ui/toast.js', () => ( {
	showToast: vi.fn(),
	toast: { success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() },
} ) );

const { toast } = await import( '../../../assets/src/js/ui/toast.js' );
const { COLOR_PRESETS, mount } = await import( '../../../assets/src/js/screens/event-types.js' );

const config = {
	ui: {
		timezone: 'America/Bogota',
		date_format: 'd/m/Y',
		time_format: 'h:i',
		meridiem: { am: 'a. m.', pm: 'p. m.' },
		page_sizes: [ 25, 50, 100 ],
		default_page_size: 25,
	},
	icons: [
		{ key: 'cake-candles', label: 'Pastel', keywords: 'cumpleaños' },
		{ key: 'graduation-cap', label: 'Birrete', keywords: 'capacitación' },
		{ key: 'briefcase', label: 'Maletín', keywords: 'reunión trabajo' },
	],
	rules: {
		event_type: {
			name: { required: true, maxLength: 100 },
			color: { required: true, pattern: '^#[0-9A-Fa-f]{6}$' },
			icon: { required: true, oneOf: 'icons' },
			description: { maxLength: 500 },
		},
	},
};

const eventType = ( overrides ) => ( {
	id: 1,
	name: 'Cumpleaños',
	slug: 'cumpleanos',
	color: '#9D174D',
	text_tone: 'light',
	icon: 'cake-candles',
	requires_attachment: true,
	description: '',
	sort_order: 1,
	events_count: 0,
	created_at: '2026-10-07T09:15:00-05:00',
	updated_at: '2026-10-08T15:30:00-05:00',
	...overrides,
} );

const SEED = [
	eventType( { id: 4, name: 'Reuniones laborales', slug: 'reuniones-laborales', color: '#1D4ED8', icon: 'briefcase', requires_attachment: false, sort_order: 4, events_count: 3 } ),
	eventType(),
	eventType( { id: 2, name: 'Capacitaciones', slug: 'capacitaciones', color: '#FDE68A', text_tone: 'dark', icon: 'graduation-cap', sort_order: 2, events_count: 1 } ),
];

let screen;
let api;

const drawer = () => document.querySelector( 'dialog.ep-drawer' );
const dialog = () => document.querySelector( 'dialog.ep-dialog' );
const field = ( name ) => drawer().querySelector( `[name="${ name }"]` );
const headerButton = ( text ) => [ ...screen.querySelectorAll( '[data-ep-header-actions] button' ) ].find( ( button ) => button.textContent === text );
const rowAction = ( rowIndex, label ) => screen.querySelectorAll( 'tbody tr' )[ rowIndex ].querySelector( `button[aria-label="${ label }"]` );
const buttonIn = ( container, text ) => [ ...container.querySelectorAll( 'button' ) ].find( ( button ) => button.textContent === text );
const flush = () => new Promise( ( resolve ) => setTimeout( resolve ) );

function type( name, value ) {
	field( name ).value = value;
	field( name ).dispatchEvent( new Event( 'input' ) );
}

function submit() {
	drawer().querySelector( 'form' ).dispatchEvent( new Event( 'submit', { cancelable: true } ) );
}

async function mountWith( rows = SEED ) {
	document.body.innerHTML = `
		<div class="wrap ep-app" data-ep-screen="eventos-probolsas-tipos">
			<div data-ep-header-actions></div>
			<div id="ep-event-types" class="ep-mount" aria-busy="true"></div>
		</div>`;
	screen = document.querySelector( '[data-ep-screen]' );
	api = {
		get: vi.fn( async () => rows ),
		post: vi.fn(),
		put: vi.fn(),
		del: vi.fn(),
	};
	await mount( screen, config, { api } );
}

beforeEach( async () => {
	vi.clearAllMocks();
	await mountWith();
} );

describe( 'pantalla Tipos de evento: tabla', () => {
	it( 'tiene 5 columnas con Acciones primero (R-06, R-19) y ordena por posición', () => {
		const headers = [ ...screen.querySelectorAll( 'thead th' ) ].map( ( th ) => th.textContent );
		expect( headers ).toEqual( [ 'Acciones', 'Tipo', 'Requiere adjunto', 'Eventos', 'Orden' ] );

		const names = [ ...screen.querySelectorAll( 'tbody .ep-badge' ) ].map( ( badge ) => badge.textContent );
		expect( names ).toEqual( [ 'Cumpleaños', 'Capacitaciones', 'Reuniones laborales' ] );
		expect( screen.querySelector( '#ep-event-types' ).hasAttribute( 'aria-busy' ) ).toBe( false );
	} );

	it( 'el badge usa el color, el ícono y el text_tone de la API (R-02)', () => {
		const [ birthday, training ] = screen.querySelectorAll( 'tbody .ep-badge' );

		expect( birthday.style.getPropertyValue( '--ep-badge-color' ) ).toBe( '#9D174D' );
		expect( birthday.querySelector( 'i' ).className ).toBe( 'fa-solid fa-cake-candles' );
		expect( birthday.classList.contains( 'ep-badge--light-text' ) ).toBe( true );
		expect( training.classList.contains( 'ep-badge--dark-text' ) ).toBe( true );
	} );

	it( 'muestra Sí/No con ícono, el conteo de eventos y el orden', () => {
		const cells = [ ...screen.querySelectorAll( 'tbody tr:last-child td' ) ].slice( 1 ).map( ( td ) => td.textContent );
		expect( cells ).toEqual( [ 'Reuniones laborales', 'No', '3', '4' ] );
		expect( screen.querySelector( 'tbody tr:first-child .ep-flag' ).classList.contains( 'is-on' ) ).toBe( true );
	} );

	it( 'las acciones son botones de ícono con nombre accesible y tooltip (R-21)', () => {
		const edit = rowAction( 0, 'Editar' );
		expect( edit.getAttribute( 'data-ep-tooltip' ) ).toBe( 'Editar' );
		expect( rowAction( 0, 'Eliminar' ) ).not.toBeNull();
	} );

	it( 'pagina en el cliente con 25 por defecto y selector 25/50/100 (R-23)', async () => {
		await mountWith( Array.from( { length: 30 }, ( _, index ) => eventType( { id: index + 1, name: `Tipo ${ index + 1 }`, sort_order: index + 1 } ) ) );

		expect( screen.querySelectorAll( 'tbody tr' ) ).toHaveLength( 25 );
		expect( [ ...screen.querySelectorAll( '.ep-data-table__page-size option' ) ].map( ( option ) => option.value ) ).toEqual( [ '25', '50', '100' ] );
		expect( screen.querySelector( '.ep-pagination__compact' ).textContent ).toBe( 'Página 1 de 2' );
	} );

	it( 'sin tipos muestra el estado vacío y deshabilita Ordenar', async () => {
		await mountWith( [] );

		expect( screen.querySelector( '.ep-empty-state__title' ).textContent ).toBe( 'Todavía no hay tipos de evento' );
		expect( headerButton( 'Ordenar' ).disabled ).toBe( true );
		expect( headerButton( 'Añadir tipo' ).disabled ).toBe( false );
	} );

	it( 'si la lista no carga, muestra el error y deshabilita las acciones', async () => {
		document.body.innerHTML = `
			<div class="wrap ep-app" data-ep-screen="eventos-probolsas-tipos">
				<div data-ep-header-actions></div>
				<div id="ep-event-types"></div>
			</div>`;
		screen = document.querySelector( '[data-ep-screen]' );
		await mount( screen, config, { api: { get: vi.fn( async () => Promise.reject( new ApiError( 'Sin conexión' ) ) ) } } );

		expect( screen.querySelector( '.ep-empty-state__title' ).textContent ).toBe( 'No se pudo cargar la información' );
		expect( headerButton( 'Añadir tipo' ).disabled ).toBe( true );
	} );
} );

describe( 'pantalla Tipos de evento: crear y editar', () => {
	it( 'el formulario toma las reglas de epConfig.rules.event_type (R-24)', () => {
		headerButton( 'Añadir tipo' ).click();

		expect( field( 'name' ).maxLength ).toBe( 100 );
		expect( field( 'name' ).getAttribute( 'aria-required' ) ).toBe( 'true' );
		expect( field( 'description' ).maxLength ).toBe( 500 );
		expect( field( 'description' ).getAttribute( 'aria-required' ) ).toBeNull();
		expect( drawer().querySelector( '.ep-field__counter' ).textContent ).toBe( '0/100' );
		expect( [ ...drawer().querySelectorAll( '.ep-color-field__swatch' ) ].map( ( swatch ) => swatch.dataset.color ) ).toEqual( COLOR_PRESETS );
		expect( [ ...drawer().querySelectorAll( '.ep-icon-picker__option' ) ] ).toHaveLength( 3 );
	} );

	it( 'no envía si faltan datos y lleva el foco al primer campo con error', () => {
		headerButton( 'Añadir tipo' ).click();
		submit();

		expect( api.post ).not.toHaveBeenCalled();
		expect( document.activeElement ).toBe( field( 'name' ) );
		const errors = [ ...drawer().querySelectorAll( '.ep-field__error:not([hidden])' ) ].map( ( error ) => error.textContent );
		expect( errors ).toEqual( [ 'El campo «Nombre» es obligatorio.', 'El campo «Ícono» es obligatorio.' ] );
	} );

	it( 'crea un tipo, lo agrega a la tabla y confirma con un toast (R-20)', async () => {
		api.post.mockResolvedValue( eventType( { id: 9, name: 'Pausas activas', slug: 'pausas-activas', color: '#0E7490', icon: 'briefcase', requires_attachment: true, sort_order: 5 } ) );
		headerButton( 'Añadir tipo' ).click();

		type( 'name', '  Pausas activas ' );
		drawer().querySelector( '[data-color="#0E7490"]' ).click();
		drawer().querySelector( '[data-key="briefcase"]' ).click();
		field( 'requires_attachment' ).click();
		submit();
		await flush();

		expect( api.post ).toHaveBeenCalledWith(
			'event-types',
			{ name: 'Pausas activas', description: '', color: '#0E7490', icon: 'briefcase', requires_attachment: true },
			{ silent: true }
		);
		expect( drawer() ).toBeNull();
		expect( screen.querySelector( 'tbody tr:last-child .ep-badge' ).textContent ).toBe( 'Pausas activas' );
		expect( toast.success ).toHaveBeenCalledWith( 'Tipo de evento «Pausas activas» creado.' );
	} );

	it( 'la vista previa sigue lo que se escribe y conserva el último color válido (QA-030)', () => {
		headerButton( 'Añadir tipo' ).click();
		const preview = () => drawer().querySelector( '.ep-badge-preview .ep-badge' );
		expect( preview().textContent ).toBe( 'Nombre del tipo' );

		type( 'name', 'Integración' );
		drawer().querySelector( '[data-key="cake-candles"]' ).click();
		type( 'color', '#FDE68A' );
		expect( preview().textContent ).toBe( 'Integración' );
		expect( preview().querySelector( 'i' ).className ).toBe( 'fa-solid fa-cake-candles' );
		expect( preview().classList.contains( 'ep-badge--dark-text' ) ).toBe( true );

		type( 'color', '#15' );
		expect( preview().style.getPropertyValue( '--ep-badge-color' ) ).toBe( '#FDE68A' );
	} );

	it( 'muestra los errores 422 bajo cada campo y el mensaje en un toast; el error se mantiene al salir del campo', async () => {
		api.post.mockRejectedValue(
			new ApiError( 'Revisa los campos marcados.', { status: 422, code: 'eventos_validation_failed', fieldErrors: { name: [ 'Ya existe un tipo de evento llamado «Cumpleaños».' ] } } )
		);
		headerButton( 'Añadir tipo' ).click();
		type( 'name', 'CUMPLEANOS' );
		drawer().querySelector( '[data-key="cake-candles"]' ).click();
		submit();
		await flush();

		const error = field( 'name' ).closest( '.ep-field' ).querySelector( '.ep-field__error' );
		expect( error.textContent ).toBe( 'Ya existe un tipo de evento llamado «Cumpleaños».' );
		expect( toast.error ).toHaveBeenCalledWith( 'Revisa los campos marcados.' );
		expect( document.activeElement ).toBe( field( 'name' ) );

		field( 'name' ).dispatchEvent( new Event( 'blur' ) );
		expect( error.hidden ).toBe( false );
		expect( drawer() ).not.toBeNull();
	} );

	it( 'edita con los datos actuales y muestra identificador y fechas en es-CO (§6.3)', async () => {
		api.put.mockResolvedValue( eventType( { name: 'Cumpleaños del mes', updated_at: '2026-10-08T16:00:00-05:00' } ) );
		rowAction( 0, 'Editar' ).click();

		expect( field( 'name' ).value ).toBe( 'Cumpleaños' );
		expect( field( 'color' ).value ).toBe( '#9D174D' );
		expect( field( 'requires_attachment' ).checked ).toBe( true );
		expect( drawer().querySelector( '[aria-checked="true"]' ).dataset.key ).toBe( 'cake-candles' );
		const note = drawer().querySelector( '.ep-drawer__note' ).textContent;
		expect( note ).toContain( 'cumpleanos' );
		expect( note ).toContain( 'Creado el 07/10/2026 09:15 a. m. · Modificado el 08/10/2026 03:30 p. m.' );

		type( 'name', 'Cumpleaños del mes' );
		submit();
		await flush();

		expect( api.put ).toHaveBeenCalledWith( 'event-types/1', expect.objectContaining( { name: 'Cumpleaños del mes', requires_attachment: true } ), { silent: true } );
		expect( screen.querySelector( 'tbody tr:first-child .ep-badge' ).textContent ).toBe( 'Cumpleaños del mes' );
		expect( toast.success ).toHaveBeenCalledWith( 'Cambios guardados en «Cumpleaños del mes».' );
	} );

	it( 'si el tipo ya no existe al guardar, avisa, cierra el panel y recarga la lista', async () => {
		api.put.mockRejectedValue( new ApiError( 'El tipo de evento no existe.', { status: 404, code: 'eventos_not_found' } ) );
		rowAction( 0, 'Editar' ).click();
		submit();
		await flush();

		expect( toast.error ).toHaveBeenCalledWith( 'El tipo de evento no existe.' );
		expect( drawer() ).toBeNull();
		expect( api.get ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'pide confirmación antes de cerrar con cambios sin guardar', async () => {
		headerButton( 'Añadir tipo' ).click();
		type( 'name', 'Borrador' );

		drawer().querySelector( 'button[aria-label="Cerrar"]' ).click();
		await flush();
		expect( dialog().textContent ).toContain( '¿Descartar los cambios?' );

		buttonIn( dialog(), 'Descartar' ).click();
		await flush();
		expect( drawer() ).toBeNull();
	} );
} );

describe( 'pantalla Tipos de evento: eliminar y ordenar', () => {
	it( 'un tipo con eventos no se elimina: avisa cuántos tiene sin llamar al servidor', () => {
		rowAction( 2, 'Eliminar' ).click();

		expect( toast.warning ).toHaveBeenCalledWith( 'No se puede eliminar «Reuniones laborales» porque tiene 3 eventos asociados.' );
		expect( dialog() ).toBeNull();
		expect( api.del ).not.toHaveBeenCalled();
	} );

	it( 'elimina tras confirmar y confirma con un toast', async () => {
		api.del.mockResolvedValue( { deleted: true, id: 1 } );
		rowAction( 0, 'Eliminar' ).click();
		expect( dialog().textContent ).toContain( '¿Eliminar el tipo «Cumpleaños»?' );

		buttonIn( dialog(), 'Eliminar' ).click();
		await flush();

		expect( api.del ).toHaveBeenCalledWith( 'event-types/1', { silent: true } );
		expect( [ ...screen.querySelectorAll( 'tbody .ep-badge' ) ].map( ( badge ) => badge.textContent ) ).toEqual( [ 'Capacitaciones', 'Reuniones laborales' ] );
		expect( toast.success ).toHaveBeenCalledWith( 'Tipo de evento «Cumpleaños» eliminado.' );
	} );

	it( 'cancelar no elimina', async () => {
		rowAction( 0, 'Eliminar' ).click();
		buttonIn( dialog(), 'Cancelar' ).click();
		await flush();

		expect( api.del ).not.toHaveBeenCalled();
	} );

	it( 'si el servidor responde 409 (el conteo cambió), muestra su mensaje y recarga la lista', async () => {
		api.del.mockRejectedValue( new ApiError( 'No se puede eliminar «Cumpleaños» porque tiene 1 evento asociado.', { status: 409, code: 'eventos_conflict' } ) );
		rowAction( 0, 'Eliminar' ).click();
		buttonIn( dialog(), 'Eliminar' ).click();
		await flush();

		expect( toast.warning ).toHaveBeenCalledWith( 'No se puede eliminar «Cumpleaños» porque tiene 1 evento asociado.' );
		expect( toast.error ).not.toHaveBeenCalled();
		expect( api.get ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'ordena con el panel y guarda el orden completo (PUT /event-types/order)', async () => {
		const reordered = [ eventType( { id: 2, name: 'Capacitaciones', sort_order: 1 } ), eventType( { sort_order: 2 } ), eventType( { id: 4, name: 'Reuniones laborales', sort_order: 3 } ) ];
		api.put.mockResolvedValue( reordered );
		headerButton( 'Ordenar' ).click();

		drawer().querySelector( 'button[aria-label="Bajar «Cumpleaños»"]' ).click();
		buttonIn( drawer(), 'Guardar orden' ).click();
		await flush();

		expect( api.put ).toHaveBeenCalledWith( 'event-types/order', { ids: [ 2, 1, 4 ] }, { silent: true } );
		expect( [ ...screen.querySelectorAll( 'tbody .ep-badge' ) ].map( ( badge ) => badge.textContent ) ).toEqual( [ 'Capacitaciones', 'Cumpleaños', 'Reuniones laborales' ] );
	} );
} );

describe( 'pantalla Tipos de evento: contrato con la página PHP', () => {
	// El URL global es el de happy-dom, que no resuelve rutas file:.
	const read = ( path ) => readFileSync( new NodeURL( `../../../${ path }`, import.meta.url ), 'utf8' );

	it( 'admin.js monta esta pantalla con el slug y el punto de montaje de EventTypesPage', () => {
		const page = read( 'src/Domains/EventType/Presentation/EventTypesPage.php' );
		const slug = /const SLUG = '([^']+)'/.exec( page )[ 1 ];
		const mountId = /'id' => '([^']+)'/.exec( page )[ 1 ];

		expect( read( 'assets/src/js/pages/admin.js' ) ).toContain( `'${ slug }': () => import( '../screens/event-types.js' )` );
		expect( read( 'assets/src/js/screens/event-types.js' ) ).toContain( `querySelector( '#${ mountId }' )` );
	} );
} );
