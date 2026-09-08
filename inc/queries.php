<?php
/**
 * Modificaciones de consulta: orden, búsqueda y helpers de related.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajusta las consultas principales: home, archivos de taxonomía, búsqueda y orden.
 *
 * @param WP_Query $query Consulta.
 */
function clipnuvex_pre_get_posts( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	// La home incluye el CPT video (Novedades), solo cuando la portada muestra
	// las últimas entradas (no cuando hay una página estática como portada).
	if ( ( $query->is_home() || $query->is_front_page() ) && 'posts' === get_option( 'show_on_front' ) ) {
		$query->set( 'post_type', array( 'video' ) );
	}

	// Nº de vídeos por página configurable desde el panel (home + archivos).
	if ( function_exists( 'clipnuvex_option' ) && ( $query->is_home() || $query->is_front_page() || $query->is_post_type_archive( 'video' ) || $query->is_tax( array( 'categoria_video', 'tag_video' ) ) ) ) {
		$per = (int) clipnuvex_option( 'home_per_page', 21 );
		if ( $per > 0 ) {
			$query->set( 'posts_per_page', $per );
		}
	}

	// Búsqueda: tipos de contenido configurables desde el panel (Clipnuvex →
	// Buscador); por defecto solo vídeos. Los términos se añaden en search.php.
	if ( $query->is_search() ) {
		$query->set( 'post_type', clipnuvex_search_post_types() );
	}

	// Orden en archivos de taxonomía.
	if ( $query->is_tax( array( 'categoria_video', 'tag_video' ) ) ) {
		$sort = isset( $_GET['orden'] ) ? sanitize_key( wp_unslash( $_GET['orden'] ) ) : 'recientes';
		clipnuvex_apply_sort( $query, $sort );
	}
}
add_action( 'pre_get_posts', 'clipnuvex_pre_get_posts' );

/**
 * Aplica un criterio de orden a la consulta.
 *
 * @param WP_Query $query Consulta.
 * @param string   $sort  recientes | populares | az.
 */
function clipnuvex_apply_sort( $query, $sort ) {
	switch ( $sort ) {
		case 'populares':
			/*
			 * NUNCA `orderby meta_value_num` en SQL (regla del repo): además de
			 * poder morir en silencio en hostings reales, su INNER JOIN EXCLUÍA
			 * los vídeos sin fila _clipnuvex_views (los nunca vistos
			 * desaparecían del archivo). Ranking en PHP sobre pool acotado +
			 * post__in, mismo patrón que clipnuvex_search_recommendations().
			 */
			$cnx_term = $query->get_queried_object();
			$cnx_ids  = ( $cnx_term instanceof WP_Term )
				? clipnuvex_tax_popular_ids( $cnx_term->taxonomy, $cnx_term->term_id )
				: array();
			if ( $cnx_ids ) {
				$query->set( 'post__in', $cnx_ids );
				$query->set( 'orderby', 'post__in' );
			} else {
				// Término sin vídeos o sin contexto: mismo orden que "recientes"
				// (jamás un post__in vacío, que devolvería cero resultados).
				$query->set( 'orderby', 'date' );
				$query->set( 'order', 'DESC' );
			}
			break;
		case 'az':
			$query->set( 'orderby', 'title' );
			$query->set( 'order', 'ASC' );
			break;
		case 'recientes':
		default:
			$query->set( 'orderby', 'date' );
			$query->set( 'order', 'DESC' );
			break;
	}
}

/**
 * IDs del término ordenados por "Populares", con SQL a prueba de hostings
 * (réplica del patrón de clipnuvex_search_recommendations, validado en
 * producción en v1.4.3):
 *
 * - Pool A: vídeos del término CON vistas — una única cláusula EXISTS y
 *   ORDER BY sobre wp_posts.post_date (nunca sobre el meta), acotado por
 *   `clipnuvex_tax_popular_pool_cap` (500). Vistas leídas con un SELECT
 *   dirigido y ranking EN PHP (decorate-sort estable).
 * - Pool B: vídeos del término SIN vistas, al final por fecha — el antiguo
 *   INNER JOIN los excluía del archivo.
 * - Caché autocurable por término: transient 1 h con items; sentinel 'none'
 *   5 min si vacío; un array vacío heredado se borra y recalcula. Las vistas
 *   pueden tardar hasta 1 h en reordenar (mismo compromiso que el buscador).
 *
 * Bajo "Populares" el archivo pagina como máximo `cap` ítems por término
 * (filtrable); los criterios recientes/az no pasan por aquí y no cambian.
 *
 * @param string $taxonomy Taxonomía del archivo.
 * @param int    $term_id  ID del término.
 * @return int[] IDs ordenados (vacío si el término no tiene vídeos).
 */
function clipnuvex_tax_popular_ids( $taxonomy, $term_id ) {
	$cap = (int) apply_filters( 'clipnuvex_tax_popular_pool_cap', 500 );
	$cap = max( 10, min( 2000, $cap ) );
	$key = 'cnx_taxpop_' . md5( CLIPNUVEX_VERSION . '|' . $taxonomy . '|' . (int) $term_id . '|' . $cap );

	$cached = get_transient( $key );
	if ( is_array( $cached ) && ! empty( $cached ) ) {
		return array_map( 'intval', $cached );
	}
	if ( 'none' === $cached ) {
		return array(); // Vacío "fresco" (TTL corto): no re-consultar todavía.
	}
	if ( is_array( $cached ) ) {
		delete_transient( $key ); // Vacío heredado/inmortal: autocuración.
	}

	$base = array(
		'post_type'              => 'video',
		'post_status'            => 'publish',
		'fields'                 => 'ids',
		'no_found_rows'          => true,
		'ignore_sticky_posts'    => true,
		'update_post_term_cache' => false,
		'orderby'                => 'date',
		'order'                  => 'DESC',
		'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => (int) $term_id,
			),
		),
	);

	// Pool A: solo vídeos con vistas.
	$pool = ( new WP_Query(
		array_merge(
			$base,
			array(
				'posts_per_page' => $cap,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_clipnuvex_views',
						'compare' => 'EXISTS',
					),
				),
			)
		)
	) )->posts;
	$pool = array_map( 'intval', (array) $pool );

	$ids = array();
	if ( $pool ) {
		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $pool ), '%d' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_clipnuvex_views' AND post_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$pool
			)
		);
		$views = array();
		foreach ( (array) $rows as $row ) {
			$views[ (int) $row->post_id ] = (int) $row->meta_value;
		}
		// Orden estable también en PHP 7.4 (usort no es estable hasta 8.0):
		// decorado con el índice del pool (date DESC) como desempate.
		$decorated = array();
		foreach ( $pool as $i => $id ) {
			$decorated[] = array(
				'id' => $id,
				'v'  => isset( $views[ $id ] ) ? $views[ $id ] : 0,
				'i'  => $i,
			);
		}
		usort(
			$decorated,
			function ( $a, $b ) {
				return ( $b['v'] <=> $a['v'] ) ?: ( $a['i'] <=> $b['i'] );
			}
		);
		$ids = wp_list_pluck( $decorated, 'id' );
	}

	// Pool B: vídeos sin vistas al final (por fecha), hasta completar el cap.
	if ( count( $ids ) < $cap ) {
		$fill_args = array_merge( $base, array( 'posts_per_page' => $cap - count( $ids ) ) );
		if ( $ids ) {
			$fill_args['post__not_in'] = $ids;
		}
		$fill = ( new WP_Query( $fill_args ) )->posts;
		$ids  = array_merge( $ids, array_map( 'intval', (array) $fill ) );
	}

	if ( $ids ) {
		set_transient( $key, $ids, HOUR_IN_SECONDS );
	} else {
		set_transient( $key, 'none', 5 * MINUTE_IN_SECONDS );
	}
	return $ids;
}

/**
 * Devuelve las opciones de orden disponibles (clave => etiqueta traducible).
 *
 * @return array
 */
function clipnuvex_sort_options() {
	return array(
		'recientes' => __( 'Recientes', 'clipnuvex' ),
		'populares' => __( 'Populares', 'clipnuvex' ),
		'az'        => __( 'A-Z', 'clipnuvex' ),
	);
}

/**
 * Incrementa de forma atómica el contador de visualizaciones (para "Populares").
 *
 * Se usa una UPDATE atómica en lugar de leer-modificar-escribir para evitar
 * perder incrementos bajo concurrencia. El conteo se dispara vía REST desde el
 * cliente (no en cada render) para no escribir en páginas servidas desde caché
 * y para no contar bots que no ejecutan JS.
 *
 * @param int $post_id ID del vídeo.
 * @return bool
 */
function clipnuvex_track_view( $post_id ) {
	global $wpdb;
	$post_id = absint( $post_id );
	if ( ! $post_id || 'video' !== get_post_type( $post_id ) ) {
		return false;
	}
	$updated = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->postmeta} SET meta_value = meta_value + 1 WHERE post_id = %d AND meta_key = %s",
			$post_id,
			'_clipnuvex_views'
		)
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	if ( ! $updated ) {
		add_post_meta( $post_id, '_clipnuvex_views', 1, true );
	}
	wp_cache_delete( $post_id, 'post_meta' );
	return true;
}

/**
 * IDs de vídeos relacionados (misma categoría), cacheados por vídeo.
 *
 * Evita `ORDER BY RAND()` en la ruta caliente: obtiene un conjunto reciente
 * mayor por taxonomía y lo baraja en PHP, completando con recientes si falta.
 * El resultado se cachea en el object cache (o transient) y se invalida al
 * guardar cualquier vídeo.
 *
 * @param int $post_id ID del vídeo actual.
 * @param int $limit   Número máximo de relacionados.
 * @return int[] IDs de vídeos.
 */
function clipnuvex_related_ids( $post_id, $limit = 6 ) {
	$cache_key = clipnuvex_cache_key( 'cnx_related_' . $post_id . '_' . $limit );
	$cached    = wp_cache_get( $cache_key, 'clipnuvex' );
	if ( false !== $cached ) {
		return $cached;
	}

	$terms = wp_get_post_terms( $post_id, 'categoria_video', array( 'fields' => 'ids' ) );
	$pool  = array();

	if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
		// Conjunto candidato amplio (4×) para barajar en PHP sin RAND() en SQL.
		$pool = get_posts(
			array(
				'post_type'           => 'video',
				'posts_per_page'      => max( $limit * 4, 24 ),
				'post__not_in'        => array( $post_id ),
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'update_post_term_cache' => false,
				'fields'              => 'ids',
				'orderby'             => 'date',
				'tax_query'           => array(
					array(
						'taxonomy' => 'categoria_video',
						'field'    => 'term_id',
						'terms'    => $terms,
					),
				),
			)
		);
		shuffle( $pool );
	}

	$ids = array_slice( $pool, 0, $limit );

	// Respaldo: completar con recientes manteniendo los ya elegidos.
	if ( count( $ids ) < $limit ) {
		$fill = get_posts(
			array(
				'post_type'           => 'video',
				'posts_per_page'      => $limit - count( $ids ),
				'post__not_in'        => array_merge( array( $post_id ), $ids ),
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'update_post_term_cache' => false,
				'fields'              => 'ids',
				'orderby'             => 'date',
			)
		);
		$ids = array_merge( $ids, $fill );
	}

	$ids = array_map( 'intval', $ids );
	wp_cache_set( $cache_key, $ids, 'clipnuvex', HOUR_IN_SECONDS );
	return $ids;
}

/**
 * Obtiene vídeos relacionados como WP_Query (misma categoría, excluye actual).
 *
 * @param int $post_id ID del vídeo actual.
 * @param int $limit   Número máximo de relacionados.
 * @return WP_Query
 */
function clipnuvex_related_videos( $post_id, $limit = 6 ) {
	$ids = clipnuvex_related_ids( $post_id, $limit );
	if ( empty( $ids ) ) {
		// Consulta vacía pero válida.
		return new WP_Query( array( 'post_type' => 'video', 'post__in' => array( 0 ), 'no_found_rows' => true ) );
	}
	return new WP_Query(
		array(
			'post_type'           => 'video',
			'post__in'            => $ids,
			'orderby'             => 'post__in',
			'posts_per_page'      => $limit,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
}

/**
 * Invalida la caché de relacionados al guardar un vídeo.
 *
 * @param int $post_id ID del post guardado.
 */
function clipnuvex_flush_related_cache( $post_id ) {
	if ( 'video' === get_post_type( $post_id ) ) {
		// Sal versionada en vez de wp_cache_flush_group() (ver clipnuvex_cache_key).
		clipnuvex_cache_bump();
	}
}
add_action( 'save_post_video', 'clipnuvex_flush_related_cache' );

/**
 * Post types públicos elegibles para el buscador (id => etiqueta), para los
 * checkboxes dinámicos del panel (Clipnuvex → Buscador). Excluye adjuntos.
 * Antes de `init` los CPT aún no existen, por eso `video` va garantizado.
 *
 * @return array post_type => etiqueta plural.
 */
function clipnuvex_search_available_post_types() {
	$out = array( 'video' => __( 'Vídeos', 'clipnuvex' ) );
	foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $name => $obj ) {
		if ( 'attachment' === $name || isset( $out[ $name ] ) ) {
			continue;
		}
		$out[ $name ] = $obj->labels->name;
	}
	return $out;
}

/**
 * Tipos de contenido activos en la búsqueda según el panel (search_in_*).
 * Fallback: solo `video` (comportamiento clásico del theme).
 *
 * @return string[]
 */
function clipnuvex_search_post_types() {
	$active = array();
	foreach ( array_keys( clipnuvex_search_available_post_types() ) as $ptype ) {
		if ( post_type_exists( $ptype ) && clipnuvex_is_on( 'search_in_' . $ptype ) ) {
			$active[] = $ptype;
		}
	}
	return $active ? $active : array( 'video' );
}

/**
 * Tamaño del pool de candidatos de "populares", con clamp de seguridad: un
 * filtro que devuelva -1/0/valores absurdos no puede provocar un OOM ni
 * matar el bloque.
 *
 * @return int
 */
function clipnuvex_search_recs_pool_cap() {
	$count = max( 2, min( 40, (int) clipnuvex_option( 'search_recs_count', 20 ) ) );
	$cap   = absint( apply_filters( 'clipnuvex_search_recs_pool', 500 ) );
	if ( ! $cap ) {
		$cap = 500;
	}
	return max( $count, min( 500, $cap ) );
}

/**
 * Clave del transient de recomendaciones para la config actual. Incluye
 * count, criterio, tipos, tamaño del pool e idioma: cambiar cualquiera genera
 * clave nueva al momento (la vieja expira por TTL) y con plugin multilenguaje
 * no se mezclan idiomas.
 *
 * @return string
 */
function clipnuvex_search_recs_key() {
	$parts = array(
		(int) clipnuvex_option( 'search_recs_count', 20 ),
		(string) clipnuvex_option( 'search_recs_orderby', 'populares' ),
		implode( ',', clipnuvex_search_post_types() ),
		clipnuvex_search_recs_pool_cap(),
		get_locale(),
	);
	return 'cnx_recs_' . md5( implode( '|', $parts ) );
}

/**
 * IDs del contenido recomendado para búsquedas sin resultados ("Quizás te
 * interese").
 *
 * PORTABILIDAD POR CONSTRUCCIÓN: ninguna consulta ordena por postmeta ni usa
 * OR/NOT EXISTS. Los JOIN de meta con ORDER BY sobre meta_value mueren en
 * silencio en algunos hostings (MAX_JOIN_SIZE, kill de queries lentas,
 * drop-ins db.php) y WP_Query devuelve vacío sin exponer el error — es lo que
 * ocultaba el bloque en producción mientras el entorno de test (SQLite)
 * funcionaba. El ranking por vistas se hace EN PHP sobre un pool acotado.
 *
 * CACHÉ AUTOCURABLE: resultado con items → transient 1 h. Resultado vacío →
 * sentinel 'none' 5 min (anti-estampida, nunca "pegajoso"). Un array VACÍO
 * encontrado en la caché (herencia de v1.4.2, incluidos transients migrados
 * sin fila _timeout_ que no expiran jamás) se borra y se recalcula al
 * momento. Las vistas NO invalidan el transient a propósito: el ranking
 * "populares" puede llevar hasta 1 h de retraso (si no, cada visualización
 * vaciaría la caché).
 *
 * @return int[]
 */
function clipnuvex_search_recommendations() {
	$key    = clipnuvex_search_recs_key();
	$cached = get_transient( $key );
	if ( is_array( $cached ) && ! empty( $cached ) ) {
		return array_map( 'intval', $cached );
	}
	if ( 'none' === $cached ) {
		return array(); // Vacío "fresco" (TTL corto): no re-consultar todavía.
	}
	if ( is_array( $cached ) ) {
		delete_transient( $key ); // Vacío heredado/inmortal: autocuración.
	}

	$count   = max( 2, min( 40, (int) clipnuvex_option( 'search_recs_count', 20 ) ) );
	$orderby = clipnuvex_option( 'search_recs_orderby', 'populares' );

	$base = array(
		'post_type'              => clipnuvex_search_post_types(),
		'post_status'            => 'publish',
		'fields'                 => 'ids',
		'no_found_rows'          => true,
		'ignore_sticky_posts'    => true,
		'update_post_term_cache' => false,
		'orderby'                => 'date',
		'order'                  => 'DESC',
	);

	$ids = array();

	if ( 'populares' === $orderby ) {
		// Pool: SOLO contenido que ya tiene vistas. Una única cláusula EXISTS y
		// ORDER BY sobre wp_posts.post_date (nunca sobre el meta) → SQL trivial
		// que funciona igual en MySQL 8, MariaDB y SQLite.
		$pool = ( new WP_Query(
			array_merge(
				$base,
				array(
					'posts_per_page' => clipnuvex_search_recs_pool_cap(),
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'     => '_clipnuvex_views',
							'compare' => 'EXISTS',
						),
					),
				)
			)
		) )->posts;
		$pool = array_map( 'intval', (array) $pool );

		if ( $pool ) {
			// Vistas con un SELECT dirigido (solo este meta; cast a int en PHP,
			// cero CAST() en SQL).
			global $wpdb;
			$placeholders = implode( ',', array_fill( 0, count( $pool ), '%d' ) );
			$rows         = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_clipnuvex_views' AND post_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$pool
				)
			);
			$views = array();
			foreach ( (array) $rows as $row ) {
				$views[ (int) $row->post_id ] = (int) $row->meta_value;
			}
			// Orden estable también en PHP 7.4 (usort no es estable hasta 8.0):
			// se decora con el índice del pool (date DESC) como desempate.
			$decorated = array();
			foreach ( $pool as $i => $id ) {
				$decorated[] = array(
					'id' => $id,
					'v'  => isset( $views[ $id ] ) ? $views[ $id ] : 0,
					'i'  => $i,
				);
			}
			usort(
				$decorated,
				function ( $a, $b ) {
					return ( $b['v'] <=> $a['v'] ) ?: ( $a['i'] <=> $b['i'] );
				}
			);
			$ids = array_slice( wp_list_pluck( $decorated, 'id' ), 0, $count );
		}
	}

	// Recientes (criterio "recientes", o relleno si "populares" no llega al count).
	if ( count( $ids ) < $count ) {
		$fill_args = array_merge( $base, array( 'posts_per_page' => $count - count( $ids ) ) );
		if ( $ids ) {
			$fill_args['post__not_in'] = $ids;
		}
		$fill = ( new WP_Query( $fill_args ) )->posts;
		$ids  = array_merge( $ids, array_map( 'intval', (array) $fill ) );
	}

	if ( $ids ) {
		set_transient( $key, $ids, HOUR_IN_SECONDS );
	} else {
		set_transient( $key, 'none', 5 * MINUTE_IN_SECONDS );
		do_action( 'clipnuvex_search_recs_empty', $key );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Clipnuvex: clipnuvex_search_recommendations() sin resultados (' . $key . ').' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	return $ids;
}

/**
 * Invalida la caché de recomendaciones al publicar/editar/borrar contenido de
 * los tipos incluidos en la búsqueda.
 *
 * @param int          $post_id ID del post.
 * @param WP_Post|null $post    Objeto post (deleted_post lo pasa; tras el
 *                              DELETE, get_post_type($id) ya no es fiable con
 *                              object cache persistente).
 */
function clipnuvex_flush_search_recs( $post_id, $post = null ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	$type = ( $post instanceof WP_Post ) ? $post->post_type : get_post_type( $post_id );
	if ( ! in_array( $type, clipnuvex_search_post_types(), true ) ) {
		return;
	}
	delete_transient( clipnuvex_search_recs_key() );
}
add_action( 'save_post', 'clipnuvex_flush_search_recs', 10, 2 );
add_action( 'deleted_post', 'clipnuvex_flush_search_recs', 10, 2 );

/**
 * Busca términos de taxonomía que coincidan con una consulta (para search.php y dropdown).
 *
 * @param string $search   Texto de búsqueda.
 * @param int    $limit    Límite de resultados.
 * @return array Array de WP_Term.
 */
function clipnuvex_search_terms( $search, $limit = 8 ) {
	if ( '' === trim( (string) $search ) ) {
		return array();
	}
	$terms = get_terms(
		array(
			'taxonomy'   => array( 'categoria_video', 'tag_video' ),
			'hide_empty' => false,
			'name__like' => $search,
			'number'     => $limit,
		)
	);
	return is_wp_error( $terms ) ? array() : $terms;
}

/**
 * Registra el endpoint REST del buscador en vivo.
 */
function clipnuvex_register_rest() {
	register_rest_route(
		'clipnuvex/v1',
		'/search',
		array(
			'methods'             => 'GET',
			'callback'            => 'clipnuvex_rest_search',
			'permission_callback' => '__return_true',
			'args'                => array(
				'q' => array(
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		)
	);

	register_rest_route(
		'clipnuvex/v1',
		'/view',
		array(
			'methods'             => 'POST',
			'callback'            => 'clipnuvex_rest_track_view',
			'permission_callback' => '__return_true',
			'args'                => array(
				'id' => array(
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'clipnuvex_register_rest' );

/**
 * IP del cliente para el cubo del throttle (NO para seguridad): tras un
 * CDN/proxy, REMOTE_ADDR es la IP del proxy y TODOS los visitantes
 * compartirían un único cupo — el desplegable se quedaba sin sugerencias en
 * sitios con tráfico. Se prefieren las cabeceras del proxy validadas.
 *
 * Filtro `clipnuvex_client_ip` ( $ip, $candidates ): un sitio tras un proxy de
 * confianza que ya fija REMOTE_ADDR (mod_remoteip, ngx_http_realip) puede
 * devolver `$_SERVER['REMOTE_ADDR']` para que las cabeceras no sean falsificables.
 *
 * @return string IP válida o 'anon'.
 */
function clipnuvex_client_ip() {
	$candidates = array();
	if ( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
		$candidates[] = (string) wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}
	if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$fwd          = explode( ',', (string) wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$candidates[] = trim( $fwd[0] );
	}
	if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
		$candidates[] = (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}
	$ip = 'anon';
	foreach ( $candidates as $candidate ) {
		if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
			$ip = $candidate;
			break;
		}
	}
	$ip = apply_filters( 'clipnuvex_client_ip', $ip, $candidates );
	return ( is_string( $ip ) && '' !== $ip ) ? $ip : 'anon';
}

/**
 * Throttle por cliente basado en transients. Devuelve true si se supera el límite.
 *
 * @param string $bucket Identificador del recurso.
 * @param int    $max    Máximo de peticiones.
 * @param int    $window Ventana en segundos.
 * @return bool
 */
function clipnuvex_rest_is_throttled( $bucket, $max = 30, $window = 10 ) {
	$key = 'cnx_thr_' . $bucket . '_' . md5( clipnuvex_client_ip() );
	$n   = (int) get_transient( $key );
	if ( $n >= $max ) {
		return true;
	}
	set_transient( $key, $n + 1, $window );
	return false;
}

/**
 * Endpoint REST: cuenta una visualización de vídeo (atómico, con throttle).
 *
 * @param WP_REST_Request $request Petición.
 * @return WP_REST_Response
 */
function clipnuvex_rest_track_view( $request ) {
	$id = absint( $request->get_param( 'id' ) );
	if ( clipnuvex_rest_is_throttled( 'view', 20, 60 ) ) {
		return rest_ensure_response( array( 'ok' => false ) );
	}
	$ok = clipnuvex_track_view( $id );
	return rest_ensure_response( array( 'ok' => (bool) $ok ) );
}

/**
 * Respuesta del buscador en vivo: categorías + vídeos coincidentes.
 *
 * @param WP_REST_Request $request Petición.
 * @return WP_REST_Response
 */
function clipnuvex_rest_search( $request ) {
	$q = trim( (string) $request->get_param( 'q' ) );
	$out = array(
		'categories' => array(),
		'videos'     => array(),
	);

	if ( strlen( $q ) < 2 ) {
		return clipnuvex_rest_search_response( $out );
	}

	// Throttle por cliente (evita abuso del LIKE de búsqueda). Aun recortada,
	// la respuesta lleva sugerencias si ya están calculadas (ruta barata):
	// el desplegable nunca se queda en un "Sin resultados" seco.
	if ( clipnuvex_rest_is_throttled( 'search', 30, 10 ) ) {
		return clipnuvex_rest_search_response( clipnuvex_rest_search_suggestions( $out, true ) );
	}

	// Caché corta de resultados por término normalizado. La clave incluye los
	// tipos activos (cambiar la cobertura no sirve resultados obsoletos) y la
	// VERSIÓN del theme (cada actualización ignora respuestas con forma vieja).
	$cnx_types = clipnuvex_search_post_types();
	$cache_key = 'cnx_search_' . md5( CLIPNUVEX_VERSION . '|' . strtolower( $q ) . '|' . implode( ',', $cnx_types ) );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return clipnuvex_rest_search_response( clipnuvex_rest_search_suggestions( (array) $cached ) );
	}

	// Categorías.
	foreach ( clipnuvex_search_terms( $q, 5 ) as $term ) {
		$link = get_term_link( $term );
		$out['categories'][] = array(
			'name'  => $term->name,
			'url'   => is_wp_error( $link ) ? '' : $link,
			'count' => (int) $term->count,
		);
	}

	// Contenido (tipos configurables desde el panel; por defecto solo vídeos).
	$videos = new WP_Query(
		array(
			'post_type'           => $cnx_types,
			'posts_per_page'      => 6,
			's'                   => $q,
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		)
	);
	while ( $videos->have_posts() ) {
		$videos->the_post();
		$data = clipnuvex_get_video_data( get_the_ID() );
		$out['videos'][] = array(
			'title'    => $data['title'],
			'url'      => $data['permalink'],
			'thumb'    => get_the_post_thumbnail_url( get_the_ID(), 'clipnuvex-poster-sm' ),
			'category' => $data['category'],
			'year'     => $data['year'],
		);
	}
	wp_reset_postdata();

	set_transient( $cache_key, $out, 5 * MINUTE_IN_SECONDS );

	return clipnuvex_rest_search_response( clipnuvex_rest_search_suggestions( $out ) );
}

/**
 * Envuelve la respuesta del buscador en vivo con cabeceras anti-caché: sin
 * ellas, un CDN/LiteSpeed puede congelar la respuesta de /wp-json y servir
 * el desplegable con datos viejos (o sin sugerencias) durante horas.
 *
 * @param array $data Datos de la respuesta.
 * @return WP_REST_Response
 */
function clipnuvex_rest_search_response( $data ) {
	$response = rest_ensure_response( $data );
	$response->header( 'Cache-Control', 'no-cache, no-store, must-revalidate' );
	return $response;
}

/**
 * Añade sugerencias ("Quizás te interese") a la respuesta del buscador en
 * vivo cuando NO hay coincidencias, para que el desplegable no sea un
 * callejón sin salida.
 *
 * - El toggle `show_search_recs` se respeta EN SERVIDOR (con él apagado no se
 *   calcula ni se expone nada).
 * - Se aplica FUERA del transient por-query (cnx_search_*): las
 *   recomendaciones ya tienen su propia caché de 1 h, así no se duplica el
 *   payload en cada término basura cacheado ni una respuesta vacía anterior
 *   al deploy puede ocultarlas.
 *
 * @param array $out   Respuesta base (categories, videos).
 * @param bool  $cheap Ruta barata (throttle): solo sirve sugerencias si ya
 *                     están en el transient; nunca recalcula.
 * @return array Respuesta con `suggestions` si procede.
 */
function clipnuvex_rest_search_suggestions( $out, $cheap = false ) {
	if ( ! empty( $out['categories'] ) || ! empty( $out['videos'] ) ) {
		return $out;
	}
	if ( ! clipnuvex_is_on( 'show_search_recs' ) ) {
		return $out;
	}

	$limit = max( 2, min( 8, (int) clipnuvex_option( 'search_recs_dropdown_count', 6 ) ) );

	if ( $cheap ) {
		// Ruta del throttle: solo el transient ya calculado, sin recalcular nada.
		$cached = get_transient( clipnuvex_search_recs_key() );
		$ids    = ( is_array( $cached ) && ! empty( $cached ) ) ? array_slice( array_map( 'intval', $cached ), 0, $limit ) : array();
	} else {
		$ids = array_slice( clipnuvex_search_recommendations(), 0, $limit );
	}
	if ( ! $ids ) {
		return $out;
	}
	_prime_post_caches( $ids, false, true );

	$out['suggestions'] = array();
	foreach ( $ids as $id ) {
		$data = clipnuvex_get_video_data( $id );
		if ( empty( $data ) ) {
			continue;
		}
		$out['suggestions'][] = array(
			'title'    => $data['title'],
			'url'      => $data['permalink'],
			'thumb'    => get_the_post_thumbnail_url( $id, 'clipnuvex-poster-sm' ),
			'category' => $data['category'],
			'year'     => $data['year'],
		);
	}
	return $out;
}
