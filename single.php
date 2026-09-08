<?php
/**
 * Plantilla de entrada genérica (post type "post" y cualquier CPT sin plantilla
 * propia). Los vídeos usan single-video.php.
 *
 * Hasta 1.5.8 no existía: las entradas caían en index.php, que solo mostraba
 * una tarjeta con el título y nunca el contenido. Misma maqueta y tipografía
 * de lectura que page.php (`.cnx-page` / `.cnx-prose`).
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
			<p class="cnx-page__meta">
				<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
			</p>
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
		if ( ( comments_open() || get_comments_number() ) && locate_template( 'comments.php' ) ) {
			comments_template();
		}
		?>
	</article>
	<?php
endwhile;

get_footer();
