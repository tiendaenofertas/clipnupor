<?php
/**
 * Custom Post Type: video.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registra el CPT `video`.
 */
function clipnuvex_register_cpt() {
	$labels = array(
		'name'                  => _x( 'Vídeos', 'Post type general name', 'clipnuvex' ),
		'singular_name'         => _x( 'Vídeo', 'Post type singular name', 'clipnuvex' ),
		'menu_name'             => _x( 'Vídeos', 'Admin Menu text', 'clipnuvex' ),
		'name_admin_bar'        => _x( 'Vídeo', 'Add New on Toolbar', 'clipnuvex' ),
		'add_new'               => __( 'Añadir nuevo', 'clipnuvex' ),
		'add_new_item'          => __( 'Añadir nuevo vídeo', 'clipnuvex' ),
		'new_item'              => __( 'Nuevo vídeo', 'clipnuvex' ),
		'edit_item'             => __( 'Editar vídeo', 'clipnuvex' ),
		'view_item'             => __( 'Ver vídeo', 'clipnuvex' ),
		'all_items'             => __( 'Todos los vídeos', 'clipnuvex' ),
		'search_items'          => __( 'Buscar vídeos', 'clipnuvex' ),
		'not_found'             => __( 'No se encontraron vídeos.', 'clipnuvex' ),
		'not_found_in_trash'    => __( 'No hay vídeos en la papelera.', 'clipnuvex' ),
		'featured_image'        => __( 'Póster (2:3)', 'clipnuvex' ),
		'set_featured_image'    => __( 'Establecer póster', 'clipnuvex' ),
		'remove_featured_image' => __( 'Quitar póster', 'clipnuvex' ),
		'use_featured_image'    => __( 'Usar como póster', 'clipnuvex' ),
		'archives'              => __( 'Archivo de vídeos', 'clipnuvex' ),
		'item_published'        => __( 'Vídeo publicado.', 'clipnuvex' ),
		'item_updated'          => __( 'Vídeo actualizado.', 'clipnuvex' ),
	);

	$args = array(
		'labels'             => $labels,
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_rest'       => true,
		'query_var'          => true,
		'rewrite'            => array(
			'slug'       => 'video',
			'with_front' => false,
		),
		'capability_type'    => 'post',
		'has_archive'        => true,
		'hierarchical'       => false,
		'menu_position'      => 5,
		'menu_icon'          => 'dashicons-video-alt3',
		'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'revisions', 'custom-fields' ),
		'taxonomies'         => array( 'categoria_video', 'tag_video' ),
	);

	// Comentarios en vídeos SOLO en sitios que han migrado entradas (traen
	// comentarios y su estado): metabox/columna en admin y estado por defecto
	// de los vídeos nuevos. Sin migración, nada cambia.
	if ( get_option( 'clipnuvex_migrate_state' ) ) {
		$args['supports'][] = 'comments';
	}

	register_post_type( 'video', apply_filters( 'clipnuvex_cpt_args', $args ) );
}
add_action( 'init', 'clipnuvex_register_cpt' );
