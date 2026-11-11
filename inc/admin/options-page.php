<?php
/**
 * Sección de administración dedicada del theme (menú de nivel superior).
 *
 * Menú "Clipnuvex" con pestañas que renderizan el registro de opciones
 * (inc/options.php): textos, colores y estilos, secciones, anuncios,
 * reproductor, SEO y acceso al Modo Demo. Personalización del theme al 100%.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registra el menú de nivel superior y sus subpáginas.
 */
function clipnuvex_admin_menu() {
	add_menu_page(
		__( 'Clipnuvex', 'clipnuvex' ),
		__( 'Clipnuvex', 'clipnuvex' ),
		'edit_theme_options',
		'clipnuvex',
		'clipnuvex_admin_render_page',
		'dashicons-video-alt3',
		3
	);

	add_submenu_page( 'clipnuvex', __( 'Ajustes del theme', 'clipnuvex' ), __( 'Ajustes', 'clipnuvex' ), 'edit_theme_options', 'clipnuvex', 'clipnuvex_admin_render_page' );
	add_submenu_page( 'clipnuvex', __( 'Modo Demo', 'clipnuvex' ), __( 'Modo Demo', 'clipnuvex' ), 'edit_theme_options', 'clipnuvex-demo', 'clipnuvex_demo_render_page' );
	if ( function_exists( 'clipnuvex_migrate_render_page' ) ) {
		add_submenu_page( 'clipnuvex', __( 'Migrar entradas a vídeos', 'clipnuvex' ), __( 'Migrar entradas', 'clipnuvex' ), 'edit_theme_options', 'clipnuvex-migrate', 'clipnuvex_migrate_render_page' );
	}
}
add_action( 'admin_menu', 'clipnuvex_admin_menu', 9 );

/**
 * Encola assets del panel (color picker + media + estilos) solo en sus pantallas.
 *
 * @param string $hook Hook de la pantalla.
 */
function clipnuvex_admin_assets( $hook ) {
	if ( false === strpos( $hook, 'clipnuvex' ) ) {
		return;
	}
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
	wp_enqueue_media();
	// Versión basada en filemtime: el CSS del panel nunca queda cacheado tras una edición.
	$admin_css = CLIPNUVEX_DIR . 'inc/admin/assets/admin.css';
	$admin_ver = file_exists( $admin_css ) ? (string) filemtime( $admin_css ) : CLIPNUVEX_VERSION;
	wp_enqueue_style( 'clipnuvex-admin', CLIPNUVEX_URI . 'inc/admin/assets/admin.css', array(), $admin_ver );

	// Pantalla "Migrar entradas": bucle AJAX por lotes.
	if ( false !== strpos( $hook, 'clipnuvex-migrate' ) && function_exists( 'clipnuvex_migrate_status_labels' ) ) {
		$mig_js  = CLIPNUVEX_DIR . 'inc/admin/assets/migrate.js';
		$mig_ver = file_exists( $mig_js ) ? (string) filemtime( $mig_js ) : CLIPNUVEX_VERSION;
		wp_enqueue_script( 'clipnuvex-migrate', CLIPNUVEX_URI . 'inc/admin/assets/migrate.js', array(), $mig_ver, true );
		wp_localize_script(
			'clipnuvex-migrate',
			'clipnuvexMigrate',
			array(
				'ajaxUrl' => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
				'nonce'   => wp_create_nonce( 'clipnuvex_migrate' ),
				'editUrl' => esc_url_raw( admin_url( 'post.php?post=%d&action=edit' ) ),
				'i18n'    => array(
					'status'         => clipnuvex_migrate_status_labels(),
					'scanning'       => __( 'Analizando', 'clipnuvex' ),
					'migrating'      => __( 'Migrando', 'clipnuvex' ),
					'reverting'      => __( 'Revirtiendo', 'clipnuvex' ),
					'scanDone'       => __( 'Análisis terminado (no se ha escrito nada).', 'clipnuvex' ),
					'migrateDone'    => __( 'Migración terminada. La página se recargará.', 'clipnuvex' ),
					'revertDone'     => __( 'Reversión terminada. La página se recargará.', 'clipnuvex' ),
					'confirmMigrate' => __( '¿Convertir las entradas seleccionadas en vídeos? Es reversible desde esta misma pantalla.', 'clipnuvex' ),
					'confirmRevert'  => __( '¿Devolver todos los vídeos migrados a entradas?', 'clipnuvex' ),
					'error'          => __( 'Error', 'clipnuvex' ),
					'leave'          => __( 'Hay una operación en curso. Si sales, podrás continuarla después.', 'clipnuvex' ),
					'sources'        => __( 'Fuentes', 'clipnuvex' ),
					'title'          => __( 'Título', 'clipnuvex' ),
					'source'         => __( 'Fuente', 'clipnuvex' ),
					'note'           => __( 'Nota', 'clipnuvex' ),
					/* translators: %d: filas no mostradas. */
					'more'           => __( '… y %d más (descarga el CSV para verlas todas).', 'clipnuvex' ),
				),
			)
		);
	}
	wp_add_inline_script(
		'wp-color-picker',
		"jQuery(function($){
			$('.clipnuvex-color').wpColorPicker();
			$(document).on('click','.clipnuvex-media-btn',function(e){
				e.preventDefault();
				var \$wrap=$(this).closest('.clipnuvex-media');
				var frame=wp.media({title:'Seleccionar imagen',multiple:false});
				frame.on('select',function(){
					var a=frame.state().get('selection').first().toJSON();
					\$wrap.find('input[type=url]').val(a.url);
					\$wrap.find('.clipnuvex-media-preview').html('<img src=\"'+a.url+'\" alt=\"\">');
				});
				frame.open();
			});
			$(document).on('click','.clipnuvex-media-clear',function(e){
				e.preventDefault();
				var \$wrap=$(this).closest('.clipnuvex-media');
				\$wrap.find('input[type=url]').val('');
				\$wrap.find('.clipnuvex-media-preview').empty();
			});
		});"
	);
}
add_action( 'admin_enqueue_scripts', 'clipnuvex_admin_assets' );

/**
 * Renderiza la página de ajustes con pestañas.
 */
function clipnuvex_admin_render_page() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'Permisos insuficientes.', 'clipnuvex' ) );
	}

	$registry = clipnuvex_options_registry();
	$tabs     = array_keys( $registry );
	$active   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : $tabs[0]; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $registry[ $active ] ) ) {
		$active = $tabs[0];
	}
	$base_url = admin_url( 'admin.php?page=clipnuvex' );
	?>
	<div class="wrap clipnuvex-admin">
		<div class="clipnuvex-admin__hero">
			<div class="clipnuvex-admin__brand">
				<span class="clipnuvex-admin__logo"><span class="clipnuvex-admin__logo-play"></span></span>
				<div>
					<h1>Clip<span>nuvex</span></h1>
					<p><?php esc_html_e( 'Personaliza el theme al 100%: textos, colores, secciones, anuncios y más.', 'clipnuvex' ); ?></p>
				</div>
			</div>
			<a class="clipnuvex-admin__demo" href="<?php echo esc_url( admin_url( 'admin.php?page=clipnuvex-demo' ) ); ?>"><?php esc_html_e( 'Modo Demo →', 'clipnuvex' ); ?></a>
		</div>

		<?php settings_errors( 'clipnuvex_options' ); ?>

		<div class="clipnuvex-admin__layout">
			<nav class="clipnuvex-admin__tabs" aria-label="<?php esc_attr_e( 'Secciones de ajustes', 'clipnuvex' ); ?>">
				<?php foreach ( $registry as $tab_id => $tab ) : ?>
					<a class="clipnuvex-admin__tab<?php echo $active === $tab_id ? ' is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', $tab_id, $base_url ) ); ?>">
						<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
						<?php echo esc_html( $tab['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="clipnuvex-admin__panel">
				<form method="post" action="options.php">
					<?php settings_fields( 'clipnuvex_options_group' ); ?>
					<input type="hidden" name="clipnuvex_options[_tab]" value="<?php echo esc_attr( $active ); ?>">

					<h2 class="clipnuvex-admin__panel-title">
						<span class="dashicons <?php echo esc_attr( $registry[ $active ]['icon'] ); ?>"></span>
						<?php echo esc_html( $registry[ $active ]['label'] ); ?>
					</h2>

					<?php clipnuvex_admin_render_fields( $registry[ $active ] ); ?>

					<p class="clipnuvex-admin__submit"><?php submit_button( __( 'Guardar cambios', 'clipnuvex' ), 'primary', 'submit', false ); ?></p>
				</form>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Renderiza los campos de una pestaña (agrupados si tiene 'groups').
 *
 * @param array $tab Definición de la pestaña.
 */
function clipnuvex_admin_render_fields( $tab ) {
	$fields = array();
	foreach ( $tab['fields'] as $f ) {
		$fields[ $f['id'] ] = $f;
	}

	if ( ! empty( $tab['groups'] ) ) {
		foreach ( $tab['groups'] as $group_label => $ids ) {
			echo '<h3 class="clipnuvex-admin__group">' . esc_html( $group_label ) . '</h3>';
			echo '<table class="form-table" role="presentation"><tbody>';
			foreach ( $ids as $id ) {
				if ( isset( $fields[ $id ] ) ) {
					clipnuvex_admin_render_row( $fields[ $id ] );
				}
			}
			echo '</tbody></table>';
		}
		return;
	}

	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( $tab['fields'] as $field ) {
		clipnuvex_admin_render_row( $field );
	}
	echo '</tbody></table>';
}

/**
 * Renderiza una fila de campo.
 *
 * @param array $field Definición del campo.
 */
function clipnuvex_admin_render_row( $field ) {
	$id    = $field['id'];
	$type  = isset( $field['type'] ) ? $field['type'] : 'text';
	$name  = 'clipnuvex_options[' . $id . ']';
	$value = clipnuvex_option( $id, clipnuvex_option_default( $id ) );
	$desc  = isset( $field['desc'] ) ? $field['desc'] : '';

	echo '<tr>';
	echo '<th scope="row"><label for="cnx-' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label></th>';
	echo '<td>';

	switch ( $type ) {
		case 'checkbox':
			// Campo oculto con valor 0 ANTES del checkbox: garantiza que el valor
			// siempre se envíe (0 si está desmarcado). Si está marcado, el checkbox
			// posterior (value=1) sobrescribe al oculto. Patrón a prueba de fallos.
			printf(
				'<input type="hidden" name="%2$s" value="0"><label class="clipnuvex-switch"><input type="checkbox" id="cnx-%1$s" name="%2$s" value="1" %3$s><span></span></label>',
				esc_attr( $id ),
				esc_attr( $name ),
				checked( (bool) $value, true, false )
			);
			break;
		case 'color':
			printf( '<input type="text" id="cnx-%1$s" class="clipnuvex-color" name="%2$s" value="%3$s" data-default-color="%4$s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ), esc_attr( clipnuvex_option_default( $id ) ) );
			break;
		case 'number':
			printf(
				'<input type="number" id="cnx-%1$s" class="small-text" name="%2$s" value="%3$s" min="%4$s" max="%5$s">',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( $value ),
				esc_attr( isset( $field['min'] ) ? $field['min'] : '' ),
				esc_attr( isset( $field['max'] ) ? $field['max'] : '' )
			);
			break;
		case 'select':
			printf( '<select id="cnx-%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
			foreach ( $field['choices'] as $val => $label ) {
				printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $val ), selected( $value, $val, false ), esc_html( $label ) );
			}
			echo '</select>';
			break;
		case 'image':
			echo '<span class="clipnuvex-media">';
			printf( '<input type="url" id="cnx-%1$s" class="regular-text" name="%2$s" value="%3$s" placeholder="https://…">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
			// type="button" explícito: sin él serían type=submit y el envío implícito
			// del form (Enter en cualquier campo) los "pulsaría" en vez de guardar.
			echo ' <button type="button" class="button clipnuvex-media-btn">' . esc_html__( 'Seleccionar', 'clipnuvex' ) . '</button>';
			echo ' <button type="button" class="button clipnuvex-media-clear">' . esc_html__( 'Quitar', 'clipnuvex' ) . '</button>';
			echo '<span class="clipnuvex-media-preview">';
			if ( $value ) {
				echo '<img src="' . esc_url( $value ) . '" alt="">';
			}
			echo '</span></span>';
			break;
		case 'code':
			printf( '<textarea id="cnx-%1$s" class="large-text code" rows="4" name="%2$s" spellcheck="false">%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( $value ) );
			break;
		case 'textarea':
			printf( '<textarea id="cnx-%1$s" class="large-text" rows="3" name="%2$s">%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( $value ) );
			break;
		case 'text':
		default:
			printf( '<input type="text" id="cnx-%1$s" class="regular-text" name="%2$s" value="%3$s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
			break;
	}

	if ( $desc ) {
		echo '<p class="description">' . esc_html( $desc ) . '</p>';
	}
	echo '</td></tr>';
}
