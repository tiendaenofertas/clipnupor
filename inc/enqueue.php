<?php
/**
 * Encolado de estilos, scripts y fuentes.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encola los assets del front-end.
 */
function clipnuvex_enqueue_assets() {
	$ver = CLIPNUVEX_VERSION;

	// CSS: en producción se sirve un ÚNICO bundle minificado (1 request, sin
	// comentarios → "minify CSS" y menos recursos que bloquean el render). En
	// desarrollo (WP_DEBUG) se cargan los 6 archivos sueltos para poder editar.
	$use_combined = ! ( defined( 'WP_DEBUG' ) && WP_DEBUG );

	// Fuentes: self-host si existen los woff2; si no, Fontshare como respaldo. En
	// producción con self-host, el @font-face se inyecta inline en el CSS crítico
	// (clipnuvex_inline_critical_css) y se omite el <link> de fonts.css: la fuente
	// queda disponible en el primer pintado (sin reflujo/CLS) y hay 1 request menos.
	$fonts_local  = file_exists( CLIPNUVEX_DIR . 'assets/fonts/fonts.css' );
	$inline_fonts = $use_combined && $fonts_local && '' !== clipnuvex_font_face_css();
	if ( ! $inline_fonts ) {
		if ( $fonts_local ) {
			wp_enqueue_style( 'clipnuvex-fonts', CLIPNUVEX_URI . 'assets/fonts/fonts.css', array(), $ver );
		} else {
			wp_enqueue_style( 'clipnuvex-fonts', 'https://api.fontshare.com/v2/css?f[]=clash-display@500,600,700&f[]=satoshi@400,500,700,900&display=swap', array(), null );
		}
	}
	$combined     = $use_combined ? clipnuvex_combined_css() : '';

	if ( $combined ) {
		// Sin dependencia de las fuentes: el bundle no debe esperar al <link> de
		// fuentes (que además ya es asíncrono). En producción este <link> se
		// convierte en carga asíncrona (ver clipnuvex_async_main_css_tag) y el
		// CSS crítico se inyecta inline en <head> (clipnuvex_inline_critical_css)
		// → el primer pintado (FCP) no espera a descargar los ~35 KB del bundle.
		wp_enqueue_style( 'clipnuvex', $combined, array(), null );
		$main_handle = 'clipnuvex';
	} else {
		wp_enqueue_style( 'clipnuvex-tokens', CLIPNUVEX_URI . 'assets/css/tokens.css', array( 'clipnuvex-fonts' ), $ver );
		wp_enqueue_style( 'clipnuvex-base', CLIPNUVEX_URI . 'assets/css/base.css', array( 'clipnuvex-tokens' ), $ver );
		wp_enqueue_style( 'clipnuvex-layout', CLIPNUVEX_URI . 'assets/css/layout.css', array( 'clipnuvex-base' ), $ver );
		wp_enqueue_style( 'clipnuvex-components', CLIPNUVEX_URI . 'assets/css/components.css', array( 'clipnuvex-layout' ), $ver );
		wp_enqueue_style( 'clipnuvex-views', CLIPNUVEX_URI . 'assets/css/views.css', array( 'clipnuvex-components' ), $ver );
		$main_handle = 'clipnuvex-views';
	}

	// Inyección de tokens overridables desde el panel (acento, colores, fuentes…).
	wp_add_inline_style( $main_handle, clipnuvex_inline_token_css() );

	// JS de interfaz.
	wp_enqueue_script( 'clipnuvex-app', CLIPNUVEX_URI . 'assets/js/app.js', array(), $ver, true );
	wp_enqueue_script( 'clipnuvex-player', CLIPNUVEX_URI . 'assets/js/lazy-player.js', array(), $ver, true );

	wp_localize_script(
		'clipnuvex-app',
		'clipnuvexData',
		array(
			'restUrl'  => esc_url_raw( rest_url( 'clipnuvex/v1/search' ) ),
			'viewUrl'  => esc_url_raw( rest_url( 'clipnuvex/v1/view' ) ),
			'videoId'  => is_singular( 'video' ) ? get_queried_object_id() : 0,
			'ajaxUrl'  => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
			// Sin 'nonce': los endpoints REST del theme son públicos y un nonce
			// incrustado en HTML cacheado caduca (12-24 h) → 403 para todos los
			// visitantes. Nunca reintroducirlo para /search ni /view.
			'homeUrl'  => esc_url_raw( home_url( '/' ) ),
			'searchUrl'=> esc_url_raw( home_url( '/?s=' ) ),
			'i18n'     => array(
				'categories'    => __( 'Categorías', 'clipnuvex' ),
				'videos'        => __( 'Vídeos', 'clipnuvex' ),
				'viewAll'       => clipnuvex_text( 'txt_view_all_results' ),
				'noResults'     => __( 'Sin resultados', 'clipnuvex' ),
				'recsTitle'     => clipnuvex_text( 'txt_search_recs' ),
				'genre'         => __( 'Género', 'clipnuvex' ),
				// Aviso del reproductor cuando el proveedor está bloqueado (lazy-player.js).
				'blockedNotice' => __( 'No se pudo cargar el vídeo. Si tienes un bloqueador de anuncios activo, puede estar bloqueando esta fuente.', 'clipnuvex' ),
				'blockedOpen'   => __( 'Abrir el vídeo en una pestaña nueva', 'clipnuvex' ),
			),
		)
	);

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'clipnuvex_enqueue_assets' );

/**
 * Devuelve la URL de un bundle CSS combinado y minificado (1 solo archivo),
 * generándolo bajo demanda en uploads/clipnuvex/. Cacheado por versión +
 * filemtime de las fuentes. Devuelve '' si no se puede (entonces se sirven los
 * archivos sueltos como respaldo).
 *
 * @return string
 */
function clipnuvex_combined_css() {
	$sources = array( 'tokens', 'base', 'layout', 'components', 'views' );
	$paths   = array();
	$mtime   = 0;
	foreach ( $sources as $s ) {
		$p = CLIPNUVEX_DIR . 'assets/css/' . $s . '.css';
		if ( ! file_exists( $p ) ) {
			return '';
		}
		$paths[] = $p;
		$mtime   = max( $mtime, (int) filemtime( $p ) );
	}

	$upload = wp_upload_dir();
	if ( ! empty( $upload['error'] ) ) {
		return '';
	}
	$dir = trailingslashit( $upload['basedir'] ) . 'clipnuvex';
	$key = substr( md5( CLIPNUVEX_VERSION . '|' . $mtime ), 0, 12 );
	$file = $dir . '/clipnuvex-' . $key . '.min.css';
	$url  = trailingslashit( $upload['baseurl'] ) . 'clipnuvex/clipnuvex-' . $key . '.min.css';

	if ( file_exists( $file ) ) {
		return set_url_scheme( $url );
	}
	if ( ! wp_mkdir_p( $dir ) || ! wp_is_writable( $dir ) ) {
		return '';
	}

	// Política de caché larga para el bundle (nombre con hash → inmutable). Mejora
	// "efficient cache policy" en Apache; en nginx se ignora sin efectos adversos.
	$htaccess = $dir . '/.htaccess';
	if ( ! file_exists( $htaccess ) ) {
		$rules = "<IfModule mod_headers.c>\n<FilesMatch \"\\.min\\.css$\">\nHeader set Cache-Control \"public, max-age=31536000, immutable\"\n</FilesMatch>\n</IfModule>\n";
		@file_put_contents( $htaccess, $rules ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.NoSilencedErrors.Discouraged
	}

	$css = '';
	foreach ( $paths as $p ) {
		$css .= (string) file_get_contents( $p ) . "\n"; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
	$css = clipnuvex_minify_css( $css );

	// Limpia bundles antiguos para no acumular.
	foreach ( (array) glob( $dir . '/clipnuvex-*.min.css' ) as $old ) {
		wp_delete_file( $old );
	}
	if ( false === file_put_contents( $file, $css ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return '';
	}
	return set_url_scheme( $url );
}

/**
 * Minificador CSS sencillo y seguro (sin tocar espacios dentro de calc()/env()).
 *
 * @param string $css CSS de entrada.
 * @return string
 */
function clipnuvex_minify_css( $css ) {
	$css = preg_replace( '#/\*(?!!).*?\*/#s', '', $css );   // comentarios.
	$css = preg_replace( '/\s+/', ' ', $css );               // espacios en blanco a uno.
	$css = preg_replace( '/\s*([{}:;,>])\s*/', '$1', $css ); // alrededor de separadores (no toca +,-,*,/).
	$css = str_replace( array( ';}', ' !important' ), array( '}', '!important' ), $css );
	return trim( $css );
}

/**
 * Carga la hoja de fuentes SIN bloquear el render (patrón media=print → all).
 * Texto visible inmediato (font-display:swap) y fallback en <noscript>.
 *
 * @param string $tag    Etiqueta <link> generada.
 * @param string $handle Handle del estilo.
 * @return string
 */
function clipnuvex_async_font_tag( $tag, $handle ) {
	if ( 'clipnuvex-fonts' !== $handle ) {
		return $tag;
	}
	$noscript = '<noscript>' . $tag . '</noscript>';
	if ( preg_match( '/media=([\'"])[^\'"]*\1/', $tag ) ) {
		$async = preg_replace( '/media=([\'"])[^\'"]*\1/', "media=\$1print\$1 onload=\"this.media='all'\"", $tag );
	} else {
		// Sin atributo media: insertar antes del cierre, tolerando tanto `>` (HTML5)
		// como ` />` (XHTML), para no duplicar la fuente si el markup no es XHTML.
		$async = preg_replace( '/\s*\/?>\s*$/', " media='print' onload=\"this.media='all'\">\n", $tag );
	}
	return $async . $noscript;
}
add_filter( 'style_loader_tag', 'clipnuvex_async_font_tag', 10, 2 );

/**
 * Convierte el <link> del bundle principal en carga ASÍNCRONA (no bloquea el
 * render). Junto con el CSS crítico inline (clipnuvex_inline_critical_css), el
 * navegador pinta de inmediato y descarga el bundle completo en paralelo a alta
 * prioridad (rel=preload), reduciendo drásticamente el First Contentful Paint.
 * Solo afecta al handle 'clipnuvex' (modo combinado/producción); en desarrollo,
 * donde se sirven los archivos sueltos, este filtro es no-op.
 *
 * @param string $tag    Etiqueta <link> generada.
 * @param string $handle Handle del estilo.
 * @return string
 */
function clipnuvex_async_main_css_tag( $tag, $handle ) {
	if ( 'clipnuvex' !== $handle ) {
		return $tag;
	}
	$noscript = '<noscript>' . $tag . '</noscript>';
	$async    = preg_replace(
		'/\srel=([\'"])stylesheet\1/',
		" rel=\$1preload\$1 as=\$1style\$1 onload=\"this.onload=null;this.rel='stylesheet'\"",
		$tag
	);
	// Si por algún motivo no se pudo transformar, devolver el tag original (bloqueante
	// pero correcto) en vez de un <link> roto.
	if ( $async === $tag || null === $async ) {
		return $tag;
	}
	return $async . $noscript;
}
add_filter( 'style_loader_tag', 'clipnuvex_async_main_css_tag', 10, 2 );

/**
 * Inyecta el CSS crítico (above-the-fold) inline en <head>, de modo que el
 * primer pintado no dependa de descargar el bundle (que se carga async). Incluye
 * al final los tokens overridables del panel para que el acento/colores
 * personalizados se apliquen ya en ese primer pintado. Solo en producción
 * (modo combinado); en desarrollo (WP_DEBUG) el CSS es bloqueante y no hace falta.
 */
function clipnuvex_inline_critical_css() {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		return;
	}
	$file = CLIPNUVEX_DIR . 'assets/css/critical.css';
	if ( ! file_exists( $file ) || ! clipnuvex_combined_css() ) {
		return;
	}
	static $css = null;
	if ( null === $css ) {
		$raw = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$css = function_exists( 'clipnuvex_minify_css' ) ? clipnuvex_minify_css( $raw ) : $raw;
	}
	// @font-face inline + reglas críticas + tokens del panel. El @font-face va
	// primero para que la fuente self-host (ya precargada) se aplique en el primer
	// pintado y no haya reflujo por "font swap" (CLS). Todo es CSS propio del theme.
	echo '<style id="cnx-critical">' . clipnuvex_font_face_css() . $css . clipnuvex_inline_token_css() . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_head', 'clipnuvex_inline_critical_css', 7 );

/**
 * ¿Alguno de los dos ajustes tipográficos del panel referencia esta familia?
 * Si el admin cambia ambas fuentes, las self-host por defecto dejan de usarse:
 * declararlas/precargarlas sería peso muerto (y un warning de consola
 * "preloaded but not used").
 *
 * @param string $family Nombre de la familia ('Satoshi' | 'Clash Display').
 * @return bool
 */
function clipnuvex_font_needed( $family ) {
	foreach ( array( 'font_display', 'font_body' ) as $id ) {
		$val = trim( (string) clipnuvex_option( $id, '' ) );
		if ( '' === $val ) {
			// Vacío en el panel = se usa el default del registry (misma
			// semántica de fallback que clipnuvex_text).
			$val = (string) clipnuvex_option_default( $id );
		}
		if ( 0 === strcasecmp( $val, $family ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Declaraciones @font-face de las fuentes self-host con URL absoluta (para poder
 * inyectarlas inline en <head>). Devuelve '' si no existen los .woff2 locales.
 * Cada familia solo se declara si algún ajuste tipográfico del panel la usa.
 *
 * @return string
 */
function clipnuvex_font_face_css() {
	$body = CLIPNUVEX_DIR . 'assets/fonts/Satoshi-Variable.woff2';
	$disp = CLIPNUVEX_DIR . 'assets/fonts/ClashDisplay-Variable.woff2';
	if ( ! file_exists( $body ) || ! file_exists( $disp ) ) {
		return '';
	}
	$css = '';
	if ( clipnuvex_font_needed( 'Satoshi' ) ) {
		$ub   = esc_url( CLIPNUVEX_URI . 'assets/fonts/Satoshi-Variable.woff2' );
		$css .= "@font-face{font-family:'Satoshi';src:url('{$ub}') format('woff2');font-weight:300 900;font-display:swap;font-style:normal}";
	}
	if ( clipnuvex_font_needed( 'Clash Display' ) ) {
		$ud   = esc_url( CLIPNUVEX_URI . 'assets/fonts/ClashDisplay-Variable.woff2' );
		$css .= "@font-face{font-family:'Clash Display';src:url('{$ud}') format('woff2');font-weight:200 700;font-display:swap;font-style:normal}";
	}
	return $css;
}

/**
 * Limpia los bundles CSS cacheados al cambiar de versión del theme.
 */
function clipnuvex_clear_css_cache() {
	$upload = wp_upload_dir();
	if ( ! empty( $upload['error'] ) ) {
		return;
	}
	foreach ( (array) glob( trailingslashit( $upload['basedir'] ) . 'clipnuvex/clipnuvex-*.min.css' ) as $old ) {
		wp_delete_file( $old );
	}
}
add_action( 'after_switch_theme', 'clipnuvex_clear_css_cache' );

/**
 * Genera el CSS inline con los tokens overridables desde el panel
 * (colores, fuentes, radio). Lee de la única fuente de verdad: la option array
 * `clipnuvex_options` vía clipnuvex_option() (panel → default del registro).
 *
 * @return string
 */
function clipnuvex_inline_token_css() {
	$accent      = sanitize_hex_color( clipnuvex_option( 'accent', '#7C5CFF' ) ) ?: '#7C5CFF';
	$accent_dark = sanitize_hex_color( clipnuvex_option( 'accent_dark', '#5B3CE0' ) ) ?: '#5B3CE0';
	$bg          = sanitize_hex_color( clipnuvex_option( 'bg', '#0a0910' ) ) ?: '#0a0910';
	$surface     = sanitize_hex_color( clipnuvex_option( 'surface', '#15131e' ) ) ?: '#15131e';
	$text        = sanitize_hex_color( clipnuvex_option( 'text', '#f1f3f8' ) ) ?: '#f1f3f8';
	$text2       = sanitize_hex_color( clipnuvex_option( 'text_2', '#aab2c6' ) ) ?: '#aab2c6';
	$radius      = (int) clipnuvex_option( 'radius_card', 15 );
	// Saneado adicional para contexto CSS: el nombre va dentro de comillas simples
	// en la declaración; eliminar comillas/barras/saltos evita romper la cadena e
	// inyectar CSS arbitrario (defensa en profundidad aunque requiera rol admin).
	$cnx_css_safe = static function ( $val ) {
		return trim( preg_replace( '/[\'";{}\\\\\r\n<>]/', '', sanitize_text_field( $val ) ) );
	};
	$font_disp   = $cnx_css_safe( clipnuvex_option( 'font_display', 'Clash Display' ) );
	$font_body   = $cnx_css_safe( clipnuvex_option( 'font_body', 'Satoshi' ) );

	$css  = ':root{';
	$css .= '--cnx-accent:' . $accent . ';';
	$css .= '--cnx-accent-dark:' . $accent_dark . ';';
	$css .= '--cnx-accent-grad:linear-gradient(135deg,' . $accent . ',' . $accent_dark . ');';
	$css .= '--cnx-bg:' . $bg . ';';
	$css .= '--cnx-surface:' . $surface . ';';
	$css .= '--cnx-text:' . $text . ';';
	$css .= '--cnx-text-2:' . $text2 . ';';
	$css .= '--cnx-radius-card:' . $radius . 'px;';
	if ( $font_disp ) {
		$css .= "--cnx-font-display:'" . $font_disp . "',system-ui,sans-serif;";
	}
	if ( $font_body ) {
		$css .= "--cnx-font-body:'" . $font_body . "',system-ui,sans-serif;";
	}
	// Tarjetas por fila en móvil: el default (2) es el literal de tokens.css →
	// solo se emite cuando el panel lo cambia (salida por defecto idéntica).
	$cnx_cols = (int) clipnuvex_option( 'cards_cols_mobile', 2 );
	if ( $cnx_cols >= 1 && $cnx_cols <= 3 && 2 !== $cnx_cols ) {
		$css .= '--cnx-cols-mobile:' . $cnx_cols . ';';
	}
	// Tokens derivados (inc/colors.php): SOLO los de las bases personalizadas.
	// Con los defaults del registry no se añade nada (salida idéntica a 1.4.x).
	// Van al final para ganar a los 8 tokens base anteriores cuando aplique
	// (p. ej. accent-dark/accent-grad derivados de un acento nuevo).
	if ( function_exists( 'clipnuvex_derived_tokens' ) ) {
		$cnx_bases = array(
			'accent'      => $accent,
			'accent_dark' => $accent_dark,
			'bg'          => $bg,
			'surface'     => $surface,
			'text'        => $text,
			'text_2'      => $text2,
		);
		foreach ( clipnuvex_derived_tokens( $cnx_bases ) as $cnx_tok => $cnx_val ) {
			$css .= '--cnx-' . $cnx_tok . ':' . $cnx_val . ';';
		}
	}
	$css .= '}';

	return $css;
}

/**
 * Preconnect/preload de fuentes para CWV.
 */
function clipnuvex_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type && ! file_exists( CLIPNUVEX_DIR . 'assets/fonts/fonts.css' ) ) {
		// Ambos orígenes de Fontshare: la CSS (api) descubre las woff2 (cdn). El
		// atributo crossorigin es obligatorio para que el preconnect sirva a la
		// descarga de fuentes (que es CORS).
		$urls[] = array(
			'href'        => 'https://api.fontshare.com',
			'crossorigin' => 'anonymous',
		);
		$urls[] = array(
			'href'        => 'https://cdn.fontshare.com',
			'crossorigin' => 'anonymous',
		);
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'clipnuvex_resource_hints', 10, 2 );

/**
 * Preload de la imagen LCP por vista:
 * - Portada: póster del primer vídeo con imagen (con srcset).
 * - Vídeo individual: backdrop del reproductor — al ser un background-image
 *   (div del stage), el navegador lo descubriría tarde sin este preload.
 */
function clipnuvex_preload_lcp_image() {
	// Vídeo individual: el LCP es el backdrop del stage del player.
	if ( is_singular( 'video' ) ) {
		if ( function_exists( 'clipnuvex_video_backdrop' ) ) {
			$cnx_backdrop = clipnuvex_video_backdrop( get_queried_object_id() );
			if ( '' === $cnx_backdrop && function_exists( 'clipnuvex_option' ) ) {
				$cnx_backdrop = (string) clipnuvex_option( 'default_poster', '' );
			}
			if ( $cnx_backdrop ) {
				printf(
					'<link rel="preload" as="image" href="%s" fetchpriority="high">' . "\n",
					esc_url( $cnx_backdrop )
				);
			}
		}
		return;
	}

	if ( ! ( is_front_page() || is_home() ) || is_paged() ) {
		return;
	}
	// Se busca entre los primeros vídeos el primero que tenga póster: así, si el
	// vídeo más reciente no tiene imagen destacada, el preload apunta igualmente
	// a la primera imagen real de la cuadrícula (que es la candidata a LCP).
	$recent = get_posts(
		array(
			'post_type'           => 'video',
			'posts_per_page'      => 6,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'fields'              => 'ids',
		)
	);
	$thumb_id = 0;
	foreach ( (array) $recent as $cnx_rid ) {
		$cnx_tid = get_post_thumbnail_id( $cnx_rid );
		if ( $cnx_tid ) {
			$thumb_id = $cnx_tid;
			break;
		}
	}
	if ( ! $thumb_id ) {
		return;
	}
	// Misma resolución de imagen que la tarjeta (tamaño/respaldo/sizes según la
	// orientación del panel): el preload debe coincidir byte a byte con el src
	// de la primera tarjeta o se desperdicia (y el navegador avisa).
	$cnx_img = clipnuvex_card_image( $thumb_id );
	if ( ! $cnx_img['url'] ) {
		return;
	}
	printf(
		'<link rel="preload" as="image" href="%s"%s imagesizes="%s" fetchpriority="high">' . "\n",
		esc_url( $cnx_img['url'] ),
		$cnx_img['srcset'] ? ' imagesrcset="' . esc_attr( $cnx_img['srcset'] ) . '"' : '',
		esc_attr( $cnx_img['sizes'] )
	);
}
add_action( 'wp_head', 'clipnuvex_preload_lcp_image', 2 );

/**
 * Preload de las fuentes self-host (si existen y el panel las usa).
 */
function clipnuvex_preload_fonts() {
	$fonts = array(
		'Satoshi'       => 'assets/fonts/Satoshi-Variable.woff2',
		'Clash Display' => 'assets/fonts/ClashDisplay-Variable.woff2',
	);
	foreach ( $fonts as $family => $font ) {
		if ( ! clipnuvex_font_needed( $family ) ) {
			continue; // Fuente sustituida desde el panel: no precargar peso muerto.
		}
		if ( file_exists( CLIPNUVEX_DIR . $font ) ) {
			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
				esc_url( CLIPNUVEX_URI . $font )
			);
		}
	}
}
add_action( 'wp_head', 'clipnuvex_preload_fonts', 1 );

/**
 * Cabeceras de seguridad universales (no rompen integraciones). Deliberadamente
 * NO se envían X-Frame-Options ni CSP desde el theme: una CSP estricta rompería
 * AdSense/GAM/analytics (justo lo que monetiza el theme) y X-Frame-Options puede
 * interferir con previsualizaciones/embeds legítimos; esas cabeceras deben
 * gestionarse a nivel de servidor o plugin de seguridad por el dueño del sitio.
 */
function clipnuvex_send_safe_headers() {
	if ( is_admin() || headers_sent() ) {
		return;
	}
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
}
add_action( 'send_headers', 'clipnuvex_send_safe_headers' );
