<?php
/**
 * SEO metadata engine. Every public page builds one $seo array and the header prints it.
 */

const SEO_TITLE_MAX = 60;
const SEO_DESCRIPTION_MAX = 160;

/**
 * Normalise page metadata with sensible, non-duplicated fallbacks.
 *
 * Keys: title, description, path (canonical), robots, type (og:type), image, schema (array of JSON-LD nodes),
 *       breadcrumbs ([label => path]), page (pagination number),
 *       canonical (absolute URL override), og_title, og_description (social overrides).
 */
function seo(array $page = []): array
{
    $siteName = setting('site_name', 'SYSCOM');
    $page += [
        'title'       => $siteName . ' – ' . setting('site_tagline', ''),
        'description' => setting('site_description', ''),
        'path'        => current_path(),
        'robots'      => 'index, follow',
        'type'        => 'website',
        'image'       => '/assets/images/og-default.png',
        'schema'      => [],
        'breadcrumbs' => [],
        'page'        => 1,
        'canonical'   => '',
        'og_title'    => '',
        'og_description' => '',
    ];

    $title = trim($page['title']);
    $canonicalPath = $page['path'];
    if ($page['page'] > 1) {
        // Paginated archives get their own title and self-referencing canonical to avoid duplicates.
        $title .= ' – Page ' . (int) $page['page'];
        $canonicalPath .= '?page=' . (int) $page['page'];
    }
    // Brand suffix only when it still fits in the space search engines usually display.
    $suffix = ' | ' . $siteName;
    if (stripos($title, $siteName) === false && mb_strlen($title . $suffix) <= SEO_TITLE_MAX + 5) {
        $title .= $suffix;
    }

    $description = str_limit(strip_tags((string) $page['description']), SEO_DESCRIPTION_MAX);

    if ($page['breadcrumbs']) {
        $page['schema'][] = schema_breadcrumbs($page['breadcrumbs']);
    }

    return [
        'title'       => $title,
        'description' => $description,
        'canonical'   => $page['canonical'] !== '' && is_http_url($page['canonical']) ? $page['canonical'] : absolute_url($canonicalPath),
        'og_title'    => trim((string) $page['og_title']) ?: $title,
        'og_description' => str_limit(trim((string) $page['og_description']) ?: $description, 200),
        'robots'      => $page['robots'],
        'type'        => $page['type'],
        'image'       => preg_match('#^https?://#', $page['image']) ? $page['image'] : absolute_url($page['image']),
        'schema'      => $page['schema'],
        'breadcrumbs' => $page['breadcrumbs'],
        'site_name'   => $siteName,
    ];
}

function seo_tags(array $seo): string
{
    $tags = [
        '<title>' . e($seo['title']) . '</title>',
        '<meta name="description" content="' . e($seo['description']) . '">',
        '<meta name="robots" content="' . e($seo['robots']) . '">',
        '<link rel="canonical" href="' . e($seo['canonical']) . '">',
        '<meta property="og:site_name" content="' . e($seo['site_name']) . '">',
        '<meta property="og:title" content="' . e($seo['og_title'] ?? $seo['title']) . '">',
        '<meta property="og:description" content="' . e($seo['og_description'] ?? $seo['description']) . '">',
        '<meta property="og:url" content="' . e($seo['canonical']) . '">',
        '<meta property="og:type" content="' . e($seo['type']) . '">',
        '<meta property="og:image" content="' . e($seo['image']) . '">',
        '<meta property="og:locale" content="en_IN">',
        '<meta name="twitter:card" content="summary_large_image">',
    ];
    if ($seo['schema']) {
        $tags[] = schema_script($seo['schema']);
    }
    return implode("\n    ", $tags);
}

/** Visible breadcrumb trail matching the BreadcrumbList schema. */
function breadcrumb_nav(array $crumbs): string
{
    if (!$crumbs) {
        return '';
    }
    $items = [];
    $last = array_key_last($crumbs);
    foreach ($crumbs as $label => $path) {
        $items[] = $label === $last
            ? '<li aria-current="page">' . e($label) . '</li>'
            : '<li><a href="' . e(url($path)) . '">' . e($label) . '</a></li>';
    }
    return '<nav class="breadcrumbs" aria-label="Breadcrumb"><ol>' . implode('', $items) . '</ol></nav>';
}

/** Length checks shared by the admin editor, content health report and auditor. */
function seo_length_status(string $text, int $min, int $max): string
{
    $len = mb_strlen(trim($text));
    if ($len === 0) {
        return 'missing';
    }
    return $len < $min ? 'short' : ($len > $max ? 'long' : 'ok');
}
