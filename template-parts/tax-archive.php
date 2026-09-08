<?php
/**
 * Listado de archivo de taxonomía (categoría / tag): hero + orden + grid +
 * anuncios + bloque SEO + paginación. Compartido por ambas taxonomías.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cnx_term = get_queried_object();
if ( ! $cnx_term || empty( $cnx_term->term_id ) ) {
	return;
}

$is_category = ( 'categoria_video' === $cnx_term->taxonomy );
$cnx_count   = (int) $cnx_term->count;
$cnx_sort    = isset( $_GET['orden'] ) ? sanitize_key( wp_unslash( $_GET['orden'] ) ) : 'recientes';
$cnx_sorts   = clipnuvex_sort_options();
$cnx_img     = $is_category ? clipnuvex_term_image_url( $cnx_term->term_id, 'clipnuvex-backdrop' ) : '';
$cnx_grad    = clipnuvex_category_gradient( $cnx_term->name );
$cnx_desc    = term_description( $cnx_term );

// Migas.
clipnuvex_breadcrumbs(
	array(
		array( 'label' => __( 'Inicio', 'clipnuvex' ), 'url' => home_url( '/' ) ),
		array(
			'label' => $is_category ? __( 'Categorías', 'clipnuvex' ) : __( 'Tags', 'clipnuvex' ),
			'url'   => $is_category ? clipnuvex_categories_url() : home_url( '/' ),
		),
		array( 'label' => $cnx_term->name ),
	)
);
?>

<section class="cnx-hero" style="background-image:<?php echo esc_attr( $cnx_grad ); ?>;">
	<?php if ( $cnx_img ) : ?>
		<img src="<?php echo esc_url( $cnx_img ); ?>" alt="" aria-hidden="true" width="1280" height="720" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.5;" loading="eager" fetchpriority="high">
	<?php endif; ?>
	<span class="cnx-hero__shade" aria-hidden="true"></span>
	<div class="cnx-hero__inner">
		<?php if ( function_exists( 'clipnuvex_is_on' ) && clipnuvex_is_on( 'show_hero_badge' ) ) : // Apagada por defecto: migas + H1 ya dan ese contexto. ?>
			<span class="cnx-hero__badge"><?php echo esc_html( $is_category ? __( 'Categoría', 'clipnuvex' ) : __( 'Tag', 'clipnuvex' ) ); ?></span>
		<?php endif; ?>
		<h1 class="cnx-hero__title"><?php echo esc_html( $cnx_term->name ); ?></h1>
		<?php if ( $cnx_desc ) : ?>
			<p class="cnx-hero__desc"><?php echo wp_kses_post( $cnx_desc ); ?></p>
		<?php else : ?>
			<p class="cnx-hero__desc">
				<?php
				/* translators: nombre de la categoría. */
				printf( esc_html__( 'Disfruta de lo mejor de %s en streaming. Catálogo curado, calidad de cine, reproductor propio y servidores alternativos. Nuevos vídeos cada semana, sin cortes.', 'clipnuvex' ), esc_html( $cnx_term->name ) );
				?>
			</p>
		<?php endif; ?>
	</div>
</section>

<section class="cnx-controls">
	<?php if ( ! function_exists( 'clipnuvex_is_on' ) || clipnuvex_is_on( 'show_sort_controls' ) ) : // Se omite la ROW entera para no dejar una fila vacía con padding; el orden por URL (?orden=) sigue operativo. ?>
	<div class="cnx-controls__row">
		<div class="cnx-sorts cnx-only-desktop">
			<?php foreach ( $cnx_sorts as $key => $label ) : ?>
				<a class="cnx-sort<?php echo $cnx_sort === $key ? ' is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'orden', $key, remove_query_arg( 'paged' ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</div>
		<select class="cnx-sort-select cnx-only-mobile" aria-label="<?php esc_attr_e( 'Ordenar', 'clipnuvex' ); ?>">
			<?php foreach ( $cnx_sorts as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $cnx_sort, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<?php endif; ?>
	<div class="cnx-controls__chips cnx-scroll cnx-hide">
		<a class="cnx-chip" href="<?php echo esc_url( clipnuvex_categories_url() ); ?>"><?php esc_html_e( 'Todo', 'clipnuvex' ); ?></a>
		<?php foreach ( clipnuvex_get_categories( 0 ) as $chip ) : ?>
			<a class="cnx-chip<?php echo ( $is_category && $chip->term_id === $cnx_term->term_id ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( get_term_link( $chip ) ); ?>"><?php echo esc_html( $chip->name ); ?></a>
		<?php endforeach; ?>
	</div>
</section>

<div class="cnx-view">
	<div class="cnx-results-row">
		<div class="cnx-results-count">
			<?php
			printf(
				/* translators: %s: número de vídeos. */
				wp_kses_post( _n( 'Mostrando <strong>%s</strong> vídeo', 'Mostrando <strong>%s</strong> vídeos', $cnx_count, 'clipnuvex' ) ),
				esc_html( number_format_i18n( $cnx_count ) )
			);
			?>
		</div>
	</div>

	<?php if ( clipnuvex_ad_has_content( 'tax_mobile' ) ) : ?>
	<div class="cnx-only-mobile-flex" style="margin-bottom:24px;">
		<?php clipnuvex_ad( 'tax_mobile' ); ?>
	</div>
	<?php endif; ?>

	<?php if ( have_posts() ) : ?>
		<div class="cnx-grid">
			<?php
			$cnx_i = 0;
			while ( have_posts() ) :
				the_post();
				$cnx_i++;
				// index → las primeras tarjetas (above-the-fold) cargan eager (LCP).
				clipnuvex_card( get_the_ID(), 'poster', array( 'index' => $cnx_i ) );
				if ( 10 === $cnx_i && clipnuvex_ad_has_content( 'tax_grid' ) ) :
					?>
					<div class="cnx-grid__ad"><?php clipnuvex_ad( 'tax_grid' ); ?></div>
					<?php
				endif;
			endwhile;
			?>
		</div>

		<?php if ( clipnuvex_ad_has_content( 'tax_bottom' ) ) : ?>
		<div style="margin-top:34px;">
			<?php clipnuvex_ad( 'tax_bottom' ); ?>
		</div>
		<?php endif; ?>

		<?php clipnuvex_pagination(); ?>
	<?php else : ?>
		<div class="cnx-noresults">
			<div class="cnx-noresults__title"><?php esc_html_e( 'No se encontraron vídeos', 'clipnuvex' ); ?></div>
			<div class="cnx-noresults__sub"><?php esc_html_e( 'Prueba con otra búsqueda o género.', 'clipnuvex' ); ?></div>
		</div>
	<?php endif; ?>

	<?php
	// Bloque SEO: contenido resuelto por categoría (term meta) con fallback a
	// los textos globales del panel. Ver clipnuvex_seo_block_data().
	$cnx_seo = ( $is_category && function_exists( 'clipnuvex_seo_block_data' ) ) ? clipnuvex_seo_block_data( $cnx_term ) : array( 'show' => false );
	?>
	<?php if ( ! empty( $cnx_seo['show'] ) ) : ?>
		<?php $cnx_seo_allowed = array( 'strong' => array(), 'em' => array() ); ?>
		<section class="cnx-seo-block">
			<h2><?php echo esc_html( $cnx_seo['title'] ); ?></h2>
			<p><?php echo esc_html( $cnx_seo['intro'] ); ?></p>
			<ul>
				<?php foreach ( $cnx_seo['points'] as $cnx_point ) : ?>
					<li><?php echo wp_kses( $cnx_point, $cnx_seo_allowed ); ?></li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>
</div>
