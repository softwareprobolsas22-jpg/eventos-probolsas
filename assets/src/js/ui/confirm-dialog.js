/**
 * Diálogo de confirmación accesible, en lugar de window.confirm().
 *
 * Usa el elemento nativo <dialog> en modo modal: el foco queda atrapado dentro, Esc cancela y el
 * resto de la página queda inerte. Al cerrarse, el foco vuelve al elemento que lo abrió.
 */
import { h, icon, uid } from '../core/dom.js';
import { __ } from '../core/i18n.js';

/**
 * Pide confirmación al usuario.
 *
 * @param {{ title: string, message: string, confirmLabel?: string, cancelLabel?: string, tone?: 'danger'|'primary', icon?: string }} options Opciones.
 * @returns {Promise<boolean>} true si confirma; false si cancela, pulsa Esc o hace clic fuera.
 */
export function confirmDialog( {
	title,
	message,
	confirmLabel = __( 'Confirmar', 'eventos-probolsas' ),
	cancelLabel = __( 'Cancelar', 'eventos-probolsas' ),
	tone = 'danger',
	icon: iconClass = 'danger' === tone ? 'fa-solid fa-triangle-exclamation' : 'fa-solid fa-circle-question',
} ) {
	return new Promise( ( resolve ) => {
		const opener = document.activeElement;
		const titleId = uid( 'ep-dialog-title' );
		const messageId = uid( 'ep-dialog-message' );

		const cancelButton = h( 'button', { type: 'button', class: 'ep-button ep-button--secondary', text: cancelLabel } );
		const confirmButton = h( 'button', {
			type: 'button',
			class: [ 'ep-button', 'danger' === tone ? 'ep-button--danger' : 'ep-button--primary' ],
			text: confirmLabel,
		} );

		// La clase ep-app aplica los estilos del plugin: el diálogo se inserta fuera del contenedor de la pantalla.
		const dialog = h(
			'dialog',
			{ class: 'ep-app ep-dialog', attrs: { 'aria-labelledby': titleId, 'aria-describedby': messageId } },
			h( 'div', { class: [ 'ep-dialog__icon', `ep-dialog__icon--${ tone }` ] }, icon( iconClass ) ),
			h( 'h2', { class: 'ep-dialog__title', id: titleId, text: title } ),
			h( 'p', { class: 'ep-dialog__message', id: messageId, text: message } ),
			h( 'div', { class: 'ep-dialog__actions' }, cancelButton, confirmButton )
		);

		let settled = false;
		const close = ( result ) => {
			if ( settled ) {
				return;
			}
			settled = true;
			dialog.close();
			dialog.remove();
			if ( opener instanceof HTMLElement && opener.isConnected ) {
				opener.focus();
			}
			resolve( result );
		};

		cancelButton.addEventListener( 'click', () => close( false ) );
		confirmButton.addEventListener( 'click', () => close( true ) );
		dialog.addEventListener( 'cancel', ( event ) => {
			event.preventDefault();
			close( false );
		} );
		dialog.addEventListener( 'click', ( event ) => {
			if ( event.target === dialog ) {
				close( false );
			}
		} );

		document.body.append( dialog );
		dialog.showModal();
		// La acción segura recibe el foco: un Enter accidental no ejecuta la acción destructiva.
		cancelButton.focus();
	} );
}
