<?php
/**
 * Comandos WP-CLI de la migración de entradas a vídeos.
 *
 *   wp clipnuvex migrate-posts [--status=publish,draft] [--category=slug,slug]
 *       [--source-meta=k1,k2] [--map-quality=k] [--map-language=k] [--map-year=k]
 *       [--map-duration=k] [--map-views=k] [--map-featured=k] [--keep-embed]
 *       [--no-content] [--skip-nosource] [--include-password] [--dry-run]
 *       [--batch=200] [--limit=N] [--yes]
 *   wp clipnuvex revert-migration [--ids=1,2,3] [--batch=200] [--yes]
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	return;
}

/**
 * Comandos `wp clipnuvex`.
 */
class Clipnuvex_CLI_Migrate {

	/**
	 * Opciones del migrador a partir de los flags.
	 *
	 * @param array $assoc Flags asociativos.
	 * @return array
	 */
	private function opts_from_flags( $assoc ) {
		$cats = array();
		if ( ! empty( $assoc['category'] ) ) {
			foreach ( explode( ',', (string) $assoc['category'] ) as $slug ) {
				$term = get_term_by( 'slug', trim( $slug ), 'category' );
				if ( $term instanceof WP_Term ) {
					$cats[] = (int) $term->term_id;
				} else {
					WP_CLI::warning( sprintf( 'Categoría no encontrada: %s', $slug ) );
				}
			}
		}
		return array(
			'statuses'         => ! empty( $assoc['status'] ) ? explode( ',', (string) $assoc['status'] ) : array( 'publish' ),
			'categories'       => $cats,
			'source_meta'      => ! empty( $assoc['source-meta'] ) ? (string) $assoc['source-meta'] : '',
			'detect_content'   => ! isset( $assoc['no-content'] ),
			'strip_embed'      => ! isset( $assoc['keep-embed'] ),
			'skip_nosource'    => isset( $assoc['skip-nosource'] ),
			'include_password' => isset( $assoc['include-password'] ),
			'map'              => array(
				'quality'  => isset( $assoc['map-quality'] ) ? $assoc['map-quality'] : '',
				'language' => isset( $assoc['map-language'] ) ? $assoc['map-language'] : '',
				'year'     => isset( $assoc['map-year'] ) ? $assoc['map-year'] : '',
				'duration' => isset( $assoc['map-duration'] ) ? $assoc['map-duration'] : '',
				'views'    => isset( $assoc['map-views'] ) ? $assoc['map-views'] : '',
				'featured' => isset( $assoc['map-featured'] ) ? $assoc['map-featured'] : '',
			),
			'batch'            => isset( $assoc['batch'] ) ? (int) $assoc['batch'] : 200,
		);
	}

	/**
	 * Migra entradas a vídeos.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<lista>]
	 * : Estados a migrar (publish,draft,pending,private,future). Por defecto publish.
	 *
	 * [--category=<slugs>]
	 * : Solo entradas de estas categorías (slugs separados por coma).
	 *
	 * [--source-meta=<claves>]
	 * : Campos personalizados del theme anterior con la URL/iframe del vídeo.
	 *
	 * [--map-quality=<clave>]
	 * : Campo del theme anterior con la calidad (HD/4K).
	 *
	 * [--map-language=<clave>]
	 * : Campo del theme anterior con el idioma.
	 *
	 * [--map-year=<clave>]
	 * : Campo del theme anterior con el año.
	 *
	 * [--map-duration=<clave>]
	 * : Campo del theme anterior con la duración en minutos.
	 *
	 * [--map-views=<clave>]
	 * : Campo del theme anterior con las vistas.
	 *
	 * [--map-featured=<clave>]
	 * : Campo del theme anterior que marca el destacado.
	 *
	 * [--keep-embed]
	 * : No retirar del contenido el iframe/URL detectado.
	 *
	 * [--no-content]
	 * : No buscar la fuente en el contenido (solo campos personalizados).
	 *
	 * [--skip-nosource]
	 * : Omitir entradas sin fuente detectada.
	 *
	 * [--include-password]
	 * : Incluir entradas protegidas con contraseña.
	 *
	 * [--dry-run]
	 * : Solo analizar: no escribe nada.
	 *
	 * [--batch=<n>]
	 * : Tamaño del lote (5-500). Por defecto 200.
	 *
	 * [--limit=<n>]
	 * : Procesar como máximo N entradas.
	 *
	 * [--yes]
	 * : No pedir confirmación.
	 *
	 * @subcommand migrate-posts
	 *
	 * @param array $args  Posicionales.
	 * @param array $assoc Asociativos.
	 */
	public function migrate_posts( $args, $assoc ) {
		if ( Clipnuvex_Post_Migrator::blocked_by_multilang() ) {
			WP_CLI::error( 'Hay un plugin multilenguaje activo (WPML/Polylang): la migración no es segura con él.' );
		}
		$migrator = new Clipnuvex_Post_Migrator( $this->opts_from_flags( $assoc ) );
		$dry      = isset( $assoc['dry-run'] );
		$limit    = isset( $assoc['limit'] ) ? max( 0, (int) $assoc['limit'] ) : 0;
		$total    = $migrator->count_candidates();
		if ( $limit && $limit < $total ) {
			$total = $limit;
		}
		if ( ! $total ) {
			WP_CLI::success( 'No hay entradas candidatas con esas opciones.' );
			return;
		}
		WP_CLI::log( sprintf( '%s %d entradas (lote %d).', $dry ? 'Analizando' : 'Migrando', $total, $migrator->get_opts()['batch'] ) );
		if ( ! $dry && ! isset( $assoc['yes'] ) ) {
			WP_CLI::confirm( sprintf( '¿Convertir %d entradas en vídeos? (reversible con revert-migration)', $total ), $assoc );
		}
		if ( ! $dry && ! Clipnuvex_Post_Migrator::acquire_lock( 'cli' ) ) {
			WP_CLI::error( 'Hay otra migración en curso (panel o CLI). Espera a que termine.' );
		}

		$counts   = array( 'migrated' => 0, 'skipped_collision' => 0, 'skipped_nosource' => 0, 'skipped_password' => 0, 'error' => 0, 'ok' => 0 );
		$sources  = array();
		$rows     = array();
		$after    = 0;
		$done     = 0;
		$batch    = $migrator->get_opts()['batch'];
		$progress = \WP_CLI\Utils\make_progress_bar( $dry ? 'Análisis' : 'Migración', $total );

		if ( ! $dry ) {
			$state = Clipnuvex_Post_Migrator::get_state();
			$state = array_merge( is_array( $state ) ? $state : array(), array( 'running' => 'migrate', 'owner' => 'cli', 'started' => time(), 'finished' => 0, 'opts' => $migrator->get_opts() ) );
			Clipnuvex_Post_Migrator::save_state( $state );
			Clipnuvex_Post_Migrator::clear_report();
		}

		while ( true ) {
			$size = $batch;
			if ( $limit ) {
				$size = min( $batch, $limit - $done );
				if ( $size <= 0 ) {
					break;
				}
			}
			if ( $dry ) {
				$ids  = $migrator->candidates( $after, $size );
				$rows = array();
				foreach ( $ids as $id ) {
					$rows[] = $migrator->analyze( $id );
					$after  = $id;
				}
				$finished = count( $ids ) < $size;
			} else {
				Clipnuvex_Post_Migrator::renew_lock( 'cli' );
				$res      = $migrator->migrate_batch( $after, $size );
				$rows     = $res['rows'];
				$after    = $res['last_id'];
				$finished = $res['done'];
				Clipnuvex_Post_Migrator::append_report( $rows );
			}
			foreach ( $rows as $row ) {
				$done++;
				$progress->tick();
				$status = $row['status'];
				if ( isset( $counts[ $status ] ) ) {
					$counts[ $status ]++;
				}
				if ( ! empty( $row['source'] ) ) {
					$sources[ $row['source'] ] = isset( $sources[ $row['source'] ] ) ? $sources[ $row['source'] ] + 1 : 1;
				}
				if ( 'error' === $status ) {
					WP_CLI::warning( sprintf( '#%d %s: %s', $row['id'], $row['title'], $row['note'] ) );
				}
			}
			if ( function_exists( '\WP_CLI\Utils\wp_clear_object_cache' ) ) {
				\WP_CLI\Utils\wp_clear_object_cache();
			}
			if ( $finished ) {
				break;
			}
		}
		$progress->finish();

		if ( ! $dry ) {
			Clipnuvex_Post_Migrator::finish();
			$state = Clipnuvex_Post_Migrator::get_state();
			$state['running']  = '';
			$state['finished'] = time();
			$state['last_id']  = $after;
			$state['counts']   = $counts;
			$state['migrated_total'] = Clipnuvex_Post_Migrator::count_migrated();
			Clipnuvex_Post_Migrator::save_state( $state );
			Clipnuvex_Post_Migrator::release_lock();
		}

		$lines = array();
		foreach ( $counts as $k => $v ) {
			if ( $v ) {
				$lines[] = $k . ': ' . $v;
			}
		}
		$src_lines = array();
		foreach ( $sources as $k => $v ) {
			$src_lines[] = $k . ': ' . $v;
		}
		WP_CLI::log( 'Resultado → ' . implode( ' · ', $lines ) );
		WP_CLI::log( 'Fuentes → ' . ( $src_lines ? implode( ' · ', $src_lines ) : '—' ) );
		if ( $dry ) {
			WP_CLI::success( 'Análisis terminado (no se ha escrito nada).' );
		} else {
			WP_CLI::success( 'Migración terminada. Recomendado: wp media regenerate --only-missing (tamaños de imagen del theme).' );
		}
	}

	/**
	 * Revierte vídeos migrados a entradas.
	 *
	 * ## OPTIONS
	 *
	 * [--ids=<ids>]
	 * : Solo estos IDs (separados por coma). Por defecto todos los migrados.
	 *
	 * [--batch=<n>]
	 * : Tamaño del lote. Por defecto 200.
	 *
	 * [--yes]
	 * : No pedir confirmación.
	 *
	 * @subcommand revert-migration
	 *
	 * @param array $args  Posicionales.
	 * @param array $assoc Asociativos.
	 */
	public function revert_migration( $args, $assoc ) {
		$only  = ! empty( $assoc['ids'] ) ? array_filter( array_map( 'absint', explode( ',', (string) $assoc['ids'] ) ) ) : array();
		$batch = isset( $assoc['batch'] ) ? max( 5, min( 500, (int) $assoc['batch'] ) ) : 200;
		$total = $only ? count( $only ) : Clipnuvex_Post_Migrator::count_migrated();
		if ( ! $total ) {
			WP_CLI::success( 'No hay vídeos migrados que revertir.' );
			return;
		}
		if ( ! isset( $assoc['yes'] ) ) {
			WP_CLI::confirm( sprintf( '¿Devolver %d vídeos a entradas?', $total ), $assoc );
		}
		if ( ! Clipnuvex_Post_Migrator::acquire_lock( 'cli' ) ) {
			WP_CLI::error( 'Hay otra operación en curso (panel o CLI).' );
		}
		$migrator = new Clipnuvex_Post_Migrator( array( 'batch' => $batch ) );
		$state    = Clipnuvex_Post_Migrator::get_state();
		$state    = array_merge( is_array( $state ) ? $state : array(), array( 'running' => 'revert', 'owner' => 'cli', 'started' => time(), 'finished' => 0 ) );
		Clipnuvex_Post_Migrator::save_state( $state );

		$progress = \WP_CLI\Utils\make_progress_bar( 'Reversión', $total );
		$after    = 0;
		$counts   = array( 'reverted' => 0, 'error' => 0 );
		while ( true ) {
			Clipnuvex_Post_Migrator::renew_lock( 'cli' );
			$res = $migrator->revert_batch( $after, $batch, $only );
			foreach ( $res['rows'] as $row ) {
				$progress->tick();
				$counts[ 'error' === $row['status'] ? 'error' : 'reverted' ]++;
				if ( 'error' === $row['status'] ) {
					WP_CLI::warning( sprintf( '#%d: %s', $row['id'], $row['note'] ) );
				}
			}
			$after = $res['last_id'];
			if ( function_exists( '\WP_CLI\Utils\wp_clear_object_cache' ) ) {
				\WP_CLI\Utils\wp_clear_object_cache();
			}
			if ( $res['done'] ) {
				break;
			}
		}
		$progress->finish();
		Clipnuvex_Post_Migrator::finish();
		Clipnuvex_Post_Migrator::cleanup_if_empty();
		$state = Clipnuvex_Post_Migrator::get_state();
		$state['running']        = '';
		$state['finished']       = time();
		$state['migrated_total'] = Clipnuvex_Post_Migrator::count_migrated();
		Clipnuvex_Post_Migrator::save_state( $state );
		Clipnuvex_Post_Migrator::release_lock();
		WP_CLI::success( sprintf( 'Revertidos: %d · errores: %d', $counts['reverted'], $counts['error'] ) );
	}
}

WP_CLI::add_command( 'clipnuvex', 'Clipnuvex_CLI_Migrate' );
