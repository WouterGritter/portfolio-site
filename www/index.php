<?php

include_once 'lib/Parsedown.php';

$request = $_GET['q'] ?? '';

if ($request != '' && !str_ends_with($request, '/')) {
    header("Location: {$request}/");
    die();
}

$redirects = array(
    '/index' => '/',
    '/my-latest-posts/' => '/blog/',
    '/2019/01/29/esp8266-lamp-project/' => '/posts/esp8266-lamp-project/',
    '/2020/07/14/advanced-csgo-in-real-life-bomb/' => '/posts/csgo-bomb-irl/',
    '/2020/07/21/how-i-got-the-csgo-bomb-beep-pattern/' => '/posts/csgo-bomb-beep-pattern/',
    '/2020/07/22/wrapping-up-the-csgo-bomb-project/' => '/posts/csgo-bomb-wrapping-up/',
);

foreach ($redirects as $key => $value) {
    if (str_starts_with($request, $key)) {
        header('Location: ' . $value);
        die();
    }
}

$request = trim($request, '/');
$request = str_replace(['../', './'], '', $request);

if ($request == '') {
    $request = 'index';
}

$php_file = __DIR__ . '/' . $request . '.php';
$md_file = __DIR__ . '/' . $request . '.md';

if ($request != 'index' && file_exists($php_file)) {
    render_php_file($php_file);
} else if(file_exists($md_file)) {
    render_md_file($md_file);
} else {
    render_404();
}

function render_php_file($file): void {
    include $file;
}

function render_md_file($file): void {
    $md_text = file_get_contents($file);
    $md_text = fix_md_links($md_text);

    $parsedown = new Parsedown();
    $md_html = $parsedown->text($md_text);

    $md_attributes = extract_md_attributes($md_text);

    include "template.php";

    template_head_start($md_attributes['title'] ?? get_pretty_file_name($file));
    template_head_end();

    template_body_start();
    echo $md_html;
    template_body_end();
}

function extract_md_attributes($md_text): array {
    $metadata = [];

    $pattern = '/<!--\s*(\w+)\s*=\s*(.*?)\s*-->/';

    if (preg_match_all($pattern, $md_text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $key = $match[1];
            $value = $match[2];
            $metadata[$key] = $value;
        }
    }

    return $metadata;
}

function fix_md_links($md_text): string {
    $pattern = '/\[[^]]+\]\((?<link>.*\.md)\)/';

    $replacement = function ($matches) {
        $newLink = str_replace('.md', '/', $matches['link']);
        return str_replace($matches['link'], $newLink, $matches[0]);
    };

    return preg_replace_callback($pattern, $replacement, $md_text);
}

function get_pretty_file_name($path): string {
    $name = basename($path);
    $name = pathinfo($name, PATHINFO_FILENAME);
    $name = str_replace(['-', '_'], ' ', $name);
    $name = ucfirst($name);

    return $name;
}

function render_404(): void {
    header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
    echo '404 Not Found';
}
