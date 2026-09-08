<?php
/**
 * Comentarios (páginas, entradas y vídeos migrados que los tengan o admitan).
 *
 * Las plantillas solo la cargan cuando comments_open() || get_comments_number():
 * un vídeo normal (comentarios cerrados, 0 comentarios) no imprime nada.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="cnx-comments">
	<?php if ( have_comments() ) : ?>
		<h2 class="cnx-comments__title">
			<?php
			$cnx_n = get_comments_number();
			/* translators: %s: número de comentarios. */
			printf( esc_html( _n( '%s comentario', '%s comentarios', $cnx_n, 'clipnuvex' ) ), esc_html( number_format_i18n( $cnx_n ) ) );
			?>
		</h2>
		<ol class="cnx-comments__list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'avatar_size' => 40,
					'short_ping'  => true,
				)
			);
			?>
		</ol>
		<?php the_comments_navigation(); ?>
	<?php endif; ?>

	<?php if ( ! comments_open() && get_comments_number() ) : ?>
		<p class="cnx-comments__closed"><?php esc_html_e( 'Los comentarios están cerrados.', 'clipnuvex' ); ?></p>
	<?php endif; ?>

	<?php
	comment_form(
		array(
			'class_container' => 'cnx-comments__form',
			'title_reply'     => __( 'Deja un comentario', 'clipnuvex' ),
		)
	);
	?>
</section>
