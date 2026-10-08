/**
 * Panel lateral para ordenar una lista (por ejemplo, los tipos de evento): el usuario arrastra las filas o usa
 * los botones Subir/Bajar y guarda el orden completo de una vez. Si cierra con cambios sin guardar,
 * se pide confirmación.
 */
import { h } from '../core/dom.js';
import { __ } from '../core/i18n.js';
import { button } from './button.js';
import { confirmDialog } from './confirm-dialog.js';
import { openDrawer } from './drawer.js';
import { createSortableList } from './sortable-list.js';
import { toast } from './toast.js';

/**
 * Abre el panel.
 *
 * @param {{ title: string, label: string, hint: string, items: import('./sortable-list.js').SortableItem[], save: (ids: Array<number|string>) => Promise<Object[]>, onSaved: (rows: Object[]) => void }} options
 *   save: guarda el orden y devuelve los registros ya ordenados; onSaved los recibe.
 * @returns {{ element: HTMLElement, close: () => void }} Panel.
 */
export function openReorderDrawer( { title, label, hint, items, save, onSaved } ) {
	const listSlot = h( 'div' );
	const list = createSortableList( listSlot, { label, items } );

	const saveButton = button( { label: __( 'Guardar orden', 'eventos-probolsas' ), icon: 'fa-solid fa-floppy-disk', variant: 'primary', onClick: () => submit() } );

	const drawer = openDrawer( {
		title,
		body: h( 'div', {}, h( 'p', { class: 'ep-field__hint ep-reorder__hint', text: hint } ), listSlot ),
		footer: [ button( { label: __( 'Cancelar', 'eventos-probolsas' ), onClick: () => drawer.requestClose() } ), saveButton ],
		onRequestClose: () =>
			! list.isDirty() ||
			confirmDialog( {
				title: __( '¿Descartar el nuevo orden?', 'eventos-probolsas' ),
				message: __( 'El orden que armaste no se guardará.', 'eventos-probolsas' ),
				confirmLabel: __( 'Descartar', 'eventos-probolsas' ),
				cancelLabel: __( 'Seguir ordenando', 'eventos-probolsas' ),
			} ),
	} );

	async function submit() {
		if ( ! list.isDirty() ) {
			drawer.close();
			return;
		}

		saveButton.disabled = true;
		saveButton.classList.add( 'is-busy' );
		try {
			onSaved( await save( list.getOrder() ) );
			drawer.close();
			toast.success( __( 'Orden guardado.', 'eventos-probolsas' ) );
		} catch ( error ) {
			toast.error( error.message );
		} finally {
			saveButton.disabled = false;
			saveButton.classList.remove( 'is-busy' );
		}
	}

	return drawer;
}
