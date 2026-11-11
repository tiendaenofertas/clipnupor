<?php
/**
 * Framework de opciones del theme.
 *
 * Registro data-driven (pestañas → secciones → campos) que alimenta el panel de
 * administración dedicado y los helpers de lectura. ÚNICA FUENTE DE VERDAD:
 * la option array `clipnuvex_options`. Precedencia de lectura:
 *   panel (option) → default del registro.
 *
 * El Customizer se retiró en 1.4.0; los theme_mods antiguos se migran una sola
 * vez a la option array (clipnuvex_migrate_theme_mods) y después se eliminan.
 * No usar set_theme_mod()/get_theme_mod() para ajustes del theme.
 *
 * Los textos por defecto se declaran con __() para seguir siendo traducibles
 * (gettext) mientras el admin no los sobrescriba.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mapeo option_id => theme_mod del Customizer retirado en 1.4.0.
 *
 * Lo usa ÚNICAMENTE la migración one-shot (clipnuvex_migrate_theme_mods); la
 * lectura normal ya no consulta theme_mods.
 *
 * @return array option_id => theme_mod_name.
 */
function clipnuvex_option_modmap() {
	$map = array(
		'accent'         => 'clipnuvex_accent',
		'accent_dark'    => 'clipnuvex_accent_dark',
		'default_lang'   => 'clipnuvex_default_lang',
		'font_display'   => 'clipnuvex_font_display',
		'font_body'      => 'clipnuvex_font_body',
		'default_poster' => 'clipnuvex_default_poster',
	);
	if ( function_exists( 'clipnuvex_ad_slots' ) ) {
		foreach ( array_keys( clipnuvex_ad_slots() ) as $slot ) {
			$map[ 'ad_' . $slot ] = 'clipnuvex_ad_' . $slot;
		}
	}
	return $map;
}

/**
 * Migración one-shot de los theme_mods del antiguo Customizer a la option
 * array `clipnuvex_options` (única fuente de verdad desde 1.4.0).
 *
 * - El valor del panel gana: solo se copian claves ausentes en la option.
 * - Cada valor migrado pasa por clipnuvex_sanitize_field() (misma regla que
 *   el guardado del panel).
 * - Al terminar se eliminan TODOS los theme_mods clipnuvex_* — también los de
 *   ajustes descartados (player_lazy, selfhost_fonts) — y se marca la flag
 *   `clipnuvex_mods_migrated` para no repetir. Coste en régimen: 1 get_option.
 */
function clipnuvex_migrate_theme_mods() {
	if ( get_option( 'clipnuvex_mods_migrated' ) ) {
		return;
	}

	$fields  = clipnuvex_options_fields();
	$stored  = get_option( 'clipnuvex_options', array() );
	$stored  = is_array( $stored ) ? $stored : array();
	$modmap  = clipnuvex_option_modmap();
	$changed = false;

	foreach ( $modmap as $id => $mod_name ) {
		if ( array_key_exists( $id, $stored ) ) {
			continue; // El panel gana.
		}
		$mod = get_theme_mod( $mod_name, null );
		if ( null === $mod || '' === $mod ) {
			continue;
		}
		$field         = isset( $fields[ $id ] ) ? $fields[ $id ] : array( 'id' => $id, 'type' => 'text' );
		$stored[ $id ] = clipnuvex_sanitize_field( $field, $mod );
		$changed       = true;
	}

	if ( $changed ) {
		update_option( 'clipnuvex_options', $stored );
	}

	$discarded = array( 'clipnuvex_player_lazy', 'clipnuvex_selfhost_fonts' );
	foreach ( array_merge( array_values( $modmap ), $discarded ) as $mod_name ) {
		remove_theme_mod( $mod_name );
	}

	update_option( 'clipnuvex_mods_migrated', CLIPNUVEX_VERSION );
}
add_action( 'after_setup_theme', 'clipnuvex_migrate_theme_mods' );

/**
 * Migración única (1.5.5): el idioma por defecto del theme pasó de 'es' a 'en'.
 *
 * El formulario del panel solo envía la pestaña activa, así que un sitio ya
 * instalado cuyo admin nunca guardó "General" no tiene la clave default_lang y
 * venía mostrando español por el respaldo antiguo. Para que NO cambie de
 * idioma, base de URL ni página al actualizar, se le escribe 'es' explícito.
 * Señales de "ya instalado": panel guardado, reglas regeneradas por versión
 * (1.5.3+), página de categorías sembrada (1.5.4+), demo importada o algún
 * vídeo existente. Instalación nueva → nada que migrar → inglés.
 * Corre en init 1, antes de registrar las taxonomías (leen el idioma), con
 * add_option() como cerrojo. `clipnuvex_mods_migrated` NO sirve de señal: se
 * crea en el primer arranque de cualquier instalación (after_setup_theme).
 */
function clipnuvex_migrate_default_lang() {
	if ( ! add_option( 'clipnuvex_lang_migrated', CLIPNUVEX_VERSION ) ) {
		return;
	}
	$stored = get_option( 'clipnuvex_options', false );
	if ( is_array( $stored ) && array_key_exists( 'default_lang', $stored ) ) {
		return; // Ya elegido explícitamente.
	}
	$installed = ( false !== $stored )
		|| ( false !== get_option( 'clipnuvex_rules_ver' ) )
		|| ( false !== get_option( 'clipnuvex_categories_page_seeded' ) )
		|| ( false !== get_option( 'clipnuvex_demo_imported' ) );
	if ( ! $installed ) {
		global $wpdb;
		// El CPT aún no está registrado en init 1: consulta directa mínima.
		$installed = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status <> 'auto-draft' LIMIT 1", 'video' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	if ( ! $installed ) {
		return;
	}
	$stored                 = is_array( $stored ) ? $stored : array();
	$stored['default_lang'] = 'es';
	update_option( 'clipnuvex_options', $stored );
}
add_action( 'init', 'clipnuvex_migrate_default_lang', 1 );

/**
 * Registro completo de opciones del theme.
 *
 * @return array Estructura de pestañas.
 */
function clipnuvex_options_registry() {
	$tabs = array(

		'general' => array(
			'label'  => __( 'General', 'clipnuvex' ),
			'icon'   => 'dashicons-admin-home',
			'fields' => array(
				array( 'id' => 'wordmark_1', 'type' => 'text', 'label' => __( 'Logotipo — parte 1', 'clipnuvex' ), 'default' => 'Clip', 'desc' => __( 'Primera parte del wordmark (color de texto).', 'clipnuvex' ) ),
				array( 'id' => 'wordmark_2', 'type' => 'text', 'label' => __( 'Logotipo — parte 2', 'clipnuvex' ), 'default' => 'nuvex', 'desc' => __( 'Segunda parte del wordmark (color de acento).', 'clipnuvex' ) ),
				array( 'id' => 'default_lang', 'type' => 'select', 'label' => __( 'Idioma por defecto', 'clipnuvex' ), 'default' => 'en', 'choices' => array( 'en' => 'English (EN)', 'es' => 'Español (ES)' ), 'desc' => __( 'Idioma del sitio: interfaz, textos SEO automáticos, base de las URLs de categoría (/categories/ o /categoria/), página "Categorías" y schema. Al cambiarlo, las URLs antiguas de categoría redirigen con 301 a las nuevas.', 'clipnuvex' ) ),
				array( 'id' => 'show_language_switcher', 'type' => 'checkbox', 'label' => __( 'Mostrar selector de idioma ES/EN', 'clipnuvex' ), 'default' => 1 ),
				array( 'id' => 'long_cache_media', 'type' => 'checkbox', 'label' => __( 'Caché larga del navegador para imágenes subidas (recomendado)', 'clipnuvex' ), 'default' => 0, 'desc' => __( 'Añade reglas REVERSIBLES al .htaccess (bloque "Clipnuvex") para servir las imágenes de la mediateca con caché de 6 meses — mejora la nota de Lighthouse. Requiere Apache o LiteSpeed; al desactivarlo, las reglas se eliminan.', 'clipnuvex' ) ),
			),
		),

		'estilos' => array(
			'label'  => __( 'Colores y estilos', 'clipnuvex' ),
			'icon'   => 'dashicons-art',
			'fields' => array(
				array(
					'id'      => 'color_preset',
					'type'    => 'select',
					'label'   => __( 'Esquema de color', 'clipnuvex' ),
					'default' => '',
					'choices' => array(
						''       => __( '— Mantener los colores actuales —', 'clipnuvex' ),
						'oscuro' => __( 'Oscuro (por defecto del theme)', 'clipnuvex' ),
						'claro'  => __( 'Claro', 'clipnuvex' ),
					),
					'desc'    => __( 'Al guardar, rellena de golpe los colores base con el esquema elegido (después puedes retocarlos uno a uno). Toda la interfaz —tintes, halos, degradados, bordes y grises— se deriva de esos colores base.', 'clipnuvex' ),
				),
				array( 'id' => 'accent', 'type' => 'color', 'label' => __( 'Color de acento', 'clipnuvex' ), 'default' => '#7C5CFF', 'desc' => __( 'Botones, chips activos, enlaces, halos y el hero del buscador siguen este color. Con un acento claro (p. ej. amarillo) el texto sobre él pasa a oscuro automáticamente.', 'clipnuvex' ) ),
				array( 'id' => 'accent_dark', 'type' => 'color', 'label' => __( 'Acento oscuro (degradado)', 'clipnuvex' ), 'default' => '#5B3CE0', 'desc' => __( 'Segundo color de los degradados. Si lo dejas en su valor por defecto, se calcula solo a partir del acento.', 'clipnuvex' ) ),
				array( 'id' => 'bg', 'type' => 'color', 'label' => __( 'Fondo base', 'clipnuvex' ), 'default' => '#0a0910', 'desc' => __( 'Fondo de la página; cabecera, pie, barra inferior y degradado del body se derivan de él.', 'clipnuvex' ) ),
				array( 'id' => 'surface', 'type' => 'color', 'label' => __( 'Superficie / tarjetas', 'clipnuvex' ), 'default' => '#15131e', 'desc' => __( 'Tarjetas, paneles, buscador, migas y pestañas del reproductor.', 'clipnuvex' ) ),
				array( 'id' => 'text', 'type' => 'color', 'label' => __( 'Texto principal', 'clipnuvex' ), 'default' => '#f1f3f8', 'desc' => __( 'Títulos, grises secundarios y bordes se derivan de él.', 'clipnuvex' ) ),
				array( 'id' => 'text_2', 'type' => 'color', 'label' => __( 'Texto secundario', 'clipnuvex' ), 'default' => '#aab2c6' ),
				array( 'id' => 'header_bg', 'type' => 'color', 'label' => __( 'Fondo de cabecera, pie y barra inferior', 'clipnuvex' ), 'default' => '', 'desc' => __( 'Opcional. Vacío = se deriva del fondo base.', 'clipnuvex' ) ),
				array( 'id' => 'preroll_color', 'type' => 'color', 'label' => __( 'Color del aviso pre-roll', 'clipnuvex' ), 'default' => '#fbbf24' ),
				array( 'id' => 'radius_card', 'type' => 'number', 'label' => __( 'Radio de tarjetas (px)', 'clipnuvex' ), 'default' => 15, 'min' => 0, 'max' => 40 ),
				array(
					'id'      => 'card_orientation',
					'type'    => 'select',
					'label'   => __( 'Orientación de las miniaturas', 'clipnuvex' ),
					'default' => 'poster',
					'choices' => array(
						'poster' => __( 'Póster vertical (2:3) — actual', 'clipnuvex' ),
						'wide'   => __( 'Horizontal (16:9)', 'clipnuvex' ),
					),
					'desc'    => __( 'Cambia solo el marco y el recorte de la imagen de las tarjetas; badge, título y play se mantienen. Las imágenes subidas antes de la versión 1.4.12 usan el recorte 1280×720 hasta regenerar miniaturas (opcional).', 'clipnuvex' ),
				),
				array(
					'id'      => 'cards_cols_mobile',
					'type'    => 'select',
					'label'   => __( 'Tarjetas por fila en móvil', 'clipnuvex' ),
					'default' => '2',
					'choices' => array(
						'1' => __( '1 por fila', 'clipnuvex' ),
						'2' => __( '2 por fila (recomendado)', 'clipnuvex' ),
						'3' => __( '3 por fila', 'clipnuvex' ),
					),
					'desc'    => __( 'Solo afecta a pantallas de menos de 520px; tablet y escritorio no cambian. Vale para ambas orientaciones; a 3 por fila las miniaturas horizontales quedan muy pequeñas.', 'clipnuvex' ),
				),
				array( 'id' => 'font_display', 'type' => 'text', 'label' => __( 'Fuente de títulos', 'clipnuvex' ), 'default' => 'Clash Display', 'desc' => __( 'Familia tipográfica para h1–h2 y la marca.', 'clipnuvex' ) ),
				array( 'id' => 'font_body', 'type' => 'text', 'label' => __( 'Fuente de texto/UI', 'clipnuvex' ), 'default' => 'Satoshi', 'desc' => __( 'Familia tipográfica para el cuerpo y la interfaz.', 'clipnuvex' ) ),
			),
		),

		'textos' => array(
			'label'  => __( 'Textos', 'clipnuvex' ),
			'icon'   => 'dashicons-editor-textcolor',
			'groups' => array(
				__( 'Navegación', 'clipnuvex' )  => array( 'nav_home', 'nav_browse', 'nav_categories' ),
				__( 'Home', 'clipnuvex' )         => array( 'txt_novedades', 'txt_todo', 'txt_view_all' ),
				__( 'Categorías', 'clipnuvex' )   => array( 'txt_cats_title', 'txt_cats_subtitle' ),
				__( 'Vídeo', 'clipnuvex' )        => array( 'txt_play', 'txt_share', 'txt_related', 'txt_you_may', 'txt_back', 'txt_preroll' ),
				__( 'Búsqueda', 'clipnuvex' )     => array( 'txt_search_results', 'txt_search_ph', 'txt_view_all_results', 'txt_no_videos', 'txt_no_videos_sub', 'txt_search_recs', 'txt_search_explore' ),
				__( 'Bloque SEO de categoría', 'clipnuvex' ) => array( 'seo_block_heading', 'seo_block_intro', 'seo_point_1', 'seo_point_2', 'seo_point_3', 'seo_point_4' ),
				__( 'Pie de página', 'clipnuvex' ) => array( 'footer_desc', 'footer_col_nav', 'footer_col_genres', 'footer_col_legal', 'footer_rights', 'footer_tagline' ),
				__( 'Errores y vacíos', 'clipnuvex' ) => array( 'txt_404_title', 'txt_404_text', 'txt_404_btn', 'txt_empty_videos' ),
			),
			'fields' => array(
				array( 'id' => 'nav_home', 'type' => 'text', 'label' => __( 'Enlace: Inicio', 'clipnuvex' ), 'default' => __( 'Inicio', 'clipnuvex' ) ),
				array( 'id' => 'nav_browse', 'type' => 'text', 'label' => __( 'Enlace: Explorar', 'clipnuvex' ), 'default' => __( 'Explorar', 'clipnuvex' ) ),
				array( 'id' => 'nav_categories', 'type' => 'text', 'label' => __( 'Enlace: Categorías', 'clipnuvex' ), 'default' => __( 'Categorías', 'clipnuvex' ) ),
				array( 'id' => 'txt_novedades', 'type' => 'text', 'label' => __( 'Título sección "Novedades"', 'clipnuvex' ), 'default' => __( 'Novedades', 'clipnuvex' ) ),
				array( 'id' => 'txt_todo', 'type' => 'text', 'label' => __( 'Chip "Todo"', 'clipnuvex' ), 'default' => __( 'Todo', 'clipnuvex' ) ),
				array( 'id' => 'txt_view_all', 'type' => 'text', 'label' => __( 'Enlace "Ver todas →"', 'clipnuvex' ), 'default' => __( 'Ver todas →', 'clipnuvex' ) ),
				array( 'id' => 'txt_cats_title', 'type' => 'text', 'label' => __( 'Título "Explora por categorías"', 'clipnuvex' ), 'default' => __( 'Explora por categorías', 'clipnuvex' ) ),
				array( 'id' => 'txt_cats_subtitle', 'type' => 'textarea', 'label' => __( 'Subtítulo de categorías', 'clipnuvex' ), 'default' => __( 'Más de %1$s vídeos en HD y 4K organizados en %2$s géneros. Elige tu mundo y empieza a ver.', 'clipnuvex' ), 'desc' => __( 'Usa %1$s para el nº de vídeos y %2$s para el nº de géneros.', 'clipnuvex' ) ),
				array( 'id' => 'txt_play', 'type' => 'text', 'label' => __( 'Botón "Reproducir"', 'clipnuvex' ), 'default' => __( 'Reproducir', 'clipnuvex' ) ),
				array( 'id' => 'txt_share', 'type' => 'text', 'label' => __( 'Botón "Compartir"', 'clipnuvex' ), 'default' => __( 'Compartir', 'clipnuvex' ) ),
				array( 'id' => 'txt_related', 'type' => 'text', 'label' => __( 'Título "Relacionados" (sidebar)', 'clipnuvex' ), 'default' => __( 'Relacionados', 'clipnuvex' ) ),
				array( 'id' => 'txt_you_may', 'type' => 'text', 'label' => __( 'Título "También te puede gustar"', 'clipnuvex' ), 'default' => __( 'También te puede gustar', 'clipnuvex' ) ),
				array( 'id' => 'txt_back', 'type' => 'text', 'label' => __( 'Botón "Volver"', 'clipnuvex' ), 'default' => __( 'Volver', 'clipnuvex' ) ),
				array( 'id' => 'txt_preroll', 'type' => 'text', 'label' => __( 'Aviso pre-roll del player', 'clipnuvex' ), 'default' => __( 'Aviso · 0:05 · Continuar ▸', 'clipnuvex' ) ),
				array( 'id' => 'txt_search_results', 'type' => 'text', 'label' => __( 'Eyebrow "Resultados de búsqueda"', 'clipnuvex' ), 'default' => __( 'Resultados de búsqueda', 'clipnuvex' ) ),
				array( 'id' => 'txt_search_ph', 'type' => 'text', 'label' => __( 'Placeholder del buscador', 'clipnuvex' ), 'default' => __( 'Buscar vídeos, categorías…', 'clipnuvex' ) ),
				array( 'id' => 'txt_view_all_results', 'type' => 'text', 'label' => __( 'Enlace "Ver todos los resultados"', 'clipnuvex' ), 'default' => __( 'Ver todos los resultados', 'clipnuvex' ) ),
				array( 'id' => 'txt_no_videos', 'type' => 'text', 'label' => __( 'Título "No se encontraron vídeos"', 'clipnuvex' ), 'default' => __( 'No se encontraron vídeos', 'clipnuvex' ) ),
				array( 'id' => 'txt_no_videos_sub', 'type' => 'text', 'label' => __( 'Subtexto sin resultados', 'clipnuvex' ), 'default' => __( 'Prueba con otra búsqueda o género.', 'clipnuvex' ) ),
				array( 'id' => 'txt_search_recs', 'type' => 'text', 'label' => __( 'Título del bloque de recomendaciones', 'clipnuvex' ), 'default' => __( 'Quizás te interese', 'clipnuvex' ), 'desc' => __( 'Se muestra cuando la búsqueda no devuelve resultados.', 'clipnuvex' ) ),
				array( 'id' => 'txt_search_explore', 'type' => 'text', 'label' => __( 'Título del bloque "Explora por categorías"', 'clipnuvex' ), 'default' => __( 'O explora por categorías', 'clipnuvex' ), 'desc' => __( 'Se muestra bajo las recomendaciones cuando la búsqueda no devuelve resultados.', 'clipnuvex' ) ),
				/* translators: 1: %s literal (se sustituye por el nombre de la categoría), 2: nombre del sitio. */
				array( 'id' => 'seo_block_heading', 'type' => 'text', 'label' => __( 'Título del bloque SEO', 'clipnuvex' ), 'default' => sprintf( __( '¿Por qué ver %1$s en %2$s?', 'clipnuvex' ), '%s', get_bloginfo( 'name' ) ), 'desc' => __( '%s = nombre de la categoría. Cada categoría puede sobrescribir estos textos desde su pantalla de edición (Vídeos → Categorías).', 'clipnuvex' ) ),
				array( 'id' => 'seo_block_intro', 'type' => 'textarea', 'label' => __( 'Intro del bloque SEO', 'clipnuvex' ), 'default' => __( 'Disfruta de lo mejor de %s en streaming. Catálogo curado, calidad de cine, reproductor propio y servidores alternativos. Nuevos vídeos cada semana, sin cortes.', 'clipnuvex' ), 'desc' => __( '%s = nombre de la categoría.', 'clipnuvex' ) ),
				array( 'id' => 'seo_point_1', 'type' => 'text', 'label' => __( 'Punto SEO 1 (admite <strong>)', 'clipnuvex' ), 'default' => __( '<strong>Streaming en HD y 4K:</strong> calidad de cine en todos los vídeos, con reproductor propio y servidores alternativos.', 'clipnuvex' ), 'allow_strong' => true ),
				array( 'id' => 'seo_point_2', 'type' => 'text', 'label' => __( 'Punto SEO 2', 'clipnuvex' ), 'default' => __( '<strong>Carga rápida y sin cortes:</strong> infraestructura optimizada para web, móvil, tablet y Smart TV.', 'clipnuvex' ), 'allow_strong' => true ),
				array( 'id' => 'seo_point_3', 'type' => 'text', 'label' => __( 'Punto SEO 3', 'clipnuvex' ), 'default' => __( '<strong>Audio latino y subtítulos:</strong> disfruta en español con opción de subtítulos.', 'clipnuvex' ), 'allow_strong' => true ),
				/* translators: %s: nombre del sitio. */
				array( 'id' => 'seo_point_4', 'type' => 'text', 'label' => __( 'Punto SEO 4', 'clipnuvex' ), 'default' => sprintf( __( '<strong>Catálogo actualizado:</strong> nuevos vídeos cada semana, curados por el equipo de %s.', 'clipnuvex' ), get_bloginfo( 'name' ) ), 'allow_strong' => true ),
				array( 'id' => 'footer_desc', 'type' => 'textarea', 'label' => __( 'Descripción de marca (pie)', 'clipnuvex' ), 'default' => __( 'Vídeos de cualquier categoría en streaming HD y 4K. Rápida, intuitiva y sin cortes.', 'clipnuvex' ) ),
				array( 'id' => 'footer_col_nav', 'type' => 'text', 'label' => __( 'Título columna "Navegación"', 'clipnuvex' ), 'default' => __( 'Navegación', 'clipnuvex' ) ),
				array( 'id' => 'footer_col_genres', 'type' => 'text', 'label' => __( 'Título columna "Géneros"', 'clipnuvex' ), 'default' => __( 'Géneros', 'clipnuvex' ) ),
				array( 'id' => 'footer_col_legal', 'type' => 'text', 'label' => __( 'Título columna "Legal"', 'clipnuvex' ), 'default' => __( 'Legal', 'clipnuvex' ) ),
				array( 'id' => 'footer_rights', 'type' => 'text', 'label' => __( 'Texto de derechos', 'clipnuvex' ), 'default' => __( 'Todos los derechos reservados.', 'clipnuvex' ) ),
				array( 'id' => 'footer_tagline', 'type' => 'text', 'label' => __( 'Tagline del pie', 'clipnuvex' ), 'default' => __( 'Streaming en HD y 4K · Sin cortes', 'clipnuvex' ) ),
				array( 'id' => 'txt_404_title', 'type' => 'text', 'label' => __( 'Título 404', 'clipnuvex' ), 'default' => __( 'Página no encontrada', 'clipnuvex' ) ),
				array( 'id' => 'txt_404_text', 'type' => 'textarea', 'label' => __( 'Texto 404', 'clipnuvex' ), 'default' => __( 'El vídeo o la página que buscas no existe o se ha movido.', 'clipnuvex' ) ),
				array( 'id' => 'txt_404_btn', 'type' => 'text', 'label' => __( 'Botón 404', 'clipnuvex' ), 'default' => __( 'Volver al inicio', 'clipnuvex' ) ),
				array( 'id' => 'txt_empty_videos', 'type' => 'textarea', 'label' => __( 'Estado vacío (home sin vídeos)', 'clipnuvex' ), 'default' => __( 'Importa el contenido demo desde Apariencia → Modo Demo o añade tu primer vídeo.', 'clipnuvex' ) ),
			),
		),

		'secciones' => array(
			'label'  => __( 'Secciones', 'clipnuvex' ),
			'icon'   => 'dashicons-layout',
			'fields' => array(
				array( 'id' => 'show_search_bar', 'type' => 'checkbox', 'label' => __( 'Buscador en la cabecera', 'clipnuvex' ), 'default' => 1 ),
				array( 'id' => 'show_bottom_nav', 'type' => 'checkbox', 'label' => __( 'Navegación inferior en móvil', 'clipnuvex' ), 'default' => 1 ),
				array( 'id' => 'show_back_to_top', 'type' => 'checkbox', 'label' => __( 'Botón "subir" flotante', 'clipnuvex' ), 'default' => 1 ),
				array( 'id' => 'show_breadcrumbs', 'type' => 'checkbox', 'label' => __( 'Migas de pan', 'clipnuvex' ), 'default' => 1 ),
				array( 'id' => 'show_hero_badge', 'type' => 'checkbox', 'label' => __( 'Insignia "Categoría/Tag" en el hero', 'clipnuvex' ), 'default' => 0, 'desc' => __( 'La píldora sobre el título del archivo. Apagada por defecto: las migas y el propio título ya dan ese contexto.', 'clipnuvex' ) ),
				array( 'id' => 'show_sort_controls', 'type' => 'checkbox', 'label' => __( 'Controles de orden en archivos (Recientes/Populares/A-Z)', 'clipnuvex' ), 'default' => 1, 'desc' => __( 'Ocultarlos no desactiva el orden por URL (?orden=populares sigue funcionando).', 'clipnuvex' ) ),
				array( 'id' => 'show_home_chips', 'type' => 'checkbox', 'label' => __( 'Chips de categorías en home', 'clipnuvex' ), 'default' => 1 ),
				array( 'id' => 'show_seo_block', 'type' => 'checkbox', 'label' => __( 'Bloque SEO al pie de categoría', 'clipnuvex' ), 'default' => 1 ),
				array( 'id' => 'show_related', 'type' => 'checkbox', 'label' => __( 'Vídeos relacionados', 'clipnuvex' ), 'default' => 1 ),
				array( 'id' => 'show_video_sidebar', 'type' => 'checkbox', 'label' => __( 'Sidebar del vídeo (desktop)', 'clipnuvex' ), 'default' => 1 ),
				array( 'id' => 'show_footer', 'type' => 'checkbox', 'label' => __( 'Pie de página', 'clipnuvex' ), 'default' => 1 ),
				array( 'id' => 'related_count', 'type' => 'number', 'label' => __( 'Nº de relacionados', 'clipnuvex' ), 'default' => 6, 'min' => 2, 'max' => 12 ),
				array( 'id' => 'home_per_page', 'type' => 'number', 'label' => __( 'Vídeos por página (home/archivos)', 'clipnuvex' ), 'default' => 21, 'min' => 6, 'max' => 60 ),
			),
		),

		'reproductor' => array(
			'label'  => __( 'Reproductor', 'clipnuvex' ),
			'icon'   => 'dashicons-controls-play',
			'fields' => array(
				array( 'id' => 'player_show_preroll', 'type' => 'checkbox', 'label' => __( 'Mostrar aviso de anuncio pre-roll', 'clipnuvex' ), 'default' => 1 ),
				array( 'id' => 'default_poster', 'type' => 'image', 'label' => __( 'Póster por defecto del player', 'clipnuvex' ), 'default' => '' ),
			),
		),

		'seo' => array(
			'label'  => __( 'SEO', 'clipnuvex' ),
			'icon'   => 'dashicons-search',
			'fields' => array(
				array(
					'id'      => 'video_root_urls',
					'type'    => 'checkbox',
					'label'   => __( 'Vídeos en la raíz del dominio (sin /video/ en la URL)', 'clipnuvex' ),
					'default' => 1,
					'desc'    => __( 'Cada vídeo se publica en tudominio.com/titulo-del-video/ y las URLs antiguas /video/… redirigen con 301 (se conserva el posicionamiento). El archivo "Explorar" sigue en /video/. Un vídeo que comparta slug con una página o entrada mantiene /video/… hasta que cambies el slug. Requiere enlaces permanentes distintos de "Simple".', 'clipnuvex' ),
				),
				array(
					'id'      => 'schema_family_friendly',
					'type'    => 'checkbox',
					'label'   => __( 'Vídeos aptos para todos los públicos (isFamilyFriendly en el esquema)', 'clipnuvex' ),
					'default' => 1,
					'desc'    => __( 'Se declara en los datos estructurados VideoObject de cada vídeo. Desactívalo si tu catálogo no es apto para menores: declararlo apto sin serlo puede costarte los resultados enriquecidos de vídeo.', 'clipnuvex' ),
				),
				array(
					'id'      => 'seo_adult_rating',
					'type'    => 'checkbox',
					'label'   => __( 'Marcar el sitio como contenido adulto (SafeSearch)', 'clipnuvex' ),
					'default' => 0,
					'desc'    => __( 'Añade la etiqueta meta rating=adult en todas las páginas para que los buscadores lo filtren en SafeSearch. Solo para sitios de contenido adulto.', 'clipnuvex' ),
				),
				array( 'id' => 'seo_org_name', 'type' => 'text', 'label' => __( 'Nombre de la organización', 'clipnuvex' ), 'default' => '', 'desc' => __( 'Para el JSON-LD Organization. Por defecto, el nombre del sitio.', 'clipnuvex' ) ),
				array( 'id' => 'seo_og_image', 'type' => 'image', 'label' => __( 'Imagen Open Graph por defecto', 'clipnuvex' ), 'default' => '' ),
				array( 'id' => 'seo_home_desc', 'type' => 'textarea', 'label' => __( 'Meta description de la home', 'clipnuvex' ), 'default' => '', 'desc' => __( 'Vacío = descripción del sitio.', 'clipnuvex' ) ),
			),
		),

	);

	// Pestaña del buscador. La cobertura de tipos se genera dinámicamente a
	// partir de los post types públicos registrados: si mañana se añade otro
	// CPT (p. ej. `product` de WooCommerce), su checkbox aparece solo. Antes
	// de `init` solo se garantiza `video` (guard en el helper).
	if ( function_exists( 'clipnuvex_search_available_post_types' ) ) {
		$search_fields = array(
			array(
				'id'      => 'show_search_recs',
				'type'    => 'checkbox',
				'label'   => __( 'Recomendar contenido cuando la búsqueda no tiene resultados', 'clipnuvex' ),
				'default' => 1,
				'desc'    => __( 'En vez de una página vacía se muestra un bloque "Quizás te interese" con contenido popular o reciente (título editable en Textos → Búsqueda).', 'clipnuvex' ),
			),
			array( 'id' => 'search_recs_count', 'type' => 'number', 'label' => __( 'Nº de recomendaciones (página de resultados)', 'clipnuvex' ), 'default' => 20, 'min' => 2, 'max' => 40 ),
		array( 'id' => 'search_recs_dropdown_count', 'type' => 'number', 'label' => __( 'Nº de sugerencias (desplegable del buscador)', 'clipnuvex' ), 'default' => 6, 'min' => 2, 'max' => 8 ),
		array(
			'id'      => 'show_search_explore_cats',
			'type'    => 'checkbox',
			'label'   => __( 'Mostrar "Explora por categorías" cuando no hay resultados', 'clipnuvex' ),
			'default' => 1,
			'desc'    => __( 'Chips con las categorías del catálogo al pie de la página de búsqueda sin resultados (título editable en Textos → Búsqueda).', 'clipnuvex' ),
		),
			array(
				'id'      => 'search_recs_orderby',
				'type'    => 'select',
				'label'   => __( 'Criterio de recomendación', 'clipnuvex' ),
				'default' => 'populares',
				'choices' => array(
					'populares' => __( 'Populares (vistas reales del contador del theme)', 'clipnuvex' ),
					'recientes' => __( 'Recientes (fecha de publicación)', 'clipnuvex' ),
				),
				'desc'    => __( '"Populares" ordena por las vistas que registra el theme (considera hasta los 500 contenidos con vistas más recientes) y completa con lo más nuevo si falta.', 'clipnuvex' ),
			),
		);
		foreach ( clipnuvex_search_available_post_types() as $cnx_ptype => $cnx_ptype_label ) {
			$search_fields[] = array(
				'id'      => 'search_in_' . $cnx_ptype,
				'type'    => 'checkbox',
				/* translators: %s: nombre del tipo de contenido (Vídeos, Entradas…). */
				'label'   => sprintf( __( 'Incluir en la búsqueda: %s', 'clipnuvex' ), $cnx_ptype_label ),
				'default' => ( 'video' === $cnx_ptype ) ? 1 : 0,
			);
		}
		$tabs['buscador'] = array(
			'label'  => __( 'Buscador', 'clipnuvex' ),
			'icon'   => 'dashicons-filter',
			'fields' => $search_fields,
		);
	}

	// Pestaña de anuncios generada desde los slots IAB.
	if ( function_exists( 'clipnuvex_ad_slots' ) ) {
		$ad_fields = array(
			array(
				'id'      => 'ads_show_placeholders',
				'type'    => 'checkbox',
				'label'   => __( 'Mostrar marcadores en los slots vacíos (vista previa del layout)', 'clipnuvex' ),
				'default' => 0,
				'desc'    => __( 'Apágalo en producción: un slot sin código de anuncio se colapsa y libera el espacio. Tu anuncio real (imagen, iframe o código) siempre se muestra cuando lo pegas.', 'clipnuvex' ),
			),
		);
		foreach ( clipnuvex_ad_slots() as $slot_id => $slot ) {
			$spec  = function_exists( 'clipnuvex_ad_format_spec' ) ? clipnuvex_ad_format_spec( $slot['format'] ) : array( 'label' => '' );
			$ad_fields[] = array(
				'id'      => 'ad_' . $slot_id,
				'type'    => 'code',
				'label'   => $slot['label'] . ' (' . $spec['label'] . ')',
				'default' => '',
				'desc'    => __( 'Pega aquí el código de Google Ad Manager / AdSense.', 'clipnuvex' ),
			);
		}
		$tabs['anuncios'] = array(
			'label'  => __( 'Anuncios', 'clipnuvex' ),
			'icon'   => 'dashicons-megaphone',
			'fields' => $ad_fields,
		);
	}

	return apply_filters( 'clipnuvex_options_registry', $tabs );
}

/**
 * Devuelve un mapa plano id => definición de campo.
 *
 * @return array
 */
function clipnuvex_options_fields() {
	static $flat = null;
	if ( null !== $flat ) {
		return $flat;
	}
	$flat = array();
	foreach ( clipnuvex_options_registry() as $tab ) {
		if ( empty( $tab['fields'] ) ) {
			continue;
		}
		foreach ( $tab['fields'] as $field ) {
			$flat[ $field['id'] ] = $field;
		}
	}
	return $flat;
}

/**
 * Default de un campo.
 *
 * @param string $id Id del campo.
 * @return mixed
 */
function clipnuvex_option_default( $id ) {
	$fields = clipnuvex_options_fields();
	return isset( $fields[ $id ]['default'] ) ? $fields[ $id ]['default'] : '';
}

/**
 * Lee una opción con precedencia: panel (option `clipnuvex_options`) → default.
 *
 * @param string $id       Id del campo.
 * @param mixed  $fallback Valor si no hay nada (por defecto, el default del registro).
 * @return mixed
 */
function clipnuvex_option( $id, $fallback = '__default__' ) {
	$stored = get_option( 'clipnuvex_options', array() );
	if ( is_array( $stored ) && array_key_exists( $id, $stored ) ) {
		return $stored[ $id ];
	}
	if ( '__default__' === $fallback ) {
		return clipnuvex_option_default( $id );
	}
	return $fallback;
}

/**
 * Texto editable por el admin con fallback al default (ya traducido por gettext).
 *
 * @param string $id Id del campo de texto.
 * @return string
 */
function clipnuvex_text( $id ) {
	$val = clipnuvex_option( $id, '__default__' );
	if ( is_string( $val ) && '' !== trim( $val ) ) {
		return $val;
	}
	return (string) clipnuvex_option_default( $id );
}

/**
 * Helper booleano para toggles de sección.
 *
 * @param string $id Id del campo checkbox.
 * @return bool
 */
function clipnuvex_is_on( $id ) {
	return (bool) clipnuvex_option( $id, clipnuvex_option_default( $id ) );
}

/**
 * Sanitiza UN valor según la definición de su campo del registro.
 *
 * Única regla de saneado del sistema: la usan tanto el guardado del panel
 * (clipnuvex_sanitize_options) como la migración de theme_mods.
 *
 * @param array $field Definición del campo (id, type, min/max, choices, allow_strong…).
 * @param mixed $raw   Valor entrante, ya sin slashes.
 * @return mixed Valor saneado.
 */
function clipnuvex_sanitize_field( $field, $raw ) {
	$id   = isset( $field['id'] ) ? $field['id'] : '';
	$type = isset( $field['type'] ) ? $field['type'] : 'text';

	switch ( $type ) {
		// Checkboxes: '1' = activado; cualquier otra cosa (incl. el oculto '0') = off.
		case 'checkbox':
			return ( '1' === (string) $raw ) ? 1 : 0;
		case 'color':
			return sanitize_hex_color( $raw ) ? sanitize_hex_color( $raw ) : clipnuvex_option_default( $id );
		case 'number':
			$val = (int) $raw;
			if ( isset( $field['min'] ) ) { $val = max( (int) $field['min'], $val ); }
			if ( isset( $field['max'] ) ) { $val = min( (int) $field['max'], $val ); }
			return $val;
		case 'select':
			// Comparación como cadenas: PHP convierte claves numéricas ('1','2','3')
			// en enteros y un in_array estricto rechazaría el valor enviado.
			$choices = isset( $field['choices'] ) ? array_map( 'strval', array_keys( $field['choices'] ) ) : array();
			return in_array( (string) $raw, $choices, true ) ? (string) $raw : clipnuvex_option_default( $id );
		case 'image':
			return esc_url_raw( $raw );
		case 'code':
			if ( ! function_exists( 'clipnuvex_ad_kses' ) ) {
				return wp_kses_post( $raw );
			}
			$clean = clipnuvex_ad_kses( $raw );
			// Aviso en el panel si la POLÍTICA por capability recortó algo (hosts
			// no permitidos o JS inline no canónico). Se compara contra el kses
			// estructural puro — no contra el texto crudo — para no dar falsos
			// positivos por la normalización de atributos de wp_kses.
			if ( function_exists( 'clipnuvex_ad_allowed_tags' ) ) {
				$structural = wp_kses( $raw, clipnuvex_ad_allowed_tags() );
				if ( trim( (string) $clean ) !== trim( (string) $structural ) ) {
					clipnuvex_warn_ad_code_stripped( $id, isset( $field['label'] ) ? $field['label'] : $id );
				}
			}
			return $clean;
		case 'textarea':
			return ! empty( $field['allow_strong'] )
				? wp_kses( $raw, array( 'strong' => array(), 'em' => array(), 'br' => array() ) )
				: sanitize_textarea_field( $raw );
		case 'text':
		default:
			return ! empty( $field['allow_strong'] )
				? wp_kses( $raw, array( 'strong' => array(), 'em' => array() ) )
				: sanitize_text_field( $raw );
	}
}

/**
 * Registra un aviso de panel cuando el saneado de un código de anuncio elimina
 * contenido por política de capability (ver clipnuvex_ad_kses). Sin él, el
 * recorte sería silencioso: el panel "acepta" el código pero el slot saldría
 * incompleto. Deduplicado por campo (el sanitize corre DOS veces en el primer
 * add_option) y con guard para contextos sin Settings API (migración/frontend).
 *
 * @param string $field_id Id del campo (p. ej. ad_home_mid).
 * @param string $label    Etiqueta visible del campo.
 */
function clipnuvex_warn_ad_code_stripped( $field_id, $label ) {
	static $warned = array();
	if ( isset( $warned[ $field_id ] ) || ! function_exists( 'add_settings_error' ) ) {
		return;
	}
	$warned[ $field_id ] = true;
	add_settings_error(
		'clipnuvex_options',
		'clipnuvex_ad_stripped_' . sanitize_key( $field_id ),
		sprintf(
			/* translators: %s: etiqueta del slot de anuncio. */
			__( 'El código de «%s» contenía scripts o iframes no permitidos para tu usuario y se han eliminado (solo se admiten hosts de redes publicitarias conocidas y los snippets inline estándar de AdSense/GAM). Un desarrollador puede ampliar la lista con los filtros clipnuvex_ad_allowed_hosts / clipnuvex_ad_inline_script_allowed.', 'clipnuvex' ),
			$label
		),
		'warning'
	);
}

/**
 * Sanitiza el array de opciones antes de guardar (por tipo de campo).
 *
 * @param array $input Valores entrantes.
 * @return array
 */
function clipnuvex_sanitize_options( $input ) {
	$fields = clipnuvex_options_fields();
	$out    = get_option( 'clipnuvex_options', array() );
	if ( ! is_array( $out ) ) {
		$out = array();
	}
	$input = is_array( $input ) ? $input : array();

	// Esquema de color (Colores y estilos → Esquema): si llega un preset válido,
	// sus colores sobrescriben los campos base ignorando los valores base que
	// viajan en ese mismo formulario, y el selector vuelve a '' (Mantener).
	// Idempotente: en la 2ª pasada del primer add_option el preset ya es '' y
	// los colores llegan como campos normales con los mismos valores.
	if ( function_exists( 'clipnuvex_color_presets' ) && isset( $input['color_preset'] ) ) {
		$cnx_presets = clipnuvex_color_presets();
		$cnx_preset  = (string) $input['color_preset'];
		if ( '' !== $cnx_preset && isset( $cnx_presets[ $cnx_preset ] ) ) {
			foreach ( $cnx_presets[ $cnx_preset ] as $cnx_pid => $cnx_pval ) {
				unset( $input[ $cnx_pid ] );
				$out[ $cnx_pid ] = $cnx_pval;
			}
		}
		$input['color_preset'] = '';
	}

	// Se procesa CADA campo presente en el envío y se preservan los demás:
	// - Los checkboxes del formulario activo siempre llegan (campo oculto value=0
	//   + checkbox value=1), así que se actualizan; los de otras pestañas no se
	//   envían y se mantienen intactos.
	// - No se depende de ningún marcador de pestaña, de modo que la sanitización
	//   es IDEMPOTENTE. Esto es imprescindible porque WordPress ejecuta el filtro
	//   de sanitización dos veces al crear la opción por primera vez (update_option
	//   → add_option); si dependiera del campo _tab, la 2ª pasada lo perdería y
	//   borraría todo (el clásico "no guarda el primer cambio").
	// - Los valores llegan YA sin slashes: wp-admin/options.php hace wp_unslash()
	//   antes de update_option(), y el demo importer pasa valores PHP crudos. Un
	//   wp_unslash() aquí sería un DOBLE unslash y comería '\' legítimas (p. ej.
	//   escapes JS en códigos de anuncio).
	foreach ( $fields as $id => $field ) {
		if ( ! array_key_exists( $id, $input ) ) {
			continue;
		}
		$out[ $id ] = clipnuvex_sanitize_field( $field, $input[ $id ] );
	}

	return $out;
}

/**
 * Registra la opción.
 */
function clipnuvex_register_options() {
	register_setting(
		'clipnuvex_options_group',
		'clipnuvex_options',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'clipnuvex_sanitize_options',
			'default'           => array(),
		)
	);
}
add_action( 'admin_init', 'clipnuvex_register_options' );

/**
 * El form del panel postea a wp-admin/options.php, que por defecto exige
 * `manage_options`; se alinea con la capability del menú (edit_theme_options)
 * para que quien ve el panel también pueda guardarlo.
 *
 * @return string Capability requerida para guardar el grupo.
 */
function clipnuvex_options_capability() {
	return 'edit_theme_options';
}
add_filter( 'option_page_capability_clipnuvex_options_group', 'clipnuvex_options_capability' );

/**
 * Aplica o retira las reglas de caché larga de imágenes en el .htaccess raíz
 * cuando cambia el ajuste `long_cache_media` del panel.
 *
 * Bloque REVERSIBLE con marcadores `# BEGIN/END Clipnuvex` (insert_with_markers,
 * lo mismo que usa el core para los permalinks). Guardas: solo Apache/LiteSpeed
 * y .htaccess escribible; si no se puede, se registra un aviso para el panel y
 * no se toca nada.
 *
 * @param mixed $old_value Valor anterior de clipnuvex_options.
 * @param mixed $value     Valor nuevo.
 */
function clipnuvex_sync_media_cache_rules( $old_value, $value ) {
	$old_on = is_array( $old_value ) && ! empty( $old_value['long_cache_media'] );
	$new_on = is_array( $value ) && ! empty( $value['long_cache_media'] );
	if ( $old_on === $new_on ) {
		return;
	}

	if ( ! function_exists( 'insert_with_markers' ) ) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}
	if ( ! function_exists( 'get_home_path' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	global $is_apache;
	$server       = isset( $_SERVER['SERVER_SOFTWARE'] ) ? (string) wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$is_litespeed = false !== stripos( $server, 'litespeed' );
	$htaccess     = get_home_path() . '.htaccess';
	$writable     = file_exists( $htaccess ) ? is_writable( $htaccess ) : is_writable( dirname( $htaccess ) );

	if ( ( ! $is_apache && ! $is_litespeed ) || ! $writable ) {
		set_transient(
			'clipnuvex_htaccess_notice',
			__( 'No se pudieron aplicar las reglas de caché de imágenes: el servidor no es Apache/LiteSpeed o el .htaccess no es escribible.', 'clipnuvex' ),
			MINUTE_IN_SECONDS
		);
		return;
	}

	$lines = array();
	if ( $new_on ) {
		$lines = array(
			'<IfModule mod_headers.c>',
			'<FilesMatch "\\.(avif|webp|jpe?g|png|gif|svg)$">',
			'Header set Cache-Control "public, max-age=15552000"',
			'</FilesMatch>',
			'</IfModule>',
			'<IfModule mod_expires.c>',
			'ExpiresActive On',
			'ExpiresByType image/avif "access plus 6 months"',
			'ExpiresByType image/webp "access plus 6 months"',
			'ExpiresByType image/jpeg "access plus 6 months"',
			'ExpiresByType image/png "access plus 6 months"',
			'ExpiresByType image/gif "access plus 6 months"',
			'ExpiresByType image/svg+xml "access plus 6 months"',
			'</IfModule>',
		);
	}
	insert_with_markers( $htaccess, 'Clipnuvex', $lines );
}
add_action( 'update_option_clipnuvex_options', 'clipnuvex_sync_media_cache_rules', 10, 2 );

/**
 * Variante para el primer guardado del panel (add_option no pasa valor previo).
 *
 * @param string $option Nombre de la option.
 * @param mixed  $value  Valor guardado.
 */
function clipnuvex_sync_media_cache_rules_on_add( $option, $value ) {
	clipnuvex_sync_media_cache_rules( array(), $value );
}
add_action( 'add_option_clipnuvex_options', 'clipnuvex_sync_media_cache_rules_on_add', 10, 2 );

/**
 * Muestra en las pantallas del theme el aviso pendiente del .htaccess, si lo hay.
 */
function clipnuvex_htaccess_admin_notice() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || false === strpos( (string) $screen->id, 'clipnuvex' ) ) {
		return;
	}
	$msg = get_transient( 'clipnuvex_htaccess_notice' );
	if ( $msg ) {
		delete_transient( 'clipnuvex_htaccess_notice' );
		printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( $msg ) );
	}
}
add_action( 'admin_notices', 'clipnuvex_htaccess_admin_notice' );
