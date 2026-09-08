<?php
/**
 * Sitemap XML de vídeos de Clipnuvex.
 *
 * Expone un **índice** de sitemaps en `/clipnuvex-sitemap.xml` que apunta a
 * sub-sitemaps paginados `/clipnuvex-sitemap-{n}.xml`, cada uno con hasta
 * CLIPNUVEX_SITEMAP_PER_PAGE vídeos y la extensión video de Google
 * (video:video). Así no hay truncado silencioso a escala (50k+ vídeos) y cada
 * página se cachea en un transient (invalidado al guardar un vídeo).
 *
 * La regla se añade en `init` (sin flush por carga). El flush ocurre en la
 * activación del theme (after_switch_theme, en inc/setup.php).
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'CLIPNUVEX_SITEMAP_QV' ) ) {
	define( 'CLIPNUVEX_SITEMAP_QV', 'clipnuvex_sitemap' );
}
if ( ! defined( 'CLIPNUVEX_SITEMAP_PAGE_QV' ) ) {
	define( 'CLIPNUVEX_SITEMAP_PAGE_QV', 'clipnuvex_sitemap_page' );
}
/**
 * URLs por sub-sitemap (Google admite hasta 50.000; usamos menos para que cada
 * página sea barata de generar y cachear).
 */
if ( ! defined( 'CLIPNUVEX_SITEMAP_PER_PAGE' ) ) {
	define( 'CLIPNUVEX_SITEMAP_PER_PAGE', 1000 );
}

/**
 * Reglas de reescritura: índice + páginas.
 */
function clipnuvex_sitemap_rewrite() {
	add_rewrite_rule( '^clipnuvex-sitemap\.xml$', 'index.php?' . CLIPNUVEX_SITEMAP_QV . '=1', 'top' );
	add_rewrite_rule( '^clipnuvex-sitemap-([0-9]+)\.xml$', 'index.php?' . CLIPNUVEX_SITEMAP_QV . '=1&' . CLIPNUVEX_SITEMAP_PAGE_QV . '=$matches[1]', 'top' );
	add_rewrite_tag( '%' . CLIPNUVEX_SITEMAP_QV . '%', '([0-1]+)' );
	add_rewrite_tag( '%' . CLIPNUVEX_SITEMAP_PAGE_QV . '%', '([0-9]+)' );
}
add_action( 'init', 'clipnuvex_sitemap_rewrite' );

/**
 * Declara las query vars públicas del sitemap.
 *
 * @param array $vars Query vars.
 * @return array
 */
function clipnuvex_sitemap_query_vars( $vars ) {
	$vars[] = CLIPNUVEX_SITEMAP_QV;
	$vars[] = CLIPNUVEX_SITEMAP_PAGE_QV;
	return $vars;
}
add_filter( 'query_vars', 'clipnuvex_sitemap_query_vars' );

/**
 * Versión de caché del sitemap (cambia al guardar/borrar un vídeo).
 *
 * @return int
 */
function clipnuvex_sitemap_version() {
	return (int) get_option( 'clipnuvex_sitemap_ver', 1 );
}

/**
 * Bump de la versión → invalida todas las páginas cacheadas.
 *
 * @param int $post_id ID del post.
 */
function clipnuvex_sitemap_bump_version( $post_id ) {
	if ( 'video' === get_post_type( $post_id ) ) {
		update_option( 'clipnuvex_sitemap_ver', clipnuvex_sitemap_version() + 1, false );
	}
}
add_action( 'save_post_video', 'clipnuvex_sitemap_bump_version' );
add_action( 'deleted_post', 'clipnuvex_sitemap_bump_version' );

/**
 * Número total de vídeos publicados.
 *
 * @return int
 */
function clipnuvex_sitemap_total() {
	$counts = wp_count_posts( 'video' );
	return isset( $counts->publish ) ? (int) $counts->publish : 0;
}

/**
 * Evita la redirección canónica (trailing slash) en las URLs del sitemap.
 *
 * @param string $redirect_url  URL a la que se redirigiría.
 * @param string $requested_url URL solicitada.
 * @return string|false
 */
function clipnuvex_sitemap_no_canonical( $redirect_url, $requested_url ) {
	if ( get_query_var( CLIPNUVEX_SITEMAP_QV ) ) {
		return false;
	}
	return $redirect_url;
}
add_filter( 'redirect_canonical', 'clipnuvex_sitemap_no_canonical', 10, 2 );

/**
 * Detecta la petición del sitemap y emite el XML (cacheado).
 */
function clipnuvex_sitemap_render() {
	if ( ! get_query_var( CLIPNUVEX_SITEMAP_QV ) ) {
		return;
	}

	$page = (int) get_query_var( CLIPNUVEX_SITEMAP_PAGE_QV );
	$key  = 'cnx_sitemap_' . clipnuvex_sitemap_version() . '_' . $page;
	$xml  = get_transient( $key );

	if ( false === $xml ) {
		$xml = ( $page > 0 ) ? clipnuvex_sitemap_build_page( $page ) : clipnuvex_sitemap_build_index();
		set_transient( $key, $xml, 12 * HOUR_IN_SECONDS );
	}

	if ( ! headers_sent() ) {
		status_header( 200 );
		header( 'Content-Type: application/xml; charset=' . get_bloginfo( 'charset' ) );
		header( 'X-Robots-Tag: noindex, follow', true );
	}

	echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML ya escapado al construirse.
	exit;
}
add_action( 'template_redirect', 'clipnuvex_sitemap_render' );

/**
 * Construye el índice de sub-sitemaps.
 *
 * @return string
 */
function clipnuvex_sitemap_build_index() {
	$total = clipnuvex_sitemap_total();
	$pages = max( 1, (int) ceil( $total / CLIPNUVEX_SITEMAP_PER_PAGE ) );

	$out  = '<?xml version="1.0" encoding="' . esc_attr( get_bloginfo( 'charset' ) ) . '"?>' . "\n";
	$out .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
	for ( $i = 1; $i <= $pages; $i++ ) {
		$out .= "\t<sitemap>\n";
		$out .= "\t\t<loc>" . esc_url( home_url( '/clipnuvex-sitemap-' . $i . '.xml' ) ) . "</loc>\n";
		$out .= "\t</sitemap>\n";
	}
	$out .= '</sitemapindex>';
	return $out;
}

/**
 * Construye una página del sitemap de vídeos.
 *
 * @param int $page Número de página (1-indexed).
 * @return string
 */
function clipnuvex_sitemap_build_page( $page ) {
	$query = new WP_Query(
		array(
			'post_type'              => 'video',
			'post_status'            => 'publish',
			'posts_per_page'         => CLIPNUVEX_SITEMAP_PER_PAGE,
			'paged'                  => $page,
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'orderby'                => 'modified',
			'order'                  => 'DESC',
		)
	);

	$out  = '<?xml version="1.0" encoding="' . esc_attr( get_bloginfo( 'charset' ) ) . '"?>' . "\n";
	$out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";

	foreach ( $query->posts as $post ) {
		$out .= clipnuvex_sitemap_url_node( $post );
	}

	$out .= '</urlset>';
	wp_reset_postdata();
	return $out;
}

/**
 * Devuelve un nodo <url> con la extensión <video:video> para un vídeo.
 *
 * @param WP_Post $post Post del vídeo.
 * @return string
 */
function clipnuvex_sitemap_url_node( $post ) {
	$id        = $post->ID;
	$permalink = get_permalink( $id );

	$thumb = function_exists( 'clipnuvex_video_backdrop' ) ? clipnuvex_video_backdrop( $id ) : '';
	if ( ! $thumb && has_post_thumbnail( $id ) ) {
		$thumb = get_the_post_thumbnail_url( $id, 'full' );
	}

	$title       = get_the_title( $id );
	$description = has_excerpt( $id ) ? get_the_excerpt( $id ) : wp_strip_all_tags( get_post_field( 'post_content', $id ) );
	$description = trim( preg_replace( '/\s+/u', ' ', (string) $description ) );
	if ( '' === $description ) {
		$description = $title;
	}
	$description = function_exists( 'mb_substr' ) ? mb_substr( $description, 0, 2000 ) : substr( $description, 0, 2000 );

	$pub_date = get_post_time( 'c', true, $id );

	$out  = "\t<url>\n";
	$out .= "\t\t<loc>" . esc_url( $permalink ) . "</loc>\n";
	if ( $thumb ) {
		$out .= "\t\t<video:video>\n";
		$out .= "\t\t\t<video:thumbnail_loc>" . esc_url( $thumb ) . "</video:thumbnail_loc>\n";
		$out .= "\t\t\t<video:title>" . clipnuvex_sitemap_cdata( $title ) . "</video:title>\n";
		$out .= "\t\t\t<video:description>" . clipnuvex_sitemap_cdata( $description ) . "</video:description>\n";
		$out .= "\t\t\t<video:player_loc>" . esc_url( $permalink ) . "</video:player_loc>\n";
		if ( $pub_date ) {
			$out .= "\t\t\t<video:publication_date>" . esc_html( $pub_date ) . "</video:publication_date>\n";
		}
		$out .= "\t\t</video:video>\n";
	}
	$out .= "\t</url>\n";
	return $out;
}

/**
 * Envuelve un texto en una sección CDATA segura para XML.
 *
 * @param string $text Texto.
 * @return string
 */
function clipnuvex_sitemap_cdata( $text ) {
	$text = (string) $text;
	$text = str_replace( ']]>', ']]]]><![CDATA[>', $text );
	$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text );
	return '<![CDATA[' . $text . ']]>';
}

/**
 * Añade la línea Sitemap al robots.txt virtual de WordPress.
 *
 * @param string $output Contenido actual.
 * @param bool   $public Si el sitio es público.
 * @return string
 */
function clipnuvex_sitemap_robots( $output, $public ) {
	if ( $public ) {
		$output .= 'Sitemap: ' . esc_url( home_url( '/clipnuvex-sitemap.xml' ) ) . "\n";
	}
	return $output;
}
add_filter( 'robots_txt', 'clipnuvex_sitemap_robots', 10, 2 );

/**
 * Añade nuestro índice de sitemap de vídeo al índice nativo de WordPress.
 *
 * @param array $sitemaps Entradas del índice nativo.
 * @return array
 */
function clipnuvex_sitemap_register_index( $sitemaps ) {
	$sitemaps[] = array( 'loc' => home_url( '/clipnuvex-sitemap.xml' ) );
	return $sitemaps;
}
add_filter( 'wp_sitemaps_index_entries', 'clipnuvex_sitemap_register_index' );
