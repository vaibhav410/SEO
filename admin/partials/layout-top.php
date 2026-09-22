<?php
/** @var string $title @var string $active @var array|null $user */
$sections = [
    'Overview' => [
        'dashboard' => ['/admin/dashboard.php', 'Dashboard', 'trending'],
    ],
    'Content' => [
        'posts'    => ['/admin/posts/', 'Articles', 'book'],
        'services' => ['/admin/services/', 'Services', 'server'],
        'landing'  => ['/admin/landing-pages/', 'Landing pages', 'zap'],
        'faqs'     => ['/admin/faqs/', 'FAQs', 'headset'],
    ],
    'SEO' => [
        'keywords'  => ['/admin/keywords/', 'Keywords', 'search'],
        'links'     => ['/admin/links/', 'Internal links', 'layers'],
        'audits'    => ['/admin/audits/', 'SEO auditor', 'shield'],
        'backlinks' => ['/admin/backlinks/', 'Off-page / backlinks', 'globe'],
    ],
    'Growth' => [
        'leads' => ['/admin/leads/', 'Leads', 'users'],
    ],
];
if (($user['role'] ?? '') === 'admin') {
    $sections['System'] = ['settings' => ['/admin/settings/', 'Settings', 'cpu']];
}
$newLeads = (int) db_value("SELECT COUNT(*) FROM leads WHERE status = 'new'");
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> · GrowthHub Admin</title>
    <link rel="icon" href="<?= e(url('/assets/images/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
    <script src="<?= e(asset('js/validation.js')) ?>" defer></script>
    <script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</head>
<body class="admin">
<a class="skip-link" href="#admin-main">Skip to content</a>
<div class="admin-shell">
    <aside class="sidebar" id="sidebar">
        <a class="sidebar-brand" href="<?= e(url('/admin/dashboard.php')) ?>">
            <img src="<?= e(url('/assets/images/logo.svg')) ?>" alt="" width="30" height="30">
            <span>GrowthHub</span>
        </a>
        <nav aria-label="Admin">
            <?php foreach ($sections as $heading => $items): ?>
                <p class="nav-heading"><?= e($heading) ?></p>
                <ul>
                    <?php foreach ($items as $key => [$href, $label, $ic]): ?>
                        <li>
                            <a href="<?= e(url($href)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>>
                                <?= icon($ic, 'icon icon-sm') ?> <span><?= e($label) ?></span>
                                <?php if ($key === 'leads' && $newLeads > 0): ?><span class="nav-count" title="New leads"><?= $newLeads ?></span><?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </nav>
    </aside>
    <div class="admin-body">
        <header class="topbar">
            <button class="btn btn-icon sidebar-toggle" type="button" aria-controls="sidebar" aria-expanded="false">
                <?= icon('menu', 'icon icon-sm') ?><span class="visually-hidden">Toggle navigation</span>
            </button>
            <a class="topbar-site" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">View site <?= icon('external', 'icon icon-xs') ?></a>
            <div class="topbar-user">
                <span><?= e($user['name'] ?? '') ?> <small class="role"><?= e($user['role'] ?? '') ?></small></span>
                <form method="post" action="<?= e(url('/admin/logout.php')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline" type="submit">Sign out</button>
                </form>
            </div>
        </header>
        <main id="admin-main" class="admin-main" tabindex="-1">
            <?= render_flash() ?>
