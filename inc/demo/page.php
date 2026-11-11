<?php
/**
 * Pantalla "Apariencia → Modo Demo".
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * El Modo Demo se registra como submenú del menú de nivel superior "Clipnuvex"
 * (ver inc/admin/options-page.php). Aquí solo vive el renderizador y el handler.
 */

/**
 * Procesa las acciones de importar/revertir.
 *
 * @return array|null Resultado o null.
 */
function clipnuvex_demo_handle_action() {
	if ( empty( $_POST['clipnuvex_demo_action'] ) ) {
		return null;
	}
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'Permisos insuficientes.', 'clipnuvex' ) );
	}
	check_admin_referer( 'clipnuvex_demo' );

	$action   = sanitize_key( wp_unslash( $_POST['clipnuvex_demo_action'] ) );
	$importer = new Clipnuvex_Demo_Importer();

	if ( 'import' === $action ) {
		// La generación de imágenes puede tardar; ampliamos el límite.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		return $importer->import();
	}
	if ( 'revert' === $action ) {
		return $importer->revert();
	}
	return null;
}

/**
 * Renderiza la pantalla.
 */
function clipnuvex_demo_render_page() {
	$result   = clipnuvex_demo_handle_action();
	$imported = get_option( 'clipnuvex_demo_imported' );
	$gd_ok    = function_exists( 'imagecreatetruecolor' );
	?>
	<div class="wrap clipnuvex-demo">
		<h1><?php esc_html_e( 'Clipnuvex · Modo Demo', 'clipnuvex' ); ?></h1>

		<?php if ( $result && ! empty( $result['messages'] ) ) : ?>
			<div class="notice notice-<?php echo $result['ok'] ? 'success' : 'error'; ?> is-dismissible">
				<?php foreach ( $result['messages'] as $msg ) : ?>
					<p><?php echo esc_html( $msg ); ?></p>
				<?php endforeach; ?>
				<?php if ( ! empty( $result['counts'] ) ) : ?>
					<p><?php
						$parts = array();
						foreach ( $result['counts'] as $k => $v ) {
							$parts[] = esc_html( ucfirst( $k ) . ': ' . $v );
						}
						echo esc_html( implode( ' · ', $parts ) );
					?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! $gd_ok ) : ?>
			<div class="notice notice-warning">
				<p><?php esc_html_e( 'La extensión GD de PHP no está disponible. La importación funcionará pero los vídeos se crearán sin pósters generados. Pídele a tu hosting que active GD para pósters de muestra.', 'clipnuvex' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="clipnuvex-demo__card">
			<p class="clipnuvex-demo__lead">
				<?php esc_html_e( 'Con un solo clic, deja tu sitio idéntico al prototipo de Clipnuvex: ~36 vídeos de muestra, 12 categorías con imágenes, tags, página de categorías, menús y los ad slots colocados en sus posiciones. Todo el contenido demo queda marcado y es 100% reversible.', 'clipnuvex' ); ?>
			</p>

			<ul class="clipnuvex-demo__list">
				<li><?php esc_html_e( '12 categorías con imagen y descripción, 10 tags y ~36 vídeos con póster 2:3, backdrop, calidad, idioma, año y embed de muestra, todo en el idioma del sitio (inglés o español, según General → Idioma por defecto).', 'clipnuvex' ); ?></li>
				<li><?php esc_html_e( 'Página "Categorías", páginas legales completas y listas para usar (Privacidad, Términos, Cookies, Contacto y DMCA) rellenadas con el nombre del sitio, su URL y el correo del administrador, menú principal y de pie enlazando a esas páginas, y ajustes del theme (acento #7C5CFF).', 'clipnuvex' ); ?></li>
				<li><?php esc_html_e( 'Reimportar revierte antes la demo anterior: cambia el idioma del sitio y reimporta para tenerla en el otro idioma.', 'clipnuvex' ); ?></li>
				<li><?php esc_html_e( 'Ad slots con placeholders IAB en sus posiciones (728×90, 970×250, 300×250, 300×600, 320×50).', 'clipnuvex' ); ?></li>
			</ul>

			<div class="clipnuvex-demo__actions">
				<form method="post">
					<?php wp_nonce_field( 'clipnuvex_demo' ); ?>
					<input type="hidden" name="clipnuvex_demo_action" value="import">
					<button type="submit" class="button button-primary button-hero">
						<?php echo $imported ? esc_html__( 'Reimportar demo', 'clipnuvex' ) : esc_html__( 'Importar demo', 'clipnuvex' ); ?>
					</button>
				</form>

				<?php if ( $imported ) : ?>
					<form method="post" onsubmit="return confirm('<?php echo esc_js( __( '¿Seguro que quieres eliminar TODO el contenido demo? Tu contenido real no se verá afectado.', 'clipnuvex' ) ); ?>');">
						<?php wp_nonce_field( 'clipnuvex_demo' ); ?>
						<input type="hidden" name="clipnuvex_demo_action" value="revert">
						<button type="submit" class="button button-secondary button-hero clipnuvex-demo__revert">
							<?php esc_html_e( 'Revertir / limpiar demo', 'clipnuvex' ); ?>
						</button>
					</form>
				<?php endif; ?>
			</div>

			<?php if ( $imported ) : ?>
				<p class="clipnuvex-demo__status">
					<?php
					/* translators: fecha de importación. */
					printf( esc_html__( 'Demo importado el %s.', 'clipnuvex' ), esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $imported ) ) );
					?>
					&nbsp;<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Ver el sitio →', 'clipnuvex' ); ?></a>
				</p>
			<?php endif; ?>
		</div>
	</div>

	<style>
		.clipnuvex-demo__card{max-width:760px;background:#fff;border:1px solid #e2e4e9;border-left:4px solid #7C5CFF;border-radius:12px;padding:24px 28px;margin-top:16px;box-shadow:0 2px 10px rgba(0,0,0,.04)}
		.clipnuvex-demo__lead{font-size:15px;line-height:1.6;color:#1d2327;max-width:64ch}
		.clipnuvex-demo__list{margin:14px 0 22px;padding-left:20px;color:#50575e;line-height:1.8}
		.clipnuvex-demo__actions{display:flex;gap:14px;flex-wrap:wrap;align-items:center}
		.clipnuvex-demo .button-primary{background:#7C5CFF;border-color:#5B3CE0}
		.clipnuvex-demo .button-primary:hover{background:#5B3CE0;border-color:#3F2AA8}
		.clipnuvex-demo__revert{color:#b32d2e!important;border-color:#b32d2e!important}
		.clipnuvex-demo__status{margin-top:18px;color:#50575e}
	</style>
	<?php
}
