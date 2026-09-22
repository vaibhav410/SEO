<?php
/**
 * Public page header. Expects $seo (from seo()) and optional $bodyClass.
 */
$nav = [
    '/services'  => 'Services',
    '/blog'      => 'Blog',
    '/resources' => 'Resources',
    '/faq'       => 'FAQ',
];
$path = current_path();
$isActive = fn(string $href) => $path === $href || str_starts_with($path, $href . '/');
?>
<!doctype html>
<html lang="en-IN" class="no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= seo_tags($seo) ?>

    <meta name="theme-color" content="#0b4f9c">
    <link rel="icon" href="<?= e(url('/assets/images/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/responsive.css')) ?>">
    <script src="<?= e(asset('js/main.js')) ?>" defer></script>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
    <div class="container header-inner">
        <a class="logo" href="<?= e(url('/')) ?>" aria-label="<?= e(setting('site_name', 'SYSCOM')) ?> home">
            <img src="<?= e(url('/assets/images/logo.svg')) ?>" alt="" width="36" height="36">
            <span><?= e(setting('site_name', 'SYSCOM')) ?></span>
        </a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">
            <?= icon('menu') ?><span class="visually-hidden">Menu</span>
        </button>
        <nav id="site-nav" class="site-nav" aria-label="Main">
            <ul>
                <?php foreach ($nav as $href => $label): ?>
                    <li><a href="<?= e(url($href)) ?>"<?= $isActive($href) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <a class="btn btn-primary btn-sm" href="<?= e(url('/contact')) ?>">Get a quote</a>
        </nav>
    </div>
</header>
<main id="main" tabindex="-1">
