<?php
/**
 * Redirecciones 301 para entradas migradas a vídeo (inc/migrate).
 *
 * Solo actúan si el sitio ha usado la migración (option autoloaded
 * `clipnuvex_migrate_state`): coste cero para cualquier otro sitio.
 *
 * Casos cubiertos (verificados contra el core):
 * 1. URL antigua con otra estructura de enlaces (/2021/05/slug/,
 *    /categoria/slug/, /%post_id%/…): el filtro `request` del theme ya
 *    resuelve el vídeo por su slug, así que la URL vieja respondía 200 (contenido
 *    duplicado). Sobre is_singular('video') se compara la ruta pedida con la
 *    ruta original guardada (`_clipnuvex_migrated_path`) y se redirige 301.
 * 2. 404 con segmento numérico (estructuras sin slug) → vídeo migrado por ID.
 * 3. Archivo antiguo de categoría: WordPress responde 200 vacío mientras el
 *    término exista. Si la categoría origen enlaza a un término destino y ya no
 *    tiene entradas, 301 al archivo de categoría de vídeo.
 * 4. `_wp_old_slug`: wp_old_slug_redirect() solo busca post_type=post; se
 *    reintenta con `video` (arregla también vídeos renombrados).
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ¿Hay migración en este sitio? (memoizado por petición).
 *
 * @return bool
 */
function clipnuvex_migrate_active() {
	static $active = null;
	if ( null === $active ) {
		$active = class_exists( 'Clipnuvex_Post_Migrator' ) && Clipnuvex_Post_Migrator::is_active();
	}
	return $active;
}

/**
 * Ruta de la petición actual relativa a la raíz de WordPress, con barra final.
 *
 * @return string
 */
function clipnuvex_migrate_request_path() {
	$request = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$path    = (string) wp_parse_url( $request, PHP_URL_PATH );
	$home    = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	if ( '' !== $home && 0 === strpos( $path, $home ) ) {
		$path = substr( $path, strlen( $home ) );
	}
	return trailingslashit( '/' . ltrim( $path, '/' ) );
}

/**
 * Redirige conservando la query string.
 *
 * @param string $target URL destino.
 */
function clipnuvex_migrate_redirect_to( $target ) {
	if ( isset( $_SERVER['QUERY_STRING'] ) && '' !== $_SERVER['QUERY_STRING'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$target .= ( false === strpos( $target, '?' ) ? '?' : '&' ) . (string) wp_unslash( $_SERVER['QUERY_STRING'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}
	wp_safe_redirect( $target, 301 );
	exit;
}

/**
 * template_redirect (prioridad 0): casos 1, 2 y 3.
 */
function clipnuvex_migrate_redirects() {
	if ( is_admin() || is_preview() || ! clipnuvex_migrate_active() ) {
		return;
	}
	$path = clipnuvex_migrate_request_path();

	// 1) Vídeo migrado pedido por su URL antigua.
	if ( is_singular( 'video' ) ) {
		$id  = get_queried_object_id();
		$old = (string) get_post_meta( $id, Clipnuvex_Post_Migrator::META_PATH, true );
		if ( '' !== $old && trailingslashit( $old ) === $path ) {
			$target = get_permalink( $id );
			if ( $target && trailingslashit( (string) wp_parse_url( $target, PHP_URL_PATH ) ) !== $path ) {
				clipnuvex_migrate_redirect_to( $target );
			}
		}
		return;
	}

	// 3) Archivo antiguo de categoría vaciado por la migración.
	if ( is_category() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$to = (int) get_term_meta( $term->term_id, Clipnuvex_Post_Migrator::TERM_META, true );
			if ( $to && ! have_posts() ) {
				$link = get_term_link( $to, 'categoria_video' );
				if ( $link && ! is_wp_error( $link ) ) {
					clipnuvex_migrate_redirect_to( $link );
				}
			}
		}
		return;
	}

	// 2) 404 con segmento numérico (estructuras /%post_id%/).
	if ( is_404() ) {
		foreach ( explode( '/', trim( $path, '/' ) ) as $segment ) {
			if ( ! ctype_digit( $segment ) ) {
				continue;
			}
			$post = get_post( (int) $segment );
			if ( $post instanceof WP_Post && 'video' === $post->post_type && 'publish' === $post->post_status
				&& '1' === get_post_meta( $post->ID, Clipnuvex_Post_Migrator::META_FLAG, true ) ) {
				clipnuvex_migrate_redirect_to( get_permalink( $post ) );
			}
		}
	}
}
add_action( 'template_redirect', 'clipnuvex_migrate_redirects', 0 );

/**
 * Caso 4: wp_old_slug_redirect() no encuentra vídeos (solo post_type=post).
 *
 * @param int $id ID encontrado por el core (0 si ninguno).
 * @return int
 */
function clipnuvex_migrate_old_slug( $id ) {
	if ( $id || ! clipnuvex_migrate_active() ) {
		return $id;
	}
	global $wpdb;
	$slug = get_query_var( 'name' );
	if ( ! $slug ) {
		$pagename = (string) get_query_var( 'pagename' );
		$parts    = array_filter( explode( '/', $pagename ) );
		$slug     = $parts ? end( $parts ) : '';
	}
	$slug = sanitize_title_for_query( (string) $slug );
	if ( '' === $slug ) {
		return $id;
	}
	$found = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_wp_old_slug' WHERE m.meta_value = %s AND p.post_type = 'video' AND p.post_status = 'publish' ORDER BY p.ID ASC LIMIT 1",
			$slug
		)
	);
	return $found ? $found : $id;
}
add_filter( 'old_slug_redirect_post_id', 'clipnuvex_migrate_old_slug' );
