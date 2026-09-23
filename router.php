<?php
/**
 * Router for PHP's built-in development server, mirroring .htaccess:
 *
 *   php -S 127.0.0.1:8080 router.php
 *
 * Not used under Apache.
 */
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Same private folders .htaccess denies.
if (preg_match('#^/(config|includes|modules|pages|database|storage|tests|docs|docker|admin/partials)(/|$)|^/(Dockerfile|render\.yaml)$|/\.|\.(sql|md|log|ini|sh)$#i', $path)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}
// Never execute PHP from the uploads folder.
if (preg_match('#^/assets/uploads/.*\.(php\d?|phtml|phar)$#i', $path)) {
    http_response_code(403);
    return true;
}

if ($path === '/robots.txt') {
    require __DIR__ . '/robots.php';
    return true;
}
if ($path === '/sitemap.xml') {
    require __DIR__ . '/sitemap.php';
    return true;
}

$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false; // let the built-in server send static files and run admin/api scripts
}
if ($path !== '/' && is_dir($file) && is_file(rtrim($file, '/') . '/index.php')) {
    if (!str_ends_with($path, '/')) {
        header('Location: ' . $path . '/', true, 301);
        return true;
    }
    $_SERVER['SCRIPT_NAME'] = rtrim($path, '/') . '/index.php';
    chdir(rtrim($file, '/'));
    require rtrim($file, '/') . '/index.php';
    return true;
}

require __DIR__ . '/index.php';
return true;
