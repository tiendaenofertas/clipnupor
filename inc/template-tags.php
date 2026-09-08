<?php
/**
 * Funciones de render reutilizables.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Recoge los datos de presentación de un vídeo.
 *
 * @param int|WP_Post $post Post o ID.
 * @return array
 */
function clipnuvex_get_video_data( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return array();
	}
	$id       = $post->ID;
	$terms    = get_the_terms( $id, 'categoria_video' );
	$cat      = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0] : null;
	$thumb_id = get_post_thumbnail_id( $id );

	return array(
		'id'       => $id,
		'title'    => get_the_title( $id ),
		'permalink'=> get_permalink( $id ),
		'poster'   => $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'clipnuvex-poster' ) : '',
		'poster_id'=> $thumb_id,
		'backdrop' => clipnuvex_video_backdrop( $id, $thumb_id ),
		'category' => $cat ? $cat->name : '',
		'cat_link' => $cat ? get_term_link( $cat ) : '',
		'year'     => get_post_meta( $id, '_clipnuvex_anio', true ),
		// El default 'HD' solo aplica a vídeos: un post/página que entre en la
		// búsqueda no debe lucir un badge de calidad falso.
		'quality'  => get_post_meta( $id, '_clipnuvex_calidad', true ) ?: ( 'video' === $post->post_type ? 'HD' : '' ),
		'lang'     => get_post_meta( $id, '_clipnuvex_idioma', true ),
		'featured' => (bool) get_post_meta( $id, '_clipnuvex_destacado', true ),
	);
}

/**
 * Devuelve la URL del backdrop 16:9 (póster como respaldo).
 *
 * @param int      $id       ID del vídeo.
 * @param int|null $thumb_id ID del adjunto (opcional, evita recalcularlo).
 * @return string
 */
function clipnuvex_video_backdrop( $id, $thumb_id = null ) {
	$thumb = ( null === $thumb_id ) ? get_post_thumbnail_id( $id ) : $thumb_id;
	if ( $thumb ) {
		$src = wp_get_attachment_image_url( $thumb, 'clipnuvex-backdrop' );
		if ( $src ) {
			return $src;
		}
		$poster = wp_get_attachment_image_url( $thumb, 'clipnuvex-poster' );
		if ( $poster ) {
			return $poster;
		}
	}
	return '';
}

/**
 * Especificación de la tarjeta según la orientación elegida en el panel
 * (Estilos → Orientación de las miniaturas). 'poster' reproduce LITERALMENTE
 * los valores históricos de la tarjeta 2:3 (salida idéntica); 'wide' es la
 * variante horizontal 16:9.
 *
 * @return array ['mode','size','fallback','w','h','sizes'].
 */
function clipnuvex_card_spec() {
	static $spec = null;
	if ( null !== $spec ) {
		return $spec;
	}
	$mode = function_exists( 'clipnuvex_option' ) ? (string) clipnuvex_option( 'card_orientation', 'poster' ) : 'poster';
	if ( 'wide' === $mode ) {
		$spec = array(
			'mode'     => 'wide',
			'size'     => 'clipnuvex-thumb',
			'fallback' => 'clipnuvex-backdrop',
			'w'        => 320,
			'h'        => 180,
			'sizes'    => '(max-width:519px) 92vw, (max-width:759px) 46vw, (max-width:1023px) 31vw, (max-width:1319px) 23vw, 19vw',
		);
	} else {
		$spec = array(
			'mode'     => 'poster',
			'size'     => 'clipnuvex-poster',
			'fallback' => '',
			'w'        => 300,
			'h'        => 450,
			'sizes'    => '(max-width:519px) 45vw, (max-width:1023px) 23vw, (max-width:1639px) 16vw, 13vw',
		);
	}
	return $spec;
}

/**
 * Imagen de la tarjeta (src + srcset + sizes) para la orientación activa.
 *
 * Modo póster: exactamente las llamadas históricas. Modo horizontal: recorte
 * 640×360 y, si el adjunto es anterior a ese tamaño (sin regenerar
 * miniaturas), el backdrop 1280×720 que existe en todas las subidas. El srcset
 * se calcula con el MISMO tamaño resuelto para que solo entren candidatos 16:9
 * (recorte coherente entre src y srcset).
 *
 * @param int $thumb_id ID del adjunto.
 * @return array ['url','srcset','sizes'] (url '' si no hay imagen).
 */
function clipnuvex_card_image( $thumb_id ) {
	$spec     = clipnuvex_card_spec();
	$empty    = array( 'url' => '', 'srcset' => '', 'sizes' => $spec['sizes'] );
	$thumb_id = (int) $thumb_id;
	if ( ! $thumb_id ) {
		return $empty;
	}
	$size = $spec['size'];
	if ( $spec['fallback'] ) {
		$src = wp_get_attachment_image_src( $thumb_id, $size );
		if ( ! $src || empty( $src[3] ) ) { // Sin ese tamaño intermedio → respaldo.
			$size = $spec['fallback'];
		}
	}
	$url = wp_get_attachment_image_url( $thumb_id, $size );
	if ( ! $url ) {
		return $empty;
	}
	$srcset = wp_get_attachment_image_srcset( $thumb_id, $size );
	return array(
		'url'    => $url,
		'srcset' => $srcset ? $srcset : '',
		'sizes'  => $spec['sizes'],
	);
}

/**
 * Clase de <body> para el modo horizontal: el CSS conmuta marco y columnas de
 * TODOS los grids sin tocar plantillas. En modo póster no añade nada.
 *
 * @param string[] $classes Clases actuales.
 * @return string[]
 */
function clipnuvex_body_class_cards( $classes ) {
	if ( 'wide' === clipnuvex_card_spec()['mode'] ) {
		$classes[] = 'cnx-cards-wide';
	}
	// 1 tarjeta por fila en móvil: restaura los overlays a tamaño normal en
	// horizontal (solo se añade fuera del default → HTML por defecto idéntico).
	if ( function_exists( 'clipnuvex_option' ) && 1 === (int) clipnuvex_option( 'cards_cols_mobile', 2 ) ) {
		$classes[] = 'cnx-cols-mobile-1';
	}
	return $classes;
}
add_filter( 'body_class', 'clipnuvex_body_class_cards' );

/**
 * Parsea las fuentes de embed de un vídeo en servidores estructurados.
 *
 * @param int $id ID del vídeo.
 * @return array Lista de ['label','url','quality'].
 */
function clipnuvex_video_servers( $id ) {
	$raw   = (string) get_post_meta( $id, '_clipnuvex_embed_url', true );
	$lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $raw ) ) );
	$out   = array();
	$i     = 0;
	foreach ( $lines as $line ) {
		$parsed = clipnuvex_parse_embed_line( $line );
		if ( null === $parsed ) {
			continue;
		}
		// Un shortcode solo es una fuente utilizable si su tag sigue registrado:
		// si el plugin que lo provee se desactiva, la fuente se omite (nunca se
		// vuelca el texto crudo del shortcode en la página).
		if ( 'shortcode' === $parsed['type'] ) {
			if ( ! preg_match( '#^\[([a-zA-Z0-9_-]+)#', $parsed['code'], $m ) || ! shortcode_exists( $m[1] ) ) {
				continue;
			}
		}
		$i++;
		$out[] = array(
			/* translators: %d: número de servidor. */
			'label'   => '' !== $parsed['label'] ? $parsed['label'] : sprintf( __( 'Servidor %d', 'clipnuvex' ), $i ),
			'url'     => $parsed['url'],
			'quality' => $parsed['quality'],
			'type'    => $parsed['type'],
			'code'    => $parsed['code'],
		);
	}
	return $out;
}

/**
 * Renderiza una tarjeta de vídeo (póster o lista).
 *
 * @param int|WP_Post $post   Post o ID.
 * @param string      $layout 'poster' | 'list'.
 */
function clipnuvex_card( $post, $layout = 'poster', $args = array() ) {
	$data = clipnuvex_get_video_data( $post );
	if ( empty( $data ) ) {
		return;
	}
	$args         = is_array( $args ) ? $args : array();
	$args['data'] = $data;
	get_template_part( 'template-parts/card', $layout, $args );
}

/**
 * Renderiza el reproductor (póster + play, iframe lazy, pestañas de servidor).
 *
 * @param int $id ID del vídeo.
 */
function clipnuvex_player( $id ) {
	$servers = clipnuvex_video_servers( $id );
	$data    = clipnuvex_get_video_data( $id );
	get_template_part(
		'template-parts/player',
		null,
		array(
			'servers' => $servers,
			'data'    => $data,
		)
	);
}

/**
 * Migas de pan accesibles.
 *
 * @param array $items Lista de ['label','url'] (la última sin url = actual).
 */
function clipnuvex_breadcrumbs( $items ) {
	if ( empty( $items ) ) {
		return;
	}
	if ( function_exists( 'clipnuvex_is_on' ) && ! clipnuvex_is_on( 'show_breadcrumbs' ) ) {
		return;
	}
	$items = array_values( array_filter( $items ) );
	echo '<nav class="cnx-breadcrumbs" aria-label="' . esc_attr__( 'Migas de pan', 'clipnuvex' ) . '"><div class="cnx-container cnx-breadcrumbs__inner">';
	$last     = count( $items ) - 1;
	$sep_done = false;
	foreach ( $items as $i => $item ) {
		// Botón "‹ Volver", sin separador. Vuelve atrás solo si la página anterior
		// es del propio sitio (referrer del mismo origen); si se llegó desde fuera
		// (Google, redes) o sin historial, va a la portada. La URL va entre
		// comillas SIMPLES con esc_js(): con comillas dobles (wp_json_encode) el
		// atributo onclick se cortaba y el botón no hacía nada (bug 1.4.11→1.5.6).
		if ( ! empty( $item['back'] ) ) {
			printf(
				'<button type="button" class="cnx-breadcrumbs__back" onclick="if(history.length&gt;1&amp;&amp;document.referrer.indexOf(location.origin)===0){history.back();}else{location.href=\'%s\';}">%s</button>',
				esc_js( home_url( '/' ) ),
				esc_html( $item['label'] )
			);
			continue;
		}
		if ( $sep_done ) {
			echo '<span class="cnx-breadcrumbs__sep" aria-hidden="true">›</span>';
		}
		$sep_done = true;
		if ( $i === $last || empty( $item['url'] ) ) {
			echo '<span class="cnx-breadcrumbs__current" aria-current="page">' . esc_html( $item['label'] ) . '</span>';
		} else {
			printf( '<a class="cnx-breadcrumbs__link" href="%s">%s</a>', esc_url( $item['url'] ), esc_html( $item['label'] ) );
		}
	}
	echo '</div></nav>';
}

/**
 * Paginación numérica con flechas, estilo del prototipo.
 *
 * @param WP_Query|null $query Consulta (por defecto la principal).
 */
function clipnuvex_pagination( $query = null ) {
	global $wp_query;
	$query = $query ? $query : $wp_query;
	$total = (int) $query->max_num_pages;
	if ( $total < 2 ) {
		return;
	}
	$links = paginate_links(
		array(
			'total'     => $total,
			'current'   => max( 1, get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1 ),
			'mid_size'  => 1,
			'end_size'  => 1,
			'prev_text' => '‹',
			'next_text' => '›',
			'type'      => 'array',
		)
	);
	if ( ! $links ) {
		return;
	}
	echo '<nav class="cnx-pagination" aria-label="' . esc_attr__( 'Paginación', 'clipnuvex' ) . '"><ul class="cnx-pagination__list">';
	foreach ( $links as $link ) {
		// paginate_links ya escapa los anchors; envolvemos en li.
		echo '<li class="cnx-pagination__item">' . wp_kses_post( $link ) . '</li>';
	}
	echo '</ul></nav>';
}

/**
 * Wordmark del logo. Usa custom logo si existe; si no, el wordmark tipográfico.
 */
function clipnuvex_logo() {
	if ( has_custom_logo() ) {
		the_custom_logo();
		return;
	}
	?>
	<?php
	$cnx_w1 = function_exists( 'clipnuvex_text' ) ? clipnuvex_text( 'wordmark_1' ) : 'Clip';
	$cnx_w2 = function_exists( 'clipnuvex_text' ) ? clipnuvex_text( 'wordmark_2' ) : 'nuvex';
	?>
	<a class="cnx-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
		<span class="cnx-logo__mark" aria-hidden="true"><span class="cnx-logo__play"></span></span>
		<span class="cnx-logo__word"><?php echo esc_html( $cnx_w1 ); ?><span class="cnx-logo__accent"><?php echo esc_html( $cnx_w2 ); ?></span></span>
	</a>
	<?php
}

/**
 * Items de la navegación principal (Inicio / Explorar / Categorías).
 *
 * Si hay un menú asignado a la ubicación 'primary' se usa ese; si no, se
 * generan items por defecto coherentes con el prototipo.
 *
 * @return array Lista de ['label','url','active'].
 */
function clipnuvex_nav_items() {
	// Menú personalizado tiene prioridad.
	if ( has_nav_menu( 'primary' ) ) {
		$locations = get_nav_menu_locations();
		$menu      = wp_get_nav_menu_items( $locations['primary'] );
		if ( $menu ) {
			$items = array();
			foreach ( $menu as $m ) {
				if ( (int) $m->menu_item_parent !== 0 ) {
					continue;
				}
				$items[] = array(
					'label'  => $m->title,
					'url'    => $m->url,
					'active' => untrailingslashit( $m->url ) === untrailingslashit( clipnuvex_current_url() ),
				);
			}
			return $items;
		}
	}

	$archive = get_post_type_archive_link( 'video' );
	$txt     = function_exists( 'clipnuvex_text' ) ? 'clipnuvex_text' : null;
	return array(
		array(
			'label'  => $txt ? clipnuvex_text( 'nav_home' ) : __( 'Inicio', 'clipnuvex' ),
			'url'    => home_url( '/' ),
			'active' => is_front_page() || is_home(),
		),
		array(
			'label'  => $txt ? clipnuvex_text( 'nav_browse' ) : __( 'Explorar', 'clipnuvex' ),
			'url'    => $archive ? $archive : home_url( '/' ),
			'active' => is_post_type_archive( 'video' ),
		),
		array(
			'label'  => $txt ? clipnuvex_text( 'nav_categories' ) : __( 'Categorías', 'clipnuvex' ),
			'url'    => clipnuvex_categories_url(),
			'active' => clipnuvex_is_categories_page() || is_tax( array( 'categoria_video', 'tag_video' ) ),
		),
	);
}

/**
 * URL actual (sin query de paginación).
 *
 * @return string
 */
function clipnuvex_current_url() {
	global $wp;
	return home_url( add_query_arg( array(), $wp->request ) );
}

/**
 * ID de la página "Todas las categorías" (plantilla page-categorias.php).
 *
 * Resuelve por el ID guardado al crearla y después por slug en cualquiera de
 * los dos idiomas (clipnuvex_find_categories_page, inc/setup.php). Solo cuenta
 * si es una página PUBLICADA: enlazar un borrador daría 404 al visitante.
 * Caché estática por petición (antes nav_items ya hacía esta consulta).
 *
 * @return int ID de la página o 0 si no existe.
 */
function clipnuvex_categories_page_id() {
	static $resolved = null;
	if ( null !== $resolved ) {
		return $resolved;
	}
	$page     = function_exists( 'clipnuvex_find_categories_page' ) ? clipnuvex_find_categories_page( true ) : null;
	$resolved = ( $page instanceof WP_Post ) ? (int) $page->ID : 0;
	return $resolved;
}

/**
 * URL de "Categorías": la página si existe y está publicada; si no, el archivo
 * "Explorar" (/video/) para no enlazar NUNCA a un 404. Úsala siempre en vez de
 * home_url( '/categorias/' ).
 *
 * @return string
 */
function clipnuvex_categories_url() {
	$id = clipnuvex_categories_page_id();
	if ( $id ) {
		$link = get_permalink( $id );
		if ( $link ) {
			return $link;
		}
	}
	$archive = get_post_type_archive_link( 'video' );
	return $archive ? $archive : home_url( '/' );
}

/**
 * ¿La vista actual es la página "Todas las categorías"?
 *
 * @return bool
 */
function clipnuvex_is_categories_page() {
	$id = clipnuvex_categories_page_id();
	// Guardado: is_page( 0 ) devolvería true en CUALQUIER página.
	return $id > 0 && is_page( $id );
}

/**
 * Enlaces legales del pie cuando no hay menú asignado a 'footer': SOLO páginas
 * publicadas. Los antiguos enlaces fijos a /privacidad/ /terminos/ /cookies/
 * /contacto/ daban 404 en cualquier sitio que no las tuviera. Cada entrada
 * reconoce los slugs en español y en inglés (los que crea el Modo Demo en cada
 * idioma) y "Privacidad" usa antes la página de Ajustes → Privacidad si está
 * definida. Incluye DMCA (desde 1.5.7).
 *
 * Una única consulta indexada (post_name IN) cacheada 12 h en el grupo
 * `clipnuvex` con el `last_changed` de posts y la estructura de enlaces como
 * sal: cualquier alta/edición/papelera de página o cambio de permalinks la
 * invalida (misma estrategia que get_page_by_path()). Sin object cache
 * persistente equivale a una consulta ligera por petición.
 *
 * Filtro `clipnuvex_footer_legal_slugs`: clave => ['label' => string,
 * 'slugs' => string[]] (forma nueva desde 1.5.7; el orden es el del pie).
 *
 * @return array Lista de ['label' => string, 'url' => string].
 */
function clipnuvex_footer_legal_links() {
	$groups = apply_filters(
		'clipnuvex_footer_legal_slugs',
		array(
			'privacy' => array( 'label' => __( 'Privacidad', 'clipnuvex' ), 'slugs' => array( 'privacidad', 'privacy-policy', 'privacy' ) ),
			'terms'   => array( 'label' => __( 'Términos', 'clipnuvex' ), 'slugs' => array( 'terminos', 'terms-of-service', 'terms' ) ),
			'cookies' => array( 'label' => __( 'Cookies', 'clipnuvex' ), 'slugs' => array( 'cookies', 'cookie-policy' ) ),
			'contact' => array( 'label' => __( 'Contacto', 'clipnuvex' ), 'slugs' => array( 'contacto', 'contact' ) ),
			'dmca'    => array( 'label' => 'DMCA', 'slugs' => array( 'dmca' ) ),
		)
	);
	$groups = is_array( $groups ) ? $groups : array();

	$all_slugs = array();
	foreach ( $groups as $group ) {
		if ( ! empty( $group['slugs'] ) && is_array( $group['slugs'] ) ) {
			$all_slugs = array_merge( $all_slugs, array_map( 'sanitize_title', $group['slugs'] ) );
		}
	}
	$all_slugs = array_values( array_unique( array_filter( $all_slugs ) ) );

	$found = array();
	if ( $all_slugs ) {
		$key   = 'cnx_legal_' . md5( implode( '|', $all_slugs ) . '|' . get_option( 'permalink_structure' ) ) . '_' . wp_cache_get_last_changed( 'posts' );
		$found = wp_cache_get( $key, 'clipnuvex' );
		if ( ! is_array( $found ) ) {
			$found = array();
			$pages = get_posts(
				array(
					'post_type'              => 'page',
					'post_status'            => 'publish',
					'post_name__in'          => $all_slugs,
					'posts_per_page'         => count( $all_slugs ),
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			foreach ( $pages as $page ) {
				$link = get_permalink( $page );
				if ( $link ) {
					$found[ $page->post_name ] = $link;
				}
			}
			wp_cache_set( $key, $found, 'clipnuvex', 12 * HOUR_IN_SECONDS );
		}
	}

	$links = array();
	foreach ( $groups as $group_key => $group ) {
		$url = '';
		if ( 'privacy' === $group_key && function_exists( 'get_privacy_policy_url' ) ) {
			$url = (string) get_privacy_policy_url();
		}
		if ( '' === $url && ! empty( $group['slugs'] ) && is_array( $group['slugs'] ) ) {
			foreach ( $group['slugs'] as $slug ) {
				$slug = sanitize_title( $slug );
				if ( isset( $found[ $slug ] ) ) {
					$url = $found[ $slug ];
					break;
				}
			}
		}
		if ( $url ) {
			$links[] = array(
				'label' => isset( $group['label'] ) ? (string) $group['label'] : $group_key,
				'url'   => $url,
			);
		}
	}
	return (array) apply_filters( 'clipnuvex_footer_legal_links', $links );
}

/**
 * Devuelve los términos de categoría ordenados con su contador e imagen.
 *
 * @param int $limit Límite (0 = todos).
 * @return array Lista de WP_Term.
 */
function clipnuvex_get_categories( $limit = 0 ) {
	$cache_key = clipnuvex_cache_key( 'cnx_cats_' . (int) $limit );
	$cached    = wp_cache_get( $cache_key, 'clipnuvex' );
	if ( false !== $cached ) {
		return $cached;
	}

	$args = array(
		'taxonomy'   => 'categoria_video',
		'hide_empty' => false,
		'orderby'    => 'name',
	);
	if ( $limit ) {
		$args['number'] = $limit;
	}
	$terms = get_terms( $args );
	$terms = is_wp_error( $terms ) ? array() : $terms;

	wp_cache_set( $cache_key, $terms, 'clipnuvex', HOUR_IN_SECONDS );
	return $terms;
}

/**
 * Invalida la caché de categorías al crear/editar/borrar términos.
 * (Sal versionada en vez de wp_cache_flush_group(): ver clipnuvex_cache_key.)
 */
function clipnuvex_flush_category_cache() {
	clipnuvex_cache_bump();
}
add_action( 'created_categoria_video', 'clipnuvex_flush_category_cache' );
add_action( 'edited_categoria_video', 'clipnuvex_flush_category_cache' );
add_action( 'delete_categoria_video', 'clipnuvex_flush_category_cache' );

/**
 * Resuelve el contenido del bloque SEO de una categoría.
 *
 * Precedencia por campo: texto propio del término (pantalla de edición de la
 * categoría) → texto global del panel (Clipnuvex → Textos) → default del
 * registry. El placeholder %s se sustituye por el nombre del término con
 * str_replace — NUNCA printf: un "%" suelto en un texto editable por el admin
 * (p. ej. "100% legal") no debe romper el render.
 *
 * @param WP_Term $term Término de categoria_video.
 * @return array{show:bool,title:string,intro:string,points:string[]}
 */
function clipnuvex_seo_block_data( $term ) {
	if ( ! ( $term instanceof WP_Term ) ) {
		return array(
			'show'   => false,
			'title'  => '',
			'intro'  => '',
			'points' => array(),
		);
	}

	$show = ( ! function_exists( 'clipnuvex_is_on' ) || clipnuvex_is_on( 'show_seo_block' ) )
		&& '1' !== get_term_meta( $term->term_id, 'clipnuvex_seo_hide', true );

	$title = trim( (string) get_term_meta( $term->term_id, 'clipnuvex_seo_title', true ) );
	if ( '' === $title ) {
		$title = clipnuvex_text( 'seo_block_heading' );
	}

	$intro = trim( (string) get_term_meta( $term->term_id, 'clipnuvex_seo_intro', true ) );
	if ( '' === $intro ) {
		$intro = clipnuvex_text( 'seo_block_intro' );
	}

	// Puntos propios: una línea = un punto (número flexible). Sin propios,
	// los cuatro globales del panel.
	$points = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) get_term_meta( $term->term_id, 'clipnuvex_seo_points', true ) ) as $line ) {
		$line = trim( $line );
		if ( '' !== $line ) {
			$points[] = $line;
		}
	}
	if ( ! $points ) {
		$points = array(
			clipnuvex_text( 'seo_point_1' ),
			clipnuvex_text( 'seo_point_2' ),
			clipnuvex_text( 'seo_point_3' ),
			clipnuvex_text( 'seo_point_4' ),
		);
	}

	$fill = static function ( $text ) use ( $term ) {
		return str_replace( '%s', $term->name, (string) $text );
	};

	return array(
		'show'   => $show,
		'title'  => $fill( $title ),
		'intro'  => $fill( $intro ),
		'points' => array_map( $fill, $points ),
	);
}
