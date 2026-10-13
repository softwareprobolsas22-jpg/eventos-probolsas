/**
 * Formulario de evento en panel lateral (H-204): crear y editar con validación en tiempo real (R-24)
 * a partir de `epConfig.rules.event`, el bloque «Cuándo» y el adjunto de la Biblioteca de Medios (R-09).
 *
 * - Formulario progresivo (H-410): al crear, primero se elige el tipo y después aparecen los demás campos.
 *   El tipo define si el adjunto es obligatorio (`requires_attachment`): «Adjunto *» o «Adjunto
 *   (opcional)» (D-19). Los campos ocultos no se validan (createForm).
 * - Una fecha pasada se permite, con un aviso que no bloquea (D-3). «Hoy» es el del servidor (R-08).
 * - Sin hora, el evento dura todo el día. El bloque «Cuándo» deja el lugar para la hora de fin y los
 *   eventos de varios días (D-7, v1.1).
 * - Los errores 422 se muestran bajo cada campo y el mensaje en un toast (R-20).
 */
import { h, icon } from '../core/dom.js';
import { __, sprintf } from '../core/i18n.js';
import { eventTypeBadge } from '../ui/badge.js';
import { button } from '../ui/button.js';
import { confirmDialog } from '../ui/confirm-dialog.js';
import { openDrawer } from '../ui/drawer.js';
import { createField, createForm, ruleOptions } from '../ui/form.js';
import { createMediaField, fromRestAttachment, openMediaLibrary } from '../ui/media-field.js';
import { toast } from '../ui/toast.js';

/**
 * Abre el formulario.
 *
 * @param {{ event?: Object|null, types: Object[], config: Object, api: Object, onSaved: (event: Object) => void, onMissing?: () => void, openLibrary?: typeof openMediaLibrary }} options
 *   event: evento a editar (null para crear). types: tipos de `GET /event-types`. onSaved: después de guardar.
 *   onMissing: el evento ya no existe (404). openLibrary: selector de medios (inyectable en pruebas).
 * @returns {{ element: HTMLDialogElement, close: () => void, requestClose: () => Promise<void> }} Panel.
 */
export function openEventForm( { event = null, types, config, api, onSaved, onMissing = () => {}, openLibrary = openMediaLibrary } ) {
	const isNew = null === event;
	const rules = config.rules?.event ?? {};
	const today = String( config.today ?? '' );
	const typeOf = ( id ) => types.find( ( type ) => String( type.id ) === String( id ) ) ?? null;
	const initialType = typeOf( event?.type?.id ?? '' );

	const preview = h( 'div', { class: 'ep-badge-preview__badge' } );
	const renderPreview = () => {
		const type = typeOf( typeField.getValue() );
		preview.replaceChildren(
			type ? eventTypeBadge( { ...type, name: titleField.getValue() || type.name, maxWidth: '22rem' } ) : h( 'span', { class: 'ep-field__hint', text: __( 'Elige un tipo para ver cómo se mostrará.', 'eventos-probolsas' ) } )
		);
	};

	const titleField = createField( {
		name: 'title',
		label: __( 'Título', 'eventos-probolsas' ),
		...ruleOptions( rules.title ),
		value: event?.title ?? '',
		placeholder: __( 'Ej.: Cumpleaños de Ana María', 'eventos-probolsas' ),
		onInput: () => renderPreview(),
	} );

	const typeField = createField( {
		name: 'type_id',
		label: __( 'Tipo', 'eventos-probolsas' ),
		type: 'select',
		required: true === rules.type_id?.required,
		options: types.map( ( type ) => ( { value: type.id, label: type.name } ) ),
		value: initialType?.id ?? '',
		placeholder: __( 'Selecciona un tipo', 'eventos-probolsas' ),
		hint: isNew ? __( 'Al elegirlo aparecen los demás datos del evento.', 'eventos-probolsas' ) : undefined,
		onInput: ( value ) => {
			const type = typeOf( value );
			mediaField.setRequired( Boolean( type?.requires_attachment ) );
			if ( type ) {
				showDetails();
			}
			renderPreview();
		},
	} );

	const dateField = createField( {
		name: 'start_date',
		label: __( 'Fecha', 'eventos-probolsas' ),
		type: 'date',
		required: true === rules.start_date?.required,
		value: event?.start_date ?? today,
		onInput: () => updatePastNotice(),
	} );

	const timeField = createField( {
		name: 'start_time',
		label: __( 'Hora', 'eventos-probolsas' ),
		type: 'time',
		value: event?.start_time ?? '',
		hint: __( 'Déjala vacía si el evento dura todo el día.', 'eventos-probolsas' ),
	} );

	const descriptionField = createField( {
		name: 'description',
		label: __( 'Descripción', 'eventos-probolsas' ),
		type: 'textarea',
		...ruleOptions( rules.description ),
		value: event?.description ?? '',
		placeholder: __( 'Ej.: Nos reunimos en la sala de juntas del segundo piso. Habrá torta y café.', 'eventos-probolsas' ),
		hint: __( 'Opcional.', 'eventos-probolsas' ),
	} );

	// H-411: soltar el archivo en el campo lo sube a la Biblioteca de Medios (docs/api/media.md). Solo si el
	// usuario puede subir archivos (`upload_files`); si no, queda «Elegir archivo».
	const media = config.media ?? {};
	const canUpload = Boolean( config.can?.upload && media.upload_url && 'function' === typeof api.upload );
	const mediaField = createMediaField( {
		name: 'attachment_id',
		label: __( 'Adjunto', 'eventos-probolsas' ),
		value: event?.attachment ?? null,
		required: Boolean( initialType?.requires_attachment ),
		allowedMimes: media.allowed_mimes ?? [],
		libraryTypes: media.library_types,
		openLibrary,
		maxUploadBytes: Number( media.max_upload_bytes ) || 0,
		uploadFile: canUpload
			? async ( file, onProgress ) =>
					fromRestAttachment(
						await api.upload( media.upload_url, file, {
							onProgress,
							silent: true,
							envelope: false,
							headers: { [ media.upload_header ?? 'X-Eventos-Upload' ]: media.upload_context ?? 'event-attachment' },
						} )
					)
			: null,
	} );

	const pastNotice = h(
		'p',
		{ class: 'ep-notice ep-notice--warning', hidden: true, attrs: { role: 'status' } },
		icon( 'fa-solid fa-clock-rotate-left' ),
		h( 'span', { text: __( 'Esta fecha ya pasó. Puedes guardar el evento igual.', 'eventos-probolsas' ) } )
	);

	/** Aviso «Esta fecha ya pasó» (D-3): compara con la fecha de hoy en Colombia que da el servidor. */
	function updatePastNotice() {
		const date = dateField.getValue();
		pastNotice.hidden = ! ( '' !== today && /^\d{4}-\d{2}-\d{2}$/.test( date ) && date < today );
	}

	const form = createForm( {
		fields: [ typeField, titleField, dateField, timeField, descriptionField, mediaField ],
		onSubmit: async ( values ) => {
			try {
				const saved = isNew ? await api.post( 'events', values, { silent: true } ) : await api.put( `events/${ event.id }`, values, { silent: true } );
				drawer.close();
				toast.success(
					isNew
						? sprintf( /* translators: %s: título del evento. */ __( 'Evento «%s» creado.', 'eventos-probolsas' ), saved.title )
						: sprintf( /* translators: %s: título del evento. */ __( 'Cambios guardados en «%s».', 'eventos-probolsas' ), saved.title )
				);
				onSaved( saved );
			} catch ( error ) {
				toast.error( error.message );
				if ( 404 === error.status ) {
					// Otra persona lo eliminó mientras se editaba.
					drawer.close();
					onMissing();
					return;
				}
				form.setErrors( error.fieldErrors ?? {} );
			}
		},
	} );

	// Bloque «Cuándo»: fecha y hora juntas. Aquí llegarán la hora de fin y los eventos de varios días (D-7).
	const when = h(
		'fieldset',
		{ class: 'ep-fieldset' },
		h( 'legend', { class: 'ep-fieldset__legend', text: __( 'Cuándo', 'eventos-probolsas' ) } ),
		h( 'div', { class: 'ep-fieldset__row' }, dateField.element, timeField.element ),
		pastNotice
	);
	form.element.insertBefore( when, descriptionField.element );

	// Los campos que dependen del tipo esperan a que se elija uno (H-410). Al editar ya hay tipo.
	const details = [ titleField.element, when, dateField.element, timeField.element, descriptionField.element, mediaField.element ];
	/** Muestra los campos que siguen al tipo. Una vez visibles no se vuelven a ocultar. */
	function showDetails() {
		details.forEach( ( element ) => {
			element.hidden = false;
		} );
	}
	details.forEach( ( element ) => {
		element.hidden = isNew;
	} );

	renderPreview();
	updatePastNotice();

	const drawer = openDrawer( {
		title: isNew ? __( 'Nuevo evento', 'eventos-probolsas' ) : __( 'Editar evento', 'eventos-probolsas' ),
		body: h(
			'div',
			{},
			h(
				'div',
				{ class: 'ep-badge-preview', attrs: { 'aria-hidden': 'true' } },
				h( 'span', { class: 'ep-badge-preview__label', text: __( 'Vista previa', 'eventos-probolsas' ) } ),
				preview
			),
			form.element
		),
		footer: [
			button( { label: __( 'Cancelar', 'eventos-probolsas' ), onClick: () => drawer.requestClose() } ),
			form.submitButton( isNew ? __( 'Crear evento', 'eventos-probolsas' ) : __( 'Guardar cambios', 'eventos-probolsas' ) ),
		],
		onRequestClose: () =>
			! form.isDirty() ||
			confirmDialog( {
				title: __( '¿Descartar los cambios?', 'eventos-probolsas' ),
				message: __( 'Los cambios que no guardaste se perderán.', 'eventos-probolsas' ),
				confirmLabel: __( 'Descartar', 'eventos-probolsas' ),
				cancelLabel: __( 'Seguir editando', 'eventos-probolsas' ),
			} ),
	} );

	// Un archivo soltado fuera de la zona del adjunto no debe abrirse en la pestaña: se perdería el formulario.
	const keepFileDrops = ( dragEvent ) => {
		// La zona del adjunto ya lo atendió (y marcó «copiar»); aquí solo el resto del panel.
		if ( ! dragEvent.defaultPrevented && [ ...( dragEvent.dataTransfer?.types ?? [] ) ].includes( 'Files' ) ) {
			dragEvent.preventDefault();
			dragEvent.dataTransfer.dropEffect = 'none';
		}
	};
	drawer.element.addEventListener( 'dragover', keepFileDrops );
	drawer.element.addEventListener( 'drop', keepFileDrops );

	return drawer;
}
