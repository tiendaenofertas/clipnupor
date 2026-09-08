<?php
/**
 * Sistema de ad slots gestionable desde el admin.
 *
 * Cada posición del README es un slot. El admin pega el código de
 * Google Ad Manager / AdSense en el panel (Clipnuvex → Anuncios). Si el slot
 * está vacío se colapsa; con la vista previa activa muestra la medida IAB.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Catálogo de slots de anuncio por vista.
 *
 * @return array slot_id => ['label','format','view'].
 */
function clipnuvex_ad_slots() {
	return array(
		// Home.
		'home_mobile'       => array( 'label' => __( 'Home · móvil (tras chips)', 'clipnuvex' ), 'format' => 'rectangle', 'view' => 'home' ),
		'home_grid'         => array( 'label' => __( 'Home · dentro del grid', 'clipnuvex' ), 'format' => 'rectangle', 'view' => 'home' ),
		'home_mid'          => array( 'label' => __( 'Home · leaderboard intermedio', 'clipnuvex' ), 'format' => 'leaderboard', 'view' => 'home' ),
		'home_bottom'       => array( 'label' => __( 'Home · leaderboard final', 'clipnuvex' ), 'format' => 'leaderboard', 'view' => 'home' ),
		// Categoría / Tag.
		'tax_mobile'        => array( 'label' => __( 'Categoría · móvil (bajo contador)', 'clipnuvex' ), 'format' => 'rectangle', 'view' => 'tax' ),
		'tax_grid'          => array( 'label' => __( 'Categoría · dentro del grid', 'clipnuvex' ), 'format' => 'rectangle', 'view' => 'tax' ),
		'tax_bottom'        => array( 'label' => __( 'Categoría · leaderboard antes de paginación', 'clipnuvex' ), 'format' => 'leaderboard', 'view' => 'tax' ),
		// Vídeo.
		'video_mobile_top'  => array( 'label' => __( 'Vídeo · móvil (sobre el player)', 'clipnuvex' ), 'format' => 'rectangle', 'view' => 'video' ),
		'video_after'       => array( 'label' => __( 'Vídeo · leaderboard tras la ficha', 'clipnuvex' ), 'format' => 'leaderboard', 'view' => 'video' ),
		'video_mobile_strip'=> array( 'label' => __( 'Vídeo · banner móvil (320×50)', 'clipnuvex' ), 'format' => 'mobile', 'view' => 'video' ),
		'video_side_1'      => array( 'label' => __( 'Vídeo · sidebar rectangle', 'clipnuvex' ), 'format' => 'rectangle', 'view' => 'video' ),
		'video_side_2'      => array( 'label' => __( 'Vídeo · sidebar halfpage', 'clipnuvex' ), 'format' => 'halfpage', 'view' => 'video' ),
		// Búsqueda.
		'search_top'        => array( 'label' => __( 'Búsqueda · billboard superior', 'clipnuvex' ), 'format' => 'billboard', 'view' => 'search' ),
	);
}

/**
 * Medidas IAB por formato (ancho, alto).
 *
 * @param string $format Formato.
 * @return array ['w','h','label','min_h'].
 */
function clipnuvex_ad_format_spec( $format ) {
	$specs = array(
		'leaderboard' => array( 'w' => 728, 'h' => 90, 'label' => '728×90', 'min_h' => 100 ),
		'billboard'   => array( 'w' => 970, 'h' => 250, 'label' => '970×250', 'min_h' => 230 ),
		'rectangle'   => array( 'w' => 300, 'h' => 250, 'label' => '300×250', 'min_h' => 250 ),
		'halfpage'    => array( 'w' => 300, 'h' => 600, 'label' => '300×600', 'min_h' => 560 ),
		'skyscraper'  => array( 'w' => 160, 'h' => 600, 'label' => '160×600', 'min_h' => 560 ),
		'mobile'      => array( 'w' => 320, 'h' => 50, 'label' => '320×50', 'min_h' => 60 ),
		'native'      => array( 'w' => 0, 'h' => 0, 'label' => __( 'Nativo', 'clipnuvex' ), 'min_h' => 270 ),
	);
	return isset( $specs[ $format ] ) ? $specs[ $format ] : $specs['leaderboard'];
}

/**
 * Devuelve el código del anunciante guardado para un slot.
 *
 * @param string $slot_id ID del slot.
 * @return string
 */
function clipnuvex_ad_code( $slot_id ) {
	// Única fuente de verdad: el panel de opciones (option array clipnuvex_options).
	$code = clipnuvex_option( 'ad_' . $slot_id, '' );
	return is_string( $code ) ? $code : '';
}

/**
 * ¿Deben mostrarse los marcadores (placeholders) de los slots vacíos? Solo si
 * el admin activa el ajuste de vista previa. En producción los slots vacíos se
 * colapsan y liberan el espacio.
 *
 * @return bool
 */
function clipnuvex_ads_show_placeholders() {
	return clipnuvex_is_on( 'ads_show_placeholders' );
}

/**
 * ¿El slot va a renderizar algo (anuncio real o marcador de vista previa)? Las
 * plantillas lo usan para no envolver en un contenedor vacío que dejaría hueco.
 *
 * @param string $slot_id ID del slot.
 * @return bool
 */
function clipnuvex_ad_has_content( $slot_id ) {
	if ( '' !== trim( clipnuvex_ad_code( $slot_id ) ) ) {
		return true;
	}
	return clipnuvex_ads_show_placeholders();
}

/**
 * Renderiza un slot de anuncio.
 *
 * @param string $slot_id ID del slot (de clipnuvex_ad_slots).
 * @param array  $args    ['format'=>override, 'echo'=>bool, 'class'=>extra].
 * @return string HTML si echo=false.
 */
function clipnuvex_ad( $slot_id, $args = array() ) {
	$slots  = clipnuvex_ad_slots();
	$format = '';
	if ( isset( $slots[ $slot_id ] ) ) {
		$format = $slots[ $slot_id ]['format'];
	}
	if ( ! empty( $args['format'] ) ) {
		$format = $args['format'];
	}
	if ( ! $format ) {
		$format = 'leaderboard';
	}

	$echo  = ! isset( $args['echo'] ) || $args['echo'];
	$extra = isset( $args['class'] ) ? $args['class'] : '';
	$code  = clipnuvex_ad_code( $slot_id );
	$spec  = clipnuvex_ad_format_spec( $format );

	// Slot vacío SIN marcadores de vista previa → no se renderiza nada y el hueco
	// se libera (comportamiento de producción). El marcador dibujado solo aparece
	// si el ajuste "mostrar marcadores" del panel está ON.
	if ( '' === trim( $code ) && ! clipnuvex_ads_show_placeholders() ) {
		return '';
	}

	$max_w = $spec['w'] ? $spec['w'] . 'px' : '100%';

	// Reserva del hueco desde el PRIMER pintado (anti-CLS, igual que el player):
	// aspect-ratio exacto del formato IAB — la caja crece si el anuncio real es
	// más alto, pero nunca empieza en 0 y empuja el contenido al cargar la
	// creatividad. Para "native" (sin medida fija), min-height del spec.
	$reserve = '';
	if ( $spec['w'] && $spec['h'] ) {
		$reserve = 'aspect-ratio:' . (int) $spec['w'] . '/' . (int) $spec['h'] . ';';
	} elseif ( ! empty( $spec['min_h'] ) ) {
		$reserve = 'min-height:' . (int) $spec['min_h'] . 'px;';
	}

	ob_start();
	echo '<div class="cnx-ad-slot ' . esc_attr( $extra ) . '" data-ad-slot="' . esc_attr( $slot_id ) . '" data-ad-format="' . esc_attr( $format ) . '">';

	if ( '' !== trim( $code ) ) {
		// Código real del anunciante, ya saneado por capability AL GUARDAR
		// (clipnuvex_ad_kses). Aquí solo saneado ESTRUCTURAL — nunca por
		// capability: current_user_can() en render evaluaría al VISITANTE y el
		// slot se vería distinto para el admin logueado que para el público.
		echo '<div class="cnx-ad-code" style="max-width:' . esc_attr( $max_w ) . ';width:100%;' . esc_attr( $reserve ) . '">';
		echo wp_kses( clipnuvex_ad_prioritize_first( $code ), clipnuvex_ad_allowed_tags() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
	} else {
		echo clipnuvex_ad_placeholder( $format ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	echo '</div>';
	$html = ob_get_clean();

	if ( $echo ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return '';
	}
	return $html;
}

/**
 * El PRIMER anuncio impreso en la página suele estar above-the-fold y puede
 * ser el LCP: si su creatividad es una imagen lazy, se convierte en
 * prioritaria (eager + fetchpriority=high). Solo el primer <img> del primer
 * slot con código; los demás slots quedan lazy y los anuncios de script
 * (AdSense/GAM) no se tocan.
 *
 * @param string $code Código del anuncio (después pasa por clipnuvex_ad_kses).
 * @return string
 */
function clipnuvex_ad_prioritize_first( $code ) {
	static $done = false;
	if ( $done ) {
		return $code;
	}
	$done = true;

	if ( false === stripos( $code, '<img' ) ) {
		return $code;
	}

	$count = 0;
	return preg_replace_callback(
		'/<img\b[^>]*>/i',
		function ( $m ) use ( &$count ) {
			$count++;
			if ( 1 !== $count ) {
				return $m[0];
			}
			$tag = str_ireplace( array( 'loading="lazy"', "loading='lazy'" ), 'loading="eager"', $m[0] );
			if ( false === stripos( $tag, 'fetchpriority' ) ) {
				$tag = preg_replace( '/<img\b/i', '<img fetchpriority="high"', $tag, 1 );
			}
			return $tag;
		},
		$code
	);
}

/**
 * Hosts permitidos para `src` de <script>/<iframe> en el código de anuncios.
 *
 * Evita que el allowlist habilite XSS persistente mediante un <script src>
 * apuntando a un host arbitrario (importante en multisitio, donde un admin de
 * sitio tiene `edit_theme_options` pero no `unfiltered_html`). Filtrable.
 *
 * @return string[] Lista de sufijos de host permitidos.
 */
function clipnuvex_ad_allowed_hosts() {
	return apply_filters(
		'clipnuvex_ad_allowed_hosts',
		array(
			'googlesyndication.com',
			'googletagservices.com',
			'doubleclick.net',
			'google.com',
			'gstatic.com',
			'adsafeprotected.com',
			'amazon-adsystem.com',
			'adnxs.com',
			'pubmatic.com',
			'rubiconproject.com',
			'criteo.com',
			'media.net',
		)
	);
}

/**
 * ¿La URL apunta a un host de red publicitaria permitido?
 *
 * @param string $url URL a comprobar.
 * @return bool
 */
function clipnuvex_ad_host_allowed( $url ) {
	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( ! $host ) {
		return false;
	}
	$host = strtolower( $host );
	foreach ( clipnuvex_ad_allowed_hosts() as $allowed ) {
		if ( $host === $allowed || substr( $host, -( strlen( $allowed ) + 1 ) ) === '.' . $allowed ) {
			return true;
		}
	}
	return false;
}

/**
 * Allowlist estructural de tags/atributos habituales de ad networks. La usan
 * tanto el saneado de guardado (clipnuvex_ad_kses) como el saneado de render
 * (clipnuvex_ad → wp_kses), que por diseño es idéntico para admin y visitante.
 *
 * @return array Allowlist para wp_kses.
 */
function clipnuvex_ad_allowed_tags() {
	return array(
		'ins'    => array( 'class' => true, 'style' => true, 'data-ad-client' => true, 'data-ad-slot' => true, 'data-ad-format' => true, 'data-full-width-responsive' => true, 'data-ad-layout' => true, 'data-ad-layout-key' => true ),
		'script' => array( 'src' => true, 'async' => true, 'type' => true, 'crossorigin' => true ),
		'div'    => array( 'id' => true, 'class' => true, 'style' => true, 'data-google-query-id' => true ),
		'iframe' => array( 'src' => true, 'width' => true, 'height' => true, 'frameborder' => true, 'scrolling' => true, 'style' => true, 'allow' => true, 'title' => true ),
		'a'      => array( 'href' => true, 'target' => true, 'rel' => true, 'class' => true, 'style' => true ),
		'img'    => array( 'src' => true, 'width' => true, 'height' => true, 'alt' => true, 'style' => true, 'loading' => true, 'fetchpriority' => true, 'decoding' => true ),
	);
}

/**
 * ¿Es este cuerpo de <script> inline uno de los snippets canónicos de Google?
 *
 * Solo se permiten, con match estricto anclado (no "contiene"):
 * - AdSense: (adsbygoogle = window.adsbygoogle || []).push({});
 * - GAM:     googletag.cmd.push(function(){ googletag.display('<id>'); });
 *
 * Cualquier otro JS inline queda fuera del privilegio de un usuario SIN
 * unfiltered_html. Filtrable para redes adicionales.
 *
 * @param string $js Cuerpo del script (sin las etiquetas).
 * @return bool
 */
function clipnuvex_ad_inline_script_allowed( $js ) {
	$js      = trim( (string) $js );
	$allowed = (bool) preg_match( '#^\(\s*adsbygoogle\s*=\s*window\.adsbygoogle\s*\|\|\s*\[\s*\]\s*\)\s*\.push\(\s*\{\s*\}\s*\)\s*;?$#', $js )
		|| (bool) preg_match( '#^googletag\.cmd\.push\(\s*function\s*\(\s*\)\s*\{\s*googletag\.display\(\s*([\'"])[\w-]+\1\s*\)\s*;?\s*\}\s*\)\s*;?$#', $js );
	return (bool) apply_filters( 'clipnuvex_ad_inline_script_allowed', $allowed, $js );
}

/**
 * Sanitiza el código de anuncio AL GUARDAR, según la capability de quien guarda.
 *
 * Modelo: la política por capability se aplica una sola vez, en el guardado
 * (contexto del autor del dato); el render aplica después solo el saneado
 * estructural (clipnuvex_ad_allowed_tags), idéntico para admin y visitante.
 * Así lo que el panel acepta es exactamente lo que ven los visitantes — sin el
 * antiguo "al admin le funciona, al visitante le sale la caja vacía".
 *
 * Con `unfiltered_html` (admin single-site / super admin) se respeta su
 * capacidad y no se filtra. Sin ella (multisitio / roles menores):
 * - Los `src` de <script>/<iframe> se restringen a hosts de redes conocidas
 *   (clipnuvex_ad_allowed_hosts); cualquier otro se elimina.
 * - Los <script> SIN src solo se conservan si su cuerpo es un snippet inline
 *   canónico de Google (clipnuvex_ad_inline_script_allowed) — sin esto, el
 *   allowlist permitiría JS inline arbitrario = XSS persistente en multisitio.
 *
 * @param string $code Código.
 * @return string
 */
function clipnuvex_ad_kses( $code ) {
	$code = wp_kses( $code, clipnuvex_ad_allowed_tags() );

	// Quien tiene unfiltered_html (admin single-site / super admin) no se filtra.
	if ( current_user_can( 'unfiltered_html' ) ) {
		return $code;
	}

	// Elimina <script>/<iframe> cuyo src no sea de un host de anuncios permitido.
	$code = preg_replace_callback(
		'#<(script|iframe)\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\2[^>]*>#is',
		function ( $m ) {
			return clipnuvex_ad_host_allowed( $m[3] ) ? $m[0] : '';
		},
		$code
	);

	// Elimina los <script> inline (sin src) que no sean snippets canónicos.
	$code = preg_replace_callback(
		'#<script\b([^>]*)>(.*?)</script\s*>#is',
		function ( $m ) {
			if ( preg_match( '/\bsrc\s*=/i', $m[1] ) ) {
				return $m[0]; // Con src: ya lo decidió el filtro de hosts.
			}
			return clipnuvex_ad_inline_script_allowed( $m[2] ) ? $m[0] : '';
		},
		$code
	);

	return (string) $code;
}

/**
 * Genera el placeholder visual de un formato (réplica del prototipo).
 *
 * @param string $format Formato.
 * @return string
 */
function clipnuvex_ad_placeholder( $format ) {
	$creatives = array(
		'leaderboard' => array( 'brand' => 'AURIS', 'initial' => 'A', 'headline' => __( 'Sonido de cine en tu salón.', 'clipnuvex' ), 'cta' => __( 'Descubrir', 'clipnuvex' ) ),
		'billboard'   => array( 'brand' => 'NOVA STUDIO', 'initial' => 'N', 'headline' => __( 'Estrena tu próxima historia en 4K.', 'clipnuvex' ), 'cta' => __( 'Probar gratis', 'clipnuvex' ) ),
		'rectangle'   => array( 'brand' => 'VELVET', 'initial' => 'V', 'headline' => __( 'Estilo que se nota.', 'clipnuvex' ), 'cta' => __( 'Ver colección', 'clipnuvex' ) ),
		'halfpage'    => array( 'brand' => 'ATLAS VPN', 'initial' => 'A', 'headline' => __( 'Streaming sin límites ni cortes.', 'clipnuvex' ), 'cta' => __( 'Empezar ahora', 'clipnuvex' ) ),
		'skyscraper'  => array( 'brand' => 'PIXEL', 'initial' => 'P', 'headline' => __( 'Captura cada escena.', 'clipnuvex' ), 'cta' => __( 'Saber más', 'clipnuvex' ) ),
		'mobile'      => array( 'brand' => 'KAVA', 'initial' => 'K', 'headline' => __( 'Tu café, tu ritmo.', 'clipnuvex' ), 'cta' => __( 'Pedir', 'clipnuvex' ) ),
		'native'      => array( 'brand' => 'Promocionado', 'initial' => 'L', 'headline' => __( 'Lentes que ven más que tus ojos.', 'clipnuvex' ), 'cta' => __( 'Saber más', 'clipnuvex' ) ),
	);
	$c     = isset( $creatives[ $format ] ) ? $creatives[ $format ] : $creatives['leaderboard'];
	$spec  = clipnuvex_ad_format_spec( $format );
	$label = ( 'native' === $format ) ? __( 'Promocionado', 'clipnuvex' ) : __( 'Anuncio', 'clipnuvex' );

	ob_start();
	?>
	<span class="cnx-ad cnx-ad--placeholder cnx-ad--ph-<?php echo esc_attr( $format ); ?>" role="img" aria-label="<?php echo esc_attr( $label . ' ' . $spec['label'] ); ?>">
		<span class="cnx-ad__glow" aria-hidden="true"></span>
		<span class="cnx-ad__sheen" aria-hidden="true"></span>
		<span class="cnx-ad__label"><span class="cnx-ad__label-dot"></span><?php echo esc_html( $label ); ?></span>
		<span class="cnx-ad__size"><?php echo esc_html( $spec['label'] ); ?></span>
		<span class="cnx-ad__ph-logo" aria-hidden="true"><?php echo esc_html( $c['initial'] ); ?></span>
		<span>
			<span class="cnx-ad__ph-brand"><?php echo esc_html( $c['brand'] ); ?></span>
			<span class="cnx-ad__ph-head"><?php echo esc_html( $c['headline'] ); ?></span>
			<span class="cnx-ad__ph-cta"><?php echo esc_html( $c['cta'] ); ?> <span aria-hidden="true">→</span></span>
		</span>
	</span>
	<?php
	return ob_get_clean();
}
