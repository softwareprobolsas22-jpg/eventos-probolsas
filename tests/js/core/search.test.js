import { describe, expect, it } from 'vitest';
import fixture from '../../fixtures/text-normalization.json';
import { matchesQuery, matchesTokens, normalizeText, queryTokens, searchableText } from '../../../assets/src/js/core/search.js';

// Casos compartidos con TextNormalizerTest.php: ambas implementaciones deben coincidir.
describe( 'normalizeText (casos compartidos con PHP)', () => {
	it.each( [ ...fixture.normalize, ...fixture.normalize_decomposed ] )( '«$input» → «$expected»', ( { input, expected } ) => {
		expect( normalizeText( input ) ).toBe( expected );
	} );

	it( 'tolera valores nulos o numéricos', () => {
		expect( normalizeText( null ) ).toBe( '' );
		expect( normalizeText( 2026 ) ).toBe( '2026' );
	} );
} );

describe( 'matchesQuery (casos compartidos con PHP)', () => {
	it.each( fixture.matches )( '«$query» en «$text» → $expected', ( { text, query, expected } ) => {
		expect( matchesQuery( text, query ) ).toBe( expected );
	} );

	it( 'busca en varios campos a la vez (código y nombre)', () => {
		expect( matchesQuery( [ 'PR-GC-001', 'Procedimiento de compras' ], 'gc-001 compras' ) ).toBe( true );
	} );
} );

describe( 'queryTokens, searchableText y matchesTokens (búsqueda sobre texto ya normalizado)', () => {
	it( 'dan el mismo resultado que matchesQuery', () => {
		const haystack = searchableText( [ 'PR-GC-001', 'PROCEDIMIENTO DE COMPRAS', null ] );

		expect( haystack ).toBe( 'pr-gc-001 procedimiento de compras' );
		expect( queryTokens( '  Procedimiento   COMPRAS ' ) ).toEqual( [ 'procedimiento', 'compras' ] );
		expect( matchesTokens( haystack, queryTokens( 'procedimiento' ) ) ).toBe( true );
		expect( matchesTokens( haystack, queryTokens( 'gestión' ) ) ).toBe( false );
		expect( matchesTokens( haystack, [] ) ).toBe( true );
	} );
} );
