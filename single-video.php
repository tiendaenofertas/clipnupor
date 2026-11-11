<?php
/**
 * Vídeo individual: player + ficha + tags + relacionados + anuncios + sidebar.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();

	$cnx_id   = get_the_ID();
	$cnx_data = clipnuvex_get_video_data( $cnx_id );
	$cnx_tags = get_the_terms( $cnx_id, 'tag_video' );
	$cnx_tags = ( $cnx_tags && ! is_wp_error( $cnx_tags ) ) ? $cnx_tags : array();
	?>

	<?php
	clipnuvex_breadcrumbs(
		array_filter(
			array(
				array( 'label' => '‹ ' . clipnuvex_text( 'txt_back' ), 'url' => '', 'back' => true ),
				array( 'label' => __( 'Inicio', 'clipnuvex' ), 'url' => home_url( '/' ) ),
				$cnx_data['category'] ? array( 'label' => $cnx_data['category'], 'url' => $cnx_data['cat_link'] ) : null,
				array( 'label' => get_the_title() ),
			)
		)
	);
	?>

	<article class="cnx-single">
		<div class="cnx-single__main">

			<?php if ( clipnuvex_ad_has_content( 'video_mobile_top' ) ) : ?>
			<div class="cnx-single__mobile-ad cnx-only-mobile">
				<?php clipnuvex_ad( 'video_mobile_top' ); ?>
			</div>
			<?php endif; ?>

			<?php clipnuvex_player( $cnx_id ); ?>

			<?php if ( clipnuvex_ad_has_content( 'video_mobile_strip' ) ) : ?>
			<div class="cnx-only-mobile-flex" style="margin-top:16px;">
				<?php clipnuvex_ad( 'video_mobile_strip' ); ?>
			</div>
			<?php endif; ?>

			<div class="cnx-ficha">
				<div class="cnx-ficha__head">
					<div style="flex:1;min-width:240px;">
						<h1 class="cnx-ficha__title"><?php echo esc_html( get_the_title() ); ?></h1>
						<div class="cnx-ficha__meta">
							<?php if ( $cnx_data['year'] ) : ?>
								<span>
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
									<?php echo esc_html( $cnx_data['year'] ); ?>
								</span>
							<?php endif; ?>
							<?php if ( $cnx_data['lang'] ) : ?>
								<span>
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20a15 15 0 0 1 0-20"/></svg>
									<?php echo esc_html( $cnx_data['lang'] ); ?>
								</span>
							<?php endif; ?>
						</div>
					</div>
					<?php if ( $cnx_data['category'] ) : ?>
						<a class="cnx-ficha__cat" href="<?php echo esc_url( $cnx_data['cat_link'] ); ?>"><?php echo esc_html( $cnx_data['category'] ); ?></a>
					<?php endif; ?>
				</div>

				<?php if ( get_the_content() || has_excerpt() ) : ?>
					<div class="cnx-ficha__desc">
						<?php echo wp_kses_post( get_the_content() ? apply_filters( 'the_content', get_the_content() ) : wpautop( get_the_excerpt() ) ); ?>
					</div>
				<?php endif; ?>

				<?php if ( $cnx_tags ) : ?>
					<div class="cnx-ficha__tags">
						<span class="cnx-ficha__tags-label"><?php esc_html_e( 'Tags', 'clipnuvex' ); ?></span>
						<?php foreach ( $cnx_tags as $tag ) : ?>
							<a class="cnx-tag" href="<?php echo esc_url( get_term_link( $tag ) ); ?>">#<?php echo esc_html( $tag->name ); ?></a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<div class="cnx-ficha__actions">
					<button class="cnx-btn cnx-btn--primary" type="button" onclick="var o=document.querySelector('.cnx-screen__overlay')||document.querySelector('.cnx-screen__stage');if(o){if(o.click&&o.classList.contains('cnx-screen__overlay')){o.click();}o.scrollIntoView({behavior:'smooth',block:'center'});}">
						<span class="cnx-btn__play-tri" aria-hidden="true"></span>
						<?php echo esc_html( clipnuvex_text( 'txt_play' ) ); ?>
					</button>
					<button class="cnx-btn cnx-btn--ghost" type="button" onclick="(function(){if(navigator.share){navigator.share({title:document.title,url:location.href}).catch(function(){});}else if(navigator.clipboard){navigator.clipboard.writeText(location.href).catch(function(){});}})();">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/></svg>
						<?php echo esc_html( clipnuvex_text( 'txt_share' ) ); ?>
					</button>
				</div>
			</div>

			<?php if ( clipnuvex_ad_has_content( 'video_after' ) ) : ?>
			<div style="margin-top:22px;">
				<?php clipnuvex_ad( 'video_after' ); ?>
			</div>
			<?php endif; ?>

			<?php
			$cnx_show_related = ! function_exists( 'clipnuvex_is_on' ) || clipnuvex_is_on( 'show_related' );
			$cnx_rel_count    = function_exists( 'clipnuvex_option' ) ? (int) clipnuvex_option( 'related_count', 6 ) : 6;
			$cnx_related      = $cnx_show_related ? clipnuvex_related_videos( $cnx_id, $cnx_rel_count ) : null;
			if ( $cnx_related && $cnx_related->have_posts() ) :
				?>
				<section class="cnx-single__related">
					<div class="cnx-section-head">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
						<h2><?php echo esc_html( clipnuvex_text( 'txt_you_may' ) ); ?></h2>
					</div>
					<div class="cnx-grid cnx-grid--related">
						<?php
						while ( $cnx_related->have_posts() ) :
							$cnx_related->the_post();
							clipnuvex_card( get_the_ID(), 'poster' );
						endwhile;
						wp_reset_postdata();
						?>
					</div>
				</section>
			<?php
			endif;
			// Comentarios solo si están abiertos o existen (entradas migradas a
			// vídeo): un vídeo normal (cerrados, 0 comentarios) no imprime nada.
			if ( ( comments_open() || get_comments_number() ) && locate_template( 'comments.php' ) ) {
				comments_template();
			}
			?>
		</div>

		<?php if ( ! function_exists( 'clipnuvex_is_on' ) || clipnuvex_is_on( 'show_video_sidebar' ) ) : ?>
		<aside class="cnx-aside">
			<?php clipnuvex_ad( 'video_side_1' ); ?>

			<?php
			// Reutiliza la misma consulta de relacionados (sin segunda query).
			if ( isset( $cnx_related ) && $cnx_related && $cnx_related->have_posts() ) :
				$cnx_related->rewind_posts();
				?>
				<div class="cnx-aside__panel">
					<h2 class="cnx-aside__title"><?php echo esc_html( clipnuvex_text( 'txt_related' ) ); ?></h2>
					<div class="cnx-aside__list">
						<?php
						while ( $cnx_related->have_posts() ) :
							$cnx_related->the_post();
							clipnuvex_card( get_the_ID(), 'list' );
						endwhile;
						wp_reset_postdata();
						?>
					</div>
				</div>
			<?php endif; ?>

			<?php clipnuvex_ad( 'video_side_2' ); ?>
		</aside>
		<?php endif; ?>
	</article>

	<?php
endwhile;

get_footer();
