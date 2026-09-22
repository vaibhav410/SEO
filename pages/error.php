<?php
/**
 * Generic error page (403, 405, 419, 429, 500, 503). Self-contained: it must render even when the
 * database is down, so it does not use settings or the shared header/footer.
 * @var int $status @var string $message @var string $detail
 */
$title = error_title($status);
$messages = [
    403 => 'You do not have permission to view this page.',
    419 => 'Your session expired. Please go back, reload the page and try again.',
    503 => 'We are having trouble reaching our database. Please try again in a few minutes.',
];
$text = $message !== '' ? $message : ($messages[$status] ?? 'An unexpected error occurred. Our team has been notified.');
?>
<!doctype html>
<html lang="en-IN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>
<main id="main" class="error-page">
    <div class="container narrow">
        <p class="error-code" aria-hidden="true"><?= (int) $status ?></p>
        <h1><?= e($title) ?></h1>
        <p class="lead"><?= e($text) ?></p>
        <?php if ($detail !== ''): ?>
            <pre class="debug-detail"><?= e($detail) ?></pre>
        <?php endif; ?>
        <div class="btn-row">
            <a class="btn btn-primary" href="<?= e(url('/')) ?>">Go to homepage</a>
        </div>
    </div>
</main>
</body>
</html>
