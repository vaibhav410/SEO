<?php
/**
 * Central error handling: log details, show a friendly page, never leak stack traces
 * or credentials unless debug mode is on.
 */

class HttpException extends RuntimeException
{
    public function __construct(int $status, string $message = '')
    {
        parent::__construct($message, $status);
    }
}

function abort(int $status, string $message = ''): void
{
    throw new HttpException($status, $message);
}

function log_error(string $message): void
{
    $dir = APP_ROOT . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @error_log('[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, 3, $dir . '/app.log');
}

function register_error_handlers(): void
{
    error_reporting(E_ALL);
    ini_set('display_errors', config('app.debug') ? '1' : '0');
    ini_set('log_errors', '1');

    set_error_handler(function (int $severity, string $message, string $file, int $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    set_exception_handler('handle_exception');
}

function handle_exception(Throwable $e): void
{
    $status = 500;
    if ($e instanceof HttpException) {
        $status = $e->getCode();
    } elseif ($e instanceof PDOException) {
        $status = 503;
        log_error('Database: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    } else {
        log_error(get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
    }

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $wantsJson = str_starts_with(current_path(), '/api/')
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    if ($wantsJson) {
        json_response(['ok' => false, 'error' => error_title($status)], $status);
    }

    http_response_code($status);
    $detail = config('app.debug') && !($e instanceof HttpException)
        ? get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
        : '';

    render_error_page($status, $e instanceof HttpException ? $e->getMessage() : '', $detail);
    exit;
}

function error_title(int $status): string
{
    return [
        400 => 'Bad request', 403 => 'Access denied', 404 => 'Page not found',
        405 => 'Method not allowed', 419 => 'Session expired', 429 => 'Too many requests',
        503 => 'Service temporarily unavailable',
    ][$status] ?? 'Something went wrong';
}

function render_error_page(int $status, string $message = '', string $detail = ''): void
{
    // The 404 page queries services for helpful links; if the DB itself is down, fall back to plain HTML.
    try {
        $template = $status === 404 ? 'pages/404.php' : 'pages/error.php';
        echo render_partial(APP_ROOT . '/' . $template, compact('status', 'message', 'detail'));
    } catch (Throwable $inner) {
        log_error('Error page failed: ' . $inner->getMessage());
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex">'
            . '<title>' . e(error_title($status)) . '</title></head><body><h1>' . e(error_title($status)) . '</h1>'
            . '<p>Please try again in a few minutes.</p></body></html>';
    }
}
