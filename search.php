<?php
/**
 * Búsqueda: billboard + categorías coincidentes + vídeos.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$cnx_query  = get_search_query();
$cnx_terms  = clipnuvex_search_terms( $cnx_query, 8 );
$cnx_total  = (int) $GLOBALS['wp_query']->found_posts;
?>

<?php
clipnuvex_breadcrumbs(
	array(
		array( 'label' => __( 'Inicio', 'clipnuvex' ), 'url' => home_url( '/' ) ),
		array( 'label' => __( 'Búsqueda', 'clipnuvex' ) ),
	)
);
?>

<section class="cnx-hero cnx-hero--search">
	<span class="cnx-hero__glow" aria-hidden="true"></span>
	<div class="cnx-hero__inner">
		<span class="cnx-hero__icon" aria-hidden="true">
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.2-3.2"></path></svg>
		</span>
		<div style="min-width:0;">
			<div class="cnx-hero__eyebrow"><?php echo esc_html( clipnuvex_text( 'txt_search_results' ) ); ?></div>
			<h1 class="cnx-hero__title">&ldquo;<?php echo esc_html( $cnx_query ); ?>&rdquo;</h1>
			<p class="cnx-hero__count">
				<?php
				printf(
					/* translators: %s: número de resultados. */
					esc_html( _n( '%s resultado en todo el catálogo', '%s resultados en todo el catálogo', $cnx_total, 'clipnuvex' ) ),
					esc_html( number_format_i18n( $cnx_total ) )
				);
				?>
			</p>
		</div>
	</div>
</section>

<div class="cnx-view">
	<?php if ( clipnuvex_ad_has_content( 'search_top' ) ) : ?>
	<div style="margin-bottom:22px;">
		<?php clipnuvex_ad( 'search_top' ); ?>
	</div>
	<?php endif; ?>

	<?php if ( ! empty( $cnx_terms ) ) : ?>
		<section style="margin-bottom:30px;">
			<div class="cnx-section-head">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
				<h2><?php esc_html_e( 'Categorías', 'clipnuvex' ); ?></h2>
			</div>
			<div style="display:flex;flex-wrap:wrap;gap:10px;">
				<?php foreach ( $cnx_terms as $term ) : ?>
					<a class="cnx-chip" href="<?php echo esc_url( get_term_link( $term ) ); ?>">
						<?php echo esc_html( $term->name ); ?>
						<span style="opacity:.7;margin-left:6px;"><?php echo esc_html( number_format_i18n( $term->count ) ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( have_posts() ) : ?>
		<section>
			<div class="cnx-section-head">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
				<h2><?php esc_html_e( 'Vídeos', 'clipnuvex' ); ?></h2>
			</div>
			<div class="cnx-grid">
				<?php
				$cnx_i = 0;
				while ( have_posts() ) :
					the_post();
					$cnx_i++;
					// index → las primeras tarjetas (above-the-fold) cargan eager (LCP).
					clipnuvex_card( get_the_ID(), 'poster', array( 'index' => $cnx_i ) );
				endwhile;
				?>
			</div>
			<?php clipnuvex_pagination(); ?>
		</section>
	<?php else : ?>
		<?php if ( empty( $cnx_terms ) ) : ?>
			<div class="cnx-noresults">
				<div class="cnx-noresults__title"><?php echo esc_html( clipnuvex_text( 'txt_no_videos' ) ); ?></div>
				<div class="cnx-noresults__sub"><?php echo esc_html( clipnuvex_text( 'txt_no_videos_sub' ) ); ?></div>
			</div>
		<?php endif; ?>

		<?php
		// "Quizás te interese": recomendaciones (populares/recientes, cacheadas
		// 1 h) cuando la búsqueda no devuelve resultados. Configurable desde el
		// panel: Clipnuvex → Buscador; título editable en Textos → Búsqueda.
		$cnx_recs = clipnuvex_is_on( 'show_search_recs' ) ? clipnuvex_search_recommendations() : array();
		if ( ! empty( $cnx_recs ) ) :
			?>
			<section style="margin-top:26px;">
				<div class="cnx-section-head">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 15.1 8.6 22 9.3 16.8 14 18.4 21 12 17.3 5.6 21 7.2 14 2 9.3 8.9 8.6 12 2"/></svg>
					<h2><?php echo esc_html( clipnuvex_text( 'txt_search_recs' ) ); ?></h2>
				</div>
				<div class="cnx-grid">
					<?php foreach ( $cnx_recs as $cnx_i => $cnx_rec_id ) : ?>
						<?php clipnuvex_card( $cnx_rec_id, 'poster', array( 'index' => $cnx_i + 1 ) ); ?>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php
		// "Explora por categorías": salida alternativa cuando la búsqueda no da
		// nada — chips con contador (solo categorías con contenido, máx. 12),
		// cacheadas 1 h por clipnuvex_get_categories(). Toggle y título en el panel.
		$cnx_explore = array();
		if ( clipnuvex_is_on( 'show_search_explore_cats' ) && function_exists( 'clipnuvex_get_categories' ) ) {
			foreach ( clipnuvex_get_categories() as $cnx_cat ) {
				if ( (int) $cnx_cat->count > 0 ) {
					$cnx_explore[] = $cnx_cat;
				}
				if ( count( $cnx_explore ) >= 12 ) {
					break;
				}
			}
		}
		if ( ! empty( $cnx_explore ) ) :
			?>
			<section style="margin-top:26px;">
				<div class="cnx-section-head">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
					<h2><?php echo esc_html( clipnuvex_text( 'txt_search_explore' ) ); ?></h2>
				</div>
				<div style="display:flex;flex-wrap:wrap;gap:10px;">
					<?php foreach ( $cnx_explore as $cnx_cat ) : ?>
						<a class="cnx-chip" href="<?php echo esc_url( get_term_link( $cnx_cat ) ); ?>">
							<?php echo esc_html( $cnx_cat->name ); ?>
							<span style="opacity:.7;margin-left:6px;"><?php echo esc_html( number_format_i18n( $cnx_cat->count ) ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>
	<?php endif; ?>
</div>

<?php
get_footer();
