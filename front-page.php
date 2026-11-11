<?php
/**
 * Home: chips dinámicos de Top 10 Tags + Novedades + anuncios + Shorts.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

// Portada estática si el admin lo configuró
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

// Obtener las 10 etiquetas más visitadas dinámicamente
$cnx_top_tags = function_exists( 'cnx_get_top_visited_tags' ) ? cnx_get_top_visited_tags( 10 ) : array();

// Enlace dinámico al directorio de tags
$tags_page_url = home_url( '/tags/' );
$tags_pages    = get_pages( array(
	'meta_key'   => '_wp_page_template',
	'meta_value' => 'page-tags.php',
	'number'     => 1,
) );
if ( ! empty( $tags_pages ) ) {
	$tags_page_url = get_permalink( $tags_pages[0]->ID );
}
?>

<div class="cnx-view cnx-home">

	<!-- BARRA DINÁMICA DE TOP 10 META TAGS MÁS VISITADAS -->
	<section class="cnx-home__chips">
		<div class="cnx-chips cnx-scroll cnx-hide">
			<a class="cnx-chip is-active" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php echo esc_html( clipnuvex_text( 'txt_todo' ) ); ?>
			</a>
			<?php if ( ! empty( $cnx_top_tags ) ) : ?>
				<?php foreach ( $cnx_top_tags as $tag_item ) : ?>
					<a class="cnx-chip" href="<?php echo esc_url( get_term_link( $tag_item ) ); ?>">
						<?php echo esc_html( $tag_item->name ); ?>
					</a>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<a class="cnx-home__viewall cnx-only-desktop" href="<?php echo esc_url( $tags_page_url ); ?>" style="color: #ff2a5f; text-decoration: none; font-weight: 700;">
			<?php echo esc_html( clipnuvex_text( 'txt_view_all' ) ); ?> &rarr;
		</a>
	</section>

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

					// Anuncio intercalado en la posición 10
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

	<!-- ==============================================================================================
	     SECCIÓN SLIDER HORIZONTAL DE SHORTS
	     ============================================================================================== -->
	<?php
	$shorts_page_url = home_url( '/shorts/' );
	$shorts_pages    = get_pages( array(
		'meta_key'   => '_wp_page_template',
		'meta_value' => 'template-shorts.php',
		'number'     => 1,
	) );
	if ( ! empty( $shorts_pages ) ) {
		$shorts_page_url = get_permalink( $shorts_pages[0]->ID );
	}

	$shorts_args = array(
		'post_type'      => array( 'video', 'post' ),
		'posts_per_page' => 15,
		'post_status'    => 'publish',
		'meta_query'     => array(
			array(
				'key'     => 'is_short',
				'value'   => 'on',
				'compare' => '=',
			),
		),
	);
	$shorts_query = new WP_Query( $shorts_args );

	if ( $shorts_query->have_posts() ) : ?>
		<div class="shorts-home-section" style="margin: 45px 0 30px; position: relative; width: 100%;">
			<div class="shorts-section-header" style="display: flex; align-items: center; margin-bottom: 18px;">
				<h3 style="margin: 0; font-size: 22px; font-weight: 800; color: #000; display: flex; align-items: center;">
					<span style="color: #ff2a5f; margin-right: 8px;"> </span> <?php esc_html_e( 'Shorts: Recomendado Para Ti', 'clipnuvex' ); ?>
				</h3>
			</div>
			
			<div class="shorts-slider-container" style="position: relative; width: 100%;">
				<button type="button" class="shorts-slider-arrow arrow-left" id="shorts-prev-btn" aria-label="Anterior">&#10094;</button>
				
				<div class="shorts-slider-track" id="shorts-slider-track">
					<?php while ( $shorts_query->have_posts() ) : $shorts_query->the_post(); ?>
						<a href="<?php echo esc_url( trailingslashit( $shorts_page_url ) ) . '#short-' . get_the_ID(); ?>" class="short-card-item">
							<?php if ( has_post_thumbnail() ) {
								the_post_thumbnail( 'medium', array( 'class' => 'short-card-img' ) );
							} else { ?>
								<div style="width:100%; height:100%; background:#12121a;"></div>
							<?php } ?>
							<div class="short-card-overlay">
								<span class="short-card-title"><?php echo wp_trim_words( get_the_title(), 6, '...' ); ?></span>
							</div>
						</a>
					<?php endwhile; wp_reset_postdata(); ?>
				</div>

				<button type="button" class="shorts-slider-arrow arrow-right" id="shorts-next-btn" aria-label="Siguiente">&#10095;</button>
			</div>
		</div>

		<style>
			.shorts-slider-arrow {
				display: none !important;
				position: absolute;
				top: 50%;
				transform: translateY(-50%);
				width: 44px;
				height: 44px;
				background: rgba(15, 15, 25, 0.9) !important;
				color: #fff !important;
				border: 2px solid rgba(255, 255, 255, 0.15) !important;
				border-radius: 50% !important;
				justify-content: center;
				align-items: center;
				cursor: pointer;
				z-index: 30;
				transition: all 0.25s ease;
				outline: none;
				box-shadow: 0 4px 14px rgba(0,0,0,0.6);
				font-size: 20px;
				font-weight: bold;
				line-height: 1;
			}
			.shorts-slider-arrow:hover {
				background: #ff2a5f !important;
				border-color: #ff2a5f !important;
				transform: translateY(-50%) scale(1.12);
			}
			.shorts-slider-arrow.arrow-left { left: 12px; }
			.shorts-slider-arrow.arrow-right { right: 12px; }

			@media (min-width: 768px) {
				.shorts-slider-arrow { display: flex !important; }
			}

			.shorts-slider-track {
				display: flex;
				gap: 14px;
				overflow-x: auto;
				scroll-snap-type: x mandatory;
				scroll-behavior: smooth;
				padding-bottom: 12px;
				padding-top: 4px;
				-webkit-overflow-scrolling: touch;
				scrollbar-width: none;
			}
			.shorts-slider-track::-webkit-scrollbar { display: none; }

			.short-card-item {
				flex: 0 0 200px;
				width: 200px;
				height: 290px;
				border-radius: 14px;
				overflow: hidden;
				position: relative;
				text-decoration: none;
				scroll-snap-align: start;
				box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
				transition: transform 0.25s cubic-bezier(0.25, 0.8, 0.25, 1), box-shadow 0.25s ease;
				background-color: #12121a;
			}
			@media (max-width: 576px) {
				.short-card-item {
					flex: 0 0 160px;
					width: 160px;
					height: 245px;
				}
			}
			.short-card-item:hover {
				transform: translateY(-4px) scale(1.02);
				box-shadow: 0 8px 22px rgba(255, 42, 95, 0.35);
				text-decoration: none;
			}

			.short-card-item .short-card-img {
				width: 100% !important;
				height: 100% !important;
				max-width: 100% !important;
				max-height: 100% !important;
				object-fit: cover !important;
				display: block !important;
				padding: 0 !important;
				margin: 0 !important;
				border: none !important;
				transition: transform 0.4s ease;
			}
			.short-card-item:hover .short-card-img { transform: scale(1.06); }

			.short-card-overlay {
				position: absolute;
				bottom: 0; left: 0; right: 0;
				background: linear-gradient(to top, rgba(0, 0, 0, 0.95) 0%, rgba(0, 0, 0, 0.45) 60%, transparent 100%);
				padding: 35px 12px 14px;
				pointer-events: none;
			}
			.short-card-title {
				color: #ffffff !important;
				font-size: 13px;
				font-weight: 600;
				line-height: 1.35;
				display: block;
				text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.9);
			}
		</style>

		<script>
		document.addEventListener("DOMContentLoaded", function() {
			const track = document.getElementById('shorts-slider-track');
			const prevBtn = document.getElementById('shorts-prev-btn');
			const nextBtn = document.getElementById('shorts-next-btn');

			if (track && prevBtn && nextBtn) {
				prevBtn.addEventListener('click', function() {
					track.scrollBy({ left: -645, behavior: 'smooth' });
				});
				nextBtn.addEventListener('click', function() {
					track.scrollBy({ left: 645, behavior: 'smooth' });
				});

				const evaluateArrows = () => {
					prevBtn.style.opacity = (track.scrollLeft <= 5) ? '0' : '1';
					prevBtn.style.pointerEvents = (track.scrollLeft <= 5) ? 'none' : 'auto';
					const atEnd = (track.scrollLeft + track.clientWidth >= track.scrollWidth - 5);
					nextBtn.style.opacity = atEnd ? '0' : '1';
					nextBtn.style.pointerEvents = atEnd ? 'none' : 'auto';
				};

				track.addEventListener('scroll', evaluateArrows);
				window.addEventListener('resize', evaluateArrows);
				setTimeout(evaluateArrows, 350);
			}
		});
		</script>
	<?php endif; ?>
</div>

<?php
get_footer();
