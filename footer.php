<?php
/**
 * Pie del theme.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
</main><!-- #cnx-main -->

<?php if ( ! function_exists( 'clipnuvex_is_on' ) || clipnuvex_is_on( 'show_footer' ) ) : ?>
<footer class="cnx-footer">
	<div class="cnx-footer__inner">
		<div class="cnx-footer__brand">
			<?php clipnuvex_logo(); ?>
			<p class="cnx-footer__brand-desc">
				<?php echo esc_html( clipnuvex_text( 'footer_desc' ) ); ?>
			</p>
		</div>

		<div class="cnx-footer__col">
			<h2 class="cnx-footer__col-title"><?php echo esc_html( clipnuvex_text( 'footer_col_nav' ) ); ?></h2>
			<ul>
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Inicio', 'clipnuvex' ); ?></a></li>
				<li><a href="<?php echo esc_url( get_post_type_archive_link( 'video' ) ); ?>"><?php esc_html_e( 'Explorar', 'clipnuvex' ); ?></a></li>
				<li><a href="<?php echo esc_url( clipnuvex_categories_url() ); ?>"><?php esc_html_e( 'Categorías', 'clipnuvex' ); ?></a></li>
			</ul>
		</div>

		<div class="cnx-footer__col">
			<h2 class="cnx-footer__col-title"><?php echo esc_html( clipnuvex_text( 'footer_col_genres' ) ); ?></h2>
			<ul>
				<?php foreach ( clipnuvex_get_categories( 6 ) as $term ) : ?>
					<li><a href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php echo esc_html( $term->name ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>

		<?php
		// Columna legal: menú 'footer' si está asignado; si no, SOLO las páginas
		// legales que existan (clipnuvex_footer_legal_links). Sin menú ni páginas
		// se omite la columna entera para no dejar un título sin enlaces.
		$cnx_footer_menu = has_nav_menu( 'footer' );
		$cnx_legal_links = $cnx_footer_menu ? array() : clipnuvex_footer_legal_links();
		if ( $cnx_footer_menu || $cnx_legal_links ) :
			?>
		<div class="cnx-footer__col">
			<h2 class="cnx-footer__col-title"><?php echo esc_html( clipnuvex_text( 'footer_col_legal' ) ); ?></h2>
			<?php
			if ( $cnx_footer_menu ) {
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => false,
						'menu_class'     => '',
						'depth'          => 1,
						'fallback_cb'    => false,
					)
				);
			} else {
				?>
				<ul>
					<?php foreach ( $cnx_legal_links as $cnx_legal ) : ?>
						<li><a href="<?php echo esc_url( $cnx_legal['url'] ); ?>"><?php echo esc_html( $cnx_legal['label'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
				<?php
			}
			?>
		</div>
		<?php endif; ?>
	</div>

	<div class="cnx-footer__bottom">
		<span>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?>. <?php echo esc_html( clipnuvex_text( 'footer_rights' ) ); ?></span>
		<span><?php echo esc_html( clipnuvex_text( 'footer_tagline' ) ); ?></span>
	</div>
</footer>
<?php endif; ?>

<?php if ( ! function_exists( 'clipnuvex_is_on' ) || clipnuvex_is_on( 'show_bottom_nav' ) ) : ?>
<!-- Bottom nav móvil -->
<nav class="cnx-bottomnav" aria-label="<?php esc_attr_e( 'Navegación inferior', 'clipnuvex' ); ?>">
	<a class="cnx-bottomnav__item<?php echo ( is_front_page() || is_home() ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>">
		<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l9-8 9 8"></path><path d="M5 10v10h14V10"></path></svg>
		<span><?php esc_html_e( 'Inicio', 'clipnuvex' ); ?></span>
	</a>
	<a class="cnx-bottomnav__item<?php echo ( clipnuvex_is_categories_page() || is_tax() ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( clipnuvex_categories_url() ); ?>">
		<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
		<span><?php esc_html_e( 'Categorías', 'clipnuvex' ); ?></span>
	</a>
	<button class="cnx-bottomnav__item" type="button" onclick="document.getElementById('cnx-search-input').focus();window.scrollTo({top:0,behavior:'smooth'});">
		<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.2-3.2"></path></svg>
		<span><?php esc_html_e( 'Buscar', 'clipnuvex' ); ?></span>
	</button>
</nav>
<?php endif; ?>

<?php if ( ! function_exists( 'clipnuvex_is_on' ) || clipnuvex_is_on( 'show_back_to_top' ) ) : ?>
<button class="cnx-totop" type="button" aria-label="<?php esc_attr_e( 'Volver arriba', 'clipnuvex' ); ?>">
	<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 19V5"></path><path d="m5 12 7-7 7 7"></path></svg>
</button>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
