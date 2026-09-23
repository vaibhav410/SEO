<?php
$ADMIN_ROLES = ['admin', 'editor'];
/** Published demo password; the security tab warns while any account still uses it. */
const DEMO_ADMIN_PASSWORD_CHECK = 'Admin@12345';
require __DIR__ . '/../_init.php';

$tabs = ['general' => 'General', 'seo' => 'SEO defaults', 'users' => 'Users & permissions', 'security' => 'Security', 'system' => 'System', 'account' => 'My account'];
$tab = isset($tabs[input('tab', '', 'get')]) ? input('tab', '', 'get') : 'general';
// Editors may only change their own password; everything else is admin-only.
if ($tab !== 'account' && $user['role'] !== 'admin') {
    abort(403, 'Only administrators can change site settings.');
}

$errors = [];
$form = input('form');
$back = fn() => redirect('/admin/settings/?tab=' . $tab);

if (is_post() && $form === 'general') {
    [$data, $errors] = validate($_POST, [
        'site_name' => 'required|max:60', 'site_tagline' => 'max:120', 'main_site_url' => 'url|max:255',
        'contact_email' => 'email|max:190', 'contact_phone' => 'phone|max:30', 'contact_address' => 'max:300', 'social_profiles' => 'max:2000',
    ]);
    foreach (lines($data['social_profiles'] ?? '') as $profile) {
        if (!is_http_url($profile)) {
            $errors['social_profiles'] = 'Each profile must be a full https:// URL on its own line.';
        }
    }
    $upload = handle_image_upload($_FILES['logo'] ?? []);
    if ($upload['error']) {
        $errors['logo'] = $upload['error'];
    }
    if (!$errors) {
        if ($upload['path']) {
            delete_upload(setting('logo_path') ?: null);
            $data['logo_path'] = $upload['path'];
        } elseif (input('remove_logo') === '1') {
            delete_upload(setting('logo_path') ?: null);
            $data['logo_path'] = '';
        }
        settings_save(array_map(fn($v) => (string) $v, $data));
        log_activity('updated', 'settings', null, 'General settings updated');
        flash('success', 'General settings saved.');
        $back();
    }
    delete_upload($upload['path']);
}

if (is_post() && $form === 'seo') {
    [$data, $errors] = validate($_POST, ['home_meta_title' => 'max:70', 'site_description' => 'required|min:70|max:300', 'internal_links_max' => 'required|int']);
    if (!$errors) {
        $data['internal_links_max'] = (string) max(0, min(15, (int) $data['internal_links_max']));
        settings_save(array_map(fn($v) => (string) $v, $data));
        log_activity('updated', 'settings', null, 'SEO defaults updated');
        flash('success', 'SEO defaults saved.');
        $back();
    }
}

if (is_post() && $form === 'user_add') {
    [$data, $errors] = validate($_POST, ['name' => 'required|max:100', 'email' => 'required|email|max:190', 'role' => 'required|in:admin,editor']);
    $password = (string) ($_POST['password'] ?? '');
    if (strlen($password) < 10 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors['password'] = 'Use at least 10 characters with letters and numbers.';
    }
    if (!isset($errors['email']) && db_value('SELECT 1 FROM users WHERE email = ?', [mb_strtolower($data['email'])])) {
        $errors['email'] = 'A user with this email already exists.';
    }
    if (!$errors) {
        $newId = db_insert('users', ['name' => $data['name'], 'email' => mb_strtolower($data['email']), 'role' => $data['role'], 'password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        log_activity('created', 'user', $newId, 'User ' . $data['email'] . ' added as ' . $data['role']);
        flash('success', 'User added. Share the password with them securely and ask them to change it.');
        $back();
    }
}

if (is_post() && in_array($form, ['user_role', 'user_delete'], true)) {
    $target = db_one('SELECT id, email, role FROM users WHERE id = ?', [input_int('id', 0, 'post')]);
    $admins = (int) db_value("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    if (!$target) {
        abort(404);
    } elseif ((int) $target['id'] === (int) $user['id']) {
        flash('error', 'You cannot change or delete your own account here.');
    } elseif ($target['role'] === 'admin' && $admins <= 1) {
        flash('error', 'At least one administrator must remain.');
    } elseif ($form === 'user_delete') {
        db_delete('users', (int) $target['id']);
        log_activity('deleted', 'user', null, 'User ' . $target['email'] . ' deleted');
        flash('success', 'User deleted.');
    } else {
        $role = input('role') === 'admin' ? 'admin' : 'editor';
        db_update('users', (int) $target['id'], ['role' => $role]);
        log_activity('updated', 'user', (int) $target['id'], 'User ' . $target['email'] . ' is now ' . $role);
        flash('success', 'Role updated.');
    }
    $back();
}

if (is_post() && $form === 'password') {
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $hash = (string) db_value('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
    if (!password_verify($current, $hash)) {
        $errors['current_password'] = 'Current password is incorrect.';
    }
    if (strlen($new) < 10 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
        $errors['new_password'] = 'Use at least 10 characters with letters and numbers.';
    } elseif ($new !== ($_POST['confirm_password'] ?? '')) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }
    if (!$errors) {
        db_update('users', (int) $user['id'], ['password_hash' => password_hash($new, PASSWORD_DEFAULT)]);
        session_regenerate_id(true);
        log_activity('updated', 'user', (int) $user['id'], 'Password changed');
        flash('success', 'Password changed.');
        $back();
    }
}

$values = array_merge(settings_all(), is_post() ? $_POST : []);
$v = fn($k) => (string) ($values[$k] ?? '');

admin_header('Settings', 'settings', ['Settings' => '/admin/settings/', $tabs[$tab] => null]);
echo page_header('Settings', 'Site identity, SEO defaults, users and security for GrowthHub.');
$visibleTabs = $user['role'] === 'admin' ? $tabs : ['account' => 'My account'];
echo tabs(array_combine(array_map(fn($k) => '/admin/settings/?tab=' . $k, array_keys($visibleTabs)), $visibleTabs), '/admin/settings/?tab=' . $tab, 'Settings sections');
if ($errors) {
    echo '<div class="alert alert-error" role="alert">Please fix the highlighted fields. Nothing was saved.</div>';
}

if ($tab === 'general'): ?>
<form method="post" enctype="multipart/form-data" class="form-layout" novalidate>
    <?= csrf_field() ?><input type="hidden" name="form" value="general">
    <section class="panel">
        <p class="section-title">Brand</p>
        <div class="form-row">
            <?= form_input('site_name', 'Site / brand name', $v('site_name'), $errors, ['required' => true, 'maxlength' => 60]) ?>
            <?= form_input('site_tagline', 'Tagline', $v('site_tagline'), $errors, ['maxlength' => 120]) ?>
        </div>
        <div class="form-row">
            <div class="field"><label for="site-url">Site URL</label><input id="site-url" value="<?= e(config('app.base_url')) ?>" readonly>
                <p class="help">Set in config/config.local.php (app.base_url). Used for canonical URLs and the sitemap.</p></div>
            <?= form_input('main_site_url', 'Main website', $v('main_site_url'), $errors, ['type' => 'url']) ?>
        </div>
        <p class="section-title">Contact information</p>
        <div class="form-row">
            <?= form_input('contact_email', 'Public email', $v('contact_email'), $errors, ['type' => 'email', 'help' => 'Shown on the contact page and in Organization schema. Leave empty to hide.']) ?>
            <?= form_input('contact_phone', 'Public phone', $v('contact_phone'), $errors) ?>
        </div>
        <?= form_textarea('contact_address', 'Address', $v('contact_address'), $errors, ['rows' => 2]) ?>
        <?= form_textarea('social_profiles', 'Official social profiles (one URL per line)', $v('social_profiles'), $errors, ['rows' => 3, 'help' => 'Output as sameAs in Organization structured data. Only list profiles SYSCOM owns.']) ?>
        <button class="btn btn-primary" type="submit">Save general settings</button>
    </section>
    <section class="panel">
        <h2>Logo</h2>
        <img class="current-image" src="<?= e(url('/' . (setting('logo_path') ?: 'assets/images/logo.svg'))) ?>" alt="Current logo" width="96" height="96">
        <?php if (setting('logo_path')): ?><label class="check"><input type="checkbox" name="remove_logo" value="1"> Use the default logo</label><?php endif; ?>
        <div class="field"><label for="f-logo">Upload logo</label><input type="file" id="f-logo" name="logo" accept="image/png,image/jpeg,image/webp"<?= field_aria($errors, 'logo') ?>>
            <p class="help">Square PNG, JPG or WebP, at least 112×112. Validated and re-encoded on upload.</p><?= field_error($errors, 'logo') ?></div>
    </section>
</form>

<?php elseif ($tab === 'seo'): ?>
<form method="post" class="form-layout" novalidate>
    <?= csrf_field() ?><input type="hidden" name="form" value="seo">
    <section class="panel">
        <div class="field">
            <label for="f-home_meta_title">Home page title <?= seo_counter('home_meta_title', 30, 60) ?></label>
            <input type="text" id="f-home_meta_title" name="home_meta_title" maxlength="70" value="<?= e($v('home_meta_title')) ?>" placeholder="Web Hosting, Domains & Business Email in India">
            <p class="help">Other pages use their own meta title with “ | <?= e(setting('site_name', 'SYSCOM')) ?>” appended when it fits.</p>
        </div>
        <div class="field">
            <label for="f-site_description">Default meta description <?= seo_counter('site_description', 70, 160) ?></label>
            <textarea id="f-site_description" name="site_description" rows="3" maxlength="300"<?= field_aria($errors, 'site_description') ?>><?= e($v('site_description')) ?></textarea>
            <?= field_error($errors, 'site_description') ?>
            <p class="help">Used on the home page and as the fallback description.</p>
        </div>
        <?= form_input('internal_links_max', 'Max automatic internal links per page', $v('internal_links_max'), $errors, ['type' => 'number', 'min' => 0, 'max' => 15]) ?>
        <button class="btn btn-primary" type="submit">Save SEO defaults</button>
    </section>
    <section class="panel">
        <h2>Canonical &amp; OpenGraph</h2>
        <?= meta_list([
            'Canonical base' => '<code>' . e(config('app.base_url')) . '</code>',
            'Canonical rule' => 'Self-referencing absolute URL on every page; per-page overrides in each editor',
            'Default OG image' => '<img class="current-image" src="' . e(url('/assets/images/og-default.png')) . '" alt="Default Open Graph image" width="220">',
            'OG type' => '<code>website</code>, or <code>article</code> on blog posts',
        ]) ?>
    </section>
</form>

<?php elseif ($tab === 'users'):
    $users = db_all('SELECT id, name, email, role, last_login_at, created_at FROM users ORDER BY role, name'); ?>
<div class="grid-2-1">
    <section class="panel">
        <h2>Admin users</h2>
        <div class="table-wrap"><table class="table-cards">
            <thead><tr><th>User</th><th>Role</th><th>Last login</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): $self = (int) $u['id'] === (int) $user['id']; ?>
                <tr>
                    <td class="primary"><strong><?= e($u['name']) ?></strong><?= $self ? ' <span class="badge badge-info">You</span>' : '' ?><span class="sub"><?= e($u['email']) ?></span></td>
                    <td data-label="Role"><?php if ($self): ?><?= status_badge($u['role']) ?><?php else: ?>
                        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="form" value="user_role"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <label class="visually-hidden" for="role-<?= (int) $u['id'] ?>">Role</label>
                            <select id="role-<?= (int) $u['id'] ?>" name="role"><option value="editor"<?= $u['role'] === 'editor' ? ' selected' : '' ?>>Editor</option><option value="admin"<?= $u['role'] === 'admin' ? ' selected' : '' ?>>Admin</option></select>
                            <button class="btn btn-outline btn-sm" type="submit">Update</button></form><?php endif; ?></td>
                    <td data-label="Last login" class="muted nowrap"><?= e(time_ago($u['last_login_at'])) ?></td>
                    <td><?= $self ? '' : action_button('', 'Delete', ['form' => 'user_delete', 'id' => $u['id']], 'btn-link danger', 'Delete the account ' . $u['email'] . '? They will lose access immediately.') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <h2>Permissions</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>Capability</th><th>Admin</th><th>Editor</th></tr></thead>
            <tbody>
            <?php foreach (['Content, landing pages, services, FAQs' => [1, 1], 'Keywords, internal links, SEO auditor' => [1, 1], 'Backlinks, distribution, leads' => [1, 1],
                'Robots.txt rules' => [1, 0], 'Site settings, users & security' => [1, 0], 'Change own password' => [1, 1]] as $cap => [$a, $ed]): ?>
                <tr><td><?= e($cap) ?></td><td><?= $a ? '✓' : '—' ?></td><td><?= $ed ? '✓' : '—' ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </section>
    <section class="panel">
        <h2>Add user</h2>
        <form method="post" novalidate>
            <?= csrf_field() ?><input type="hidden" name="form" value="user_add">
            <?= form_input('name', 'Name', $form === 'user_add' ? input('name') : '', $errors, ['required' => true, 'maxlength' => 100]) ?>
            <?= form_input('email', 'Email', $form === 'user_add' ? input('email') : '', $errors, ['type' => 'email', 'required' => true]) ?>
            <?= form_select('role', 'Role', $form === 'user_add' ? input('role') : 'editor', ['editor' => 'Editor', 'admin' => 'Admin'], $errors) ?>
            <?= form_input('password', 'Temporary password', '', $errors, ['type' => 'password', 'autocomplete' => 'new-password', 'minlength' => 10, 'help' => 'At least 10 characters with letters and numbers.']) ?>
            <button class="btn btn-primary" type="submit">Add user</button>
        </form>
    </section>
</div>

<?php elseif ($tab === 'security'):
    $failed24 = (int) db_value('SELECT COUNT(*) FROM login_attempts WHERE attempted_at >= NOW() - INTERVAL 24 HOUR');
    $demoPw = password_verify(DEMO_ADMIN_PASSWORD_CHECK, (string) db_value('SELECT password_hash FROM users WHERE email = ?', ['admin@syscom.local']));
    $checks = [
        ['Debug mode off', !config('app.debug'), 'Error details are hidden from visitors.', 'Set app.debug to false in production.'],
        ['HTTPS', is_https(), 'Pages and cookies are served securely.', 'Enable SSL and the HTTPS redirect in .htaccess.'],
        ['App secret changed', config('app.secret') !== 'change-this-secret-in-config-local', 'Used to sign form tokens and hash IPs.', 'Set a long random app.secret.'],
        ['Demo admin password replaced', !$demoPw, 'No account uses the published demo password.', 'Change the demo admin password or delete that account.'],
        ['CSRF protection', true, 'Every form and AJAX POST carries a verified token.', ''],
        ['Content-Security-Policy', true, 'No inline scripts or styles are allowed.', ''],
        ['Upload protection', is_file(APP_ROOT . '/assets/uploads/.htaccess'), 'Script execution disabled in uploads; files re-encoded.', 'Restore assets/uploads/.htaccess.'],
        ['SSRF guard', true, 'Auditor and link checker block private and metadata addresses.', ''],
    ]; ?>
<div class="grid-2">
    <section class="panel">
        <h2>Security status</h2>
        <ul class="issue-list">
            <?php foreach ($checks as [$label, $ok, $good, $fix]): ?>
                <li class="issue-<?= $ok ? 'pass' : 'warn' ?>"><span class="issue-icon" aria-hidden="true"><?= $ok ? '✓' : '!' ?></span>
                    <div><strong><?= e($label) ?></strong><br><span class="small muted"><?= e($ok ? $good : $fix) ?></span></div></li>
            <?php endforeach; ?>
        </ul>
    </section>
    <div>
        <section class="panel">
            <h2>Session settings</h2>
            <?= meta_list([
                'Idle timeout' => (int) (config('session.idle_timeout') / 60) . ' minutes',
                'Cookie flags' => 'HttpOnly · SameSite=Lax' . (is_https() ? ' · Secure' : ' · Secure when served over HTTPS'),
                'Session ID' => 'Regenerated on login and password change',
                'Strict mode' => 'Enabled (uninitialised session IDs are rejected)',
            ]) ?>
        </section>
        <section class="panel">
            <h2>Login protection</h2>
            <?= meta_list([
                'Policy' => LOGIN_MAX_ATTEMPTS . ' failed attempts per ' . LOGIN_WINDOW_MINUTES . ' minutes per IP or email, then temporarily locked',
                'Failed attempts (24 h)' => (string) $failed24,
                'Passwords' => 'bcrypt via password_hash(), rehashed automatically',
                'Lead form' => 'CSRF, honeypot, signed timing token, ' . LEAD_RATE_LIMIT . ' submissions per ' . LEAD_RATE_WINDOW_MINUTES . ' minutes',
            ]) ?>
        </section>
    </div>
</div>

<?php elseif ($tab === 'system'):
    $mysql = (string) db_value('SELECT VERSION()'); ?>
<div class="grid-2">
    <section class="panel">
        <h2>Sitemap &amp; robots</h2>
        <?= meta_list([
            'Sitemap' => '<a href="' . e(url('/sitemap.xml')) . '" target="_blank" rel="noopener">' . e(absolute_url('/sitemap.xml')) . '</a> · ' . count(sitemap_entries()) . ' URLs',
            'Robots.txt' => '<a href="' . e(url('/robots.txt')) . '" target="_blank" rel="noopener">' . e(absolute_url('/robots.txt')) . '</a>',
        ]) ?>
        <p><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/technical/sitemap.php')) ?>">Sitemap manager</a> <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/technical/robots.php')) ?>">Edit robots.txt</a></p>
    </section>
    <section class="panel">
        <h2>Audit settings</h2>
        <?= meta_list([
            'Request timeout' => (int) config('audit.timeout') . ' s (connect ' . (int) config('audit.connect_timeout') . ' s)',
            'Max page size' => round(config('audit.max_bytes') / 1048576, 1) . ' MB',
            'Max redirects' => (string) (int) config('audit.max_redirects'),
            'Allow own origin' => config('audit.allow_self') ? 'Yes (development)' : 'No',
        ]) ?>
        <p class="help">Change these in config/config.local.php.</p>
    </section>
    <section class="panel">
        <h2>Environment</h2>
        <?= meta_list([
            'PHP' => e(PHP_VERSION),
            'MySQL' => e($mysql),
            'Debug mode' => config('app.debug') ? '<span class="badge badge-warning">On – turn off in production</span>' : 'Off',
            'AI assistant' => ai_enabled() ? 'Enabled (' . e(config('ai.model')) . ')' : 'Not configured',
        ]) ?>
        <p class="help">API keys and database credentials are never displayed here.</p>
    </section>
</div>

<?php else: ?>
<div class="form-layout">
    <section class="panel">
        <h2>Change your password</h2>
        <form method="post" novalidate>
            <?= csrf_field() ?><input type="hidden" name="form" value="password">
            <?= form_input('current_password', 'Current password', '', $errors, ['type' => 'password', 'autocomplete' => 'current-password']) ?>
            <?= form_input('new_password', 'New password', '', $errors, ['type' => 'password', 'autocomplete' => 'new-password', 'minlength' => 10, 'help' => 'At least 10 characters with letters and numbers.']) ?>
            <?= form_input('confirm_password', 'Confirm new password', '', $errors, ['type' => 'password', 'autocomplete' => 'new-password']) ?>
            <button class="btn btn-primary" type="submit">Change password</button>
        </form>
    </section>
    <section class="panel">
        <h2>Your account</h2>
        <?= meta_list(['Name' => e($user['name']), 'Email' => e($user['email']), 'Role' => e(ucfirst($user['role']))]) ?>
    </section>
</div>
<?php endif; ?>
<?php admin_footer(); ?>
