/**
 * Pantalla «Tipos de evento» (H-104): tabla con badges, creación y edición en panel lateral con vista
 * previa en vivo, orden con arrastre y eliminación con confirmación.
 * Contrato de la API en docs/api/event-types.md.
 */
import { createApi } from '../core/api.js';
import { isHexColor } from '../core/color.js';
import { createDateFormatter } from '../core/date.js';
import { h, icon } from '../core/dom.js';
import { __, _n, sprintf } from '../core/i18n.js';
import { eventTypeBadge } from '../ui/badge.js';
import { button } from '../ui/button.js';
import { createColorField } from '../ui/color-field.js';
import { confirmDialog } from '../ui/confirm-dialog.js';
import { createDataTable } from '../ui/data-table.js';
import { openDrawer } from '../ui/drawer.js';
import { createField, createForm, createSwitchField, ruleOptions } from '../ui/form.js';
import { createIconPicker } from '../ui/icon-picker.js';
import { loadError } from '../ui/load-state.js';
import { openReorderDrawer } from '../ui/reorder-drawer.js';
import { showToast, toast } from '../ui/toast.js';

/**
 * Colores sugeridos: los de la marca, los de los tipos iniciales (docs/api/event-types.md) y otros
 * fáciles de distinguir entre sí. El color de un tipo es un dato del usuario (excepción de R-01).
 */
export const COLOR_PRESETS = [ '#155728', '#669F30', '#9D174D', '#B45309', '#1D4ED8', '#0E7490', '#7C3AED', '#BE123C', '#475569', '#1B1F1C' ];

/** Valores de un tipo nuevo. El ícono se elige en el formulario. */
const NEW_TYPE = { name: '', description: '', color: '#155728', icon: '', requires_attachment: false, events_count: 0 };

/**
 * Ordena como el servidor: por la posición elegida con «Ordenar» y luego por nombre.
 *
 * @param {Object[]} types Tipos de evento.
 * @returns {Object[]} Tipos ordenados.
 */
function sortTypes( types ) {
	return [ ...types ].sort( ( a, b ) => a.sort_order - b.sort_order || a.name.localeCompare( b.name, 'es', { sensitivity: 'base' } ) );
}

/**
 * Valor sí/no con ícono y texto.
 *
 * @param {boolean} value Valor.
 * @returns {HTMLElement} Elemento.
 */
function yesNo( value ) {
	return h(
		'span',
		{ class: [ 'ep-flag', value ? 'is-on' : 'is-off' ] },
		icon( value ? 'fa-solid fa-paperclip' : 'fa-solid fa-minus' ),
		h( 'span', { text: value ? __( 'Sí', 'eventos-probolsas' ) : __( 'No', 'eventos-probolsas' ) } )
	);
}

/**
 * Mensaje cuando no se puede eliminar un tipo porque tiene eventos (el mismo texto del 409 del servidor).
 *
 * @param {{ name: string, events_count: number }} type Tipo.
 * @returns {string} Mensaje.
 */
function inUseMessage( type ) {
	return sprintf(
		/* translators: 1: nombre del tipo de evento, 2: cantidad de eventos. */
		_n(
			'No se puede eliminar «%1$s» porque tiene %2$d evento asociado.',
			'No se puede eliminar «%1$s» porque tiene %2$d eventos asociados.',
			type.events_count,
			'eventos-probolsas'
		),
		type.name,
		type.events_count
	);
}

/**
 * Monta la pantalla.
 *
 * @param {HTMLElement} screen Contenedor de la pantalla (data-ep-screen).
 * @param {Object} config Configuración (epConfig).
 * @param {{ api?: ReturnType<typeof createApi> }} [dependencies] Dependencias inyectables (pruebas).
 * @returns {Promise<void>} Termina cuando se cargan los datos.
 */
export async function mount( screen, config, { api = createApi( config, { notify: showToast } ) } = {} ) {
	const root = screen.querySelector( '#ep-event-types' );
	const rules = config.rules?.event_type ?? {};
	const dates = createDateFormatter( config.ui );
	let types = [];

	const tableSlot = h( 'div' );
	const table = createDataTable( tableSlot, {
		caption: __( 'Tipos de evento', 'eventos-probolsas' ),
		pageSizes: config.ui.page_sizes,
		pageSize: config.ui.default_page_size,
		columns: [
			{ key: 'name', label: __( 'Tipo', 'eventos-probolsas' ), render: ( row ) => eventTypeBadge( { ...row, maxWidth: '20rem' } ) },
			{ key: 'requires_attachment', label: __( 'Requiere adjunto', 'eventos-probolsas' ), align: 'center', render: ( row ) => yesNo( row.requires_attachment ) },
			{ key: 'events_count', label: __( 'Eventos', 'eventos-probolsas' ), align: 'center', render: ( row ) => String( row.events_count ) },
			{ key: 'sort_order', label: __( 'Orden', 'eventos-probolsas' ), align: 'center', render: ( row ) => String( row.sort_order ) },
		],
		actions: ( row ) => [
			{ icon: 'fa-solid fa-pen', label: __( 'Editar', 'eventos-probolsas' ), onClick: () => openForm( row ) },
			{ icon: 'fa-solid fa-trash', label: __( 'Eliminar', 'eventos-probolsas' ), variant: 'danger', onClick: () => remove( row ) },
		],
		emptyState: {
			icon: 'fa-solid fa-tags',
			title: __( 'Todavía no hay tipos de evento', 'eventos-probolsas' ),
			message: __( 'Crea el primero con «Añadir tipo».', 'eventos-probolsas' ),
		},
	} );

	const orderButton = button( { label: __( 'Ordenar', 'eventos-probolsas' ), icon: 'fa-solid fa-arrow-down-short-wide', onClick: () => openOrder() } );
	const addButton = button( { label: __( 'Añadir tipo', 'eventos-probolsas' ), icon: 'fa-solid fa-plus', variant: 'primary', onClick: () => openForm() } );
	screen.querySelector( '[data-ep-header-actions]' )?.append( orderButton, addButton );

	root.removeAttribute( 'aria-busy' );
	root.replaceChildren( tableSlot );

	function setTypes( list ) {
		types = sortTypes( list );
		table.setRows( types );
		// Ordenar solo tiene sentido con dos tipos o más.
		orderButton.disabled = types.length < 2;
	}

	/** Vuelve a pedir la lista (por ejemplo, si cambió en otra pestaña). */
	async function reload() {
		try {
			setTypes( await api.get( 'event-types' ) );
		} catch {
			// El cliente de la API ya mostró el error.
		}
	}

	/** Panel «Ordenar tipos de evento»: define el orden del filtro y la leyenda del calendario. */
	function openOrder() {
		openReorderDrawer( {
			title: __( 'Ordenar tipos de evento', 'eventos-probolsas' ),
			label: __( 'Tipos de evento en orden', 'eventos-probolsas' ),
			hint: __( 'Arrastra los tipos o usa las flechas. Este orden se usa en el filtro y la leyenda del calendario y en el formulario de eventos.', 'eventos-probolsas' ),
			items: types.map( ( type ) => ( { id: type.id, name: type.name, render: () => eventTypeBadge( { ...type, maxWidth: '22rem' } ) } ) ),
			save: ( ids ) => api.put( 'event-types/order', { ids }, { silent: true } ),
			onSaved: setTypes,
		} );
	}

	/**
	 * Abre el panel para crear (sin tipo) o editar.
	 *
	 * @param {Object|null} type Tipo a editar.
	 */
	function openForm( type = null ) {
		const isNew = null === type;
		const current = type ?? NEW_TYPE;
		// Mientras el color escrito está incompleto, la vista previa conserva el último válido (QA-030).
		let previewColor = current.color;

		const preview = h( 'div', { class: 'ep-badge-preview__badge' } );
		const renderPreview = () => {
			const color = colorField.getValue();
			previewColor = isHexColor( color ) ? color : previewColor;
			preview.replaceChildren(
				eventTypeBadge( {
					name: nameField.getValue() || __( 'Nombre del tipo', 'eventos-probolsas' ),
					color: previewColor,
					icon: iconField.getValue(),
				} )
			);
		};

		const nameField = createField( {
			name: 'name',
			label: __( 'Nombre', 'eventos-probolsas' ),
			...ruleOptions( rules.name ),
			value: current.name,
			placeholder: __( 'Ej.: Capacitaciones', 'eventos-probolsas' ),
			onInput: () => renderPreview(),
		} );

		const colorField = createColorField( {
			name: 'color',
			label: __( 'Color', 'eventos-probolsas' ),
			required: true === rules.color?.required,
			value: current.color,
			presets: COLOR_PRESETS,
			onChange: () => renderPreview(),
		} );

		const iconField = createIconPicker( {
			name: 'icon',
			label: __( 'Ícono', 'eventos-probolsas' ),
			required: true === rules.icon?.required,
			icons: config.icons ?? [],
			value: current.icon,
			onChange: () => renderPreview(),
		} );

		const form = createForm( {
			fields: [
				nameField,
				createField( {
					name: 'description',
					label: __( 'Descripción', 'eventos-probolsas' ),
					type: 'textarea',
					...ruleOptions( rules.description ),
					value: current.description,
					hint: __( 'Opcional. Para qué se usa este tipo.', 'eventos-probolsas' ),
				} ),
				colorField,
				iconField,
				createSwitchField( {
					name: 'requires_attachment',
					label: __( 'Requiere adjunto', 'eventos-probolsas' ),
					hint: __( 'Los eventos de este tipo deberán llevar una imagen o un PDF.', 'eventos-probolsas' ),
					value: current.requires_attachment,
				} ),
			],
			onSubmit: async ( values ) => {
				try {
					const saved = isNew ? await api.post( 'event-types', values, { silent: true } ) : await api.put( `event-types/${ type.id }`, values, { silent: true } );
					setTypes( [ ...types.filter( ( item ) => item.id !== saved.id ), saved ] );
					drawer.close();
					toast.success(
						isNew
							? sprintf( /* translators: %s: nombre del tipo de evento. */ __( 'Tipo de evento «%s» creado.', 'eventos-probolsas' ), saved.name )
							: sprintf( /* translators: %s: nombre del tipo de evento. */ __( 'Cambios guardados en «%s».', 'eventos-probolsas' ), saved.name )
					);
				} catch ( error ) {
					toast.error( error.message );
					if ( 404 === error.status ) {
						// Otra persona lo eliminó mientras se editaba.
						drawer.close();
						reload();
						return;
					}
					form.setErrors( error.fieldErrors ?? {} );
				}
			},
		} );

		renderPreview();

		const drawer = openDrawer( {
			title: isNew ? __( 'Nuevo tipo de evento', 'eventos-probolsas' ) : __( 'Editar tipo de evento', 'eventos-probolsas' ),
			body: h(
				'div',
				{},
				h(
					'div',
					{ class: 'ep-badge-preview', attrs: { 'aria-hidden': 'true' } },
					h( 'span', { class: 'ep-badge-preview__label', text: __( 'Vista previa', 'eventos-probolsas' ) } ),
					preview
				),
				form.element,
				! isNew && auditNote( type )
			),
			footer: [
				button( { label: __( 'Cancelar', 'eventos-probolsas' ), onClick: () => drawer.requestClose() } ),
				form.submitButton( isNew ? __( 'Crear tipo', 'eventos-probolsas' ) : __( 'Guardar cambios', 'eventos-probolsas' ) ),
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
	}

	/**
	 * Datos que no caben en la tabla (§6.3): identificador y fechas de creación y modificación.
	 *
	 * @param {Object} type Tipo.
	 * @returns {HTMLElement} Nota.
	 */
	function auditNote( type ) {
		return h(
			'div',
			{ class: 'ep-drawer__note' },
			h( 'p', {}, __( 'Identificador (no cambia al renombrar): ', 'eventos-probolsas' ), h( 'code', { text: type.slug } ) ),
			h( 'p', {
				text: sprintf(
					/* translators: 1: fecha y hora de creación, 2: fecha y hora de la última modificación. */
					__( 'Creado el %1$s · Modificado el %2$s', 'eventos-probolsas' ),
					dates.formatDateTime( type.created_at ),
					dates.formatDateTime( type.updated_at )
				),
			} )
		);
	}

	/**
	 * Elimina un tipo tras confirmar. Si tiene eventos, avisa sin llamar al servidor; si el conteo
	 * estaba desactualizado, el servidor responde 409 y la lista se actualiza.
	 *
	 * @param {Object} type Tipo.
	 */
	async function remove( type ) {
		if ( type.events_count > 0 ) {
			toast.warning( inUseMessage( type ) );
			return;
		}

		const confirmed = await confirmDialog( {
			/* translators: %s: nombre del tipo de evento. */
			title: sprintf( __( '¿Eliminar el tipo «%s»?', 'eventos-probolsas' ), type.name ),
			message: __( 'Esta acción no se puede deshacer.', 'eventos-probolsas' ),
			confirmLabel: __( 'Eliminar', 'eventos-probolsas' ),
		} );
		if ( ! confirmed ) {
			return;
		}

		try {
			await api.del( `event-types/${ type.id }`, { silent: true } );
			setTypes( types.filter( ( item ) => item.id !== type.id ) );
			/* translators: %s: nombre del tipo de evento. */
			toast.success( sprintf( __( 'Tipo de evento «%s» eliminado.', 'eventos-probolsas' ), type.name ) );
		} catch ( error ) {
			if ( 409 === error.status ) {
				toast.warning( error.message );
				reload();
				return;
			}
			toast.error( error.message );
			if ( 404 === error.status ) {
				reload();
			}
		}
	}

	table.setLoading( true );
	try {
		setTypes( await api.get( 'event-types' ) );
	} catch {
		root.replaceChildren( loadError() );
		orderButton.disabled = true;
		addButton.disabled = true;
	}
}
