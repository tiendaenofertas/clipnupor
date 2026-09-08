/* Clipnuvex — interfaz: header, drawer, buscador live, idioma, back-to-top */
( function () {
	'use strict';

	var data = window.clipnuvexData || {};

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) { fn(); }
		else { document.addEventListener( 'DOMContentLoaded', fn ); }
	}

	ready( function () {
		initHeaderScroll();
		initDrawer();
		initSearch();
		initBackToTop();
		initSortSelect();
		initCardImages();
		initViewTracking();
	} );

	/* Conteo de visualización (asíncrono, una vez por carga de vídeo) */
	function initViewTracking() {
		if ( ! data.videoId || ! data.viewUrl ) { return; }
		// Evita doble conteo en la misma sesión de pestaña.
		try {
			var k = 'cnx_viewed_' + data.videoId;
			if ( sessionStorage.getItem( k ) ) { return; }
			sessionStorage.setItem( k, '1' );
		} catch ( e ) {}
		// Sin X-WP-Nonce a propósito: el endpoint es público (permission_callback
		// __return_true + throttle). Un nonce incrustado en una página cacheada
		// caduca a las 12-24 h y WordPress responde 403 "Cookie check failed"
		// a TODOS los visitantes hasta vaciar la caché.
		fetch( data.viewUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( { id: data.videoId } ),
			keepalive: true
		} ).catch( function () {} );
	}

	/* Header: clase al hacer scroll */
	function initHeaderScroll() {
		var header = document.querySelector( '.cnx-header' );
		if ( ! header ) { return; }
		var onScroll = function () {
			header.classList.toggle( 'is-scrolled', window.scrollY > 8 );
		};
		onScroll();
		window.addEventListener( 'scroll', onScroll, { passive: true } );
	}

	/* Drawer móvil */
	function initDrawer() {
		var burger = document.querySelector( '.cnx-burger' );
		var drawer = document.querySelector( '.cnx-drawer' );
		if ( ! burger || ! drawer ) { return; }
		var open = function () {
			drawer.hidden = false;
			burger.setAttribute( 'aria-expanded', 'true' );
			var firstLink = drawer.querySelector( '.cnx-drawer__close, a, button' );
			if ( firstLink ) { firstLink.focus(); }
			document.addEventListener( 'keydown', onKey );
		};
		var close = function () {
			drawer.hidden = true;
			burger.setAttribute( 'aria-expanded', 'false' );
			burger.focus();
			document.removeEventListener( 'keydown', onKey );
		};
		var onKey = function ( e ) {
			if ( e.key === 'Escape' ) { close(); return; }
			// Focus trap: el tabulador cicla dentro del drawer (aria-modal).
			if ( e.key === 'Tab' ) {
				var f = drawer.querySelectorAll( 'a[href], button:not([disabled])' );
				if ( ! f.length ) { return; }
				var first = f[ 0 ], last = f[ f.length - 1 ];
				if ( e.shiftKey && document.activeElement === first ) { e.preventDefault(); last.focus(); }
				else if ( ! e.shiftKey && document.activeElement === last ) { e.preventDefault(); first.focus(); }
			}
		};
		burger.addEventListener( 'click', open );
		drawer.addEventListener( 'click', function ( e ) {
			if ( e.target === drawer || e.target.closest( '.cnx-drawer__close' ) ) { close(); }
		} );
	}

	/* Buscador con dropdown en vivo */
	function initSearch() {
		var input = document.querySelector( '.cnx-search__input' );
		var panel = document.querySelector( '.cnx-search__panel' );
		if ( ! input || ! panel ) { return; }
		var timer = null;

		var hide = function () { panel.hidden = true; input.setAttribute( 'aria-expanded', 'false' ); };
		var show = function () { panel.hidden = false; input.setAttribute( 'aria-expanded', 'true' ); };

		input.addEventListener( 'input', function () {
			var q = input.value.trim();
			clearTimeout( timer );
			if ( q.length < 2 ) { hide(); return; }
			timer = setTimeout( function () { runSearch( q, panel ); show(); }, 200 );
		} );

		input.addEventListener( 'focus', function () {
			var q = input.value.trim();
			if ( q.length < 2 ) { return; }
			// En la página de resultados el input llega prefijado: si el panel
			// aún no tiene contenido, se rellena (coincidencias o sugerencias)
			// en vez de abrir una caja vacía.
			if ( ! panel.childNodes.length ) {
				clearTimeout( timer );
				timer = setTimeout( function () { runSearch( q, panel ); show(); }, 150 );
				return;
			}
			show();
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( ! e.target.closest( '.cnx-search' ) ) { hide(); }
		} );

		input.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) { hide(); }
		} );

		// El buscador es un <form> real: Enter navega a la página de resultados
		// de forma nativa. Solo se evita el envío con el campo vacío.
		if ( input.form ) {
			input.form.addEventListener( 'submit', function ( e ) {
				if ( ! input.value.trim() ) { e.preventDefault(); }
			} );
		}
	}

	function runSearch( q, panel ) {
		if ( ! data.restUrl ) { return; }
		var url = data.restUrl + '?q=' + encodeURIComponent( q );
		// Sin nonce (endpoint público; ver initViewTracking).
		fetch( url )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) { renderSearch( res, q, panel ); } )
			.catch( function () {} );
	}

	function el( tag, cls, html ) {
		var n = document.createElement( tag );
		if ( cls ) { n.className = cls; }
		if ( html != null ) { n.innerHTML = html; }
		return n;
	}

	// Item de vídeo/contenido del desplegable (resultados y sugerencias).
	function searchItem( v ) {
		var a = el( 'a', 'cnx-search__result' );
		a.href = v.url;
		var thumb = v.thumb ? '<img class="cnx-search__result-thumb" src="' + escapeHtml( v.thumb ) + '" alt="" loading="lazy">' : '<span class="cnx-search__result-thumb"></span>';
		a.innerHTML = thumb +
			'<span><span class="cnx-search__result-title">' + escapeHtml( v.title ) + '</span>' +
			'<span class="cnx-search__result-meta">' + escapeHtml( v.category || '' ) + ( v.year ? ' · ' + escapeHtml( String( v.year ) ) : '' ) + '</span></span>';
		return a;
	}

	// Región aria-live (fuera del panel, que se vacía en cada render) para que
	// los lectores de pantalla anuncien el cambio de contenido del desplegable.
	function announceSearch( msg, panel ) {
		var live = panel.parentNode ? panel.parentNode.querySelector( '.cnx-search__live' ) : null;
		if ( ! live && panel.parentNode ) {
			live = el( 'div', 'cnx-search__live' );
			live.setAttribute( 'aria-live', 'polite' );
			live.style.cssText = 'position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;';
			panel.parentNode.appendChild( live );
		}
		if ( live ) { live.textContent = msg; }
	}

	function renderSearch( res, q, panel ) {
		var i18n = data.i18n || {};
		panel.innerHTML = '';
		var cats = res.categories || [];
		var vids = res.videos || [];
		// Guard: tolera respuestas/transients con la forma antigua (sin suggestions).
		var recs = res.suggestions || [];

		if ( ! cats.length && ! vids.length ) {
			// Mensaje primero; debajo, sugerencias "Quizás te interese" si el
			// servidor las adjunta (toggle del panel). Sin footer "Ver todos los
			// resultados": enlazaría a la misma búsqueda vacía.
			panel.appendChild( el( 'div', 'cnx-search__empty', ( i18n.noResults || 'Sin resultados' ) + ' "' + escapeHtml( q ) + '"' ) );
			if ( recs.length ) {
				panel.appendChild( el( 'div', 'cnx-search__group-label', escapeHtml( i18n.recsTitle || 'Quizás te interese' ) ) );
				recs.forEach( function ( v ) {
					panel.appendChild( searchItem( v ) );
				} );
			}
			announceSearch( ( i18n.noResults || 'Sin resultados' ) + ( recs.length ? '. ' + ( i18n.recsTitle || 'Quizás te interese' ) : '' ), panel );
			return;
		}

		if ( cats.length ) {
			panel.appendChild( el( 'div', 'cnx-search__group-label', i18n.categories || 'Categorías' ) );
			cats.forEach( function ( c ) {
				var a = el( 'a', 'cnx-search__result' );
				a.href = c.url;
				a.innerHTML = '<span class="cnx-search__result-cat-icon" aria-hidden="true">' + gridIcon() + '</span>' +
					'<span><span class="cnx-search__result-title">' + escapeHtml( c.name ) + '</span>' +
					'<span class="cnx-search__result-meta">' + c.count + ' ' + ( i18n.videos || 'vídeos' ) + '</span></span>' +
					'<span class="cnx-search__result-badge">' + ( i18n.genre || 'Género' ) + '</span>';
				panel.appendChild( a );
			} );
		}

		if ( vids.length ) {
			panel.appendChild( el( 'div', 'cnx-search__group-label', i18n.videos || 'Vídeos' ) );
			vids.forEach( function ( v ) {
				panel.appendChild( searchItem( v ) );
			} );
		}

		var foot = el( 'a', 'cnx-search__footer', ( i18n.viewAll || 'Ver todos los resultados' ) + ' →' );
		foot.href = ( data.searchUrl || '/?s=' ) + encodeURIComponent( q );
		panel.appendChild( foot );
	}

	function gridIcon() {
		return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>';
	}

	function escapeHtml( s ) {
		return String( s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	/* Back to top */
	function initBackToTop() {
		var btn = document.querySelector( '.cnx-totop' );
		if ( ! btn ) { return; }
		var onScroll = function () {
			var vis = window.scrollY > 520;
			btn.classList.toggle( 'is-visible', vis );
			// inert: fuera del orden de tabulación y del árbol de accesibilidad
			// mientras está oculto, conservando la animación de opacidad.
			btn.inert = ! vis;
		};
		onScroll();
		window.addEventListener( 'scroll', onScroll, { passive: true } );
		btn.addEventListener( 'click', function () { window.scrollTo( { top: 0, behavior: 'smooth' } ); } );
	}

	/* Select de orden (móvil) → navega cambiando ?orden= */
	function initSortSelect() {
		var sel = document.querySelector( '.cnx-sort-select' );
		if ( ! sel ) { return; }
		sel.addEventListener( 'change', function () {
			var u = new URL( window.location.href );
			u.searchParams.set( 'orden', sel.value );
			u.searchParams.delete( 'paged' );
			window.location.href = u.toString();
		} );
	}

	/* Marca imágenes de tarjeta como cargadas (oculta skeleton) */
	function initCardImages() {
		var imgs = document.querySelectorAll( '.cnx-poster__img' );
		imgs.forEach( function ( img ) {
			if ( img.complete && img.naturalWidth ) {
				img.classList.add( 'is-loaded' );
			} else {
				img.addEventListener( 'load', function () { img.classList.add( 'is-loaded' ); } );
				img.addEventListener( 'error', function () { img.classList.add( 'is-loaded' ); } );
			}
		} );
	}
} )();
