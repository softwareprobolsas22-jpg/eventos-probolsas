/**
 * Campo de adjunto: una imagen o un PDF de la Biblioteca de Medios de WordPress (R-09).
 *
 * - El selector `wp.media` permite elegir uno de la biblioteca o subirlo a ella, filtrado a imágenes y PDF
 *   (`epConfig.media.library_types`).
 * - Arrastrar y soltar (H-411, si se pasa `uploadFile`): el archivo se valida aquí (tipo y tamaño) antes de
 *   subirlo, se sube a la Biblioteca de Medios con progreso y el servidor lo revisa otra vez antes de
 *   guardarlo (docs/api/media.md). Un archivo rechazado nunca queda guardado (D-4).
 * - Vista previa: miniatura de la imagen o ícono del PDF con su nombre, y enlace para abrirlo.
 * - Obligatorio o no según el tipo de evento (setRequired): la etiqueta muestra « *» o « (opcional)»
 *   (D-19). El servidor revalida el tipo MIME real.
 * - Cumple la interfaz FormField (ui/form.js). Su valor es el ID del adjunto como texto ('' sin adjunto).
 */
import { h, icon, suspendModal, uid } from '../core/dom.js';
import { __, sprintf } from '../core/i18n.js';
import { createFieldError } from './form.js';
import { toast } from './toast.js';

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
 * Adjunto de la API de WordPress (`POST /wp/v2/media`) con la forma de la API del plugin.
 *
 * @param {Object} attachment Respuesta de `wp/v2/media`.
 * @returns {MediaAttachment} Adjunto.
 */
export function fromRestAttachment( attachment ) {
	const mime = String( attachment.mime_type ?? '' );
	const url = String( attachment.source_url ?? '' );
	const sizes = attachment.media_details?.sizes ?? {};
	return {
		id: Number( attachment.id ),
		mime,
		url,
		thumbnail_url: mime.startsWith( 'image/' ) ? sizes.thumbnail?.source_url ?? sizes.medium?.source_url ?? ( url || null ) : null,
		filename: fileName( url ),
		title: String( attachment.title?.raw ?? '' ),
	};
}

/**
 * Nombre del archivo al final de una dirección.
 *
 * @param {string} url Dirección.
 * @returns {string} Nombre.
 */
function fileName( url ) {
	const name = url.split( /[?#]/ )[ 0 ].split( '/' ).pop() ?? '';
	try {
		return decodeURIComponent( name );
	} catch {
		return name;
	}
}

/**
 * Tamaño legible, por ejemplo «10 MB».
 *
 * @param {number} bytes Bytes.
 * @returns {string} Tamaño.
 */
function formatSize( bytes ) {
	const megabytes = bytes / ( 1024 * 1024 );
	return megabytes >= 1 ? `${ new Intl.NumberFormat( 'es-CO', { maximumFractionDigits: 1 } ).format( megabytes ) } MB` : `${ Math.max( 1, Math.round( bytes / 1024 ) ) } KB`;
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
				frame.on( 'select', () => resolve( fromWpAttachment( frame.state().get( 'selection' ).first().toJSON() ) ) );
				// Al elegir, WordPress cierra el selector y después emite «select» (QA-043): se espera un turno
				// antes de dar por hecho que se cerró sin elegir. La promesa solo se resuelve una vez.
				frame.on( 'close', () => setTimeout( () => resolve( null ) ) );
				frame.open();
			} )
	);
}

/**
 * Crea el campo.
 *
 * @param {{ name: string, label: string, value?: MediaAttachment|null, required?: boolean, allowedMimes?: string[], libraryTypes?: string[], hint?: string, openLibrary?: typeof openMediaLibrary, uploadFile?: ((file: File, onProgress: (percent: number) => void) => Promise<MediaAttachment>)|null, maxUploadBytes?: number }} options Opciones.
 *   openLibrary: inyectable en pruebas. uploadFile: sube un archivo soltado en el campo (H-411); sin él, el
 *   campo no acepta archivos soltados. maxUploadBytes: tamaño máximo (0 = sin límite en el navegador).
 * @returns {import('./form.js').FormField & { setRequired: (required: boolean) => void, getAttachment: () => MediaAttachment|null, uploadDropped: (files: FileList|File[]) => Promise<void> }} Campo.
 */
export function createMediaField( {
	name,
	label,
	value = null,
	required = false,
	allowedMimes = [],
	libraryTypes = [ 'image', 'application/pdf' ],
	uploadFile = null,
	maxUploadBytes = 0,
	hint = uploadFile
		? __( 'Arrastra aquí una imagen (JPG, PNG, WEBP o GIF) o un PDF, o elígelo en la Biblioteca de Medios.', 'eventos-probolsas' )
		: __( 'Una imagen (JPG, PNG, WEBP o GIF) o un PDF de la Biblioteca de Medios.', 'eventos-probolsas' ),
	openLibrary = openMediaLibrary,
} ) {
	const labelId = uid( `ep-field-${ name }-label` );
	const hintId = `${ labelId }-hint`;
	const errorId = `${ labelId }-error`;
	let attachment = value?.id ? value : null;
	let isRequired = required;
	let uploading = false;

	const requiredMark = h( 'span', { class: 'ep-field__required', text: ' *', attrs: { 'aria-hidden': 'true' } } );
	// D-19: si no es obligatorio, la etiqueta lo dice («Adjunto (opcional)»).
	const optionalMark = h( 'span', { class: 'ep-field__optional', text: ` ${ __( '(opcional)', 'eventos-probolsas' ) }` } );
	const preview = h(
		'div',
		{
			class: [ 'ep-media-field__preview', uploadFile && 'ep-media-field__dropzone' ],
			attrs: { 'aria-live': 'polite' },
			on: uploadFile ? { dragenter: onDragOver, dragover: onDragOver, dragleave: onDragLeave, drop: onDrop } : {},
		}
	);
	const progressBar = h( 'span', { class: 'ep-media-field__progress-bar' } );
	const progressText = h( 'span', { class: 'ep-media-field__progress-text' } );
	const progress = h(
		'div',
		{ class: 'ep-media-field__progress', hidden: true },
		progressText,
		h( 'span', { class: 'ep-media-field__progress-track', attrs: { role: 'progressbar', 'aria-valuemin': '0', 'aria-valuemax': '100', 'aria-label': __( 'Progreso de la subida', 'eventos-probolsas' ) } }, progressBar )
	);
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
		h( 'span', { class: 'ep-field__label', id: labelId }, label, requiredMark, optionalMark ),
		preview,
		progress,
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
		optionalMark.hidden = isRequired;
		removeButton.hidden = ! attachment;
		chooseButton.replaceChildren(
			icon( attachment ? 'fa-solid fa-arrows-rotate' : 'fa-solid fa-photo-film' ),
			h( 'span', { text: attachment ? __( 'Cambiar archivo', 'eventos-probolsas' ) : __( 'Elegir archivo', 'eventos-probolsas' ) } )
		);

		if ( ! attachment ) {
			preview.replaceChildren(
				uploadFile
					? h( 'p', { class: 'ep-media-field__empty' }, icon( 'fa-solid fa-cloud-arrow-up' ), h( 'span', { text: __( 'Sin archivo. Suelta aquí una imagen o un PDF.', 'eventos-probolsas' ) } ) )
					: h( 'p', { class: 'ep-media-field__empty', text: __( 'Sin archivo', 'eventos-probolsas' ) } )
			);
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
	 * Solo reacciona a archivos arrastrados (no a texto ni a enlaces).
	 *
	 * @param {DragEvent} event Evento.
	 * @returns {boolean} Si se arrastran archivos.
	 */
	function carriesFiles( event ) {
		return [ ...( event.dataTransfer?.types ?? [] ) ].includes( 'Files' );
	}

	/** @param {DragEvent} event Evento. */
	function onDragOver( event ) {
		if ( ! carriesFiles( event ) ) {
			return;
		}
		event.preventDefault();
		event.dataTransfer.dropEffect = uploading ? 'none' : 'copy';
		preview.classList.toggle( 'is-dragover', ! uploading );
	}

	/** @param {DragEvent} event Evento. */
	function onDragLeave( event ) {
		if ( ! preview.contains( event.relatedTarget ) ) {
			preview.classList.remove( 'is-dragover' );
		}
	}

	/** @param {DragEvent} event Evento. */
	function onDrop( event ) {
		if ( ! carriesFiles( event ) ) {
			return;
		}
		event.preventDefault();
		preview.classList.remove( 'is-dragover' );
		uploadDropped( event.dataTransfer.files );
	}

	/**
	 * Valida y sube el archivo soltado (H-411). Se valida antes de subirlo para no enviar lo que el servidor
	 * rechazaría; el servidor lo revisa otra vez antes de guardarlo.
	 *
	 * @param {FileList|File[]} files Archivos soltados.
	 * @returns {Promise<void>} Termina con el archivo subido o el error a la vista.
	 */
	async function uploadDropped( files ) {
		if ( ! uploadFile || uploading ) {
			return;
		}

		const list = [ ...( files ?? [] ) ];
		const problem = 1 !== list.length ? __( 'Suelta un solo archivo.', 'eventos-probolsas' ) : checkFile( list[ 0 ] );
		if ( problem ) {
			fieldError.fromServer( problem );
			return;
		}

		const file = list[ 0 ];
		setUploading( true, file );
		try {
			attachment = await uploadFile( file, ( percent ) => showProgress( file, percent ) );
			render();
			fieldError.fromValidation( null );
			validate();
			/* translators: %s: nombre del archivo. */
			toast.success( sprintf( __( 'Archivo «%s» subido a la Biblioteca de Medios.', 'eventos-probolsas' ), file.name ) );
		} catch ( uploadError ) {
			fieldError.fromServer( uploadError.fieldErrors?.file?.[ 0 ] ?? uploadError.message );
		} finally {
			setUploading( false );
		}
	}

	/**
	 * Lo que impide subir el archivo, o null.
	 *
	 * @param {File} file Archivo.
	 * @returns {string|null} Mensaje.
	 */
	function checkFile( file ) {
		if ( allowedMimes.length > 0 && ! allowedMimes.includes( String( file.type ).toLowerCase() ) ) {
			return __( 'El archivo debe ser una imagen o un PDF.', 'eventos-probolsas' );
		}
		if ( maxUploadBytes > 0 && file.size > maxUploadBytes ) {
			/* translators: %s: tamaño máximo, por ejemplo «10 MB». */
			return sprintf( __( 'El archivo supera el tamaño máximo permitido (%s).', 'eventos-probolsas' ), formatSize( maxUploadBytes ) );
		}
		return null;
	}

	/**
	 * Estado «subiendo»: barra de progreso y botones desactivados.
	 *
	 * @param {boolean} value Si está subiendo.
	 * @param {File} [file] Archivo.
	 */
	function setUploading( value, file ) {
		uploading = value;
		progress.hidden = ! value;
		preview.classList.toggle( 'is-uploading', value );
		element.setAttribute( 'aria-busy', String( value ) );
		chooseButton.disabled = value;
		removeButton.disabled = value;
		if ( value ) {
			showProgress( file, 0 );
		}
	}

	/**
	 * Muestra el avance de la subida.
	 *
	 * @param {File} file Archivo.
	 * @param {number} percent Porcentaje.
	 */
	function showProgress( file, percent ) {
		const value = Math.max( 0, Math.min( 100, Math.round( percent ) ) );
		/* translators: %s: nombre del archivo. */
		progressText.textContent = `${ sprintf( __( 'Subiendo «%s»…', 'eventos-probolsas' ), file.name ) } ${ value } %`;
		progressBar.style.width = `${ value }%`;
		progressBar.parentElement.setAttribute( 'aria-valuenow', String( value ) );
	}

	/**
	 * Valida con las mismas reglas y mensajes que el servidor.
	 *
	 * @returns {string|null} Mensaje de error o null.
	 */
	function validate() {
		let message = null;
		if ( uploading ) {
			message = __( 'Espera a que termine de subir el archivo.', 'eventos-probolsas' );
		} else if ( isRequired && ! attachment ) {
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
		uploadDropped,
	};
}
