<?php
/**
 * Home: chips de categorías + Novedades + anuncios + paginación numérica.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

// Si el administrador ha asignado una página estática como portada, se respeta
// su contenido en lugar de mostrar el grid de Novedades.
if ( 'page' === get_option( 'show_on_front' ) ) :
	while ( have_posts() ) :
		the_post();
		?>
		<div class="cnx-view cnx-page">
			<article <?php post_class(); ?>>
				<?php if ( ! is_page_template( 'page-categorias.php' ) ) : ?>
					<h1 class="cnx-hero__title" style="margin-bottom:24px;"><?php echo esc_html( get_the_title() ); ?></h1>
				<?php endif; ?>
				<div class="cnx-entry-content"><?php the_content(); ?></div>
			</article>
		</div>
		<?php
	endwhile;
	get_footer();
	return;
endif;

$cnx_categories = clipnuvex_get_categories( 8 );
?>

<div class="cnx-view cnx-home">

	<?php if ( clipnuvex_is_on( 'show_home_chips' ) ) : ?>
	<section class="cnx-home__chips">
		<div class="cnx-chips cnx-scroll cnx-hide">
			<a class="cnx-chip is-active" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( clipnuvex_text( 'txt_todo' ) ); ?></a>
			<?php foreach ( $cnx_categories as $term ) : ?>
				<a class="cnx-chip" href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php echo esc_html( $term->name ); ?></a>
			<?php endforeach; ?>
		</div>
		<a class="cnx-home__viewall cnx-only-desktop" href="<?php echo esc_url( clipnuvex_categories_url() ); ?>"><?php echo esc_html( clipnuvex_text( 'txt_view_all' ) ); ?></a>
	</section>
	<?php endif; ?>

	<?php if ( clipnuvex_ad_has_content( 'home_mobile' ) ) : ?>
	<div class="cnx-home__mobile-ad cnx-only-mobile">
		<?php clipnuvex_ad( 'home_mobile' ); ?>
	</div>
	<?php endif; ?>

	<section class="cnx-home__section">
		<div class="cnx-section-head">
			<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg>
			<h1><?php echo esc_html( clipnuvex_text( 'txt_novedades' ) ); ?></h1>
		</div>

		<?php if ( have_posts() ) : ?>
			<div class="cnx-grid">
				<?php
				$cnx_i = 0;
				while ( have_posts() ) :
					the_post();
					$cnx_i++;
					clipnuvex_card( get_the_ID(), 'poster', array( 'index' => $cnx_i ) );

					// Anuncios intercalados: tras 10 tarjetas un bloque rectangle (x2 desktop).
					if ( 10 === $cnx_i && clipnuvex_ad_has_content( 'home_grid' ) ) :
						?>
						<div class="cnx-grid__ad">
							<?php clipnuvex_ad( 'home_grid' ); ?>
						</div>
						<?php
					endif;
				endwhile;
				?>
			</div>

			<?php if ( clipnuvex_ad_has_content( 'home_mid' ) ) : ?>
			<div style="margin-top:30px;">
				<?php clipnuvex_ad( 'home_mid' ); ?>
			</div>
			<?php endif; ?>

			<?php clipnuvex_pagination(); ?>

			<?php if ( clipnuvex_ad_has_content( 'home_bottom' ) ) : ?>
			<div style="margin-top:30px;">
				<?php clipnuvex_ad( 'home_bottom' ); ?>
			</div>
			<?php endif; ?>

		<?php else : ?>
			<div class="cnx-noresults">
				<div class="cnx-noresults__title"><?php esc_html_e( 'Aún no hay vídeos', 'clipnuvex' ); ?></div>
				<div class="cnx-noresults__sub"><?php echo esc_html( clipnuvex_text( 'txt_empty_videos' ) ); ?></div>
			</div>
		<?php endif; ?>
	</section>
</div>

<?php
get_footer();
