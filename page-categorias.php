<?php
/**
 * Template Name: Clipnuvex — Todas las categorías
 *
 * Página "Todas las categorías": grid de términos con imagen y contador.
 * El theme la crea al activarse (slug "categorias" en español o "categories"
 * en inglés, con esta plantilla asignada); también puede asignarse a
 * cualquier página desde el editor. Se localiza por ID y por ambos slugs
 * (clipnuvex_find_categories_page), así que cambiar de idioma no la pierde.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$cnx_terms = clipnuvex_get_categories( 0 );
$cnx_total = wp_count_posts( 'video' );
$cnx_count = isset( $cnx_total->publish ) ? (int) $cnx_total->publish : 0;
?>

<?php
clipnuvex_breadcrumbs(
	array(
		array( 'label' => __( 'Inicio', 'clipnuvex' ), 'url' => home_url( '/' ) ),
		array( 'label' => __( 'Categorías', 'clipnuvex' ) ),
	)
);
?>

<div class="cnx-cats">
	<div class="cnx-cats__head">
		<h1><?php echo esc_html( clipnuvex_text( 'txt_cats_title' ) ); ?></h1>
		<p>
			<?php
			/*
			 * Sustitución literal de %1$s/%2$s (como el bloque SEO): un printf
			 * sobre texto editable rompería con un '%' suelto del admin
			 * ("100% gratis") — salida corrupta o ValueError en PHP 8.
			 */
			echo str_replace( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- todo escapado antes del reemplazo.
				array( '%1$s', '%2$s' ),
				array(
					esc_html( number_format_i18n( max( $cnx_count, 0 ) ) ),
					esc_html( number_format_i18n( count( $cnx_terms ) ) ),
				),
				esc_html( clipnuvex_text( 'txt_cats_subtitle' ) )
			);
			?>
		</p>
	</div>

	<?php if ( $cnx_terms ) : ?>
		<div class="cnx-cats__grid">
			<?php
			foreach ( $cnx_terms as $term ) :
				$img  = clipnuvex_term_image_url( $term->term_id, 'clipnuvex-backdrop' );
				$grad = clipnuvex_category_gradient( $term->name );
				?>
				<a class="cnx-catcard" href="<?php echo esc_url( get_term_link( $term ) ); ?>" style="background-image:<?php echo esc_attr( $grad ); ?>;">
					<?php if ( $img ) : ?>
						<img class="cnx-catcard__img" src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $term->name ); ?>" loading="lazy" decoding="async">
					<?php endif; ?>
					<span class="cnx-catcard__shade" aria-hidden="true"></span>
					<span class="cnx-catcard__body">
						<span class="cnx-catcard__icon" aria-hidden="true">
							<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
						</span>
						<span class="cnx-catcard__name"><?php echo esc_html( $term->name ); ?></span>
						<span class="cnx-catcard__count">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
							<?php
							/* translators: número de vídeos en la categoría. */
							printf( esc_html__( '%s vídeos', 'clipnuvex' ), esc_html( number_format_i18n( $term->count ) ) );
							?>
						</span>
					</span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="cnx-noresults">
			<div class="cnx-noresults__title"><?php esc_html_e( 'Aún no hay categorías', 'clipnuvex' ); ?></div>
		</div>
	<?php endif; ?>
</div>

<?php
get_footer();
