<?php
/**
 * Tarjeta de vídeo — variante lista (relacionados sidebar).
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
?>
<a class="cnx-listcard" href="<?php echo esc_url( $d['permalink'] ); ?>">
	<span class="cnx-listcard__thumb">
		<?php if ( $d['poster'] ) : ?>
			<img src="<?php echo esc_url( $d['poster'] ); ?>" alt="<?php echo esc_attr( $d['title'] ); ?>" loading="lazy" decoding="async" width="62" height="93">
		<?php endif; ?>
	</span>
	<span class="cnx-listcard__body">
		<?php if ( $d['category'] ) : ?>
			<span class="cnx-listcard__cat"><?php echo esc_html( $d['category'] ); ?></span>
		<?php endif; ?>
		<span class="cnx-listcard__title"><?php echo esc_html( $d['title'] ); ?></span>
		<span class="cnx-listcard__meta"><?php echo esc_html( trim( ( $d['year'] ? $d['year'] : '' ) . ( $d['year'] && $d['quality'] ? ' · ' : '' ) . ( $d['quality'] ? $d['quality'] : '' ) ) ); ?></span>
	</span>
</a>
