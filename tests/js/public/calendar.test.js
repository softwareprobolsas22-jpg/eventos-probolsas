// @vitest-environment happy-dom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { calendarDate, createCalendar, hideDecorativeIcons, MOBILE_QUERY } from '../../../assets/src/js/public/calendar.js';
import { createDateFormatter } from '../../../assets/src/js/core/date.js';

const UI = { timezone: 'America/Bogota', date_format: 'd/m/Y', time_format: 'h:i', meridiem: { am: 'a. m.', pm: 'p. m.' } };
const CONFIG = { today: '2026-10-07', firstDay: 1, ui: UI, restUrl: '/wp-json/eventos/v1/', restNonce: 'n' };

const TYPES = [
	{ id: 1, name: 'Cumpleaños', color: '#9D174D', icon: 'cake-candles', text_tone: 'light' },
	{ id: 2, name: 'Capacitaciones', color: '#155728', icon: 'graduation-cap', text_tone: 'light' },
	{ id: 3, name: 'Reuniones laborales', color: '#1D4ED8', icon: 'briefcase', text_tone: 'light' },
];

/**
 * FullCalendar simulado: guarda las opciones y las llamadas.
 */
class FakeCalendar {
	static last = null;

	constructor( host, options ) {
		this.host = host;
		this.options = { ...options };
		this.calls = [];
		FakeCalendar.last = this;
	}

	render() {
		this.calls.push( [ 'render' ] );
	}

	refetchEvents() {
		this.calls.push( [ 'refetchEvents' ] );
	}

	setOption( name, value ) {
		this.options[ name ] = value;
		this.calls.push( [ 'setOption', name, value ] );
	}

	changeView( view, date ) {
		this.calls.push( [ 'changeView', view, date ] );
	}

	updateSize() {
		this.calls.push( [ 'updateSize' ] );
	}

	destroy() {
		this.calls.push( [ 'destroy' ] );
	}
}

/**
 * ResizeObserver simulado: permite disparar el cambio de tamaño.
 */
class FakeResizeObserver {
	static last = null;

	constructor( callback ) {
		this.callback = callback;
		this.disconnected = false;
		FakeResizeObserver.last = this;
	}

	observe( target ) {
		this.target = target;
	}

	disconnect() {
		this.disconnected = true;
	}
}

/**
 * Monta el calendario con dependencias simuladas.
 *
 * @param {Object} [options] Opciones.
 * @returns {Object} Calendario, API, filtro y contenedor.
 */
function setup( { props = {}, mobile = false, now = '2026-10-08T01:30:00Z', types = TYPES, failTypes = false, events = [], onOpen = null } = {} ) {
	const element = document.createElement( 'div' );
	element.className = 'ep-public';
	element.setAttribute( 'aria-busy', 'true' );
	element.textContent = 'Cargando…';
	document.body.append( element );

	const api = {
		get: vi.fn( ( path ) => {
			if ( path.startsWith( 'event-types' ) ) {
				return failTypes ? Promise.reject( new Error( 'Sin conexión' ) ) : Promise.resolve( types );
			}
			return Promise.resolve( events );
		} ),
	};
	const filter = { onChange: null, options: null };
	const clock = { now: new Date( now ) };

	const mounted = createCalendar( element, props, { config: CONFIG, dates: createDateFormatter( UI ) }, {
		CalendarClass: FakeCalendar,
		api,
		matchMedia: ( query ) => ( { matches: MOBILE_QUERY === query && mobile } ),
		clock: () => clock.now,
		onOpen,
		ResizeObserverClass: FakeResizeObserver,
		typeFilter: ( host, options, onChange ) => {
			filter.options = options;
			filter.onChange = onChange;
		},
	} );

	return { mounted, element, api, filter, clock, calendar: FakeCalendar.last };
}

/**
 * Pide eventos como lo haría FullCalendar.
 *
 * @param {FakeCalendar} calendar Calendario.
 * @param {string} start Inicio ISO (UTC).
 * @param {string} end Fin ISO (UTC).
 * @returns {Promise<Object[]>} Eventos entregados.
 */
function fetchRange( calendar, start, end ) {
	return new Promise( ( resolve, reject ) => {
		calendar.options.events( { start: new Date( start ), end: new Date( end ) }, resolve, reject );
	} );
}

const flush = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

describe( 'widget del calendario (H-302)', () => {
	afterEach( () => {
		document.body.replaceChildren();
		vi.useRealTimers();
	} );

	it( 'configura FullCalendar sin conversión de zona y con el «hoy» de Bogotá (R-08, RL-02)', () => {
		const { calendar, element } = setup();

		expect( calendar.options.timeZone ).toBe( 'UTC' );
		// 2026-10-08 01:30 UTC = 7 de octubre, 8:30 p. m. en Bogotá.
		expect( calendar.options.now ).toBe( '2026-10-07T20:30:00' );
		expect( calendar.options.locale.code ).toBe( 'es' );
		expect( calendar.options.firstDay ).toBe( 1 );
		expect( calendar.options.headerToolbar.end ).toBe( 'dayGridMonth,listMonth' );
		expect( calendar.calls[ 0 ] ).toEqual( [ 'render' ] );
		expect( element.classList.contains( 'ep-calendar' ) ).toBe( true );
		expect( element.textContent ).not.toContain( 'Cargando' );
	} );

	it( 'si el reloj del equipo está atrasado, «hoy» no es anterior al del servidor', () => {
		const { calendar } = setup( { now: '2026-10-05T15:00:00Z' } );

		expect( calendar.options.now ).toBe( '2026-10-07T00:00:00' );
	} );

	it( 'la semana empieza el día que configura WordPress (RL-06)', () => {
		CONFIG.firstDay = 0;
		try {
			expect( setup().calendar.options.firstDay ).toBe( 0 );
		} finally {
			CONFIG.firstDay = 1;
		}
	} );

	it( 'en móvil empieza en la lista; en escritorio, en el mes (R-11)', () => {
		expect( setup( { mobile: true } ).calendar.options.initialView ).toBe( 'listMonth' );
		expect( setup( { mobile: false } ).calendar.options.initialView ).toBe( 'dayGridMonth' );
	} );

	it( 'pide el rango visible como fechas sin zona y entrega los eventos', async () => {
		const events = [ { id: '1', title: 'Cumpleaños de Ana', start: '2026-10-07T15:00:00' } ];
		const { calendar, api } = setup( { events } );

		const received = await fetchRange( calendar, '2026-09-28T00:00:00Z', '2026-11-09T00:00:00Z' );

		expect( api.get ).toHaveBeenCalledWith( 'calendar?start=2026-09-28&end=2026-11-09' );
		expect( received ).toEqual( events );
	} );

	it( 'filtra por los tipos elegidos o, si no hay, por los del shortcode', async () => {
		const { calendar, api, filter } = setup( { props: { types: [ 1, 3 ] } } );
		await flush();

		expect( filter.options.map( ( type ) => type.id ) ).toEqual( [ 1, 3 ] );

		await fetchRange( calendar, '2026-10-01T00:00:00Z', '2026-11-01T00:00:00Z' );
		expect( api.get ).toHaveBeenLastCalledWith( 'calendar?start=2026-10-01&end=2026-11-01&types%5B%5D=1&types%5B%5D=3' );

		filter.onChange( [ 3 ] );
		expect( calendar.calls ).toContainEqual( [ 'refetchEvents' ] );
		await fetchRange( calendar, '2026-10-01T00:00:00Z', '2026-11-01T00:00:00Z' );
		expect( api.get ).toHaveBeenLastCalledWith( 'calendar?start=2026-10-01&end=2026-11-01&types%5B%5D=3' );
	} );

	it( 'sin al menos dos tipos no muestra el filtro', async () => {
		const { filter } = setup( { props: { types: [ 2 ] } } );
		await flush();

		expect( filter.onChange ).toBeNull();
	} );

	it( 'si no carga los tipos, el calendario sigue sin filtro', async () => {
		const { element, filter, api } = setup( { failTypes: true } );
		await flush();

		expect( api.get ).toHaveBeenCalledWith( 'event-types', { silent: true } );
		expect( filter.onChange ).toBeNull();
		expect( element.querySelector( '.ep-calendar__filters' ) ).toBeNull();
		expect( element.querySelector( '.ep-calendar__body' ) ).not.toBeNull();
	} );

	it( 'un error de la API llega a FullCalendar para que deje de cargar', async () => {
		const { calendar, api } = setup();
		const error = new Error( 'Sin conexión' );
		api.get.mockImplementation( () => Promise.reject( error ) );

		await expect( fetchRange( calendar, '2026-10-01T00:00:00Z', '2026-11-01T00:00:00Z' ) ).rejects.toBe( error );
	} );

	it( 'muestra la hora en 12 h es-CO (R-07)', () => {
		const { calendar } = setup();

		expect( calendar.options.eventTimeFormat( { date: { hour: 15, minute: 0 } } ) ).toBe( '03:00 p. m.' );
		expect( calendar.options.eventTimeFormat( { date: { hour: 9, minute: 5 } } ) ).toBe( '09:05 a. m.' );
	} );

	it( 'cada evento lleva el ícono de su tipo, la hora en el mes y el título truncable', () => {
		const { calendar } = setup();
		const arg = ( view, timeText ) => ( { event: { title: 'Cumpleaños de Ana', extendedProps: { icon: 'cake-candles' } }, timeText, view: { type: view } } );

		const month = calendar.options.eventContent( arg( 'dayGridMonth', '03:00 p. m.' ) ).domNodes[ 0 ];
		expect( month.querySelector( '.fa-cake-candles' ) ).not.toBeNull();
		expect( month.querySelector( '.ep-calendar__event-time' ).textContent ).toBe( '03:00 p. m.' );
		expect( month.querySelector( '.ep-calendar__event-title.ep-truncate' ).textContent ).toBe( 'Cumpleaños de Ana' );

		const list = calendar.options.eventContent( arg( 'listMonth', '03:00 p. m.' ) ).domNodes[ 0 ];
		expect( list.querySelector( '.ep-calendar__event-time' ) ).toBeNull();
		expect( list.querySelector( '.ep-calendar__event-title' ).classList.contains( 'ep-truncate' ) ).toBe( false );
	} );

	it( 'el número del día abre la lista de ese día (RL-01)', () => {
		const { calendar } = setup();
		const day = new Date( '2026-10-07T00:00:00Z' );

		calendar.options.navLinkDayClick( day );

		expect( calendar.calls ).toContainEqual( [ 'changeView', 'listDay', day ] );
	} );

	it( 'los eventos se abren con clic o teclado cuando hay detalle', () => {
		const onOpen = vi.fn();
		const { calendar } = setup( { onOpen } );
		const preventDefault = vi.fn();
		const el = document.createElement( 'a' );

		calendar.options.eventClick( { event: { id: '42' }, el, jsEvent: { preventDefault } } );

		expect( calendar.options.eventInteractive ).toBe( true );
		expect( preventDefault ).toHaveBeenCalled();
		expect( onOpen ).toHaveBeenCalledWith( 42, el );
		expect( setup().calendar.options.eventInteractive ).toBe( false );
	} );

	it( 'se reajusta cuando cambia el ancho del contenedor, no solo el de la ventana (R-13)', () => {
		const { calendar, mounted } = setup();
		const observer = FakeResizeObserver.last;
		let width = 800;
		Object.defineProperty( observer.target, 'clientWidth', { get: () => width } );

		observer.callback();
		observer.callback();
		width = 783;
		observer.callback();

		expect( calendar.calls.filter( ( [ name ] ) => 'updateSize' === name ) ).toHaveLength( 2 );
		mounted.destroy();
		expect( observer.disconnected ).toBe( true );
	} );

	it( 'accesibilidad de FullCalendar: «+N más» es un botón y los íconos de flecha son decorativos (R-16)', () => {
		const { calendar } = setup();
		const more = document.createElement( 'a' );
		calendar.options.moreLinkDidMount( { el: more } );
		expect( more.getAttribute( 'role' ) ).toBe( 'button' );

		const host = document.createElement( 'div' );
		const arrow = document.createElement( 'span' );
		arrow.className = 'fc-icon fc-icon-chevron-left';
		arrow.setAttribute( 'role', 'img' );
		host.append( arrow );
		hideDecorativeIcons( host );
		expect( arrow.hasAttribute( 'role' ) ).toBe( false );
		expect( arrow.getAttribute( 'aria-hidden' ) ).toBe( 'true' );
		expect( typeof calendar.options.datesSet ).toBe( 'function' );
	} );

	it( 'anuncia la carga con aria-busy', () => {
		const { calendar, element } = setup();

		calendar.options.loading( true );
		expect( element.getAttribute( 'aria-busy' ) ).toBe( 'true' );
		calendar.options.loading( false );
		expect( element.getAttribute( 'aria-busy' ) ).toBe( 'false' );
	} );

	describe( '«hoy» con la página abierta después de medianoche (QA-005)', () => {
		beforeEach( () => {
			vi.useFakeTimers();
		} );

		it( 'cambia a las 00:00 de Bogotá sin recargar', () => {
			// 23:59:00 del 7 de octubre en Bogotá.
			const { calendar, clock } = setup( { now: '2026-10-08T04:59:00Z' } );
			expect( calendar.options.now ).toBe( '2026-10-07T23:59:00' );

			clock.now = new Date( '2026-10-08T05:00:01Z' );
			vi.advanceTimersByTime( 61 * 1000 );

			expect( calendar.calls ).toContainEqual( [ 'setOption', 'now', '2026-10-08T00:00:01' ] );
		} );

		it( 'al volver a la pestaña revisa el día (los temporizadores se pausan en segundo plano)', () => {
			const { calendar, clock } = setup( { now: '2026-10-08T01:30:00Z' } );

			clock.now = new Date( '2026-10-09T13:00:00Z' );
			document.dispatchEvent( new Event( 'visibilitychange' ) );

			expect( calendar.options.now ).toBe( '2026-10-09T08:00:00' );
		} );

		it( 'no vuelve a dibujar si el día no cambió', () => {
			const { calendar, mounted } = setup();

			mounted.refreshNow();

			expect( calendar.calls.filter( ( [ name ] ) => 'setOption' === name ) ).toEqual( [] );
		} );

		it( 'destroy() detiene el temporizador y el calendario', () => {
			const { calendar, mounted, clock } = setup( { now: '2026-10-08T04:59:00Z' } );

			mounted.destroy();
			clock.now = new Date( '2026-10-08T05:00:01Z' );
			vi.advanceTimersByTime( 120 * 1000 );
			document.dispatchEvent( new Event( 'visibilitychange' ) );

			expect( calendar.calls ).toContainEqual( [ 'destroy' ] );
			expect( calendar.calls.filter( ( [ name ] ) => 'setOption' === name ) ).toEqual( [] );
		} );
	} );
} );

describe( 'calendarDate', () => {
	it.each( [
		[ '2026-10-07T00:00:00Z', '2026-10-07' ],
		[ '2026-12-31T00:00:00Z', '2026-12-31' ],
		[ '2027-01-01T00:00:00Z', '2027-01-01' ],
	] )( '%s → %s, sin importar la zona del equipo', ( iso, expected ) => {
		expect( calendarDate( new Date( iso ) ) ).toBe( expected );
	} );
} );
