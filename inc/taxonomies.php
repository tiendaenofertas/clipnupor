<?php
/**
 * Taxonomías: categoria_video y tag_video.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base de URL de las categorías según el idioma del SITIO (ajuste del panel,
 * nunca la cookie del visitante: las reglas de reescritura son globales).
 *
 * EN usa `categories` y NO `category`: el core registra antes su taxonomía
 * `category` con la regla `category/(.+?)/?$`, que taparía la nuestra.
 * Filtro `clipnuvex_category_base( $base, $lang )`. Al cambiar de idioma las
 * reglas se regeneran en la siguiente petición (clipnuvex_lang_option_changed)
 * y la base antigua redirige con 301 (clipnuvex_category_base_redirect).
 *
 * @return string
 */
function clipnuvex_category_base() {
	$lang = function_exists( 'clipnuvex_site_lang' ) ? clipnuvex_site_lang() : 'en';
	$base = ( 'en' === $lang ) ? 'categories' : 'categoria';
	$base = (string) apply_filters( 'clipnuvex_category_base', $base, $lang );
	return preg_match( '/^[a-z0-9][a-z0-9-]*$/', $base ) ? $base : 'categoria';
}

/**
 * Registra las taxonomías del CPT video.
 */
function clipnuvex_register_taxonomies() {
	// Categoría de vídeo (jerárquica, como las categorías de WP).
	$cat_labels = array(
		'name'              => _x( 'Categorías', 'taxonomy general name', 'clipnuvex' ),
		'singular_name'     => _x( 'Categoría', 'taxonomy singular name', 'clipnuvex' ),
		'search_items'      => __( 'Buscar categorías', 'clipnuvex' ),
		'all_items'         => __( 'Todas las categorías', 'clipnuvex' ),
		'parent_item'       => __( 'Categoría superior', 'clipnuvex' ),
		'parent_item_colon' => __( 'Categoría superior:', 'clipnuvex' ),
		'edit_item'         => __( 'Editar categoría', 'clipnuvex' ),
		'update_item'       => __( 'Actualizar categoría', 'clipnuvex' ),
		'add_new_item'      => __( 'Añadir nueva categoría', 'clipnuvex' ),
		'new_item_name'     => __( 'Nombre de la nueva categoría', 'clipnuvex' ),
		'menu_name'         => __( 'Categorías', 'clipnuvex' ),
	);

	register_taxonomy(
		'categoria_video',
		array( 'video' ),
		array(
			'hierarchical'      => true,
			'labels'            => $cat_labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => array(
				'slug'       => clipnuvex_category_base(), // /categoria/ (ES) o /categories/ (EN).
				'with_front' => false,
			),
		)
	);

	// Tag de vídeo (no jerárquica).
	$tag_labels = array(
		'name'                       => _x( 'Tags', 'taxonomy general name', 'clipnuvex' ),
		'singular_name'              => _x( 'Tag', 'taxonomy singular name', 'clipnuvex' ),
		'search_items'               => __( 'Buscar tags', 'clipnuvex' ),
		'popular_items'              => __( 'Tags populares', 'clipnuvex' ),
		'all_items'                  => __( 'Todos los tags', 'clipnuvex' ),
		'edit_item'                  => __( 'Editar tag', 'clipnuvex' ),
		'update_item'                => __( 'Actualizar tag', 'clipnuvex' ),
		'add_new_item'               => __( 'Añadir nuevo tag', 'clipnuvex' ),
		'new_item_name'              => __( 'Nombre del nuevo tag', 'clipnuvex' ),
		'separate_items_with_commas' => __( 'Separa los tags con comas', 'clipnuvex' ),
		'add_or_remove_items'        => __( 'Añadir o quitar tags', 'clipnuvex' ),
		'choose_from_most_used'      => __( 'Elegir entre los más usados', 'clipnuvex' ),
		'menu_name'                  => __( 'Tags', 'clipnuvex' ),
	);

	register_taxonomy(
		'tag_video',
		array( 'video' ),
		array(
			'hierarchical'      => false,
			'labels'            => $tag_labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => array(
				'slug'       => 'tag',
				'with_front' => false,
			),
		)
	);
}
add_action( 'init', 'clipnuvex_register_taxonomies' );

/**
 * 301 entre las dos bases de categoría (/categoria/ ES ⇄ /categories/ EN) tras
 * un cambio de idioma, para no perder URLs ya indexadas.
 *
 * Solo actúa cuando la petición NO resolvió a contenido real: 404 o un adjunto
 * (con /%postname%/ el core interpreta /base-antigua/{slug}/ como "adjunto
 * {slug}" y lo sirve si existe un medio con ese slug, p. ej. la imagen de la
 * propia categoría). Una página o entrada real en esa ruta nunca se toca.
 * Prioridad 0: antes del 301 de vídeos en la raíz (1) y del de adjuntos.
 */
function clipnuvex_category_base_redirect() {
	if ( is_admin() || ( ! is_404() && ! is_attachment() ) ) {
		return;
	}
	$request = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$path    = (string) wp_parse_url( $request, PHP_URL_PATH );
	$home    = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	if ( '' !== $home && 0 === strpos( $path, $home ) ) {
		$path = substr( $path, strlen( $home ) ); // WordPress en subdirectorio.
	}
	if ( ! preg_match( '#^/(categoria|categories)/([^/]+)(/.*)?$#', $path, $m ) ) {
		return;
	}
	$current = clipnuvex_category_base();
	if ( $m[1] === $current ) {
		return;
	}
	// Una página o entrada real llamada como la base antigua tiene prioridad.
	if ( function_exists( 'clipnuvex_slug_owner' ) && clipnuvex_slug_owner( $m[1], array( 'page', 'post' ), true ) ) {
		return;
	}
	$term = get_term_by( 'slug', sanitize_title( $m[2] ), 'categoria_video' );
	if ( ! $term instanceof WP_Term ) {
		return;
	}
	$rest   = ( isset( $m[3] ) && '' !== $m[3] ) ? $m[3] : '/';
	$target = home_url( '/' . $current . '/' . $term->slug . $rest );
	if ( isset( $_SERVER['QUERY_STRING'] ) && '' !== $_SERVER['QUERY_STRING'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$target .= '?' . (string) wp_unslash( $_SERVER['QUERY_STRING'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}
	wp_safe_redirect( $target, 301 );
	exit;
}
add_action( 'template_redirect', 'clipnuvex_category_base_redirect', 0 );

/**
 * Al cambiar el idioma del sitio cambia la base de URL de las categorías: se
 * fuerza la regeneración de las reglas de reescritura EN LA SIGUIENTE petición
 * borrando `clipnuvex_rules_ver` (lo consume clipnuvex_maybe_flush_rules en
 * init 99, con las taxonomías ya registradas con la base nueva). Nunca
 * flush_rewrite_rules() aquí: en esta petición se registraron con la base
 * antigua y se escribirían reglas obsoletas. Compara el valor EFECTIVO (con
 * respaldo): muchos sitios no tienen la clave guardada.
 *
 * @param mixed $old_value Valor anterior de clipnuvex_options.
 * @param mixed $value     Valor nuevo.
 */
function clipnuvex_lang_option_changed( $old_value, $value ) {
	if ( ! function_exists( 'clipnuvex_lang_from_options' ) ) {
		return;
	}
	if ( clipnuvex_lang_from_options( $old_value ) !== clipnuvex_lang_from_options( $value ) ) {
		delete_option( 'clipnuvex_rules_ver' );
	}
}
add_action( 'update_option_clipnuvex_options', 'clipnuvex_lang_option_changed', 10, 2 );

/**
 * Variante para el primer guardado del panel (add_option no pasa valor previo).
 *
 * @param string $option Nombre de la option.
 * @param mixed  $value  Valor guardado.
 */
function clipnuvex_lang_option_added( $option, $value ) {
	clipnuvex_lang_option_changed( array(), $value );
}
add_action( 'add_option_clipnuvex_options', 'clipnuvex_lang_option_added', 10, 2 );

/**
 * Devuelve la URL de la imagen destacada de un término de categoría.
 *
 * @param int    $term_id ID del término.
 * @param string $size    Tamaño de imagen.
 * @return string URL o cadena vacía.
 */
function clipnuvex_term_image_url( $term_id, $size = 'clipnuvex-backdrop' ) {
	$attachment_id = absint( get_term_meta( $term_id, 'clipnuvex_term_image', true ) );
	if ( $attachment_id ) {
		$src = wp_get_attachment_image_url( $attachment_id, $size );
		if ( $src ) {
			return $src;
		}
	}
	return '';
}

/**
 * Gradiente de marca por nombre de categoría (réplica del prototipo).
 *
 * @param string $name Nombre de la categoría.
 * @return string Valor CSS de background-image.
 */
function clipnuvex_category_gradient( $name ) {
	$map = array(
		'Acción'          => 'linear-gradient(140deg,#3a1216,#5B3CE0)',
		'Drama'           => 'linear-gradient(140deg,#1a1c33,#3b3f8c)',
		'Comedia'         => 'linear-gradient(140deg,#2e2410,#b8901f)',
		'Terror'          => 'linear-gradient(140deg,#1a0f14,#5a1230)',
		'Anime'           => 'linear-gradient(140deg,#2a1230,#8b2fb0)',
		'Ciencia Ficción' => 'linear-gradient(140deg,#0c2030,#1f7a8c)',
		'Documentales'    => 'linear-gradient(140deg,#14241a,#2e7d5b)',
		'Romance'         => 'linear-gradient(140deg,#3a1226,#c23a6e)',
		'Thriller'        => 'linear-gradient(140deg,#1c1f2e,#4a5270)',
		'Aventura'        => 'linear-gradient(140deg,#26321a,#5e8c2e)',
		'Fantasía'        => 'linear-gradient(140deg,#241640,#5a3ca6)',
		'Crimen'          => 'linear-gradient(140deg,#1a1a1f,#54545f)',
	);

	if ( isset( $map[ $name ] ) ) {
		return $map[ $name ];
	}

	// Gradiente determinista de respaldo basado en el nombre.
	$hue = abs( crc32( $name ) ) % 360;
	return sprintf( 'linear-gradient(140deg,hsl(%1$d,40%%,14%%),hsl(%2$d,55%%,38%%))', $hue, ( $hue + 28 ) % 360 );
}
