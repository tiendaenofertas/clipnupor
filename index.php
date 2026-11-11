<?php
/**
 * Plantilla de respaldo genérica.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="cnx-view">
	<?php if ( have_posts() ) : ?>
		<div class="cnx-section-head">
			<h1>
				<?php
				if ( is_archive() ) {
					the_archive_title();
				} else {
					echo esc_html( get_bloginfo( 'name' ) );
				}
				?>
			</h1>
		</div>
		<div class="cnx-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				if ( 'video' === get_post_type() ) {
					clipnuvex_card( get_the_ID(), 'poster' );
				} else {
					?>
					<a class="cnx-poster" href="<?php the_permalink(); ?>">
						<?php if ( has_post_thumbnail() ) : ?>
							<?php the_post_thumbnail( clipnuvex_card_spec()['size'], array( 'class' => 'cnx-poster__img is-loaded', 'loading' => 'lazy' ) ); ?>
						<?php endif; ?>
						<span class="cnx-poster__shade" aria-hidden="true"></span>
						<span class="cnx-poster__bottom"><span class="cnx-poster__title"><?php the_title(); ?></span></span>
					</a>
					<?php
				}
			endwhile;
			?>
		</div>
		<?php clipnuvex_pagination(); ?>
	<?php else : ?>
		<div class="cnx-noresults">
			<div class="cnx-noresults__title"><?php esc_html_e( 'No hay contenido todavía', 'clipnuvex' ); ?></div>
		</div>
	<?php endif; ?>
</div>

<?php
get_footer();
