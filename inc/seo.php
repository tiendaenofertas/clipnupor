<?php
/**
 * Capa de SEO on-page de Clipnuvex.
 *
 * Imprime, por vista, la meta description, canonical, Open Graph, Twitter Card,
 * robots y theme-color en `wp_head`. Refina además el <title> del documento
 * (title-tag está activo, por lo que se usan los filtros nativos en lugar de
 * imprimir una etiqueta <title> propia).
 *
 * Todas las cadenas se escapan en el punto de salida (esc_attr / esc_url).
 * No accede a la base de datos directamente; usa la API de WordPress.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ¿Hay un plugin SEO conocido activo (Yoast, Rank Math, SEOPress, AIOSEO,
 * The SEO Framework)? En ese caso el theme NO debe emitir su propio title,
 * description, canonical, OG, robots ni JSON-LD para evitar duplicados (que
 * penalizan el SEO). El plugin pasa a ser la única fuente.
 *
 * @return bool
 */
function clipnuvex_seo_plugin_active() {
	static $active = null;
	if ( null !== $active ) {
		return $active;
	}
	$active = (
		defined( 'WPSEO_VERSION' )            // Yoast SEO.
		|| defined( 'RANK_MATH_VERSION' )     // Rank Math.
		|| defined( 'SEOPRESS_VERSION' )      // SEOPress.
		|| defined( 'AIOSEO_VERSION' )        // All in One SEO.
		|| defined( 'THE_SEO_FRAMEWORK_VERSION' ) // The SEO Framework.
		|| function_exists( 'rank_math' )
	);
	return (bool) apply_filters( 'clipnuvex_seo_plugin_active', $active );
}

/**
 * Robots por vista mediante el filtro nativo de WordPress (una sola etiqueta
 * <meta name="robots">, sin duplicar la que añade el core).
 *
 * @param array $robots Directivas actuales.
 * @return array
 */
function clipnuvex_filter_robots( $robots ) {
	if ( clipnuvex_seo_plugin_active() ) {
		return $robots;
	}
	$robots['max-image-preview'] = 'large';
	// Páginas finas/duplicadas que no deben indexarse (evita contenido pobre).
	if ( is_search() || is_author() || is_date() || is_attachment() ) {
		$robots['noindex'] = true;
	}
	return $robots;
}
add_filter( 'wp_robots', 'clipnuvex_filter_robots' );

/**
 * Redirige las páginas de adjunto al contenido padre (evita páginas vacías).
 */
function clipnuvex_redirect_attachments() {
	if ( is_attachment() && ! clipnuvex_seo_plugin_active() ) {
		$parent = wp_get_post_parent_id( get_queried_object_id() );
		if ( $parent ) {
			wp_safe_redirect( get_permalink( $parent ), 301 );
			exit;
		}
	}
}
add_action( 'template_redirect', 'clipnuvex_redirect_attachments' );

/**
 * Aviso en el admin si los buscadores están bloqueados (blog_public = 0):
 * con esa opción activa el SEO nunca llega a 100 y el sitio no se indexa.
 */
function clipnuvex_warn_blog_not_public() {
	if ( ! current_user_can( 'manage_options' ) || '0' !== get_option( 'blog_public' ) ) {
		return;
	}
	printf(
		'<div class="notice notice-error"><p><strong>Clipnuvex:</strong> %s <a href="%s">%s</a></p></div>',
		esc_html__( 'Los motores de búsqueda están bloqueados (noindex global). El SEO no puede llegar a 100 y tu sitio no se indexará en Google.', 'clipnuvex' ),
		esc_url( admin_url( 'options-reading.php' ) ),
		esc_html__( 'Corregir en Ajustes → Lectura', 'clipnuvex' )
	);
}
add_action( 'admin_notices', 'clipnuvex_warn_blog_not_public' );

/**
 * Evita el canonical duplicado: WordPress añade su propio `rel_canonical` en
 * páginas singulares. Como el theme emite un canonical consistente para TODAS
 * las vistas, se elimina el del core (salvo que un plugin SEO gestione el SEO).
 */
function clipnuvex_seo_dedupe_canonical() {
	if ( ! clipnuvex_seo_plugin_active() ) {
		remove_action( 'wp_head', 'rel_canonical' );
	}
}
add_action( 'wp', 'clipnuvex_seo_dedupe_canonical' );

/**
 * Recorta y normaliza un texto para usarlo como descripción.
 *
 * @param string $text   Texto de origen.
 * @param int    $length Longitud máxima en caracteres.
 * @return string
 */
function clipnuvex_seo_trim( $text, $length = 160 ) {
	$text = wp_strip_all_tags( (string) $text, true );
	$text = preg_replace( '/\s+/u', ' ', $text );
	$text = trim( $text );
	if ( '' === $text ) {
		return '';
	}
	if ( function_exists( 'mb_strlen' ) ) {
		if ( mb_strlen( $text ) <= $length ) {
			return $text;
		}
		$text = mb_substr( $text, 0, $length - 1 );
		$cut  = mb_strrpos( $text, ' ' );
		if ( false !== $cut && $cut > 40 ) {
			$text = mb_substr( $text, 0, $cut );
		}
		return rtrim( $text, " ,.;:" ) . '…';
	}
	if ( strlen( $text ) <= $length ) {
		return $text;
	}
	return rtrim( substr( $text, 0, $length - 1 ) ) . '…';
}

/**
 * Imagen por defecto del sitio para Open Graph / Twitter.
 *
 * Orden de preferencia: imagen OG del panel (SEO → Imagen Open Graph) →
 * icono del sitio (site icon) → logo personalizado.
 * Devuelve cadena vacía si no hay ninguna disponible.
 *
 * @return string
 */
function clipnuvex_seo_default_image() {
	$custom = (string) clipnuvex_option( 'seo_og_image', '' );
	if ( '' !== trim( $custom ) ) {
		return esc_url_raw( $custom );
	}
	if ( function_exists( 'get_site_icon_url' ) ) {
		$icon = get_site_icon_url( 512 );
		if ( $icon ) {
			return $icon;
		}
	}
	$logo_id = get_theme_mod( 'custom_logo' );
	if ( $logo_id ) {
		$src = wp_get_attachment_image_url( $logo_id, 'full' );
		if ( $src ) {
			return $src;
		}
	}
	return '';
}

/**
 * Calcula el contexto SEO de la vista actual.
 *
 * @return array{title:string,description:string,canonical:string,og_image:string,type:string}
 */
function clipnuvex_seo_context() {
	$site_name = get_bloginfo( 'name' );
	$context   = array(
		'title'       => $site_name,
		'description' => clipnuvex_seo_trim( get_bloginfo( 'description' ) ),
		'canonical'   => home_url( '/' ),
		'og_image'    => clipnuvex_seo_default_image(),
		'type'        => 'website',
	);

	// Vista: portada / inicio.
	if ( is_front_page() || is_home() ) {
		$context['title']     = $site_name;
		$context['canonical'] = home_url( '/' );
		// Meta description editable (panel → SEO); si está vacía, descripción del sitio.
		$desc = (string) clipnuvex_option( 'seo_home_desc', '' );
		if ( '' === trim( $desc ) ) {
			$desc = get_bloginfo( 'description' );
		}
		$context['description'] = clipnuvex_seo_trim(
			$desc ? $desc : __( 'Mira películas y series online en HD y 4K. Catálogo actualizado de vídeo en streaming en Clipnuvex.', 'clipnuvex' )
		);
		return $context;
	}

	// Vista: vídeo individual.
	if ( is_singular( 'video' ) ) {
		$data    = function_exists( 'clipnuvex_get_video_data' ) ? clipnuvex_get_video_data( get_queried_object_id() ) : array();
		$title   = isset( $data['title'] ) && $data['title'] ? $data['title'] : get_the_title();
		$quality = isset( $data['quality'] ) && $data['quality'] ? $data['quality'] : 'HD';
		$excerpt = has_excerpt( get_queried_object_id() ) ? get_the_excerpt( get_queried_object_id() ) : '';

		$context['title']     = $title;
		$context['type']      = 'video.other';
		$context['canonical'] = isset( $data['permalink'] ) && $data['permalink'] ? $data['permalink'] : get_permalink();
		$context['description'] = clipnuvex_seo_trim(
			sprintf(
				/* translators: 1: título del vídeo, 2: calidad (HD/4K), 3: extracto. */
				__( 'Ver %1$s online en %2$s. %3$s', 'clipnuvex' ),
				$title,
				$quality,
				$excerpt
			)
		);
		if ( ! empty( $data['backdrop'] ) ) {
			$context['og_image'] = $data['backdrop'];
		} elseif ( ! empty( $data['poster'] ) ) {
			$context['og_image'] = $data['poster'];
		}
		return $context;
	}

	// Vista: archivo de taxonomía (categoría o tag).
	if ( is_tax( array( 'categoria_video', 'tag_video' ) ) ) {
		$term = get_queried_object();
		$name = ( $term && isset( $term->name ) ) ? $term->name : '';
		$link = $term ? get_term_link( $term ) : '';

		$context['title']     = $name;
		$context['type']      = 'website';
		$context['canonical'] = ( $link && ! is_wp_error( $link ) ) ? $link : clipnuvex_seo_current_url();
		$desc                 = ( $term && ! empty( $term->description ) ) ? $term->description : '';
		$context['description'] = clipnuvex_seo_trim(
			$desc ? $desc : sprintf(
				/* translators: %s: nombre del término. */
				__( 'Ver %s online en streaming HD y 4K. Catálogo actualizado en Clipnuvex.', 'clipnuvex' ),
				$name
			)
		);
		return $context;
	}

	// Vista: resultados de búsqueda.
	if ( is_search() ) {
		$query                = get_search_query();
		$context['title']     = $query;
		$context['type']      = 'website';
		$context['canonical'] = home_url( '/?s=' . rawurlencode( $query ) );
		$context['description'] = clipnuvex_seo_trim(
			sprintf(
				/* translators: %s: término buscado. */
				__( 'Resultados de búsqueda para «%s» en Clipnuvex. Encuentra y mira vídeo online en HD y 4K.', 'clipnuvex' ),
				$query
			)
		);
		return $context;
	}

	// Vista: archivo del CPT vídeo ("Explorar").
	if ( is_post_type_archive( 'video' ) ) {
		$archive              = get_post_type_archive_link( 'video' );
		$context['title']     = __( 'Explorar', 'clipnuvex' );
		$context['canonical'] = $archive ? $archive : clipnuvex_seo_current_url();
		$context['description'] = clipnuvex_seo_trim(
			__( 'Explora todo el catálogo de vídeo online en HD y 4K. Novedades y estrenos actualizados en Clipnuvex.', 'clipnuvex' )
		);
		return $context;
	}

	// Vista: página de todas las categorías (resuelta por ID/slug, solo publicada).
	if ( function_exists( 'clipnuvex_is_categories_page' ) && clipnuvex_is_categories_page() ) {
		$context['title']     = get_the_title( get_queried_object_id() );
		$context['canonical'] = get_permalink( get_queried_object_id() );
		$context['description'] = clipnuvex_seo_trim(
			__( 'Todas las categorías de vídeo de Clipnuvex. Explora por género y mira online en streaming HD y 4K.', 'clipnuvex' )
		);
		return $context;
	}

	// Fallback genérico (otras páginas, posts, etc.).
	if ( is_singular() ) {
		$context['title']       = get_the_title( get_queried_object_id() );
		$context['canonical']   = get_permalink( get_queried_object_id() );
		$excerpt                = get_the_excerpt( get_queried_object_id() );
		$context['description'] = clipnuvex_seo_trim( $excerpt ? $excerpt : get_bloginfo( 'description' ) );
		if ( has_post_thumbnail( get_queried_object_id() ) ) {
			$img = get_the_post_thumbnail_url( get_queried_object_id(), 'clipnuvex-backdrop' );
			if ( $img ) {
				$context['og_image'] = $img;
			}
		}
	} else {
		$context['canonical'] = clipnuvex_seo_current_url();
	}

	return $context;
}

/**
 * URL actual completa (con esquema y host), sin parámetros de paginación añadidos.
 *
 * @return string
 */
function clipnuvex_seo_current_url() {
	if ( function_exists( 'clipnuvex_current_url' ) ) {
		return clipnuvex_current_url();
	}
	global $wp;
	$request = isset( $wp->request ) ? $wp->request : '';
	return home_url( add_query_arg( array(), $request ) );
}

/**
 * Imprime las metaetiquetas SEO en el <head>.
 *
 * Enganchado en `wp_head` con prioridad 1 para situarse antes de otras metas.
 */
function clipnuvex_seo_meta_tags() {
	// Evita imprimir metas en feeds, embeds o respuestas no HTML.
	if ( is_feed() || is_embed() || is_404() ) {
		return;
	}

	// Si hay un plugin SEO activo, él gestiona todo: no duplicar.
	if ( clipnuvex_seo_plugin_active() ) {
		return;
	}

	$context   = clipnuvex_seo_context();
	$site_name = get_bloginfo( 'name' );

	// Canonical correcto en páginas paginadas: apunta a /page/N/ (no a la 1),
	// para que Google no trate las páginas 2+ como duplicados de la 1.
	$cnx_paged = (int) get_query_var( 'paged' );
	if ( $cnx_paged < 2 ) {
		$cnx_paged = (int) get_query_var( 'page' );
	}
	if ( $cnx_paged > 1 && ! empty( $context['canonical'] ) && false === strpos( $context['canonical'], '/page/' ) ) {
		$context['canonical'] = user_trailingslashit( trailingslashit( $context['canonical'] ) . 'page/' . $cnx_paged );
	}

	echo "\n\t<!-- Clipnuvex SEO -->\n";

	printf(
		"\t<meta name=\"description\" content=\"%s\">\n",
		esc_attr( $context['description'] )
	);

	// El meta robots lo emite WordPress (wp_robots) refinado en clipnuvex_filter_robots,
	// para no duplicar la etiqueta.

	if ( ! empty( $context['canonical'] ) ) {
		printf(
			"\t<link rel=\"canonical\" href=\"%s\">\n",
			esc_url( $context['canonical'] )
		);
	}

	// Open Graph.
	printf( "\t<meta property=\"og:type\" content=\"%s\">\n", esc_attr( $context['type'] ) );
	printf( "\t<meta property=\"og:site_name\" content=\"%s\">\n", esc_attr( $site_name ) );
	printf( "\t<meta property=\"og:title\" content=\"%s\">\n", esc_attr( $context['title'] ) );
	printf( "\t<meta property=\"og:description\" content=\"%s\">\n", esc_attr( $context['description'] ) );
	if ( ! empty( $context['canonical'] ) ) {
		printf( "\t<meta property=\"og:url\" content=\"%s\">\n", esc_url( $context['canonical'] ) );
	}
	if ( ! empty( $context['og_image'] ) ) {
		printf( "\t<meta property=\"og:image\" content=\"%s\">\n", esc_url( $context['og_image'] ) );
	}
	$locale = get_locale();
	if ( $locale ) {
		printf( "\t<meta property=\"og:locale\" content=\"%s\">\n", esc_attr( $locale ) );
	}

	// Twitter Card.
	printf( "\t<meta name=\"twitter:card\" content=\"%s\">\n", esc_attr( 'summary_large_image' ) );
	printf( "\t<meta name=\"twitter:title\" content=\"%s\">\n", esc_attr( $context['title'] ) );
	printf( "\t<meta name=\"twitter:description\" content=\"%s\">\n", esc_attr( $context['description'] ) );
	if ( ! empty( $context['og_image'] ) ) {
		printf( "\t<meta name=\"twitter:image\" content=\"%s\">\n", esc_url( $context['og_image'] ) );
	}

	// theme-color desde el acento del panel de opciones.
	printf(
		"\t<meta name=\"theme-color\" content=\"%s\">\n",
		esc_attr( sanitize_hex_color( clipnuvex_option( 'accent', '#7C5CFF' ) ) ?: '#7C5CFF' )
	);
}
add_action( 'wp_head', 'clipnuvex_seo_meta_tags', 1 );

/**
 * SafeSearch: <meta name="rating" content="adult"> solo si el panel lo activa
 * (SEO → "Marcar el sitio como contenido adulto"). Independiente de la capa SEO
 * (se imprime aunque haya plugin SEO: ninguno emite esta etiqueta, no hay
 * duplicado). Apagado por defecto → salida idéntica.
 */
function clipnuvex_seo_rating_meta() {
	if ( function_exists( 'clipnuvex_is_on' ) && clipnuvex_is_on( 'seo_adult_rating' ) ) {
		echo "\t<meta name=\"rating\" content=\"adult\">\n";
	}
}
add_action( 'wp_head', 'clipnuvex_seo_rating_meta', 2 );

/**
 * Refina las partes del título del documento (title-tag activo).
 *
 * @param array $parts Partes del título: title, page, tagline, site.
 * @return array
 */
function clipnuvex_seo_document_title_parts( $parts ) {
	if ( clipnuvex_seo_plugin_active() ) {
		return $parts;
	}
	$site = isset( $parts['site'] ) ? $parts['site'] : get_bloginfo( 'name' );

	if ( is_singular( 'video' ) ) {
		$data    = function_exists( 'clipnuvex_get_video_data' ) ? clipnuvex_get_video_data( get_queried_object_id() ) : array();
		$title   = isset( $data['title'] ) && $data['title'] ? $data['title'] : get_the_title();
		$quality = isset( $data['quality'] ) && $data['quality'] ? $data['quality'] : 'HD';
		$parts['title'] = sprintf(
			/* translators: 1: título, 2: calidad. */
			__( 'Ver %1$s online en %2$s', 'clipnuvex' ),
			$title,
			$quality
		);
		return $parts;
	}

	if ( is_tax( array( 'categoria_video', 'tag_video' ) ) ) {
		$term = get_queried_object();
		$name = ( $term && isset( $term->name ) ) ? $term->name : '';
		$parts['title'] = sprintf(
			/* translators: %s: nombre del término. */
			__( '%s online en HD y 4K', 'clipnuvex' ),
			$name
		);
		return $parts;
	}

	if ( is_search() ) {
		$parts['title'] = sprintf(
			/* translators: %s: término buscado. */
			__( 'Resultados de «%s»', 'clipnuvex' ),
			get_search_query()
		);
		return $parts;
	}

	if ( is_post_type_archive( 'video' ) ) {
		$parts['title'] = __( 'Explorar el catálogo', 'clipnuvex' );
		return $parts;
	}

	unset( $site );
	return $parts;
}
add_filter( 'document_title_parts', 'clipnuvex_seo_document_title_parts' );
