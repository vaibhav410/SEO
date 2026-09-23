<?php
/** @var string $slug */
$post = post_by_slug($slug);
if (!$post) {
    abort(404);
}
$path = '/blog/' . $post['slug'];
$faqs = faqs_for('post', (int) $post['id']);
$toc = markdown_toc($post['content']);
$related = posts_related($post);
$body = apply_internal_links(render_markdown($post['content']), $path, (int) setting('internal_links_max', '5'))['html'];

$seo = seo([
    'title'       => $post['meta_title'] ?: $post['title'],
    'description' => $post['meta_description'] ?: $post['excerpt'],
    'path'        => $path,
    'type'        => 'article',
    'canonical'   => (string) $post['canonical_url'],
    'og_title'    => (string) $post['og_title'],
    'og_description' => (string) $post['og_description'],
    'image'       => $post['featured_image'] ? '/' . $post['featured_image'] : '/assets/images/og-default.png',
    'breadcrumbs' => ['Home' => '/', 'Blog' => '/blog', $post['title'] => $path],
    'schema'      => [schema_article($post), schema_faq($faqs)],
]);
require APP_ROOT . '/includes/header.php';
?>
<header class="article-header">
    <div class="container">
        <?= breadcrumb_nav($seo['breadcrumbs']) ?>
        <h1><?= e($post['title']) ?></h1>
        <p class="lead"><?= e($post['excerpt']) ?></p>
        <p class="article-meta">
            <?php if ($post['category_slug']): ?><a class="article-category" href="<?= e(url('/blog/category/' . $post['category_slug'])) ?>"><?= e($post['category_name']) ?></a><?php endif; ?>
            <span>By <?= e($post['author_name'] ?? 'SYSCOM Team') ?></span>
            <span>Published <time datetime="<?= e(iso_date($post['published_at'])) ?>"><?= e(format_date($post['published_at'])) ?></time></span>
            <?php if (strtotime($post['updated_at']) > strtotime($post['published_at']) + 86400): ?>
                <span>Updated <time datetime="<?= e(iso_date($post['updated_at'])) ?>"><?= e(format_date($post['updated_at'])) ?></time></span>
            <?php endif; ?>
            <span><?= reading_time($post['content']) ?> min read</span>
        </p>
    </div>
</header>

<div class="container article-layout">
    <article>
        <?php if ($post['featured_image']): ?>
            <img class="article-image" src="<?= e(url('/' . $post['featured_image'])) ?>" alt="<?= e($post['featured_image_alt'] ?: $post['title']) ?>" width="1200" height="675">
        <?php endif; ?>
        <div class="prose">
            <?= $body ?>
        </div>

        <?php if ($faqs): ?>
            <section class="article-faq" aria-labelledby="article-faq">
                <h2 id="article-faq">Frequently asked questions</h2>
                <?= faq_list($faqs) ?>
            </section>
        <?php endif; ?>
    </article>

    <aside class="article-aside">
        <?php if (count($toc) > 1): ?>
            <nav class="toc" aria-labelledby="toc-title">
                <h2 id="toc-title">On this page</h2>
                <ol>
                    <?php foreach ($toc as $h): ?>
                        <li><a href="#<?= e($h['id']) ?>"><?= e($h['text']) ?></a></li>
                    <?php endforeach; ?>
                </ol>
            </nav>
        <?php endif; ?>
        <?php if ($post['service_slug']): ?>
            <div class="aside-card">
                <h2><?= e($post['service_name']) ?></h2>
                <p>See what is included and who it is best for.</p>
                <a class="btn btn-primary btn-sm" href="<?= e(url('/services/' . $post['service_slug'])) ?>">View <?= e($post['service_name']) ?></a>
            </div>
        <?php endif; ?>
        <div class="aside-card">
            <h2>Related services</h2>
            <ul class="aside-links">
                <?php foreach (array_slice(array_filter(services_published(), fn($s) => $s['slug'] !== $post['service_slug']), 0, 4) as $s): ?>
                    <li><a href="<?= e(url('/services/' . $s['slug'])) ?>"><?= icon($s['icon'], 'icon icon-sm') ?> <?= e($s['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </aside>
</div>

<?php if ($related): ?>
<section class="section section-alt" aria-labelledby="related-title">
    <div class="container">
        <h2 id="related-title">Keep reading</h2>
        <div class="grid grid-3">
            <?php foreach ($related as $p): ?>
                <?= post_card($p) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="container">
        <div class="cta-band">
            <div>
                <h2>Have a question about your setup?</h2>
                <p>Our team can help you choose the right hosting, domain and email options.</p>
            </div>
            <a class="btn btn-light" href="<?= e(url('/contact')) ?>">Contact us</a>
        </div>
    </div>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
