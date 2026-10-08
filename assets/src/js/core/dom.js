/**
 * Construcción declarativa y segura del DOM. Nunca usa innerHTML: todo texto se inserta como texto.
 */

/**
 * Crea un elemento.
 *
 * @example
 * h( 'button', { class: [ 'ep-button', isPrimary && 'ep-button--primary' ], type: 'button', on: { click } }, 'Guardar' );
 *
 * @param {string} tag Etiqueta HTML.
 * @param {Object} [props] Propiedades:
 *   - class: string | Array<string|false|null> (los valores falsos se ignoran)
 *   - text: contenido de texto
 *   - attrs: atributos (false/null/undefined se omiten; true crea el atributo vacío)
 *   - dataset: atributos data-*
 *   - style: estilos; las claves que empiezan por `--` se asignan como custom properties
 *   - on: manejadores de eventos { click: fn }
 *   - cualquier otra clave se asigna como propiedad del elemento (type, value, disabled, id…)
 * @param {...(Node|string|number|null|undefined|false|Array)} children Hijos.
 * @returns {HTMLElement} Elemento creado.
 */
export function h( tag, props = {}, ...children ) {
	const element = document.createElement( tag );
	const { class: className, text, attrs = {}, dataset = {}, style = {}, on = {}, ...properties } = props;

	if ( className ) {
		element.className = Array.isArray( className ) ? className.filter( Boolean ).join( ' ' ) : className;
	}

	for ( const [ name, value ] of Object.entries( attrs ) ) {
		if ( false === value || null === value || undefined === value ) {
			continue;
		}
		element.setAttribute( name, true === value ? '' : String( value ) );
	}

	Object.assign( element.dataset, dataset );

	for ( const [ name, value ] of Object.entries( style ) ) {
		if ( name.startsWith( '--' ) ) {
			element.style.setProperty( name, value );
		} else {
			element.style[ name ] = value;
		}
	}

	for ( const [ event, handler ] of Object.entries( on ) ) {
		element.addEventListener( event, handler );
	}

	Object.assign( element, properties );

	if ( undefined !== text ) {
		element.textContent = String( text );
	}

	append( element, children );

	return element;
}

/**
 * Agrega hijos a un elemento ignorando valores vacíos.
 *
 * @param {HTMLElement} parent Elemento padre.
 * @param {Array} children Hijos (se aplanan).
 */
export function append( parent, children ) {
	for ( const child of [ children ].flat( Infinity ) ) {
		if ( null === child || undefined === child || false === child ) {
			continue;
		}
		parent.append( child instanceof Node ? child : String( child ) );
	}
}

/**
 * Reemplaza el contenido de un elemento ignorando los valores vacíos, igual que h().
 *
 * Usar en lugar de `replaceChildren()` cuando algún hijo es condicional (`condición && elemento`):
 * el método nativo convierte `false` o `null` en texto y lo muestra en pantalla.
 *
 * @param {HTMLElement} parent Elemento.
 * @param {...(Node|string|number|null|undefined|false|Array)} children Hijos.
 */
export function replaceContent( parent, ...children ) {
	parent.replaceChildren();
	append( parent, children );
}

/**
 * Ícono de Font Awesome decorativo (oculto para lectores de pantalla).
 *
 * @param {string} classes Clases de Font Awesome, por ejemplo `fa-solid fa-pen`.
 * @returns {HTMLElement} Elemento <i>.
 */
export function icon( classes ) {
	return h( 'i', { class: classes, attrs: { 'aria-hidden': 'true' } } );
}

let uidCounter = 0;

/**
 * Identificador único para asociar etiquetas y controles.
 *
 * @param {string} prefix Prefijo.
 * @returns {string} Identificador.
 */
export function uid( prefix = 'ep' ) {
	uidCounter += 1;
	return `${ prefix }-${ uidCounter }`;
}

/**
 * Escapa texto para insertarlo en bibliotecas que solo aceptan HTML (por ejemplo, Notyf).
 *
 * @param {unknown} value Texto.
 * @returns {string} Texto escapado.
 */
export function escapeHtml( value ) {
	return String( value ?? '' )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' )
		.replace( /'/g, '&#039;' );
}
