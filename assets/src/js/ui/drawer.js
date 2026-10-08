/**
 * Panel lateral (drawer) para crear y editar sin salir de la pantalla.
 *
 * Usa <dialog> en modo modal: foco atrapado, Esc para cerrar y el resto de la página inerte. Antes de
 * cerrar consulta onRequestClose (por ejemplo, para confirmar si hay cambios sin guardar).
 * En pantallas pequeñas ocupa todo el ancho.
 */
import { h, releaseFloating, uid } from '../core/dom.js';
import { __ } from '../core/i18n.js';
import { iconButton } from './button.js';

/**
 * Abre un panel lateral.
 *
 * @param {{ title: string, body: Node, footer?: Node[], size?: 'default'|'wide', onRequestClose?: () => boolean|Promise<boolean> }} options
 *   size: 'wide' para formularios largos. onRequestClose: devuelve false para impedir el cierre.
 * @returns {{ element: HTMLDialogElement, close: () => void, requestClose: () => Promise<void> }} Panel.
 */
export function openDrawer( { title, body, footer = [], size = 'default', onRequestClose } ) {
	const opener = document.activeElement;
	const titleId = uid( 'ep-drawer-title' );
	let closed = false;

	const dialog = h(
		'dialog',
		{ class: [ 'ep-app', 'ep-drawer', 'wide' === size && 'ep-drawer--wide' ], attrs: { 'aria-labelledby': titleId } },
		h(
			'header',
			{ class: 'ep-drawer__header' },
			h( 'h2', { class: 'ep-drawer__title', id: titleId, text: title } ),
			iconButton( { icon: 'fa-solid fa-xmark', label: __( 'Cerrar', 'eventos-probolsas' ), onClick: requestClose } )
		),
		h( 'div', { class: 'ep-drawer__body' }, body ),
		footer.length > 0 && h( 'footer', { class: 'ep-drawer__footer' }, footer )
	);

	/** Cierra si onRequestClose lo permite. */
	async function requestClose() {
		if ( onRequestClose && ! ( await onRequestClose() ) ) {
			return;
		}
		close();
	}

	/** Cierra sin preguntar. */
	function close() {
		if ( closed ) {
			return;
		}
		closed = true;
		dialog.close();
		releaseFloating( dialog );
		dialog.remove();
		if ( opener instanceof HTMLElement && opener.isConnected ) {
			opener.focus();
		}
	}

	dialog.addEventListener( 'cancel', ( event ) => {
		event.preventDefault();
		requestClose();
	} );
	dialog.addEventListener( 'click', ( event ) => {
		if ( event.target === dialog ) {
			requestClose();
		}
	} );

	document.body.append( dialog );
	dialog.showModal();
	// Marca de modal para core/dom.js suspendModal (no todos los navegadores tienen :modal).
	dialog.setAttribute( 'data-ep-modal', '' );
	dialog.querySelector( '.ep-drawer__body input, .ep-drawer__body textarea, .ep-drawer__body select' )?.focus();

	return { element: dialog, close, requestClose };
}
