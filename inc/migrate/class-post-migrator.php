<?php
/**
 * Migración masiva y REVERSIBLE de entradas (post_type `post`) a vídeos (`video`).
 *
 * Pensada para sitios que vienen de otro theme con miles de vídeos publicados
 * como entradas. Convierte cada entrada EN SU SITIO (mismo ID, mismo slug,
 * mismas fechas, autor, estado, imagen destacada, metadatos, comentarios y
 * revisiones): solo cambia `post_type`, se mapean las taxonomías
 * (category → categoria_video, post_tag → tag_video) y se detecta la fuente del
 * vídeo (campos del theme anterior y/o contenido). Todo lo que cambia queda
 * registrado por entrada en `_clipnuvex_migrated_data` para poder revertir al
 * estado exacto.
 *
 * Reglas que NO se pueden romper (verificadas contra el core, ver CLAUDE.md):
 * - El slug NUNCA se renombra: durante wp_update_post se engancha
 *   `pre_wp_unique_post_slug` devolviendo el slug actual (ni el core ni
 *   clipnuvex_root_unique_slug pueden añadir -2). Las colisiones se OMITEN.
 * - Metas y contenido se escriben con wp_slash() (update_post_meta y
 *   wp_update_post des-escapan): sin eso se pierden barras invertidas.
 * - set_post_type() ANTES de tocar términos (el recuento cuenta por tipo);
 *   wp_defer_term_counting() durante el lote y false al final.
 * - Una sola wp_update_post() por entrada, y NUNCA wp_suspend_cache_invalidation().
 * - post_modified se conserva (SEO: lastmod) restaurándolo tras wp_update_post.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrador de entradas a vídeos.
 */
class Clipnuvex_Post_Migrator {

	const META_FLAG     = '_clipnuvex_migrated';
	const META_DATA     = '_clipnuvex_migrated_data';
	const META_PATH     = '_clipnuvex_migrated_path';
	const META_NOSOURCE = '_clipnuvex_migrated_nosource';
	const TERM_META     = 'clipnuvex_migrated_to';
	const OPTION_STATE  = 'clipnuvex_migrate_state';
	const OPTION_REPORT = 'clipnuvex_migrate_report';
	const OPTION_LOCK   = 'clipnuvex_migrate_lock';
	const LOCK_TTL      = 300;
	const REPORT_MAX    = 10000;

	/**
	 * Opciones normalizadas de la ejecución.
	 *
	 * @var array
	 */
	private $opts = array();

	/**
	 * Caché de mapeo de términos por petición: "taxonomía:id" => id destino.
	 *
	 * @var array
	 */
	private $term_cache = array();

	/**
	 * Slug protegido durante wp_update_post (filtro pre_wp_unique_post_slug).
	 *
	 * @var string
	 */
	private $keep_slug = '';

	/**
	 * Constructor.
	 *
	 * @param array $opts Opciones (se normalizan).
	 */
	public function __construct( $opts = array() ) {
		$this->opts = self::normalize( $opts );
	}

	/* ------------------------------------------------------------------ */
	/* Opciones                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Opciones por defecto.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'statuses'         => array( 'publish' ),
			'categories'       => array(),
			'source_meta'      => array(),
			'detect_content'   => true,
			'strip_embed'      => true,
			'skip_nosource'    => false,
			'include_password' => false,
			'map'              => array(
				'quality'  => '',
				'language' => '',
				'year'     => '',
				'duration' => '',
				'views'    => '',
				'featured' => '',
			),
			'batch'            => 25,
		);
	}

	/**
	 * Normaliza y sanea un array de opciones (del formulario, del JSON del AJAX
	 * o de los flags de WP-CLI).
	 *
	 * @param array $raw Opciones crudas.
	 * @return array
	 */
	public static function normalize( $raw ) {
		$d   = self::defaults();
		$raw = is_array( $raw ) ? $raw : array();
		$out = $d;

		$allowed_statuses = array( 'publish', 'draft', 'pending', 'private', 'future' );
		$statuses         = isset( $raw['statuses'] ) ? (array) $raw['statuses'] : $d['statuses'];
		$statuses         = array_values( array_intersect( array_map( 'sanitize_key', $statuses ), $allowed_statuses ) );
		$out['statuses']  = $statuses ? $statuses : array( 'publish' );

		$out['categories'] = isset( $raw['categories'] ) ? array_values( array_filter( array_map( 'absint', (array) $raw['categories'] ) ) ) : array();

		$meta = isset( $raw['source_meta'] ) ? $raw['source_meta'] : array();
		if ( is_string( $meta ) ) {
			$meta = explode( ',', $meta );
		}
		$clean_meta = array();
		foreach ( (array) $meta as $key ) {
			$key = preg_replace( '/[^A-Za-z0-9_\-]/', '', trim( (string) $key ) );
			if ( '' !== $key ) {
				$clean_meta[] = $key;
			}
		}
		$out['source_meta'] = array_values( array_unique( $clean_meta ) );

		foreach ( array( 'detect_content', 'strip_embed', 'skip_nosource', 'include_password' ) as $flag ) {
			$out[ $flag ] = isset( $raw[ $flag ] ) ? (bool) $raw[ $flag ] : $d[ $flag ];
		}

		$map = isset( $raw['map'] ) && is_array( $raw['map'] ) ? $raw['map'] : array();
		foreach ( $d['map'] as $field => $unused ) {
			$out['map'][ $field ] = isset( $map[ $field ] ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', trim( (string) $map[ $field ] ) ) : '';
		}

		$batch        = isset( $raw['batch'] ) ? (int) $raw['batch'] : $d['batch'];
		$out['batch'] = max( 5, min( 500, $batch ) );

		return $out;
	}

	/**
	 * Opciones actuales.
	 *
	 * @return array
	 */
	public function get_opts() {
		return $this->opts;
	}

	/* ------------------------------------------------------------------ */
	/* Estado, informe y cerrojo                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * ¿El sitio ha usado alguna vez la migración? Activa las redirecciones y
	 * las vistas admin (coste: una option autoloaded).
	 *
	 * @return bool
	 */
	public static function is_active() {
		return (bool) get_option( self::OPTION_STATE );
	}

	/**
	 * Estado persistente (array pequeño, autoloaded).
	 *
	 * @return array
	 */
	public static function get_state() {
		$s = get_option( self::OPTION_STATE );
		return is_array( $s ) ? $s : array();
	}

	/**
	 * Guarda el estado.
	 *
	 * @param array $state Estado.
	 */
	public static function save_state( $state ) {
		update_option( self::OPTION_STATE, $state );
	}

	/**
	 * Filas del informe (option NO autoloaded, acotada a REPORT_MAX).
	 *
	 * @return array
	 */
	public static function get_report() {
		$r = get_option( self::OPTION_REPORT );
		return is_array( $r ) ? $r : array();
	}

	/**
	 * Añade filas al informe.
	 *
	 * @param array $rows Filas.
	 */
	public static function append_report( $rows ) {
		if ( empty( $rows ) ) {
			return;
		}
		$report = self::get_report();
		if ( count( $report ) >= self::REPORT_MAX ) {
			return;
		}
		$report = array_merge( $report, array_slice( array_values( $rows ), 0, self::REPORT_MAX - count( $report ) ) );
		if ( false === get_option( self::OPTION_REPORT ) ) {
			add_option( self::OPTION_REPORT, $report, '', false );
		} else {
			update_option( self::OPTION_REPORT, $report, false );
		}
	}

	/**
	 * Vacía el informe.
	 */
	public static function clear_report() {
		delete_option( self::OPTION_REPORT );
	}

	/**
	 * Cerrojo atómico (add_option falla si existe). Un cerrojo caducado
	 * (LOCK_TTL) se sustituye; el mismo propietario siempre puede renovarlo.
	 *
	 * @param string $owner Identificador ('ajax', 'cli').
	 * @return bool
	 */
	public static function acquire_lock( $owner ) {
		$now  = time();
		$lock = array( 'owner' => (string) $owner, 'time' => $now );
		if ( add_option( self::OPTION_LOCK, $lock, '', false ) ) {
			return true;
		}
		$current = get_option( self::OPTION_LOCK );
		if ( is_array( $current ) && ( $now - (int) $current['time'] ) < self::LOCK_TTL && (string) $current['owner'] !== (string) $owner ) {
			return false;
		}
		update_option( self::OPTION_LOCK, $lock, false );
		return true;
	}

	/**
	 * Renueva el cerrojo.
	 *
	 * @param string $owner Propietario.
	 */
	public static function renew_lock( $owner ) {
		update_option( self::OPTION_LOCK, array( 'owner' => (string) $owner, 'time' => time() ), false );
	}

	/**
	 * Libera el cerrojo.
	 */
	public static function release_lock() {
		delete_option( self::OPTION_LOCK );
	}

	/**
	 * ¿Hay un plugin multilenguaje? La migración no es segura con WPML/Polylang
	 * (guardan el tipo de objeto y filtran las consultas por idioma).
	 *
	 * @return bool
	 */
	public static function blocked_by_multilang() {
		return function_exists( 'clipnuvex_has_multilang_plugin' ) && clipnuvex_has_multilang_plugin();
	}

	/* ------------------------------------------------------------------ */
	/* Candidatos                                                         */
	/* ------------------------------------------------------------------ */

	/**
	 * SQL WHERE de las entradas candidatas según las opciones.
	 *
	 * @return string Fragmento (sin WHERE) ya preparado.
	 */
	private function candidates_where() {
		global $wpdb;
		$statuses = "'" . implode( "','", array_map( 'esc_sql', $this->opts['statuses'] ) ) . "'";
		$where    = "p.post_type = 'post' AND p.post_status IN ($statuses)";
		if ( ! $this->opts['include_password'] ) {
			$where .= " AND p.post_password = ''";
		}
		if ( $this->opts['categories'] ) {
			$ids    = implode( ',', array_map( 'intval', $this->opts['categories'] ) );
			$where .= " AND p.ID IN (SELECT tr.object_id FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.taxonomy = 'category' AND tt.term_id IN ($ids))";
		}
		return $where;
	}

	/**
	 * Total de entradas candidatas.
	 *
	 * @return int
	 */
	public function count_candidates() {
		global $wpdb;
		$where = $this->candidates_where();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE $where" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * IDs candidatos a partir de un ID (recorrido ascendente, reanudable).
	 *
	 * @param int $after_id Último ID procesado.
	 * @param int $limit    Tamaño del lote.
	 * @return int[]
	 */
	public function candidates( $after_id, $limit ) {
		global $wpdb;
		$where = $this->candidates_where();
		return array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT p.ID FROM {$wpdb->posts} p WHERE $where AND p.ID > %d ORDER BY p.ID ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					(int) $after_id,
					(int) $limit
				)
			)
		);
	}

	/**
	 * Total de vídeos migrados (para revertir).
	 *
	 * @return int
	 */
	public static function count_migrated() {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_type = 'video'",
				self::META_FLAG
			)
		);
	}

	/**
	 * IDs de vídeos migrados a partir de un ID.
	 *
	 * @param int   $after_id Último ID.
	 * @param int   $limit    Lote.
	 * @param int[] $only     Restringir a estos IDs (opcional).
	 * @return int[]
	 */
	public static function migrated_ids( $after_id, $limit, $only = array() ) {
		global $wpdb;
		$extra = '';
		if ( $only ) {
			$extra = ' AND p.ID IN (' . implode( ',', array_map( 'intval', $only ) ) . ')';
		}
		return array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_type = 'video' AND p.ID > %d $extra ORDER BY p.ID ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					self::META_FLAG,
					(int) $after_id,
					(int) $limit
				)
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Detección de la fuente                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Hosts de vídeo reconocidos para URLs sueltas en el contenido.
	 *
	 * @return string[]
	 */
	public static function video_hosts() {
		return (array) apply_filters(
			'clipnuvex_migrate_video_hosts',
			array(
				'youtube.com', 'youtu.be', 'youtube-nocookie.com', 'vimeo.com', 'dailymotion.com', 'dai.ly', 'ok.ru', 'twitch.tv',
				'streamable.com', 'facebook.com', 'fb.watch', 'drive.google.com', 'rumble.com', 'bitchute.com', 'odysee.com',
				'archive.org', 'wistia.com', 'wistia.net', 'vk.com', 'mega.nz', 'streamtape.com', 'doodstream.com', 'dood.watch',
				'mixdrop.co', 'filemoon.sx', 'voe.sx', 'uqload.com', 'upstream.to', 'vidmoly.to', 'mp4upload.com', 'sendvid.com',
			)
		);
	}

	/**
	 * Normaliza URLs "de página" de proveedores conocidos a su forma embebible
	 * (una URL de watch de YouTube dentro de un iframe no reproduce).
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function normalize_provider_url( $url ) {
		$url = trim( (string) $url );
		if ( preg_match( '#^https?://(?:www\.|m\.)?youtube\.com/watch\?(?:.*&)?v=([A-Za-z0-9_-]{6,})#i', $url, $m )
			|| preg_match( '#^https?://youtu\.be/([A-Za-z0-9_-]{6,})#i', $url, $m )
			|| preg_match( '#^https?://(?:www\.)?youtube\.com/shorts/([A-Za-z0-9_-]{6,})#i', $url, $m ) ) {
			return 'https://www.youtube.com/embed/' . $m[1];
		}
		if ( preg_match( '#^https?://(?:www\.)?vimeo\.com/(\d+)#i', $url, $m ) ) {
			return 'https://player.vimeo.com/video/' . $m[1];
		}
		if ( preg_match( '#^https?://(?:www\.)?dailymotion\.com/video/([A-Za-z0-9]+)#i', $url, $m ) ) {
			return 'https://www.dailymotion.com/embed/video/' . $m[1];
		}
		if ( preg_match( '#^https?://dai\.ly/([A-Za-z0-9]+)#i', $url, $m ) ) {
			return 'https://www.dailymotion.com/embed/video/' . $m[1];
		}
		if ( preg_match( '#^https?://(?:www\.)?ok\.ru/video/(\d+)#i', $url, $m ) ) {
			return 'https://ok.ru/videoembed/' . $m[1];
		}
		if ( preg_match( '#^https?://(?:www\.)?streamable\.com/([A-Za-z0-9]+)#i', $url, $m ) && false === strpos( $url, '/e/' ) ) {
			return 'https://streamable.com/e/' . $m[1];
		}
		if ( preg_match( '#^https?://drive\.google\.com/file/d/([A-Za-z0-9_-]+)#i', $url, $m ) ) {
			return 'https://drive.google.com/file/d/' . $m[1] . '/preview';
		}
		if ( preg_match( '#^https?://(?:www\.)?rumble\.com/embed/#i', $url ) ) {
			return $url;
		}
		return $url;
	}

	/**
	 * Detecta la fuente del vídeo de una entrada SIN escribir nada.
	 *
	 * Orden: campos personalizados indicados → contenido (bloque wp:embed,
	 * <iframe>, [embed]URL[/embed], [shortcode] con src/url, URL de proveedor
	 * conocido en línea propia). Devuelve las líneas ya saneadas con el mismo
	 * parser del meta box y el fragmento del contenido a retirar (si procede).
	 *
	 * @param WP_Post $post Entrada.
	 * @return array ['lines' => string[], 'type' => string, 'fragment' => string]
	 */
	public function detect_source( $post ) {
		$result = array( 'lines' => array(), 'type' => 'none', 'fragment' => '' );

		// 1) Campos personalizados del theme anterior.
		foreach ( $this->opts['source_meta'] as $key ) {
			$value = get_post_meta( $post->ID, $key, true );
			if ( is_array( $value ) || '' === trim( (string) $value ) ) {
				continue;
			}
			$value = (string) $value;
			// Un valor de un solo campo puede traer varias líneas (varios servidores).
			$lines = preg_split( '/\r\n|\r|\n/', $value );
			foreach ( $lines as $line ) {
				$line   = trim( $line );
				$parsed = clipnuvex_parse_embed_line( self::normalize_provider_url( $line ) );
				if ( null === $parsed && false === strpos( $line, '|' ) ) {
					// URL de página (watch) dentro de un iframe o texto: normalizar.
					$src = clipnuvex_extract_embed_src( $line );
					if ( $src ) {
						$parsed = clipnuvex_parse_embed_line( self::normalize_provider_url( $src ) );
					}
				}
				if ( null !== $parsed ) {
					$result['lines'][] = ( 'shortcode' === $parsed['type'] ) ? $parsed['code'] : $parsed['url'];
				}
			}
		}
		if ( $result['lines'] ) {
			$result['type'] = 'meta';
			return $result;
		}

		if ( ! $this->opts['detect_content'] ) {
			return $result;
		}
		$content = (string) $post->post_content;
		if ( '' === trim( $content ) ) {
			return $result;
		}

		// 2a) Bloque de embed del editor de bloques.
		if ( preg_match( '#<!--\s*wp:embed\b.*?-->.*?<!--\s*/wp:embed\s*-->#s', $content, $m ) ) {
			$url = self::find_url_in( wp_strip_all_tags( $m[0] ) );
			if ( $url && self::is_video_host( $url ) ) {
				return $this->source_from_url( $url, $m[0], 'embed' );
			}
		}
		// 2b) <iframe src="…">
		if ( preg_match( '#<iframe\b[^>]*>(?:\s*</iframe>)?#is', $content, $m ) ) {
			$src = clipnuvex_extract_embed_src( $m[0] );
			if ( $src ) {
				return $this->source_from_url( $src, $m[0], 'iframe' );
			}
		}
		// 2c) [embed]URL[/embed]
		if ( preg_match( '#\[embed(?:\s[^\]]*)?\](.*?)\[/embed\]#is', $content, $m ) ) {
			$url = trim( wp_strip_all_tags( $m[1] ) );
			if ( $url ) {
				return $this->source_from_url( $url, $m[0], 'embed' );
			}
		}
		// 2d) [shortcode …] de vídeo (src/url/mp4/…): se prefiere la URL; si no la
		//     hay y el shortcode está registrado, se guarda el shortcode.
		if ( preg_match_all( '#\[([a-zA-Z0-9_-]+)(\s[^\]]*)?\](?:.*?\[/\1\])?#s', $content, $all, PREG_SET_ORDER ) ) {
			foreach ( $all as $sc ) {
				$tag = strtolower( $sc[1] );
				if ( in_array( $tag, array( 'caption', 'gallery', 'audio', 'playlist' ), true ) ) {
					continue;
				}
				$atts = isset( $sc[2] ) ? shortcode_parse_atts( trim( $sc[2] ) ) : array();
				$url  = '';
				if ( is_array( $atts ) ) {
					foreach ( array( 'src', 'url', 'mp4', 'm4v', 'webm', 'ogv', 'link', 'video', 'file', 'href' ) as $att ) {
						if ( ! empty( $atts[ $att ] ) && preg_match( '#^https?://#i', (string) $atts[ $att ] ) ) {
							$url = (string) $atts[ $att ];
							break;
						}
					}
				}
				if ( $url ) {
					return $this->source_from_url( $url, $sc[0], 'shortcode' );
				}
				if ( false !== strpos( $tag, 'video' ) || false !== strpos( $tag, 'player' ) || false !== strpos( $tag, 'embed' ) ) {
					if ( shortcode_exists( $tag ) ) {
						$parsed = clipnuvex_parse_embed_line( $sc[0] );
						if ( $parsed && 'shortcode' === $parsed['type'] ) {
							return array( 'lines' => array( $parsed['code'] ), 'type' => 'shortcode', 'fragment' => $sc[0] );
						}
					}
				}
			}
		}
		// 2e) URL de proveedor conocido en línea/párrafo propio.
		if ( preg_match_all( '#(?:<p>\s*)?(https?://[^\s<>"\']+)(?:\s*</p>)?#i', $content, $urls, PREG_SET_ORDER ) ) {
			foreach ( $urls as $u ) {
				$url = html_entity_decode( $u[1], ENT_QUOTES, 'UTF-8' );
				if ( self::is_video_host( $url ) ) {
					return $this->source_from_url( $url, $u[0], 'url' );
				}
			}
		}
		return $result;
	}

	/**
	 * Construye el resultado de detección a partir de una URL.
	 *
	 * @param string $url      URL detectada.
	 * @param string $fragment Fragmento del contenido que la contenía.
	 * @param string $type     Tipo detectado.
	 * @return array
	 */
	private function source_from_url( $url, $fragment, $type ) {
		$url    = self::normalize_provider_url( html_entity_decode( $url, ENT_QUOTES, 'UTF-8' ) );
		$parsed = clipnuvex_parse_embed_line( $url );
		if ( null === $parsed ) {
			return array( 'lines' => array(), 'type' => 'none', 'fragment' => '' );
		}
		return array( 'lines' => array( $parsed['url'] ), 'type' => $type, 'fragment' => (string) $fragment );
	}

	/**
	 * Primera URL http(s) dentro de un texto.
	 *
	 * @param string $text Texto.
	 * @return string
	 */
	private static function find_url_in( $text ) {
		return preg_match( '#https?://[^\s<>"\']+#i', (string) $text, $m ) ? $m[0] : '';
	}

	/**
	 * ¿El host de la URL es un proveedor de vídeo conocido?
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private static function is_video_host( $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( '' === $host ) {
			return false;
		}
		foreach ( self::video_hosts() as $known ) {
			$known = strtolower( trim( $known ) );
			if ( $host === $known || ( strlen( $host ) > strlen( $known ) && substr( $host, -strlen( '.' . $known ) ) === '.' . $known ) ) {
				return true;
			}
		}
		return false;
	}

	/* ------------------------------------------------------------------ */
	/* Análisis (simulación)                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Analiza una entrada sin escribir: colisión, contraseña y fuente.
	 *
	 * @param int $post_id ID.
	 * @return array Fila de informe.
	 */
	public function analyze( $post_id ) {
		$post = get_post( $post_id );
		$row  = array(
			'id'      => (int) $post_id,
			'title'   => $post ? $post->post_title : '',
			'status'  => 'ok',
			'source'  => 'none',
			'old_url' => $post ? get_permalink( $post ) : '',
			'new_url' => '',
			'note'    => '',
		);
		if ( ! $post || 'post' !== $post->post_type ) {
			$row['status'] = 'error';
			$row['note']   = 'not_post';
			return $row;
		}
		if ( '' !== $post->post_password && ! $this->opts['include_password'] ) {
			$row['status'] = 'skipped_password';
			return $row;
		}
		if ( '' === $post->post_name ) {
			$row['status'] = 'error';
			$row['note']   = 'empty_slug';
			return $row;
		}
		$owner = clipnuvex_slug_owner( $post->post_name, array( 'video', 'page', 'post' ), false, $post->ID );
		if ( $owner ) {
			$row['status'] = 'skipped_collision';
			$row['note']   = (string) $owner;
			return $row;
		}
		$src           = $this->detect_source( $post );
		$row['source'] = $src['type'];
		if ( 'none' === $src['type'] && $this->opts['skip_nosource'] ) {
			$row['status'] = 'skipped_nosource';
		}
		return $row;
	}

	/* ------------------------------------------------------------------ */
	/* Migración                                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * Prepara el entorno de un lote (recuento diferido, sin bumps por entrada).
	 */
	private function batch_begin() {
		wp_defer_term_counting( true );
		remove_action( 'save_post_video', 'clipnuvex_sitemap_bump_version' );
		// _publish_post_hook marca cada entrada publicada con _pingme/_encloseme
		// para que el cron envíe pingbacks y busque adjuntos: con miles de
		// entradas dispararía peticiones salientes a todos los enlaces del
		// contenido y dejaría metas que no existían. La conversión no es una
		// publicación nueva: se desengancha durante el lote.
		remove_action( 'publish_post', '_publish_post_hook', 5 );
		add_filter( 'pre_wp_unique_post_slug', array( $this, 'keep_slug_filter' ), 10, 2 );
	}

	/**
	 * Cierra un lote: recuentos, cachés del theme.
	 */
	private function batch_end() {
		remove_filter( 'pre_wp_unique_post_slug', array( $this, 'keep_slug_filter' ), 10 );
		add_action( 'publish_post', '_publish_post_hook', 5, 1 );
		wp_defer_term_counting( false );
		add_action( 'save_post_video', 'clipnuvex_sitemap_bump_version' );
		if ( function_exists( 'clipnuvex_cache_bump' ) ) {
			clipnuvex_cache_bump();
		}
		delete_transient( 'clipnuvex_root_slug_collisions' );
		$this->term_cache = array();
	}

	/**
	 * Filtro pre_wp_unique_post_slug: durante la conversión el slug NUNCA cambia.
	 *
	 * @param string|null $override Valor previo.
	 * @param string      $slug     Slug propuesto.
	 * @return string|null
	 */
	public function keep_slug_filter( $override, $slug ) {
		return ( '' !== $this->keep_slug ) ? $this->keep_slug : $override;
	}

	/**
	 * Invalidación final tras terminar toda la migración/reversión.
	 */
	public static function finish() {
		if ( function_exists( 'clipnuvex_sitemap_version' ) ) {
			update_option( 'clipnuvex_sitemap_ver', clipnuvex_sitemap_version() + 1, false );
		}
		if ( function_exists( 'clipnuvex_cache_bump' ) ) {
			clipnuvex_cache_bump();
		}
		delete_transient( 'clipnuvex_root_slug_collisions' );
	}

	/**
	 * Migra un lote de entradas.
	 *
	 * @param int $after_id Último ID procesado.
	 * @param int $limit    Tamaño del lote.
	 * @return array ['last_id' => int, 'rows' => array, 'done' => bool]
	 */
	public function migrate_batch( $after_id, $limit ) {
		$ids = $this->candidates( $after_id, $limit );
		if ( ! $ids ) {
			return array( 'last_id' => (int) $after_id, 'rows' => array(), 'done' => true );
		}
		$this->batch_begin();
		$rows = array();
		foreach ( $ids as $id ) {
			$rows[]   = $this->migrate_post( $id );
			$after_id = $id;
		}
		$this->batch_end();
		return array( 'last_id' => (int) $after_id, 'rows' => $rows, 'done' => count( $ids ) < $limit );
	}

	/**
	 * Convierte UNA entrada en vídeo (o la omite con motivo).
	 *
	 * @param int $post_id ID de la entrada.
	 * @return array Fila de informe.
	 */
	public function migrate_post( $post_id ) {
		$row = $this->analyze( $post_id );
		if ( 'ok' !== $row['status'] ) {
			return $row;
		}
		$post = get_post( $post_id );
		$src  = $this->detect_source( $post );
		$slug = $post->post_name;

		$data = array(
			'type'     => 'post',
			'path'     => (string) wp_parse_url( get_permalink( $post ), PHP_URL_PATH ),
			'cats'     => array_map( 'intval', (array) wp_get_object_terms( $post->ID, 'category', array( 'fields' => 'ids' ) ) ),
			'tags'     => array_map( 'intval', (array) wp_get_object_terms( $post->ID, 'post_tag', array( 'fields' => 'ids' ) ) ),
			'format'   => (string) get_post_format( $post->ID ),
			'sticky'   => is_sticky( $post->ID ) ? 1 : 0,
			'modified' => $post->post_modified,
			'modified_gmt' => $post->post_modified_gmt,
			'fragment' => '',
			'offset'   => -1,
			'hash'     => '',
			'meta_prev' => array(),
			'time'     => time(),
			'version'  => defined( 'CLIPNUVEX_VERSION' ) ? CLIPNUVEX_VERSION : '',
		);
		if ( is_wp_error( $data['cats'] ) ) {
			$data['cats'] = array();
		}

		// 1) Tipo (UPDATE directo + clean_post_cache) antes que los términos.
		set_post_type( $post->ID, 'video' );

		// 2) Taxonomías.
		$new_cats = array();
		foreach ( $data['cats'] as $cat_id ) {
			$target = $this->map_term( $cat_id, 'category', 'categoria_video' );
			if ( $target ) {
				$new_cats[] = $target;
			}
		}
		$new_tags = array();
		foreach ( $data['tags'] as $tag_id ) {
			$target = $this->map_term( $tag_id, 'post_tag', 'tag_video' );
			if ( $target ) {
				$new_tags[] = $target;
			}
		}
		if ( $new_cats ) {
			wp_set_object_terms( $post->ID, array_values( array_unique( $new_cats ) ), 'categoria_video' );
		}
		if ( $new_tags ) {
			wp_set_object_terms( $post->ID, array_values( array_unique( $new_tags ) ), 'tag_video' );
		}
		wp_delete_object_term_relationships( $post->ID, array( 'category', 'post_tag' ) );
		if ( $data['sticky'] ) {
			unstick_post( $post->ID );
		}

		// 3) Fuente y metas del theme (recordando el valor previo para revertir).
		$new_content = null;
		if ( $src['lines'] ) {
			$this->set_meta_tracked( $post->ID, '_clipnuvex_embed_url', clipnuvex_sanitize_embed_urls( implode( "\n", $src['lines'] ) ), $data );
			$this->set_meta_tracked( $post->ID, '_clipnuvex_fuente', 'embed', $data );
			if ( $this->opts['strip_embed'] && '' !== $src['fragment'] ) {
				$pos = strpos( $post->post_content, $src['fragment'] );
				if ( false !== $pos ) {
					$new_content      = substr_replace( $post->post_content, '', $pos, strlen( $src['fragment'] ) );
					$data['fragment'] = $src['fragment'];
					$data['offset']   = $pos;
					$data['hash']     = md5( $new_content );
				}
			}
		} else {
			update_post_meta( $post->ID, self::META_NOSOURCE, '1' );
		}
		$this->apply_meta_map( $post->ID, $data );

		// 4) Registro para revertir (con wp_slash: update_post_meta des-escapa).
		update_post_meta( $post->ID, self::META_DATA, wp_slash( $data ) );

		// 5) Una sola wp_update_post: dispara save_post (SEO, cachés, hooks del
		//    theme) sin renombrar el slug (pre_wp_unique_post_slug) y sin tocar
		//    post_modified (se restaura justo después).
		$args = array(
			'ID'        => $post->ID,
			'post_name' => $slug,
			'post_type' => 'video',
		);
		if ( null !== $new_content ) {
			$args['post_content'] = wp_slash( $new_content );
		}
		$this->keep_slug = $slug;
		$updated         = wp_update_post( $args, true );
		$this->keep_slug = '';
		if ( is_wp_error( $updated ) ) {
			$row['status'] = 'error';
			$row['note']   = $updated->get_error_message();
			// Se deja marcada igualmente: revertir la restaura.
		}
		$this->restore_modified( $post->ID, $data['modified'], $data['modified_gmt'] );

		update_post_meta( $post->ID, self::META_FLAG, '1' );
		$new_url  = get_permalink( $post->ID );
		$new_path = (string) wp_parse_url( $new_url, PHP_URL_PATH );
		if ( untrailingslashit( $new_path ) !== untrailingslashit( $data['path'] ) ) {
			update_post_meta( $post->ID, self::META_PATH, $data['path'] );
		}

		do_action( 'clipnuvex_post_migrated', $post->ID, 'post', $data );

		if ( 'error' !== $row['status'] ) {
			$row['status'] = 'migrated';
		}
		$row['source']  = $src['type'];
		$row['new_url'] = $new_url;
		return $row;
	}

	/**
	 * Escribe una meta del theme recordando su valor previo (null = no existía).
	 *
	 * @param int    $post_id ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Valor.
	 * @param array  $data    Registro de reversión (por referencia).
	 */
	private function set_meta_tracked( $post_id, $key, $value, &$data ) {
		if ( ! array_key_exists( $key, $data['meta_prev'] ) ) {
			$data['meta_prev'][ $key ] = metadata_exists( 'post', $post_id, $key ) ? get_post_meta( $post_id, $key, true ) : null;
		}
		update_post_meta( $post_id, $key, wp_slash( $value ) );
	}

	/**
	 * Mapeo opcional de metadatos del theme anterior a los campos del vídeo.
	 *
	 * @param int   $post_id ID.
	 * @param array $data    Registro (por referencia).
	 */
	private function apply_meta_map( $post_id, &$data ) {
		$fields = array(
			'quality'  => '_clipnuvex_calidad',
			'language' => '_clipnuvex_idioma',
			'year'     => '_clipnuvex_anio',
			'duration' => '_clipnuvex_duracion',
			'views'    => '_clipnuvex_views',
			'featured' => '_clipnuvex_destacado',
		);
		foreach ( $fields as $field => $meta_key ) {
			$old_key = $this->opts['map'][ $field ];
			if ( '' === $old_key ) {
				continue;
			}
			$raw = get_post_meta( $post_id, $old_key, true );
			if ( is_array( $raw ) || '' === trim( (string) $raw ) ) {
				continue;
			}
			$raw = trim( (string) $raw );
			switch ( $field ) {
				case 'year':
					if ( ! preg_match( '/(\d{4})/', $raw, $m ) ) {
						continue 2;
					}
					$value = $m[1];
					break;
				case 'duration':
				case 'views':
					$value = (string) absint( preg_replace( '/[^0-9]/', '', $raw ) );
					break;
				case 'featured':
					$value = in_array( strtolower( $raw ), array( '1', 'true', 'yes', 'si', 'sí', 'on' ), true ) ? '1' : '';
					break;
				default:
					$value = sanitize_text_field( $raw );
			}
			$this->set_meta_tracked( $post_id, $meta_key, $value, $data );
		}
	}

	/**
	 * Restaura post_modified tras wp_update_post (conserva el lastmod SEO).
	 *
	 * @param int    $post_id      ID.
	 * @param string $modified     Fecha local.
	 * @param string $modified_gmt Fecha GMT.
	 */
	private function restore_modified( $post_id, $modified, $modified_gmt ) {
		global $wpdb;
		if ( ! $modified || '0000-00-00 00:00:00' === $modified ) {
			return;
		}
		$wpdb->update(
			$wpdb->posts,
			array( 'post_modified' => $modified, 'post_modified_gmt' => $modified_gmt ),
			array( 'ID' => (int) $post_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		clean_post_cache( $post_id );
	}

	/**
	 * Mapea (o crea) el término destino de un término origen, respetando padres.
	 *
	 * @param int    $term_id  ID del término origen.
	 * @param string $from     Taxonomía origen.
	 * @param string $to       Taxonomía destino.
	 * @return int ID destino o 0.
	 */
	private function map_term( $term_id, $from, $to ) {
		$cache_key = $from . ':' . (int) $term_id;
		if ( isset( $this->term_cache[ $cache_key ] ) ) {
			return $this->term_cache[ $cache_key ];
		}
		$term = get_term( (int) $term_id, $from );
		if ( ! $term instanceof WP_Term ) {
			$this->term_cache[ $cache_key ] = 0;
			return 0;
		}
		// "Sin categoría" (categoría por defecto) no se traslada.
		if ( 'category' === $from && (int) get_option( 'default_category' ) === (int) $term->term_id ) {
			$this->term_cache[ $cache_key ] = 0;
			return 0;
		}
		$target = 0;
		$saved  = (int) get_term_meta( $term->term_id, self::TERM_META, true );
		if ( $saved && get_term( $saved, $to ) instanceof WP_Term ) {
			$target = $saved;
		}
		if ( ! $target ) {
			$existing = get_term_by( 'slug', $term->slug, $to );
			if ( ! $existing ) {
				$existing = get_term_by( 'name', $term->name, $to );
			}
			if ( $existing instanceof WP_Term ) {
				$target = (int) $existing->term_id;
			}
		}
		if ( ! $target ) {
			$parent = 0;
			if ( is_taxonomy_hierarchical( $to ) && $term->parent ) {
				$parent = $this->map_term( $term->parent, $from, $to );
			}
			$new = wp_insert_term(
				$term->name,
				$to,
				array(
					'slug'        => $term->slug,
					'description' => $term->description,
					'parent'      => $parent,
				)
			);
			if ( is_wp_error( $new ) ) {
				// Slug ocupado con otro nombre, etc.: reintenta sin slug fijo.
				$existing_id = (int) $new->get_error_data( 'term_exists' );
				$target      = $existing_id ? $existing_id : 0;
				if ( ! $target ) {
					$new = wp_insert_term( $term->name, $to, array( 'description' => $term->description, 'parent' => $parent ) );
					$target = is_wp_error( $new ) ? 0 : (int) $new['term_id'];
				}
			} else {
				$target = (int) $new['term_id'];
			}
		}
		if ( $target ) {
			update_term_meta( $term->term_id, self::TERM_META, $target );
		}
		$this->term_cache[ $cache_key ] = $target;
		return $target;
	}

	/* ------------------------------------------------------------------ */
	/* Reversión                                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * Revierte un lote de vídeos migrados.
	 *
	 * @param int   $after_id Último ID.
	 * @param int   $limit    Lote.
	 * @param int[] $only     IDs concretos (opcional).
	 * @return array ['last_id', 'rows', 'done']
	 */
	public function revert_batch( $after_id, $limit, $only = array() ) {
		$ids = self::migrated_ids( $after_id, $limit, $only );
		if ( ! $ids ) {
			return array( 'last_id' => (int) $after_id, 'rows' => array(), 'done' => true );
		}
		$this->batch_begin();
		$rows = array();
		foreach ( $ids as $id ) {
			$rows[]   = $this->revert_post( $id );
			$after_id = $id;
		}
		$this->batch_end();
		return array( 'last_id' => (int) $after_id, 'rows' => $rows, 'done' => count( $ids ) < $limit );
	}

	/**
	 * Devuelve UN vídeo migrado a su estado original de entrada.
	 *
	 * @param int $post_id ID.
	 * @return array Fila de informe.
	 */
	public function revert_post( $post_id ) {
		$post = get_post( $post_id );
		$row  = array(
			'id'      => (int) $post_id,
			'title'   => $post ? $post->post_title : '',
			'status'  => 'reverted',
			'source'  => '',
			'old_url' => $post ? get_permalink( $post ) : '',
			'new_url' => '',
			'note'    => '',
		);
		$data = $post ? get_post_meta( $post_id, self::META_DATA, true ) : null;
		if ( ! $post || 'video' !== $post->post_type || ! is_array( $data ) ) {
			$row['status'] = 'error';
			$row['note']   = 'not_migrated';
			return $row;
		}
		$slug = $post->post_name;

		set_post_type( $post->ID, 'post' );

		// Taxonomías: fuera las del vídeo, dentro las originales que sigan existiendo.
		wp_delete_object_term_relationships( $post->ID, array( 'categoria_video', 'tag_video' ) );
		$cats = array();
		foreach ( (array) $data['cats'] as $cid ) {
			if ( get_term( (int) $cid, 'category' ) instanceof WP_Term ) {
				$cats[] = (int) $cid;
			}
		}
		if ( ! $cats ) {
			$cats = array( (int) get_option( 'default_category' ) );
		}
		wp_set_object_terms( $post->ID, $cats, 'category' );
		$tags = array();
		foreach ( (array) $data['tags'] as $tid ) {
			if ( get_term( (int) $tid, 'post_tag' ) instanceof WP_Term ) {
				$tags[] = (int) $tid;
			}
		}
		wp_set_object_terms( $post->ID, $tags, 'post_tag' );
		if ( ! empty( $data['format'] ) ) {
			set_post_format( $post->ID, $data['format'] );
		}

		// Contenido: reinsertar el fragmento retirado.
		$new_content = null;
		if ( ! empty( $data['fragment'] ) ) {
			$current = (string) $post->post_content;
			if ( ! empty( $data['hash'] ) && md5( $current ) === $data['hash'] && isset( $data['offset'] ) && (int) $data['offset'] >= 0 ) {
				$new_content = substr_replace( $current, $data['fragment'], (int) $data['offset'], 0 );
			} else {
				$new_content = $data['fragment'] . "\n" . $current;
				$row['note'] = 'content_edited_after_migration';
			}
		}

		// Metas del theme: restaurar el valor previo (null = no existía → borrar).
		foreach ( (array) $data['meta_prev'] as $key => $prev ) {
			if ( null === $prev ) {
				delete_post_meta( $post->ID, $key );
			} else {
				update_post_meta( $post->ID, $key, wp_slash( $prev ) );
			}
		}
		delete_post_meta( $post->ID, self::META_NOSOURCE );
		delete_post_meta( $post->ID, self::META_PATH );
		delete_post_meta( $post->ID, self::META_DATA );
		delete_post_meta( $post->ID, self::META_FLAG );

		$args = array(
			'ID'        => $post->ID,
			'post_name' => $slug,
			'post_type' => 'post',
		);
		if ( null !== $new_content ) {
			$args['post_content'] = wp_slash( $new_content );
		}
		$this->keep_slug = $slug;
		$updated         = wp_update_post( $args, true );
		$this->keep_slug = '';
		if ( is_wp_error( $updated ) ) {
			$row['status'] = 'error';
			$row['note']   = $updated->get_error_message();
		}
		$this->restore_modified( $post->ID, isset( $data['modified'] ) ? $data['modified'] : '', isset( $data['modified_gmt'] ) ? $data['modified_gmt'] : '' );
		if ( ! empty( $data['sticky'] ) ) {
			stick_post( $post->ID );
		}

		do_action( 'clipnuvex_post_reverted', $post->ID, $data );

		$row['new_url'] = get_permalink( $post->ID );
		return $row;
	}

	/**
	 * Limpieza tras revertir todo: si ya no queda ningún vídeo migrado se
	 * retira el enlace de las categorías origen.
	 */
	public static function cleanup_if_empty() {
		if ( self::count_migrated() > 0 ) {
			return;
		}
		delete_metadata( 'term', 0, self::TERM_META, '', true );
	}
}
