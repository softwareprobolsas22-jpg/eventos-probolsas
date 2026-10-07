import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { debounce, prefersReducedMotion } from '../../../assets/src/js/core/timing.js';

describe( 'debounce', () => {
	beforeEach( () => {
		vi.useFakeTimers();
	} );

	afterEach( () => {
		vi.useRealTimers();
	} );

	it( 'ejecuta una sola vez, con los últimos argumentos, tras la espera', () => {
		const fn = vi.fn();
		const search = debounce( fn, 300 );

		search( 'cu' );
		search( 'cum' );
		vi.advanceTimersByTime( 299 );
		expect( fn ).not.toHaveBeenCalled();

		search( 'cumple' );
		vi.advanceTimersByTime( 300 );
		expect( fn ).toHaveBeenCalledOnce();
		expect( fn ).toHaveBeenCalledWith( 'cumple' );
	} );

	it( 'cancel descarta la llamada pendiente', () => {
		const fn = vi.fn();
		const search = debounce( fn, 300 );

		search( 'x' );
		search.cancel();
		vi.advanceTimersByTime( 1000 );

		expect( fn ).not.toHaveBeenCalled();
	} );

	it( 'flush ejecuta de inmediato la llamada pendiente y solo una vez', () => {
		const fn = vi.fn();
		const search = debounce( fn, 300 );

		search( 'x' );
		search.flush();
		search.flush();
		vi.advanceTimersByTime( 1000 );

		expect( fn ).toHaveBeenCalledOnce();
		expect( fn ).toHaveBeenCalledWith( 'x' );
	} );
} );

describe( 'prefersReducedMotion (R-12)', () => {
	afterEach( () => {
		vi.unstubAllGlobals();
	} );

	it.each( [
		[ 'pide reducir el movimiento', true ],
		[ 'no lo pide', false ],
	] )( 'cuando el sistema %s', ( _label, matches ) => {
		vi.stubGlobal( 'matchMedia', vi.fn( () => ( { matches } ) ) );

		expect( prefersReducedMotion() ).toBe( matches );
		expect( globalThis.matchMedia ).toHaveBeenCalledWith( '(prefers-reduced-motion: reduce)' );
	} );

	it( 'sin matchMedia (entornos sin navegador) no reduce', () => {
		vi.stubGlobal( 'matchMedia', undefined );

		expect( prefersReducedMotion() ).toBe( false );
	} );
} );
