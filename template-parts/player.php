<?php
/**
 * Reproductor: póster + botón play, iframe lazy y pestañas de servidor.
 *
 * @package Clipnuvex
 *
 * @var array $args ['servers' => [], 'data' => []].
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$servers = isset( $args['servers'] ) ? $args['servers'] : array();
$d       = isset( $args['data'] ) ? $args['data'] : array();
$multi   = count( $servers ) > 1;
$first   = ! empty( $servers ) ? $servers[0]['url'] : '';
// ¿La fuente inicial es un [shortcode]? Su salida se renderiza server-side y
// gestiona su propia UI: sin overlay de play ni mensaje de "sin fuente".
$first_is_embed = ! empty( $servers[0]['type'] ) && 'shortcode' === $servers[0]['type'];
$backdrop = isset( $d['backdrop'] ) ? $d['backdrop'] : '';
if ( '' === $backdrop ) {
	// Póster de respaldo configurable (panel → Reproductor → Póster por defecto).
	$backdrop = (string) clipnuvex_option( 'default_poster', '' );
}
$quality  = isset( $d['quality'] ) ? $d['quality'] : 'HD';
$title    = isset( $d['title'] ) ? $d['title'] : '';
?>
<div class="cnx-screen">
	<?php if ( $multi ) : ?>
		<div class="cnx-screen__tabs">
			<span class="cnx-screen__tabs-label">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/></svg>
				<?php esc_html_e( 'Servidor:', 'clipnuvex' ); ?>
			</span>
			<div class="cnx-screen__servers" role="group" aria-label="<?php esc_attr_e( 'Servidores de vídeo', 'clipnuvex' ); ?>">
				<?php foreach ( $servers as $i => $s ) : ?>
					<button type="button" class="cnx-screen__server<?php echo 0 === $i ? ' is-active' : ''; ?>"
						data-src="<?php echo esc_url( $s['url'] ); ?>"
						<?php if ( ! empty( $s['type'] ) && 'shortcode' === $s['type'] ) : ?>data-embed="cnx-embed-<?php echo (int) $i; ?>"<?php endif; ?>
						aria-pressed="<?php echo 0 === $i ? 'true' : 'false'; ?>">
						<?php echo esc_html( $s['label'] ); ?>
						<?php if ( ! empty( $s['quality'] ) ) : ?>
							<span class="cnx-screen__server-q"><?php echo esc_html( $s['quality'] ); ?></span>
						<?php endif; ?>
					</button>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php
	/*
	 * Reserva de altura 16:9 EN LÍNEA (no en hoja de estilos). Es a prueba de
	 * producción: ningún minificador, plugin de optimización/caché ni bundle CSS
	 * obsoleto puede eliminarla ni anularla (el inline gana incluso sobre reglas
	 * con !important salvo aspect-ratio:auto!important, que nadie inyecta). Como
	 * todos los hijos del stage son position:absolute, esto evita que el
	 * reproductor colapse a 0 y "desaparezca tras unos segundos". min-height es el
	 * suelo de respaldo para navegadores sin soporte de aspect-ratio.
	 */
	?>
	<div class="cnx-screen__stage" style="position:relative;width:100%;aspect-ratio:16/9;min-height:160px;background:var(--cnx-bg);" data-src="<?php echo esc_url( $first ); ?>" data-title="<?php echo esc_attr( $title ); ?>">
		<?php if ( $backdrop ) : ?>
			<div class="cnx-screen__poster" style="background-image:url('<?php echo esc_url( $backdrop ); ?>');" aria-hidden="true"></div>
		<?php endif; ?>

		<?php if ( $first ) : ?>
			<?php /* translators: %s: título del vídeo. */ ?>
			<button type="button" class="cnx-screen__overlay" aria-label="<?php echo esc_attr( sprintf( __( 'Reproducir %s', 'clipnuvex' ), ( '' !== $title ? $title : get_bloginfo( 'name' ) ) ) ); ?>">
				<span class="cnx-screen__bigplay" aria-hidden="true"></span>
			</button>
			<?php if ( ! function_exists( 'clipnuvex_is_on' ) || clipnuvex_is_on( 'player_show_preroll' ) ) : ?>
				<span class="cnx-screen__notice" aria-hidden="true"><?php echo esc_html( clipnuvex_text( 'txt_preroll' ) ); ?></span>
			<?php endif; ?>
		<?php elseif ( ! $first_is_embed ) : ?>
			<div class="cnx-screen__overlay" style="cursor:default;">
				<span style="color:#aab2c6;font:600 14px/1 var(--cnx-font-body);"><?php esc_html_e( 'Este vídeo aún no tiene una fuente configurada.', 'clipnuvex' ); ?></span>
			</div>
		<?php endif; ?>

		<span class="cnx-screen__quality"><?php echo esc_html( $quality ); ?></span>

		<?php
		/*
		 * Fuentes de tipo [shortcode]: la salida del plugin se renderiza
		 * server-side (así sus scripts se encolan e inicializan con normalidad)
		 * dentro de un contenedor absoluto que llena el stage. Solo el activo es
		 * visible; el resto queda hidden y lazy-player.js los conmuta con las
		 * pestañas de servidor. Estilos INLINE a propósito: misma filosofía
		 * anti-colapso que el propio stage (a prueba de bundles CSS obsoletos).
		 * Van como ÚLTIMOS hijos del stage para quedar por encima del póster.
		 */
		foreach ( $servers as $i => $s ) :
			if ( empty( $s['type'] ) || 'shortcode' !== $s['type'] ) {
				continue;
			}
			?>
			<div class="cnx-screen__embed" id="cnx-embed-<?php echo (int) $i; ?>"
				style="position:absolute;inset:0;overflow:hidden;background:var(--cnx-bg);"
				<?php echo 0 === $i ? '' : 'hidden'; ?>>
				<?php
				// Salida HTML del plugin dueño del shortcode (tag ya validado como
				// registrado en clipnuvex_video_servers). Igual que en the_content,
				// es HTML de terceros por diseño: no se re-escapa.
				echo do_shortcode( $s['code'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div>
		<?php endforeach; ?>
	</div>
</div>
