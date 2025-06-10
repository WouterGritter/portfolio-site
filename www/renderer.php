<?php

include_once 'lib/Parsedown.php';

function render_php_file($file): void {
    include $file;
}

function render_md_file($file): void {
    $md_text = file_get_contents($file);
    $md_attributes = extract_md_attributes($md_text);

    $md_text = fix_md_links($md_text);
    $md_text = replace_placeholders($md_text, $md_attributes);

    $parsedown = new Parsedown();
    $md_html = $parsedown->text($md_text);

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

function replace_placeholders($md_text, array $md_attributes): string {
    $pattern = '/@(\w+)/';

    $replacement = function ($matches) use ($md_attributes) {
        $placeholder = $matches[1];
        if (array_key_exists($placeholder, $md_attributes)) {
            return $md_attributes[$placeholder];
        }
        return $matches[0];
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
