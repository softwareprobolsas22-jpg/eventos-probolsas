import { describe, expect, it } from 'vitest';
import { countActiveFilters, isActiveFilter } from '../../../assets/src/js/core/filtering.js';

describe( 'filtros activos', () => {
	it( 'un filtro está activo si tiene texto o algún extremo del rango', () => {
		expect( isActiveFilter( '' ) ).toBe( false );
		expect( isActiveFilter( '   ' ) ).toBe( false );
		expect( isActiveFilter( null ) ).toBe( false );
		expect( isActiveFilter( 'ana' ) ).toBe( true );
		expect( isActiveFilter( 0 ) ).toBe( true );
		expect( isActiveFilter( { from: '', to: '' } ) ).toBe( false );
		expect( isActiveFilter( { from: '2026-10-01', to: '' } ) ).toBe( true );
	} );

	it( 'cuenta los filtros activos', () => {
		expect( countActiveFilters( { search: 'ana', type: '', dates: { from: '', to: '2026-10-31' } } ) ).toBe( 2 );
	} );
} );
