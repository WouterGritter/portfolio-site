<?php

include_once "template.php";
include_once "renderer.php";

template_head_start('Projects');
template_head_end();

template_body_start();

?>

<h1>Projects</h1>

<p>Here you can read about some of my projects in a blog-post-style format.</p>

<hr>

<?php

$posts_dir = 'posts/';
$posts = [];
foreach (scandir($posts_dir) as $file) {
    if (!str_ends_with($file, '.md')) {
        continue;
    }

    $file_path = $posts_dir . $file;
    $file_contents = file_get_contents($file_path);
    $file_attributes = extract_md_attributes($file_contents);

    $parsed_postdate = $date = DateTime::createFromFormat('jS \o\f F Y', $file_attributes['postdate']);

    $posts[] = array(
        'url' => str_replace('.md', '/', $file_path),
        'title' => $file_attributes['longtitle'] ?? $file_attributes['title'] ?? substr($file, 0, -3),
        'postdate' => $file_attributes['postdate'] ?? 'Unknown',
        'description' => $file_attributes['description'] ?? '<br>',
        'parsed_postdate' => $parsed_postdate,
    );
}

usort($posts, function($a, $b) {
    return $b['parsed_postdate'] <=> $a['parsed_postdate'];
});

foreach ($posts as $post) {
    ?>

    <h3><?= $post['title'] ?></h3>
    <p>
        <em>
            Posted on <?= $post['postdate'] ?>.
            <a href="<?= $post['url'] ?>">Read here</a>.
        </em>
        <br>
        <?= $post['description'] ?>
    </p>

    <?php
}

template_body_end();

?>

