<?php

// Redirect www to non-www
if (str_starts_with($_SERVER['HTTP_HOST'], 'www.')) {
    $scheme = $_SERVER['REQUEST_SCHEME'] ?? 'http';
    $newHost = substr($_SERVER['HTTP_HOST'], 4);
    $newUrl = "$scheme://$newHost" . $_SERVER['REQUEST_URI'];

    header("Location: $newUrl", true, 301);
    exit();
}

$request = $_SERVER['DOCUMENT_URI'] ?? '';
$request = '/' . trim($request, '/');

$remove_extensions = ['.php', '.md', '.htm', '.html'];

// Remove any extensions from url if present
foreach ($remove_extensions as $extension) {
    if (str_ends_with($request, $extension)) {
        $newPath = substr($request, 0, -strlen($extension));
        header("Location: $newPath", true, 301);
        exit();
    }
}

$redirects = array(
    '/index' => '/',
    '/my-latest-posts' => '/projects/',
    '/blog' => '/projects/',
    '/2019/01/29/esp8266-lamp-project' => '/posts/esp8266-lamp-project/',
    '/2020/07/14/advanced-csgo-in-real-life-bomb' => '/posts/csgo-bomb-irl/',
    '/2020/07/21/how-i-got-the-csgo-bomb-beep-pattern' => '/posts/csgo-bomb-beep-pattern/',
    '/2020/07/22/wrapping-up-the-csgo-bomb-project' => '/posts/csgo-bomb-wrapping-up/',
);

// Apply any redirect in $redirects map
foreach ($redirects as $key => $value) {
    if (str_starts_with($request, $key)) {
        header("Location: $value", true, 301);
        exit();
    }
}

$request = trim($request, '/');
$request = str_replace(['../', './'], '', $request);

if ($request == '') {
    $request = 'home';
}

$php_file = __DIR__ . '/' . $request . '.php';
$md_file = __DIR__ . '/' . $request . '.md';
$html_file = __DIR__ . '/' . $request . '.html';

include_once "renderer.php";
include_once 'lib/TinyHtmlMinifier.php';

ob_start();

if ($request != 'index' && file_exists($php_file)) {
    render_php_file($php_file);
} else if (file_exists($md_file)) {
    render_md_file($md_file);
} else {
    render_404();
}

$html = ob_get_clean();

$minifier = new TinyHtmlMinifier([]);
echo $minifier->minify($html);
