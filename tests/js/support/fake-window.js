/**
 * Ventana simulada con dirección e historial (pushState, replaceState, back y popstate), para probar la
 * navegación de los widgets sin depender del navegador de pruebas.
 *
 * @param {string} href Dirección inicial.
 * @returns {{ location: { href: string, search: string }, history: Object, addEventListener: Function }} Ventana.
 */
export function fakeWindow( href ) {
	const listeners = {};
	const entries = [ { url: href, state: null } ];
	let index = 0;

	const win = {
		location: {},
		history: {
			get state() {
				return entries[ index ].state;
			},
			pushState( state, title, url ) {
				entries.splice( index + 1, Infinity, { url, state } );
				index += 1;
				sync();
			},
			replaceState( state, title, url ) {
				entries[ index ] = { url, state };
				sync();
			},
			back() {
				index -= 1;
				sync();
				listeners.popstate?.();
			},
		},
		addEventListener: ( type, listener ) => {
			listeners[ type ] = listener;
		},
	};

	function sync() {
		const url = new URL( entries[ index ].url );
		win.location.href = url.href;
		win.location.search = url.search;
	}

	sync();
	return win;
}
