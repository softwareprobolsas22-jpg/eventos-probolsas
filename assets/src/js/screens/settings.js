/**
 * Pantalla «Ajustes» (H-402, D-16): si desinstalar el plugin borra los eventos y los tipos.
 *
 * Usa `GET/PUT /settings` (docs/api/settings.md). Por defecto los datos se conservan; activar el borrado
 * pide confirmación, porque no se puede deshacer después de desinstalar. Los archivos de la Biblioteca
 * de Medios nunca se borran (D-4).
 */
import { createApi } from '../core/api.js';
import { h, icon } from '../core/dom.js';
import { __ } from '../core/i18n.js';
import { button } from '../ui/button.js';
import { confirmDialog } from '../ui/confirm-dialog.js';
import { createSwitchField } from '../ui/form.js';
import { loadError } from '../ui/load-state.js';
import { showToast, toast } from '../ui/toast.js';

/**
 * Monta la pantalla.
 *
 * @param {HTMLElement} screen Contenedor `[data-ep-screen]`.
 * @param {Object} config Configuración (epConfig).
 * @param {{ api?: ReturnType<typeof createApi>, confirm?: typeof confirmDialog }} [dependencies] Dependencias (pruebas).
 * @returns {Promise<void>} Termina cuando se cargan los ajustes.
 */
export async function mount( screen, config, { api = createApi( config, { notify: showToast } ), confirm = confirmDialog } = {} ) {
	const root = screen.querySelector( '#ep-settings' );

	let saved;
	try {
		saved = await api.get( 'settings' );
	} catch {
		root.removeAttribute( 'aria-busy' );
		root.replaceChildren( loadError() );
		return;
	}

	const field = createSwitchField( {
		name: 'delete_data_on_uninstall',
		label: __( 'Borrar todos los datos al desinstalar', 'eventos-probolsas' ),
		hint: __( 'Si está desactivado, al desinstalar el plugin se conservan los eventos y los tipos para una reinstalación. Los archivos de la Biblioteca de Medios nunca se borran.', 'eventos-probolsas' ),
		value: saved.delete_data_on_uninstall,
	} );

	const saveButton = button( { label: __( 'Guardar cambios', 'eventos-probolsas' ), icon: 'fa-solid fa-floppy-disk', variant: 'primary', type: 'submit' } );
	saveButton.disabled = true;
	field.control.addEventListener( 'change', () => {
		saveButton.disabled = field.getValue() === saved.delete_data_on_uninstall;
	} );

	const form = h(
		'form',
		{ class: 'ep-card ep-settings', attrs: { novalidate: true }, on: { submit: ( event ) => save( event ) } },
		h( 'h2', { class: 'ep-card__title' }, icon( 'fa-solid fa-box-archive' ), h( 'span', { text: __( 'Desinstalación', 'eventos-probolsas' ) } ) ),
		field.element,
		h( 'div', { class: 'ep-settings__actions' }, saveButton )
	);

	root.removeAttribute( 'aria-busy' );
	root.replaceChildren( form );

	/**
	 * Guarda. Activar el borrado pide confirmación.
	 *
	 * @param {SubmitEvent} event Envío del formulario.
	 */
	async function save( event ) {
		event.preventDefault();
		const value = field.getValue();

		if (
			value &&
			! ( await confirm( {
				title: __( '¿Borrar los datos al desinstalar?', 'eventos-probolsas' ),
				message: __( 'Si alguien desinstala el plugin, se borrarán todos los eventos y los tipos de evento. No se puede deshacer.', 'eventos-probolsas' ),
				confirmLabel: __( 'Sí, borrar al desinstalar', 'eventos-probolsas' ),
			} ) )
		) {
			return;
		}

		saveButton.disabled = true;
		try {
			saved = await api.put( 'settings', { delete_data_on_uninstall: value }, { silent: true } );
			field.setValue( saved.delete_data_on_uninstall );
			toast.success( saved.delete_data_on_uninstall ? __( 'Ajustes guardados: al desinstalar se borrarán los datos.', 'eventos-probolsas' ) : __( 'Ajustes guardados: al desinstalar se conservarán los datos.', 'eventos-probolsas' ) );
		} catch ( error ) {
			toast.error( error.message );
			field.setError( error.fieldErrors?.delete_data_on_uninstall?.[ 0 ] ?? null );
			saveButton.disabled = false;
		}
	}
}
