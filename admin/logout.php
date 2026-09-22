<?php
define('ADMIN_PUBLIC', true);
require __DIR__ . '/_init.php';

// Logout is state-changing, so it only accepts POST with a valid CSRF token.
if (!is_post()) {
    redirect('/admin/dashboard.php');
}
csrf_verify();
logout_user();
start_secure_session();
flash('success', 'You have been signed out.');
redirect('/admin/login.php');
