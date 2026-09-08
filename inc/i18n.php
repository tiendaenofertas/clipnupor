<?php
/**
 * Internacionalización: text domain, toggle ES/EN ligero y hreflang.
 *
 * No fuerza ningún plugin. Si Polylang/WPML está activo, el theme respeta su
 * idioma y delega el hreflang en el plugin. Sin plugin, ofrece un toggle del
 * "chrome" (interfaz) ES/EN basado en cookie + filtro de locale.
 *
 * Dos nociones distintas (desde 1.5.5):
 * - clipnuvex_site_lang()    → idioma del SITIO: solo el ajuste del panel.
 *   Gobierna lo global (base de URL de categorías, slug de la página
 *   "Categorías", idioma por defecto del schema). Inglés por defecto.
 * - clipnuvex_current_lang() → idioma del VISITANTE (cookie del toggle, si no
 *   el del sitio). Solo cambia el chrome mediante el filtro `locale`.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ¿Hay un plugin multilenguaje activo?
 *
 * @return bool
 */
function clipnuvex_has_multilang_plugin() {
	return ( defined( 'POLYLANG_VERSION' ) || function_exists( 'pll_current_language' ) || defined( 'ICL_SITEPRESS_VERSION' ) );
}

/**
 * Idiomas soportados por el toggle ligero.
 *
 * @return array code => ['label','locale'].
 */
function clipnuvex_languages() {
	return array(
		'es' => array( 'label' => 'ES', 'locale' => 'es_ES' ),
		'en' => array( 'label' => 'EN', 'locale' => 'en_US' ),
	);
}

/**
 * Idioma actual del chrome (cookie o idioma por defecto del panel).
 *
 * @return string Código 'es' | 'en'.
 */
function clipnuvex_current_lang() {
	if ( clipnuvex_has_multilang_plugin() && function_exists( 'pll_current_language' ) ) {
		$pll = pll_current_language();
		return ( 'en' === $pll ) ? 'en' : 'es';
	}
	if ( isset( $_COOKIE['clipnuvex_lang'] ) ) {
		$c = sanitize_key( wp_unslash( $_COOKIE['clipnuvex_lang'] ) );
		if ( array_key_exists( $c, clipnuvex_languages() ) ) {
			return $c;
		}
	}

	// IMPORTANTE: esta función la usa el filtro `locale`, así que NO debe invocar
	// gettext (__()) ni el registro de opciones (que sí lo usa) para evitar una
	// recursión infinita. Se lee la option array en crudo (única fuente de verdad).
	return clipnuvex_site_lang();
}

/**
 * Idioma efectivo a partir de una option array `clipnuvex_options` (o de lo
 * que haya guardado): la clave `default_lang` si es válida, si no INGLÉS.
 * Sin gettext ni registro de opciones (se usa desde el filtro `locale`, desde
 * el registro de taxonomías en init y desde los hooks de guardado del panel).
 *
 * @param mixed $stored Valor de la option (array) o false/otro.
 * @return string 'es' | 'en'.
 */
function clipnuvex_lang_from_options( $stored ) {
	$lang = ( is_array( $stored ) && isset( $stored['default_lang'] ) ) ? (string) $stored['default_lang'] : 'en';
	return array_key_exists( $lang, clipnuvex_languages() ) ? $lang : 'en';
}

/**
 * Idioma del SITIO: solo el ajuste del panel (General → Idioma por defecto),
 * nunca la cookie del visitante. Es lo que deben leer las cosas globales
 * (base de URL de categorías, slug de la página "Categorías", schema): las
 * reglas de reescritura y el contenido son iguales para todos los visitantes.
 * Con plugin multilenguaje sigue mandando el panel por la misma razón.
 *
 * @return string 'es' | 'en'.
 */
function clipnuvex_site_lang() {
	return clipnuvex_lang_from_options( get_option( 'clipnuvex_options', array() ) );
}

/**
 * Cambia el locale del chrome según la cookie (solo sin plugin multilenguaje).
 *
 * @param string $locale Locale actual.
 * @return string
 */
function clipnuvex_filter_locale( $locale ) {
	static $running = false;
	if ( $running || is_admin() || clipnuvex_has_multilang_plugin() ) {
		return $locale;
	}
	$running = true;
	$langs   = clipnuvex_languages();
	$cur     = clipnuvex_current_lang();
	$running = false;
	return isset( $langs[ $cur ] ) ? $langs[ $cur ]['locale'] : $locale;
}
add_filter( 'locale', 'clipnuvex_filter_locale' );

/**
 * Procesa el cambio de idioma vía ?cnxlang=.
 */
function clipnuvex_handle_lang_switch() {
	if ( isset( $_GET['cnxlang'] ) ) {
		$lang  = sanitize_key( wp_unslash( $_GET['cnxlang'] ) );
		$langs = clipnuvex_languages();
		if ( array_key_exists( $lang, $langs ) ) {
			setcookie(
				'clipnuvex_lang',
				$lang,
				array(
					'expires'  => time() + YEAR_IN_SECONDS,
					'path'     => COOKIEPATH ? COOKIEPATH : '/',
					'domain'   => COOKIE_DOMAIN,
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
			$_COOKIE['clipnuvex_lang'] = $lang;
		}
		wp_safe_redirect( remove_query_arg( 'cnxlang' ) );
		exit;
	}
}
add_action( 'template_redirect', 'clipnuvex_handle_lang_switch' );

/**
 * Renderiza el switcher de idioma del header.
 */
function clipnuvex_language_switcher() {
	// Toggle desactivable desde el panel.
	if ( function_exists( 'clipnuvex_is_on' ) && ! clipnuvex_is_on( 'show_language_switcher' ) ) {
		return;
	}

	// Con plugin multilenguaje, delega en sus enlaces si existen.
	if ( clipnuvex_has_multilang_plugin() && function_exists( 'pll_the_languages' ) ) {
		$links = pll_the_languages( array( 'raw' => 1, 'echo' => 0 ) );
		if ( ! empty( $links ) ) {
			foreach ( $links as $l ) {
				if ( empty( $l['current_lang'] ) ) {
					printf(
						'<a class="cnx-lang" href="%s" aria-label="%s">%s</a>',
						esc_url( $l['url'] ),
						esc_attr__( 'Cambiar idioma', 'clipnuvex' ),
						esc_html( strtoupper( $l['slug'] ) )
					);
					return;
				}
			}
		}
	}

	// Toggle ligero ES ↔ EN.
	$cur   = clipnuvex_current_lang();
	$next  = ( 'es' === $cur ) ? 'en' : 'es';
	$langs = clipnuvex_languages();
	printf(
		'<a class="cnx-lang" href="%s" role="button" aria-label="%s">%s</a>',
		esc_url( add_query_arg( 'cnxlang', $next ) ),
		esc_attr__( 'Idioma / Language', 'clipnuvex' ),
		esc_html( $langs[ $cur ]['label'] )
	);
}

/**
 * Emite hreflang ES/EN solo si no hay plugin (que ya lo gestiona).
 */
function clipnuvex_hreflang() {
	if ( clipnuvex_has_multilang_plugin() ) {
		return;
	}
	// Sin plugin no hay URLs alternativas reales; se omite para no emitir hreflang inválidos.
}
add_action( 'wp_head', 'clipnuvex_hreflang' );
