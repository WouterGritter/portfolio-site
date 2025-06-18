<?php

include_once "template.php";
include_once "renderer.php";

template_head_start('Home');
?>
<style>
    a.post-link {
        font-size: 1.2em;
    }

    .post-date {
        font-size: 0.75em;
    }
</style>
<?php
template_head_end();

template_body_start();

?>

<h1>Hello, world!</h1>

<h6>Psst, see this site's source code <a href="https://github.com/WouterGritter/portfolio-site">on GitHub</a>.</h6>

<p>
    Programming has been my core passion since childhood, driving everything I do. I thrive on building software
    solutions and love getting hands-on with infrastructure and physical hardware – there's something uniquely
    satisfying about learning from tangible tech. This site showcases that journey: explore my software projects,
    technical accomplishments, and the blog posts where I dive deep into the how and why behind code I've written
    and projects I've worked on. Be sure to check out the recently updated <a href="/homelab/">Homelab</a> section too,
    where I geek out on datacenter gear, experiment with new stacks and tools, and tinker with self-hosted setups to
    push my understanding further. Explore the sections to the left to see my work.
</p>

<br><hr><br>

<h2>Recent posts</h2>

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
        'url' => '/' . str_replace('.md', '/', $file_path),
        'title' => $file_attributes['longtitle'] ?? $file_attributes['title'] ?? substr($file, 0, -3),
        'postdate' => $file_attributes['postdate'] ?? 'Unknown',
        'description' => $file_attributes['description'] ?? '<br>',
        'parsed_postdate' => $parsed_postdate,
    );
}

usort($posts, function($a, $b) {
    return $b['parsed_postdate'] <=> $a['parsed_postdate'];
});

$posts = array_slice($posts, 0, 3);
?>

<ul>
<?php foreach ($posts as $post) { ?>
    <li>
        <a href="<?= $post['url'] ?>" class="post-link"><?= $post['title'] ?></a>
        <span class="post-date">(Posted <?= $post['postdate'] ?>)</span>
    </li>
<?php } ?>
</ul>

<?php

template_body_end();

?>

