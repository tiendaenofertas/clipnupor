<?php
/**
 * Modo Demo — importador/reversor integrado en el theme.
 *
 * Enfoque programático y 100% autocontenido: no depende de plugins externos ni
 * de archivos binarios. Genera pósters (2:3) y backdrops (16:9) de marca con GD,
 * crea el CPT `video`, taxonomías, página de categorías, menús y ajustes del
 * panel (option array `clipnuvex_options`). Todo el contenido queda marcado con
 * la meta `_clipnuvex_demo` para poder revertirlo sin tocar contenido real.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CLIPNUVEX_DIR . 'inc/demo/data.php';

/**
 * Importador del Modo Demo.
 */
class Clipnuvex_Demo_Importer {

	const FLAG_META   = '_clipnuvex_demo';
	const TERM_FLAG   = 'clipnuvex_demo';
	const OPTION_DONE = 'clipnuvex_demo_imported';
	const PRIVACY_OPT = 'clipnuvex_demo_privacy_page';

	/**
	 * Idioma del dataset ('es' | 'en'): el del SITIO (panel), nunca el del
	 * escritorio. El importador corre en admin, donde el filtro de locale del
	 * theme no actúa, por eso el contenido demo NUNCA pasa por gettext.
	 *
	 * @var string
	 */
	private $lang = 'en';

	/**
	 * Textos del dataset en ese idioma (clipnuvex_demo_texts).
	 *
	 * @var array
	 */
	private $texts = array();

	/**
	 * Texto del dataset con respaldo.
	 *
	 * @param string $key      Clave.
	 * @param string $fallback Respaldo.
	 * @return string
	 */
	private function txt( $key, $fallback = '' ) {
		return ( isset( $this->texts[ $key ] ) && is_string( $this->texts[ $key ] ) ) ? $this->texts[ $key ] : $fallback;
	}

	/**
	 * Ejecuta la importación completa.
	 *
	 * @return array ['ok'=>bool,'messages'=>[],'counts'=>[]].
	 */
	public function import() {
		$result = array( 'ok' => true, 'messages' => array(), 'counts' => array() );

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			$result['ok']         = false;
			$result['messages'][] = __( 'Permisos insuficientes.', 'clipnuvex' );
			return $result;
		}

		// Reimportar = revertir primero: así un cambio de idioma se aplica limpio
		// y nunca se duplica contenido (antes reimportar creaba 36 vídeos más).
		if ( get_option( self::OPTION_DONE ) ) {
			$this->revert();
		}

		$this->lang  = clipnuvex_demo_lang();
		$this->texts = clipnuvex_demo_texts( $this->lang );

		// Necesario para sideload/generación de adjuntos.
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$term_map = $this->import_terms();
		$result['counts']['categorias'] = count( $term_map );

		$tags = $this->import_tags();
		$result['counts']['tags'] = count( $tags );

		$videos = $this->import_videos( $term_map );
		$result['counts']['videos'] = $videos;

		$page_id = $this->ensure_categorias_page();
		$legal   = $this->import_pages();
		$result['counts']['paginas'] = count( $legal ) + ( $page_id ? 1 : 0 );

		$this->setup_menus( $page_id, $legal );
		$this->apply_options();
		$this->import_ads();
		$this->configure_reading();

		update_option( self::OPTION_DONE, time() );
		flush_rewrite_rules();

		$result['messages'][] = __( 'Contenido demo importado correctamente.', 'clipnuvex' );
		return $result;
	}

	/**
	 * Revierte (elimina) todo el contenido demo.
	 *
	 * @return array
	 */
	public function revert() {
		$result = array( 'ok' => true, 'messages' => array(), 'counts' => array() );

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			$result['ok']         = false;
			$result['messages'][] = __( 'Permisos insuficientes.', 'clipnuvex' );
			return $result;
		}

		// Borrar vídeos demo y sus adjuntos.
		$videos = get_posts(
			array(
				'post_type'      => 'video',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'meta_key'       => self::FLAG_META,
				'meta_value'     => '1',
			)
		);
		foreach ( $videos as $vid ) {
			$this->delete_post_attachments( $vid );
			wp_delete_post( $vid, true );
		}
		$result['counts']['videos'] = count( $videos );

		// Página de privacidad asignada por la demo → restaurar el valor anterior
		// ANTES de borrar las páginas: al borrar la página asignada, el core pone
		// la option a 0 y ya no se podría reconocer. Solo si sigue apuntando a la
		// de la demo (una elegida por el admin no se toca).
		$demo_privacy = (int) get_option( self::PRIVACY_OPT, 0 );
		if ( $demo_privacy && (int) get_option( 'wp_page_for_privacy_policy' ) === $demo_privacy ) {
			update_option( 'wp_page_for_privacy_policy', (int) get_option( self::PRIVACY_OPT . '_prev', 0 ) );
		}
		delete_option( self::PRIVACY_OPT );
		delete_option( self::PRIVACY_OPT . '_prev' );

		// Borrar páginas demo.
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'meta_key'       => self::FLAG_META,
				'meta_value'     => '1',
			)
		);
		foreach ( $pages as $pid ) {
			wp_delete_post( $pid, true );
		}
		$result['counts']['paginas'] = count( $pages );

		// Borrar términos demo (categorías + tags) y sus imágenes.
		$deleted_terms = 0;
		foreach ( array( 'categoria_video', 'tag_video' ) as $tax ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $tax,
					'hide_empty' => false,
					'meta_key'   => self::TERM_FLAG,
					'meta_value' => '1',
				)
			);
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$img = absint( get_term_meta( $term->term_id, 'clipnuvex_term_image', true ) );
					if ( $img ) {
						wp_delete_attachment( $img, true );
					}
					wp_delete_term( $term->term_id, $tax );
					$deleted_terms++;
				}
			}
		}
		$result['counts']['terminos'] = $deleted_terms;

		// Borrar menús demo.
		foreach ( array( 'Clipnuvex Principal', 'Clipnuvex Footer' ) as $menu_name ) {
			$menu = wp_get_nav_menu_object( $menu_name );
			if ( $menu ) {
				wp_delete_nav_menu( $menu->term_id );
			}
		}

		// Quitar los anuncios demo de los slots (option array clipnuvex_options):
		// SOLO los códigos que apunten a una imagen demo (attachment marcado con
		// _clipnuvex_demo). Un código real pegado por el admin no se toca. Los
		// slots limpiados vuelven a estar vacíos y, por tanto, colapsados.
		if ( function_exists( 'clipnuvex_ad_slots' ) ) {
			$demo_urls = array();
			$demo_att  = get_posts(
				array(
					'post_type'   => 'attachment',
					'numberposts' => -1,
					'post_status' => 'any',
					'meta_key'    => '_clipnuvex_demo', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'fields'      => 'ids',
				)
			);
			foreach ( $demo_att as $att_id ) {
				$url = wp_get_attachment_url( $att_id );
				if ( $url ) {
					$demo_urls[] = $url;
				}
			}
			$stored = get_option( 'clipnuvex_options', array() );
			if ( is_array( $stored ) && $demo_urls ) {
				$dirty = false;
				foreach ( array_keys( clipnuvex_ad_slots() ) as $slot ) {
					$key = 'ad_' . $slot;
					if ( empty( $stored[ $key ] ) || ! is_string( $stored[ $key ] ) ) {
						continue;
					}
					foreach ( $demo_urls as $url ) {
						if ( false !== strpos( $stored[ $key ], $url ) ) {
							unset( $stored[ $key ] );
							$dirty = true;
							break;
						}
					}
				}
				if ( $dirty ) {
					update_option( 'clipnuvex_options', $stored );
				}
			}
		}

		// Borrar adjuntos demo que pudieran quedar (imágenes de anuncio, sin post
		// padre, marcadas con la flag). Los pósters/backdrops ya se borraron arriba.
		$demo_atts = get_posts(
			array(
				'post_type'      => 'attachment',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'meta_key'       => self::FLAG_META,
				'meta_value'     => '1',
			)
		);
		foreach ( $demo_atts as $aid ) {
			wp_delete_attachment( $aid, true );
		}

		delete_option( self::OPTION_DONE );
		flush_rewrite_rules();

		$result['messages'][] = __( 'Contenido demo eliminado. Tu contenido real no se ha tocado.', 'clipnuvex' );
		return $result;
	}

	/* ------------------------------------------------------------------ */
	/* Importación de piezas                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Crea las categorías con descripción e imagen generada.
	 *
	 * @return array nombre => term_id.
	 */
	private function import_terms() {
		$map = array();
		foreach ( clipnuvex_demo_categories( $this->lang ) as $name => $info ) {
			$existing = term_exists( $name, 'categoria_video' );
			if ( $existing ) {
				$term_id = (int) $existing['term_id'];
			} else {
				$new = wp_insert_term( $name, 'categoria_video', array( 'description' => $info['desc'] ) );
				if ( is_wp_error( $new ) ) {
					continue;
				}
				$term_id = (int) $new['term_id'];
			}
			update_term_meta( $term_id, self::TERM_FLAG, '1' );

			// Imagen de término (backdrop 16:9).
			if ( ! get_term_meta( $term_id, 'clipnuvex_term_image', true ) ) {
				$att = $this->generate_image( $name, 1280, 720, $info['c1'], $info['c2'], 'backdrop' );
				if ( $att ) {
					update_term_meta( $term_id, 'clipnuvex_term_image', $att );
				}
			}
			$map[ $name ] = $term_id;
		}
		return $map;
	}

	/**
	 * Crea los tags demo.
	 *
	 * @return array
	 */
	private function import_tags() {
		$ids = array();
		foreach ( clipnuvex_demo_tags( $this->lang ) as $tag ) {
			$existing = term_exists( $tag, 'tag_video' );
			if ( $existing ) {
				$tid = (int) $existing['term_id'];
			} else {
				$new = wp_insert_term( $tag, 'tag_video' );
				if ( is_wp_error( $new ) ) {
					continue;
				}
				$tid = (int) $new['term_id'];
			}
			update_term_meta( $tid, self::TERM_FLAG, '1' );
			$ids[] = $tid;
		}
		return $ids;
	}

	/**
	 * Crea los vídeos demo con póster, meta y taxonomías.
	 *
	 * @param array $term_map nombre => term_id.
	 * @return int Número de vídeos creados.
	 */
	private function import_videos( $term_map ) {
		$titles  = clipnuvex_demo_titles( $this->lang );
		$cats    = clipnuvex_demo_categories( $this->lang );
		$embeds  = clipnuvex_demo_embeds();
		$tags    = clipnuvex_demo_tags( $this->lang );
		$langs   = ( isset( $this->texts['video_langs'] ) && is_array( $this->texts['video_langs'] ) && count( $this->texts['video_langs'] ) >= 2 )
			? array_values( $this->texts['video_langs'] )
			: array( 'English', 'Subtitled' );
		$tpl_content = $this->txt( 'video_content', '%1$s is one of the featured %2$s titles on Clipnuvex.' );
		$tpl_excerpt = $this->txt( 'video_excerpt', 'Watch %1$s online in %2$s. %3$s' );
		$created = 0;
		$idx     = 0;

		foreach ( $titles as $cat_name => $cat_titles ) {
			if ( ! isset( $term_map[ $cat_name ] ) ) {
				continue;
			}
			$info = $cats[ $cat_name ];
			foreach ( $cat_titles as $n => $title ) {
				$idx++;
				$year    = (string) wp_rand( 2019, 2025 );
				$quality = ( 0 === $idx % 3 ) ? '4K' : 'HD';
				$lang    = ( 0 === $idx % 2 ) ? $langs[0] : $langs[1];
				$embed   = $embeds[ $idx % count( $embeds ) ];

				$post_id = wp_insert_post(
					array(
						'post_type'    => 'video',
						'post_status'  => 'publish',
						'post_title'   => $title,
						// Textos del dataset en el idioma del sitio (sin gettext: ver $lang).
						'post_content' => sprintf( $tpl_content, $title, $cat_name ),
						'post_excerpt' => sprintf( $tpl_excerpt, $title, $quality, $info['desc'] ),
					),
					true
				);

				if ( is_wp_error( $post_id ) || ! $post_id ) {
					continue;
				}

				update_post_meta( $post_id, self::FLAG_META, '1' );
				update_post_meta( $post_id, '_clipnuvex_embed_url', $embed );
				update_post_meta( $post_id, '_clipnuvex_fuente', 'embed' );
				update_post_meta( $post_id, '_clipnuvex_calidad', $quality );
				update_post_meta( $post_id, '_clipnuvex_idioma', $lang );
				update_post_meta( $post_id, '_clipnuvex_anio', $year );
				update_post_meta( $post_id, '_clipnuvex_duracion', (string) wp_rand( 78, 142 ) );
				update_post_meta( $post_id, '_clipnuvex_destacado', ( 0 === $n ) ? '1' : '' );
				update_post_meta( $post_id, '_clipnuvex_views', (string) wp_rand( 50, 5000 ) );

				wp_set_object_terms( $post_id, array( (int) $term_map[ $cat_name ] ), 'categoria_video' );
				// 2 tags aleatorios.
				$pick = array( $tags[ $idx % count( $tags ) ], $tags[ ( $idx + 3 ) % count( $tags ) ] );
				wp_set_object_terms( $post_id, array_unique( $pick ), 'tag_video' );

				// Póster 2:3.
				$att = $this->generate_image( $title, 600, 900, $info['c1'], $info['c2'], 'poster', $cat_name );
				if ( $att ) {
					set_post_thumbnail( $post_id, $att );
					update_post_meta( $att, self::FLAG_META, '1' );
				}
				$created++;
			}
		}
		return $created;
	}

	/**
	 * Crea (si no existe) la página "Categorías" con su plantilla.
	 *
	 * @return int ID de la página.
	 */
	private function ensure_categorias_page() {
		// Misma lógica que la activación del theme (inc/setup.php). Solo se marca
		// como demo si la CREA el importador: la página que ya creó el theme al
		// activarse debe sobrevivir a "Revertir demo" (si no, la navegación caía
		// al archivo /video/ tras revertir).
		if ( ! function_exists( 'clipnuvex_ensure_categories_page' ) ) {
			return 0;
		}
		$existing = function_exists( 'clipnuvex_find_categories_page' ) ? clipnuvex_find_categories_page( false ) : null;
		if ( $existing instanceof WP_Post ) {
			return (int) $existing->ID;
		}
		$page_id = clipnuvex_ensure_categories_page();
		if ( $page_id ) {
			update_post_meta( $page_id, self::FLAG_META, '1' );
		}
		return (int) $page_id;
	}

	/**
	 * Páginas legales de ejemplo (Privacidad, Términos, Cookies, Contacto, DMCA)
	 * en el idioma del sitio, marcadas como demo (revertibles). Si ya existe una
	 * página real con ese slug se enlaza tal cual y NO se marca como demo. Si
	 * WordPress no tiene página de privacidad asignada, se asigna la de la demo
	 * (se recuerda en PRIVACY_OPT para desasignarla al revertir).
	 *
	 * @return array clave => ID de página.
	 */
	private function import_pages() {
		$ids     = array();
		$created = array();
		foreach ( clipnuvex_demo_pages( $this->lang ) as $key => $page ) {
			// Solo se reutiliza una página real PUBLICADA (un borrador, como el de
			// privacidad que crea WordPress, daría un enlace roto en el menú).
			$existing = get_page_by_path( $page['slug'], OBJECT, 'page' );
			if ( $existing instanceof WP_Post && 'page' === $existing->post_type && 'publish' === $existing->post_status ) {
				$ids[ $key ] = (int) $existing->ID;
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $page['title'],
					'post_name'    => $page['slug'],
					'post_content' => $page['content'],
				),
				true
			);
			if ( is_wp_error( $id ) || ! $id ) {
				continue;
			}
			update_post_meta( $id, self::FLAG_META, '1' );
			$ids[ $key ]     = (int) $id;
			$created[ $key ] = (int) $id;
		}

		// Segunda pasada: los textos son completos y traen marcadores que se
		// rellenan con los datos reales del sitio y los permalinks de las propias
		// páginas (enlaces cruzados). Solo en las páginas creadas por la demo.
		if ( $created ) {
			$email = sanitize_email( (string) get_option( 'admin_email' ) );
			$repl  = array(
				'{{site_name}}' => esc_html( get_bloginfo( 'name' ) ),
				'{{site_url}}'  => esc_url( home_url( '/' ) ),
				'{{email}}'     => esc_html( $email ),
				'{{date}}'      => esc_html( clipnuvex_demo_date( $this->lang ) ),
			);
			foreach ( array( 'privacy', 'terms', 'cookies', 'contact', 'dmca' ) as $k ) {
				$repl[ '{{' . $k . '_url}}' ] = esc_url( ! empty( $ids[ $k ] ) ? get_permalink( $ids[ $k ] ) : home_url( '/' ) );
			}
			foreach ( $created as $id ) {
				$post = get_post( $id );
				if ( $post instanceof WP_Post ) {
					wp_update_post(
						array(
							'ID'           => $id,
							'post_content' => strtr( $post->post_content, $repl ),
						)
					);
				}
			}
		}
		// Página de privacidad de WordPress: si no hay ninguna PUBLICADA asignada
		// (instalación nueva = borrador "Privacy Policy"), se asigna la de la demo
		// recordando el valor anterior para restaurarlo al revertir.
		if ( ! empty( $ids['privacy'] ) && '1' === get_post_meta( $ids['privacy'], self::FLAG_META, true ) ) {
			$current = (int) get_option( 'wp_page_for_privacy_policy' );
			if ( ! $current || 'publish' !== get_post_status( $current ) ) {
				update_option( self::PRIVACY_OPT . '_prev', $current, false );
				update_option( self::PRIVACY_OPT, (int) $ids['privacy'], false );
				update_option( 'wp_page_for_privacy_policy', (int) $ids['privacy'] );
			}
		}
		return $ids;
	}

	/**
	 * Crea los menús principal y de footer y los asigna a las ubicaciones.
	 * Etiquetas en el idioma del sitio (dataset, sin gettext) y menú de pie
	 * enlazando a las páginas legales reales (antes apuntaba a la portada).
	 *
	 * @param int   $page_id     ID de la página de categorías.
	 * @param array $legal_pages clave => ID de página legal (import_pages).
	 */
	private function setup_menus( $page_id, $legal_pages ) {
		$locations = get_theme_mod( 'nav_menu_locations', array() );
		$menu_txt  = ( isset( $this->texts['menu'] ) && is_array( $this->texts['menu'] ) ) ? $this->texts['menu'] : array();
		$label     = static function ( $key, $fallback ) use ( $menu_txt ) {
			return ( isset( $menu_txt[ $key ] ) && '' !== $menu_txt[ $key ] ) ? $menu_txt[ $key ] : $fallback;
		};

		// Menú principal.
		$primary = wp_get_nav_menu_object( 'Clipnuvex Principal' );
		if ( ! $primary ) {
			$menu_id = wp_create_nav_menu( 'Clipnuvex Principal' );
			if ( ! is_wp_error( $menu_id ) ) {
				wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => $label( 'home', 'Home' ), 'menu-item-url' => home_url( '/' ), 'menu-item-status' => 'publish' ) );
				wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => $label( 'browse', 'Browse' ), 'menu-item-url' => get_post_type_archive_link( 'video' ), 'menu-item-status' => 'publish' ) );
				if ( $page_id ) {
					wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => $label( 'categories', 'Categories' ), 'menu-item-object' => 'page', 'menu-item-object-id' => (int) $page_id, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
				}
				$locations['primary'] = $menu_id;
			}
		}

		// Menú footer: una entrada por página legal, en el orden del dataset.
		$footer = wp_get_nav_menu_object( 'Clipnuvex Footer' );
		if ( ! $footer ) {
			$footer_id = wp_create_nav_menu( 'Clipnuvex Footer' );
			if ( ! is_wp_error( $footer_id ) ) {
				foreach ( array_keys( clipnuvex_demo_pages( $this->lang ) ) as $key ) {
					if ( empty( $legal_pages[ $key ] ) ) {
						continue;
					}
					$pid = (int) $legal_pages[ $key ];
					wp_update_nav_menu_item(
						$footer_id,
						0,
						array(
							'menu-item-title'     => get_the_title( $pid ),
							'menu-item-object'    => 'page',
							'menu-item-object-id' => $pid,
							'menu-item-type'      => 'post_type',
							'menu-item-status'    => 'publish',
						)
					);
				}
				$locations['footer'] = $footer_id;
			}
		}

		set_theme_mod( 'nav_menu_locations', $locations );
	}

	/**
	 * Aplica los ajustes del prototipo en la única fuente de verdad: la option
	 * array `clipnuvex_options` (NUNCA theme_mods). Solo rellena claves que el
	 * admin no haya configurado ya: la demo no pisa la personalización real.
	 */
	private function apply_options() {
		// Sin `default_lang`: la demo respeta el idioma del theme (inglés por
		// defecto desde 1.5.5) aunque su contenido de muestra sea español.
		$defaults = array(
			'accent'      => '#7C5CFF',
			'accent_dark' => '#5B3CE0',
		);
		$stored  = get_option( 'clipnuvex_options', array() );
		$stored  = is_array( $stored ) ? $stored : array();
		$missing = array_diff_key( $defaults, $stored );
		if ( $missing ) {
			$this->merge_options( $missing );
		}
	}

	/**
	 * Fusiona pares id => valor dentro de la option array `clipnuvex_options`.
	 *
	 * @param array $pairs Pares opción => valor (ya saneados).
	 */
	private function merge_options( $pairs ) {
		$stored = get_option( 'clipnuvex_options', array() );
		$stored = is_array( $stored ) ? $stored : array();
		update_option( 'clipnuvex_options', array_merge( $stored, $pairs ) );
	}

	/**
	 * Inserta anuncios demo REALES (imágenes generadas) en cada slot, como
	 * código de anuncio editable/removible. Así la demo se ve poblada y, si el
	 * admin borra un slot desde el panel, el hueco se libera (slot vacío =
	 * colapsa). Se genera 1 imagen por formato y se reutiliza en sus slots.
	 */
	private function import_ads() {
		if ( ! function_exists( 'clipnuvex_ad_slots' ) || ! function_exists( 'imagecreatetruecolor' ) ) {
			return;
		}
		// Gradiente (c1,c2) + marca + titular por formato (réplica del prototipo);
		// titulares del dataset en el idioma del sitio (sin gettext).
		$heads     = ( isset( $this->texts['ad_headlines'] ) && is_array( $this->texts['ad_headlines'] ) ) ? $this->texts['ad_headlines'] : array();
		$head      = static function ( $format, $fallback ) use ( $heads ) {
			return ( isset( $heads[ $format ] ) && '' !== $heads[ $format ] ) ? $heads[ $format ] : $fallback;
		};
		$creatives = array(
			'leaderboard' => array( '#1b2c4d', '#3b82f6', 'AURIS', $head( 'leaderboard', 'Cinema sound in your living room.' ) ),
			'billboard'   => array( '#0e2a3b', '#2dd4bf', 'NOVA STUDIO', $head( 'billboard', 'Premiere your next story in 4K.' ) ),
			'rectangle'   => array( '#3a1228', '#d23a5e', 'VELVET', $head( 'rectangle', 'Style that shows.' ) ),
			'halfpage'    => array( '#241b3e', '#7c5cff', 'ATLAS VPN', $head( 'halfpage', 'Streaming without limits or buffering.' ) ),
			'skyscraper'  => array( '#10243a', '#22d3ee', 'PIXEL', $head( 'skyscraper', 'Capture every scene.' ) ),
			'mobile'      => array( '#241a12', '#caa15a', 'KAVA', $head( 'mobile', 'Your coffee, your pace.' ) ),
		);
		$stored = get_option( 'clipnuvex_options', array() );
		$stored = is_array( $stored ) ? $stored : array();
		$cache  = array();
		$pairs  = array();
		foreach ( clipnuvex_ad_slots() as $slot_id => $slot ) {
			// No pisar un código de anuncio real que el admin ya haya pegado.
			if ( ! empty( $stored[ 'ad_' . $slot_id ] ) ) {
				continue;
			}
			$format = isset( $creatives[ $slot['format'] ] ) ? $slot['format'] : 'leaderboard';
			if ( ! isset( $cache[ $format ] ) ) {
				$cache[ $format ] = $this->generate_ad_image( $format, $creatives[ $format ] );
			}
			$img = $cache[ $format ];
			if ( empty( $img['url'] ) ) {
				continue;
			}
			$code = sprintf(
				'<a href="%1$s" rel="nofollow noopener" aria-label="%2$s"><img src="%3$s" width="%4$d" height="%5$d" alt="%2$s" loading="lazy" style="display:block;width:100%%;max-width:%4$dpx;height:auto;border-radius:13px;"></a>',
				esc_url( home_url( '/' ) ),
				esc_attr( $this->txt( 'ad_alt', 'Sample ad' ) ),
				esc_url( $img['url'] ),
				(int) $img['w'],
				(int) $img['h']
			);
			// Misma regla de saneado que el guardado del panel.
			$pairs[ 'ad_' . $slot_id ] = function_exists( 'clipnuvex_ad_kses' ) ? clipnuvex_ad_kses( $code ) : wp_kses_post( $code );
		}
		if ( $pairs ) {
			$this->merge_options( $pairs );
		}
	}

	/**
	 * Genera una imagen de anuncio (banner) de marca con GD y la registra como
	 * adjunto demo. Devuelve ['url','w','h'] o [] si falla.
	 *
	 * @param string $format   Formato IAB.
	 * @param array  $creative  [c1, c2, marca, titular].
	 * @return array
	 */
	private function generate_ad_image( $format, $creative ) {
		$spec = clipnuvex_ad_format_spec( $format );
		$w    = (int) $spec['w'] ? (int) $spec['w'] : 728;
		$h    = (int) $spec['h'] ? (int) $spec['h'] : 90;
		list( $c1, $c2, $brand, $headline ) = $creative;

		$im = imagecreatetruecolor( $w, $h );
		list( $r1, $g1, $b1 ) = $this->hex_rgb( $c1 );
		list( $r2, $g2, $b2 ) = $this->hex_rgb( $c2 );
		// Gradiente diagonal (de izquierda-arriba a derecha-abajo).
		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x += 4 ) {
				$t   = ( $x / max( 1, $w ) + $y / max( 1, $h ) ) / 2;
				$r   = (int) ( $r1 + ( $r2 - $r1 ) * $t );
				$g   = (int) ( $g1 + ( $g2 - $g1 ) * $t );
				$b   = (int) ( $b1 + ( $b2 - $b1 ) * $t );
				$col = imagecolorallocate( $im, $r, $g, $b );
				imagefilledrectangle( $im, $x, $y, $x + 4, $y, $col );
			}
		}
		$white = imagecolorallocate( $im, 255, 255, 255 );
		$soft  = imagecolorallocatealpha( $im, 255, 255, 255, 60 );

		$pad      = max( 12, (int) ( $w * 0.04 ) );
		$is_tall  = $h >= $w; // halfpage/skyscraper → texto centrado.
		// Etiqueta "ANUNCIO" arriba-izquierda.
		imagestring( $im, 2, $pad, 10, 'ANUNCIO', $soft );
		// Medida arriba-derecha. La fuente bitmap de GD es ASCII: "×" → "x".
		$size_txt = str_replace( array( '×', "\xc3\x97" ), 'x', $spec['label'] );
		imagestring( $im, 2, $w - $pad - imagefontwidth( 2 ) * strlen( $size_txt ), 10, $size_txt, $soft );
		// Marca + titular.
		if ( $is_tall ) {
			$by = (int) ( $h * 0.42 );
			imagestring( $im, 5, $pad, $by, $this->ascii( $brand ), $white );
			$ty = $by + 26;
			foreach ( $this->wrap_text( $this->ascii( $headline ), 20 ) as $line ) {
				imagestring( $im, 3, $pad, $ty, $line, $white );
				$ty += 16;
			}
		} else {
			$by = (int) ( $h / 2 ) - 16;
			imagestring( $im, 5, $pad, $by, $this->ascii( $brand ), $white );
			imagestring( $im, 3, $pad, $by + 22, $this->ascii( $headline ), $white );
		}

		// Se guarda a 2x (vecino más cercano: conserva el look bitmap) para que
		// en pantallas retina no dispare la auditoría "image-size-responsive"
		// de Lighthouse (Best Practices). El <img> se muestra a w×h.
		$im2x = imagescale( $im, $w * 2, $h * 2, IMG_NEAREST_NEIGHBOUR );
		if ( $im2x ) {
			imagedestroy( $im );
			$im = $im2x;
		}

		$slug     = 'demo-ad-' . $format;
		$upload   = wp_upload_dir();
		$filename = wp_unique_filename( $upload['path'], $slug . '.jpg' );
		ob_start();
		imagejpeg( $im, null, 88 );
		$data = ob_get_clean();
		imagedestroy( $im );

		$saved = wp_upload_bits( $filename, null, $data );
		if ( ! empty( $saved['error'] ) ) {
			return array();
		}
		$att_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => sprintf( $this->txt( 'ad_title', 'Demo ad %s' ), $spec['label'] ),
				'post_status'    => 'inherit',
			),
			$saved['file']
		);
		if ( is_wp_error( $att_id ) || ! $att_id ) {
			return array();
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $att_id, wp_generate_attachment_metadata( $att_id, $saved['file'] ) );
		update_post_meta( $att_id, self::FLAG_META, '1' );
		update_post_meta( $att_id, '_wp_attachment_image_alt', $this->txt( 'ad_alt', 'Sample ad' ) );

		return array( 'url' => wp_get_attachment_url( $att_id ), 'w' => $w, 'h' => $h, 'id' => $att_id );
	}

	/**
	 * Configura la portada para mostrar las últimas entradas (vídeos).
	 */
	private function configure_reading() {
		update_option( 'show_on_front', 'posts' );
		update_option( 'posts_per_page', 21 );
	}

	/* ------------------------------------------------------------------ */
	/* Utilidades                                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * Elimina los adjuntos de un post.
	 *
	 * @param int $post_id ID del post.
	 */
	private function delete_post_attachments( $post_id ) {
		$thumb = get_post_thumbnail_id( $post_id );
		if ( $thumb ) {
			wp_delete_attachment( $thumb, true );
		}
		$children = get_children( array( 'post_parent' => $post_id, 'post_type' => 'attachment' ) );
		foreach ( $children as $child ) {
			wp_delete_attachment( $child->ID, true );
		}
	}

	/**
	 * Genera una imagen de marca (gradiente + título) y la registra como adjunto.
	 *
	 * @param string $title  Texto principal.
	 * @param int    $w      Ancho.
	 * @param int    $h      Alto.
	 * @param string $c1     Color superior (hex).
	 * @param string $c2     Color inferior (hex).
	 * @param string $kind   'poster' | 'backdrop'.
	 * @param string $label  Etiqueta opcional (categoría).
	 * @return int ID del adjunto o 0.
	 */
	private function generate_image( $title, $w, $h, $c1, $c2, $kind = 'poster', $label = '' ) {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return 0; // GD no disponible.
		}

		$im = imagecreatetruecolor( $w, $h );
		list( $r1, $g1, $b1 ) = $this->hex_rgb( $c1 );
		list( $r2, $g2, $b2 ) = $this->hex_rgb( $c2 );

		// Gradiente vertical diagonal.
		for ( $y = 0; $y < $h; $y++ ) {
			$t = $y / max( 1, $h - 1 );
			$r = (int) ( $r1 + ( $r2 - $r1 ) * $t );
			$g = (int) ( $g1 + ( $g2 - $g1 ) * $t );
			$b = (int) ( $b1 + ( $b2 - $b1 ) * $t );
			$col = imagecolorallocate( $im, $r, $g, $b );
			imagefilledrectangle( $im, 0, $y, $w, $y, $col );
		}

		// Viñeta inferior para legibilidad del título.
		for ( $y = (int) ( $h * 0.55 ); $y < $h; $y++ ) {
			$alpha = (int) ( 110 * ( ( $y - $h * 0.55 ) / ( $h * 0.45 ) ) );
			$alpha = min( 110, max( 0, $alpha ) );
			$shade = imagecolorallocatealpha( $im, 6, 8, 13, 127 - $alpha );
			imagefilledrectangle( $im, 0, $y, $w, $y, $shade );
		}

		// Glifo de play (triángulo) centrado-superior.
		$cx   = (int) ( $w / 2 );
		$cy   = (int) ( $h * 0.4 );
		$size = (int) ( min( $w, $h ) * 0.12 );
		$white = imagecolorallocatealpha( $im, 255, 255, 255, 35 );
		$tri = array(
			$cx - $size * 0.5, $cy - $size,
			$cx - $size * 0.5, $cy + $size,
			$cx + $size, $cy,
		);
		imagefilledpolygon( $im, $tri, $white );

		// Texto: etiqueta + título con fuente bitmap incorporada.
		$txt_color = imagecolorallocate( $im, 255, 255, 255 );
		if ( $label ) {
			imagestring( $im, 4, 24, (int) ( $h - 78 ), strtoupper( $this->ascii( $label ) ), $txt_color );
		}
		$wrapped = $this->wrap_text( $this->ascii( $title ), 28 );
		$ty      = (int) ( $h - 56 );
		foreach ( $wrapped as $line ) {
			imagestring( $im, 5, 24, $ty, $line, $txt_color );
			$ty += 18;
		}

		// Marca de agua "Clipnuvex".
		imagestring( $im, 2, 24, 18, 'CLIPNUVEX', imagecolorallocatealpha( $im, 255, 255, 255, 70 ) );

		// Guardar a uploads.
		$slug     = sanitize_title( $title ) . '-' . $kind;
		$upload   = wp_upload_dir();
		$filename = wp_unique_filename( $upload['path'], $slug . '.jpg' );
		$filepath = trailingslashit( $upload['path'] ) . $filename;

		ob_start();
		imagejpeg( $im, null, 86 );
		$data = ob_get_clean();
		imagedestroy( $im );

		$saved = wp_upload_bits( $filename, null, $data );
		if ( ! empty( $saved['error'] ) ) {
			return 0;
		}

		$attachment = array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => $title,
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$att_id = wp_insert_attachment( $attachment, $saved['file'] );
		if ( is_wp_error( $att_id ) || ! $att_id ) {
			return 0;
		}
		$meta = wp_generate_attachment_metadata( $att_id, $saved['file'] );
		wp_update_attachment_metadata( $att_id, $meta );
		update_post_meta( $att_id, self::FLAG_META, '1' );
		// Alt text para accesibilidad/SEO.
		update_post_meta( $att_id, '_wp_attachment_image_alt', $title );

		return $att_id;
	}

	/**
	 * Hex a RGB.
	 *
	 * @param string $hex Color.
	 * @return array [r,g,b].
	 */
	private function hex_rgb( $hex ) {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		return array(
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * Translitera a ASCII para la fuente bitmap de GD.
	 *
	 * @param string $s Texto.
	 * @return string
	 */
	private function ascii( $s ) {
		$s = remove_accents( $s );
		return preg_replace( '/[^\x20-\x7E]/', '', $s );
	}

	/**
	 * Envuelve texto en líneas de máximo $max caracteres.
	 *
	 * @param string $text Texto.
	 * @param int    $max  Máximo por línea.
	 * @return array
	 */
	private function wrap_text( $text, $max ) {
		return explode( "\n", wordwrap( $text, $max, "\n", true ) );
	}
}
