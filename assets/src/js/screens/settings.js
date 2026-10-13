/**
 * Pantalla «Ajustes»: si desinstalar el plugin borra los eventos y los tipos (H-402, D-16) y la guía de
 * los shortcodes (H-406).
 *
 * Usa `GET/PUT /settings` y `GET /settings/shortcodes` (docs/api/settings.md), y `GET /event-types` para
 * mostrar los slugs que acepta el atributo `tipos`. Por defecto los datos se conservan; activar el borrado
 * pide confirmación, porque no se puede deshacer después de desinstalar. Los archivos de la Biblioteca de
 * Medios nunca se borran (D-4). Cada sección carga por su cuenta: si una falla, la otra se muestra igual.
 */
import { createApi } from '../core/api.js';
import { copyText } from '../core/clipboard.js';
import { h, icon, replaceContent, uid } from '../core/dom.js';
import { __, sprintf } from '../core/i18n.js';
import { button } from '../ui/button.js';
import { confirmDialog } from '../ui/confirm-dialog.js';
import { createSwitchField } from '../ui/form.js';
import { loadError } from '../ui/load-state.js';
import { showToast, toast } from '../ui/toast.js';

/**
 * Valores del atributo que son los slugs de los tipos (ShortcodeAttribute::EVENT_TYPES).
 */
const EVENT_TYPE_VALUES = 'event_types';

/**
 * Monta la pantalla.
 *
 * @param {HTMLElement} screen Contenedor `[data-ep-screen]`.
 * @param {Object} config Configuración (epConfig).
 * @param {{ api?: ReturnType<typeof createApi>, confirm?: typeof confirmDialog, copy?: typeof copyText }} [dependencies] Dependencias (pruebas).
 * @returns {Promise<void>} Termina cuando se cargan los ajustes y la guía.
 */
export async function mount( screen, config, { api = createApi( config, { notify: showToast } ), confirm = confirmDialog, copy = copyText } = {} ) {
	const root = screen.querySelector( '#ep-settings' );

	const [ settings, guides, types ] = await Promise.allSettled( [
		api.get( 'settings' ),
		api.get( 'settings/shortcodes', { silent: true } ),
		api.get( 'event-types', { silent: true } ),
	] );

	root.removeAttribute( 'aria-busy' );
	replaceContent(
		root,
		h(
			'div',
			{ class: 'ep-settings-screen' },
			'fulfilled' === settings.status ? uninstallForm( settings.value, { api, confirm } ) : loadError(),
			'fulfilled' === guides.status
				? shortcodesSection( guides.value, 'fulfilled' === types.status && Array.isArray( types.value ) ? types.value : null, copy )
				: loadError( __( 'No se pudo cargar la guía de los shortcodes', 'eventos-probolsas' ) )
		)
	);
}

/**
 * Tarjeta «Desinstalación».
 *
 * @param {{ delete_data_on_uninstall: boolean }} initial Ajustes guardados.
 * @param {{ api: ReturnType<typeof createApi>, confirm: typeof confirmDialog }} dependencies Dependencias.
 * @returns {HTMLFormElement} Formulario.
 */
function uninstallForm( initial, { api, confirm } ) {
	let saved = initial;

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

	return h(
		'form',
		{ class: 'ep-card ep-settings', attrs: { novalidate: true }, on: { submit: ( event ) => save( event ) } },
		h( 'h2', { class: 'ep-card__title' }, icon( 'fa-solid fa-box-archive' ), h( 'span', { text: __( 'Desinstalación', 'eventos-probolsas' ) } ) ),
		field.element,
		h( 'div', { class: 'ep-settings__actions' }, saveButton )
	);

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

/**
 * Tarjeta «Shortcodes» (H-406): para qué sirve cada uno, sus atributos y un ejemplo para copiar.
 *
 * @param {Array<Object>} guides Guías de `GET /settings/shortcodes`.
 * @param {Array<{ slug: string, name: string }>|null} types Tipos de evento, o null si no cargaron.
 * @param {typeof copyText} copy Copia al portapapeles.
 * @returns {HTMLElement} Sección.
 */
function shortcodesSection( guides, types, copy ) {
	const titleId = uid( 'ep-shortcodes-title' );

	return h(
		'section',
		{ class: 'ep-card ep-settings ep-shortcodes', attrs: { 'aria-labelledby': titleId } },
		h( 'h2', { class: 'ep-card__title', id: titleId }, icon( 'fa-solid fa-code' ), h( 'span', { text: __( 'Shortcodes', 'eventos-probolsas' ) } ) ),
		h( 'p', { class: 'ep-shortcodes__intro', text: __( 'Copia un shortcode y pégalo en una página o entrada de la intranet para mostrar los eventos.', 'eventos-probolsas' ) } ),
		guides.map( ( guide ) => shortcodeGuide( guide, types, copy ) )
	);
}

/**
 * Guía de un shortcode.
 *
 * @param {{ tag: string, title: string, description: string, example: string, attributes: Array<Object> }} guide Guía.
 * @param {Array<{ slug: string, name: string }>|null} types Tipos de evento, o null si no cargaron.
 * @param {typeof copyText} copy Copia al portapapeles.
 * @returns {HTMLElement} Artículo.
 */
function shortcodeGuide( guide, types, copy ) {
	const titleId = uid( 'ep-shortcode-title' );
	const example = h( 'code', { class: 'ep-shortcode__code', text: guide.example } );
	const copyButton = button( {
		label: __( 'Copiar', 'eventos-probolsas' ),
		icon: 'fa-solid fa-copy',
		size: 'sm',
		onClick: async () => {
			if ( await copy( guide.example ) ) {
				toast.success( sprintf( /* translators: %s: shortcode copiado. */ __( 'Shortcode copiado: %s', 'eventos-probolsas' ), guide.example ) );
			} else {
				selectText( example );
				toast.error( __( 'No se pudo copiar. El shortcode quedó seleccionado: cópialo con Ctrl+C.', 'eventos-probolsas' ) );
			}
		},
	} );
	/* translators: %s: nombre del shortcode, por ejemplo «Calendario de eventos». */
	copyButton.setAttribute( 'aria-label', sprintf( __( 'Copiar el shortcode «%s»', 'eventos-probolsas' ), guide.title ) );

	return h(
		'article',
		{ class: 'ep-shortcode', attrs: { 'aria-labelledby': titleId } },
		h( 'h3', { class: 'ep-shortcode__title', id: titleId, text: guide.title } ),
		h( 'p', { class: 'ep-shortcode__description', text: guide.description } ),
		h( 'div', { class: 'ep-shortcode__example' }, example, copyButton ),
		guide.attributes.length > 0 && [
			h( 'h4', { class: 'ep-shortcode__subtitle', text: __( 'Atributos', 'eventos-probolsas' ) } ),
			h(
				'dl',
				{ class: 'ep-shortcode__attributes' },
				guide.attributes.map( ( attribute ) => [
					h( 'dt', {}, h( 'code', { text: attribute.name } ), h( 'span', { class: 'ep-shortcode__required', text: attribute.required ? __( 'obligatorio', 'eventos-probolsas' ) : __( 'opcional', 'eventos-probolsas' ) } ) ),
					h(
						'dd',
						{},
						h( 'p', { text: attribute.description } ),
						'' !== attribute.default && h( 'p', { class: 'ep-shortcode__default', text: sprintf( /* translators: %s: valor por defecto del atributo. */ __( 'Por defecto: %s', 'eventos-probolsas' ), attribute.default ) } ),
						EVENT_TYPE_VALUES === attribute.values && eventTypeValues( types )
					),
				] )
			),
		]
	);
}

/**
 * Slugs de los tipos de evento, que acepta el atributo `tipos`.
 *
 * @param {Array<{ slug: string, name: string }>|null} types Tipos, o null si no cargaron.
 * @returns {HTMLElement} Lista o aviso.
 */
function eventTypeValues( types ) {
	if ( null === types ) {
		return h( 'p', { class: 'ep-shortcode__values-note', text: __( 'Los valores son los identificadores de los tipos, que se ven en «Tipos de evento».', 'eventos-probolsas' ) } );
	}

	if ( 0 === types.length ) {
		return h( 'p', { class: 'ep-shortcode__values-note', text: __( 'Aún no hay tipos de evento.', 'eventos-probolsas' ) } );
	}

	return h(
		'div',
		{ class: 'ep-shortcode__values' },
		h( 'p', { text: __( 'Valores (el identificador de cada tipo):', 'eventos-probolsas' ) } ),
		h(
			'ul',
			{},
			types.map( ( type ) => h( 'li', {}, h( 'code', { text: type.slug } ), h( 'span', { text: type.name } ) ) )
		)
	);
}

/**
 * Selecciona el texto de un elemento, para copiarlo a mano si el portapapeles no está disponible.
 *
 * @param {HTMLElement} element Elemento.
 */
function selectText( element ) {
	const selection = window.getSelection?.();
	if ( ! selection ) {
		return;
	}
	const range = document.createRange();
	range.selectNodeContents( element );
	selection.removeAllRanges();
	selection.addRange( range );
}
