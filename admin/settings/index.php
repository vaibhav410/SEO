<?php
$ADMIN_ROLES = ['admin'];
require __DIR__ . '/../_init.php';

$errors = [];
$pwErrors = [];
$form = input('form');

if (is_post() && $form === 'settings') {
    [$data, $errors] = validate($_POST, [
        'site_name'          => 'required|max:60',
        'site_tagline'       => 'max:120',
        'site_description'   => 'required|max:300',
        'contact_email'      => 'email|max:190',
        'contact_phone'      => 'phone|max:30',
        'contact_address'    => 'max:300',
        'main_site_url'      => 'url|max:255',
        'social_profiles'    => 'max:2000',
        'internal_links_max' => 'required|int',
    ]);
    foreach (lines($data['social_profiles'] ?? '') as $profile) {
        if (!is_http_url($profile)) {
            $errors['social_profiles'] = 'Each profile must be a full https:// URL on its own line.';
        }
    }
    if (!$errors) {
        $data['internal_links_max'] = (string) max(0, min(15, (int) $data['internal_links_max']));
        settings_save(array_map(fn($v) => (string) $v, $data));
        flash('success', 'Settings saved.');
        redirect('/admin/settings/');
    }
}

if (is_post() && $form === 'password') {
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $hash = (string) db_value('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
    if (!password_verify($current, $hash)) {
        $pwErrors['current_password'] = 'Current password is incorrect.';
    }
    if (strlen($new) < 10 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
        $pwErrors['new_password'] = 'Use at least 10 characters with letters and numbers.';
    } elseif ($new !== ($_POST['confirm_password'] ?? '')) {
        $pwErrors['confirm_password'] = 'Passwords do not match.';
    }
    if (!$pwErrors) {
        db_update('users', (int) $user['id'], ['password_hash' => password_hash($new, PASSWORD_DEFAULT)]);
        session_regenerate_id(true);
        flash('success', 'Password changed.');
        redirect('/admin/settings/');
    }
}

$values = array_merge(settings_all(), $form === 'settings' ? $_POST : []);
$v = fn($k) => (string) ($values[$k] ?? '');

admin_header('Settings', 'settings');
?>
<div class="page-head"><div><h1>Settings</h1><p>Site identity used in titles, structured data and the footer.</p></div></div>
<div class="form-layout">
    <section class="panel">
        <h2>Site</h2>
        <form method="post" novalidate>
            <?= csrf_field() ?><input type="hidden" name="form" value="settings">
            <div class="form-row">
                <?= form_input('site_name', 'Site / brand name', $v('site_name'), $errors, ['required' => true, 'maxlength' => 60]) ?>
                <?= form_input('site_tagline', 'Tagline', $v('site_tagline'), $errors, ['maxlength' => 120]) ?>
            </div>
            <?= form_textarea('site_description', 'Default meta description', $v('site_description'), $errors, ['rows' => 3, 'maxlength' => 300]) ?>
            <div class="form-row">
                <?= form_input('contact_email', 'Public contact email', $v('contact_email'), $errors, ['type' => 'email', 'help' => 'Shown on the contact page and in Organization schema. Leave empty to hide.']) ?>
                <?= form_input('contact_phone', 'Public phone', $v('contact_phone'), $errors) ?>
            </div>
            <?= form_textarea('contact_address', 'Address', $v('contact_address'), $errors, ['rows' => 2]) ?>
            <?= form_input('main_site_url', 'Main website URL', $v('main_site_url'), $errors, ['type' => 'url']) ?>
            <?= form_textarea('social_profiles', 'Official social profiles (one URL per line)', $v('social_profiles'), $errors, ['rows' => 3, 'help' => 'Used as sameAs in Organization structured data. Only add profiles SYSCOM actually owns.']) ?>
            <?= form_input('internal_links_max', 'Max automatic internal links per page', $v('internal_links_max'), $errors, ['type' => 'number', 'min' => 0, 'max' => 15]) ?>
            <button class="btn btn-primary" type="submit">Save settings</button>
        </form>
    </section>
    <div>
        <section class="panel">
            <h2>Change your password</h2>
            <form method="post" novalidate>
                <?= csrf_field() ?><input type="hidden" name="form" value="password">
                <?= form_input('current_password', 'Current password', '', $pwErrors, ['type' => 'password', 'autocomplete' => 'current-password']) ?>
                <?= form_input('new_password', 'New password', '', $pwErrors, ['type' => 'password', 'autocomplete' => 'new-password', 'minlength' => 10]) ?>
                <?= form_input('confirm_password', 'Confirm new password', '', $pwErrors, ['type' => 'password', 'autocomplete' => 'new-password']) ?>
                <button class="btn btn-outline" type="submit">Change password</button>
            </form>
        </section>
        <section class="panel">
            <h2>Environment</h2>
            <dl class="meta small">
                <dt>Base URL</dt><dd><?= e(config('app.base_url')) ?></dd>
                <dt>PHP</dt><dd><?= e(PHP_VERSION) ?></dd>
                <dt>Debug mode</dt><dd><?= config('app.debug') ? '<span class="badge badge-warning">On – turn off in production</span>' : 'Off' ?></dd>
                <dt>AI assistant</dt><dd><?= ai_enabled() ? 'Enabled (' . e(config('ai.model')) . ')' : 'Not configured' ?></dd>
                <dt>Sitemap</dt><dd><a href="<?= e(url('/sitemap.xml')) ?>" target="_blank" rel="noopener"><?= e(absolute_url('/sitemap.xml')) ?></a></dd>
            </dl>
        </section>
    </div>
</div>
<?php admin_footer(); ?>
