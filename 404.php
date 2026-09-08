<?php
/**
 * Error 404.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="cnx-view" style="text-align:center;padding-top:clamp(40px,8vw,90px);">
	<div style="font:700 clamp(60px,12vw,120px)/1 var(--cnx-font-display);background:var(--cnx-accent-grad);-webkit-background-clip:text;background-clip:text;color:transparent;">404</div>
	<h1 style="margin:14px 0 0;font:700 clamp(22px,3vw,32px) var(--cnx-font-display);color:#fff;"><?php echo esc_html( clipnuvex_text( 'txt_404_title' ) ); ?></h1>
	<p style="margin:12px auto 26px;max-width:480px;color:var(--cnx-text-2);font:500 15px/1.6 var(--cnx-font-body);"><?php echo esc_html( clipnuvex_text( 'txt_404_text' ) ); ?></p>
	<a class="cnx-btn cnx-btn--primary" style="display:inline-flex;max-width:240px;margin:0 auto;text-decoration:none;" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( clipnuvex_text( 'txt_404_btn' ) ); ?></a>
</div>

<?php
get_footer();
