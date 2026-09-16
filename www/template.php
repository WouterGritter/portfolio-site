<?php

// Giscus (https://giscus.app) comment settings. Comments are stored as GitHub
// Discussions in the repository below, one discussion per post.
const GISCUS_REPO = 'WouterGritter/portfolio-comments';
const GISCUS_REPO_ID = 'R_kgDOUdaHsg';
const GISCUS_CATEGORY = 'Comments';
const GISCUS_CATEGORY_ID = 'DIC_kwDOUdaHss4DFvD_';

// Cache-busting query string for our own static assets (Cloudflare caches them).
// On the live site this is the short git commit hash baked into the image, so it
// changes on every publish. During development (no hash set) a random string is
// generated per request, so assets are never served stale.
function asset_version() {
    static $version = null;
    if ($version === null) {
        $commit = getenv('GIT_COMMIT');
        if ($commit !== false && $commit !== '') {
            $version = $commit;
        } else {
            $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-';
            $random = '';
            for ($i = 0; $i < 8; $i++) {
                $random .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $version = 'dev-' . $random;
        }
    }
    return $version;
}

function template_head_start($title) {
    $v = asset_version();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title><?= $title ?> | Wouter's Portfolio</title>

        <script>
            (function() {
                var pref = localStorage.getItem('theme') || 'auto';
                var dark = pref === 'dark' || (pref === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                if (dark) document.documentElement.classList.add('dark-theme');
            })();
        </script>

        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=G-HFTES63JGM"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());

            gtag('config', 'G-HFTES63JGM');
        </script>

        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2840255380088642" crossorigin="anonymous"></script>

        <link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon-16x16.png">
        <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="48x48" href="/assets/favicon-48x48.png">
        <link rel="icon" type="image/png" sizes="64x64" href="/assets/favicon-64x64.png">
        <link rel="shortcut icon" href="/favicon.ico">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <link rel="stylesheet" href="/assets/style.css?<?= $v ?>">

        <link rel="stylesheet" id="hljs-theme" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>

        <script src="/assets/theme.js?<?= $v ?>"></script>
    <?php
}

function template_head_end() {
    ?>
    </head>
    <?php
}

function template_body_start() {
    ?>
    <body>
        <div class="header">
            <div>
                <img class="header-icon" src="/assets/favicon-64x64.png">
                Wouter Gritter
            </div>
            <div class="sub-header">Software Developer <span class="pipe">|</span> Electronics Hobbyist <span class="pipe">|</span> Homelab Enthusiast</div>
        </div>

        <div class="nav-links">
            <a href="/">Home</a>
            <a href="/homelab/">My Homelab</a>
            <a href="/posts/">Posts</a>
            <a href="/contact/">Contact/Where to Find Me</a>
        </div>
        <button id="theme-toggle" title="Theme"><i class="fa-solid fa-circle-half-stroke"></i></button>
        <div class="content-container">
            <div class="content">
    <?php
}

// Comment section for a post. $term identifies the post (the post slug) and is
// the title of the matching GitHub Discussion. The giscus script itself is
// injected by theme.js so it can be given the active theme up front.
function template_comments($term) {
    if (GISCUS_REPO_ID === '' || GISCUS_CATEGORY_ID === '') {
        return;
    }
    ?>
    <hr>
    <h2 id="comments">Comments</h2>
    <div class="giscus"
         data-repo="<?= htmlspecialchars(GISCUS_REPO) ?>"
         data-repo-id="<?= htmlspecialchars(GISCUS_REPO_ID) ?>"
         data-category="<?= htmlspecialchars(GISCUS_CATEGORY) ?>"
         data-category-id="<?= htmlspecialchars(GISCUS_CATEGORY_ID) ?>"
         data-mapping="specific"
         data-term="<?= htmlspecialchars($term) ?>"
         data-strict="1"
         data-reactions-enabled="1"
         data-emit-metadata="0"
         data-input-position="top"
         data-lang="en"
         data-loading="lazy">
        <noscript>Comments require JavaScript. You can also leave one directly at
            <a href="https://github.com/<?= htmlspecialchars(GISCUS_REPO) ?>/discussions">github.com/<?= htmlspecialchars(GISCUS_REPO) ?>/discussions</a>.</noscript>
    </div>
    <?php
}

function template_body_end() {
    ?>
            </div>
        </div>
    </body>
    </html>
    <?php
}

?>
