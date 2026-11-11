<?php
/**
 * Template Name: Directorio de Tags (A-Z)
 *
 * Optimizado: Top 40 tags populares por letra, caché Transient HTML y renderizado GPU.
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header(); //

$cache_key   = 'cnx_tags_popular_html_cache';
$cached_html = get_transient( $cache_key );

if ( false === $cached_html ) {
	global $wpdb;

	// 1. Identificar taxonomías activas (tag_video y post_tag)
	$taxonomies = array();
	if ( taxonomy_exists( 'tag_video' ) ) {
		$taxonomies[] = 'tag_video'; //[cite: 4]
	}
	if ( taxonomy_exists( 'post_tag' ) ) {
		$taxonomies[] = 'post_tag'; //[cite: 4]
	}
	if ( empty( $taxonomies ) ) {
		$taxonomies = array( 'post_tag' ); //[cite: 4]
	}

	$tax_sql = implode( "','", array_map( 'esc_sql', $taxonomies ) );

	// 2. Consulta SQL ultra-rápida (ordena por popularidad descendente de entrada)
	$results = $wpdb->get_results( "
		SELECT t.term_id, t.name, t.slug, tt.taxonomy, tt.count
		FROM {$wpdb->terms} t
		INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
		WHERE tt.taxonomy IN ('$tax_sql') AND tt.count > 0
		ORDER BY tt.count DESC
	" );

	$limit_per_letter = 40; // Rango seleccionado (entre 35 y 45 más populares)
	$grouped_tags     = array();

	if ( ! empty( $results ) ) {
		foreach ( $results as $row ) {
			$first_char = mb_strtoupper( mb_substr( $row->name, 0, 1, 'UTF-8' ), 'UTF-8' );

			// Normalizar tildes y caracteres especiales
			$unaccented = @iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $first_char );
			if ( ! empty( $unaccented ) && ctype_alpha( $unaccented[0] ) ) {
				$letter = strtoupper( $unaccented[0] );
			} elseif ( is_numeric( $first_char ) ) {
				$letter = '#';
			} else {
				$letter = '#';
			}

			// Solo guardar si la letra aún no alcanza el límite de popularidad
			if ( ! isset( $grouped_tags[ $letter ] ) || count( $grouped_tags[ $letter ] ) < $limit_per_letter ) {
				$grouped_tags[ $letter ][] = array(
					'name'  => $row->name,
					'count' => (int) $row->count,
					'url'   => get_term_link( (int) $row->term_id, $row->taxonomy ),
				);
			}
		}

		ksort( $grouped_tags );

		// Colocar '#' al inicio si existe
		if ( isset( $grouped_tags['#'] ) ) {
			$num_group = array( '#' => $grouped_tags['#'] );
			unset( $grouped_tags['#'] );
			$grouped_tags = $num_group + $grouped_tags;
		}

		// Ordenar las 40 etiquetas de cada letra en orden A-Z para lectura cómoda
		foreach ( $grouped_tags as $letter => &$tags ) {
			usort( $tags, function( $a, $b ) {
				return strcoll( $a['name'], $b['name'] );
			} );
		}
		unset( $tags );
	}

	// 3. Generar y compilar el HTML en memoria
	ob_start();
	?>
	<div class="cnx-view cnx-tags-view-container">

		<header class="cnx-tags-header">
			<div class="cnx-tags-title-row">
				<span class="cnx-tags-icon">⚡</span>
				<h1 class="cnx-tags-main-title"><?php esc_html_e( 'Directorio de Tags Populares', 'clipnuvex' ); ?></h1>
			</div>
			<p class="cnx-tags-sub-title">
				<?php esc_html_e( 'Mostrando las etiquetas más destacadas organizadas de la A a la Z.', 'clipnuvex' ); ?>
			</p>
		</header>

		<?php if ( ! empty( $grouped_tags ) ) : ?>
			<!-- Barra de navegación rápida -->
			<nav class="cnx-az-quicknav" aria-label="<?php esc_attr_e( 'Índice alfabético', 'clipnuvex' ); ?>">
				<?php foreach ( array_keys( $grouped_tags ) as $nav_char ) : 
					$nav_target = ( '#' === $nav_char ) ? 'num' : $nav_char;
				?>
					<a href="#letter-<?php echo esc_attr( $nav_target ); ?>" class="cnx-az-letter-btn">
						<?php echo esc_html( $nav_char ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<!-- Contenedor alfabético optimizado -->
			<div class="cnx-tags-directory-list">
				<?php foreach ( $grouped_tags as $letter => $tags ) : 
					$anchor_id = ( '#' === $letter ) ? 'num' : $letter;
				?>
					<div class="cnx-tag-group-block" id="letter-<?php echo esc_attr( $anchor_id ); ?>">
						<div class="cnx-tag-letter-col">
							<span class="cnx-tag-big-letter"><?php echo esc_html( $letter ); ?></span>
						</div>

						<div class="cnx-tag-chips-col">
							<?php foreach ( $tags as $tag_data ) : ?>
								<a href="<?php echo esc_url( $tag_data['url'] ); ?>" class="cnx-tag-chip">
									<span class="cnx-tag-chip-name"><?php echo esc_html( $tag_data['name'] ); ?></span>
									<span class="cnx-tag-chip-count"><?php echo esc_html( number_format_i18n( $tag_data['count'] ) ); ?></span>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="cnx-noresults">
				<div class="cnx-noresults__title"><?php esc_html_e( 'No hay tags disponibles en este momento.', 'clipnuvex' ); ?></div>
			</div>
		<?php endif; ?>

	</div>
	<?php
	$cached_html = ob_get_clean();

	// Guardar en Transient por 24 horas (Opción 1)
	set_transient( $cache_key, $cached_html, 24 * HOUR_IN_SECONDS );
}

// Imprimir HTML precompilado
echo $cached_html;
?>

<style>
/* ==============================================================================================
   OPCIÓN 4: RENDERIZADO DIFERIDO EN NAVEGADOR (Zero Scroll Lag)
   ============================================================================================== */
.cnx-tag-group-block {
	content-visibility: auto; /* Solo procesa en GPU lo visible en pantalla */
	contain-intrinsic-size: 0 150px; /* Reserva la altura estimada para evitar saltos en el scroll */
	display: flex;
	align-items: flex-start;
	gap: 20px;
	padding: 22px;
	background: #ffffff;
	border: 1px solid #e5e7eb;
	border-radius: 14px;
	box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
	scroll-margin-top: 90px;
}

/* ==============================================================================================
   DISEÑO Y CONTRASTES DE CLIPNUVEX
   ============================================================================================== */
.cnx-tags-view-container {
	max-width: 1300px;
	margin: 0 auto;
	padding: 25px 20px 60px;
}

.cnx-tags-header {
	margin-bottom: 25px;
}

.cnx-tags-title-row {
	display: flex;
	align-items: center;
	gap: 10px;
	margin-bottom: 6px;
}

.cnx-tags-icon {
	color: #ff2a5f;
	font-size: 24px;
	line-height: 1;
}

.cnx-tags-main-title {
	margin: 0;
	font-size: 24px;
	font-weight: 800;
	color: #111827;
	text-transform: uppercase;
	letter-spacing: -0.01em;
}

.cnx-tags-sub-title {
	margin: 0;
	font-size: 14px;
	color: #6b7280;
}

/* Barra Superior A-Z */
.cnx-az-quicknav {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
	margin-bottom: 35px;
	padding: 12px 14px;
	background: #ffffff;
	border: 1px solid #e5e7eb;
	border-radius: 12px;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
}

.cnx-az-letter-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 36px;
	height: 36px;
	background: #f3f4f6;
	color: #1f2937;
	border-radius: 8px;
	font-size: 13px;
	font-weight: 700;
	text-decoration: none;
	transition: all 0.2s cubic-bezier(0.25, 0.8, 0.25, 1);
}

.cnx-az-letter-btn:hover {
	background: #ff2a5f !important;
	color: #ffffff !important;
	transform: translateY(-2px);
	box-shadow: 0 4px 10px rgba(255, 42, 95, 0.3);
	text-decoration: none;
}

.cnx-tags-directory-list {
	display: flex;
	flex-direction: column;
	gap: 24px;
}

.cnx-tag-letter-col {
	min-width: 44px;
}

.cnx-tag-big-letter {
	font-size: 28px;
	font-weight: 900;
	color: #111827;
	line-height: 1;
	display: inline-block;
}

/* Píldoras de Tags */
.cnx-tag-chips-col {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	flex: 1;
}

.cnx-tag-chip {
	display: inline-flex;
	align-items: center;
	gap: 7px;
	padding: 7px 14px;
	background: #f8f9fa;
	color: #374151;
	border: 1px solid #e5e7eb;
	border-radius: 50px;
	font-size: 13px;
	font-weight: 600;
	text-decoration: none;
	transition: all 0.2s ease;
}

.cnx-tag-chip:hover {
	background: #ff2a5f;
	border-color: #ff2a5f;
	color: #ffffff;
	transform: translateY(-2px);
	box-shadow: 0 4px 12px rgba(255, 42, 95, 0.25);
	text-decoration: none;
}

.cnx-tag-chip-count {
	font-size: 11px;
	font-weight: 700;
	padding: 2px 7px;
	border-radius: 20px;
	background: #e5e7eb;
	color: #6b7280;
	transition: all 0.2s ease;
}

.cnx-tag-chip:hover .cnx-tag-chip-count {
	background: rgba(255, 255, 255, 0.25);
	color: #ffffff;
}

/* Responsive */
@media (max-width: 640px) {
	.cnx-tag-group-block {
		flex-direction: column;
		gap: 14px;
		padding: 16px;
	}
	.cnx-az-letter-btn {
		width: 32px;
		height: 32px;
		font-size: 12px;
	}
}
</style>

<?php
get_footer(); //[cite: 4]