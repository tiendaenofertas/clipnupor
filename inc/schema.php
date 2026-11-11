<?php
/**
 * Datos estructurados JSON-LD (schema.org) de Clipnuvex.
 *
 * Imprime un grafo @graph por vista en `wp_head` (prioridad 20) usando
 * `wp_json_encode`, que escapa automáticamente el contenido. El bloque se
 * envuelve en <script type="application/ld+json">.
 *
 * No ejecuta consultas pesadas: la lista de vídeos para los ItemList se lee
 * de `$GLOBALS['wp_query']->posts` (el bucle principal ya cargado).
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Imprime un grafo JSON-LD ya construido dentro de un <script> seguro.
 *
 * @param array $graph Lista de nodos schema.org.
 */
function clipnuvex_schema_print_graph( $graph ) {
	$graph = array_values( array_filter( $graph ) );
	if ( empty( $graph ) ) {
		return;
	}

	$payload = array(
		'@context' => 'https://schema.org',
		'@graph'   => $graph,
	);

	$json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( false === $json ) {
		return;
	}

	// wp_json_encode escapa el contenido; cerramos cualquier </script> defensivamente.
	$json = str_replace( '</', '<\/', $json );

	echo "\n\t<!-- Clipnuvex JSON-LD -->\n";
	echo '<script type="application/ld+json">' . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode escapa el payload.
}

/**
 * Devuelve la imagen representativa de un vídeo (backdrop con respaldo de póster).
 *
 * @param int $id ID del vídeo.
 * @return array Lista de URLs (puede estar vacía).
 */
function clipnuvex_schema_video_images( $id ) {
	$images = array();
	if ( function_exists( 'clipnuvex_get_video_data' ) ) {
		$data = clipnuvex_get_video_data( $id );
		if ( ! empty( $data['backdrop'] ) ) {
			$images[] = $data['backdrop'];
		}
		if ( ! empty( $data['poster'] ) && ! in_array( $data['poster'], $images, true ) ) {
			$images[] = $data['poster'];
		}
	}
	if ( empty( $images ) && has_post_thumbnail( $id ) ) {
		$src = get_the_post_thumbnail_url( $id, 'full' );
		if ( $src ) {
			$images[] = $src;
		}
	}
	return $images;
}

/**
 * Construye un nodo VideoObject a partir de un ID de vídeo.
 *
 * @param int $id ID del vídeo.
 * @return array
 */
function clipnuvex_schema_video_object( $id ) {
	$data        = function_exists( 'clipnuvex_get_video_data' ) ? clipnuvex_get_video_data( $id ) : array();
	$title       = isset( $data['title'] ) && $data['title'] ? $data['title'] : get_the_title( $id );
	$permalink   = isset( $data['permalink'] ) && $data['permalink'] ? $data['permalink'] : get_permalink( $id );
	$images      = clipnuvex_schema_video_images( $id );
	$description = has_excerpt( $id ) ? get_the_excerpt( $id ) : wp_strip_all_tags( get_post_field( 'post_content', $id ) );
	$description = trim( preg_replace( '/\s+/u', ' ', (string) $description ) );
	if ( '' === $description ) {
		$description = $title;
	}

	// uploadDate = fecha REAL de publicación en ISO 8601 con zona horaria (lo que
	// exige Google). get_post_time() no pasa por el filtro `get_the_date`. El
	// año del título (meta) NO es la fecha de subida: va como copyrightYear para
	// no confundir a los buscadores con la fecha del contenido.
	$upload_date = get_post_time( 'c', false, $id );
	$year        = get_post_meta( $id, '_clipnuvex_anio', true );

	// Sin campo Idioma en el vídeo → idioma del sitio (ajuste del panel).
	$lang = get_post_meta( $id, '_clipnuvex_idioma', true );
	$lang = $lang ? $lang : ( function_exists( 'clipnuvex_site_lang' ) ? clipnuvex_site_lang() : 'en' );

	// isFamilyFriendly configurable (SEO → "Vídeos aptos para todos los
	// públicos", activado por defecto → misma salida que antes).
	$family = function_exists( 'clipnuvex_is_on' ) ? clipnuvex_is_on( 'schema_family_friendly' ) : true;

	$node = array(
		'@type'            => 'VideoObject',
		'@id'              => $permalink . '#video',
		'name'             => $title,
		'description'      => $description,
		'uploadDate'       => $upload_date,
		'inLanguage'       => $lang,
		'isFamilyFriendly' => (bool) $family,
		'url'              => $permalink,
		'potentialAction'  => array(
			'@type'  => 'WatchAction',
			'target' => $permalink,
		),
	);
	if ( $year && preg_match( '/^\d{4}$/', (string) $year ) ) {
		$node['copyrightYear'] = (int) $year;
	}

	// thumbnailUrl es OBLIGATORIo para Google: si el vídeo no tiene imagen,
	// usar la imagen por defecto del sitio para no emitir un VideoObject inválido.
	if ( empty( $images ) && function_exists( 'clipnuvex_seo_default_image' ) ) {
		$fallback = clipnuvex_seo_default_image();
		if ( $fallback ) {
			$images[] = $fallback;
		}
	}
	if ( ! empty( $images ) ) {
		$node['thumbnailUrl'] = $images;
	}

	// embedUrl recomendado por Google: primera fuente del vídeo con URL real
	// (las fuentes de tipo [shortcode] no tienen URL de embed y se saltan).
	if ( function_exists( 'clipnuvex_video_servers' ) ) {
		$servers = clipnuvex_video_servers( $id );
		foreach ( $servers as $server ) {
			if ( ! empty( $server['url'] ) ) {
				$node['embedUrl'] = $server['url'];
				break;
			}
		}
	}

	if ( ! empty( $data['category'] ) ) {
		$node['genre'] = $data['category'];
	}

	// Duración en minutos → ISO 8601 (PT#M).
	$minutes = (int) get_post_meta( $id, '_clipnuvex_duracion', true );
	if ( $minutes > 0 ) {
		$node['duration'] = 'PT' . $minutes . 'M';
	}

	return $node;
}

/**
 * Construye un nodo BreadcrumbList.
 *
 * @param array $items Lista de ['name','url']. El último puede omitir 'url'.
 * @return array
 */
function clipnuvex_schema_breadcrumbs( $items ) {
	$elements = array();
	$position = 1;
	foreach ( $items as $item ) {
		if ( empty( $item['name'] ) ) {
			continue;
		}
		$element = array(
			'@type'    => 'ListItem',
			'position' => $position,
			'name'     => $item['name'],
		);
		if ( ! empty( $item['url'] ) ) {
			$element['item'] = $item['url'];
		}
		$elements[] = $element;
		$position++;
	}
	return array(
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $elements,
	);
}

/**
 * Construye un ItemList a partir de los posts del bucle principal.
 *
 * @return array Lista de elementos ListItem.
 */
function clipnuvex_schema_loop_item_list() {
	$elements = array();
	if ( empty( $GLOBALS['wp_query'] ) || empty( $GLOBALS['wp_query']->posts ) ) {
		return $elements;
	}
	$position = 1;
	foreach ( $GLOBALS['wp_query']->posts as $post ) {
		if ( ! is_a( $post, 'WP_Post' ) ) {
			continue;
		}
		$elements[] = array(
			'@type'    => 'ListItem',
			'position' => $position,
			'url'      => get_permalink( $post ),
			'name'     => get_the_title( $post ),
		);
		$position++;
	}
	return $elements;
}

/**
 * Imprime el JSON-LD apropiado para la vista actual.
 *
 * Enganchado en `wp_head` con prioridad 20.
 */
function clipnuvex_schema_output() {
	if ( is_feed() || is_embed() || is_404() ) {
		return;
	}

	// Si hay un plugin SEO activo, él emite el JSON-LD: no duplicar.
	if ( function_exists( 'clipnuvex_seo_plugin_active' ) && clipnuvex_seo_plugin_active() ) {
		return;
	}

	$site_name = get_bloginfo( 'name' );
	$home      = home_url( '/' );
	$graph     = array();

	// Portada: WebSite + Organization.
	if ( is_front_page() || is_home() ) {
		$website = array(
			'@type'           => 'WebSite',
			'@id'             => $home . '#website',
			'url'             => $home,
			'name'            => $site_name,
			'description'     => get_bloginfo( 'description' ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				),
				'query-input' => 'required name=search_term_string',
			),
		);

		// Nombre editable (panel → SEO → Nombre de la organización); vacío = nombre del sitio.
		$org_name     = (string) clipnuvex_option( 'seo_org_name', '' );
		$organization = array(
			'@type' => 'Organization',
			'@id'   => $home . '#organization',
			'name'  => '' !== trim( $org_name ) ? $org_name : $site_name,
			'url'   => $home,
		);
		// Logo como ImageObject (lo que pide Google) con respaldo al logo del theme.
		$logo = function_exists( 'get_site_icon_url' ) ? get_site_icon_url( 512 ) : '';
		if ( ! $logo && function_exists( 'clipnuvex_seo_default_image' ) ) {
			$logo = clipnuvex_seo_default_image();
		}
		if ( $logo ) {
			$organization['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $logo,
			);
		}

		clipnuvex_schema_print_graph( array( $website, $organization ) );
		return;
	}

	// Vídeo individual: VideoObject + BreadcrumbList.
	if ( is_singular( 'video' ) ) {
		$id   = get_queried_object_id();
		$data = function_exists( 'clipnuvex_get_video_data' ) ? clipnuvex_get_video_data( $id ) : array();

		$crumbs = array( array( 'name' => __( 'Inicio', 'clipnuvex' ), 'url' => $home ) );
		if ( ! empty( $data['category'] ) ) {
			$crumbs[] = array(
				'name' => $data['category'],
				'url'  => ! empty( $data['cat_link'] ) ? $data['cat_link'] : '',
			);
		}
		$crumbs[] = array( 'name' => isset( $data['title'] ) && $data['title'] ? $data['title'] : get_the_title( $id ) );

		$graph[] = clipnuvex_schema_video_object( $id );
		$graph[] = clipnuvex_schema_breadcrumbs( $crumbs );
		clipnuvex_schema_print_graph( $graph );
		return;
	}

	// Archivo de taxonomía: CollectionPage + ItemList + BreadcrumbList.
	if ( is_tax( array( 'categoria_video', 'tag_video' ) ) ) {
		$term     = get_queried_object();
		$name     = ( $term && isset( $term->name ) ) ? $term->name : '';
		$term_url = $term ? get_term_link( $term ) : '';
		$term_url = ( $term_url && ! is_wp_error( $term_url ) ) ? $term_url : clipnuvex_schema_current_url();
		$is_tag   = is_tax( 'tag_video' );

		$collection = array(
			'@type' => 'CollectionPage',
			'@id'   => $term_url . '#collection',
			'url'   => $term_url,
			'name'  => $name,
		);
		if ( $term && ! empty( $term->description ) ) {
			$collection['description'] = wp_strip_all_tags( $term->description );
		}

		$item_list = array(
			'@type'           => 'ItemList',
			'itemListElement' => clipnuvex_schema_loop_item_list(),
		);

		$mid_label = $is_tag ? __( 'Tags', 'clipnuvex' ) : __( 'Categorías', 'clipnuvex' );
		$mid_url   = home_url( '/tag/' );
		if ( ! $is_tag ) {
			// Página de categorías publicada o, si no existe, el archivo /video/
			// (mismo destino que la miga visible; antes caía a /categoria/, un 404).
			$mid_url = function_exists( 'clipnuvex_categories_url' ) ? clipnuvex_categories_url() : home_url( '/' );
		}

		$crumbs = array(
			array( 'name' => __( 'Inicio', 'clipnuvex' ), 'url' => $home ),
			array( 'name' => $mid_label, 'url' => $mid_url ),
			array( 'name' => $name ),
		);

		clipnuvex_schema_print_graph(
			array( $collection, $item_list, clipnuvex_schema_breadcrumbs( $crumbs ) )
		);
		return;
	}

	// Resultados de búsqueda: SearchResultsPage + BreadcrumbList.
	if ( is_search() ) {
		$query      = get_search_query();
		$search_url = home_url( '/?s=' . rawurlencode( $query ) );

		$results = array(
			'@type' => 'SearchResultsPage',
			'@id'   => $search_url . '#search',
			'url'   => $search_url,
			'name'  => sprintf(
				/* translators: %s: término buscado. */
				__( 'Resultados de «%s»', 'clipnuvex' ),
				$query
			),
		);

		$crumbs = array(
			array( 'name' => __( 'Inicio', 'clipnuvex' ), 'url' => $home ),
			array( 'name' => __( 'Búsqueda', 'clipnuvex' ) ),
		);

		clipnuvex_schema_print_graph(
			array( $results, clipnuvex_schema_breadcrumbs( $crumbs ) )
		);
		return;
	}
}
add_action( 'wp_head', 'clipnuvex_schema_output', 20 );

/**
 * URL actual completa para nodos de schema cuando no hay enlace canónico claro.
 *
 * @return string
 */
function clipnuvex_schema_current_url() {
	if ( function_exists( 'clipnuvex_seo_current_url' ) ) {
		return clipnuvex_seo_current_url();
	}
	if ( function_exists( 'clipnuvex_current_url' ) ) {
		return clipnuvex_current_url();
	}
	global $wp;
	$request = isset( $wp->request ) ? $wp->request : '';
	return home_url( add_query_arg( array(), $request ) );
}
