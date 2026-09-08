<?php
/**
 * Plantilla de página genérica (privacidad, términos, contacto, DMCA, etc.).
 *
 * Hasta 1.5.8 no existía: las páginas caían en index.php, que solo mostraba
 * una tarjeta con el título y NUNCA el contenido. Las páginas con plantilla
 * propia (page-categorias.php o la asignada desde el editor) no pasan por aquí.
 * Tipografía de lectura en `.cnx-prose` (views.css + reserva en critical.css).
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();

	clipnuvex_breadcrumbs(
		array(
			array( 'label' => __( 'Inicio', 'clipnuvex' ), 'url' => home_url( '/' ) ),
			array( 'label' => get_the_title() ),
		)
	);
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'cnx-page' ); ?>>
		<header class="cnx-page__head">
			<h1 class="cnx-page__title"><?php echo esc_html( get_the_title() ); ?></h1>
		</header>
		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="cnx-page__figure">
				<?php the_post_thumbnail( 'clipnuvex-backdrop', array( 'class' => 'cnx-page__img', 'loading' => 'eager', 'fetchpriority' => 'high' ) ); ?>
			</figure>
		<?php endif; ?>
		<div class="cnx-page__content cnx-prose">
			<?php
			the_content();
			wp_link_pages(
				array(
					'before' => '<nav class="cnx-page__pages">' . esc_html__( 'Páginas:', 'clipnuvex' ) . ' ',
					'after'  => '</nav>',
				)
			);
			?>
		</div>
		<?php
		// Comentarios solo si están abiertos (o existen) y el theme tiene comments.php.
		if ( ( comments_open() || get_comments_number() ) && locate_template( 'comments.php' ) ) {
			comments_template();
		}
		?>
	</article>
	<?php
endwhile;

get_footer();
