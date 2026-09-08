<?php
/**
 * Clipnuvex theme bootstrap.
 *
 * Carga modular de todas las piezas del theme. Cada archivo de inc/ es
 * autocontenido y se engancha a los hooks de WordPress por su cuenta.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No acceso directo.
}

define( 'CLIPNUVEX_VERSION', '1.6.0' );
define( 'CLIPNUVEX_DIR', trailingslashit( get_template_directory() ) );
define( 'CLIPNUVEX_URI', trailingslashit( get_template_directory_uri() ) );

/**
 * Carga un archivo de la carpeta inc/ si existe.
 *
 * @param string $relative Ruta relativa dentro del theme (p. ej. 'inc/setup.php').
 */
function clipnuvex_require( $relative ) {
	$path = CLIPNUVEX_DIR . ltrim( $relative, '/' );
	if ( file_exists( $path ) ) {
		require_once $path;
	}
}

/*
 * Orden de carga: primero infraestructura (setup, CPT, taxonomías, meta),
 * luego helpers de render, después capas de presentación/SEO/monetización
 * y por último admin/demo/i18n.
 */
$clipnuvex_modules = array(
	'inc/setup.php',
	'inc/enqueue.php',
	'inc/cpt.php',
	'inc/taxonomies.php',
	'inc/permalinks.php',
	'inc/meta.php',
	'inc/queries.php',
	'inc/template-tags.php',
	'inc/ads.php',
	'inc/options.php',
	'inc/colors.php',
	'inc/seo.php',
	'inc/schema.php',
	'inc/sitemap.php',
	'inc/i18n.php',
	'inc/demo/class-demo-importer.php',
	'inc/demo/page.php',
	'inc/migrate/class-post-migrator.php',
	'inc/migrate/redirects.php',
	'inc/migrate/page.php',
	'inc/migrate/cli.php',
	'inc/admin/options-page.php',
);

foreach ( $clipnuvex_modules as $clipnuvex_module ) {
	clipnuvex_require( $clipnuvex_module );
}
