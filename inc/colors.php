<?php
/**
 * Sistema de color derivado (v1.5.0).
 *
 * A partir de los colores base del panel (acento, fondo, superficie, texto)
 * calcula toda la paleta secundaria: tintes con transparencia, variantes
 * claras/oscuras del acento, halos, degradados del body/hero/cabecera,
 * superficies secundarias, grises de texto y bordes.
 *
 * REGLA: un token derivado SOLO se emite cuando su color base difiere del
 * default del registry. Con los valores por defecto no se emite nada y el CSS
 * usa los defaults literales de tokens.css → la apariencia por defecto es
 * byte a byte la histórica.
 *
 * Seguridad: todas las entradas pasan por sanitize_hex_color y las salidas se
 * componen aquí (nunca texto del usuario crudo en el CSS inline).
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hex → [r, g, b]. Admite #abc y #aabbcc.
 *
 * @param string $hex Color.
 * @return int[]|null
 */
function clipnuvex_hex_to_rgb( $hex ) {
	$hex = ltrim( trim( (string) $hex ), '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( ! preg_match( '/^[0-9a-f]{6}$/i', $hex ) ) {
		return null;
	}
	return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
}

/**
 * [r, g, b] → #rrggbb (con clamp).
 *
 * @param array $rgb Componentes.
 * @return string
 */
function clipnuvex_rgb_to_hex( $rgb ) {
	$c = array();
	foreach ( array( 0, 1, 2 ) as $i ) {
		$c[] = max( 0, min( 255, (int) round( isset( $rgb[ $i ] ) ? $rgb[ $i ] : 0 ) ) );
	}
	return sprintf( '#%02x%02x%02x', $c[0], $c[1], $c[2] );
}

/**
 * "r, g, b" para usar dentro de rgba(var(--x), a).
 *
 * @param string $hex Color.
 * @return string
 */
function clipnuvex_rgb_list( $hex ) {
	$rgb = clipnuvex_hex_to_rgb( $hex );
	return $rgb ? $rgb[0] . ', ' . $rgb[1] . ', ' . $rgb[2] : '';
}

/**
 * rgba(r, g, b, a) a partir de un hex.
 *
 * @param string    $hex   Color.
 * @param float|int $alpha Alfa (0–1).
 * @return string
 */
function clipnuvex_rgba( $hex, $alpha ) {
	return 'rgba(' . clipnuvex_rgb_list( $hex ) . ', ' . $alpha . ')';
}

/**
 * Mezcla lineal: resultado = a + (b − a) · t.
 *
 * @param string $a Color base.
 * @param string $b Color destino.
 * @param float  $t Peso de $b (0–1).
 * @return string Hex.
 */
function clipnuvex_color_mix( $a, $b, $t ) {
	$ra = clipnuvex_hex_to_rgb( $a );
	$rb = clipnuvex_hex_to_rgb( $b );
	if ( ! $ra || ! $rb ) {
		return (string) $a;
	}
	$t = max( 0, min( 1, (float) $t ) );
	return clipnuvex_rgb_to_hex(
		array(
			$ra[0] + ( $rb[0] - $ra[0] ) * $t,
			$ra[1] + ( $rb[1] - $ra[1] ) * $t,
			$ra[2] + ( $rb[2] - $ra[2] ) * $t,
		)
	);
}

/**
 * Luminancia relativa (WCAG, 0 = negro, 1 = blanco).
 *
 * @param string $hex Color.
 * @return float
 */
function clipnuvex_luminance( $hex ) {
	$rgb = clipnuvex_hex_to_rgb( $hex );
	if ( ! $rgb ) {
		return 0.0;
	}
	$lin = array();
	foreach ( $rgb as $v ) {
		$v     = $v / 255;
		$lin[] = ( $v <= 0.03928 ) ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
	}
	return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
}

/**
 * Aclara/oscurece según el esquema: sobre fondos oscuros "subir un tono" es
 * mezclar con blanco; sobre fondos claros, con negro (así el degradado del
 * body o la superficie-2 siguen siendo sutiles en ambos esquemas).
 *
 * @param string $hex Color.
 * @param float  $t   Intensidad (0–1).
 * @return string
 */
function clipnuvex_color_step( $hex, $t ) {
	return clipnuvex_color_mix( $hex, clipnuvex_luminance( $hex ) > 0.5 ? '#000000' : '#ffffff', $t );
}

/**
 * ¿El valor difiere del default del registry? (comparación normalizada).
 *
 * @param string $id    Id del campo.
 * @param string $value Valor saneado.
 * @return bool
 */
function clipnuvex_color_customized( $id, $value ) {
	$default = function_exists( 'clipnuvex_option_default' ) ? (string) clipnuvex_option_default( $id ) : '';
	return strtolower( trim( (string) $value ) ) !== strtolower( trim( $default ) );
}

/**
 * Presets de esquema aplicables desde el panel (Colores y estilos → Esquema).
 * 'oscuro' = defaults del registry (lo histórico); 'claro' = paleta clara.
 *
 * @return array preset => [campo => valor].
 */
function clipnuvex_color_presets() {
	$def = function_exists( 'clipnuvex_option_default' ) ? 'clipnuvex_option_default' : null;
	$get = static function ( $id, $fallback ) use ( $def ) {
		return $def ? (string) call_user_func( $def, $id ) : $fallback;
	};
	return array(
		'oscuro' => array(
			'accent'        => $get( 'accent', '#7C5CFF' ),
			'accent_dark'   => $get( 'accent_dark', '#5B3CE0' ),
			'bg'            => $get( 'bg', '#0a0910' ),
			'surface'       => $get( 'surface', '#15131e' ),
			'text'          => $get( 'text', '#f1f3f8' ),
			'text_2'        => $get( 'text_2', '#aab2c6' ),
			'header_bg'     => '',
			'preroll_color' => $get( 'preroll_color', '#fbbf24' ),
		),
		'claro'  => array(
			'accent'        => $get( 'accent', '#7C5CFF' ),
			'accent_dark'   => $get( 'accent_dark', '#5B3CE0' ),
			'bg'            => '#f4f5f9',
			'surface'       => '#ffffff',
			'text'          => '#14161f',
			'text_2'        => '#4a5266',
			'header_bg'     => '',
			'preroll_color' => $get( 'preroll_color', '#fbbf24' ),
		),
	);
}

/**
 * Tokens derivados a emitir en el CSS inline (sin el prefijo --cnx-).
 *
 * @param array $c Colores base YA saneados: accent, accent_dark, bg, surface, text, text_2.
 * @return array token => valor CSS.
 */
function clipnuvex_derived_tokens( $c ) {
	$out = array();

	$accent      = isset( $c['accent'] ) ? $c['accent'] : '#7C5CFF';
	$accent_dark = isset( $c['accent_dark'] ) ? $c['accent_dark'] : '#5B3CE0';
	$bg          = isset( $c['bg'] ) ? $c['bg'] : '#0a0910';
	$surface     = isset( $c['surface'] ) ? $c['surface'] : '#15131e';
	$text        = isset( $c['text'] ) ? $c['text'] : '#f1f3f8';
	$text_2      = isset( $c['text_2'] ) ? $c['text_2'] : '#aab2c6';

	$accent_custom  = clipnuvex_color_customized( 'accent', $accent );
	$bg_custom      = clipnuvex_color_customized( 'bg', $bg );
	$surface_custom = clipnuvex_color_customized( 'surface', $surface );
	$text_custom    = clipnuvex_color_customized( 'text', $text );

	// Valores efectivos que otras familias necesitan (default literal si no
	// se personalizaron, para que las mezclas coincidan con tokens.css).
	$bg_mid   = $bg_custom ? clipnuvex_color_step( $bg, 0.03 ) : '#0f0d18';
	$light_ui = $bg_custom && clipnuvex_luminance( $bg ) > 0.5; // Esquema claro.

	// ---- Familia del acento ----
	if ( $accent_custom ) {
		$dark_eff = clipnuvex_color_customized( 'accent_dark', $accent_dark )
			? $accent_dark
			: clipnuvex_color_mix( $accent, '#000000', 0.22 );
		$out['accent-rgb']        = clipnuvex_rgb_list( $accent );
		$out['accent-dark']       = $dark_eff;
		$out['accent-grad']       = 'linear-gradient(135deg,' . $accent . ',' . $dark_eff . ')';
		$out['accent-light-2']    = clipnuvex_color_mix( $accent, '#ffffff', 0.58 );
		$out['accent-deep']       = clipnuvex_color_mix( $accent, '#000000', 0.35 );
		$out['shadow-accent']     = '0 6px 18px ' . clipnuvex_rgba( $accent, '.4' );
		$out['shadow-card-hover'] = '0 26px 50px rgba(0, 0, 0, .6), 0 0 0 2px var(--cnx-accent), 0 0 44px ' . clipnuvex_rgba( $accent, '.32' );
		$out['ad-size-bg']        = clipnuvex_rgba( $accent, '.92' );
		// Banner del hero de búsqueda: acento sobre ancla oscura fija (texto
		// blanco legible en cualquier esquema).
		$out['hero-grad'] = 'linear-gradient(120deg,' . clipnuvex_color_mix( $accent, '#0a0910', 0.82 ) . ',' . clipnuvex_color_mix( $accent, '#0a0910', 0.62 ) . ' 58%,' . clipnuvex_color_mix( $accent, '#0a0910', 0.48 ) . ')';
		$out['on-accent'] = ( clipnuvex_luminance( $accent ) > 0.45 ) ? '#0a0910' : '#fff';
	}
	// Texto "acento claro" de chips/sorts/pestañas ACTIVAS y etiquetas de
	// categoría: sobre fondo oscuro es el acento aclarado; sobre un esquema
	// claro debe ser el acento OSCURECIDO (si no, lila sobre blanco ≈ 1.5:1).
	// Se emite si cambia el acento o si el esquema es claro (el default
	// literal solo vale para acento morado sobre fondo oscuro).
	if ( $accent_custom || $light_ui ) {
		$out['accent-light'] = $light_ui
			? clipnuvex_color_mix( $accent, '#000000', 0.32 )
			: clipnuvex_color_mix( $accent, '#ffffff', 0.42 );
	}

	// ---- Familia del fondo ----
	if ( $bg_custom ) {
		$out['bg-mid']             = $bg_mid;
		$out['body-grad']          = 'linear-gradient(180deg,' . $bg . ' 0%,' . $bg_mid . ' 50%,' . $bg . ' 100%)';
		$out['header-bg']          = clipnuvex_rgba( $bg, '.66' );
		$out['header-bg-scrolled'] = clipnuvex_rgba( clipnuvex_color_mix( $bg, '#000000', 0.02 ), '.96' );
		$out['bottomnav-bg']       = clipnuvex_rgba( $bg, '.93' );
		$out['footer-bg']          = clipnuvex_rgba( $bg, '.6' );
		$out['controls-bg']        = clipnuvex_rgba( $bg, '.9' );
		$out['badge-bg']           = clipnuvex_rgba( $bg, '.9' );
	}

	// ---- Familia de la superficie ----
	if ( $surface_custom ) {
		$out['surface-2']          = clipnuvex_color_step( $surface, 0.02 );
		$out['translucent']        = clipnuvex_rgba( $surface, '.5' );
		$out['translucent-strong'] = clipnuvex_rgba( $surface, '.6' );
		$out['slate-rgb']          = clipnuvex_rgb_list( $surface );
		$out['search-bg']          = clipnuvex_rgba( $surface, '.92' );
		$out['search-panel-bg']    = clipnuvex_rgba( $surface, '.98' );
		$out['tabs-grad']          = 'linear-gradient(135deg,' . clipnuvex_rgba( $surface, '.9' ) . ',' . clipnuvex_rgba( $bg_mid, '.95' ) . ')';
		$out['seo-bg']             = clipnuvex_rgba( $surface, '.45' );
	}
	if ( $bg_custom || $surface_custom ) {
		$out['drawer-grad'] = 'linear-gradient(135deg,' . $surface . ',' . $bg_mid . ')';
	}

	// ---- Familia del texto (grises, títulos, bordes) ----
	if ( $text_custom ) {
		$out['fg-rgb']      = clipnuvex_rgb_list( $text );
		$out['heading']     = $text;
		$out['text-strong'] = $text;
		if ( ! clipnuvex_color_customized( 'text_2', $text_2 ) ) {
			$out['text-2'] = clipnuvex_color_mix( $text, $bg, 0.3 );
		}
		// Grises secundarios: mezcla del texto hacia el fondo. En esquemas
		// claros la misma mezcla pierde contraste (el fondo es casi blanco), así
		// que se atenúa un 22% para mantener ≥ 4.5:1 (AA) en textos pequeños
		// (pie, migas, meta de la ficha).
		$k = $light_ui ? 0.78 : 1;
		$out['text-soft']     = clipnuvex_color_mix( $text, $bg, 0.15 * $k );
		$out['text-muted']    = clipnuvex_color_mix( $text, $bg, 0.35 * $k );
		$out['text-3']        = clipnuvex_color_mix( $text, $bg, 0.42 * $k );
		$out['text-3b']       = clipnuvex_color_mix( $text, $bg, 0.48 * $k );
		$out['text-4']        = clipnuvex_color_mix( $text, $bg, 0.6 * $k );
		$out['text-5']        = clipnuvex_color_mix( $text, $bg, 0.68 * $k );
		$out['border']        = clipnuvex_rgba( $text, '.07' );
		$out['border-2']      = clipnuvex_rgba( $text, '.1' );
		$out['border-3']      = clipnuvex_rgba( $text, '.12' );
		$out['border-strong'] = clipnuvex_rgba( $text, '.16' );
	}

	// ---- Campos opcionales del panel ----
	if ( function_exists( 'clipnuvex_option' ) ) {
		$header_bg = sanitize_hex_color( (string) clipnuvex_option( 'header_bg', '' ) );
		if ( $header_bg ) {
			$out['header-bg']          = clipnuvex_rgba( $header_bg, '.66' );
			$out['header-bg-scrolled'] = clipnuvex_rgba( $header_bg, '.96' );
			$out['footer-bg']          = clipnuvex_rgba( $header_bg, '.6' );
			$out['bottomnav-bg']       = clipnuvex_rgba( $header_bg, '.93' );
		}
		$preroll = sanitize_hex_color( (string) clipnuvex_option( 'preroll_color', '#fbbf24' ) );
		if ( $preroll && clipnuvex_color_customized( 'preroll_color', $preroll ) ) {
			$out['preroll-color'] = $preroll;
		}
	}

	return $out;
}

/**
 * Paleta del editor de bloques sincronizada con los colores del panel.
 *
 * theme.json es estático: sin esto el editor ofrecía la paleta morada por
 * defecto aunque el sitio usara otros colores. Solo actúa cuando algún color
 * base difiere de su default (con defaults devuelve el objeto INTACTO → salida
 * idéntica). Sustituye únicamente settings.color.palette — update_with() la
 * reemplaza entera, por eso van los 6 slugs de theme.json — y no toca styles.*.
 * Nota: para themes clásicos WP también imprime los presets
 * --wp--preset--color--* en el front; con colores personalizados pasan a
 * coincidir con los tokens del panel (coherente con 1.5.0).
 *
 * @param WP_Theme_JSON_Data $theme_json Datos del theme.json del theme.
 * @return WP_Theme_JSON_Data
 */
function clipnuvex_theme_json_palette( $theme_json ) {
	if ( ! function_exists( 'clipnuvex_option' ) || ! function_exists( 'clipnuvex_option_default' ) || ! is_object( $theme_json ) || ! method_exists( $theme_json, 'update_with' ) ) {
		return $theme_json;
	}

	$ids        = array( 'accent', 'accent_dark', 'bg', 'surface', 'text', 'text_2' );
	$c          = array();
	$customized = false;
	foreach ( $ids as $id ) {
		$val = sanitize_hex_color( (string) clipnuvex_option( $id ) );
		if ( ! $val ) {
			$val = (string) clipnuvex_option_default( $id );
		}
		$c[ $id ] = $val;
		if ( clipnuvex_color_customized( $id, $val ) ) {
			$customized = true;
		}
	}
	if ( ! $customized ) {
		return $theme_json;
	}

	// Acento oscuro efectivo: misma regla que clipnuvex_derived_tokens() —
	// el personalizado si lo hay; si solo cambió el acento, mezcla con negro.
	$dark_eff = $c['accent_dark'];
	if ( ! clipnuvex_color_customized( 'accent_dark', $dark_eff ) && clipnuvex_color_customized( 'accent', $c['accent'] ) ) {
		$dark_eff = clipnuvex_color_mix( $c['accent'], '#000000', 0.22 );
	}

	$palette = array(
		array( 'slug' => 'base',        'color' => $c['bg'],      'name' => __( 'Fondo base', 'clipnuvex' ) ),
		array( 'slug' => 'surface',     'color' => $c['surface'], 'name' => __( 'Superficie', 'clipnuvex' ) ),
		array( 'slug' => 'accent',      'color' => $c['accent'],  'name' => __( 'Acento', 'clipnuvex' ) ),
		array( 'slug' => 'accent-dark', 'color' => $dark_eff,     'name' => __( 'Acento oscuro', 'clipnuvex' ) ),
		array( 'slug' => 'text',        'color' => $c['text'],    'name' => __( 'Texto', 'clipnuvex' ) ),
		array( 'slug' => 'text-2',      'color' => $c['text_2'],  'name' => __( 'Texto secundario', 'clipnuvex' ) ),
	);

	return $theme_json->update_with(
		array(
			'version'  => 2,
			'settings' => array(
				'color' => array( 'palette' => $palette ),
			),
		)
	);
}
add_filter( 'wp_theme_json_data_theme', 'clipnuvex_theme_json_palette' );

/**
 * Al guardar el panel, invalida la caché del theme.json (WP 6.2+) para que el
 * editor refleje la paleta nueva también con object cache persistente.
 */
function clipnuvex_clean_theme_json_cache() {
	if ( function_exists( 'wp_clean_theme_json_cache' ) ) {
		wp_clean_theme_json_cache();
	}
}
add_action( 'update_option_clipnuvex_options', 'clipnuvex_clean_theme_json_cache' );
add_action( 'add_option_clipnuvex_options', 'clipnuvex_clean_theme_json_cache' );
