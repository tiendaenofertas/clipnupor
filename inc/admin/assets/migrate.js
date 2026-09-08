/* Clipnuvex — Migrar entradas a vídeos: bucle AJAX por lotes con progreso,
   reanudación e informe. Sin dependencias. */
( function () {
	'use strict';

	var cfg = window.clipnuvexMigrate || {};
	var root = document.getElementById( 'clipnuvex-migrate' );
	if ( ! root || ! cfg.ajaxUrl ) { return; }

	var form     = document.getElementById( 'clipnuvex-migrate-form' );
	var progress = document.getElementById( 'clipnuvex-migrate-progress' );
	var bar      = document.getElementById( 'clipnuvex-migrate-bar' );
	var status   = document.getElementById( 'clipnuvex-migrate-status' );
	var results  = document.getElementById( 'clipnuvex-migrate-results' );
	var summary  = document.getElementById( 'clipnuvex-migrate-summary' );
	var tables   = document.getElementById( 'clipnuvex-migrate-tables' );
	var after    = document.getElementById( 'clipnuvex-migrate-after' );
	var buttons  = root.querySelectorAll( 'button[data-op]' );
	var i18n     = cfg.i18n || {};
	var busy     = false;

	function t( key, fallback ) { return i18n[ key ] || fallback; }

	function readOpts() {
		var data = new FormData( form );
		var opts = {
			statuses: data.getAll( 'statuses[]' ),
			categories: data.getAll( 'categories[]' ),
			source_meta: data.get( 'source_meta' ) || '',
			detect_content: !! data.get( 'detect_content' ),
			strip_embed: !! data.get( 'strip_embed' ),
			skip_nosource: !! data.get( 'skip_nosource' ),
			include_password: !! data.get( 'include_password' ),
			map: {},
			batch: parseInt( data.get( 'batch' ), 10 ) || 25
		};
		[ 'quality', 'language', 'year', 'duration', 'views', 'featured' ].forEach( function ( k ) {
			opts.map[ k ] = data.get( 'map[' + k + ']' ) || '';
		} );
		return opts;
	}

	function request( op, extra ) {
		var body = new FormData();
		body.append( 'action', 'clipnuvex_migrate' );
		body.append( 'nonce', cfg.nonce );
		body.append( 'op', op );
		body.append( 'opts', JSON.stringify( readOpts() ) );
		Object.keys( extra || {} ).forEach( function ( k ) { body.append( k, extra[ k ] ); } );
		return fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					throw new Error( ( json && json.data && json.data.message ) ? json.data.message : t( 'error', 'Error' ) );
				}
				return json.data;
			} );
	}

	function setBusy( on ) {
		busy = on;
		buttons.forEach( function ( b ) { b.disabled = on; } );
		window.onbeforeunload = on ? function () { return t( 'leave', '' ); } : null;
	}

	function showProgress( done, total, text ) {
		progress.hidden = false;
		var pct = total > 0 ? Math.min( 100, Math.round( done * 100 / total ) ) : 0;
		bar.style.width = pct + '%';
		status.textContent = text + ' ' + done + ' / ' + total + ' (' + pct + '%)';
	}

	function label( key ) { return ( i18n.status && i18n.status[ key ] ) || key; }

	function renderRows( rowsByStatus, counts, sources, mode ) {
		results.hidden = false;
		var parts = [];
		Object.keys( counts ).forEach( function ( k ) { parts.push( label( k ) + ': ' + counts[ k ] ); } );
		var src = [];
		Object.keys( sources || {} ).forEach( function ( k ) { src.push( k + ': ' + sources[ k ] ); } );
		summary.textContent = parts.join( ' · ' ) + ( src.length ? ' — ' + t( 'sources', 'Fuentes' ) + ': ' + src.join( ', ' ) : '' );
		tables.innerHTML = '';
		var order = [ 'error', 'skipped_collision', 'skipped_password', 'skipped_nosource', 'ok', 'migrated', 'reverted' ];
		order.forEach( function ( key ) {
			var rows = rowsByStatus[ key ];
			if ( ! rows || ! rows.length ) { return; }
			var h = document.createElement( 'h3' );
			h.textContent = label( key ) + ' (' + counts[ key ] + ')';
			tables.appendChild( h );
			var table = document.createElement( 'table' );
			table.className = 'widefat striped';
			var thead = table.createTHead();
			var hr = thead.insertRow();
			[ 'ID', t( 'title', 'Título' ), t( 'source', 'Fuente' ), 'URL', t( 'note', 'Nota' ) ].forEach( function ( c ) {
				var th = document.createElement( 'th' ); th.textContent = c; hr.appendChild( th );
			} );
			var tbody = table.createTBody();
			rows.slice( 0, 200 ).forEach( function ( r ) {
				var tr = tbody.insertRow();
				var c1 = tr.insertCell();
				var a = document.createElement( 'a' );
				a.href = cfg.editUrl.replace( '%d', r.id );
				a.textContent = '#' + r.id;
				c1.appendChild( a );
				tr.insertCell().textContent = r.title || '';
				tr.insertCell().textContent = r.source || '';
				var c4 = tr.insertCell();
				var url = r.new_url || r.old_url || '';
				if ( url ) {
					var u = document.createElement( 'a' ); u.href = url; u.target = '_blank'; u.rel = 'noopener'; u.textContent = url; c4.appendChild( u );
				}
				tr.insertCell().textContent = r.note || '';
			} );
			tables.appendChild( table );
			if ( rows.length > 200 ) {
				var more = document.createElement( 'p' );
				more.className = 'description';
				more.textContent = t( 'more', '' ).replace( '%d', rows.length - 200 );
				tables.appendChild( more );
			}
		} );
		after.hidden = ( mode !== 'migrate' );
	}

	function collect( acc, rows ) {
		rows.forEach( function ( r ) {
			acc.counts[ r.status ] = ( acc.counts[ r.status ] || 0 ) + 1;
			if ( ! acc.rows[ r.status ] ) { acc.rows[ r.status ] = []; }
			acc.rows[ r.status ].push( r );
			if ( r.status === 'migrated' && r.source ) { acc.sources[ r.source ] = ( acc.sources[ r.source ] || 0 ) + 1; }
			if ( r.status === 'ok' && r.source ) { acc.sources[ r.source ] = ( acc.sources[ r.source ] || 0 ) + 1; }
		} );
	}

	function fail( err ) {
		setBusy( false );
		status.textContent = t( 'error', 'Error' ) + ': ' + err.message;
		progress.hidden = false;
	}

	function runScan() {
		var acc = { counts: {}, rows: {}, sources: {} };
		var total = 0, done = 0, afterId = 0;
		setBusy( true );
		results.hidden = true;
		function step() {
			request( 'scan', { after_id: afterId } ).then( function ( data ) {
				total = data.total;
				afterId = data.last_id;
				done += data.rows.length;
				collect( acc, data.rows );
				showProgress( done, total, t( 'scanning', 'Analizando' ) );
				if ( data.done ) {
					setBusy( false );
					status.textContent = t( 'scanDone', 'Análisis terminado (no se ha escrito nada).' );
					renderRows( acc.rows, acc.counts, acc.sources, 'scan' );
				} else { step(); }
			} ).catch( fail );
		}
		step();
	}

	function runLoop( startOp, stepOp, resume, mode ) {
		var acc = { counts: {}, rows: {}, sources: {} };
		setBusy( true );
		results.hidden = true;
		request( startOp, resume ? { resume: 1 } : {} ).then( function ( data ) {
			var state = data.state;
			showProgress( state.done || 0, state.total || 0, mode === 'migrate' ? t( 'migrating', 'Migrando' ) : t( 'reverting', 'Revirtiendo' ) );
			function step() {
				request( stepOp ).then( function ( d ) {
					collect( acc, d.rows );
					showProgress( d.state.done || 0, d.state.total || 0, mode === 'migrate' ? t( 'migrating', 'Migrando' ) : t( 'reverting', 'Revirtiendo' ) );
					if ( d.done ) {
						setBusy( false );
						status.textContent = mode === 'migrate' ? t( 'migrateDone', 'Migración terminada.' ) : t( 'revertDone', 'Reversión terminada.' );
						renderRows( acc.rows, d.state.counts || acc.counts, d.state.sources || acc.sources, mode );
						window.setTimeout( function () { window.location.reload(); }, 4000 );
					} else { step(); }
				} ).catch( fail );
			}
			step();
		} ).catch( fail );
	}

	buttons.forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			if ( busy ) { return; }
			var op = btn.getAttribute( 'data-op' );
			if ( op === 'scan' ) { runScan(); }
			else if ( op === 'migrate' ) { if ( window.confirm( t( 'confirmMigrate', '¿Migrar?' ) ) ) { runLoop( 'start', 'migrate', false, 'migrate' ); } }
			else if ( op === 'resume' ) { runLoop( 'start', 'migrate', true, 'migrate' ); }
			else if ( op === 'revert' ) { if ( window.confirm( t( 'confirmRevert', '¿Revertir?' ) ) ) { runLoop( 'revert_start', 'revert', false, 'revert' ); } }
			else if ( op === 'revert_resume' ) { runLoop( 'revert_start', 'revert', true, 'revert' ); }
			else if ( op === 'reset' ) { request( 'reset' ).then( function () { window.location.reload(); } ).catch( fail ); }
		} );
	} );
} )();
