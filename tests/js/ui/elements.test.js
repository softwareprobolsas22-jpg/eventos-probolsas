// @vitest-environment happy-dom
import { describe, expect, it, vi } from 'vitest';
import { eventTypeBadge } from '../../../assets/src/js/ui/badge.js';
import { button, iconButton } from '../../../assets/src/js/ui/button.js';
import { loadError } from '../../../assets/src/js/ui/load-state.js';

describe( 'eventTypeBadge (R-02)', () => {
	it( 'usa el text_tone que entrega la API, el color y el ícono del tipo', () => {
		const badge = eventTypeBadge( { name: 'Capacitaciones', color: '#155728', icon: 'graduation-cap', text_tone: 'light' } );

		expect( badge.classList.contains( 'ep-badge--light-text' ) ).toBe( true );
		expect( badge.style.getPropertyValue( '--ep-badge-color' ) ).toBe( '#155728' );
		expect( badge.querySelector( 'i' ).className ).toBe( 'fa-solid fa-graduation-cap' );
		expect( badge.querySelector( '.ep-badge__label' ).textContent ).toBe( 'Capacitaciones' );
	} );

	it( 'sin text_tone (vista previa del formulario) lo calcula con la misma fórmula del servidor', () => {
		expect( eventTypeBadge( { name: 'Nuevo', color: '#669F30' } ).classList.contains( 'ep-badge--dark-text' ) ).toBe( true );
		expect( eventTypeBadge( { name: 'Nuevo', color: '#669F30', text_tone: 'otro' } ).classList.contains( 'ep-badge--dark-text' ) ).toBe( true );
	} );

	it( 'trunca nombres largos con un ancho máximo opcional y nunca interpreta HTML', () => {
		const badge = eventTypeBadge( { name: '<b>Reunión</b>', color: '#1D4ED8', maxWidth: '12rem' } );

		expect( badge.style.getPropertyValue( '--ep-truncate-width' ) ).toBe( '12rem' );
		expect( badge.querySelector( '.ep-truncate' ).textContent ).toBe( '<b>Reunión</b>' );
		expect( badge.querySelector( 'b' ) ).toBeNull();
		expect( badge.querySelector( 'i' ) ).toBeNull();
	} );
} );

describe( 'botones', () => {
	it( 'el botón de ícono tiene nombre accesible y tooltip con el mismo texto (CP-1.21)', () => {
		const onClick = vi.fn();
		const element = iconButton( { icon: 'fa-solid fa-trash', label: 'Eliminar', variant: 'danger', onClick, attrs: { 'data-extra': 'x' } } );

		expect( element.type ).toBe( 'button' );
		expect( element.getAttribute( 'aria-label' ) ).toBe( 'Eliminar' );
		expect( element.getAttribute( 'data-ep-tooltip' ) ).toBe( 'Eliminar' );
		expect( element.getAttribute( 'data-extra' ) ).toBe( 'x' );
		expect( element.classList.contains( 'ep-icon-button--danger' ) ).toBe( true );

		element.click();
		expect( onClick ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'el botón de ícono por defecto no lleva variante y puede ir deshabilitado', () => {
		const element = iconButton( { icon: 'fa-solid fa-pen', label: 'Editar', disabled: true } );

		expect( element.className ).toBe( 'ep-icon-button' );
		expect( element.disabled ).toBe( true );
	} );

	it( 'el botón con texto admite variante, tamaño, tipo e ícono opcional', () => {
		const onClick = vi.fn();
		const primary = button( { label: 'Guardar', icon: 'fa-solid fa-floppy-disk', variant: 'primary', size: 'sm', type: 'submit', onClick } );
		const plain = button( { label: 'Cancelar' } );

		expect( primary.type ).toBe( 'submit' );
		expect( [ ...primary.classList ] ).toEqual( [ 'ep-button', 'ep-button--primary', 'ep-button--sm' ] );
		expect( primary.textContent ).toBe( 'Guardar' );
		primary.click();
		expect( onClick ).toHaveBeenCalledTimes( 1 );

		expect( [ ...plain.classList ] ).toEqual( [ 'ep-button', 'ep-button--secondary' ] );
		expect( plain.querySelector( 'i' ) ).toBeNull();
	} );
} );

describe( 'loadError', () => {
	it( 'anuncia el error y sugiere recargar', () => {
		const element = loadError();

		expect( element.getAttribute( 'role' ) ).toBe( 'status' );
		expect( element.querySelector( '.ep-empty-state__title' ).textContent ).toBe( 'No se pudo cargar la información' );
		expect( loadError( 'No se pudieron cargar los tipos' ).querySelector( '.ep-empty-state__title' ).textContent ).toBe( 'No se pudieron cargar los tipos' );
	} );
} );
