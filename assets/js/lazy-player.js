/* Clipnuvex — carga diferida del iframe del player (solo al pulsar play) +
   resiliencia frente a bloqueadores de anuncios.

   Contexto: los bloqueadores (uBlock/AdGuard/Brave) inyectan reglas cosméticas
   (normalmente un <style> en <head>) que pueden poner display:none / height:0 a
   elementos que "parecen" anuncios. El reproductor NO es un anuncio, así que su
   ocultación es un falso positivo. Defensa en dos capas:
   1) El stage ya reserva su tamaño inline en el HTML (a prueba de minificadores).
   2) Aquí garantizamos además que el stage nunca quede oculto/colapsado, y si el
      proveedor del iframe se bloquea a nivel de red, mostramos un aviso útil.
   Los anuncios reales (.cnx-ad*, data-ad-*) NO se tocan: siguen siendo bloqueables. */
( function () {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) { fn(); }
		else { document.addEventListener( 'DOMContentLoaded', fn ); }
	}

	ready( function () {
		var stages = document.querySelectorAll( '.cnx-screen__stage' );

		// Fuerza que el STAGE permanezca visible y con altura. Solo actúa sobre el
		// propio stage (nunca sobre sus hijos: la ocultación de overlay/poster al
		// reproducir es intencional). setProperty('...','important') gana incluso
		// sobre reglas !important inyectadas por un bloqueador (mayor especificidad
		// del inline).
		var ensureStage = function ( stage ) {
			var cs = window.getComputedStyle( stage );
			if ( 'none' === cs.display ) { stage.style.setProperty( 'display', 'block', 'important' ); }
			if ( 'hidden' === cs.visibility ) { stage.style.setProperty( 'visibility', 'visible', 'important' ); }
			if ( parseFloat( cs.opacity ) < 0.05 ) { stage.style.setProperty( 'opacity', '1', 'important' ); }
			if ( stage.getBoundingClientRect().height < 80 ) {
				stage.style.setProperty( 'position', 'relative', 'important' );
				stage.style.setProperty( 'aspect-ratio', '16 / 9', 'important' );
				stage.style.setProperty( 'min-height', '160px', 'important' );
			}
		};
		var guardRuns = 0;
		var runGuard = function () { stages.forEach( ensureStage ); };
		runGuard();
		window.addEventListener( 'load', runGuard );

		// Los bloqueadores inyectan sus reglas como <style>/<link> en <head>. Se
		// observa SOLO <head> (no todo el documento) y se reevalúa el guard cuando
		// aparece una inyección, con un techo para evitar bucles.
		if ( window.MutationObserver ) {
			var mo = new MutationObserver( function ( muts ) {
				var injected = muts.some( function ( m ) {
					return Array.prototype.some.call( m.addedNodes, function ( n ) {
						return 'STYLE' === n.nodeName || 'LINK' === n.nodeName;
					} );
				} );
				if ( ! injected ) { return; }
				guardRuns++;
				if ( guardRuns > 8 ) { return; }
				setTimeout( runGuard, 0 );
			} );
			mo.observe( document.head, { childList: true } );
		}

		stages.forEach( function ( stage ) {
			var overlay = stage.querySelector( '.cnx-screen__overlay' );
			if ( ! overlay ) { return; }
			overlay.addEventListener( 'click', function () { play( stage ); } );
		} );

		// Pestañas de servidor: cambian la fuente actual del stage. Una pestaña
		// puede ser una URL (data-src → iframe propio) o un [shortcode]
		// (data-embed → contenedor .cnx-screen__embed renderizado en el HTML).
		var servers = document.querySelectorAll( '.cnx-screen__server' );
		servers.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var stage = document.querySelector( '.cnx-screen__stage' );
				if ( ! stage ) { return; }
				var src = btn.getAttribute( 'data-src' ) || '';
				stage.setAttribute( 'data-src', src );
				servers.forEach( function ( b ) { b.classList.remove( 'is-active' ); b.setAttribute( 'aria-pressed', 'false' ); } );
				btn.classList.add( 'is-active' );
				btn.setAttribute( 'aria-pressed', 'true' );
				removeBlockedNotice( stage );

				hideEmbeds( stage );
				var frame   = stage.querySelector( '.cnx-screen__frame' );
				var embedId = btn.getAttribute( 'data-embed' );

				if ( embedId ) {
					// Pestaña de shortcode: se retira el iframe propio (detiene la
					// reproducción) y se muestra el contenedor del plugin.
					if ( frame ) { frame.parentNode.removeChild( frame ); }
					showEmbed( embedId );
					stage.classList.add( 'is-playing' ); // oculta póster/overlay/aviso.
					return;
				}

				// Pestaña de URL: comportamiento original si ya había iframe.
				if ( frame ) {
					frame.src = src;
					return;
				}
				// Sin iframe: si veníamos de un shortcode (is-playing sin frame) o el
				// stage no tiene overlay de play (la 1ª fuente era un shortcode), se
				// reproduce directamente — no hay póster útil al que volver.
				if ( stage.classList.contains( 'is-playing' ) || ! stage.querySelector( '.cnx-screen__overlay' ) ) {
					stage.classList.remove( 'is-playing' );
					play( stage );
				}
			} );
		} );
	} );

	function play( stage ) {
		if ( stage.classList.contains( 'is-playing' ) ) { return; }
		var src = stage.getAttribute( 'data-src' );
		if ( ! src ) { return; }
		var iframe = document.createElement( 'iframe' );
		iframe.className = 'cnx-screen__frame';
		iframe.src = appendAutoplay( src );
		iframe.setAttribute( 'allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen' );
		iframe.setAttribute( 'allowfullscreen', '' );
		iframe.setAttribute( 'title', stage.getAttribute( 'data-title' ) || 'Video' );
		iframe.setAttribute( 'loading', 'eager' );
		stage.appendChild( iframe );
		stage.classList.add( 'is-playing' );

		// Detección de bloqueo a nivel de red: si el bloqueador colapsa el iframe,
		// queda como un documento de mismo origen (about:blank) con el body vacío.
		// Un proveedor real es de origen cruzado → acceder a contentDocument lanza
		// SecurityError (lo capturamos = cargó bien). Sin timeouts → sin falsos
		// positivos por cargas lentas.
		iframe.addEventListener( 'load', function () {
			try {
				var doc = iframe.contentDocument || ( iframe.contentWindow && iframe.contentWindow.document );
				if ( doc && doc.body && '' === doc.body.innerHTML.replace( /\s/g, '' ) ) {
					showBlockedNotice( stage, src );
				} else {
					removeBlockedNotice( stage );
				}
			} catch ( e ) {
				removeBlockedNotice( stage );
			}
		} );
	}

	// Oculta todos los contenedores de shortcode del stage. Ocultar no basta
	// para silenciarlos: se pausan los <video>/<audio> y los iframes internos se
	// descargan (guardando su src en data-cnx-src para restaurarlo al volver).
	function hideEmbeds( stage ) {
		stage.querySelectorAll( '.cnx-screen__embed' ).forEach( function ( box ) {
			if ( box.hidden ) { return; }
			box.hidden = true;
			box.querySelectorAll( 'video,audio' ).forEach( function ( media ) {
				try { media.pause(); } catch ( e ) { /* sin permisos: ignorar */ }
			} );
			box.querySelectorAll( 'iframe' ).forEach( function ( f ) {
				var current = f.getAttribute( 'src' );
				if ( current && 'about:blank' !== current ) {
					f.setAttribute( 'data-cnx-src', current );
					f.setAttribute( 'src', 'about:blank' );
				}
			} );
		} );
	}

	function showEmbed( id ) {
		var box = document.getElementById( id );
		if ( ! box ) { return; }
		box.querySelectorAll( 'iframe[data-cnx-src]' ).forEach( function ( f ) {
			f.setAttribute( 'src', f.getAttribute( 'data-cnx-src' ) );
			f.removeAttribute( 'data-cnx-src' );
		} );
		box.hidden = false;
	}

	function removeBlockedNotice( stage ) {
		var n = stage.querySelector( '.cnx-screen__blocked' );
		if ( n ) { n.parentNode.removeChild( n ); }
	}

	function showBlockedNotice( stage, src ) {
		if ( stage.querySelector( '.cnx-screen__blocked' ) ) { return; }
		var box = document.createElement( 'div' );
		box.className = 'cnx-screen__blocked';
		box.style.cssText = 'position:absolute;inset:0;z-index:6;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;text-align:center;padding:24px;box-sizing:border-box;background:var(--cnx-bg,#0a0910);color:var(--cnx-text-2,#aab2c6);font:600 14px/1.5 var(--cnx-font-body,system-ui,sans-serif)';
		// Icono construido con el namespace SVG (sin innerHTML → sin riesgo XSS).
		var svgNS = 'http://www.w3.org/2000/svg';
		var svg = document.createElementNS( svgNS, 'svg' );
		svg.setAttribute( 'width', '36' ); svg.setAttribute( 'height', '36' );
		svg.setAttribute( 'viewBox', '0 0 24 24' ); svg.setAttribute( 'fill', 'none' );
		svg.style.stroke = 'var(--cnx-accent,#7c5cff)'; svg.setAttribute( 'stroke-width', '1.5' );
		svg.setAttribute( 'aria-hidden', 'true' );
		var circle = document.createElementNS( svgNS, 'circle' );
		circle.setAttribute( 'cx', '12' ); circle.setAttribute( 'cy', '12' ); circle.setAttribute( 'r', '10' );
		var line = document.createElementNS( svgNS, 'line' );
		line.setAttribute( 'x1', '4.9' ); line.setAttribute( 'y1', '4.9' );
		line.setAttribute( 'x2', '19.1' ); line.setAttribute( 'y2', '19.1' );
		svg.appendChild( circle ); svg.appendChild( line ); box.appendChild( svg );
		// Textos traducibles (inc/enqueue.php → clipnuvexData.i18n); el literal
		// español es solo el respaldo si el objeto no está disponible.
		var i18n = ( window.clipnuvexData && window.clipnuvexData.i18n ) || {};
		var p = document.createElement( 'p' );
		p.style.cssText = 'margin:0;max-width:320px;';
		p.textContent = i18n.blockedNotice || 'No se pudo cargar el vídeo. Si tienes un bloqueador de anuncios activo, puede estar bloqueando esta fuente.';
		var a = document.createElement( 'a' );
		a.href = src;
		a.target = '_blank';
		a.rel = 'noopener noreferrer';
		a.textContent = i18n.blockedOpen || 'Abrir el vídeo en una pestaña nueva';
		a.style.cssText = 'color:var(--cnx-accent,#7c5cff);font-weight:700;text-decoration:underline;text-underline-offset:3px';
		box.appendChild( p );
		box.appendChild( a );
		stage.appendChild( box );
	}

	function appendAutoplay( src ) {
		try {
			var u = new URL( src, window.location.origin );
			if ( /youtube\.com|youtube-nocookie\.com|vimeo\.com/.test( u.hostname ) ) {
				u.searchParams.set( 'autoplay', '1' );
			}
			return u.toString();
		} catch ( e ) {
			return src;
		}
	}
} )();
