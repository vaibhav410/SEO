<?php
$ADMIN_ROLES = ['admin'];
require __DIR__ . '/../_init.php';

$rules = setting('robots_extra');
$problems = robots_validate($rules);

if (is_post()) {
    $rules = trim(str_replace("\r", '', (string) ($_POST['robots_extra'] ?? '')));
    $problems = robots_validate($rules);
    $errors = array_filter($problems, fn($p) => $p['level'] === 'error');
    if (mb_strlen($rules) > 3000) {
        flash('error', 'Custom rules must be under 3000 characters.');
    } elseif ($errors) {
        flash('error', 'Fix the errors below before saving. Nothing was changed.');
    } else {
        settings_save(['robots_extra' => $rules]);
        log_activity('updated', 'settings', null, 'robots.txt custom rules updated');
        flash('success', 'robots.txt updated.' . ($problems ? ' Saved with warnings.' : ''));
        redirect('/admin/technical/robots.php');
    }
}
$lastUpdated = db_value("SELECT updated_at FROM settings WHERE setting_key = 'robots_extra'");

admin_header('Robots.txt', 'robots', ['Technical SEO' => '/admin/technical/', 'Robots.txt' => null]);
echo page_header('Robots.txt', 'Controls which paths crawlers may request. Built-in rules protect private areas; add your own below.',
    '<a class="btn btn-outline" href="' . e(url('/robots.txt')) . '" target="_blank" rel="noopener">Open live file</a>');
echo tabs(['/admin/technical/' => 'Overview', '/admin/technical/schema.php' => 'Structured data', '/admin/technical/sitemap.php' => 'Sitemap', '/admin/technical/robots.php' => 'Robots.txt'], '/admin/technical/robots.php', 'Technical SEO sections');
?>
<div class="grid-2">
    <section class="panel">
        <h2>Rules</h2>
        <?= meta_list([
            'User-agent' => '<code>*</code> (all crawlers)',
            'Allow' => '<code>' . e(base_path() . '/') . '</code>',
            'Disallow' => implode(' ', array_map(fn($p) => '<code>' . e(base_path() . $p) . '</code>', robots_default_disallow())),
            'Sitemap' => '<code>' . e(absolute_url('/sitemap.xml')) . '</code>',
            'Last updated' => $lastUpdated ? e(format_date($lastUpdated, 'j M Y, g:i a')) : 'Default rules only',
        ]) ?>
        <hr>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="field">
                <label for="robots_extra">Custom rules <span class="muted">(appended after the built-in rules)</span></label>
                <textarea id="robots_extra" name="robots_extra" rows="8" class="mono" placeholder="User-agent: GPTBot&#10;Disallow: /"><?= e($rules) ?></textarea>
                <p class="help">One directive per line: <code>User-agent</code>, <code>Allow</code>, <code>Disallow</code>, <code>Sitemap</code>. Built-in rules for admin and private folders cannot be removed.</p>
            </div>
            <?php if ($problems): ?>
                <ul class="issue-list">
                    <?php foreach ($problems as $p): ?>
                        <li class="issue-<?= $p['level'] === 'error' ? 'fail' : 'warn' ?>"><span class="issue-icon" aria-hidden="true">!</span><div><strong>Line <?= (int) $p['line'] ?></strong> · <?= e($p['message']) ?></div></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="small"><?= status_indicator('valid', 'Validation passed') ?></p>
            <?php endif; ?>
            <button class="btn btn-primary" type="submit">Validate &amp; save</button>
        </form>
    </section>
    <section class="panel">
        <div class="panel-head"><h2>Live preview</h2><button class="btn btn-outline btn-sm" type="button" data-copy="robots-preview">Copy</button></div>
        <pre class="code-box" id="robots-preview"><?= e(robots_txt()) ?></pre>
        <p class="small muted">robots.txt controls crawling, not indexing. To keep a page out of search results, use <code>noindex</code> (GrowthHub already does this for admin, 404 and search pages).</p>
    </section>
</div>
<?php admin_footer(); ?>
