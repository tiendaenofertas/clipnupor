<?php
/**
 * Tarjeta de vídeo — variante póster (2:3).
 *
 * @package Clipnuvex
 *
 * @var array $args ['data' => array de clipnuvex_get_video_data].
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$d = isset( $args['data'] ) ? $args['data'] : array();
if ( empty( $d ) ) {
	return;
}
$rank = isset( $args['rank'] ) ? $args['rank'] : '';
// Las primeras tarjetas (sobre el pliegue) son candidatas a LCP: se cargan con
// prioridad (eager + fetchpriority) en vez de lazy → mejora el LCP.
$cnx_index = isset( $args['index'] ) ? (int) $args['index'] : 99;
$cnx_eager = $cnx_index > 0 && $cnx_index <= 6;
// Orientación (póster 2:3 / horizontal 16:9) según el panel: solo cambian el
// tamaño de imagen, sus dimensiones y el marco (vía body.cnx-cards-wide).
$cnx_spec = clipnuvex_card_spec();
$cnx_img  = clipnuvex_card_image( $d['poster_id'] );
?>
<a class="cnx-poster" href="<?php echo esc_url( $d['permalink'] ); ?>" aria-label="<?php echo esc_attr( $d['title'] ); ?>">
	<?php if ( $cnx_img['url'] ) : ?>
		<img class="cnx-poster__img" src="<?php echo esc_url( $cnx_img['url'] ); ?>"
			alt="<?php echo esc_attr( $d['title'] ); ?>"
			<?php echo $cnx_eager ? 'loading="eager" fetchpriority="' . ( 1 === $cnx_index ? 'high' : 'auto' ) . '"' : 'loading="lazy"'; ?>
			decoding="async" width="<?php echo (int) $cnx_spec['w']; ?>" height="<?php echo (int) $cnx_spec['h']; ?>"
			<?php
			if ( $cnx_img['srcset'] ) {
				printf( ' srcset="%s" sizes="%s"', esc_attr( $cnx_img['srcset'] ), esc_attr( $cnx_img['sizes'] ) );
			}
			?>
		>
	<?php endif; ?>
	<div class="cnx-skel" aria-hidden="true"></div>
	<div class="cnx-poster__shade" aria-hidden="true"></div>

	<?php if ( $d['category'] ) : ?>
		<span class="cnx-poster__badge"><?php echo esc_html( $d['category'] ); ?></span>
	<?php endif; ?>

	<?php if ( '' !== $rank ) : ?>
		<span class="cnx-poster__rank">#<?php echo esc_html( $rank ); ?></span>
	<?php endif; ?>

	<div class="cnx-poster__bottom">
		<span class="cnx-poster__title"><?php echo esc_html( $d['title'] ); ?></span>
		<span class="cnx-poster__play" aria-hidden="true"></span>
	</div>
</a>
