<?php
/**
 * Configuración base del theme.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Soportes del theme, menús y tamaños de imagen.
 */
function clipnuvex_setup() {
	load_theme_textdomain( 'clipnuvex', CLIPNUVEX_DIR . 'languages' );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'customize-selective-refresh-widgets' );

	// Logo opcional (el wordmark tipográfico es el predeterminado).
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 34,
			'width'       => 160,
			'flex-width'  => true,
			'flex-height' => true,
		)
	);

	// Póster vertical 2:3 (300x450) y backdrop 16:9 (1280x720) para los vídeos.
	add_image_size( 'clipnuvex-poster', 400, 600, true );
	add_image_size( 'clipnuvex-poster-sm', 200, 300, true );
	add_image_size( 'clipnuvex-backdrop', 1280, 720, true );
	// Miniatura horizontal 16:9 ligera para las tarjetas en modo "Horizontal"
	// (panel → Estilos → Orientación de las miniaturas). Se registra siempre para
	// que toda subida futura la tenga aunque el modo se cambie más adelante; las
	// subidas anteriores usan el backdrop como respaldo (ver clipnuvex_card_image).
	add_image_size( 'clipnuvex-thumb', 640, 360, true );

	register_nav_menus(
		array(
			'primary' => __( 'Navegación principal', 'clipnuvex' ),
			'footer'  => __( 'Navegación del pie', 'clipnuvex' ),
		)
	);
}
add_action( 'after_setup_theme', 'clipnuvex_setup' );

/**
 * Ancho de contenido para embeds.
 */
function clipnuvex_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'clipnuvex_content_width', 1600 );
}
add_action( 'after_setup_theme', 'clipnuvex_content_width', 0 );

/**
 * Áreas de widgets (footer + slots demo).
 */
function clipnuvex_widgets_init() {
	register_sidebar(
		array(
			'name'          => __( 'Pie de página', 'clipnuvex' ),
			'id'            => 'footer-1',
			'description'   => __( 'Widgets de la zona del pie.', 'clipnuvex' ),
			'before_widget' => '<section id="%1$s" class="cnx-widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="cnx-widget__title">',
			'after_title'   => '</h2>',
		)
	);
}
add_action( 'widgets_init', 'clipnuvex_widgets_init' );

/**
 * Hace que los nombres de las imágenes responsive estén disponibles para srcset.
 *
 * @param array $sizes Tamaños registrados.
 * @return array
 */
function clipnuvex_custom_image_sizes( $sizes ) {
	return array_merge(
		$sizes,
		array(
			'clipnuvex-poster'    => __( 'Póster (2:3)', 'clipnuvex' ),
			'clipnuvex-backdrop'  => __( 'Backdrop (16:9)', 'clipnuvex' ),
			'clipnuvex-thumb'     => __( 'Miniatura horizontal (16:9)', 'clipnuvex' ),
		)
	);
}
add_filter( 'image_size_names_choose', 'clipnuvex_custom_image_sizes' );

/**
 * Activación del theme: registra contenido y limpia reglas de reescritura.
 * Se ejecuta una vez al cambiar de theme (after_switch_theme).
 */
function clipnuvex_on_activation() {
	// Asegura que CPT/taxonomías existen antes del flush.
	if ( function_exists( 'clipnuvex_register_cpt' ) ) {
		clipnuvex_register_cpt();
	}
	if ( function_exists( 'clipnuvex_register_taxonomies' ) ) {
		clipnuvex_register_taxonomies();
	}
	flush_rewrite_rules();

	// La navegación, el pie, la barra inferior y las migas enlazan a la página
	// "Categorías": se crea aquí si falta (antes solo la creaba el Modo Demo).
	clipnuvex_ensure_categories_page();
	add_option( 'clipnuvex_categories_page_seeded', 1 );
}
add_action( 'after_switch_theme', 'clipnuvex_on_activation' );

/**
 * Slug de la página "Todas las categorías" según el idioma del SITIO:
 * `categorias` (ES) o `categories` (EN). Filtro
 * `clipnuvex_categories_page_slug( $slug, $lang )`.
 *
 * @return string
 */
function clipnuvex_categories_page_slug() {
	$lang = function_exists( 'clipnuvex_site_lang' ) ? clipnuvex_site_lang() : 'en';
	$slug = ( 'en' === $lang ) ? 'categories' : 'categorias';
	$slug = sanitize_title( (string) apply_filters( 'clipnuvex_categories_page_slug', $slug, $lang ) );
	return '' !== $slug ? $slug : 'categorias';
}

/**
 * Localiza la página "Todas las categorías" sin importar el idioma con el que
 * se creó: primero por el ID guardado (`clipnuvex_categories_page_id`) y
 * después por slug (el del idioma actual y las dos formas conocidas, en una
 * sola consulta). Solo PÁGINAS (get_page_by_path( …, 'page' ) devuelve también
 * adjuntos) y nunca de la papelera.
 *
 * @param bool $published_only Solo publicadas (enlaces del front).
 * @return WP_Post|null
 */
function clipnuvex_find_categories_page( $published_only = false ) {
	$ok = static function ( $p ) use ( $published_only ) {
		return $p instanceof WP_Post
			&& 'page' === $p->post_type
			&& 'trash' !== $p->post_status
			&& ( ! $published_only || 'publish' === $p->post_status );
	};
	$saved = (int) get_option( 'clipnuvex_categories_page_id', 0 );
	if ( $saved > 0 ) {
		$page = get_post( $saved );
		if ( $ok( $page ) ) {
			return $page;
		}
	}
	$slugs = array_values( array_unique( array( clipnuvex_categories_page_slug(), 'categorias', 'categories' ) ) );
	$pages = get_posts(
		array(
			'post_type'              => 'page',
			'post_status'            => $published_only ? 'publish' : array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'post_name__in'          => $slugs,
			'posts_per_page'         => count( $slugs ),
			'orderby'                => 'post_name__in',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	foreach ( $pages as $page ) {
		if ( $ok( $page ) ) {
			return $page;
		}
	}
	return null;
}

/**
 * Crea (si no existe) la página "Todas las categorías" y devuelve su ID.
 *
 * Misma página que creaba el importador demo: publicada, slug según el idioma
 * del sitio (clipnuvex_categories_page_slug), plantilla page-categorias.php.
 * El ID se guarda en la option autoloaded `clipnuvex_categories_page_id` para
 * resolverla aunque el slug reciba sufijo (-2) por colisión con otro contenido
 * o cambie el idioma después. Si ya hay una página (cualquier idioma, cualquier
 * estado salvo papelera) se reutiliza: nunca se duplica; si está en borrador
 * los enlaces caen al archivo hasta que se publique (ver clipnuvex_categories_url).
 *
 * @return int ID de la página (existente o creada) o 0 si no se pudo crear.
 */
function clipnuvex_ensure_categories_page() {
	$existing = clipnuvex_find_categories_page( false );
	if ( $existing instanceof WP_Post ) {
		if ( 'publish' === $existing->post_status ) {
			update_option( 'clipnuvex_categories_page_id', (int) $existing->ID );
		}
		return (int) $existing->ID;
	}
	$lang    = function_exists( 'clipnuvex_site_lang' ) ? clipnuvex_site_lang() : 'en';
	$page_id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			// Título literal por idioma, sin gettext: fuera del admin el filtro
			// de locale ya actúa y dentro no; así es determinista y editable.
			'post_title'   => ( 'en' === $lang ) ? 'Categories' : 'Categorías',
			'post_name'    => clipnuvex_categories_page_slug(),
			'post_content' => '',
			'post_author'  => get_current_user_id(),
		)
	);
	if ( ! $page_id || is_wp_error( $page_id ) ) {
		return 0;
	}
	update_post_meta( $page_id, '_wp_page_template', 'page-categorias.php' );
	update_option( 'clipnuvex_categories_page_id', (int) $page_id );
	return (int) $page_id;
}

/**
 * Siembra la página "Categorías" UNA sola vez por sitio en instalaciones ya
 * activas (actualizaciones desde versiones que no la creaban).
 *
 * Corre en admin_init y no en el front a propósito: hay un usuario autenticado
 * (autor real, sin `publish_page` disparado por un visitante anónimo) y no hay
 * carrera entre peticiones concurrentes. add_option() hace de cerrojo: devuelve
 * false si el flag ya existe. Si después el administrador borra la página, NO se
 * recrea (los enlaces caen al archivo /video/).
 */
function clipnuvex_maybe_seed_categories_page() {
	if ( wp_doing_ajax() || ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	if ( ! add_option( 'clipnuvex_categories_page_seeded', 1 ) ) {
		return;
	}
	clipnuvex_ensure_categories_page();
}
add_action( 'admin_init', 'clipnuvex_maybe_seed_categories_page' );

/**
 * Clave de caché versionada para el grupo `clipnuvex` del object cache.
 *
 * Sustituye a wp_cache_flush_group(): el core la delega en el drop-in sin
 * comprobar wp_cache_supports() (fatal con object caches antiguos) y no todos
 * los backends la implementan. Con una sal `last_changed` (el mismo patrón que
 * usa el core para posts/terms) invalidar = cambiar la sal; las entradas
 * antiguas caducan por su TTL. Sin object cache persistente el comportamiento
 * es el de siempre (caché por petición).
 *
 * @param string $key Clave base.
 * @return string Clave con la sal actual del grupo.
 */
function clipnuvex_cache_key( $key ) {
	return $key . '_' . wp_cache_get_last_changed( 'clipnuvex' );
}

/**
 * Invalida todas las entradas del grupo `clipnuvex` cambiando su sal.
 */
function clipnuvex_cache_bump() {
	wp_cache_set( 'last_changed', microtime(), 'clipnuvex' );
}

/**
 * Auto-regeneración de las reglas de reescritura UNA vez por versión del theme.
 *
 * Las reglas viven cacheadas en la option `rewrite_rules`. Si se regeneran en
 * un contexto en el que el CPT/las taxonomías aún no estaban registrados
 * (plugins que flushean antes de `init`, WP-CLI, importadores…), las URLs
 * bonitas de /categoria/… y /tag/… devuelven 404 aunque el contenido exista
 * (caso real en producción, 1.5.2). Corre en `init` tardío (CPT, taxonomías y
 * sitemap ya registrados) y solo cuando cambia CLIPNUVEX_VERSION; en régimen
 * cuesta un get_option autoloaded. Flush "suave" (no reescribe .htaccess).
 */
function clipnuvex_maybe_flush_rules() {
	if ( get_option( 'clipnuvex_rules_ver' ) === CLIPNUVEX_VERSION ) {
		return;
	}
	flush_rewrite_rules( false );
	update_option( 'clipnuvex_rules_ver', CLIPNUVEX_VERSION );
}
add_action( 'init', 'clipnuvex_maybe_flush_rules', 99 );
