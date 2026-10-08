/**
 * Arrastrar para desplazar (drag to scroll) en contenedores con desplazamiento horizontal.
 *
 * - Solo con mouse: en pantallas táctiles se usa el desplazamiento nativo del dedo.
 * - El arrastre empieza tras moverse unos píxeles, para no interferir con los clics normales.
 * - Si hubo arrastre, el clic que sigue al soltar se cancela (no activa botones por accidente).
 * - No arranca sobre campos de texto ni selects, ni sobre elementos con `data-ep-no-drag`.
 */

const THRESHOLD_PX = 5;
const IGNORED = 'input, select, textarea, [contenteditable], [data-ep-no-drag]';

/**
 * Activa el arrastre en un contenedor.
 *
 * @param {HTMLElement} container Elemento con overflow-x.
 * @returns {() => void} Función para desactivarlo.
 */
export function enableDragScroll( container ) {
	let start = null;
	let dragged = false;

	const isScrollable = () => container.scrollWidth > container.clientWidth;

	function onPointerDown( event ) {
		if ( 'mouse' !== event.pointerType || 0 !== event.button || event.target.closest( IGNORED ) || ! isScrollable() ) {
			return;
		}
		start = { x: event.clientX, scrollLeft: container.scrollLeft, pointerId: event.pointerId };
		dragged = false;
	}

	function onPointerMove( event ) {
		if ( ! start ) {
			return;
		}

		const deltaX = event.clientX - start.x;
		if ( ! dragged ) {
			if ( Math.abs( deltaX ) < THRESHOLD_PX ) {
				return;
			}
			dragged = true;
			container.classList.add( 'is-dragging' );
			container.setPointerCapture?.( start.pointerId );
		}

		container.scrollLeft = start.scrollLeft - deltaX;
		event.preventDefault();
	}

	function onPointerUp() {
		if ( ! start ) {
			return;
		}
		start = null;
		container.classList.remove( 'is-dragging' );
		// El clic posterior al arrastre llega en esta misma tarea; después se restablece el estado.
		setTimeout( () => {
			dragged = false;
		} );
	}

	function onClickCapture( event ) {
		if ( dragged ) {
			event.preventDefault();
			event.stopPropagation();
			dragged = false;
		}
	}

	const updateScrollableState = () => container.classList.toggle( 'is-scrollable', isScrollable() );
	const observer = 'function' === typeof ResizeObserver ? new ResizeObserver( updateScrollableState ) : null;
	observer?.observe( container );
	updateScrollableState();

	container.addEventListener( 'pointerdown', onPointerDown );
	container.addEventListener( 'pointermove', onPointerMove );
	container.addEventListener( 'pointerup', onPointerUp );
	container.addEventListener( 'pointercancel', onPointerUp );
	container.addEventListener( 'click', onClickCapture, true );

	return () => {
		observer?.disconnect();
		container.removeEventListener( 'pointerdown', onPointerDown );
		container.removeEventListener( 'pointermove', onPointerMove );
		container.removeEventListener( 'pointerup', onPointerUp );
		container.removeEventListener( 'pointercancel', onPointerUp );
		container.removeEventListener( 'click', onClickCapture, true );
	};
}
