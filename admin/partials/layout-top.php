<?php
/** @var string $title @var string $active @var array $crumbs @var array|null $user */
$isAdmin = ($user['role'] ?? '') === 'admin';
$newLeads = (int) db_value("SELECT COUNT(*) FROM leads WHERE status = 'new'");
$notifications = $user ? notifications_for($user) : [];
$unread = $user ? notifications_unread($notifications, $user) : 0;

// [key, href, label, icon, children[key => [href, label]]]
$nav = [
    'Overview' => [
        ['dashboard', '/admin/dashboard.php', 'Dashboard', 'trending', []],
        ['analytics', '/admin/analytics/', 'Analytics', 'layers', []],
        ['opportunities', '/admin/opportunities/', 'Opportunities', 'zap', []],
    ],
    'Content' => [
        ['content', '/admin/posts/', 'Content', 'book', ['posts' => ['/admin/posts/', 'Blog posts'], 'categories' => ['/admin/categories/', 'Categories'], 'faqs' => ['/admin/faqs/', 'FAQs']]],
        ['landing', '/admin/landing-pages/', 'Landing pages', 'zap', []],
        ['services', '/admin/services/', 'Services', 'server', []],
    ],
    'SEO' => [
        ['keywords', '/admin/keywords/', 'Keywords', 'search', []],
        ['links', '/admin/links/', 'Internal links', 'layers', []],
        ['audits', '/admin/audits/', 'SEO auditor', 'shield', []],
        ['technical', '/admin/technical/', 'Technical SEO', 'cpu', ['technical' => ['/admin/technical/', 'Overview'], 'schema' => ['/admin/technical/schema.php', 'Structured data'], 'sitemap' => ['/admin/technical/sitemap.php', 'Sitemap'], 'robots' => ['/admin/technical/robots.php', 'Robots.txt']]],
    ],
    'Off-page' => [
        ['backlinks', '/admin/backlinks/', 'Backlinks', 'globe', []],
        ['distribution', '/admin/distribution/', 'Organic distribution', 'trending', []],
    ],
    'Growth' => [
        ['leads', '/admin/leads/', 'Leads', 'users', []],
    ],
];
if ($isAdmin) {
    $nav['System'] = [['settings', '/admin/settings/', 'Settings', 'cpu', []]];
}
$isActive = function (string $key, array $children) use ($active): bool {
    return $active === $key || isset($children[$active]);
};
$quick = [
    ['/admin/posts/edit.php', 'New blog post', 'book'],
    ['/admin/landing-pages/edit.php', 'New landing page', 'zap'],
    ['/admin/keywords/edit.php', 'Add keyword', 'search'],
    ['/admin/audits/', 'Run SEO audit', 'shield'],
    ['/admin/backlinks/edit.php', 'Track backlink', 'globe'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> · GrowthHub</title>
    <link rel="icon" href="<?= e(url('/assets/images/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
    <script src="<?= e(asset('js/validation.js')) ?>" defer></script>
    <script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</head>
<body class="admin">
<a class="skip-link" href="#admin-main">Skip to content</a>
<div class="admin-shell">
    <aside class="sidebar" id="sidebar" aria-label="Admin navigation">
        <div class="sidebar-head">
            <a class="sidebar-brand" href="<?= e(url('/admin/dashboard.php')) ?>">
                <img src="<?= e(url('/assets/images/logo.svg')) ?>" alt="" width="30" height="30">
                <span><strong>GrowthHub</strong><small>SYSCOM SEO &amp; Growth</small></span>
            </a>
            <button class="btn btn-icon sidebar-close" type="button" aria-label="Close navigation"><span aria-hidden="true">×</span></button>
        </div>
        <nav>
            <?php foreach ($nav as $heading => $items): ?>
                <p class="nav-heading"><?= e($heading) ?></p>
                <ul>
                    <?php foreach ($items as [$key, $href, $label, $ic, $children]): $on = $isActive($key, $children); ?>
                        <li>
                            <a href="<?= e(url($href)) ?>"<?= $on && !$children ? ' aria-current="page"' : '' ?> class="<?= $on ? 'is-open' : '' ?>">
                                <?= icon($ic, 'icon icon-sm') ?> <span><?= e($label) ?></span>
                                <?php if ($key === 'leads' && $newLeads > 0): ?><span class="nav-count" data-tooltip="New leads"><?= $newLeads ?></span><?php endif; ?>
                            </a>
                            <?php if ($children && $on): ?>
                                <ul class="subnav">
                                    <?php foreach ($children as $childKey => [$childHref, $childLabel]): ?>
                                        <li><a href="<?= e(url($childHref)) ?>"<?= $active === $childKey ? ' aria-current="page"' : '' ?>><?= e($childLabel) ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </nav>
        <p class="sidebar-foot"><a href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><?= icon('external', 'icon icon-xs') ?> View public site</a></p>
    </aside>
    <div class="sidebar-backdrop" hidden></div>

    <div class="admin-body">
        <header class="topbar">
            <button class="btn btn-icon sidebar-toggle" type="button" aria-controls="sidebar" aria-expanded="false">
                <?= icon('menu', 'icon icon-sm') ?><span class="visually-hidden">Open navigation</span>
            </button>

            <form class="global-search" role="search" method="get" action="<?= e(url('/admin/search.php')) ?>">
                <label for="global-q" class="visually-hidden">Search GrowthHub</label>
                <?= icon('search', 'icon icon-sm') ?>
                <input type="search" id="global-q" name="q" placeholder="Search content, keywords, leads…" autocomplete="off"
                       data-admin-search="<?= e(url('/api/admin-search.php')) ?>" aria-controls="global-results" aria-expanded="false" maxlength="100">
                <kbd class="kbd" aria-hidden="true">/</kbd>
                <div id="global-results" class="search-popover" role="listbox" hidden></div>
            </form>

            <div class="topbar-actions">
                <?= dropdown('<span class="btn btn-primary btn-sm">' . icon('zap', 'icon icon-xs') . '<span class="btn-label">Quick actions</span></span>',
                    implode('', array_map(fn($q) => '<a href="' . e(url($q[0])) . '">' . icon($q[2], 'icon icon-sm') . ' ' . e($q[1]) . '</a>', $quick)), 'dropdown-right quick-actions') ?>

                <details class="dropdown dropdown-right notifications">
                    <summary aria-label="Notifications<?= $unread ? ", $unread unread" : '' ?>" class="btn btn-icon">
                        <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 1112 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 003.4 0"/></svg>
                        <?php if ($unread): ?><span class="badge-dot"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
                    </summary>
                    <div class="dropdown-menu notif-menu">
                        <div class="notif-head">
                            <strong>Notifications</strong>
                            <?php if ($unread): ?>
                                <form method="post" action="<?= e(url('/admin/notifications.php')) ?>" class="inline-form"><?= csrf_field() ?>
                                    <button class="btn btn-link btn-sm" type="submit">Mark all read</button></form>
                            <?php endif; ?>
                        </div>
                        <?php if ($notifications): ?>
                            <?php foreach (array_slice($notifications, 0, 8) as $n): ?>
                                <a class="notif notif-<?= e($n['tone']) ?>" href="<?= e(url($n['url'])) ?>">
                                    <span class="notif-icon"><?= icon($n['icon'], 'icon icon-xs') ?></span>
                                    <span><strong><?= e($n['title']) ?></strong><br><span class="muted small"><?= e($n['detail']) ?></span></span>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="muted small notif-empty">You're all caught up.</p>
                        <?php endif; ?>
                    </div>
                </details>

                <details class="dropdown dropdown-right profile">
                    <summary aria-label="Account menu" class="avatar-btn">
                        <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($user['name'] ?? '?', 0, 1))) ?></span>
                        <span class="profile-name"><?= e($user['name'] ?? '') ?><small><?= e(ucfirst($user['role'] ?? '')) ?></small></span>
                    </summary>
                    <div class="dropdown-menu">
                        <p class="dropdown-label"><?= e($user['email'] ?? '') ?></p>
                        <?php if ($isAdmin): ?><a href="<?= e(url('/admin/settings/')) ?>"><?= icon('cpu', 'icon icon-sm') ?> Settings</a><?php endif; ?>
                        <a href="<?= e(url('/admin/settings/?tab=account')) ?>"><?= icon('lock', 'icon icon-sm') ?> Change password</a>
                        <a href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><?= icon('external', 'icon icon-sm') ?> View site</a>
                        <form method="post" action="<?= e(url('/admin/logout.php')) ?>"><?= csrf_field() ?>
                            <button class="dropdown-item" type="submit"><?= icon('arrow', 'icon icon-sm') ?> Sign out</button></form>
                    </div>
                </details>
            </div>
        </header>

        <?php if ($crumbs): ?>
            <nav class="admin-crumbs" aria-label="Breadcrumb"><ol>
                <li><a href="<?= e(url('/admin/dashboard.php')) ?>">Dashboard</a></li>
                <?php $last = array_key_last($crumbs); foreach ($crumbs as $label => $href): ?>
                    <li<?= $label === $last ? ' aria-current="page"' : '' ?>><?= $label === $last || !$href ? e($label) : '<a href="' . e(url($href)) . '">' . e($label) . '</a>' ?></li>
                <?php endforeach; ?>
            </ol></nav>
        <?php endif; ?>

        <main id="admin-main" class="admin-main" tabindex="-1">
