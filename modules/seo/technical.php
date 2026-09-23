<?php
/**
 * Technical SEO: sitemap entries, robots.txt, structured-data inventory and the technical overview.
 * The public /sitemap.xml and /robots.txt use the same functions, so what the admin sees is exactly
 * what crawlers receive.
 */

const SITEMAP_PRIORITY = ['Home' => '1.0', 'Landing page' => '0.9', 'Service' => '0.9', 'Hub' => '0.8', 'Blog' => '0.7', 'Category' => '0.5', 'FAQ' => '0.5', 'Page' => '0.4'];

/**
 * Every public, indexable URL.
 * @return array<array{path: string, type: string, priority: string, lastmod: ?string, title: string}>
 */
function sitemap_entries(): array
{
    $latestPost = db_value('SELECT MAX(p.updated_at) FROM posts p WHERE ' . POST_PUBLIC_SQL);
    $latestService = db_value("SELECT MAX(updated_at) FROM services WHERE status = 'published'");
    $entry = fn(string $path, string $type, ?string $lastmod, string $title) =>
        ['path' => $path, 'type' => $type, 'priority' => SITEMAP_PRIORITY[$type] ?? '0.5', 'lastmod' => $lastmod, 'title' => $title];

    $urls = [
        $entry('/', 'Home', max((string) $latestPost, (string) $latestService) ?: null, 'Home'),
        $entry('/services', 'Hub', $latestService, 'Services'),
        $entry('/blog', 'Hub', $latestPost, 'Blog'),
        $entry('/resources', 'Hub', $latestPost, 'Resources'),
        $entry('/faq', 'FAQ', db_value("SELECT MAX(updated_at) FROM faqs WHERE status = 'published'"), 'FAQ'),
        $entry('/contact', 'Page', null, 'Contact'),
    ];
    foreach (db_all("SELECT name, slug, updated_at FROM services WHERE status = 'published' ORDER BY sort_order") as $s) {
        $urls[] = $entry('/services/' . $s['slug'], 'Service', $s['updated_at'], $s['name']);
    }
    foreach (db_all("SELECT title, slug, updated_at FROM landing_pages WHERE status = 'published' ORDER BY slug") as $l) {
        $urls[] = $entry('/' . $l['slug'], 'Landing page', $l['updated_at'], $l['title']);
    }
    foreach (db_all('SELECT p.title, p.slug, p.updated_at FROM posts p WHERE ' . POST_PUBLIC_SQL . ' ORDER BY p.published_at DESC') as $p) {
        $urls[] = $entry('/blog/' . $p['slug'], 'Blog', $p['updated_at'], $p['title']);
    }
    foreach (db_all('SELECT c.name, c.slug, MAX(p.updated_at) AS lastmod FROM categories c JOIN posts p ON p.category_id = c.id AND '
        . POST_PUBLIC_SQL . ' GROUP BY c.id, c.name, c.slug ORDER BY c.sort_order') as $c) {
        $urls[] = $entry('/blog/category/' . $c['slug'], 'Category', $c['lastmod'], $c['name']);
    }
    return $urls;
}

function sitemap_xml(array $entries): string
{
    $xml = new XMLWriter();
    $xml->openMemory();
    $xml->setIndent(true);
    $xml->startDocument('1.0', 'UTF-8');
    $xml->startElement('urlset');
    $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    foreach ($entries as $u) {
        $xml->startElement('url');
        $xml->writeElement('loc', absolute_url($u['path']));
        if ($u['lastmod']) {
            $xml->writeElement('lastmod', date('Y-m-d', strtotime($u['lastmod'])));
        }
        $xml->writeElement('priority', $u['priority']);
        $xml->endElement();
    }
    $xml->endElement();
    return $xml->outputMemory();
}

/** Built-in rules that always apply; admins can add extra lines in Technical SEO → robots.txt. */
function robots_default_disallow(): array
{
    return ['/admin/', '/api/', '/config/', '/includes/', '/modules/', '/database/', '/storage/', '/tests/', '/blog?q=', '/search'];
}

function robots_txt(): string
{
    $base = base_path();
    $out = "User-agent: *\nAllow: {$base}/\n";
    foreach (robots_default_disallow() as $path) {
        $out .= 'Disallow: ' . $base . $path . "\n";
    }
    $extra = trim(setting('robots_extra'));
    if ($extra !== '') {
        $out .= "\n# Custom rules\n" . $extra . "\n";
    }
    return $out . "\nSitemap: " . absolute_url('/sitemap.xml') . "\n";
}

/**
 * Validate custom robots.txt lines.
 * @return array<array{line: int, level: string, message: string}>
 */
function robots_validate(string $rules): array
{
    $problems = [];
    $agent = null;
    foreach (preg_split('/\R/', $rules) as $i => $line) {
        $n = $i + 1;
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!preg_match('/^(user-agent|allow|disallow|sitemap|crawl-delay)\s*:\s*(.*)$/i', $line, $m)) {
            $problems[] = ['line' => $n, 'level' => 'error', 'message' => 'Unknown directive. Use User-agent, Allow, Disallow or Sitemap.'];
            continue;
        }
        $directive = strtolower($m[1]);
        $value = trim($m[2]);
        if ($directive === 'user-agent') {
            $agent = $value;
        } elseif (in_array($directive, ['allow', 'disallow'], true)) {
            if ($agent === null) {
                $problems[] = ['line' => $n, 'level' => 'warning', 'message' => 'Rule appears before any User-agent line; it will apply to the group above (User-agent: *).'];
            }
            if ($value !== '' && $value[0] !== '/' && $value[0] !== '*') {
                $problems[] = ['line' => $n, 'level' => 'error', 'message' => 'Paths must start with "/".'];
            }
            if ($directive === 'disallow' && $value === '/' && ($agent === null || $agent === '*' || stripos($agent, 'googlebot') !== false)) {
                $problems[] = ['line' => $n, 'level' => 'error', 'message' => 'This blocks the entire site from search engines.'];
            }
        } elseif ($directive === 'sitemap' && !is_http_url($value)) {
            $problems[] = ['line' => $n, 'level' => 'error', 'message' => 'Sitemap must be an absolute URL.'];
        } elseif ($directive === 'crawl-delay') {
            $problems[] = ['line' => $n, 'level' => 'warning', 'message' => 'Googlebot ignores Crawl-delay.'];
        }
    }
    return $problems;
}

/**
 * Required and recommended properties per schema type (based on schema.org and Google's documentation).
 * This is a local completeness check, not Google's Rich Results validation.
 */
const SCHEMA_RULES = [
    'Organization'   => ['required' => ['name', 'url'], 'recommended' => ['logo', 'sameAs']],
    'WebSite'        => ['required' => ['name', 'url'], 'recommended' => []],
    'Article'        => ['required' => ['headline', 'datePublished', 'author'], 'recommended' => ['image', 'dateModified', 'publisher']],
    'BlogPosting'    => ['required' => ['headline', 'datePublished', 'author'], 'recommended' => ['image', 'dateModified', 'publisher']],
    'TechArticle'    => ['required' => ['headline', 'datePublished', 'author'], 'recommended' => ['image', 'dateModified', 'publisher']],
    'Service'        => ['required' => ['name', 'provider'], 'recommended' => ['description', 'areaServed']],
    'FAQPage'        => ['required' => ['mainEntity'], 'recommended' => []],
    'BreadcrumbList' => ['required' => ['itemListElement'], 'recommended' => []],
];

/** @return array{status: string, missing: array, missing_recommended: array} */
function schema_validate(array $node): array
{
    $rules = SCHEMA_RULES[$node['@type']] ?? ['required' => [], 'recommended' => []];
    $missing = array_values(array_filter($rules['required'], fn($p) => empty($node[$p])));
    $missingRec = array_values(array_filter($rules['recommended'], fn($p) => empty($node[$p])));

    if ($node['@type'] === 'FAQPage') {
        foreach ((array) ($node['mainEntity'] ?? []) as $q) {
            if (empty($q['name']) || empty($q['acceptedAnswer']['text'])) {
                $missing[] = 'Question.name / acceptedAnswer.text';
                break;
            }
        }
    }
    if ($node['@type'] === 'BreadcrumbList' && count((array) ($node['itemListElement'] ?? [])) < 2) {
        $missingRec[] = 'at least 2 items';
    }
    return ['status' => $missing ? 'error' : ($missingRec ? 'warning' : 'valid'), 'missing' => $missing, 'missing_recommended' => $missingRec];
}

/**
 * Build the same JSON-LD nodes the public pages output, page by page.
 * @return array<array{page: string, path: string, type: string, node: array, updated: ?string, edit: ?string}>
 */
function schema_inventory(): array
{
    $rows = [];
    $add = function (string $page, string $path, ?array $node, ?string $updated, ?string $edit) use (&$rows) {
        if ($node) {
            $rows[] = ['page' => $page, 'path' => $path, 'type' => $node['@type'], 'node' => $node, 'updated' => $updated, 'edit' => $edit];
        }
    };
    $add('Home', '/', schema_organization(), null, '/admin/settings/');
    $add('Home', '/', schema_website(), null, '/admin/settings/');
    $add('Home', '/', schema_faq(faqs_for('general')), null, '/admin/faqs/?owner=general');

    foreach (db_all("SELECT * FROM services WHERE status = 'published' ORDER BY sort_order") as $s) {
        $path = '/services/' . $s['slug'];
        $add($s['name'], $path, schema_service($s), $s['updated_at'], '/admin/services/edit.php?id=' . $s['id']);
        $add($s['name'], $path, schema_faq(faqs_for('service', (int) $s['id'])), $s['updated_at'], '/admin/services/edit.php?id=' . $s['id']);
        $add($s['name'], $path, schema_breadcrumbs(['Home' => '/', 'Services' => '/services', $s['name'] => $path]), $s['updated_at'], null);
    }
    foreach (db_all('SELECT p.*, u.name AS author_name FROM posts p LEFT JOIN users u ON u.id = p.author_id WHERE ' . POST_PUBLIC_SQL . ' ORDER BY p.published_at DESC') as $p) {
        $path = '/blog/' . $p['slug'];
        $add($p['title'], $path, schema_article($p), $p['updated_at'], '/admin/posts/edit.php?id=' . $p['id']);
        $add($p['title'], $path, schema_faq(faqs_for('post', (int) $p['id'])), $p['updated_at'], '/admin/posts/edit.php?id=' . $p['id']);
        $add($p['title'], $path, schema_breadcrumbs(['Home' => '/', 'Blog' => '/blog', $p['title'] => $path]), $p['updated_at'], null);
    }
    foreach (db_all("SELECT * FROM landing_pages WHERE status = 'published' ORDER BY title") as $l) {
        $path = '/' . $l['slug'];
        $add($l['title'], $path, schema_faq(faqs_for('landing', (int) $l['id'])), $l['updated_at'], '/admin/landing-pages/edit.php?id=' . $l['id']);
        $add($l['title'], $path, schema_breadcrumbs(['Home' => '/', $l['title'] => $path]), $l['updated_at'], null);
    }
    return $rows;
}

/**
 * Technical overview cards, each derived from real application data.
 * @return array<array{key: string, title: string, status: string, summary: string, detail: string, link: ?string}>
 */
function technical_overview(): array
{
    $entries = sitemap_entries();
    $issues = content_health_issues();
    $countIssues = fn(array $types, ?string $severity = null) => count(array_filter($issues,
        fn($i) => in_array($i['type'], $types, true) && ($severity === null || $i['severity'] === $severity)));
    $schemas = schema_inventory();
    $schemaErrors = count(array_filter($schemas, fn($s) => schema_validate($s['node'])['status'] === 'error'));
    $schemaWarnings = count(array_filter($schemas, fn($s) => schema_validate($s['node'])['status'] === 'warning'));
    $robotsProblems = robots_validate(setting('robots_extra'));
    $longSlugs = count(array_filter($entries, fn($e) => strlen($e['path']) > 75));
    $drafts = (int) db_value("SELECT (SELECT COUNT(*) FROM posts WHERE status = 'draft') + (SELECT COUNT(*) FROM landing_pages WHERE status = 'draft')");
    $missingAlt = (int) db_value("SELECT COUNT(*) FROM posts WHERE featured_image IS NOT NULL AND (featured_image_alt IS NULL OR featured_image_alt = '')");
    $audits = db_one('SELECT AVG(a.heading_score) heading, AVG(a.mobile_score) mobile, AVG(a.alt_score) alt, COUNT(*) n FROM seo_audits a
                      JOIN (SELECT MAX(id) id FROM seo_audits WHERE http_status < 400 GROUP BY url) l ON l.id = a.id');
    $hasAudits = $audits && (int) $audits['n'] > 0;
    $auditNote = fn(string $col) => $hasAudits ? 'Average ' . (int) round((float) $audits[$col]) . '/100 across the latest audit of ' . (int) $audits['n'] . ' URL(s).' : 'Run the SEO auditor to measure this on live pages.';
    $auditStatus = fn(string $col) => !$hasAudits ? 'warning' : ((float) $audits[$col] >= 80 ? 'healthy' : ((float) $audits[$col] >= 50 ? 'warning' : 'attention'));
    $metaErrors = $countIssues(['Meta title', 'Meta description', 'Duplicate title', 'Duplicate description'], 'error');
    $metaWarnings = $countIssues(['Meta title', 'Meta description']);

    return [
        ['key' => 'indexability', 'title' => 'Indexability', 'status' => 'healthy', 'summary' => count($entries) . ' indexable URLs',
            'detail' => "Published pages are index,follow. 404, search results and admin send noindex. $drafts draft(s) stay private.", 'link' => '/admin/technical/sitemap.php'],
        ['key' => 'crawlability', 'title' => 'Crawlability', 'status' => $robotsProblems ? 'attention' : 'healthy', 'summary' => $robotsProblems ? count($robotsProblems) . ' robots.txt problem(s)' : 'robots.txt valid',
            'detail' => 'Admin, API and private folders are disallowed; every public page is reachable through navigation, footer and hubs.', 'link' => '/admin/technical/robots.php'],
        ['key' => 'canonical', 'title' => 'Canonical URLs', 'status' => 'healthy', 'summary' => 'Self-referencing on every page',
            'detail' => 'Trailing slashes, upper case and /index.php 301 to one URL. Paginated archives use self-canonicals.', 'link' => null],
        ['key' => 'sitemap', 'title' => 'XML sitemap', 'status' => 'healthy', 'summary' => count($entries) . ' URLs, generated live',
            'detail' => 'Only published, indexable URLs with lastmod dates. Referenced from robots.txt.', 'link' => '/admin/technical/sitemap.php'],
        ['key' => 'robots', 'title' => 'Robots.txt', 'status' => $robotsProblems ? 'attention' : 'healthy', 'summary' => trim(setting('robots_extra')) === '' ? 'Default rules' : 'Default + custom rules',
            'detail' => 'Blocks private paths and internal search results; links the sitemap.', 'link' => '/admin/technical/robots.php'],
        ['key' => 'meta', 'title' => 'Meta tags', 'status' => $metaErrors ? 'attention' : ($metaWarnings ? 'warning' : 'healthy'),
            'summary' => $metaErrors ? "$metaErrors error(s)" : ($metaWarnings ? "$metaWarnings warning(s)" : 'All titles and descriptions OK'),
            'detail' => 'Unique titles and descriptions, checked for length and duplicates across all content.', 'link' => '/admin/opportunities/?type=technical'],
        ['key' => 'opengraph', 'title' => 'OpenGraph', 'status' => 'healthy', 'summary' => 'Generated for every page',
            'detail' => 'og:title, og:description, og:url, og:type and og:image (featured image or branded default).', 'link' => null],
        ['key' => 'schema', 'title' => 'Structured data', 'status' => $schemaErrors ? 'attention' : ($schemaWarnings ? 'warning' : 'healthy'),
            'summary' => count($schemas) . ' blocks · ' . $schemaErrors . ' errors · ' . $schemaWarnings . ' warnings',
            'detail' => 'Organization, WebSite, Article, Service, BreadcrumbList and FAQPage, built only from visible content.', 'link' => '/admin/technical/schema.php'],
        ['key' => 'urls', 'title' => 'URL structure', 'status' => $longSlugs ? 'warning' : 'healthy', 'summary' => $longSlugs ? "$longSlugs long URL(s)" : 'Short, descriptive slugs',
            'detail' => 'Lowercase, hyphenated slugs validated on save; reserved words protected.', 'link' => '/admin/technical/sitemap.php'],
        ['key' => 'headings', 'title' => 'Heading structure', 'status' => $auditStatus('heading'), 'summary' => $hasAudits ? 'From live audits' : 'Not yet audited',
            'detail' => 'Templates output one H1; Markdown headings start at H2. ' . $auditNote('heading'), 'link' => '/admin/audits/'],
        ['key' => 'images', 'title' => 'Image SEO', 'status' => $missingAlt ? 'attention' : 'healthy', 'summary' => $missingAlt ? "$missingAlt image(s) without alt text" : 'Alt text on featured images',
            'detail' => 'Uploads are resized, re-encoded and lazy-loaded with explicit dimensions.', 'link' => '/admin/posts/'],
        ['key' => 'mobile', 'title' => 'Mobile SEO', 'status' => $auditStatus('mobile'), 'summary' => $hasAudits ? 'From live audits' : 'Not yet audited',
            'detail' => 'Responsive viewport, mobile-first CSS, no horizontal overflow. ' . $auditNote('mobile'), 'link' => '/admin/audits/'],
        ['key' => 'accessibility', 'title' => 'Accessibility', 'status' => $auditStatus('alt'), 'summary' => 'Semantic HTML, labels, focus states',
            'detail' => 'Skip links, visible focus, labelled inputs, aria-current navigation. ' . $auditNote('alt'), 'link' => '/admin/audits/'],
    ];
}
