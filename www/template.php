<?php

function template_head_start($title) {
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

        <script src="https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.10.0/p5.min.js"></script>

        <link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon-16x16.png">
        <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="48x48" href="/assets/favicon-48x48.png">
        <link rel="icon" type="image/png" sizes="64x64" href="/assets/favicon-64x64.png">
        <link rel="shortcut icon" href="/favicon.ico">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <link rel="stylesheet" href="/assets/style.css?4">

        <link rel="stylesheet" id="hljs-theme" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>

        <script src="/assets/theme.js?1"></script>
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

function template_body_end() {
    ?>
            </div>
        </div>
    </body>
    </html>
    <?php
}

?>
