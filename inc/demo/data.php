<?php
/**
 * Dataset del Modo Demo (categorías, tags, vídeos, textos, menús y páginas
 * legales) en INGLÉS y ESPAÑOL.
 *
 * El importador corre en el escritorio, donde manda el idioma de WordPress y
 * no el del panel: por eso aquí NO se usa gettext para el contenido. Cada
 * función recibe el idioma del SITIO (clipnuvex_site_lang, panel → General →
 * Idioma por defecto; inglés por defecto) y devuelve el dataset en ese idioma.
 * Las claves de categoría son sus nombres en ese mismo idioma (el importador
 * las usa para term_exists y para emparejar los títulos).
 *
 * @package Clipnuvex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Idioma del dataset demo: el del sitio ('es' | 'en'), inglés por defecto.
 *
 * @param string|null $lang Forzar idioma (tests) o null = idioma del sitio.
 * @return string
 */
function clipnuvex_demo_lang( $lang = null ) {
	if ( null === $lang ) {
		$lang = function_exists( 'clipnuvex_site_lang' ) ? clipnuvex_site_lang() : 'en';
	}
	return ( 'es' === $lang ) ? 'es' : 'en';
}

/**
 * Categorías demo: nombre => [descripción, color base, color claro].
 *
 * @param string|null $lang Idioma.
 * @return array
 */
function clipnuvex_demo_categories( $lang = null ) {
	$colors = array(
		array( '#3a1216', '#5B3CE0' ),
		array( '#1a1c33', '#3b3f8c' ),
		array( '#2e2410', '#b8901f' ),
		array( '#1a0f14', '#5a1230' ),
		array( '#2a1230', '#8b2fb0' ),
		array( '#0c2030', '#1f7a8c' ),
		array( '#14241a', '#2e7d5b' ),
		array( '#3a1226', '#c23a6e' ),
		array( '#1c1f2e', '#4a5270' ),
		array( '#26321a', '#5e8c2e' ),
		array( '#241640', '#5a3ca6' ),
		array( '#1a1a1f', '#54545f' ),
	);
	if ( 'es' === clipnuvex_demo_lang( $lang ) ) {
		$names = array(
			'Acción'          => 'Adrenalina pura: persecuciones, explosiones y héroes al límite en HD y 4K.',
			'Drama'           => 'Historias intensas y personajes inolvidables que te tocan el corazón.',
			'Comedia'         => 'Risas garantizadas con lo mejor del humor y la comedia actual.',
			'Terror'          => 'Sustos, tensión y atmósferas que te helarán la sangre.',
			'Anime'           => 'Lo mejor de la animación japonesa, del shonen al slice of life.',
			'Ciencia Ficción' => 'Futuros imposibles, viajes espaciales y tecnología que asombra.',
			'Documentales'    => 'Realidad fascinante: naturaleza, ciencia, historia y más.',
			'Romance'         => 'Historias de amor que enamoran, del clásico al contemporáneo.',
			'Thriller'        => 'Suspense que no te deja respirar hasta el último minuto.',
			'Aventura'        => 'Expediciones épicas y mundos por descubrir.',
			'Fantasía'        => 'Magia, criaturas y reinos imaginarios de otra dimensión.',
			'Crimen'          => 'Intriga policial, mafias y misterios por resolver.',
		);
	} else {
		$names = array(
			'Action'        => 'Pure adrenaline: chases, explosions and heroes pushed to the limit in HD and 4K.',
			'Drama'         => 'Intense stories and unforgettable characters that touch your heart.',
			'Comedy'        => 'Guaranteed laughs with the best of today\'s humor and comedy.',
			'Horror'        => 'Scares, tension and atmospheres that will chill your blood.',
			'Anime'         => 'The best of Japanese animation, from shonen to slice of life.',
			'Sci-Fi'        => 'Impossible futures, space travel and technology that amazes.',
			'Documentaries' => 'Fascinating reality: nature, science, history and more.',
			'Romance'       => 'Love stories that make you fall in love, from classic to contemporary.',
			'Thriller'      => 'Suspense that won\'t let you breathe until the last minute.',
			'Adventure'     => 'Epic expeditions and worlds waiting to be discovered.',
			'Fantasy'       => 'Magic, creatures and imaginary kingdoms from another dimension.',
			'Crime'         => 'Police intrigue, mobs and mysteries to solve.',
		);
	}
	$out = array();
	$i   = 0;
	foreach ( $names as $name => $desc ) {
		$out[ $name ] = array(
			'desc' => $desc,
			'c1'   => $colors[ $i ][0],
			'c2'   => $colors[ $i ][1],
		);
		$i++;
	}
	return $out;
}

/**
 * Tags demo.
 *
 * @param string|null $lang Idioma.
 * @return array
 */
function clipnuvex_demo_tags( $lang = null ) {
	if ( 'es' === clipnuvex_demo_lang( $lang ) ) {
		return array( 'HD', '4K', 'Estreno', 'Subtitulado', 'Latino', 'Clásico', 'Saga', 'Trending', 'Sin cortes', 'Recomendado' );
	}
	return array( 'HD', '4K', 'New Release', 'Subtitled', 'Dubbed', 'Classic', 'Saga', 'Trending', 'Uncut', 'Recommended' );
}

/**
 * Títulos demo por categoría (3 cada una = 36 vídeos), con las mismas claves
 * que clipnuvex_demo_categories() en ese idioma.
 *
 * @param string|null $lang Idioma.
 * @return array nombre_categoria => [titulos].
 */
function clipnuvex_demo_titles( $lang = null ) {
	if ( 'es' === clipnuvex_demo_lang( $lang ) ) {
		return array(
			'Acción'          => array( 'Vértigo Final', 'Código Tormenta', 'El Último Disparo' ),
			'Drama'           => array( 'Cartas al Silencio', 'La Hora Gris', 'Raíces Rotas' ),
			'Comedia'         => array( 'Caos en la Oficina', 'Vecinos Imposibles', 'Plan B' ),
			'Terror'          => array( 'La Casa del Eco', 'Susurros en la Niebla', 'Habitación 13' ),
			'Anime'           => array( 'Espíritu de Acero', 'Sakura Eterna', 'Guardianes del Alba' ),
			'Ciencia Ficción' => array( 'Órbita Cero', 'Memoria Sintética', 'El Reino Cuántico' ),
			'Documentales'    => array( 'Planeta Oculto', 'Voces del Océano', 'Más Allá del Cosmos' ),
			'Romance'         => array( 'Bajo la Misma Luna', 'Cartas de Verano', 'Dos Trenes' ),
			'Thriller'        => array( 'Punto de Fuga', 'La Lista Negra', 'Contrarreloj' ),
			'Aventura'        => array( 'El Mapa Perdido', 'Cumbre Salvaje', 'Río Esmeralda' ),
			'Fantasía'        => array( 'La Corona de Cristal', 'El Bosque que Respira', 'Hija del Dragón' ),
			'Crimen'          => array( 'Sangre y Asfalto', 'El Soplón', 'Caso Abierto' ),
		);
	}
	return array(
		'Action'        => array( 'Final Vertigo', 'Storm Protocol', 'The Last Shot' ),
		'Drama'         => array( 'Letters to Silence', 'The Grey Hour', 'Broken Roots' ),
		'Comedy'        => array( 'Office Chaos', 'Impossible Neighbors', 'Plan B' ),
		'Horror'        => array( 'The Echo House', 'Whispers in the Fog', 'Room 13' ),
		'Anime'         => array( 'Steel Spirit', 'Eternal Sakura', 'Guardians of Dawn' ),
		'Sci-Fi'        => array( 'Orbit Zero', 'Synthetic Memory', 'The Quantum Realm' ),
		'Documentaries' => array( 'Hidden Planet', 'Voices of the Ocean', 'Beyond the Cosmos' ),
		'Romance'       => array( 'Under the Same Moon', 'Summer Letters', 'Two Trains' ),
		'Thriller'      => array( 'Vanishing Point', 'The Blacklist', 'Against the Clock' ),
		'Adventure'     => array( 'The Lost Map', 'Wild Summit', 'Emerald River' ),
		'Fantasy'       => array( 'The Crystal Crown', 'The Breathing Forest', 'Daughter of the Dragon' ),
		'Crime'         => array( 'Blood and Asphalt', 'The Informant', 'Open Case' ),
	);
}

/**
 * URLs de embed demo (vídeos libres de muestra).
 *
 * @return array
 */
function clipnuvex_demo_embeds() {
	return array(
		'https://www.youtube.com/embed/aqz-KE-bpKQ', // Big Buck Bunny.
		'https://www.youtube.com/embed/ScMzIvxBSi4', // Big Buck Bunny alt.
		'https://www.youtube.com/embed/eRsGyueVLvQ', // Sintel.
	);
}

/**
 * Textos del contenido demo: plantillas de vídeo, idiomas de los vídeos,
 * etiquetas de menú, titulares de los banners y textos de los anuncios.
 *
 * @param string|null $lang Idioma.
 * @return array
 */
function clipnuvex_demo_texts( $lang = null ) {
	if ( 'es' === clipnuvex_demo_lang( $lang ) ) {
		return array(
			/* 1: título, 2: categoría. */
			'video_content' => '%1$s es uno de los títulos destacados de %2$s en Clipnuvex. Disfrútalo en streaming con calidad de cine, reproductor propio y servidores alternativos. Nuevos vídeos cada semana, sin cortes.',
			/* 1: título, 2: calidad, 3: descripción de la categoría. */
			'video_excerpt' => 'Ver %1$s online en %2$s. %3$s',
			'video_langs'   => array( 'Latino', 'Subtitulado' ),
			'menu'          => array(
				'home'       => 'Inicio',
				'browse'     => 'Explorar',
				'categories' => 'Categorías',
			),
			'ad_alt'        => 'Anuncio de ejemplo',
			/* %s: formato. */
			'ad_title'      => 'Anuncio demo %s',
			'ad_headlines'  => array(
				'leaderboard' => 'Sonido de cine en tu salón.',
				'billboard'   => 'Estrena tu próxima historia en 4K.',
				'rectangle'   => 'Estilo que se nota.',
				'halfpage'    => 'Streaming sin límites ni cortes.',
				'skyscraper'  => 'Captura cada escena.',
				'mobile'      => 'Tu café, tu ritmo.',
			),
		);
	}
	return array(
		'video_content' => '%1$s is one of the featured %2$s titles on Clipnuvex. Stream it in cinema quality with our own player and alternative servers. New videos every week, no buffering.',
		'video_excerpt' => 'Watch %1$s online in %2$s. %3$s',
		'video_langs'   => array( 'English', 'Subtitled' ),
		'menu'          => array(
			'home'       => 'Home',
			'browse'     => 'Browse',
			'categories' => 'Categories',
		),
		'ad_alt'        => 'Sample ad',
		'ad_title'      => 'Demo ad %s',
		'ad_headlines'  => array(
			'leaderboard' => 'Cinema sound in your living room.',
			'billboard'   => 'Premiere your next story in 4K.',
			'rectangle'   => 'Style that shows.',
			'halfpage'    => 'Streaming without limits or buffering.',
			'skyscraper'  => 'Capture every scene.',
			'mobile'      => 'Your coffee, your pace.',
		),
	);
}

/**
 * Fecha "última actualización" en el idioma del dataset (sin depender del
 * locale del escritorio).
 *
 * @param string|null $lang Idioma.
 * @return string
 */
function clipnuvex_demo_date( $lang = null ) {
	$ts = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
	$d  = (int) gmdate( 'j', $ts );
	$m  = (int) gmdate( 'n', $ts );
	$y  = (int) gmdate( 'Y', $ts );
	if ( 'es' === clipnuvex_demo_lang( $lang ) ) {
		$months = array( 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre' );
		return $d . ' de ' . $months[ $m - 1 ] . ' de ' . $y;
	}
	$months = array( 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' );
	return $months[ $m - 1 ] . ' ' . $d . ', ' . $y;
}

/**
 * Páginas legales COMPLETAS que crea la demo (menú de pie): clave => [slug,
 * título, contenido]. Redactadas para un sitio de vídeo que incrusta contenido
 * de terceros y muestra publicidad. Los marcadores {{site_name}}, {{site_url}},
 * {{email}}, {{date}} y {{privacy_url}} {{terms_url}} {{cookies_url}}
 * {{contact_url}} {{dmca_url}} los rellena el importador con los datos reales
 * del sitio y los permalinks de las propias páginas. El pie del theme reconoce
 * estos slugs cuando no hay menú asignado (clipnuvex_footer_legal_links).
 *
 * @param string|null $lang Idioma.
 * @return array
 */
function clipnuvex_demo_pages( $lang = null ) {
	if ( 'es' === clipnuvex_demo_lang( $lang ) ) {
		return array(
			'privacy' => array(
				'slug'    => 'privacidad',
				'title'   => 'Política de privacidad',
				'content' => <<<'HTML'
<p><strong>Última actualización:</strong> {{date}}</p>
<p>Esta Política de privacidad explica cómo {{site_name}} («nosotros»), disponible en {{site_url}}, recoge, utiliza y protege la información cuando visitas el sitio. Al usar {{site_name}} aceptas las prácticas aquí descritas.</p>
<h2>1. Información que recogemos</h2>
<p><strong>Información que nos facilitas.</strong> Cuando nos contactas (por correo electrónico o mediante un formulario) recibimos la información que decides enviarnos, como tu nombre, tu dirección de correo y el contenido del mensaje.</p>
<p><strong>Información recogida automáticamente.</strong> Como la mayoría de los sitios web, nuestros servidores y proveedores registran datos técnicos al navegar: dirección IP, tipo y versión del navegador, sistema operativo, tipo de dispositivo, idioma, páginas de origen, páginas visitadas, fechas y horas de acceso y ubicación aproximada derivada de la IP. Parte de estos datos se recogen mediante cookies y tecnologías similares descritas en nuestra <a href="{{cookies_url}}">Política de cookies</a>.</p>
<h2>2. Para qué usamos la información</h2>
<ul><li>Para operar, mantener y mejorar el sitio y sus funciones.</li><li>Para medir la audiencia y entender cómo se usa el sitio.</li><li>Para mostrar publicidad, incluida publicidad personalizada cuando la ley y tus preferencias de consentimiento lo permitan.</li><li>Para responder a tus solicitudes y mensajes.</li><li>Para detectar, prevenir y resolver fraudes, abusos, incidentes de seguridad y problemas técnicos.</li><li>Para cumplir obligaciones legales y hacer valer nuestros <a href="{{terms_url}}">Términos de uso</a>.</li></ul>
<h2>3. Bases jurídicas (usuarios del EEE y Reino Unido)</h2>
<p>Cuando se aplica el Reglamento General de Protección de Datos, tratamos los datos personales sobre estas bases: nuestro interés legítimo en operar y proteger el sitio y medir su audiencia; tu consentimiento para las cookies no esenciales y la publicidad personalizada, que puedes retirar en cualquier momento; la ejecución de un contrato cuando nos solicitas un servicio; y el cumplimiento de obligaciones legales.</p>
<h2>4. Cookies y publicidad</h2>
<p>Utilizamos cookies y tecnologías similares para funciones esenciales, analítica y publicidad. La publicidad puede servirla redes de terceros como Google AdSense o Google Ad Manager, que pueden usar cookies e identificadores del dispositivo para mostrar anuncios basados en tus visitas a este y otros sitios. Puedes gestionar tus preferencias desde la configuración de anuncios de esos proveedores y desde tu navegador, como se explica en nuestra <a href="{{cookies_url}}">Política de cookies</a>.</p>
<h2>5. Vídeos incrustados y servicios de terceros</h2>
<p>Los vídeos que se muestran en {{site_name}} están incrustados desde plataformas de terceros (por ejemplo YouTube u otros alojamientos de vídeo). No alojamos los archivos de vídeo. Cuando un vídeo incrustado se carga o se reproduce, la plataforma que lo proporciona puede recoger datos sobre ti y establecer sus propias cookies conforme a su propia política de privacidad, sobre la que no tenemos control. Te recomendamos revisar las políticas de privacidad de esas plataformas.</p>
<h2>6. Con quién compartimos la información</h2>
<p>No vendemos tus datos personales. Solo compartimos información con proveedores que nos ayudan a operar el sitio (alojamiento, redes de distribución de contenido, analítica, publicidad y correo), con las autoridades cuando la ley lo exige o para proteger nuestros derechos, y en el marco de una fusión, adquisición o venta de activos, en cuyo caso esta política seguirá aplicándose a los datos transferidos.</p>
<h2>7. Conservación de los datos</h2>
<p>Conservamos los datos personales solo durante el tiempo necesario para los fines descritos. Los registros del servidor se conservan, en general, durante un periodo limitado por seguridad y diagnóstico; los mensajes que nos envías se conservan mientras atendemos tu solicitud y durante un tiempo razonable después.</p>
<h2>8. Tus derechos</h2>
<p>Según dónde residas, puedes tener derecho a acceder a tus datos personales, rectificarlos, suprimirlos u obtener una copia, a oponerte a su tratamiento o limitarlo y a retirar tu consentimiento. Los residentes en California y en otras jurisdicciones con leyes similares también pueden tener derecho a saber qué información recogemos y a oponerse a la venta o cesión de información personal, práctica que no realizamos. Para ejercer tus derechos escribe a <a href="mailto:{{email}}">{{email}}</a>. También tienes derecho a presentar una reclamación ante tu autoridad de protección de datos.</p>
<h2>9. Seguridad</h2>
<p>Aplicamos medidas técnicas y organizativas adecuadas al riesgo, como conexiones cifradas (HTTPS), controles de acceso y actualizaciones periódicas. Ningún sistema es completamente seguro, por lo que no podemos garantizar una seguridad absoluta.</p>
<h2>10. Menores</h2>
<p>{{site_name}} no se dirige a menores de 13 años (o de la edad mínima exigida en tu jurisdicción) y no recogemos a sabiendas datos personales de menores. Si crees que un menor nos ha facilitado datos personales, contáctanos y los eliminaremos.</p>
<h2>11. Transferencias internacionales</h2>
<p>Nuestros proveedores pueden tratar datos en países distintos del tuyo. Cuando ocurre, nos apoyamos en garantías adecuadas, como cláusulas contractuales tipo o decisiones de adecuación, cuando la ley aplicable lo exige.</p>
<h2>12. Cambios en esta política</h2>
<p>Podemos actualizar esta política de vez en cuando. La fecha del encabezado indica la última revisión. Los cambios relevantes se anunciarán en el sitio.</p>
<h2>13. Contacto</h2>
<p>Para cualquier pregunta sobre esta política o sobre tus datos personales, escríbenos a <a href="mailto:{{email}}">{{email}}</a> o a través de nuestra <a href="{{contact_url}}">página de contacto</a>.</p>
HTML
				,
			),
			'terms'   => array(
				'slug'    => 'terminos',
				'title'   => 'Términos de uso',
				'content' => <<<'HTML'
<p><strong>Última actualización:</strong> {{date}}</p>
<p>Estos Términos de uso («Términos») regulan el acceso y el uso de {{site_name}}, disponible en {{site_url}} (el «Sitio»). Al acceder al Sitio o utilizarlo aceptas quedar vinculado por estos Términos y por nuestra <a href="{{privacy_url}}">Política de privacidad</a>. Si no estás de acuerdo, no utilices el Sitio.</p>
<h2>1. El servicio</h2>
<p>{{site_name}} es un catálogo en línea que te permite descubrir y ver vídeos incrustados desde plataformas de terceros. No alojamos, almacenamos ni transmitimos los archivos de vídeo; cada vídeo lo sirve la plataforma que lo aloja y está sujeto a las condiciones y políticas de esa plataforma. Podemos añadir, modificar o retirar contenidos y funciones en cualquier momento sin previo aviso.</p>
<h2>2. Requisitos</h2>
<p>Debes tener al menos 13 años (o la edad mínima legal de tu país) para usar el Sitio. Si un contenido está señalado como apto solo para adultos, debes ser mayor de edad en tu jurisdicción para acceder a él.</p>
<h2>3. Uso permitido</h2>
<p>Puedes usar el Sitio con fines personales y no comerciales. Te comprometes a no:</p>
<ul><li>copiar, reproducir, distribuir, vender o explotar cualquier parte del Sitio o de su contenido sin autorización;</li><li>usar bots, rastreadores o medios automatizados para acceder al Sitio o extraer datos, más allá de lo que hacen los buscadores conforme a nuestras directivas de robots;</li><li>interferir con el funcionamiento o la seguridad del Sitio, sus servidores o redes, ni eludir ninguna restricción de acceso o protección técnica;</li><li>subir o transmitir código malicioso, ni usar el Sitio con fines ilícitos, fraudulentos o abusivos;</li><li>suplantar a cualquier persona o falsear tu vinculación con cualquier entidad.</li></ul>
<h2>4. Propiedad intelectual</h2>
<p>El diseño, los textos, los logotipos, las marcas y el software del Sitio pertenecen a {{site_name}} o a sus licenciantes y están protegidos por las leyes de propiedad intelectual. Los vídeos incrustados, los títulos, las imágenes y demás materiales de terceros pertenecen a sus respectivos titulares. Nada en estos Términos te concede derecho alguno sobre esos materiales más allá de visualizarlos a través del Sitio.</p>
<h2>5. Reclamaciones por derechos de autor</h2>
<p>Respetamos los derechos de los titulares de derechos de autor. Si crees que un contenido accesible a través del Sitio infringe tus derechos, sigue el procedimiento descrito en nuestra <a href="{{dmca_url}}">página DMCA</a>. Respondemos con rapidez a las notificaciones válidas y podemos retirar o bloquear el acceso al contenido denunciado.</p>
<h2>6. Enlaces y servicios de terceros</h2>
<p>El Sitio puede contener enlaces a sitios y servicios de terceros, o incrustar contenido de ellos, que no controlamos. No somos responsables de su contenido, disponibilidad, políticas o prácticas. Accedes a ellos bajo tu propia responsabilidad.</p>
<h2>7. Publicidad</h2>
<p>El Sitio se financia con publicidad. Los anuncios pueden proporcionarlos terceros y pueden personalizarse según tu configuración y consentimiento, como se describe en nuestra <a href="{{privacy_url}}">Política de privacidad</a> y en nuestra <a href="{{cookies_url}}">Política de cookies</a>. No somos responsables de los productos o servicios anunciados.</p>
<h2>8. Exclusión de garantías</h2>
<p>El Sitio y su contenido se ofrecen «tal cual» y «según disponibilidad», sin garantías de ningún tipo, expresas o implícitas, incluidas las de exactitud, disponibilidad, comerciabilidad, idoneidad para un fin concreto o no infracción. No garantizamos que el Sitio funcione sin interrupciones ni errores, ni que un vídeo siga disponible.</p>
<h2>9. Limitación de responsabilidad</h2>
<p>En la máxima medida permitida por la ley, {{site_name}} y sus operadores no serán responsables de daños indirectos, incidentales, especiales, consecuentes o punitivos, ni de pérdidas de datos, ingresos o beneficios, derivados del uso o de la imposibilidad de uso del Sitio o de cualquier contenido de terceros. Cuando la responsabilidad no pueda excluirse, quedará limitada en la máxima medida que permita la ley aplicable.</p>
<h2>10. Indemnización</h2>
<p>Te comprometes a indemnizar y mantener indemnes a {{site_name}} y a sus operadores frente a cualquier reclamación, daño o gasto (incluidos honorarios legales razonables) derivado del incumplimiento de estos Términos o del uso indebido del Sitio.</p>
<h2>11. Suspensión</h2>
<p>Podemos suspender o bloquear el acceso al Sitio, total o parcialmente, a cualquier usuario que incumpla estos Términos o la ley, sin previo aviso.</p>
<h2>12. Cambios en los Términos</h2>
<p>Podemos modificar estos Términos en cualquier momento. La versión actualizada entra en vigor al publicarse en el Sitio, con la fecha indicada en el encabezado. Seguir usando el Sitio tras un cambio supone aceptar los nuevos Términos.</p>
<h2>13. Ley aplicable</h2>
<p>Estos Términos se rigen por las leyes del país en el que está establecido el operador de {{site_name}}, sin perjuicio de las normas imperativas de protección de los consumidores de tu país de residencia. Cualquier controversia se someterá a los tribunales competentes conforme a esas leyes.</p>
<h2>14. Contacto</h2>
<p>Preguntas sobre estos Términos: <a href="mailto:{{email}}">{{email}}</a> o nuestra <a href="{{contact_url}}">página de contacto</a>.</p>
HTML
				,
			),
			'cookies' => array(
				'slug'    => 'cookies',
				'title'   => 'Política de cookies',
				'content' => <<<'HTML'
<p><strong>Última actualización:</strong> {{date}}</p>
<p>Esta Política de cookies explica qué son las cookies, cuáles utiliza {{site_name}} ({{site_url}}) y cómo puedes gestionarlas. Complementa nuestra <a href="{{privacy_url}}">Política de privacidad</a>.</p>
<h2>1. Qué son las cookies</h2>
<p>Las cookies son pequeños archivos de texto que un sitio web guarda en tu navegador cuando lo visitas. Permiten que el sitio recuerde tus acciones y preferencias (como el idioma o la sesión) y reconozca tu dispositivo en visitas posteriores. Esta política cubre también tecnologías similares, como el almacenamiento local, los píxeles y los identificadores de dispositivo.</p>
<h2>2. Cookies que utilizamos</h2>
<p><strong>Cookies esenciales.</strong> Necesarias para que el Sitio funcione: recuerdan tu idioma, mantienen tu sesión y protegen frente a abusos. No pueden desactivarse desde nuestra configuración, aunque puedes bloquearlas en tu navegador a costa de algunas funciones.</p>
<p><strong>Cookies de preferencias.</strong> Guardan elecciones como el idioma de la interfaz, el servidor de vídeo seleccionado o si has cerrado un aviso.</p>
<p><strong>Cookies analíticas.</strong> Nos ayudan a entender cómo se usa el Sitio (páginas vistas, tiempo de permanencia, errores) para mejorarlo. Los datos se agregan y no te identifican directamente.</p>
<p><strong>Cookies publicitarias.</strong> Las establecen nuestros socios publicitarios (por ejemplo Google AdSense/Ad Manager y sus proveedores certificados) para mostrarte anuncios relevantes, limitar cuántas veces ves un anuncio y medir el rendimiento de las campañas. Pueden rastrear tu navegación entre sitios.</p>
<p><strong>Cookies de contenido de terceros.</strong> Los reproductores de vídeo incrustados (por ejemplo YouTube) pueden establecer sus propias cookies cuando un vídeo se carga o se reproduce, conforme a las políticas de esas plataformas.</p>
<h2>3. Consentimiento</h2>
<p>Cuando la ley lo exige, las cookies no esenciales solo se utilizan después de que des tu consentimiento, que puedes retirar en cualquier momento. Google y sus socios pueden mostrar anuncios no personalizados a los usuarios que no hayan consentido la publicidad personalizada.</p>
<h2>4. Cómo gestionar las cookies</h2>
<ul><li><strong>Configuración del navegador:</strong> todos los navegadores principales permiten ver, bloquear y borrar cookies. Consulta las páginas de ayuda de Chrome, Firefox, Safari o Edge.</li><li><strong>Configuración de anuncios de Google:</strong> gestiona la publicidad personalizada en <a href="https://adssettings.google.com" rel="nofollow noopener" target="_blank">adssettings.google.com</a>.</li><li><strong>Exclusiones sectoriales:</strong> <a href="https://www.youronlinechoices.com" rel="nofollow noopener" target="_blank">youronlinechoices.com</a> (UE) y <a href="https://optout.aboutads.info" rel="nofollow noopener" target="_blank">optout.aboutads.info</a> (EE. UU.).</li></ul>
<p>Bloquear las cookies esenciales puede impedir que partes del Sitio funcionen correctamente.</p>
<h2>5. Cambios</h2>
<p>Podemos actualizar esta política para reflejar cambios en las cookies que utilizamos o en la ley. La fecha del encabezado indica la última revisión.</p>
<h2>6. Contacto</h2>
<p>Preguntas sobre cookies: <a href="mailto:{{email}}">{{email}}</a>.</p>
HTML
				,
			),
			'contact' => array(
				'slug'    => 'contacto',
				'title'   => 'Contacto',
				'content' => <<<'HTML'
<p>Nos encantará saber de ti. Ya sea una pregunta sobre {{site_name}}, una sugerencia, un problema técnico con un vídeo o una propuesta comercial, puedes escribirnos por los canales que aparecen a continuación.</p>
<h2>Correo electrónico</h2>
<p>Escribe a <a href="mailto:{{email}}">{{email}}</a>. Normalmente respondemos en un plazo de 48 horas en días laborables.</p>
<h2>Antes de escribir</h2>
<ul><li><strong>Un vídeo no se reproduce:</strong> indícanos el título o la URL del vídeo, la pestaña de servidor que probaste, tu navegador y tu dispositivo. Recuerda que los vídeos están incrustados desde plataformas de terceros y algunos pueden no estar disponibles temporalmente.</li><li><strong>Publicidad y colaboraciones:</strong> incluye tu empresa, el tipo de colaboración y la audiencia a la que te diriges.</li><li><strong>Derechos de autor:</strong> por favor, no utilices esta página. Sigue el procedimiento de nuestra <a href="{{dmca_url}}">página DMCA</a> para que podamos tramitar tu solicitud sin demora.</li><li><strong>Solicitudes de privacidad:</strong> para acceder a tus datos personales, rectificarlos o suprimirlos, consulta nuestra <a href="{{privacy_url}}">Política de privacidad</a> y escribe a la misma dirección.</li></ul>
<h2>Aviso</h2>
<p>{{site_name}} no aloja archivos de vídeo. No podemos facilitar descargas, subtítulos ni copias de ningún contenido.</p>
HTML
				,
			),
			'dmca'    => array(
				'slug'    => 'dmca',
				'title'   => 'DMCA',
				'content' => <<<'HTML'
<p><strong>Última actualización:</strong> {{date}}</p>
<p>{{site_name}} ({{site_url}}) respeta los derechos de propiedad intelectual de terceros y espera que sus usuarios hagan lo mismo. Esta página describe nuestra política y nuestros procedimientos conforme a la Digital Millennium Copyright Act (DMCA) y a las leyes equivalentes para tramitar reclamaciones por infracción de derechos de autor.</p>
<h2>1. Sobre el contenido de este sitio</h2>
<p>{{site_name}} no aloja, almacena ni sube ningún archivo de vídeo. Todos los vídeos están incrustados desde plataformas de terceros y se sirven desde sus servidores. Actuamos como un índice de contenido públicamente disponible. Retirar un vídeo incrustado de {{site_name}} no elimina el archivo original de la plataforma que lo aloja; para retirar la fuente, contacta también con esa plataforma.</p>
<h2>2. Cómo presentar una notificación de retirada</h2>
<p>Si eres titular de derechos de autor, o estás autorizado a actuar en nombre de uno, y crees que un contenido accesible a través de {{site_name}} infringe tus derechos, envía una notificación por escrito a nuestro agente designado en <a href="mailto:{{email}}">{{email}}</a> con el asunto «DMCA Notice». Para ser válida conforme a 17 U.S.C. § 512(c)(3), la notificación debe incluir:</p>
<ol><li>La firma física o electrónica del titular de los derechos de autor o de una persona autorizada a actuar en su nombre.</li><li>La identificación de la obra protegida que se considera infringida (o una lista representativa si la notificación cubre varias obras).</li><li>La identificación del material que se considera infractor y la información razonablemente suficiente para localizarlo: la URL o URLs exactas en {{site_name}}.</li><li>Tus datos de contacto: nombre, dirección, número de teléfono y dirección de correo electrónico.</li><li>Una declaración de que crees de buena fe que el uso del material no está autorizado por el titular de los derechos, su agente o la ley.</li><li>Una declaración, bajo pena de perjurio, de que la información de la notificación es exacta y de que eres el titular de los derechos o estás autorizado a actuar en su nombre.</li></ol>
<p>Las notificaciones incompletas pueden no tramitarse. Declarar a sabiendas que un material es infractor sin serlo puede acarrear responsabilidad por daños conforme a la Sección 512(f).</p>
<h2>3. Qué hacemos con una notificación válida</h2>
<p>Revisamos las notificaciones válidas con rapidez, en general en un plazo de 2 días laborables, y retiramos o bloqueamos el acceso al material denunciado. Podemos remitir la notificación, incluidos tus datos de contacto, a la persona responsable del contenido.</p>
<h2>4. Contranotificación</h2>
<p>Si crees que un material que aportaste se retiró por error o por identificación errónea, puedes enviar una contranotificación a <a href="mailto:{{email}}">{{email}}</a> que incluya: tu firma física o electrónica; la identificación del material retirado y su ubicación antes de la retirada; una declaración bajo pena de perjurio de que crees de buena fe que el material se retiró por error o identificación errónea; tu nombre, dirección y número de teléfono; y una declaración de que aceptas la jurisdicción del tribunal federal de tu distrito (o, si estás fuera de Estados Unidos, de cualquier distrito judicial en el que podamos ser localizados) y de que aceptarás la notificación de la demanda por parte de quien presentó la notificación original. Al recibir una contranotificación válida podremos restablecer el material en un plazo de 10 a 14 días laborables, salvo que el reclamante original nos informe de que ha presentado una acción judicial.</p>
<h2>5. Infractores reincidentes</h2>
<p>Podemos bloquear el acceso a usuarios o fuentes que sean objeto de notificaciones de infracción válidas reiteradas.</p>
<h2>6. Contacto</h2>
<p>Agente designado para notificaciones de derechos de autor: <a href="mailto:{{email}}">{{email}}</a>. Para cualquier otro asunto, utiliza nuestra <a href="{{contact_url}}">página de contacto</a>.</p>
HTML
				,
			),
		);
	}
	return array(
		'privacy' => array(
			// 'privacy' y no 'privacy-policy': ese slug lo ocupa el borrador de
			// privacidad que WordPress crea en toda instalación nueva.
			'slug'    => 'privacy',
			'title'   => 'Privacy Policy',
			'content' => <<<'HTML'
<p><strong>Last updated:</strong> {{date}}</p>
<p>This Privacy Policy explains how {{site_name}} ("we", "us" or "our"), available at {{site_url}}, collects, uses and protects information when you visit the site. By using {{site_name}} you agree to the practices described here.</p>
<h2>1. Information we collect</h2>
<p><strong>Information you provide.</strong> When you contact us (for example by email or through a form) we receive the information you choose to send, such as your name, email address and the content of your message.</p>
<p><strong>Information collected automatically.</strong> Like most websites, our servers and service providers record technical data when you browse: IP address, browser type and version, operating system, device type, language, referring pages, pages visited, dates and times of access and approximate location derived from the IP address. Part of this data is collected through cookies and similar technologies described in our <a href="{{cookies_url}}">Cookie Policy</a>.</p>
<h2>2. How we use the information</h2>
<ul><li>To operate, maintain and improve the site and its features.</li><li>To measure audience and understand how the site is used.</li><li>To show advertising, including personalized advertising where permitted by law and by your consent choices.</li><li>To answer your requests and messages.</li><li>To detect, prevent and address fraud, abuse, security incidents and technical problems.</li><li>To comply with legal obligations and enforce our <a href="{{terms_url}}">Terms of Service</a>.</li></ul>
<h2>3. Legal bases (EEA and UK users)</h2>
<p>Where the General Data Protection Regulation applies, we process personal data on the following bases: our legitimate interest in operating and securing the site and measuring its audience; your consent for non-essential cookies and personalized advertising, which you can withdraw at any time; the performance of a contract when you request a service from us; and compliance with legal obligations.</p>
<h2>4. Cookies and advertising</h2>
<p>We use cookies and similar technologies for essential functions, analytics and advertising. Advertising may be served by third-party networks such as Google AdSense or Google Ad Manager, which may use cookies and device identifiers to show ads based on your visits to this and other websites. You can manage your preferences from the ad settings of those providers and from your browser, as explained in our <a href="{{cookies_url}}">Cookie Policy</a>.</p>
<h2>5. Embedded videos and third-party services</h2>
<p>Videos shown on {{site_name}} are embedded from third-party platforms (for example YouTube or other video hosts). We do not host the video files. When an embedded video loads or plays, the platform that provides it may collect data about you and set its own cookies under its own privacy policy, over which we have no control. We recommend reviewing the privacy policies of those platforms.</p>
<h2>6. Sharing of information</h2>
<p>We do not sell your personal data. We share information only with service providers who help us operate the site (hosting, content delivery networks, analytics, advertising and email), with authorities when required by law or to protect our rights, and in connection with a merger, acquisition or sale of assets, in which case this policy will continue to apply to the transferred data.</p>
<h2>7. Data retention</h2>
<p>We keep personal data only for as long as necessary for the purposes described above. Server logs are generally retained for a limited period for security and diagnostics; messages you send us are kept while we handle your request and for a reasonable time afterwards.</p>
<h2>8. Your rights</h2>
<p>Depending on where you live, you may have the right to access, correct, delete or receive a copy of your personal data, to object to or restrict its processing, and to withdraw consent. Residents of California and other jurisdictions with similar laws may also have the right to know what information we collect and to opt out of the sale or sharing of personal information, which we do not practice. To exercise your rights, write to <a href="mailto:{{email}}">{{email}}</a>. You also have the right to lodge a complaint with your local data protection authority.</p>
<h2>9. Security</h2>
<p>We apply technical and organizational measures appropriate to the risk, such as encrypted connections (HTTPS), access controls and regular updates. No system is completely secure, so we cannot guarantee absolute security.</p>
<h2>10. Children</h2>
<p>{{site_name}} is not directed at children under 13 (or the minimum age required in your jurisdiction) and we do not knowingly collect personal data from them. If you believe a child has provided us with personal data, contact us and we will delete it.</p>
<h2>11. International transfers</h2>
<p>Our providers may process data in countries other than yours. When that happens we rely on appropriate safeguards, such as standard contractual clauses or adequacy decisions, where required by applicable law.</p>
<h2>12. Changes to this policy</h2>
<p>We may update this policy from time to time. The date at the top indicates the latest revision. Significant changes will be announced on the site.</p>
<h2>13. Contact</h2>
<p>For any question about this policy or your personal data, contact us at <a href="mailto:{{email}}">{{email}}</a> or through our <a href="{{contact_url}}">contact page</a>.</p>
HTML
			,
		),
		'terms'   => array(
			'slug'    => 'terms-of-service',
			'title'   => 'Terms of Service',
			'content' => <<<'HTML'
<p><strong>Last updated:</strong> {{date}}</p>
<p>These Terms of Service ("Terms") govern your access to and use of {{site_name}}, available at {{site_url}} (the "Site"). By accessing or using the Site you agree to be bound by these Terms and by our <a href="{{privacy_url}}">Privacy Policy</a>. If you do not agree, do not use the Site.</p>
<h2>1. The service</h2>
<p>{{site_name}} is an online catalog that lets you discover and watch videos embedded from third-party platforms. We do not host, store or transmit the video files; each video is served by the platform that hosts it and is subject to that platform's terms and policies. We may add, modify or remove content and features at any time without notice.</p>
<h2>2. Eligibility</h2>
<p>You must be at least 13 years old (or the minimum legal age in your country) to use the Site. If content is marked as suitable for adults only, you must be of legal age in your jurisdiction to access it.</p>
<h2>3. Permitted use</h2>
<p>You may use the Site for personal, non-commercial purposes. You agree not to:</p>
<ul><li>copy, reproduce, distribute, sell or exploit any part of the Site or its content without authorization;</li><li>use bots, scrapers or automated means to access the Site or extract data, beyond what search engines do under our robots directives;</li><li>interfere with the operation or security of the Site, its servers or networks, or circumvent any access restriction or technical protection;</li><li>upload or transmit malicious code, or use the Site for unlawful, fraudulent or abusive purposes;</li><li>impersonate any person or misrepresent your affiliation with any entity.</li></ul>
<h2>4. Intellectual property</h2>
<p>The Site's design, texts, logos, trademarks and software belong to {{site_name}} or its licensors and are protected by intellectual property laws. Embedded videos, titles, artwork and other third-party materials belong to their respective owners. Nothing in these Terms grants you any right over such materials beyond viewing them through the Site.</p>
<h2>5. Copyright complaints</h2>
<p>We respect the rights of copyright holders. If you believe that content accessible through the Site infringes your rights, follow the procedure described on our <a href="{{dmca_url}}">DMCA page</a>. We respond promptly to valid notices and may remove or disable access to the reported content.</p>
<h2>6. Third-party links and services</h2>
<p>The Site may contain links to, or embed content from, third-party websites and services that we do not control. We are not responsible for their content, availability, policies or practices. Accessing them is at your own risk.</p>
<h2>7. Advertising</h2>
<p>The Site is supported by advertising. Ads may be provided by third parties and may be personalized according to your settings and consent, as described in our <a href="{{privacy_url}}">Privacy Policy</a> and <a href="{{cookies_url}}">Cookie Policy</a>. We are not responsible for the products or services advertised.</p>
<h2>8. Disclaimer of warranties</h2>
<p>The Site and its content are provided "as is" and "as available", without warranties of any kind, express or implied, including warranties of accuracy, availability, merchantability, fitness for a particular purpose or non-infringement. We do not guarantee that the Site will be uninterrupted or error-free, or that any video will remain available.</p>
<h2>9. Limitation of liability</h2>
<p>To the fullest extent permitted by law, {{site_name}} and its operators shall not be liable for any indirect, incidental, special, consequential or punitive damages, or for any loss of data, revenue or profits, arising from your use of or inability to use the Site or any third-party content. Where liability cannot be excluded, it is limited to the maximum extent permitted by applicable law.</p>
<h2>10. Indemnification</h2>
<p>You agree to indemnify and hold harmless {{site_name}} and its operators from any claim, damage or expense (including reasonable legal fees) arising from your breach of these Terms or your misuse of the Site.</p>
<h2>11. Termination</h2>
<p>We may suspend or block access to the Site, in whole or in part, for any user who violates these Terms or the law, without prior notice.</p>
<h2>12. Changes to the Terms</h2>
<p>We may modify these Terms at any time. The updated version takes effect when published on the Site, with the date shown at the top. Continued use of the Site after changes constitutes acceptance of the new Terms.</p>
<h2>13. Governing law</h2>
<p>These Terms are governed by the laws of the country where the operator of {{site_name}} is established, without prejudice to the mandatory consumer protection rules of your country of residence. Any dispute shall be submitted to the competent courts under those laws.</p>
<h2>14. Contact</h2>
<p>Questions about these Terms: <a href="mailto:{{email}}">{{email}}</a> or our <a href="{{contact_url}}">contact page</a>.</p>
HTML
			,
		),
		'cookies' => array(
			'slug'    => 'cookie-policy',
			'title'   => 'Cookie Policy',
			'content' => <<<'HTML'
<p><strong>Last updated:</strong> {{date}}</p>
<p>This Cookie Policy explains what cookies are, which ones {{site_name}} ({{site_url}}) uses and how you can manage them. It complements our <a href="{{privacy_url}}">Privacy Policy</a>.</p>
<h2>1. What cookies are</h2>
<p>Cookies are small text files that a website stores in your browser when you visit it. They allow the site to remember your actions and preferences (such as language or session) and to recognize your device on later visits. Similar technologies, such as local storage, pixels and device identifiers, are covered by this policy as well.</p>
<h2>2. Cookies we use</h2>
<p><strong>Essential cookies.</strong> Required for the Site to work: they remember your language, keep your session and protect against abuse. They cannot be disabled from our settings, though you can block them in your browser at the cost of some features.</p>
<p><strong>Preference cookies.</strong> Store choices such as the interface language, the selected video server or whether you dismissed a notice.</p>
<p><strong>Analytics cookies.</strong> Help us understand how visitors use the Site (pages viewed, time spent, errors) so we can improve it. Data is aggregated and does not directly identify you.</p>
<p><strong>Advertising cookies.</strong> Set by our advertising partners (for example Google AdSense/Ad Manager and their certified vendors) to show ads relevant to you, limit how often you see an ad and measure campaign performance. They may track your browsing across websites.</p>
<p><strong>Third-party content cookies.</strong> Embedded video players (for example YouTube) may set their own cookies when a video loads or plays, under the policies of those platforms.</p>
<h2>3. Consent</h2>
<p>Where the law requires it, non-essential cookies are only used after you give consent, which you can withdraw at any time. Google and its partners may show non-personalized ads to users who have not consented to personalized advertising.</p>
<h2>4. How to manage cookies</h2>
<ul><li><strong>Browser settings:</strong> all major browsers let you view, block and delete cookies. See the help pages of Chrome, Firefox, Safari or Edge.</li><li><strong>Google ad settings:</strong> manage personalized advertising at <a href="https://adssettings.google.com" rel="nofollow noopener" target="_blank">adssettings.google.com</a>.</li><li><strong>Industry opt-outs:</strong> <a href="https://www.youronlinechoices.com" rel="nofollow noopener" target="_blank">youronlinechoices.com</a> (EU) and <a href="https://optout.aboutads.info" rel="nofollow noopener" target="_blank">optout.aboutads.info</a> (US).</li></ul>
<p>Blocking essential cookies may prevent parts of the Site from working correctly.</p>
<h2>5. Changes</h2>
<p>We may update this policy to reflect changes in the cookies we use or in the law. The date at the top indicates the latest revision.</p>
<h2>6. Contact</h2>
<p>Questions about cookies: <a href="mailto:{{email}}">{{email}}</a>.</p>
HTML
			,
		),
		'contact' => array(
			'slug'    => 'contact',
			'title'   => 'Contact',
			'content' => <<<'HTML'
<p>We would love to hear from you. Whether you have a question about {{site_name}}, a suggestion, a technical problem with a video or a business proposal, you can reach us through the channels below.</p>
<h2>Email</h2>
<p>Write to <a href="mailto:{{email}}">{{email}}</a>. We usually reply within 48 hours on business days.</p>
<h2>Before you write</h2>
<ul><li><strong>A video does not play:</strong> tell us the video title or URL, the server tab you tried, your browser and your device. Remember that videos are embedded from third-party platforms and some may be temporarily unavailable.</li><li><strong>Advertising and partnerships:</strong> include your company, the type of collaboration and the audience you are targeting.</li><li><strong>Copyright:</strong> please do not use this page. Follow the procedure on our <a href="{{dmca_url}}">DMCA page</a> so we can process your request without delay.</li><li><strong>Privacy requests:</strong> to access, correct or delete your personal data, see our <a href="{{privacy_url}}">Privacy Policy</a> and write to the same address.</li></ul>
<h2>Notice</h2>
<p>{{site_name}} does not host video files. We cannot provide downloads, subtitles or copies of any content.</p>
HTML
			,
		),
		'dmca'    => array(
			'slug'    => 'dmca',
			'title'   => 'DMCA',
			'content' => <<<'HTML'
<p><strong>Last updated:</strong> {{date}}</p>
<p>{{site_name}} ({{site_url}}) respects the intellectual property rights of others and expects its users to do the same. This page describes our policy and procedures under the Digital Millennium Copyright Act (DMCA) and equivalent laws for handling claims of copyright infringement.</p>
<h2>1. About the content on this site</h2>
<p>{{site_name}} does not host, store or upload any video file. All videos are embedded from third-party platforms and are served from their servers. We act as an index of publicly available content. Removing an embed from {{site_name}} does not remove the original file from the platform that hosts it; to remove the source, please also contact that platform.</p>
<h2>2. Filing a takedown notice</h2>
<p>If you are a copyright owner, or authorized to act on behalf of one, and believe that content accessible through {{site_name}} infringes your copyright, send a written notice to our designated agent at <a href="mailto:{{email}}">{{email}}</a> with the subject "DMCA Notice". To be valid under 17 U.S.C. § 512(c)(3), the notice must include:</p>
<ol><li>A physical or electronic signature of the copyright owner or of a person authorized to act on their behalf.</li><li>Identification of the copyrighted work claimed to have been infringed (or a representative list if the notice covers multiple works).</li><li>Identification of the material claimed to be infringing and information reasonably sufficient to locate it: the exact URL or URLs on {{site_name}}.</li><li>Your contact information: name, address, telephone number and email address.</li><li>A statement that you have a good-faith belief that the use of the material is not authorized by the copyright owner, its agent or the law.</li><li>A statement, under penalty of perjury, that the information in the notice is accurate and that you are the copyright owner or are authorized to act on the owner's behalf.</li></ol>
<p>Incomplete notices may not be processed. Knowingly misrepresenting that material is infringing may expose you to liability for damages under Section 512(f).</p>
<h2>3. What we do with a valid notice</h2>
<p>We review valid notices promptly, generally within 2 business days, and remove or disable access to the reported material. We may forward the notice, including your contact details, to the person responsible for the content.</p>
<h2>4. Counter-notification</h2>
<p>If you believe that material you provided was removed by mistake or misidentification, you may send a counter-notification to <a href="mailto:{{email}}">{{email}}</a> including: your physical or electronic signature; identification of the removed material and its location before removal; a statement under penalty of perjury that you have a good-faith belief the material was removed as a result of mistake or misidentification; your name, address and telephone number; and a statement that you consent to the jurisdiction of the federal court for your district (or, if you are outside the United States, of any judicial district in which we may be found) and that you will accept service of process from the person who filed the original notice. Upon receiving a valid counter-notification we may restore the material within 10 to 14 business days unless the original claimant informs us that they have filed a court action.</p>
<h2>5. Repeat infringers</h2>
<p>We may block access for users or sources that are the subject of repeated valid infringement notices.</p>
<h2>6. Contact</h2>
<p>Designated agent for copyright notices: <a href="mailto:{{email}}">{{email}}</a>. For any other matter, use our <a href="{{contact_url}}">contact page</a>.</p>
HTML
			,
		),
	);
}
