import { describe, expect, it } from 'vitest';
import fixture from '../../fixtures/date-formatting.json';
import { createDateFormatter } from '../../../assets/src/js/core/date.js';

const formatter = createDateFormatter( fixture.ui );

// Casos compartidos con DateFormatterTest.php: el navegador debe mostrar lo mismo que el servidor (R-07).
describe( 'createDateFormatter (casos compartidos con PHP)', () => {
	it.each( fixture.datetimes )( 'momento de auditoría: $case', ( { iso, date, datetime } ) => {
		expect( formatter.formatDate( iso ) ).toBe( date );
		expect( formatter.formatDateTime( iso ) ).toBe( datetime );
	} );

	it.each( fixture.datetimes )( 'acepta también UTC con Z: $case', ( { utc, datetime } ) => {
		expect( formatter.formatDateTime( `${ utc.replace( ' ', 'T' ) }Z` ) ).toBe( datetime );
	} );

	it.each( fixture.calendar_dates )( 'fecha de calendario $value', ( { value, formatted } ) => {
		expect( formatter.formatCalendarDate( value ) ).toBe( formatted );
	} );

	it.each( fixture.calendar_times )( 'hora de calendario $value en 12 h', ( { value, formatted } ) => {
		expect( formatter.formatCalendarTime( value ) ).toBe( formatted );
	} );

	it.each( fixture.invalid_calendar_times )( 'rechaza la hora «%s»', ( value ) => {
		expect( () => formatter.formatCalendarTime( value ) ).toThrow( /Hora no válida/ );
	} );

	it.each( fixture.today )( 'today(): $case', ( { now_utc: nowUtc, today } ) => {
		expect( formatter.today( new Date( `${ nowUtc.replace( ' ', 'T' ) }Z` ) ) ).toBe( today );
	} );

	it( 'rechaza momentos sin zona horaria (serían ambiguos)', () => {
		expect( () => formatter.formatDateTime( '2026-10-01 10:00:00' ) ).toThrow( /zona horaria/ );
	} );

	it( 'rechaza fechas de calendario inexistentes o con otro formato', () => {
		for ( const value of [ '01/10/2026', '2026-02-29', '2026-13-01', '0000-01-01', '2026-10-07T00:00:00' ] ) {
			expect( () => formatter.formatCalendarDate( value ), value ).toThrow( /Fecha no válida/ );
		}
		expect( formatter.formatCalendarDate( '2028-02-29' ) ).toBe( '29/02/2028' );
	} );
} );

describe( 'las fechas de calendario no dependen de la zona del equipo (R-08)', () => {
	it( 'formatea igual sin importar la zona del proceso', () => {
		// Las fechas y horas de calendario se tratan como texto: no pasan por Date.
		expect( formatter.formatCalendarDate( '2026-10-07' ) ).toBe( '07/10/2026' );
		expect( formatter.formatCalendarTime( '15:00' ) ).toBe( '03:00 p. m.' );
	} );
} );
