<?php
/**
 * URLs de vídeo en la raíz del dominio (sin el prefijo /video/).
 *
 * Con el ajuste SEO → "Vídeos en la raíz del dominio" (activado por defecto)
 * cada vídeo se publica en  tudominio.com/{slug}/  en vez de
 * tudominio.com/video/{slug}/. El archivo "Explorar" sigue en /video/.
 *
 * Cómo funciona (núcleo, sin reglas de reescritura nuevas):
 * 1. `post_type_link`: el permalink del vídeo pasa a home_url('/{slug}/').
 *    Todo el theme (tarjetas, sitemap, canonical, schema, buscador, migas)
 *    usa get_permalink(), así que lo sigue automáticamente.
 * 2. `request`: WordPress no sabe si /{slug}/ es una página, una entrada o
 *    un vídeo. Si el slug no pertenece a una página/entrada (el contenido
 *    nativo SIEMPRE gana) y existe un vídeo publicado con ese slug, la
 *    petición se resuelve como ese vídeo. Coste: UNA consulta indexada solo
 *    en URLs de un segmento que no sean una página verificada.
 * 3. `template_redirect`: 301 entre las dos formas conocidas de la URL
 *    (/video/{slug}/ ⇄ /{slug}/) hacia la canónica según el ajuste, para que
 *    Google traspase el posicionamiento y no haya contenido duplicado.
 * 4. `wp_unique_post_slug`: al guardar un vídeo, una página o una entrada de
 *    raíz, el slug se hace único ENTRE los tres tipos (sufijo -2, -3…), así
 *    nunca compiten dos contenidos por la misma URL.
 * 5. Colisiones heredadas (anteriores a 1.5.2): esos vídeos conservan su URL
 *    /video/{slug}/ (la página/entrada gana en la raíz) y el panel lo avisa.
 *    La lista se cachea en un transient con sentinel (nunca un array vacío
 *    inmortal) y se invalida al guardar/borrar contenido.
 * 6. `url_to_postid`: el core no pasa por `request` al resolver una URL a un
 *    post (oEmbed, incrustar pegando la URL en otro WordPress); se traduce
 *    /{slug}/ a /video/{slug}/ con la misma decisión que el punto 2. Y el
 *    punto 2 resuelve también /{slug}/embed/ (iframe del oEmbed).
 *
 * Con enlaces permanentes "simples" (sin estructura) nada de esto aplica.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Tipos nativos que viven en la raíz y pueden chocar con un vídeo. */
function clipnuvex_root_native_types() {
	return array( 'page', 'post' );
}

/**
 * ¿Están activas las URLs de vídeo en la raíz? Requiere estructura de
 * enlaces permanentes y el ajuste del panel (default: activado).
 *
 * @return bool
 */
function clipnuvex_root_urls_enabled() {
	if ( '' === (string) get_option( 'permalink_structure' ) ) {
		return false;
	}
	return ! function_exists( 'clipnuvex_is_on' ) || clipnuvex_is_on( 'video_root_urls' );
}

/**
 * Slug del archivo del CPT (por defecto 'video').
 *
 * @return string
 */
function clipnuvex_video_archive_slug() {
	$pt = get_post_type_object( 'video' );
	if ( $pt && ! empty( $pt->rewrite['slug'] ) ) {
		return trim( (string) $pt->rewrite['slug'], '/' );
	}
	return 'video';
}

/**
 * ¿Hay un contenido "vivo" (no papelera/auto-draft) de esos tipos con ese slug?
 *
 * @param string   $slug           post_name.
 * @param string[] $types          post types.
 * @param bool     $published_only Solo publicados.
 * @param int      $exclude        ID a excluir (el propio post al guardar).
 * @return int ID o 0.
 */
function clipnuvex_slug_owner( $slug, $types, $published_only = false, $exclude = 0 ) {
	global $wpdb;
	$slug = sanitize_title_for_query( $slug );
	if ( '' === $slug ) {
		return 0;
	}
	$types  = array_map( 'sanitize_key', (array) $types );
	$in     = "'" . implode( "','", array_map( 'esc_sql', $types ) ) . "'";
	$status = $published_only ? "= 'publish'" : "NOT IN ('trash','auto-draft','inherit')";
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type IN ($in) AND post_status $status AND ID <> %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$slug,
			(int) $exclude
		)
	);
}

/**
 * Slugs de vídeos publicados que chocan con una página/entrada (colisiones
 * heredadas). Cacheado 12 h con sentinel 'none' (un array vacío en un
 * transient sin _timeout_ sería inmortal — ver gotcha del repo).
 *
 * @return string[]
 */
function clipnuvex_root_slug_collisions() {
	static $memo = null;
	if ( null !== $memo ) {
		return $memo;
	}
	$cached = get_transient( 'clipnuvex_root_slug_collisions' );
	if ( 'none' === $cached ) {
		$memo = array();
		return $memo;
	}
	if ( is_array( $cached ) && ! empty( $cached ) ) {
		$memo = array_map( 'strval', $cached );
		return $memo;
	}
	global $wpdb;
	$in   = "'" . implode( "','", array_map( 'esc_sql', clipnuvex_root_native_types() ) ) . "'";
	$rows = $wpdb->get_col(
		"SELECT DISTINCT v.post_name FROM {$wpdb->posts} v
		 INNER JOIN {$wpdb->posts} o ON o.post_name = v.post_name AND o.post_type IN ($in) AND o.post_status NOT IN ('trash','auto-draft','inherit')
		 WHERE v.post_type = 'video' AND v.post_status = 'publish' LIMIT 500" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);
	$memo = array_values( array_filter( array_map( 'strval', (array) $rows ) ) );
	set_transient( 'clipnuvex_root_slug_collisions', $memo ? $memo : 'none', 12 * HOUR_IN_SECONDS );
	return $memo;
}

/**
 * Invalida la caché de colisiones al guardar/borrar vídeos, páginas o entradas.
 *
 * @param int $post_id ID del post.
 */
function clipnuvex_root_flush_collisions( $post_id ) {
	$type = get_post_type( $post_id );
	if ( 'video' === $type || in_array( $type, clipnuvex_root_native_types(), true ) ) {
		delete_transient( 'clipnuvex_root_slug_collisions' );
	}
}
add_action( 'save_post', 'clipnuvex_root_flush_collisions' );
add_action( 'deleted_post', 'clipnuvex_root_flush_collisions' );
add_action( 'trashed_post', 'clipnuvex_root_flush_collisions' );
add_action( 'untrashed_post', 'clipnuvex_root_flush_collisions' );

/**
 * ¿Este vídeo vive en la raíz? (ajuste activo, slug utilizable y sin colisión).
 *
 * @param WP_Post $post Vídeo.
 * @return bool
 */
function clipnuvex_video_lives_at_root( $post ) {
	if ( ! $post || 'video' !== $post->post_type || empty( $post->post_name ) ) {
		return false;
	}
	if ( in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft', 'future' ), true ) ) {
		return false;
	}
	if ( ! clipnuvex_root_urls_enabled() ) {
		return false;
	}
	return ! in_array( (string) $post->post_name, clipnuvex_root_slug_collisions(), true );
}

/**
 * 1) Permalink del vídeo en la raíz.
 *
 * @param string  $link Permalink por defecto (/video/{slug}/).
 * @param WP_Post $post Post.
 * @return string
 */
function clipnuvex_root_video_link( $link, $post ) {
	if ( ! clipnuvex_video_lives_at_root( $post ) ) {
		return $link;
	}
	return home_url( user_trailingslashit( $post->post_name ) );
}
add_filter( 'post_type_link', 'clipnuvex_root_video_link', 10, 2 );

/**
 * 2) Resuelve /{slug}/ como vídeo cuando no es una página ni una entrada.
 *
 * Se aplica SIEMPRE que haya estructura de enlaces (aunque el ajuste esté
 * apagado): así una URL de raíz compartida nunca da 404 — el template_redirect
 * la lleva a la canónica que corresponda.
 *
 * @param array $qv Query vars parseadas.
 * @return array
 */
function clipnuvex_root_video_request( $qv ) {
	global $wp_rewrite;
	if ( is_admin() || '' === (string) get_option( 'permalink_structure' ) ) {
		return $qv;
	}
	// `embed` NO se salta: /{slug}/embed/ es el iframe que devuelve el oEmbed del
	// vídeo (get_post_embed_url = permalink + /embed/) y debe resolverse igual.
	foreach ( array( 'post_type', 'attachment', 'feed', 'error' ) as $skip ) {
		if ( ! empty( $qv[ $skip ] ) ) {
			return $qv;
		}
	}
	$slug = '';
	if ( ! empty( $qv['pagename'] ) ) {
		// Con reglas de página "verbosas" (estructura /%postname%/) WordPress ya
		// verificó que la página existe → no se toca nada.
		if ( $wp_rewrite && $wp_rewrite->use_verbose_page_rules ) {
			return $qv;
		}
		$slug = (string) $qv['pagename'];
	} elseif ( ! empty( $qv['name'] ) ) {
		$slug = (string) $qv['name'];
	}
	if ( '' === $slug || false !== strpos( $slug, '/' ) ) {
		return $qv;
	}
	if ( in_array( $slug, clipnuvex_root_slug_collisions(), true ) ) {
		return $qv; // El contenido nativo gana.
	}
	$video_id = clipnuvex_slug_owner( $slug, array( 'video' ), true );
	if ( ! $video_id ) {
		return $qv;
	}
	// Cinturón: si a pesar de la caché existe una página/entrada con ese slug, gana ella.
	if ( clipnuvex_slug_owner( $slug, clipnuvex_root_native_types() ) ) {
		return $qv;
	}
	$keep = array_intersect_key( $qv, array_flip( array( 'page', 'cpage', 'preview', 'preview_id', 'preview_nonce', 'embed' ) ) );
	return array_merge(
		$keep,
		array(
			'post_type' => 'video',
			'video'     => $slug,
			'name'      => $slug,
		)
	);
}
add_filter( 'request', 'clipnuvex_root_video_request', 5 );

/**
 * 6) url_to_postid() no pasa por el filtro `request`: la URL raíz de un vídeo
 * devolvía 0 y el endpoint oEmbed del core (/wp-json/oembed/1.0/embed?url=…)
 * respondía 404, así que el <link rel="alternate" type="application/json+oembed">
 * de cada vídeo enlazaba a un 404 y no se podía incrustar un vídeo pegando su
 * URL en otro WordPress (caso real en producción, 1.5.2 → 1.5.5).
 *
 * Se traduce /{slug}/ a /video/{slug}/ (forma que el core sí resuelve) con la
 * MISMA decisión que clipnuvex_root_video_request: solo URLs de un segmento,
 * vídeo publicado con ese slug y sin página/entrada que lo posea (el contenido
 * nativo gana). Igual que el punto 2, se aplica aunque el ajuste esté apagado:
 * una URL de raíz compartida siempre resuelve. Coste: la misma consulta
 * indexada, y solo dentro de url_to_postid().
 *
 * @param string $url URL a resolver.
 * @return string
 */
function clipnuvex_root_url_to_postid( $url ) {
	if ( '' === (string) get_option( 'permalink_structure' ) ) {
		return $url;
	}
	$path      = (string) wp_parse_url( $url, PHP_URL_PATH );
	$home_path = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	if ( '' !== $home_path && 0 === strpos( $path, $home_path ) ) {
		$path = substr( $path, strlen( $home_path ) ); // WordPress en subdirectorio.
	}
	$path = trim( $path, '/' );
	if ( '' === $path || false !== strpos( $path, '/' ) ) {
		return $url; // Solo URLs de un segmento.
	}
	$slug = sanitize_title_for_query( $path );
	if ( '' === $slug || in_array( $slug, clipnuvex_root_slug_collisions(), true ) ) {
		return $url; // El contenido nativo gana.
	}
	$video_id = clipnuvex_slug_owner( $slug, array( 'video' ), true );
	if ( ! $video_id || clipnuvex_slug_owner( $slug, clipnuvex_root_native_types() ) ) {
		return $url;
	}
	return home_url( '/' . clipnuvex_video_archive_slug() . '/' . $slug . '/' );
}
add_filter( 'url_to_postid', 'clipnuvex_root_url_to_postid' );

/**
 * 3) 301 entre /video/{slug}/ y /{slug}/ hacia la canónica según el ajuste.
 */
function clipnuvex_root_video_redirect() {
	if ( is_admin() || ! is_singular( 'video' ) || is_preview() || '' === (string) get_option( 'permalink_structure' ) ) {
		return;
	}
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post || empty( $post->post_name ) ) {
		return;
	}
	$request_path = untrailingslashit( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$root_path    = untrailingslashit( (string) wp_parse_url( home_url( '/' . $post->post_name . '/' ), PHP_URL_PATH ) );
	$old_path     = untrailingslashit( (string) wp_parse_url( home_url( '/' . clipnuvex_video_archive_slug() . '/' . $post->post_name . '/' ), PHP_URL_PATH ) );
	// Solo se actúa sobre las dos formas conocidas (nunca sobre paginación de
	// comentarios, embeds u otras variantes que gestiona el propio WordPress).
	if ( ! in_array( $request_path, array( $root_path, $old_path ), true ) ) {
		return;
	}
	$target      = get_permalink( $post ); // Ya refleja ajuste + colisiones.
	$target_path = untrailingslashit( (string) wp_parse_url( $target, PHP_URL_PATH ) );
	if ( $target_path === $request_path ) {
		return; // Ya estamos en la canónica.
	}
	if ( isset( $_SERVER['QUERY_STRING'] ) && '' !== $_SERVER['QUERY_STRING'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$target .= ( false === strpos( $target, '?' ) ? '?' : '&' ) . (string) wp_unslash( $_SERVER['QUERY_STRING'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}
	wp_safe_redirect( $target, 301 );
	exit;
}
add_action( 'template_redirect', 'clipnuvex_root_video_redirect', 1 );

/**
 * 4) Slug único ENTRE vídeos, páginas y entradas de raíz (sufijo -2, -3…).
 *
 * @param string $slug          Slug ya único dentro de su tipo.
 * @param int    $post_id       ID.
 * @param string $post_status   Estado.
 * @param string $post_type     Tipo.
 * @param int    $post_parent   Padre.
 * @param string $original_slug Slug deseado.
 * @return string
 */
function clipnuvex_root_unique_slug( $slug, $post_id, $post_status, $post_type, $post_parent, $original_slug ) {
	if ( ! clipnuvex_root_urls_enabled() ) {
		return $slug;
	}
	if ( 'video' === $post_type ) {
		$others = clipnuvex_root_native_types();
	} elseif ( in_array( $post_type, clipnuvex_root_native_types(), true ) && 0 === (int) $post_parent ) {
		$others = array( 'video' ); // Las páginas hijas viven en /padre/hijo/: no chocan.
	} else {
		return $slug;
	}
	$types     = array_merge( $others, array( $post_type ) );
	$candidate = $slug;
	$n         = 2;
	while ( clipnuvex_slug_owner( $candidate, $types, false, $post_id ) ) {
		$candidate = $original_slug . '-' . $n;
		$n++;
		if ( $n > 200 ) {
			break;
		}
	}
	return $candidate;
}
add_filter( 'wp_unique_post_slug', 'clipnuvex_root_unique_slug', 10, 6 );

/**
 * Al cambiar el ajuste cambian TODAS las URLs de vídeo: se invalida el sitemap
 * cacheado (su clave lleva una versión que se bumpea al guardar vídeos, pero
 * no al tocar el panel).
 *
 * @param mixed $old_value Valor anterior de clipnuvex_options.
 * @param mixed $value     Valor nuevo.
 */
function clipnuvex_root_urls_option_changed( $old_value, $value ) {
	$old_on = ! is_array( $old_value ) || ! array_key_exists( 'video_root_urls', $old_value ) || ! empty( $old_value['video_root_urls'] );
	$new_on = ! is_array( $value ) || ! array_key_exists( 'video_root_urls', $value ) || ! empty( $value['video_root_urls'] );
	// clipnuvex_sitemap_bump_version() solo bumpea para un post de tipo vídeo;
	// aquí no hay post: se incrementa la versión directamente.
	if ( $old_on !== $new_on && function_exists( 'clipnuvex_sitemap_version' ) ) {
		update_option( 'clipnuvex_sitemap_ver', clipnuvex_sitemap_version() + 1, false );
	}
}
add_action( 'update_option_clipnuvex_options', 'clipnuvex_root_urls_option_changed', 10, 2 );

/**
 * 5) Aviso en el admin (pantallas del theme y listado de vídeos) con las
 * colisiones heredadas: esos vídeos siguen en /video/{slug}/.
 */
function clipnuvex_root_collision_notice() {
	if ( ! clipnuvex_root_urls_enabled() ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || ( false === strpos( (string) $screen->id, 'clipnuvex' ) && 'edit-video' !== $screen->id ) ) {
		return;
	}
	$slugs = clipnuvex_root_slug_collisions();
	if ( ! $slugs ) {
		return;
	}
	$items = array_map( 'esc_html', array_slice( $slugs, 0, 15 ) );
	printf(
		'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s <code>%3$s</code>. %4$s</p></div>',
		esc_html__( 'URLs de vídeo en la raíz:', 'clipnuvex' ),
		esc_html__( 'estos vídeos comparten slug con una página o entrada, así que conservan su URL /video/… (la página o entrada gana en la raíz):', 'clipnuvex' ),
		implode( '</code>, <code>', $items ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado arriba.
		esc_html__( 'Cambia el slug del vídeo (o el de la página/entrada) para que el vídeo pase a la raíz.', 'clipnuvex' )
	);
}
add_action( 'admin_notices', 'clipnuvex_root_collision_notice' );
