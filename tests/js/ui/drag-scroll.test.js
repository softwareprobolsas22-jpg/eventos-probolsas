// @vitest-environment happy-dom
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { enableDragScroll } from '../../../assets/src/js/ui/drag-scroll.js';

let container;
let button;

function pointer( type, { x = 0, pointerType = 'mouse', target = container } = {} ) {
	target.dispatchEvent( new PointerEvent( type, { bubbles: true, cancelable: true, clientX: x, pointerType, button: 0, pointerId: 1 } ) );
}

beforeEach( () => {
	document.body.innerHTML = '';
	container = document.createElement( 'div' );
	button = document.createElement( 'button' );
	container.append( button );
	document.body.append( container );

	// happy-dom no calcula diseño: se simula contenido más ancho que el contenedor.
	Object.defineProperty( container, 'scrollWidth', { configurable: true, value: 1000 } );
	Object.defineProperty( container, 'clientWidth', { configurable: true, value: 300 } );
	container.scrollLeft = 200;
} );

describe( 'enableDragScroll (CP-1.22)', () => {
	it( 'arrastrar con el mouse desplaza el contenedor', () => {
		enableDragScroll( container );

		pointer( 'pointerdown', { x: 100 } );
		pointer( 'pointermove', { x: 40 } );

		expect( container.scrollLeft ).toBe( 260 );
		expect( container.classList.contains( 'is-dragging' ) ).toBe( true );

		pointer( 'pointerup', { x: 40 } );
		expect( container.classList.contains( 'is-dragging' ) ).toBe( false );
	} );

	it( 'un movimiento mínimo no cuenta como arrastre', () => {
		enableDragScroll( container );

		pointer( 'pointerdown', { x: 100 } );
		pointer( 'pointermove', { x: 97 } );

		expect( container.scrollLeft ).toBe( 200 );
	} );

	it( 'tras arrastrar, el clic al soltar no activa el botón', () => {
		const onClick = vi.fn();
		button.addEventListener( 'click', onClick );
		enableDragScroll( container );

		pointer( 'pointerdown', { x: 100, target: button } );
		pointer( 'pointermove', { x: 20, target: button } );
		pointer( 'pointerup', { x: 20, target: button } );
		button.click();

		expect( onClick ).not.toHaveBeenCalled();
	} );

	it( 'sin arrastre, los clics funcionan normalmente', () => {
		const onClick = vi.fn();
		button.addEventListener( 'click', onClick );
		enableDragScroll( container );

		pointer( 'pointerdown', { x: 100, target: button } );
		pointer( 'pointerup', { x: 100, target: button } );
		button.click();

		expect( onClick ).toHaveBeenCalledOnce();
	} );

	it( 'en pantallas táctiles deja el desplazamiento nativo (CP-1.23)', () => {
		enableDragScroll( container );

		pointer( 'pointerdown', { x: 100, pointerType: 'touch' } );
		pointer( 'pointermove', { x: 20, pointerType: 'touch' } );

		expect( container.scrollLeft ).toBe( 200 );
	} );

	it( 'no arrastra si el contenido cabe completo', () => {
		Object.defineProperty( container, 'scrollWidth', { configurable: true, value: 300 } );
		enableDragScroll( container );

		pointer( 'pointerdown', { x: 100 } );
		pointer( 'pointermove', { x: 20 } );

		expect( container.scrollLeft ).toBe( 200 );
	} );
} );
