<?php
/**
 * Front controller for every public URL. Apache (.htaccess) or router.php sends any request
 * that is not a real file here, and this file maps clean URLs to page templates.
 *
 *   /                    home
 *   /services            services index      /services/{slug}   service detail
 *   /blog                article index       /blog/{slug}       article
 *   /resources /faq /contact
 *   /{slug}              SEO landing page
 */
require __DIR__ . '/includes/bootstrap.php';

/** Words that can never be used as landing-page slugs because they are real routes. */
const RESERVED_SLUGS = ['services', 'blog', 'resources', 'faq', 'contact', 'admin', 'api', 'assets',
    'config', 'includes', 'modules', 'pages', 'database', 'storage', 'tests', 'docs', 'index', 'search',
    'sitemap', 'robots', 'login', 'logout', 'thank-you'];

$path = current_path();

// One URL per resource: drop trailing slashes, index.php and upper-case variants with a 301.
$normalized = preg_replace('#/index\.php$#', '/', $path);
$normalized = $normalized === '/' ? '/' : rtrim($normalized, '/');
if (preg_match('/[A-Z]/', $normalized) && preg_match('#^[A-Za-z0-9/-]+$#', $normalized)) {
    $normalized = strtolower($normalized);
}
if ($normalized !== $path && in_array(request_method(), ['GET', 'HEAD'], true)) {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    redirect($normalized . ($qs !== '' ? '?' . $qs : ''), 301);
}

$segments = $path === '/' ? [] : explode('/', trim($path, '/'));
$page = null;
$params = [];

switch (count($segments)) {
    case 0:
        $page = 'home';
        break;
    case 1:
        $static = ['services' => 'services', 'blog' => 'blog', 'resources' => 'resources', 'faq' => 'faq', 'contact' => 'contact'];
        if (isset($static[$segments[0]])) {
            $page = $static[$segments[0]];
        } elseif (is_valid_slug($segments[0]) && !in_array($segments[0], RESERVED_SLUGS, true)) {
            $page = 'landing';
            $params['slug'] = $segments[0];
        }
        break;
    case 2:
        $detail = ['services' => 'service', 'blog' => 'article'];
        if (isset($detail[$segments[0]]) && is_valid_slug($segments[1])) {
            $page = $detail[$segments[0]];
            $params['slug'] = $segments[1];
        }
        break;
}

if ($page === null) {
    abort(404);
}
if (!in_array(request_method(), ['GET', 'HEAD', 'POST'], true) || (is_post() && $page !== 'contact' && $page !== 'landing')) {
    header('Allow: GET, HEAD' . ($page === 'contact' || $page === 'landing' ? ', POST' : ''));
    abort(405);
}

echo render_partial(__DIR__ . '/pages/' . $page . '.php', $params);
