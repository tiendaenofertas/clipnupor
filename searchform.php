<?php
/**
 * Formulario de búsqueda (limita a vídeos).
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$cnx_sid = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'cnx-s-' ) : 'cnx-s-' . wp_rand();
?>
<form role="search" method="get" class="cnx-searchform" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="cnx-sr" for="<?php echo esc_attr( $cnx_sid ); ?>"><?php esc_html_e( 'Buscar:', 'clipnuvex' ); ?></label>
	<input id="<?php echo esc_attr( $cnx_sid ); ?>" type="search" class="cnx-search__input" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Buscar vídeos, categorías…', 'clipnuvex' ); ?>">
	<button type="submit" class="cnx-sr"><?php esc_html_e( 'Buscar', 'clipnuvex' ); ?></button>
</form>
