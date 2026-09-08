<?php
/**
 * Cabecera del theme.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'cnx-body' ); ?>>
<?php wp_body_open(); ?>

<a class="cnx-skip" href="#cnx-main"><?php esc_html_e( 'Saltar al contenido', 'clipnuvex' ); ?></a>

<?php
$cnx_nav_items = clipnuvex_nav_items();
?>
<header class="cnx-header">
	<div class="cnx-header__inner">

		<?php clipnuvex_logo(); ?>

		<nav class="cnx-nav" aria-label="<?php esc_attr_e( 'Navegación principal', 'clipnuvex' ); ?>">
			<?php foreach ( $cnx_nav_items as $item ) : ?>
				<a class="cnx-nav__link<?php echo $item['active'] ? ' is-active' : ''; ?>" href="<?php echo esc_url( $item['url'] ); ?>"<?php echo $item['active'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $item['label'] ); ?></a>
			<?php endforeach; ?>
		</nav>

		<?php if ( ! function_exists( 'clipnuvex_is_on' ) || clipnuvex_is_on( 'show_search_bar' ) ) : ?>
		<?php // Form REAL (no un div): pulsar Enter navega a la página de resultados de forma nativa, con o sin JS. ?>
		<form class="cnx-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<div class="cnx-search__box">
				<span class="cnx-search__icon" aria-hidden="true">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.2-3.2"></path></svg>
				</span>
				<label class="cnx-sr" for="cnx-search-input"><?php esc_html_e( 'Buscar en Clipnuvex', 'clipnuvex' ); ?></label>
				<input
					id="cnx-search-input"
					class="cnx-search__input"
					type="search"
					name="s"
					value="<?php echo esc_attr( get_search_query() ); ?>"
					placeholder="<?php echo esc_attr( clipnuvex_text( 'txt_search_ph' ) ); ?>"
					autocomplete="off"
					role="combobox"
					aria-expanded="false"
					aria-autocomplete="list"
					aria-controls="cnx-search-panel"
				>
				<span class="cnx-search__kbd" aria-hidden="true">⌘K</span>
				<button type="submit" class="cnx-sr"><?php esc_html_e( 'Buscar', 'clipnuvex' ); ?></button>
			</div>
			<div id="cnx-search-panel" class="cnx-search__panel" role="listbox" aria-label="<?php esc_attr_e( 'Sugerencias de búsqueda', 'clipnuvex' ); ?>" hidden></div>
		</form>
		<?php endif; ?>

		<?php clipnuvex_language_switcher(); ?>

		<button class="cnx-burger" type="button" aria-label="<?php esc_attr_e( 'Abrir menú de navegación', 'clipnuvex' ); ?>" aria-expanded="false" aria-controls="cnx-drawer">
			<span></span><span></span><span></span>
		</button>
	</div>
</header>

<!-- Drawer móvil -->
<div id="cnx-drawer" class="cnx-drawer" hidden>
	<div class="cnx-drawer__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Menú', 'clipnuvex' ); ?>">
		<button class="cnx-drawer__close" type="button" aria-label="<?php esc_attr_e( 'Cerrar menú', 'clipnuvex' ); ?>">&times;</button>
		<div class="cnx-drawer__label"><?php esc_html_e( 'Navegación', 'clipnuvex' ); ?></div>
		<?php foreach ( $cnx_nav_items as $item ) : ?>
			<a class="cnx-drawer__link<?php echo $item['active'] ? ' is-active' : ''; ?>" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
		<?php endforeach; ?>
		<div class="cnx-drawer__divider"></div>
		<div class="cnx-drawer__label"><?php esc_html_e( 'Géneros', 'clipnuvex' ); ?></div>
		<?php foreach ( clipnuvex_get_categories( 12 ) as $term ) : ?>
			<a class="cnx-drawer__link" href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php echo esc_html( $term->name ); ?></a>
		<?php endforeach; ?>
	</div>
</div>

<main id="cnx-main" class="cnx-main" tabindex="-1">
