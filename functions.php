<?php
/**
 * Clipnuvex theme bootstrap.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CLIPNUVEX_VERSION', '1.6.0' );
define( 'CLIPNUVEX_DIR', trailingslashit( get_template_directory() ) );
define( 'CLIPNUVEX_URI', trailingslashit( get_template_directory_uri() ) );

function clipnuvex_require( $relative ) {
	$path = CLIPNUVEX_DIR . ltrim( $relative, '/' );
	if ( file_exists( $path ) ) {
		require_once $path;
	}
}

$clipnuvex_modules = array(
	'inc/setup.php',
	'inc/enqueue.php',
	'inc/cpt.php',
	'inc/taxonomies.php',
	'inc/permalinks.php',
	'inc/meta.php',
	'inc/queries.php',
	'inc/template-tags.php',
	'inc/ads.php',
	'inc/options.php',
	'inc/colors.php',
	'inc/seo.php',
	'inc/schema.php',
	'inc/sitemap.php',
	'inc/i18n.php',
	'inc/demo/class-demo-importer.php',
	'inc/demo/page.php',
	'inc/migrate/class-post-migrator.php',
	'inc/migrate/redirects.php',
	'inc/migrate/page.php',
	'inc/migrate/cli.php',
	'inc/admin/options-page.php',
);

foreach ( $clipnuvex_modules as $clipnuvex_module ) {
	clipnuvex_require( $clipnuvex_module );
}

/*
|---------------------------------------------------------------------------------------------------
| 1. METABOX SHORTS
|---------------------------------------------------------------------------------------------------
*/
function clipnupor_agregar_metabox_shorts() {
    $tipos_post = array( 'video', 'post' );
    foreach ( $tipos_post as $tipo ) {
        add_meta_box( 'shorts_metabox_id', 'Shorts', 'clipnupor_mostrar_metabox_shorts', $tipo, 'side', 'high' );
    }
}
add_action( 'add_meta_boxes', 'clipnupor_agregar_metabox_shorts' );

function clipnupor_mostrar_metabox_shorts( $post ) {
    wp_nonce_field( 'guardar_shorts_nonce_action', 'shorts_nonce_field' );
    $valor = get_post_meta( $post->ID, 'is_short', true ) ?: 'off';
    ?>
    <p style="font-size: 13px; color: #888; margin-bottom: 8px;">¿Este vídeo es un Short vertical?</p>
    <select name="is_short" id="is_short" style="width:100%; border-radius: 4px; padding: 6px;">
        <option value="off" <?php selected( $valor, 'off' ); ?>>No</option>
        <option value="on" <?php selected( $valor, 'on' ); ?>>Sí</option>
    </select>
    <?php
}

function clipnupor_guardar_metabox_shorts( $post_id ) {
    if ( ! isset( $_POST['shorts_nonce_field'] ) || ! wp_verify_nonce( $_POST['shorts_nonce_field'], 'guardar_shorts_nonce_action' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    if ( isset( $_POST['is_short'] ) ) {
        update_post_meta( $post_id, 'is_short', sanitize_text_field( $_POST['is_short'] ) );
    }
}
add_action( 'save_post', 'clipnupor_guardar_metabox_shorts' );

/*
|---------------------------------------------------------------------------------------------------
| 2. METABOX 3 EMBEDS
|---------------------------------------------------------------------------------------------------
*/
function clipnupor_agregar_metabox_tres_embeds() {
    $tipos_post = array( 'video', 'post' );
    foreach ( $tipos_post as $tipo ) {
        add_meta_box( 'tres_embeds_metabox_id', 'Opciones de Vídeo (Servidores Embed 1, 2 y 3)', 'clipnupor_render_metabox_tres_embeds', $tipo, 'normal', 'high' );
    }
}
add_action( 'add_meta_boxes', 'clipnupor_agregar_metabox_tres_embeds' );

function clipnupor_render_metabox_tres_embeds( $post ) {
    wp_nonce_field( 'guardar_embeds_nonce_action', 'embeds_nonce_field' );
    $embed_1 = get_post_meta( $post->ID, 'embed', true ) ?: get_post_meta( $post->ID, 'video_embed_code_1', true );
    $embed_2 = get_post_meta( $post->ID, 'embed_2', true ) ?: get_post_meta( $post->ID, 'video_embed_code_2', true );
    $embed_3 = get_post_meta( $post->ID, 'embed_3', true ) ?: get_post_meta( $post->ID, 'video_embed_code_3', true );
    ?>
    <div style="display: flex; flex-direction: column; gap: 15px; margin-top: 10px;">
        <div>
            <label style="font-weight: bold; display: block; margin-bottom: 5px;">Opción 1 (Embed / Iframe principal):</label>
            <textarea name="video_embed_1" rows="3" style="width: 100%; font-family: monospace; border-radius: 4px;"><?php echo esc_textarea( $embed_1 ); ?></textarea>
        </div>
        <div>
            <label style="font-weight: bold; display: block; margin-bottom: 5px;">Opción 2 (Embed / Iframe secundario):</label>
            <textarea name="video_embed_2" rows="3" style="width: 100%; font-family: monospace; border-radius: 4px;"><?php echo esc_textarea( $embed_2 ); ?></textarea>
        </div>
        <div>
            <label style="font-weight: bold; display: block; margin-bottom: 5px;">Opción 3 (Embed / Iframe alternativo):</label>
            <textarea name="video_embed_3" rows="3" style="width: 100%; font-family: monospace; border-radius: 4px;"><?php echo esc_textarea( $embed_3 ); ?></textarea>
        </div>
    </div>
    <?php
}

function clipnupor_guardar_metabox_tres_embeds( $post_id ) {
    if ( ! isset( $_POST['embeds_nonce_field'] ) || ! wp_verify_nonce( $_POST['embeds_nonce_field'], 'guardar_embeds_nonce_action' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    if ( isset( $_POST['video_embed_1'] ) ) {
        $c1 = trim( $_POST['video_embed_1'] );
        update_post_meta( $post_id, 'embed', $c1 );
        update_post_meta( $post_id, 'video_embed_code_1', $c1 );
    }
    if ( isset( $_POST['video_embed_2'] ) ) {
        $c2 = trim( $_POST['video_embed_2'] );
        update_post_meta( $post_id, 'embed_2', $c2 );
        update_post_meta( $post_id, 'video_embed_code_2', $c2 );
    }
    if ( isset( $_POST['video_embed_3'] ) ) {
        $c3 = trim( $_POST['video_embed_3'] );
        update_post_meta( $post_id, 'embed_3', $c3 );
        update_post_meta( $post_id, 'video_embed_code_3', $c3 );
    }
}
add_action( 'save_post', 'clipnupor_guardar_metabox_tres_embeds' );

/*
|---------------------------------------------------------------------------------------------------
| 3. INTEGRACIONES: GOOGLE ANALYTICS & POPUNDER
|---------------------------------------------------------------------------------------------------
*/
function clipnuvex_admin_menu_integraciones() {
    add_menu_page( 'Integraciones', 'Integraciones', 'manage_options', 'clipnuvex-integraciones', 'clipnuvex_render_integraciones_page', 'dashicons-chart-area', 60 );
}
add_action( 'admin_menu', 'clipnuvex_admin_menu_integraciones' );

function clipnuvex_registrar_ajustes_integraciones() {
    register_setting( 'clipnuvex_integraciones_group', 'clipnuvex_ga_enable' );
    register_setting( 'clipnuvex_integraciones_group', 'clipnuvex_ga_id' );
    register_setting( 'clipnuvex_integraciones_group', 'clipnuvex_pop_enable' );
    register_setting( 'clipnuvex_integraciones_group', 'clipnuvex_pop_location' );
    register_setting( 'clipnuvex_integraciones_group', 'clipnuvex_pop_js' );
    register_setting( 'clipnuvex_integraciones_group', 'clipnuvex_pop_html' );
    register_setting( 'clipnuvex_integraciones_group', 'clipnuvex_pop_adicional' );
}
add_action( 'admin_init', 'clipnuvex_registrar_ajustes_integraciones' );

function clipnuvex_render_integraciones_page() {
    ?>
    <div class="wrap" style="max-width: 850px;">
        <h1>Integraciones &amp; Publicidad</h1>
        <form method="post" action="options.php" style="background:#fff; padding:25px; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.05); margin-top:20px;">
            <?php settings_fields( 'clipnuvex_integraciones_group' ); ?>
            <h2>Google Analytics</h2>
            <table class="form-table">
                <tr>
                    <th>Activar Google Analytics</th>
                    <td><input type="checkbox" name="clipnuvex_ga_enable" value="1" <?php checked( 1, get_option('clipnuvex_ga_enable') ); ?>></td>
                </tr>
                <tr>
                    <th>ID de medición</th>
                    <td><input type="text" name="clipnuvex_ga_id" value="<?php echo esc_attr( get_option('clipnuvex_ga_id') ); ?>" placeholder="G-XXXXXXXXXX" class="regular-text"></td>
                </tr>
            </table>
            <hr>
            <h2>Publicidad Popunder</h2>
            <table class="form-table">
                <tr>
                    <th>Activar popunder</th>
                    <td><input type="checkbox" name="clipnuvex_pop_enable" value="1" <?php checked( 1, get_option('clipnuvex_pop_enable') ); ?>></td>
                </tr>
                <tr>
                    <th>Código JavaScript</th>
                    <td><textarea name="clipnuvex_pop_js" rows="4" class="large-text code"><?php echo esc_textarea( get_option('clipnuvex_pop_js') ); ?></textarea></td>
                </tr>
                <tr>
                    <th>Código HTML</th>
                    <td><textarea name="clipnuvex_pop_html" rows="3" class="large-text code"><?php echo esc_textarea( get_option('clipnuvex_pop_html') ); ?></textarea></td>
                </tr>
                <tr>
                    <th>Código adicional</th>
                    <td><textarea name="clipnuvex_pop_adicional" rows="3" class="large-text code"><?php echo esc_textarea( get_option('clipnuvex_pop_adicional') ); ?></textarea></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function clipnuvex_inyectar_google_analytics() {
    if ( ! get_option( 'clipnuvex_ga_enable' ) ) return;
    $ga_id = trim( get_option( 'clipnuvex_ga_id' ) );
    if ( empty( $ga_id ) ) return;
    ?>
    <script>
    (function() {
        var gaId = <?php echo wp_json_encode( $ga_id ); ?>;
        function initGA() {
            if (window.cnxGaLoaded) return;
            window.cnxGaLoaded = true;
            var s = document.createElement('script');
            s.async = true;
            s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(gaId);
            document.head.appendChild(s);
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', gaId);
        }
        window.addEventListener('load', function() { setTimeout(initGA, 1500); });
        ['scroll', 'touchstart', 'click'].forEach(function(ev) { window.addEventListener(ev, initGA, { once: true, passive: true }); });
    })();
    </script>
    <?php
}
add_action( 'wp_head', 'clipnuvex_inyectar_google_analytics', 20 );

function clipnuvex_inyectar_popunder() {
    if ( ! get_option( 'clipnuvex_pop_enable' ) ) return;
    $pop_js   = get_option( 'clipnuvex_pop_js' );
    $pop_html = get_option( 'clipnuvex_pop_html' );
    $pop_add  = get_option( 'clipnuvex_pop_adicional' );
    if ( empty( $pop_js ) && empty( $pop_html ) && empty( $pop_add ) ) return;
    ?>
    <div id="cnx-popunder-wrap" style="display:none;"><?php echo $pop_html . $pop_add; ?></div>
    <?php if ( ! empty( $pop_js ) ) : ?>
    <script>
    (function() {
        var popDone = false;
        function triggerPop() {
            if (popDone) return;
            popDone = true;
            var wrap = document.getElementById('cnx-popunder-wrap');
            if (wrap) wrap.style.display = 'block';
            var s = document.createElement('script');
            s.type = 'text/javascript';
            <?php
            $clean_js = trim( $pop_js );
            if ( preg_match( '/<script\b[^>]*src=["\']([^"\']+)["\']/i', $clean_js, $m ) ) {
                echo "s.src = " . wp_json_encode( esc_url_raw( $m[1] ) ) . ";\n";
            } else {
                $js_body = preg_replace( '#</?script[^>]*>#is', '', $clean_js );
                echo "s.text = " . wp_json_encode( $js_body ) . ";\n";
            }
            ?>
            document.body.appendChild(s);
            ['click', 'touchstart'].forEach(function(e) { document.removeEventListener(e, triggerPop); });
        }
        ['click', 'touchstart'].forEach(function(e) { document.addEventListener(e, triggerPop, { once: true, passive: true }); });
    })();
    </script>
    <?php endif;
}
add_action( 'wp_footer', 'clipnuvex_inyectar_popunder', 999 );

/*
|---------------------------------------------------------------------------------------------------
| 4. MOTOR DE VISITAS: TOP 10 TAGS MÁS VISITADAS
|---------------------------------------------------------------------------------------------------
*/
function cnx_contar_visitas_tags() {
    if ( is_admin() || wp_doing_ajax() ) return;
    if ( ! is_tag() && ! is_tax( 'tag_video' ) ) return;

    $term = get_queried_object();
    if ( ! $term || empty( $term->term_id ) ) return;

    if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) && preg_match( '/bot|crawl|slurp|spider|mediapartners|lighthouse/i', $_SERVER['HTTP_USER_AGENT'] ) ) {
        return;
    }

    $views = (int) get_term_meta( $term->term_id, 'cnx_tag_views', true );
    update_term_meta( $term->term_id, 'cnx_tag_views', $views + 1 );
}
add_action( 'template_redirect', 'cnx_contar_visitas_tags' );

function cnx_get_top_visited_tags( $limit = 10 ) {
    $transient_key = 'cnx_top_10_visited_tags';
    $cached_tags   = get_transient( $transient_key );

    if ( false !== $cached_tags ) {
        return $cached_tags;
    }

    $taxonomies = array();
    if ( taxonomy_exists( 'tag_video' ) ) $taxonomies[] = 'tag_video';
    if ( taxonomy_exists( 'post_tag' ) )  $taxonomies[] = 'post_tag';
    if ( empty( $taxonomies ) )           $taxonomies   = array( 'post_tag' );

    $tags = get_terms( array(
        'taxonomy'   => $taxonomies,
        'hide_empty' => true,
        'number'     => $limit,
        'meta_key'   => 'cnx_tag_views',
        'orderby'    => 'meta_value_num',
        'order'      => 'DESC',
    ) );

    if ( empty( $tags ) || count( $tags ) < $limit ) {
        $exclude_ids = ! empty( $tags ) ? wp_list_pluck( $tags, 'term_id' ) : array();
        $needed      = $limit - count( $tags );

        $fallback_tags = get_terms( array(
            'taxonomy'   => $taxonomies,
            'hide_empty' => true,
            'number'     => $needed,
            'orderby'    => 'count',
            'order'      => 'DESC',
            'exclude'    => $exclude_ids,
        ) );

        if ( ! empty( $fallback_tags ) && ! is_wp_error( $fallback_tags ) ) {
            $tags = array_merge( (array) $tags, (array) $fallback_tags );
        }
    }

    if ( is_wp_error( $tags ) ) $tags = array();

    set_transient( $transient_key, $tags, HOUR_IN_SECONDS );
    return $tags;
}
