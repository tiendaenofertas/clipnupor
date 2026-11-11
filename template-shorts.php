<?php
/**
 * Template Name: Página de Shorts
 */

/* RENDERIZADOR NATIVO DE VIDEOS CORTOS */
function vtt_render_short_video_html( $is_lazy = true ) {
    $post_id = get_the_ID();
    $video_url = get_post_meta($post_id, 'video_url', true);
    $embed_code = get_post_meta($post_id, 'embed', true) ?: get_post_meta($post_id, 'video_embed_code_1', true);
    
    if (strpos($video_url, '<iframe') !== false) {
        $embed_code = $video_url;
        $video_url = '';
    }

    if ($is_lazy && !empty($embed_code)) {
        $embed_code = str_replace(' src=', ' data-src=', $embed_code);
    }

    $likes = get_post_meta($post_id, 'likes_count', true) ?: 0;
    $comments_count = get_comments_number();
    ?>
    
    <div class="short-video-wrapper" id="short-<?php echo $post_id; ?>">
        <?php if (!empty($video_url) && empty($embed_code)) : ?>
            <?php if ($is_lazy) : ?>
                <video data-src="<?php echo esc_url($video_url); ?>" playsinline muted preload="none"></video>
            <?php else : ?>
                <video src="<?php echo esc_url($video_url); ?>" playsinline muted preload="auto"></video>
            <?php endif; ?>
            <div class="sound-indicator" style="display:none;">
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 5px;"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><line x1="23" y1="9" x2="17" y2="15"></line><line x1="17" y1="9" x2="23" y2="15"></line></svg>
                Toca para escuchar
            </div>
        <?php elseif (!empty($embed_code)) : ?>
            <div class="iframe-container">
                <?php echo $embed_code; ?>
            </div>
        <?php else : ?>
            <p style="color:#fff;">No hay vídeo disponible.</p>
        <?php endif; ?>

        <div class="short-sidebar">
            <button class="short-action-btn btn-like" data-post-id="<?php echo $post_id; ?>">
                <svg viewBox="0 0 24 24" width="34" height="34" class="icon-svg icon-heart" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                <span class="like-counter"><?php echo $likes; ?></span>
            </button>
            
            <a href="<?php comments_link(); ?>" class="short-action-btn btn-comment">
                <svg viewBox="0 0 24 24" width="34" height="34" class="icon-svg icon-comment" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                <span><?php echo $comments_count; ?></span>
            </a>
        </div>

        <div class="short-info">
            <h4><?php the_title(); ?></h4>
        </div>
    </div>
    <?php
}

/* CARGA AJAX CON PROTECCIÓN CONTRA CACHÉ */
if (isset($_POST['ajax_shorts']) && $_POST['ajax_shorts'] == '1') {
    $paged = isset($_POST['paged']) ? intval($_POST['paged']) : 1;
    $shorts = new WP_Query(array(
        'post_type'           => array('video', 'post'),
        'posts_per_page'      => 15,
        'paged'               => $paged,
        'post_status'         => 'publish',
        'ignore_sticky_posts' => 1,
        'meta_query'          => array(array('key' => 'is_short', 'value' => 'on', 'compare' => '='))
    ));
    if ($shorts->have_posts()) {
        while ($shorts->have_posts()) {
            $shorts->the_post();
            vtt_render_short_video_html(true);
        }
    }
    wp_reset_postdata();
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <title><?php wp_title(); ?></title>
    <?php wp_head(); ?>
    <style>
        body > *:not(.shorts-viewer-container):not(.back-home-btn):not(script):not(style):not(link):not(meta) {
            display: none !important; opacity: 0 !important; visibility: hidden !important; height: 0 !important;
        }
        html, body {
            margin: 0 !important; padding: 0 !important; background-color: #000 !important;
            height: 100dvh !important; width: 100vw !important; overflow: hidden !important;
            overscroll-behavior: none; font-family: system-ui, -apple-system, sans-serif;
        }
        #wpadminbar { display: none !important; }

        .shorts-viewer-container {
            height: 100dvh; width: 100%; max-width: 500px; margin: 0 auto;
            overflow-y: scroll; scroll-snap-type: y mandatory; scroll-behavior: smooth;
            position: relative; background-color: #000; -ms-overflow-style: none; scrollbar-width: none;
            transform: translateZ(0);
        }
        .shorts-viewer-container::-webkit-scrollbar { display: none; }

        .back-home-btn {
            position: fixed; top: 20px; left: 20px; background: rgba(0, 0, 0, 0.45); color: #fff;
            width: 44px; height: 44px; display: flex; justify-content: center; align-items: center;
            border-radius: 50%; z-index: 9999; text-decoration: none; backdrop-filter: blur(5px);
            transition: transform 0.2s ease;
        }
        .back-home-btn:hover { background: #ff2a5f; transform: scale(1.1); color: #fff; }

        .short-video-wrapper {
            height: 100dvh; width: 100%; scroll-snap-align: start; position: relative;
            display: flex; justify-content: center; align-items: center; background-color: #111;
        }
        .short-video-wrapper video { width: 100%; height: 100%; object-fit: cover; cursor: pointer; will-change: transform; }
        .iframe-container { width: 100%; height: 100%; display: flex; justify-content: center; align-items: center; background: #000; }
        .iframe-container iframe { width: 100%; height: 100%; border: none; }

        .short-sidebar {
            position: absolute; right: 12px; bottom: 100px; display: flex;
            flex-direction: column; align-items: center; gap: 20px; z-index: 10;
        }
        .short-action-btn {
            background: transparent; border: none; color: white; cursor: pointer;
            display: flex; flex-direction: column; align-items: center; text-shadow: 0px 2px 4px rgba(0,0,0,0.8);
            transition: transform 0.2s; outline: none;
        }
        .short-action-btn:hover { transform: scale(1.1); }
        .short-action-btn span { font-size: 13px; margin-top: 4px; font-weight: bold; color: #fff; }
        .icon-svg { fill: rgba(255,255,255,0.9); stroke: #fff; filter: drop-shadow(0px 2px 4px rgba(0,0,0,0.6)); transition: fill 0.3s, stroke 0.3s; }
        .icon-comment { fill: transparent; stroke: #fff; }
        .short-action-btn.liked .icon-heart { fill: #ff2a5f; stroke: #ff2a5f; }

        .short-info {
            position: absolute; bottom: 25px; left: 15px; right: 80px; color: white;
            z-index: 10; pointer-events: none;
        }
        .short-info h4 { margin: 0; font-size: 15px; font-weight: 600; line-height: 1.4; color: #fff; text-shadow: 0px 1px 4px rgba(0,0,0,0.9); }
        .sound-indicator {
            position: absolute; top: 20px; right: 20px; color: white; background: rgba(0,0,0,0.5);
            padding: 6px 12px; border-radius: 20px; font-size: 12px; pointer-events: none; display: flex; align-items: center;
        }
        .shorts-loader { height: 80px; width: 100%; display: flex; justify-content: center; align-items: center; color: #fff; scroll-snap-align: start; }
        .shorts-loader svg { animation: spin 1s linear infinite; display: none; color: #ff2a5f; }
        @keyframes spin { 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

    <a href="<?php echo esc_url(home_url('/')); ?>" class="back-home-btn" aria-label="Volver">
        <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
    </a>

    <div class="shorts-viewer-container" id="shorts-scroll-container">
        <?php
        $shorts = new WP_Query(array(
            'post_type'      => array('video', 'post'),
            'posts_per_page' => 15,
            'paged'          => 1,
            'post_status'    => 'publish',
            'meta_query'     => array(array('key' => 'is_short', 'value' => 'on', 'compare' => '='))
        ));

        if ($shorts->have_posts()) :
            $count = 0;
            while ($shorts->have_posts()) : $shorts->the_post();
                $count++;
                vtt_render_short_video_html($count > 3);
            endwhile; wp_reset_postdata();
        else :
            echo '<div style="display:flex; justify-content:center; align-items:center; height:100vh; color:white;"><p>No hay Shorts disponibles aún.</p></div>';
        endif;
        ?>
        <div class="shorts-loader" id="shorts-loader">
            <svg viewBox="0 0 24 24" width="30" height="30" stroke="currentColor" stroke-width="2" fill="none"><circle cx="12" cy="12" r="10" stroke-dasharray="32" stroke-dashoffset="10"></circle></svg>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const container = document.getElementById('shorts-scroll-container');
        let currentPage = 1, isFetching = false, hasMore = true;
        const loaderEl = document.getElementById('shorts-loader');

        const playbackObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                const wrapper = entry.target;
                const video = wrapper.querySelector('video');
                const iframe = wrapper.querySelector('iframe');

                if (entry.isIntersecting) {
                    if (video) {
                        video.muted = true;
                        const indicator = wrapper.querySelector('.sound-indicator');
                        if (indicator) indicator.style.display = 'flex';
                        video.play().catch(() => {});
                    }
                } else {
                    if (video) { video.pause(); video.currentTime = 0; }
                    if (iframe && !iframe.hasAttribute('data-src')) {
                        iframe.src = iframe.src;
                    }
                }
            });
        }, { root: container, threshold: 0.6 });

        const preloaderObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const wrapper = entry.target;
                    const video = wrapper.querySelector('video');
                    const iframe = wrapper.querySelector('iframe');

                    if (video && video.hasAttribute('data-src')) {
                        video.src = video.getAttribute('data-src');
                        video.removeAttribute('data-src');
                        video.load();
                    }
                    if (iframe && iframe.hasAttribute('data-src')) {
                        iframe.src = iframe.getAttribute('data-src');
                        iframe.removeAttribute('data-src');
                    }
                    preloaderObserver.unobserve(wrapper);
                }
            });
        }, { root: container, rootMargin: "400px 0px 400px 0px" });

        function setupShortsEngine() {
            const wrappers = document.querySelectorAll('.short-video-wrapper:not(.observed)');
            wrappers.forEach((wrapper, index) => {
                wrapper.classList.add('observed');
                playbackObserver.observe(wrapper);
                preloaderObserver.observe(wrapper);

                if (index === 11) {
                    const ajaxTrigger = new IntersectionObserver((entries) => {
                        if (entries[0].isIntersecting && !isFetching && hasMore) {
                            loadMoreShorts();
                            ajaxTrigger.unobserve(wrapper);
                        }
                    }, { root: container, threshold: 0.1 });
                    ajaxTrigger.observe(wrapper);
                }

                const video = wrapper.querySelector('video');
                if (video) {
                    video.loop = false;
                    video.addEventListener('ended', function() {
                        const next = wrapper.nextElementSibling;
                        if (next && next.classList.contains('short-video-wrapper')) {
                            next.scrollIntoView({ behavior: 'smooth' });
                        } else {
                            this.currentTime = 0; this.play();
                        }
                    });
                    video.addEventListener('click', function() {
                        if (this.muted) {
                            this.muted = false;
                            const ind = this.parentElement.querySelector('.sound-indicator');
                            if (ind) ind.style.display = 'none';
                        } else {
                            if (this.paused) this.play(); else this.pause();
                        }
                    });
                }

                const likeBtn = wrapper.querySelector('.btn-like');
                if (likeBtn) {
                    likeBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        if (this.classList.contains('liked')) return;
                        this.classList.add('liked');
                        const cnt = this.querySelector('.like-counter');
                        cnt.innerText = parseInt(cnt.innerText) + 1;

                        const fd = new FormData();
                        fd.append('action', 'post_like');
                        fd.append('post_id', this.getAttribute('data-post-id'));
                        fd.append('nonce', '<?php echo wp_create_nonce("ajax-nonce"); ?>');
                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd }).catch(() => {});
                    });
                }
            });
        }
        setupShortsEngine();

        function loadMoreShorts() {
            isFetching = true;
            currentPage++;
            const spinner = loaderEl.querySelector('svg');
            if (spinner) spinner.style.display = 'inline-block';

            const fd = new FormData();
            fd.append('ajax_shorts', '1');
            fd.append('paged', currentPage);

            fetch(window.location.href, { method: 'POST', body: fd })
            .then(res => res.text())
            .then(html => {
                if (html.trim() === '') {
                    hasMore = false;
                    loaderEl.innerHTML = '<p style="color:#555; font-size:13px; margin-top:15px;">Has visto todos los Shorts</p>';
                    return;
                }
                loaderEl.insertAdjacentHTML('beforebegin', html);
                setupShortsEngine();
                isFetching = false;
                if (spinner) spinner.style.display = 'none';
            })
            .catch(() => { isFetching = false; });
        }

        if (window.location.hash) {
            const targetEl = document.getElementById(window.location.hash.substring(1));
            if (targetEl) {
                setTimeout(() => { targetEl.scrollIntoView({ behavior: 'auto' }); }, 120);
            }
        }
    });
    </script>
    <?php wp_footer(); ?>
</body>
</html>