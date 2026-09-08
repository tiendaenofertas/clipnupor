# Clipnuvex — Theme de WordPress

Plataforma de vídeo premium agnóstica de nicho. Theme de producción: SEO, rápido,
responsive, accesible (AA), monetizable con ad slots IAB, i18n ES/EN y **Modo Demo**
de importación con 1 clic. Recrea el prototipo de diseño en PHP + The Loop, sin build
ni dependencias de plugins.

## Requisitos
- WordPress 6.2+ · PHP 7.4+ (probado en PHP 8.4).
- Extensión **GD** recomendada (el Modo Demo genera pósters de muestra). Opcional.
- Sin plugins obligatorios. Compatible (no requerido) con Polylang/WPML para multilenguaje.

## Instalación
1. Copia la carpeta `clipnuvex/` en `wp-content/themes/`.
2. **Apariencia → Temas → Activar** Clipnuvex. Al activar se registran el CPT y las
   taxonomías y se refrescan los permalinks automáticamente.
3. (Recomendado) **Apariencia → Modo Demo → Importar demo** para dejar el sitio
   idéntico al prototipo (12 categorías, ~36 vídeos, menús, página de categorías, páginas
   legales completas con DMCA rellenadas con los datos del sitio, y ad slots colocados), todo en el idioma del sitio (inglés
   por defecto; español si lo eliges en General → Idioma por defecto). Es **reversible**
   desde la misma pantalla, y reimportar revierte antes la demo anterior.

> Tras importar el demo, la portada muestra las últimas entradas (vídeos). Si usas una
> página estática como portada, asegúrate de que **Ajustes → Lectura** esté en
> "Tus últimas entradas" para ver la home de Novedades.

## Estructura de contenido
- **CPT `video`** — la imagen destacada es el **póster 2:3**. Campos (meta box "Detalles
  del vídeo"): `embed_url` (una o varias fuentes; varias activan pestañas de servidor,
  formato `Etiqueta|URL|Calidad` por línea), fuente, calidad (HD/4K), idioma, año,
  duración (opcional), destacado.
- **Taxonomía `categoria_video`** (`/categoria/…` con el sitio en español, `/categories/…` en
  inglés; sigue al ajuste General → Idioma por defecto y la base antigua redirige con 301) — admite
  imagen destacada del término (campo en el formulario de la categoría), usada en la página de
  categorías y el hero. Filtro `clipnuvex_category_base`.
- **Taxonomía `tag_video`** (`/tag/...`).
- Página "Todas las categorías": el theme la crea al activarse (slug `categorias` en español o
  `categories` en inglés, plantilla *Clipnuvex — Todas las categorías*) y la localiza por ID y por
  ambos slugs. Si la borras, los enlaces "Categorías" llevan al archivo `/video/` en lugar de a un
  404. Filtro `clipnuvex_categories_page_slug`. En inglés la base de la taxonomía y el slug de la
  página coinciden (`categories`): una página hija de `/categories/` no sería accesible.
- Pie de página, columna "Legal": asigna un menú a **Navegación del pie** o crea páginas con
  slug `privacidad` (o la página de Ajustes → Privacidad), `terminos`, `cookies` y `contacto`:
  solo se enlazan las que existan y, sin ninguna, la columna se oculta (filtros
  `clipnuvex_footer_legal_slugs` / `clipnuvex_footer_legal_links`).

## Personalización (panel Clipnuvex en el escritorio)
- **Marca y color**: acento (`#7C5CFF`) y acento oscuro (se inyectan como `--cnx-accent`).
- **Tipografía**: Clash Display + Satoshi (Fontshare; aloja los `.woff2` en `assets/fonts/`
  con un `fonts.css` para self-host y mejor CWV — si existe, se usa en lugar del CDN).
- **Idioma**: inglés por defecto; el español se elige en General → Idioma por defecto. Gobierna
  la interfaz, los textos SEO automáticos, la base de las URLs de categoría, la página
  "Categorías" y el idioma por defecto del schema. El selector ES/EN de la cabecera solo cambia
  la interfaz por cookie (no indexable): apágalo en un sitio de un solo idioma.
- **Reproductor**: lazy-load del iframe (carga el embed solo al pulsar play) y póster por defecto.
- **Anuncios**: un campo por posición IAB y vista. Pega ahí tu código de Google Ad Manager
  / AdSense. Si un slot está vacío, se muestra un placeholder con la medida (útil para ver
  el layout). Panel de resumen en **Apariencia → Clipnuvex**.

## SEO
Render en servidor por vista: `<title>`, meta description, canonical, Open Graph, Twitter
Card, `robots` (búsqueda = `noindex,follow`) y JSON-LD:
- Home → `WebSite` + `SearchAction` + `Organization`.
- Categoría/Tag → `CollectionPage` + `ItemList` + `BreadcrumbList`.
- Búsqueda → `SearchResultsPage` + `BreadcrumbList`.
- Vídeo → `VideoObject` + `WatchAction` + `BreadcrumbList`.

Sitemap de vídeo en `/clipnuvex-sitemap.xml` (extensión `video:video`) y referencia en
`robots.txt`. Imágenes con `width/height`, `loading=lazy`, `decoding=async`, `srcset`.

## Internacionalización
Text domain `clipnuvex`. `languages/clipnuvex.pot` + `en_US.po/.mo` (interfaz traducida) y
`es_ES.po/.mo` (origen español). Toggle ES/EN en la cabecera (cookie + filtro de locale).
Si instalas Polylang/WPML, el theme respeta su idioma y no fuerza nada.

## Arquitectura (resumen)
- `assets/css/` — `tokens` → `base` → `layout` → `components` → `views` (CSS propio, sin build).
- `assets/js/` — `app.js` (header, drawer, buscador en vivo vía REST, back-to-top, orden móvil)
  y `lazy-player.js` (carga diferida del iframe + pestañas de servidor).
- `inc/` — setup, enqueue, cpt, taxonomies, permalinks, meta, queries (+REST
  `clipnuvex/v1/search` y `/view`), template-tags, ads, options (panel), colors, seo, schema,
  sitemap, i18n, admin, demo.
- Plantillas en raíz (incluidas `page.php` y `single.php` para páginas y entradas genéricas, con la
  tipografía de lectura `.cnx-prose`) + `template-parts/` (card-poster, card-list, ad-slot, player, tax-archive).

## Notas de desarrollo
- Buscador en vivo: endpoint REST `clipnuvex/v1/search` (categorías + vídeos). Los endpoints
  del theme son públicos y **no usan nonce** a propósito: un nonce incrustado en una página
  cacheada caduca y WordPress devolvería 403 a todos los visitantes.
- "Populares" se ordena por la meta `_clipnuvex_views`, que se incrementa al ver un vídeo
  (REST `clipnuvex/v1/view`, con throttle por IP). Tras un proxy de confianza que ya fija
  `REMOTE_ADDR`, el filtro `clipnuvex_client_ip` permite ignorar las cabeceras `X-Forwarded-For`.
- Object cache: las cachés del grupo `clipnuvex` usan claves versionadas
  (`clipnuvex_cache_key()` / `clipnuvex_cache_bump()`), sin `wp_cache_flush_group()`.

## Migrar entradas a vídeos (sitios que vienen de otro theme)
**Clipnuvex → Migrar entradas** convierte las entradas normales en vídeos del theme en su sitio:
mismo ID, mismo slug y misma URL (con `/%postname%/`; con otras estructuras la URL antigua
redirige con 301), mismas fechas, autor, imagen destacada, metadatos (Yoast/Rank Math incluidos),
comentarios y revisiones. Las categorías y etiquetas se mapean a las del theme (se crean si faltan),
la fuente del vídeo se detecta en los campos personalizados que indiques y/o en el contenido
(iframe, bloque de embed, `[embed]`, shortcode, URL de YouTube/Vimeo/ok.ru…) y todo queda
registrado para poder **revertir** exactamente. Flujo recomendado: "Analizar" (no escribe nada),
"Migrar", revisar la vista "Sin fuente" de Vídeos y, si conviene, regenerar miniaturas
(`wp media regenerate --only-missing`). Para sitios grandes:

```bash
wp clipnuvex migrate-posts --source-meta=video_url --dry-run
wp clipnuvex migrate-posts --source-meta=video_url --batch=200 --yes
wp clipnuvex revert-migration --yes
```

Las entradas con slug ya usado por otro vídeo, página o entrada se omiten (nunca se renombran).
Con WPML o Polylang activos la herramienta se desactiva.
- Todo el contenido del demo se marca con la meta `_clipnuvex_demo` para revertir sin tocar
  contenido real.
