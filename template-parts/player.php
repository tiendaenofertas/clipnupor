<?php
/**
 * Reproductor: pestañas Opción 1, 2, 3 perfectamente alineadas + reproducción a pantalla completa.
 *
 * @package Clipnuvex
 *
 * @var array $args ['servers' => [], 'data' => []].
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = get_the_ID();
$servers = isset( $args['servers'] ) ? $args['servers'] : array();
$d       = isset( $args['data'] ) ? $args['data'] : array();

/*
|---------------------------------------------------------------------------------------------------
| 1. CARGA DIRECTA DE OPCIÓN 1, 2 Y 3 (100% Compatible con Clipnuvex y WP-Script)
|---------------------------------------------------------------------------------------------------
*/
$e1 = get_post_meta( $post_id, 'embed', true ) ?: get_post_meta( $post_id, 'video_embed_code_1', true );
$e2 = get_post_meta( $post_id, 'embed_2', true ) ?: get_post_meta( $post_id, 'video_embed_code_2', true );
$e3 = get_post_meta( $post_id, 'embed_3', true ) ?: get_post_meta( $post_id, 'video_embed_code_3', true );

// Fallback por líneas de texto si se guardaron en campos nativos
if ( empty( $e1 ) && empty( $e2 ) && empty( $e3 ) ) {
	$raw_lines = get_post_meta( $post_id, '_video_embed', true ) ?: get_post_meta( $post_id, 'video_url', true );
	if ( $raw_lines ) {
		$lines = array_filter( array_map( 'trim', explode( "\n", $raw_lines ) ) );
		if ( ! empty( $lines[0] ) ) $e1 = $lines[0];
		if ( ! empty( $lines[1] ) ) $e2 = $lines[1];
		if ( ! empty( $lines[2] ) ) $e3 = $lines[2];
	}
}

$custom_servers = array();
if ( ! empty( $e1 ) ) {
	$custom_servers[] = array(
		'label' => __( 'Opción 1', 'clipnuvex' ),
		'code'  => $e1,
	);
}
if ( ! empty( $e2 ) ) {
	$custom_servers[] = array(
		'label' => __( 'Opción 2', 'clipnuvex' ),
		'code'  => $e2,
	);
}
if ( ! empty( $e3 ) ) {
	$custom_servers[] = array(
		'label' => __( 'Opción 3', 'clipnuvex' ),
		'code'  => $e3,
	);
}

if ( ! empty( $custom_servers ) ) {
	$servers = $custom_servers;
}

$backdrop = isset( $d['backdrop'] ) ? $d['backdrop'] : '';
if ( '' === $backdrop && function_exists( 'clipnuvex_option' ) ) {
	$backdrop = (string) clipnuvex_option( 'default_poster', '' );
}
$quality = isset( $d['quality'] ) ? $d['quality'] : 'HD';
?>

<div class="cnx-player-master-container" style="position: relative; width: 100%; margin-bottom: 25px; clear: both;">

	<!-- Barra de Opciones 1, 2 y 3 (Separada e independiente del cuadro de vídeo) -->
	<?php if ( count( $servers ) > 1 ) : ?>
		<div class="cnx-server-tabs-bar" style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; width: 100%; z-index: 10; position: relative;">
			<?php foreach ( $servers as $i => $s ) : ?>
				<button type="button" 
					class="cnx-server-tab-btn<?php echo 0 === $i ? ' is-active' : ''; ?>"
					data-index="<?php echo (int) $i; ?>"
					style="background: <?php echo 0 === $i ? '#6c5ce7' : '#1e1e2d'; ?>; color: #ffffff; border: 1px solid rgba(255,255,255,0.12); padding: 8px 18px; border-radius: 7px; font-weight: 600; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; transition: all 0.2s ease; outline: none; box-shadow: 0 2px 6px rgba(0,0,0,0.2);">
					<span class="cnx-dot" style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: <?php echo 0 === $i ? '#55efc4' : '#ff0600'; ?>; margin-right: 8px;"></span>
					<?php echo esc_html( $s['label'] ); ?>
				</button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<!-- Escenario 16:9 donde se reproduce el vídeo -->
	<div class="cnx-screen__stage" id="cnx-player-stage" style="position:relative; width:100%; aspect-ratio:16/9; min-height:160px; background:#000000; border-radius:12px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.5); clear: both;">
		
		<!-- Póster inicial -->
		<?php if ( $backdrop ) : ?>
			<div class="cnx-screen__poster" id="cnx-stage-poster" style="position:absolute; inset:0; background-size:cover; background-position:center; background-image:url('<?php echo esc_url( $backdrop ); ?>'); z-index:2;" aria-hidden="true"></div>
		<?php endif; ?>

		<!-- Botón de Play Central (CORREGIDO: sintaxis cerrada e integrado con ocultamiento limpio) -->
		<button type="button" class="cnx-screen__overlay" id="cnx-stage-play-btn" style="position:absolute; inset:0; z-index:3; background:rgba(0,0,0,0.3); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center;" aria-label="Reproducir vídeo">
			<span class="cnx-screen__bigplay" aria-hidden="true"></span>
		</button>

		<span class="cnx-screen__quality" style="position:absolute; top:12px; right:12px; z-index:4; background:#ff2a5f; color:#fff; padding:3px 8px; border-radius:4px; font-size:11px; font-weight:700;"><?php echo esc_html( $quality ); ?></span>

		<!-- Pantallas de los Servidores / Embeds -->
		<?php foreach ( $servers as $i => $s ) : 
			$raw_code = ! empty( $s['code'] ) ? $s['code'] : '';
			$formatted_code = '';

			// Convierte URL en iframe y garantiza autoplay seguro
			if ( strpos( $raw_code, '<iframe' ) === false && filter_var( $raw_code, FILTER_VALIDATE_URL ) ) {
				if ( preg_match( '/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/i', $raw_code, $matches ) ) {
					$formatted_code = '<iframe src="https://www.youtube.com/embed/' . esc_attr( $matches[1] ) . '?autoplay=1" frameborder="0" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>';
				} else {
					$formatted_code = '<iframe src="' . esc_url( $raw_code ) . '" frameborder="0" allowfullscreen allow="autoplay"></iframe>';
				}
			} elseif ( strpos( $raw_code, '[' ) !== false && strpos( $raw_code, '<iframe' ) === false ) {
				$formatted_code = do_shortcode( $raw_code );
			} else {
				$formatted_code = $raw_code;
			}

			// Forzado de responsividad para llenar el cuadro exacto
			if ( strpos( $formatted_code, '<iframe' ) !== false ) {
				$formatted_code = preg_replace( '/width=["\'][^"\']*["\']/i', 'width="100%"', $formatted_code );
				$formatted_code = preg_replace( '/height=["\'][^"\']*["\']/i', 'height="100%"', $formatted_code );
				if ( strpos( $formatted_code, 'style=' ) !== false ) {
					$formatted_code = preg_replace( '/style=["\']([^"\']*)["\']/i', 'style="position:absolute;top:0;left:0;width:100%!important;height:100%!important;border:0;$1"', $formatted_code );
				} else {
					$formatted_code = str_replace( '<iframe', '<iframe style="position:absolute;top:0;left:0;width:100%!important;height:100%!important;border:0;"', $formatted_code );
				}
			}
		?>
			<div class="cnx-source-layer" id="cnx-source-layer-<?php echo (int) $i; ?>"
				style="position:absolute; inset:0; width:100%; height:100%; background:#000; z-index:1; display:<?php echo 0 === $i ? 'block' : 'none'; ?>;">
				<?php if ( 0 === $i ) : ?>
					<div class="cnx-embed-content" style="width:100%; height:100%;"><?php echo $formatted_code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<?php else : ?>
					<template class="cnx-lazy-embed"><?php echo $formatted_code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
					<div class="cnx-embed-content" style="width:100%; height:100%;"></div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
</div>

<style>
/* Forzado de proporciones para impedir cualquier desborde sobre los botones */
.cnx-screen__stage iframe,
.cnx-source-layer iframe,
.cnx-embed-content iframe {
	position: absolute !important;
	top: 0 !important;
	left: 0 !important;
	width: 100% !important;
	height: 100% !important;
	border: 0 !important;
	margin: 0 !important;
	padding: 0 !important;
	display: block !important;
	object-fit: cover !important;
}
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
	const playBtn = document.getElementById('cnx-stage-play-btn');
	const poster = document.getElementById('cnx-stage-poster');
	const tabBtns = document.querySelectorAll('.cnx-server-tab-btn');
	const layers = document.querySelectorAll('.cnx-source-layer');

	function startPlayback() {
		if (playBtn) {
			playBtn.style.display = 'none';
			playBtn.style.pointerEvents = 'none';
		}
		if (poster) {
			poster.style.display = 'none';
		}
	}

	if (playBtn) {
		playBtn.addEventListener('click', startPlayback);
	}

	tabBtns.forEach(btn => {
		btn.addEventListener('click', function() {
			const idx = this.getAttribute('data-index');
			startPlayback();

			tabBtns.forEach(b => {
				b.classList.remove('is-active');
				b.style.background = '#1e1e2d';
				const dot = b.querySelector('.cnx-dot');
				if (dot) dot.style.background = '#ff0600';
			});

			this.classList.add('is-active');
			this.style.background = '#6c5ce7';
			const dot = this.querySelector('.cnx-dot');
			if (dot) dot.style.background = '#55efc4';

			layers.forEach((layer, lIdx) => {
				const ifr = layer.querySelector('iframe');
				if (lIdx == idx) {
					layer.style.display = 'block';
					layer.style.zIndex = '5';

					const tmpl = layer.querySelector('.cnx-lazy-embed');
					const content = layer.querySelector('.cnx-embed-content');
					if (tmpl && content && content.innerHTML.trim() === '') {
						content.innerHTML = tmpl.innerHTML;
					}
				} else {
					layer.style.display = 'none';
					layer.style.zIndex = '1';
					if (ifr) {
						const src = ifr.src;
						ifr.src = src;
					}
				}
			});
		});
	});
});
</script>
