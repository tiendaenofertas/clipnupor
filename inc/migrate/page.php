<?php
/**
 * Pantalla Clipnuvex → Migrar entradas: formulario, análisis (simulación),
 * migración/reversión por lotes vía AJAX con progreso y reanudación, informe
 * con CSV, y vista "Sin fuente" en la lista de Vídeos.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Opciones del formulario (JSON en $_POST['opts']) normalizadas. El nonce se
 * comprueba antes en el handler.
 *
 * @return array
 */
function clipnuvex_migrate_opts_from_request() {
	$raw = array();
	if ( isset( $_POST['opts'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$decoded = json_decode( (string) wp_unslash( $_POST['opts'] ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( is_array( $decoded ) ) {
			$raw = $decoded;
		}
	}
	return Clipnuvex_Post_Migrator::normalize( $raw );
}

/**
 * Acumula contadores de filas en el estado.
 *
 * @param array $state Estado (por referencia).
 * @param array $rows  Filas.
 */
function clipnuvex_migrate_tally( &$state, $rows ) {
	if ( empty( $state['counts'] ) || ! is_array( $state['counts'] ) ) {
		$state['counts'] = array();
	}
	if ( empty( $state['sources'] ) || ! is_array( $state['sources'] ) ) {
		$state['sources'] = array();
	}
	foreach ( $rows as $row ) {
		$s = $row['status'];
		$state['counts'][ $s ] = isset( $state['counts'][ $s ] ) ? $state['counts'][ $s ] + 1 : 1;
		$state['done']         = isset( $state['done'] ) ? $state['done'] + 1 : 1;
		if ( 'migrated' === $s && ! empty( $row['source'] ) ) {
			$state['sources'][ $row['source'] ] = isset( $state['sources'][ $row['source'] ] ) ? $state['sources'][ $row['source'] ] + 1 : 1;
		}
	}
}

/**
 * Handler AJAX (nonce + capability). Operaciones: scan, start, migrate,
 * revert_start, revert, status, reset.
 */
function clipnuvex_migrate_ajax() {
	check_ajax_referer( 'clipnuvex_migrate', 'nonce' );
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'clipnuvex' ) ), 403 );
	}
	if ( Clipnuvex_Post_Migrator::blocked_by_multilang() ) {
		wp_send_json_error( array( 'message' => __( 'Hay un plugin multilenguaje activo (WPML/Polylang): la migración no es segura con él.', 'clipnuvex' ) ) );
	}
	$op       = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
	$after_id = isset( $_POST['after_id'] ) ? absint( $_POST['after_id'] ) : 0;
	$opts     = clipnuvex_migrate_opts_from_request();
	$migrator = new Clipnuvex_Post_Migrator( $opts );
	$state    = Clipnuvex_Post_Migrator::get_state();

	// Tiempo por petición: el PHP de muchos hostings corta a 30 s.
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	switch ( $op ) {
		case 'scan':
			$total = $migrator->count_candidates();
			$ids   = $migrator->candidates( $after_id, $opts['batch'] );
			$rows  = array();
			foreach ( $ids as $id ) {
				$rows[]   = $migrator->analyze( $id );
				$after_id = $id;
			}
			wp_send_json_success(
				array(
					'total'   => $total,
					'rows'    => $rows,
					'last_id' => $after_id,
					'done'    => count( $ids ) < $opts['batch'],
				)
			);
			break;

		case 'start':
			if ( ! empty( $state['running'] ) && 'cli' === ( isset( $state['owner'] ) ? $state['owner'] : '' ) && ! Clipnuvex_Post_Migrator::acquire_lock( 'ajax' ) ) {
				wp_send_json_error( array( 'message' => __( 'Hay una migración en curso desde WP-CLI. Espera a que termine.', 'clipnuvex' ) ) );
			}
			if ( ! Clipnuvex_Post_Migrator::acquire_lock( 'ajax' ) ) {
				wp_send_json_error( array( 'message' => __( 'Hay otra operación en curso. Espera unos minutos y vuelve a intentarlo.', 'clipnuvex' ) ) );
			}
			$resume = ! empty( $_POST['resume'] ) && ! empty( $state['running'] ) && 'migrate' === $state['running'];
			if ( ! $resume ) {
				$state = array_merge(
					is_array( $state ) ? $state : array(),
					array(
						'running'  => 'migrate',
						'owner'    => 'ajax',
						'started'  => time(),
						'finished' => 0,
						'last_id'  => 0,
						'total'    => $migrator->count_candidates(),
						'done'     => 0,
						'counts'   => array(),
						'sources'  => array(),
						'opts'     => $opts,
					)
				);
				Clipnuvex_Post_Migrator::clear_report();
			} else {
				$state['owner'] = 'ajax';
			}
			Clipnuvex_Post_Migrator::save_state( $state );
			wp_send_json_success( array( 'state' => $state ) );
			break;

		case 'migrate':
			if ( empty( $state['running'] ) || 'migrate' !== $state['running'] ) {
				wp_send_json_error( array( 'message' => __( 'No hay ninguna migración iniciada.', 'clipnuvex' ) ) );
			}
			Clipnuvex_Post_Migrator::renew_lock( 'ajax' );
			$migrator = new Clipnuvex_Post_Migrator( isset( $state['opts'] ) ? $state['opts'] : $opts );
			$res      = $migrator->migrate_batch( (int) $state['last_id'], $migrator->get_opts()['batch'] );
			clipnuvex_migrate_tally( $state, $res['rows'] );
			Clipnuvex_Post_Migrator::append_report( $res['rows'] );
			$state['last_id'] = $res['last_id'];
			if ( $res['done'] ) {
				Clipnuvex_Post_Migrator::finish();
				$state['running']        = '';
				$state['finished']       = time();
				$state['migrated_total'] = Clipnuvex_Post_Migrator::count_migrated();
				Clipnuvex_Post_Migrator::release_lock();
			}
			Clipnuvex_Post_Migrator::save_state( $state );
			wp_send_json_success( array( 'state' => $state, 'rows' => $res['rows'], 'done' => $res['done'] ) );
			break;

		case 'revert_start':
			if ( ! Clipnuvex_Post_Migrator::acquire_lock( 'ajax' ) ) {
				wp_send_json_error( array( 'message' => __( 'Hay otra operación en curso. Espera unos minutos y vuelve a intentarlo.', 'clipnuvex' ) ) );
			}
			$state = array_merge(
				is_array( $state ) ? $state : array(),
				array(
					'running'  => 'revert',
					'owner'    => 'ajax',
					'started'  => time(),
					'finished' => 0,
					'last_id'  => 0,
					'total'    => Clipnuvex_Post_Migrator::count_migrated(),
					'done'     => 0,
					'counts'   => array(),
					'sources'  => array(),
				)
			);
			Clipnuvex_Post_Migrator::clear_report();
			Clipnuvex_Post_Migrator::save_state( $state );
			wp_send_json_success( array( 'state' => $state ) );
			break;

		case 'revert':
			if ( empty( $state['running'] ) || 'revert' !== $state['running'] ) {
				wp_send_json_error( array( 'message' => __( 'No hay ninguna reversión iniciada.', 'clipnuvex' ) ) );
			}
			Clipnuvex_Post_Migrator::renew_lock( 'ajax' );
			$res = $migrator->revert_batch( (int) $state['last_id'], $opts['batch'] );
			clipnuvex_migrate_tally( $state, $res['rows'] );
			Clipnuvex_Post_Migrator::append_report( $res['rows'] );
			$state['last_id'] = $res['last_id'];
			if ( $res['done'] ) {
				Clipnuvex_Post_Migrator::finish();
				Clipnuvex_Post_Migrator::cleanup_if_empty();
				$state['running']        = '';
				$state['finished']       = time();
				$state['migrated_total'] = Clipnuvex_Post_Migrator::count_migrated();
				Clipnuvex_Post_Migrator::release_lock();
			}
			Clipnuvex_Post_Migrator::save_state( $state );
			wp_send_json_success( array( 'state' => $state, 'rows' => $res['rows'], 'done' => $res['done'] ) );
			break;

		case 'status':
			wp_send_json_success( array( 'state' => $state, 'migrated_total' => Clipnuvex_Post_Migrator::count_migrated() ) );
			break;

		case 'reset':
			// Descarta una ejecución interrumpida (no toca el contenido migrado).
			Clipnuvex_Post_Migrator::release_lock();
			$state['running']  = '';
			$state['finished'] = time();
			Clipnuvex_Post_Migrator::save_state( $state );
			wp_send_json_success( array( 'state' => $state ) );
			break;

		default:
			wp_send_json_error( array( 'message' => __( 'Operación desconocida.', 'clipnuvex' ) ) );
	}
}
add_action( 'wp_ajax_clipnuvex_migrate', 'clipnuvex_migrate_ajax' );

/**
 * Exporta el informe de la última ejecución como CSV.
 */
function clipnuvex_migrate_csv() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'Permisos insuficientes.', 'clipnuvex' ) );
	}
	check_admin_referer( 'clipnuvex_migrate_csv' );
	$rows = Clipnuvex_Post_Migrator::get_report();
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="clipnuvex-migracion-' . gmdate( 'Ymd-His' ) . '.csv"' );
	$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	fputcsv( $out, array( 'id', 'title', 'status', 'source', 'old_url', 'new_url', 'note' ) );
	foreach ( $rows as $row ) {
		fputcsv(
			$out,
			array(
				isset( $row['id'] ) ? (int) $row['id'] : '',
				isset( $row['title'] ) ? (string) $row['title'] : '',
				isset( $row['status'] ) ? (string) $row['status'] : '',
				isset( $row['source'] ) ? (string) $row['source'] : '',
				isset( $row['old_url'] ) ? (string) $row['old_url'] : '',
				isset( $row['new_url'] ) ? (string) $row['new_url'] : '',
				isset( $row['note'] ) ? (string) $row['note'] : '',
			)
		);
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	exit;
}
add_action( 'admin_post_clipnuvex_migrate_csv', 'clipnuvex_migrate_csv' );

/**
 * Etiquetas legibles de los estados del informe.
 *
 * @return array
 */
function clipnuvex_migrate_status_labels() {
	return array(
		'ok'                => __( 'Lista para migrar', 'clipnuvex' ),
		'migrated'          => __( 'Migrada', 'clipnuvex' ),
		'reverted'          => __( 'Revertida', 'clipnuvex' ),
		'skipped_collision' => __( 'Omitida: slug en uso', 'clipnuvex' ),
		'skipped_nosource'  => __( 'Omitida: sin fuente', 'clipnuvex' ),
		'skipped_password'  => __( 'Omitida: con contraseña', 'clipnuvex' ),
		'error'             => __( 'Error', 'clipnuvex' ),
	);
}

/**
 * Renderiza la pantalla.
 */
function clipnuvex_migrate_render_page() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'Permisos insuficientes.', 'clipnuvex' ) );
	}
	$state     = Clipnuvex_Post_Migrator::get_state();
	$migrated  = Clipnuvex_Post_Migrator::count_migrated();
	$blocked   = Clipnuvex_Post_Migrator::blocked_by_multilang();
	$defaults  = Clipnuvex_Post_Migrator::defaults();
	$opts      = ! empty( $state['opts'] ) && is_array( $state['opts'] ) ? Clipnuvex_Post_Migrator::normalize( $state['opts'] ) : $defaults;
	$running   = ! empty( $state['running'] ) ? $state['running'] : '';
	$total_pub = wp_count_posts( 'post' );
	$total_pub = isset( $total_pub->publish ) ? (int) $total_pub->publish : 0;
	$cats      = get_categories( array( 'hide_empty' => false ) );
	$csv_url   = wp_nonce_url( admin_url( 'admin-post.php?action=clipnuvex_migrate_csv' ), 'clipnuvex_migrate_csv' );
	?>
	<div class="wrap clipnuvex-migrate" id="clipnuvex-migrate">
		<h1><?php esc_html_e( 'Clipnuvex · Migrar entradas a vídeos', 'clipnuvex' ); ?></h1>

		<?php if ( $blocked ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'Hay un plugin multilenguaje activo (WPML/Polylang). La migración no es segura con él y queda desactivada.', 'clipnuvex' ); ?></p></div>
		<?php endif; ?>

		<?php if ( 'migrate' === $running || 'revert' === $running ) : ?>
			<div class="notice notice-warning"><p>
				<?php echo esc_html( 'migrate' === $running ? __( 'Hay una migración interrumpida. Puedes continuarla desde donde se quedó o descartarla.', 'clipnuvex' ) : __( 'Hay una reversión interrumpida. Puedes continuarla o descartarla.', 'clipnuvex' ) ); ?>
			</p></div>
		<?php endif; ?>

		<div class="clipnuvex-demo__card clipnuvex-migrate__card">
			<p class="clipnuvex-demo__lead">
				<?php esc_html_e( 'Convierte las entradas normales (post) en vídeos del theme conservando ID, URL, fechas, autor, imagen destacada, metadatos, comentarios y posicionamiento. Se mapean las categorías y etiquetas, se detecta la fuente del vídeo y todo queda registrado para poder revertirlo.', 'clipnuvex' ); ?>
			</p>
			<p>
				<?php
				/* translators: %d: nº de entradas publicadas. */
				printf( esc_html__( 'Entradas publicadas ahora mismo: %d.', 'clipnuvex' ), (int) $total_pub );
				if ( $migrated ) {
					echo ' ';
					/* translators: %d: nº de vídeos migrados. */
					printf( esc_html__( 'Vídeos migrados hasta ahora: %d.', 'clipnuvex' ), (int) $migrated );
				}
				?>
			</p>

			<form id="clipnuvex-migrate-form" onsubmit="return false;">
				<h2><?php esc_html_e( '1. Qué migrar', 'clipnuvex' ); ?></h2>
				<div class="clipnuvex-migrate__grid">
					<fieldset>
						<legend><?php esc_html_e( 'Estados', 'clipnuvex' ); ?></legend>
						<?php
						$status_labels = array(
							'publish' => __( 'Publicadas', 'clipnuvex' ),
							'draft'   => __( 'Borradores', 'clipnuvex' ),
							'pending' => __( 'Pendientes', 'clipnuvex' ),
							'private' => __( 'Privadas', 'clipnuvex' ),
							'future'  => __( 'Programadas', 'clipnuvex' ),
						);
						foreach ( $status_labels as $key => $label ) :
							?>
							<label><input type="checkbox" name="statuses[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $opts['statuses'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
						<?php endforeach; ?>
						<label><input type="checkbox" name="include_password" value="1" <?php checked( $opts['include_password'] ); ?>> <?php esc_html_e( 'Incluir entradas protegidas con contraseña', 'clipnuvex' ); ?></label>
					</fieldset>
					<fieldset>
						<legend><?php esc_html_e( 'Categorías de entrada (vacío = todas)', 'clipnuvex' ); ?></legend>
						<select name="categories[]" multiple size="6">
							<?php foreach ( $cats as $cat ) : ?>
								<option value="<?php echo (int) $cat->term_id; ?>" <?php selected( in_array( (int) $cat->term_id, $opts['categories'], true ) ); ?>><?php echo esc_html( $cat->name . ' (' . (int) $cat->count . ')' ); ?></option>
							<?php endforeach; ?>
						</select>
					</fieldset>
				</div>

				<h2><?php esc_html_e( '2. Fuente del vídeo', 'clipnuvex' ); ?></h2>
				<div class="clipnuvex-migrate__grid">
					<fieldset>
						<legend><?php esc_html_e( 'Campos personalizados del theme anterior', 'clipnuvex' ); ?></legend>
						<input type="text" name="source_meta" class="regular-text" value="<?php echo esc_attr( implode( ', ', $opts['source_meta'] ) ); ?>" placeholder="video_url, _video_embed">
						<p class="description"><?php esc_html_e( 'Opcional. Claves separadas por comas; se prueban primero. Cada valor válido (URL, iframe o shortcode) se convierte en un servidor del vídeo.', 'clipnuvex' ); ?></p>
						<label><input type="checkbox" name="detect_content" value="1" <?php checked( $opts['detect_content'] ); ?>> <?php esc_html_e( 'Buscar también en el contenido (iframe, bloque de embed, [embed], shortcode o URL de YouTube, Vimeo, Dailymotion, ok.ru…)', 'clipnuvex' ); ?></label>
						<label><input type="checkbox" name="strip_embed" value="1" <?php checked( $opts['strip_embed'] ); ?>> <?php esc_html_e( 'Retirar del contenido el fragmento detectado (evita el reproductor duplicado; reversible)', 'clipnuvex' ); ?></label>
						<label><input type="checkbox" name="skip_nosource" value="1" <?php checked( $opts['skip_nosource'] ); ?>> <?php esc_html_e( 'Omitir las entradas sin fuente detectada (por defecto se migran y se marcan)', 'clipnuvex' ); ?></label>
					</fieldset>
					<fieldset>
						<legend><?php esc_html_e( 'Mapeo de metadatos (opcional): clave del theme anterior', 'clipnuvex' ); ?></legend>
						<?php
						$map_labels = array(
							'quality'  => __( 'Calidad (HD/4K)', 'clipnuvex' ),
							'language' => __( 'Idioma', 'clipnuvex' ),
							'year'     => __( 'Año', 'clipnuvex' ),
							'duration' => __( 'Duración (min)', 'clipnuvex' ),
							'views'    => __( 'Vistas', 'clipnuvex' ),
							'featured' => __( 'Destacado', 'clipnuvex' ),
						);
						foreach ( $map_labels as $field => $label ) :
							?>
							<label class="clipnuvex-migrate__map"><span><?php echo esc_html( $label ); ?></span><input type="text" name="map[<?php echo esc_attr( $field ); ?>]" value="<?php echo esc_attr( $opts['map'][ $field ] ); ?>" placeholder="meta_key"></label>
						<?php endforeach; ?>
					</fieldset>
				</div>

				<h2><?php esc_html_e( '3. Ejecución', 'clipnuvex' ); ?></h2>
				<p>
					<label><?php esc_html_e( 'Entradas por lote', 'clipnuvex' ); ?> <input type="number" name="batch" min="5" max="500" value="<?php echo (int) $opts['batch']; ?>" class="small-text"></label>
					<span class="description"><?php esc_html_e( 'Cada lote es una petición; con hostings lentos usa lotes pequeños.', 'clipnuvex' ); ?></span>
				</p>
				<div class="clipnuvex-migrate__actions">
					<button type="button" class="button button-secondary button-hero" data-op="scan" <?php disabled( $blocked || '' !== $running ); ?>><?php esc_html_e( 'Analizar (no escribe nada)', 'clipnuvex' ); ?></button>
					<?php if ( 'migrate' === $running ) : ?>
						<button type="button" class="button button-primary button-hero" data-op="resume"><?php esc_html_e( 'Continuar la migración', 'clipnuvex' ); ?></button>
						<button type="button" class="button button-secondary" data-op="reset"><?php esc_html_e( 'Descartar la ejecución interrumpida', 'clipnuvex' ); ?></button>
					<?php elseif ( 'revert' === $running ) : ?>
						<button type="button" class="button button-primary button-hero" data-op="revert_resume"><?php esc_html_e( 'Continuar la reversión', 'clipnuvex' ); ?></button>
						<button type="button" class="button button-secondary" data-op="reset"><?php esc_html_e( 'Descartar la ejecución interrumpida', 'clipnuvex' ); ?></button>
					<?php else : ?>
						<button type="button" class="button button-primary button-hero" data-op="migrate" <?php disabled( $blocked ); ?>><?php esc_html_e( 'Migrar a vídeos', 'clipnuvex' ); ?></button>
						<?php if ( $migrated ) : ?>
							<button type="button" class="button button-secondary button-hero clipnuvex-demo__revert" data-op="revert" <?php disabled( $blocked ); ?>><?php esc_html_e( 'Revertir la migración', 'clipnuvex' ); ?></button>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</form>

			<div class="clipnuvex-migrate__progress" id="clipnuvex-migrate-progress" hidden>
				<div class="clipnuvex-migrate__bar"><span id="clipnuvex-migrate-bar"></span></div>
				<p id="clipnuvex-migrate-status"></p>
			</div>

			<div id="clipnuvex-migrate-results" hidden>
				<h2><?php esc_html_e( 'Resultado', 'clipnuvex' ); ?></h2>
				<p id="clipnuvex-migrate-summary"></p>
				<div class="clipnuvex-migrate__tables" id="clipnuvex-migrate-tables"></div>
				<p class="clipnuvex-migrate__after" id="clipnuvex-migrate-after" hidden>
					<?php esc_html_e( 'Recomendado tras migrar: regenerar las miniaturas de las imágenes antiguas (por ejemplo con WP-CLI: wp media regenerate --only-missing) para que las tarjetas usen los tamaños del theme, y revisar la "categoría principal" si usas Yoast o Rank Math (apuntaba a las categorías de entrada).', 'clipnuvex' ); ?>
				</p>
			</div>

			<?php if ( ! empty( $state['finished'] ) && empty( $running ) ) : ?>
				<p class="clipnuvex-demo__status">
					<?php
					$labels = clipnuvex_migrate_status_labels();
					$parts  = array();
					if ( ! empty( $state['counts'] ) ) {
						foreach ( $state['counts'] as $k => $v ) {
							$parts[] = ( isset( $labels[ $k ] ) ? $labels[ $k ] : $k ) . ': ' . (int) $v;
						}
					}
					/* translators: 1: fecha, 2: resumen. */
					printf( esc_html__( 'Última ejecución: %1$s. %2$s', 'clipnuvex' ), esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $state['finished'] ) ), esc_html( implode( ' · ', $parts ) ) );
					?>
					&nbsp;<a href="<?php echo esc_url( $csv_url ); ?>"><?php esc_html_e( 'Descargar informe CSV', 'clipnuvex' ); ?></a>
					<?php if ( $migrated ) : ?>
						&nbsp;·&nbsp;<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=video&clipnuvex_nosource=1' ) ); ?>"><?php esc_html_e( 'Ver vídeos sin fuente', 'clipnuvex' ); ?></a>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * Vista "Sin fuente" en la lista de Vídeos (solo si hay migración).
 *
 * @param array $views Vistas.
 * @return array
 */
function clipnuvex_migrate_views( $views ) {
	if ( ! Clipnuvex_Post_Migrator::is_active() ) {
		return $views;
	}
	global $wpdb;
	$count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_type = 'video' AND p.post_status <> 'trash'",
			Clipnuvex_Post_Migrator::META_NOSOURCE
		)
	);
	if ( ! $count ) {
		return $views;
	}
	$current = ! empty( $_GET['clipnuvex_nosource'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$views['clipnuvex_nosource'] = sprintf(
		'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
		esc_url( admin_url( 'edit.php?post_type=video&clipnuvex_nosource=1' ) ),
		$current ? ' class="current" aria-current="page"' : '',
		esc_html__( 'Sin fuente', 'clipnuvex' ),
		$count
	);
	return $views;
}
add_filter( 'views_edit-video', 'clipnuvex_migrate_views' );

/**
 * Filtro de la lista de Vídeos por "sin fuente".
 *
 * @param WP_Query $query Consulta.
 */
function clipnuvex_migrate_admin_filter( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || 'video' !== $query->get( 'post_type' ) ) {
		return;
	}
	if ( empty( $_GET['clipnuvex_nosource'] ) || ! Clipnuvex_Post_Migrator::is_active() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$meta = (array) $query->get( 'meta_query' );
	$meta[] = array(
		'key'   => Clipnuvex_Post_Migrator::META_NOSOURCE,
		'value' => '1',
	);
	$query->set( 'meta_query', $meta );
}
add_action( 'pre_get_posts', 'clipnuvex_migrate_admin_filter' );
