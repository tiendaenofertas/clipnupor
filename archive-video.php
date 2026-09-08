<?php
/**
 * Archivo del CPT video ("Explorar"): grid + paginación.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<?php
clipnuvex_breadcrumbs(
	array(
		array( 'label' => __( 'Inicio', 'clipnuvex' ), 'url' => home_url( '/' ) ),
		array( 'label' => __( 'Explorar', 'clipnuvex' ) ),
	)
);
?>

<div class="cnx-view">
	<div class="cnx-section-head">
		<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
		<h1><?php esc_html_e( 'Explorar todos los vídeos', 'clipnuvex' ); ?></h1>
	</div>

	<?php if ( have_posts() ) : ?>
		<div class="cnx-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				clipnuvex_card( get_the_ID(), 'poster' );
			endwhile;
			?>
		</div>
		<?php clipnuvex_pagination(); ?>
	<?php else : ?>
		<div class="cnx-noresults">
			<div class="cnx-noresults__title"><?php esc_html_e( 'Aún no hay vídeos', 'clipnuvex' ); ?></div>
		</div>
	<?php endif; ?>
</div>

<?php
get_footer();
