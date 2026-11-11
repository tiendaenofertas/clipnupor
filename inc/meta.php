<?php
/**
 * Meta boxes nativos del CPT video + term meta de categoría.
 *
 * Campos del vídeo: embed_url (una o varias fuentes), fuente, calidad,
 * idioma, anio, duracion, destacado.
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Definición central de los meta del vídeo.
 *
 * @return array
 */
function clipnuvex_video_meta_fields() {
	return array(
		'_clipnuvex_embed_url' => array(
			'label'    => __( 'URL(s) de embed / iframe', 'clipnuvex' ),
			'type'     => 'textarea',
			'sanitize' => 'clipnuvex_sanitize_embed_urls',
			'desc'     => __( 'Una fuente por línea: pega la URL del embed, el código <iframe> completo O un [shortcode] de cualquier plugin de vídeo. Formato opcional "Etiqueta|valor|Calidad" para nombrar servidores. Varias líneas activan las pestañas de servidor.', 'clipnuvex' ),
		),
		'_clipnuvex_fuente'    => array(
			'label'    => __( 'Fuente', 'clipnuvex' ),
			'type'     => 'select',
			'options'  => array(
				'embed'  => __( 'Embed (iframe / YouTube)', 'clipnuvex' ),
				'propio' => __( 'Reproductor propio', 'clipnuvex' ),
			),
			'sanitize' => 'sanitize_text_field',
		),
		'_clipnuvex_calidad'   => array(
			'label'    => __( 'Calidad', 'clipnuvex' ),
			'type'     => 'select',
			'options'  => array(
				'HD' => 'HD',
				'4K' => '4K',
				'SD' => 'SD',
			),
			'sanitize' => 'sanitize_text_field',
		),
		'_clipnuvex_idioma'    => array(
			'label'    => __( 'Idioma', 'clipnuvex' ),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
			'desc'     => __( 'P. ej. Español, Latino, Subtitulado, English.', 'clipnuvex' ),
		),
		'_clipnuvex_anio'      => array(
			'label'    => __( 'Año', 'clipnuvex' ),
			'type'     => 'number',
			'sanitize' => 'absint',
		),
		'_clipnuvex_duracion'  => array(
			'label'    => __( 'Duración (min)', 'clipnuvex' ),
			'type'     => 'number',
			'sanitize' => 'absint',
			'desc'     => __( 'Opcional. No se muestra en la ficha pero alimenta el schema.', 'clipnuvex' ),
		),
		'_clipnuvex_destacado' => array(
			'label'    => __( 'Destacado', 'clipnuvex' ),
			'type'     => 'checkbox',
			'sanitize' => 'clipnuvex_sanitize_bool',
		),
	);
}

/**
 * Normaliza un valor de embed a una URL segura. Acepta tanto una URL directa
 * como un código <iframe ...> completo (de cualquier proveedor): en ese caso
 * extrae el atributo src. Devuelve '' si no hay una URL http/https válida.
 *
 * Seguridad: el resultado pasa por esc_url_raw (solo protocolos permitidos), por
 * lo que javascript:, data:, etc. se descartan. El theme NUNCA vuelca el HTML del
 * proveedor: construye su propio <iframe> con este src ya validado.
 *
 * @param string $value URL o código <iframe>.
 * @return string URL saneada o ''.
 */
function clipnuvex_extract_embed_src( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}
	// ¿Es un código <iframe ...>? Extraer el src (admite comillas dobles, simples
	// o sin comillas). Si no tiene src usable, se descarta.
	if ( false !== stripos( $value, '<iframe' ) ) {
		if ( preg_match( '#<iframe[^>]*\bsrc\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))#i', $value, $m ) ) {
			$value = ! empty( $m[1] ) ? $m[1] : ( ! empty( $m[2] ) ? $m[2] : ( isset( $m[3] ) ? $m[3] : '' ) );
		} else {
			return '';
		}
	}
	// Al pegar HTML, el src puede traer entidades (&amp; → &). Decodificarlas para
	// no romper los parámetros de la URL.
	$value = html_entity_decode( $value, ENT_QUOTES, 'UTF-8' );
	$value = trim( $value );
	// Si tras la extracción sigue habiendo marcado HTML (<script>, <div>, etc.),
	// NO es una URL limpia: se rechaza por completo en vez de dejar que esc_url_raw
	// genere una URL basura. Una URL real nunca contiene '<' o '>' sin codificar.
	if ( false !== strpos( $value, '<' ) || false !== strpos( $value, '>' ) ) {
		return '';
	}
	// esc_url_raw admite http/https y URLs protocolo-relativas (//host/...).
	return esc_url_raw( $value );
}

/**
 * Parsea UNA línea del campo de fuentes en sus componentes. Cada línea puede
 * ser una URL, un código <iframe> o un [shortcode] de cualquier plugin de
 * vídeo, con el formato opcional "Etiqueta|valor|Calidad".
 *
 * Es la ÚNICA fuente de verdad del formato: la usan tanto el guardado
 * (clipnuvex_sanitize_embed_urls) como la lectura (clipnuvex_video_servers),
 * así ambos no pueden divergir.
 *
 * Seguridad del shortcode: se guarda como texto plano (nunca contiene '<' ni
 * '>') y SOLO se ejecuta en el frontend vía do_shortcode() cuando su tag está
 * registrado; el theme jamás vuelca el texto crudo en la página.
 *
 * @param string $line Línea cruda (al guardar) o ya saneada (al leer).
 * @return array|null ['type' => 'url'|'shortcode', 'url', 'code', 'label', 'quality'] o null si inválida.
 */
function clipnuvex_parse_embed_line( $line ) {
	$line = trim( (string) $line );
	if ( '' === $line ) {
		return null;
	}
	// ¿La línea contiene un [shortcode]? Se localiza por POSICIÓN (no con un
	// split por '|') para tolerar '|' dentro de sus atributos. Solo cuenta como
	// shortcode si ocupa la línea entera o va delimitado por '|' (formato
	// Etiqueta|valor|Calidad); así unos corchetes dentro de una URL o de un
	// query-string (p. ej. ?opts[autoplay]=1) nunca se confunden con uno. Se
	// exige además que la línea no traiga HTML ('<' o '>').
	if ( false === strpos( $line, '<' ) && false === strpos( $line, '>' )
		&& preg_match( '#\[([a-zA-Z0-9_-]+)(?:\s[^\]]*)?\](?:.*\[/\1\])?#s', $line, $m, PREG_OFFSET_CAPTURE ) ) {
		$code   = $m[0][0];
		$offset = $m[0][1];
		$prefix = rtrim( substr( $line, 0, $offset ) );
		$suffix = ltrim( substr( $line, $offset + strlen( $code ) ) );
		if ( ( '' === $prefix || '|' === substr( $prefix, -1 ) )
			&& ( '' === $suffix || 0 === strpos( $suffix, '|' ) ) ) {
			return array(
				'type'    => 'shortcode',
				'url'     => '',
				'code'    => $code,
				'label'   => sanitize_text_field( trim( $prefix, "| \t" ) ),
				'quality' => sanitize_text_field( trim( $suffix, "| \t" ) ),
			);
		}
	}
	// "Etiqueta|valor|Calidad" clásico: el valor (parte central) puede ser URL
	// o <iframe>; el split por '|' es seguro porque un src no contiene '|'.
	$parts    = array_map( 'trim', explode( '|', $line ) );
	$has_meta = count( $parts ) > 1;
	$value    = $has_meta ? $parts[1] : $parts[0];
	$url      = clipnuvex_extract_embed_src( $value );
	if ( ! $url ) {
		return null;
	}
	return array(
		'type'    => 'url',
		'url'     => $url,
		'code'    => '',
		'label'   => $has_meta ? sanitize_text_field( $parts[0] ) : '',
		'quality' => ( $has_meta && isset( $parts[2] ) ) ? sanitize_text_field( $parts[2] ) : '',
	);
}

/**
 * Sanitiza el bloque de fuentes de embed (una por línea). Cada línea puede ser
 * una URL, un código <iframe> completo o un [shortcode], con el formato
 * opcional "Etiqueta|valor|Calidad" para nombrar servidores.
 *
 * @param string $raw Valor crudo.
 * @return string
 */
function clipnuvex_sanitize_embed_urls( $raw ) {
	$lines = preg_split( '/\r\n|\r|\n/', (string) $raw );
	$clean = array();
	foreach ( $lines as $line ) {
		$parsed = clipnuvex_parse_embed_line( $line );
		if ( null === $parsed ) {
			continue;
		}
		$value = ( 'shortcode' === $parsed['type'] ) ? $parsed['code'] : $parsed['url'];
		if ( '' !== $parsed['label'] || '' !== $parsed['quality'] ) {
			// rtrim (no trim): con etiqueta vacía pero calidad presente se
			// conserva el '|' inicial para que la lectura no corra las partes.
			$clean[] = rtrim( $parsed['label'] . '|' . $value . '|' . $parsed['quality'], '|' );
		} else {
			$clean[] = $value;
		}
	}
	return implode( "\n", $clean );
}

/**
 * Sanitiza un booleano de checkbox.
 *
 * @param mixed $val Valor.
 * @return string '1' o ''.
 */
function clipnuvex_sanitize_bool( $val ) {
	return $val ? '1' : '';
}

/**
 * Registra los meta para REST y sanitización.
 */
function clipnuvex_register_post_meta() {
	foreach ( clipnuvex_video_meta_fields() as $key => $field ) {
		register_post_meta(
			'video',
			$key,
			array(
				'type'              => ( 'number' === $field['type'] ) ? 'integer' : 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => $field['sanitize'],
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}
add_action( 'init', 'clipnuvex_register_post_meta' );

/**
 * Añade la meta box al editor de vídeos.
 */
function clipnuvex_add_meta_boxes() {
	add_meta_box(
		'clipnuvex_video_details',
		__( 'Detalles del vídeo', 'clipnuvex' ),
		'clipnuvex_render_meta_box',
		'video',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'clipnuvex_add_meta_boxes' );

/**
 * Renderiza la meta box.
 *
 * @param WP_Post $post Post actual.
 */
function clipnuvex_render_meta_box( $post ) {
	wp_nonce_field( 'clipnuvex_save_meta', 'clipnuvex_meta_nonce' );
	echo '<div class="clipnuvex-meta-grid" style="display:grid;gap:16px;">';

	foreach ( clipnuvex_video_meta_fields() as $key => $field ) {
		$value = get_post_meta( $post->ID, $key, true );
		printf( '<p style="margin:0;"><label for="%1$s" style="display:block;font-weight:600;margin-bottom:4px;">%2$s</label>', esc_attr( $key ), esc_html( $field['label'] ) );

		switch ( $field['type'] ) {
			case 'textarea':
				printf( '<textarea id="%1$s" name="%1$s" rows="3" class="widefat" style="width:100%%;">%2$s</textarea>', esc_attr( $key ), esc_textarea( $value ) );
				break;
			case 'select':
				printf( '<select id="%1$s" name="%1$s">', esc_attr( $key ) );
				foreach ( $field['options'] as $opt_val => $opt_label ) {
					printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $opt_val ), selected( $value, $opt_val, false ), esc_html( $opt_label ) );
				}
				echo '</select>';
				break;
			case 'checkbox':
				printf( '<label><input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s> %3$s</label>', esc_attr( $key ), checked( $value, '1', false ), esc_html__( 'Marcar como destacado', 'clipnuvex' ) );
				break;
			case 'number':
				printf( '<input type="number" id="%1$s" name="%1$s" value="%2$s" class="small-text">', esc_attr( $key ), esc_attr( $value ) );
				break;
			default:
				printf( '<input type="text" id="%1$s" name="%1$s" value="%2$s" class="widefat">', esc_attr( $key ), esc_attr( $value ) );
		}

		if ( ! empty( $field['desc'] ) ) {
			printf( '<span class="description" style="display:block;margin-top:4px;color:#666;">%s</span>', esc_html( $field['desc'] ) );
		}
		echo '</p>';
	}

	echo '</div>';
}

/**
 * Guarda los meta del vídeo.
 *
 * @param int $post_id ID del post.
 */
function clipnuvex_save_meta( $post_id ) {
	if ( ! isset( $_POST['clipnuvex_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['clipnuvex_meta_nonce'] ) ), 'clipnuvex_save_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( get_post_type( $post_id ) !== 'video' ) {
		return;
	}

	foreach ( clipnuvex_video_meta_fields() as $key => $field ) {
		if ( 'checkbox' === $field['type'] ) {
			$value = isset( $_POST[ $key ] ) ? '1' : '';
		} elseif ( isset( $_POST[ $key ] ) ) {
			$raw   = wp_unslash( $_POST[ $key ] );
			$value = call_user_func( $field['sanitize'], $raw );
		} else {
			$value = '';
		}
		update_post_meta( $post_id, $key, $value );
	}
}
add_action( 'save_post_video', 'clipnuvex_save_meta' );

/* -------------------------------------------------------------------------
 * Term meta: imagen destacada + backdrop por categoría.
 * ---------------------------------------------------------------------- */

/**
 * Campos de imagen en el formulario "Añadir categoría".
 */
function clipnuvex_term_add_image_field() {
	?>
	<div class="form-field term-clipnuvex-image-wrap">
		<label for="clipnuvex_term_image"><?php esc_html_e( 'Imagen destacada (póster/backdrop del término)', 'clipnuvex' ); ?></label>
		<input type="hidden" id="clipnuvex_term_image" name="clipnuvex_term_image" value="">
		<button type="button" class="button clipnuvex-upload-image"><?php esc_html_e( 'Seleccionar imagen', 'clipnuvex' ); ?></button>
		<p class="description"><?php esc_html_e( 'Se usa en la página de categorías y en el hero del archivo.', 'clipnuvex' ); ?></p>
		<div class="clipnuvex-image-preview" style="margin-top:8px;"></div>
	</div>
	<?php
}
add_action( 'categoria_video_add_form_fields', 'clipnuvex_term_add_image_field' );

/**
 * Campo de imagen en el formulario "Editar categoría".
 *
 * @param WP_Term $term Término.
 */
function clipnuvex_term_edit_image_field( $term ) {
	$image_id = absint( get_term_meta( $term->term_id, 'clipnuvex_term_image', true ) );
	$src      = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
	?>
	<tr class="form-field term-clipnuvex-image-wrap">
		<th scope="row"><label for="clipnuvex_term_image"><?php esc_html_e( 'Imagen destacada', 'clipnuvex' ); ?></label></th>
		<td>
			<input type="hidden" id="clipnuvex_term_image" name="clipnuvex_term_image" value="<?php echo esc_attr( $image_id ); ?>">
			<button type="button" class="button clipnuvex-upload-image"><?php esc_html_e( 'Seleccionar imagen', 'clipnuvex' ); ?></button>
			<button type="button" class="button clipnuvex-remove-image" <?php disabled( ! $image_id ); ?>><?php esc_html_e( 'Quitar', 'clipnuvex' ); ?></button>
			<div class="clipnuvex-image-preview" style="margin-top:8px;">
				<?php if ( $src ) : ?>
					<img src="<?php echo esc_url( $src ); ?>" style="max-width:200px;height:auto;border-radius:8px;">
				<?php endif; ?>
			</div>
		</td>
	</tr>
	<?php
}
add_action( 'categoria_video_edit_form_fields', 'clipnuvex_term_edit_image_field' );

/**
 * Guarda la imagen del término.
 *
 * @param int $term_id ID del término.
 */
function clipnuvex_save_term_image( $term_id ) {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	if ( isset( $_POST['clipnuvex_term_image'] ) ) {
		// nonce de WP para edición de términos lo gestiona el core (edit-tags).
		check_admin_referer( 'update-tag_' . $term_id );
		update_term_meta( $term_id, 'clipnuvex_term_image', absint( $_POST['clipnuvex_term_image'] ) );
	}
}
add_action( 'edited_categoria_video', 'clipnuvex_save_term_image' );

/**
 * Guarda la imagen al crear término (sin el nonce de update-tag).
 *
 * @param int $term_id ID del término.
 */
function clipnuvex_create_term_image( $term_id ) {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	// Solo en el envío del formulario "Añadir término" (no en creaciones
	// programáticas como el Modo Demo, que no envían este campo POST).
	if ( isset( $_POST['clipnuvex_term_image'] ) ) {
		check_admin_referer( 'add-tag', '_wpnonce_add-tag' );
		update_term_meta( $term_id, 'clipnuvex_term_image', absint( wp_unslash( $_POST['clipnuvex_term_image'] ) ) );
	}
}
add_action( 'created_categoria_video', 'clipnuvex_create_term_image' );

/**
 * Encola el media uploader en las pantallas de términos de categoría.
 *
 * @param string $hook Hook actual del admin.
 */
function clipnuvex_term_media_assets( $hook ) {
	if ( 'edit-tags.php' !== $hook && 'term.php' !== $hook ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || 'categoria_video' !== $screen->taxonomy ) {
		return;
	}
	wp_enqueue_media();
	wp_add_inline_script(
		'jquery-core',
		"jQuery(function($){
			var frame;
			$(document).on('click','.clipnuvex-upload-image',function(e){
				e.preventDefault();
				var \$wrap=$(this).closest('.term-clipnuvex-image-wrap, td, .form-field');
				if(frame){frame.open();return;}
				frame=wp.media({title:'Seleccionar imagen',multiple:false});
				frame.on('select',function(){
					var att=frame.state().get('selection').first().toJSON();
					\$wrap.find('#clipnuvex_term_image').val(att.id);
					\$wrap.find('.clipnuvex-image-preview').html('<img src=\"'+att.url+'\" style=\"max-width:200px;height:auto;border-radius:8px;\">');
					\$wrap.find('.clipnuvex-remove-image').prop('disabled',false);
				});
				frame.open();
			});
			$(document).on('click','.clipnuvex-remove-image',function(e){
				e.preventDefault();
				var \$wrap=$(this).closest('.term-clipnuvex-image-wrap, td, .form-field');
				\$wrap.find('#clipnuvex_term_image').val('');
				\$wrap.find('.clipnuvex-image-preview').empty();
				$(this).prop('disabled',true);
			});
		});"
	);
}
add_action( 'admin_enqueue_scripts', 'clipnuvex_term_media_assets' );

/* -------------------------------------------------------------------------- */
/* Bloque SEO por categoría (term meta con fallback a los textos del panel).  */
/* -------------------------------------------------------------------------- */

/**
 * Definición de los campos del bloque SEO editables en cada categoría.
 *
 * @return array id (= meta key) => [label, type, desc].
 */
function clipnuvex_term_seo_fields() {
	return array(
		'clipnuvex_seo_title'  => array(
			'label' => __( 'Título del bloque SEO', 'clipnuvex' ),
			'type'  => 'text',
			'desc'  => __( 'Vacío = usa el título global del panel. %s = nombre de la categoría.', 'clipnuvex' ),
		),
		'clipnuvex_seo_intro'  => array(
			'label' => __( 'Introducción del bloque SEO', 'clipnuvex' ),
			'type'  => 'textarea',
			'desc'  => __( 'Vacío = usa la introducción global del panel. %s = nombre de la categoría.', 'clipnuvex' ),
		),
		'clipnuvex_seo_points' => array(
			'label' => __( 'Puntos del bloque SEO (uno por línea)', 'clipnuvex' ),
			'type'  => 'textarea',
			'desc'  => __( 'Un punto por línea; admite <strong> y <em>. Vacío = usa los puntos globales del panel.', 'clipnuvex' ),
		),
		'clipnuvex_seo_hide'   => array(
			'label' => __( 'Ocultar el bloque SEO en esta categoría', 'clipnuvex' ),
			'type'  => 'checkbox',
			'desc'  => '',
		),
	);
}

/**
 * Campos del bloque SEO en el formulario "Añadir categoría".
 */
function clipnuvex_term_add_seo_fields() {
	?>
	<h3 style="margin-top:1.5em;"><?php esc_html_e( 'Bloque SEO de esta categoría', 'clipnuvex' ); ?></h3>
	<p class="description"><?php esc_html_e( 'Personaliza el recuadro "¿Por qué ver…?" de la página de esta categoría. Vacío = se usan los textos globales del panel (Clipnuvex → Textos → Bloque SEO de categoría).', 'clipnuvex' ); ?></p>
	<?php foreach ( clipnuvex_term_seo_fields() as $cnx_id => $cnx_field ) : ?>
		<div class="form-field">
			<?php if ( 'checkbox' === $cnx_field['type'] ) : ?>
				<label><input type="checkbox" name="<?php echo esc_attr( $cnx_id ); ?>" value="1"> <?php echo esc_html( $cnx_field['label'] ); ?></label>
			<?php else : ?>
				<label for="<?php echo esc_attr( $cnx_id ); ?>"><?php echo esc_html( $cnx_field['label'] ); ?></label>
				<?php if ( 'textarea' === $cnx_field['type'] ) : ?>
					<textarea id="<?php echo esc_attr( $cnx_id ); ?>" name="<?php echo esc_attr( $cnx_id ); ?>" rows="4"></textarea>
				<?php else : ?>
					<input type="text" id="<?php echo esc_attr( $cnx_id ); ?>" name="<?php echo esc_attr( $cnx_id ); ?>" value="">
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( $cnx_field['desc'] ) : ?>
				<p class="description"><?php echo esc_html( $cnx_field['desc'] ); ?></p>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
	<?php
}
add_action( 'categoria_video_add_form_fields', 'clipnuvex_term_add_seo_fields' );

/**
 * Campos del bloque SEO en el formulario "Editar categoría".
 *
 * @param WP_Term $term Término en edición.
 */
function clipnuvex_term_edit_seo_fields( $term ) {
	?>
	<tr>
		<th scope="row" colspan="2" style="padding-bottom:4px;">
			<h3 style="margin:1em 0 4px;"><?php esc_html_e( 'Bloque SEO de esta categoría', 'clipnuvex' ); ?></h3>
			<p class="description" style="font-weight:normal;"><?php esc_html_e( 'Personaliza el recuadro "¿Por qué ver…?" de la página de esta categoría. Vacío = se usan los textos globales del panel (Clipnuvex → Textos → Bloque SEO de categoría).', 'clipnuvex' ); ?></p>
		</th>
	</tr>
	<?php
	foreach ( clipnuvex_term_seo_fields() as $cnx_id => $cnx_field ) :
		$cnx_value = (string) get_term_meta( $term->term_id, $cnx_id, true );
		?>
		<tr class="form-field">
			<th scope="row"><label for="<?php echo esc_attr( $cnx_id ); ?>"><?php echo esc_html( $cnx_field['label'] ); ?></label></th>
			<td>
				<?php if ( 'checkbox' === $cnx_field['type'] ) : ?>
					<label><input type="checkbox" id="<?php echo esc_attr( $cnx_id ); ?>" name="<?php echo esc_attr( $cnx_id ); ?>" value="1" <?php checked( '1', $cnx_value ); ?>> <?php echo esc_html( $cnx_field['label'] ); ?></label>
				<?php elseif ( 'textarea' === $cnx_field['type'] ) : ?>
					<textarea id="<?php echo esc_attr( $cnx_id ); ?>" name="<?php echo esc_attr( $cnx_id ); ?>" rows="5"><?php echo esc_textarea( $cnx_value ); ?></textarea>
				<?php else : ?>
					<input type="text" id="<?php echo esc_attr( $cnx_id ); ?>" name="<?php echo esc_attr( $cnx_id ); ?>" value="<?php echo esc_attr( $cnx_value ); ?>">
				<?php endif; ?>
				<?php if ( $cnx_field['desc'] ) : ?>
					<p class="description"><?php echo esc_html( $cnx_field['desc'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
	<?php
}
add_action( 'categoria_video_edit_form_fields', 'clipnuvex_term_edit_seo_fields' );

/**
 * Sanea y persiste los campos del bloque SEO de un término. Un valor vacío se
 * ELIMINA (semántica de fallback al panel + BD limpia).
 *
 * @param int $term_id ID del término.
 */
function clipnuvex_persist_term_seo( $term_id ) {
	$allowed = array(
		'strong' => array(),
		'em'     => array(),
	);

	// Título e intro (texto plano; el placeholder %s se conserva tal cual).
	$title = isset( $_POST['clipnuvex_seo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['clipnuvex_seo_title'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$intro = isset( $_POST['clipnuvex_seo_intro'] ) ? sanitize_textarea_field( wp_unslash( $_POST['clipnuvex_seo_intro'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

	// Puntos: una línea por punto, kses por línea, descartando vacías.
	$raw_points = isset( $_POST['clipnuvex_seo_points'] ) ? (string) wp_unslash( $_POST['clipnuvex_seo_points'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$lines      = array();
	foreach ( preg_split( '/\r\n|\r|\n/', $raw_points ) as $line ) {
		$line = trim( wp_kses( $line, $allowed ) );
		if ( '' !== $line ) {
			$lines[] = $line;
		}
	}

	$hide = ! empty( $_POST['clipnuvex_seo_hide'] ) ? '1' : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

	$values = array(
		'clipnuvex_seo_title'  => $title,
		'clipnuvex_seo_intro'  => $intro,
		'clipnuvex_seo_points' => implode( "\n", $lines ),
		'clipnuvex_seo_hide'   => $hide,
	);
	foreach ( $values as $key => $value ) {
		if ( '' === trim( $value ) ) {
			delete_term_meta( $term_id, $key );
		} else {
			update_term_meta( $term_id, $key, $value );
		}
	}
}

/**
 * Guarda el bloque SEO al editar el término.
 *
 * @param int $term_id ID del término.
 */
function clipnuvex_save_term_seo( $term_id ) {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	// Centinela: el quick-edit y las creaciones programáticas (Modo Demo) no
	// envían estos campos; sin él, se borrarían los textos guardados.
	if ( ! isset( $_POST['clipnuvex_seo_title'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}
	check_admin_referer( 'update-tag_' . $term_id );
	clipnuvex_persist_term_seo( $term_id );
}
add_action( 'edited_categoria_video', 'clipnuvex_save_term_seo' );

/**
 * Guarda el bloque SEO al crear el término (nonce del formulario "Añadir").
 *
 * @param int $term_id ID del término.
 */
function clipnuvex_create_term_seo( $term_id ) {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	if ( ! isset( $_POST['clipnuvex_seo_title'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}
	check_admin_referer( 'add-tag', '_wpnonce_add-tag' );
	clipnuvex_persist_term_seo( $term_id );
}
add_action( 'created_categoria_video', 'clipnuvex_create_term_seo' );
