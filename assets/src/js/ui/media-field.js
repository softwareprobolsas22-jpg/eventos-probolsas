/**
 * Campo de adjunto: una imagen o un PDF de la Biblioteca de Medios de WordPress (R-09).
 *
 * - El plugin no sube archivos por su cuenta: el selector `wp.media` permite elegir uno de la biblioteca
 *   o subirlo a ella, filtrado a imágenes y PDF (`epConfig.media.library_types`).
 * - Vista previa: miniatura de la imagen o ícono del PDF con su nombre, y enlace para abrirlo.
 * - Obligatorio o no según el tipo de evento (setRequired). El servidor revalida el tipo MIME real.
 * - Cumple la interfaz FormField (ui/form.js). Su valor es el ID del adjunto como texto ('' sin adjunto).
 */
import { h, icon, suspendModal, uid } from '../core/dom.js';
import { __ } from '../core/i18n.js';
import { createFieldError } from './form.js';

/**
 * @typedef {Object} MediaAttachment
 * @property {number} id ID del adjunto.
 * @property {string} mime Tipo MIME.
 * @property {string} url Dirección del archivo.
 * @property {string|null} [thumbnail_url] Miniatura (imágenes).
 * @property {string} [filename] Nombre del archivo.
 * @property {string} [title] Título en la biblioteca.
 */

/**
 * Adjunto de `wp.media` (attachment.toJSON()) con la forma de la API del plugin.
 *
 * @param {Object} attachment Adjunto de WordPress.
 * @returns {MediaAttachment} Adjunto.
 */
export function fromWpAttachment( attachment ) {
	const isImage = 'image' === attachment.type;
	return {
		id: Number( attachment.id ),
		mime: String( attachment.mime ?? '' ),
		url: String( attachment.url ?? '' ),
		thumbnail_url: isImage ? attachment.sizes?.thumbnail?.url ?? attachment.sizes?.medium?.url ?? attachment.url ?? null : null,
		filename: String( attachment.filename ?? '' ),
		title: String( attachment.title ?? '' ),
	};
}

/**
 * Abre el selector de la Biblioteca de Medios de WordPress.
 *
 * @param {{ title: string, buttonText: string, libraryTypes: string[], anchor: Element }} options Opciones.
 * @returns {Promise<MediaAttachment|null|undefined>} Adjunto elegido, null si se cerró sin elegir o
 *   undefined si `wp.media` no está disponible.
 */
export function openMediaLibrary( { title, buttonText, libraryTypes, anchor } ) {
	const media = globalThis.wp?.media;
	if ( 'function' !== typeof media ) {
		return Promise.resolve( undefined );
	}

	// El formulario está en un panel modal: mientras el selector está abierto, el panel deja de ser modal
	// para que el selector (insertado en <body>) quede encima y responda.
	return suspendModal(
		anchor,
		() =>
			new Promise( ( resolve ) => {
				const frame = media( { title, button: { text: buttonText }, library: { type: libraryTypes }, multiple: false } );
				let chosen = null;
				frame.on( 'select', () => {
					chosen = fromWpAttachment( frame.state().get( 'selection' ).first().toJSON() );
				} );
				// «close» llega también después de «select».
				frame.on( 'close', () => resolve( chosen ) );
				frame.open();
			} )
	);
}

/**
 * Crea el campo.
 *
 * @param {{ name: string, label: string, value?: MediaAttachment|null, required?: boolean, allowedMimes?: string[], libraryTypes?: string[], hint?: string, openLibrary?: typeof openMediaLibrary }} options Opciones.
 *   openLibrary: inyectable en pruebas.
 * @returns {import('./form.js').FormField & { setRequired: (required: boolean) => void, getAttachment: () => MediaAttachment|null }} Campo.
 */
export function createMediaField( {
	name,
	label,
	value = null,
	required = false,
	allowedMimes = [],
	libraryTypes = [ 'image', 'application/pdf' ],
	hint = __( 'Una imagen (JPG, PNG, WEBP o GIF) o un PDF de la Biblioteca de Medios.', 'eventos-probolsas' ),
	openLibrary = openMediaLibrary,
} ) {
	const labelId = uid( `ep-field-${ name }-label` );
	const hintId = `${ labelId }-hint`;
	const errorId = `${ labelId }-error`;
	let attachment = value?.id ? value : null;
	let isRequired = required;

	const requiredMark = h( 'span', { class: 'ep-field__required', text: ' *', attrs: { 'aria-hidden': 'true' } } );
	const preview = h( 'div', { class: 'ep-media-field__preview', attrs: { 'aria-live': 'polite' } } );
	const chooseButton = h( 'button', { type: 'button', class: 'ep-button ep-button--secondary ep-button--sm', attrs: { 'aria-describedby': `${ hintId } ${ errorId }` }, on: { click: choose } } );
	const removeButton = h(
		'button',
		{ type: 'button', class: 'ep-button ep-button--ghost ep-button--sm', on: { click: remove } },
		icon( 'fa-solid fa-xmark' ),
		h( 'span', { text: __( 'Quitar', 'eventos-probolsas' ) } )
	);
	const errorText = h( 'span' );
	const error = h( 'p', { class: 'ep-field__error', id: errorId, hidden: true }, icon( 'fa-solid fa-circle-exclamation' ), errorText );

	const element = h(
		'div',
		{ class: 'ep-field ep-media-field', attrs: { role: 'group', 'aria-labelledby': labelId } },
		h( 'span', { class: 'ep-field__label', id: labelId }, label, requiredMark ),
		preview,
		h( 'div', { class: 'ep-media-field__actions' }, chooseButton, removeButton ),
		h( 'p', { class: 'ep-field__hint', id: hintId, text: hint } ),
		error
	);

	const fieldError = createFieldError( getValue, ( message ) => {
		errorText.textContent = message ?? '';
		error.hidden = ! message;
		element.classList.toggle( 'is-invalid', Boolean( message ) );
	} );

	render();

	function getValue() {
		return attachment ? String( attachment.id ) : '';
	}

	function render() {
		requiredMark.hidden = ! isRequired;
		removeButton.hidden = ! attachment;
		chooseButton.replaceChildren(
			icon( attachment ? 'fa-solid fa-arrows-rotate' : 'fa-solid fa-photo-film' ),
			h( 'span', { text: attachment ? __( 'Cambiar archivo', 'eventos-probolsas' ) : __( 'Elegir archivo', 'eventos-probolsas' ) } )
		);

		if ( ! attachment ) {
			preview.replaceChildren( h( 'p', { class: 'ep-media-field__empty', text: __( 'Sin archivo', 'eventos-probolsas' ) } ) );
			return;
		}

		const isPdf = 'application/pdf' === attachment.mime;
		const name = attachment.filename || attachment.title || __( 'Archivo', 'eventos-probolsas' );
		preview.replaceChildren(
			isPdf || ! attachment.thumbnail_url
				? h( 'span', { class: 'ep-media-field__icon', attrs: { 'aria-hidden': 'true' } }, icon( isPdf ? 'fa-solid fa-file-pdf' : 'fa-solid fa-image' ) )
				: h( 'img', { class: 'ep-media-field__thumbnail', src: attachment.thumbnail_url, alt: '', loading: 'lazy' } ),
			h(
				'div',
				{ class: 'ep-media-field__details' },
				h( 'span', { class: 'ep-media-field__name ep-truncate', text: name } ),
				h(
					'a',
					{ class: 'ep-media-field__link', href: attachment.url, target: '_blank', rel: 'noopener noreferrer' },
					h( 'span', { text: isPdf ? __( 'Abrir PDF', 'eventos-probolsas' ) : __( 'Ver imagen', 'eventos-probolsas' ) } ),
					icon( 'fa-solid fa-arrow-up-right-from-square' ),
					h( 'span', { class: 'ep-visually-hidden', text: __( '(se abre en otra pestaña)', 'eventos-probolsas' ) } )
				)
			)
		);
	}

	async function choose() {
		const chosen = await openLibrary( {
			title: label,
			buttonText: __( 'Usar este archivo', 'eventos-probolsas' ),
			libraryTypes,
			anchor: element,
		} );

		if ( undefined === chosen ) {
			fieldError.fromServer( __( 'No se pudo abrir la Biblioteca de Medios. Recarga la página e inténtalo de nuevo.', 'eventos-probolsas' ) );
			return;
		}
		if ( chosen ) {
			attachment = chosen;
			render();
			validate();
		}
		chooseButton.focus();
	}

	function remove() {
		attachment = null;
		render();
		fieldError.fromValidation( null );
		chooseButton.focus();
	}

	/**
	 * Valida con las mismas reglas y mensajes que el servidor.
	 *
	 * @returns {string|null} Mensaje de error o null.
	 */
	function validate() {
		let message = null;
		if ( isRequired && ! attachment ) {
			message = __( 'Este tipo de evento requiere una imagen o un PDF.', 'eventos-probolsas' );
		} else if ( attachment && allowedMimes.length > 0 && ! allowedMimes.includes( attachment.mime ) ) {
			message = __( 'El archivo debe ser una imagen o un PDF.', 'eventos-probolsas' );
		}
		fieldError.fromValidation( message );
		return message;
	}

	return {
		name,
		element,
		getValue,
		getAttachment: () => attachment,
		setValue( newValue ) {
			attachment = newValue?.id ? newValue : null;
			render();
		},
		validate,
		setError: fieldError.fromServer,
		/** Obligatorio según el tipo de evento elegido; si deja de serlo, se quita el aviso. */
		setRequired( newRequired ) {
			isRequired = Boolean( newRequired );
			render();
			if ( ! isRequired && element.classList.contains( 'is-invalid' ) ) {
				validate();
			}
		},
		focus: () => chooseButton.focus(),
	};
}
