// @vitest-environment happy-dom
import { describe, expect, it, vi } from 'vitest';
import { escapeHtml, h, replaceContent } from '../../../assets/src/js/core/dom.js';

describe( 'h', () => {
	it( 'inserta el texto como texto, nunca como HTML', () => {
		const element = h( 'p', { text: '<img src=x onerror=alert(1)>' } );
		expect( element.querySelector( 'img' ) ).toBeNull();
		expect( element.textContent ).toBe( '<img src=x onerror=alert(1)>' );
	} );

	it( 'aplica clases, atributos, propiedades, estilos y eventos', () => {
		const onClick = vi.fn();
		const element = h(
			'button',
			{
				class: [ 'ep-button', false, 'ep-button--primary' ],
				type: 'button',
				attrs: { 'aria-label': 'Guardar', hidden: false, 'data-x': true },
				style: { '--ep-truncate-width': '10rem' },
				on: { click: onClick },
			},
			'Guardar'
		);

		element.click();
		expect( element.className ).toBe( 'ep-button ep-button--primary' );
		expect( element.type ).toBe( 'button' );
		expect( element.getAttribute( 'aria-label' ) ).toBe( 'Guardar' );
		expect( element.hasAttribute( 'hidden' ) ).toBe( false );
		expect( element.getAttribute( 'data-x' ) ).toBe( '' );
		expect( element.style.getPropertyValue( '--ep-truncate-width' ) ).toBe( '10rem' );
		expect( onClick ).toHaveBeenCalledOnce();
	} );

	it( 'ignora hijos vacíos y aplana listas', () => {
		const element = h( 'ul', {}, null, false, [ h( 'li', { text: 'a' } ), [ h( 'li', { text: 'b' } ) ] ] );
		expect( element.children ).toHaveLength( 2 );
	} );
} );

describe( 'replaceContent', () => {
	it( 'reemplaza el contenido ignorando los hijos condicionales vacíos (replaceChildren mostraría «false»)', () => {
		const element = h( 'p', {}, 'antes' );
		const hasWarning = false;

		replaceContent( element, h( 'span', { text: 'Código' } ), hasWarning && h( 'span', { text: 'aviso' } ), null, undefined, ' ok' );

		expect( element.textContent ).toBe( 'Código ok' );
	} );
} );

describe( 'escapeHtml', () => {
	it( 'escapa los caracteres especiales', () => {
		expect( escapeHtml( `<a href="x">'&'</a>` ) ).toBe( '&lt;a href=&quot;x&quot;&gt;&#039;&amp;&#039;&lt;/a&gt;' );
	} );
} );
