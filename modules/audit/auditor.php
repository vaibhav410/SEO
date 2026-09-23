<?php
/**
 * SEO auditor: fetch a page safely, analyse its on-page SEO, score it and store the result.
 *
 * The score is an INTERNAL 0-100 diagnostic of on-page best practice. It is not a Google metric
 * and does not predict rankings.
 */

/** Weight of each check in the overall score (sums to 100). */
const AUDIT_WEIGHTS = [
    'title' => 15, 'description' => 15, 'h1' => 10, 'heading' => 5, 'canonical' => 10, 'robots' => 5,
    'og' => 10, 'schema' => 10, 'alt' => 10, 'internal_link' => 5, 'mobile' => 5,
];

const AUDIT_LABELS = [
    'title' => 'Title tag', 'description' => 'Meta description', 'h1' => 'H1 heading', 'heading' => 'Heading structure',
    'canonical' => 'Canonical URL', 'robots' => 'Indexability', 'og' => 'Open Graph', 'schema' => 'Structured data',
    'alt' => 'Image alt text', 'internal_link' => 'Internal links', 'mobile' => 'Mobile & language', 'http' => 'HTTP',
];

/**
 * Fetch a URL with SSRF protection on every hop.
 * @return array{ok: bool, error: ?string, status: ?int, final_url: string, html: string, ms: int, headers: array, redirects: array}
 */
function audit_fetch(string $url): array
{
    $result = ['ok' => false, 'error' => null, 'status' => null, 'final_url' => $url, 'html' => '', 'ms' => 0, 'headers' => [], 'redirects' => []];
    $current = $url;
    $started = microtime(true);
    $maxBytes = (int) config('audit.max_bytes');

    for ($hop = 0; $hop <= (int) config('audit.max_redirects'); $hop++) {
        $check = url_guard_check($current);
        if (!$check['ok']) {
            $result['error'] = $hop === 0 ? $check['error'] : 'Redirected to a blocked address: ' . $check['error'];
            return $result;
        }

        $headers = [];
        $body = '';
        $tooBig = false;
        $ch = curl_init($current);
        $pinIp = str_contains((string) $check['ip'], ':') ? '[' . $check['ip'] . ']' : $check['ip'];
        curl_setopt_array($ch, [
            CURLOPT_RESOLVE        => $check['ip'] ? [$check['host'] . ':' . $check['port'] . ':' . $pinIp] : [],
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => (int) config('audit.connect_timeout'),
            CURLOPT_TIMEOUT        => (int) config('audit.timeout'),
            CURLOPT_USERAGENT      => 'SYSCOM-GrowthHub-SEO-Auditor/1.0 (+' . absolute_url('/') . ')',
            CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.5', 'Accept-Language: en-IN,en;q=0.8'],
            CURLOPT_ENCODING       => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$headers) {
                if (str_contains($line, ':')) {
                    [$k, $v] = explode(':', $line, 2);
                    $headers[strtolower(trim($k))] = trim($v);
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use (&$body, &$tooBig, $maxBytes) {
                $body .= $chunk;
                if (strlen($body) > $maxBytes) {
                    $tooBig = true;
                    return 0; // abort transfer
                }
                return strlen($chunk);
            },
        ]);
        if (defined('CURLSSLOPT_NATIVE_CA')) {
            // Use the operating system certificate store (fixes SSL errors on Windows/XAMPP).
            curl_setopt($ch, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
        }

        curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($tooBig) {
            $result['error'] = 'The page is larger than ' . round($maxBytes / 1048576, 1) . ' MB and was not analysed.';
            return $result;
        }
        if ($errno) {
            $result['error'] = match (true) {
                $errno === CURLE_OPERATION_TIMEDOUT => 'The request timed out after ' . config('audit.timeout') . ' seconds.',
                in_array($errno, [CURLE_SSL_CONNECT_ERROR, CURLE_PEER_FAILED_VERIFICATION, 60, 77], true) => 'SSL/TLS error: ' . $error,
                $errno === CURLE_COULDNT_CONNECT => 'Could not connect to the server.',
                $errno === CURLE_COULDNT_RESOLVE_HOST => 'The domain could not be resolved.',
                default => 'Request failed: ' . $error,
            };
            return $result;
        }

        if ($status >= 300 && $status < 400 && !empty($headers['location'])) {
            $next = url_resolve($current, $headers['location']);
            $result['redirects'][] = ['from' => $current, 'to' => $next, 'status' => $status];
            $current = $next;
            continue;
        }

        $result['ms'] = (int) round((microtime(true) - $started) * 1000);
        $result['status'] = $status;
        $result['final_url'] = $current;
        $result['headers'] = $headers;
        $result['html'] = $body;
        $result['ok'] = true;

        $type = $headers['content-type'] ?? '';
        if ($type !== '' && !str_contains($type, 'html')) {
            $result['ok'] = false;
            $result['error'] = 'The URL did not return an HTML page (' . $type . ').';
        }
        return $result;
    }

    $result['error'] = 'Too many redirects (more than ' . config('audit.max_redirects') . ').';
    return $result;
}

/** Resolve a (possibly relative) Location header against the current URL. */
function url_resolve(string $base, string $relative): string
{
    if (preg_match('#^https?://#i', $relative)) {
        return $relative;
    }
    $b = parse_url($base);
    $origin = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
    if (str_starts_with($relative, '//')) {
        return $b['scheme'] . ':' . $relative;
    }
    if (str_starts_with($relative, '/')) {
        return $origin . $relative;
    }
    $dir = preg_replace('#/[^/]*$#', '/', $b['path'] ?? '/');
    return $origin . $dir . $relative;
}

/**
 * Analyse HTML and return per-check scores (0-100), issues and stats. Pure function (no I/O).
 */
function audit_analyze(string $html, string $pageUrl, array $headers = []): array
{
    $doc = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . ($html !== '' ? $html : '<html></html>'), LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $x = new DOMXPath($doc);

    $issues = [];
    $scores = [];
    $add = function (string $check, string $status, string $message) use (&$issues) {
        $issues[] = ['check' => $check, 'status' => $status, 'message' => $message];
    };
    $text = fn(string $q) => trim(preg_replace('/\s+/', ' ', (string) $x->evaluate("string($q)")));

    // Title ---------------------------------------------------------------
    $titles = $x->query('//head/title | //title');
    $title = $text('//title');
    $len = mb_strlen($title);
    if ($title === '') {
        $scores['title'] = 0;
        $add('title', 'fail', 'The page has no <title> tag.');
    } else {
        $scores['title'] = ($len >= 30 && $len <= 60) ? 100 : (($len >= 15 && $len <= 70) ? 60 : 30);
        $scores['title'] === 100
            ? $add('title', 'pass', "Title is $len characters: “" . str_limit($title, 80) . '”')
            : $add('title', 'warn', "Title is $len characters; aim for roughly 30–60 so it is descriptive but not truncated.");
        if ($titles->length > 1) {
            $scores['title'] = max(0, $scores['title'] - 30);
            $add('title', 'warn', 'The page has ' . $titles->length . ' <title> tags; keep exactly one.');
        }
    }

    // Meta description ----------------------------------------------------
    $desc = $text('//meta[translate(@name,"DESCRIPTION","description")="description"]/@content');
    $len = mb_strlen($desc);
    if ($desc === '') {
        $scores['description'] = 0;
        $add('description', 'fail', 'Missing meta description. Search engines may pick a random snippet instead.');
    } else {
        $scores['description'] = ($len >= 70 && $len <= 160) ? 100 : (($len >= 50 && $len <= 200) ? 60 : 30);
        $scores['description'] === 100
            ? $add('description', 'pass', "Meta description is $len characters.")
            : $add('description', 'warn', "Meta description is $len characters; " . ($len < 70 ? 'it is too short to summarise the page well.' : 'it will likely be truncated (aim for 70–160).'));
    }

    // H1 ------------------------------------------------------------------
    $h1s = $x->query('//body//h1');
    if ($h1s->length === 1) {
        $scores['h1'] = 100;
        $add('h1', 'pass', 'One H1: “' . str_limit(trim($h1s->item(0)->textContent), 80) . '”');
    } elseif ($h1s->length === 0) {
        $scores['h1'] = 0;
        $add('h1', 'fail', 'No H1 heading found. Add one clear main heading.');
    } else {
        $scores['h1'] = 50;
        $add('h1', 'warn', $h1s->length . ' H1 headings found. Use one H1 for the main topic and H2/H3 for sections.');
    }

    // Heading hierarchy ---------------------------------------------------
    $levels = [];
    foreach ($x->query('//body//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6]') as $h) {
        $levels[] = (int) substr($h->nodeName, 1);
    }
    $skips = 0;
    for ($i = 1; $i < count($levels); $i++) {
        if ($levels[$i] > $levels[$i - 1] + 1) {
            $skips++;
        }
    }
    $h2count = count(array_filter($levels, fn($l) => $l === 2));
    if ($h2count === 0) {
        $scores['heading'] = 50;
        $add('heading', 'warn', 'No H2 subheadings. Break longer content into sections with H2s.');
    } elseif ($skips > 0) {
        $scores['heading'] = 70;
        $add('heading', 'warn', "Heading levels are skipped $skips time(s) (e.g. H2 followed by H4).");
    } else {
        $scores['heading'] = 100;
        $add('heading', 'pass', "Logical heading structure ($h2count H2 sections).");
    }

    // Canonical -----------------------------------------------------------
    $canonicals = $x->query('//link[translate(@rel,"CANONICAL","canonical")="canonical"]/@href');
    if ($canonicals->length === 0) {
        $scores['canonical'] = 0;
        $add('canonical', 'fail', 'No canonical link. Add rel="canonical" to signal the preferred URL.');
    } elseif ($canonicals->length > 1) {
        $scores['canonical'] = 40;
        $add('canonical', 'warn', 'Multiple canonical tags found; search engines may ignore all of them.');
    } else {
        $href = trim($canonicals->item(0)->nodeValue);
        if (!preg_match('#^https?://#i', $href)) {
            $scores['canonical'] = 60;
            $add('canonical', 'warn', 'Canonical URL is relative; use an absolute URL.');
        } else {
            $scores['canonical'] = 100;
            $same = rtrim(strtolower(strtok($href, '#')), '/') === rtrim(strtolower(strtok($pageUrl, '#')), '/');
            $add('canonical', $same ? 'pass' : 'info', $same ? 'Self-referencing canonical URL.' : 'Canonical points to a different URL: ' . $href);
        }
    }

    // Robots / indexability -----------------------------------------------
    $robots = strtolower($text('//meta[translate(@name,"ROBOTS","robots")="robots"]/@content') . ' ' . ($headers['x-robots-tag'] ?? ''));
    if (str_contains($robots, 'noindex')) {
        $scores['robots'] = 0;
        $add('robots', 'fail', 'The page is set to noindex, so it will not appear in search results.');
    } else {
        $scores['robots'] = 100;
        $add('robots', 'pass', 'Page is indexable (no noindex directive).');
    }

    // Open Graph ----------------------------------------------------------
    $ogFound = [];
    foreach (['og:title', 'og:description', 'og:image', 'og:url'] as $prop) {
        if ($text('//meta[@property="' . $prop . '"]/@content') !== '') {
            $ogFound[] = $prop;
        }
    }
    $scores['og'] = count($ogFound) * 25;
    $missing = array_diff(['og:title', 'og:description', 'og:image', 'og:url'], $ogFound);
    $missing
        ? $add('og', $ogFound ? 'warn' : 'fail', 'Missing Open Graph tags: ' . implode(', ', $missing) . '.')
        : $add('og', 'pass', 'Open Graph title, description, image and URL are set.');

    // Structured data -----------------------------------------------------
    $types = [];
    $invalid = 0;
    foreach ($x->query('//script[@type="application/ld+json"]') as $script) {
        $data = json_decode(trim($script->textContent), true);
        if (!is_array($data)) {
            $invalid++;
            continue;
        }
        $nodes = isset($data['@graph']) ? $data['@graph'] : (array_is_list($data) ? $data : [$data]);
        foreach ($nodes as $node) {
            if (isset($node['@type'])) {
                $types = array_merge($types, (array) $node['@type']);
            }
        }
    }
    $microdata = $x->query('//*[@itemscope]')->length;
    if ($invalid > 0) {
        $scores['schema'] = 40;
        $add('schema', 'warn', "$invalid JSON-LD block(s) contain invalid JSON.");
    } elseif ($types || $microdata) {
        $scores['schema'] = 100;
        $add('schema', 'pass', 'Structured data found: ' . ($types ? implode(', ', array_unique($types)) : "$microdata microdata item(s)") . '.');
    } else {
        $scores['schema'] = 0;
        $add('schema', 'fail', 'No structured data (JSON-LD) found.');
    }

    // Image alt text ------------------------------------------------------
    $images = $x->query('//body//img');
    $noAlt = $x->query('//body//img[not(@alt)]')->length;
    if ($images->length === 0) {
        $scores['alt'] = 100;
        $add('alt', 'info', 'No images on the page.');
    } else {
        $scores['alt'] = (int) round(100 * ($images->length - $noAlt) / $images->length);
        $noAlt === 0
            ? $add('alt', 'pass', $images->length === 1 ? 'The image has an alt attribute.' : 'All ' . $images->length . ' images have alt attributes.')
            : $add('alt', 'warn', "$noAlt of {$images->length} images are missing alt text.");
    }

    // Links ---------------------------------------------------------------
    $pageHost = strtolower((string) parse_url($pageUrl, PHP_URL_HOST));
    $internal = $external = $nofollow = 0;
    foreach ($x->query('//body//a[@href]') as $a) {
        $href = trim($a->getAttribute('href'));
        if ($href === '' || preg_match('#^(\#|mailto:|tel:|javascript:)#i', $href)) {
            continue;
        }
        $host = strtolower((string) parse_url($href, PHP_URL_HOST));
        if ($host === '' || $host === $pageHost || preg_replace('/^www\./', '', $host) === preg_replace('/^www\./', '', $pageHost)) {
            $internal++;
        } else {
            $external++;
        }
        if (str_contains(strtolower($a->getAttribute('rel')), 'nofollow')) {
            $nofollow++;
        }
    }
    $scores['internal_link'] = $internal >= 3 ? 100 : ($internal > 0 ? 60 : 20);
    $internal >= 3
        ? $add('internal_link', 'pass', "$internal internal links and $external external links.")
        : $add('internal_link', 'warn', "Only $internal internal link(s). Link to related pages to help users and crawlers.");

    // Mobile & language ---------------------------------------------------
    $viewport = strtolower($text('//meta[translate(@name,"VIEWPORT","viewport")="viewport"]/@content'));
    $lang = $text('//html/@lang');
    $scores['mobile'] = (str_contains($viewport, 'width=device-width') ? 70 : 0) + ($lang !== '' ? 30 : 0);
    if (!str_contains($viewport, 'width=device-width')) {
        $add('mobile', 'fail', 'No responsive viewport meta tag; the page may not display well on phones.');
    }
    $lang === ''
        ? $add('mobile', 'warn', 'The <html> element has no lang attribute.')
        : $add('mobile', 'pass', "Responsive viewport set; language: $lang.");

    // HTTPS ---------------------------------------------------------------
    if (str_starts_with(strtolower($pageUrl), 'http://')) {
        $add('http', 'warn', 'The page is served over HTTP. Move to HTTPS.');
    }

    $overall = 0;
    foreach (AUDIT_WEIGHTS as $check => $weight) {
        $overall += ($scores[$check] ?? 0) * $weight;
    }

    $bodyText = '';
    $body = $x->query('//body')->item(0);
    if ($body) {
        foreach ($x->query('.//script | .//style | .//noscript', $body) as $node) {
            $node->parentNode->removeChild($node);
        }
        $bodyText = trim(preg_replace('/\s+/', ' ', $body->textContent));
    }

    return [
        'scores'  => $scores,
        'overall' => (int) round($overall / 100),
        'issues'  => $issues,
        'stats'   => [
            'title' => $title, 'description' => $desc, 'words' => $bodyText === '' ? 0 : count(preg_split('/\s+/u', $bodyText)),
            'internal_links' => $internal, 'external_links' => $external, 'nofollow_links' => $nofollow,
            'images' => $images->length, 'images_missing_alt' => $noAlt, 'headings' => array_count_values(array_map(fn($l) => 'h' . $l, $levels)),
            'schema_types' => array_values(array_unique($types)), 'bytes' => strlen($html),
        ],
    ];
}

/**
 * Full workflow: fetch -> analyse -> save. Returns the saved audit id, or an error message.
 * @return array{id: ?int, error: ?string}
 */
function audit_run(string $url, ?int $userId): array
{
    $check = url_guard_check($url);
    if (!$check['ok']) {
        return ['id' => null, 'error' => $check['error']];
    }

    $fetch = audit_fetch($url);
    if ($fetch['status'] === null) {
        return ['id' => null, 'error' => $fetch['error'] ?? 'The page could not be fetched.'];
    }

    $row = ['url' => mb_substr($url, 0, 500), 'final_url' => mb_substr($fetch['final_url'], 0, 500),
        'http_status' => $fetch['status'], 'response_ms' => $fetch['ms'], 'created_by' => $userId];

    if ($fetch['status'] >= 400 || !$fetch['ok']) {
        $message = $fetch['status'] >= 400 ? "The page returned HTTP {$fetch['status']}." : $fetch['error'];
        $row += ['overall_score' => 0, 'issues' => json_encode([['check' => 'http', 'status' => 'fail', 'message' => $message]]),
            'stats' => json_encode(['redirects' => $fetch['redirects']])];
        return ['id' => db_insert('seo_audits', $row), 'error' => null];
    }

    $analysis = audit_analyze($fetch['html'], $fetch['final_url'], $fetch['headers']);
    foreach ($fetch['redirects'] as $r) {
        array_unshift($analysis['issues'], ['check' => 'http', 'status' => 'info', 'message' => "Redirect {$r['status']}: {$r['from']} → {$r['to']}"]);
    }
    foreach ($analysis['scores'] as $check => $score) {
        $row[$check . '_score'] = $score;
    }
    $row['overall_score'] = $analysis['overall'];
    $row['issues'] = json_encode($analysis['issues'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $row['stats'] = json_encode($analysis['stats'] + ['redirects' => $fetch['redirects']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return ['id' => db_insert('seo_audits', $row), 'error' => null];
}

function audit_find(int $id): ?array
{
    $audit = db_one('SELECT * FROM seo_audits WHERE id = ?', [$id]);
    if ($audit) {
        $audit['issues'] = json_decode($audit['issues'], true) ?: [];
        $audit['stats'] = json_decode((string) $audit['stats'], true) ?: [];
    }
    return $audit;
}

function audits_recent(int $limit = 20, int $offset = 0): array
{
    return db_all('SELECT id, url, final_url, http_status, overall_score, response_ms, created_at FROM seo_audits
                   ORDER BY created_at DESC, id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset);
}

/** Recommended fix for each check, shown when it does not pass. */
const AUDIT_FIXES = [
    'title' => 'Write one unique, descriptive <title> of roughly 30–60 characters that includes the page topic.',
    'description' => 'Add a meta description of 70–160 characters that summarises the page and invites the click.',
    'h1' => 'Use exactly one H1 that states the main topic; demote other H1s to H2/H3.',
    'heading' => 'Break content into H2 sections and do not skip levels (H2 → H3, not H2 → H4).',
    'canonical' => 'Add <link rel="canonical" href="https://…"> with the absolute preferred URL of this page.',
    'robots' => 'Remove the noindex directive (meta robots or X-Robots-Tag) if this page should appear in search.',
    'og' => 'Add og:title, og:description, og:image (1200×630) and og:url so shared links look right.',
    'schema' => 'Add JSON-LD structured data that describes visible content (Organization, Article, Service, FAQPage, BreadcrumbList).',
    'alt' => 'Give every meaningful image descriptive alt text; use alt="" for purely decorative images.',
    'internal_link' => 'Link to at least three relevant pages on the same site with descriptive anchor text.',
    'external' => 'Where helpful, cite authoritative sources; external links are informational and do not affect this score.',
    'viewport' => 'Add <meta name="viewport" content="width=device-width, initial-scale=1"> and a responsive layout.',
    'lang' => 'Declare the page language, e.g. <html lang="en-IN">.',
    'http' => 'Serve the page over HTTPS and 301-redirect HTTP to HTTPS.',
];

/**
 * One row per check for the audit report UI.
 * @return array<array{key: string, label: string, status: string, finding: string, fix: ?string, score: ?int}>
 */
function audit_report_rows(array $audit): array
{
    $byCheck = [];
    foreach ($audit['issues'] as $i) {
        $byCheck[$i['check']][] = $i;
    }
    $stats = $audit['stats'] ?? [];
    $worst = function (array $items): string {
        $order = ['fail' => 0, 'warn' => 1, 'info' => 2, 'pass' => 3];
        usort($items, fn($a, $b) => ($order[$a['status']] ?? 9) <=> ($order[$b['status']] ?? 9));
        return $items[0]['status'] ?? 'info';
    };
    $row = function (string $key, string $label, array $items, ?int $score) use ($worst) {
        $status = $items ? $worst($items) : 'info';
        return ['key' => $key, 'label' => $label, 'status' => $status === 'info' ? 'pass' : $status,
            'finding' => implode(' ', array_column($items, 'message')) ?: '—',
            'fix' => in_array($status, ['warn', 'fail'], true) ? (AUDIT_FIXES[$key] ?? null) : null, 'score' => $score];
    };

    $mobile = $byCheck['mobile'] ?? [];
    $viewport = array_values(array_filter($mobile, fn($i) => str_contains($i['message'], 'viewport')));
    $lang = array_values(array_filter($mobile, fn($i) => str_contains($i['message'], 'lang')));
    if (!$viewport && $mobile && (int) $audit['mobile_score'] >= 70) {
        $viewport = [['status' => 'pass', 'message' => 'Responsive viewport meta tag present.']];
    }
    if (!$lang && $mobile && preg_match('/language: (\S+)/', implode(' ', array_column($mobile, 'message')), $m)) {
        $lang = [['status' => 'pass', 'message' => 'Language declared: ' . rtrim($m[1], '.') . '.']];
    }
    $external = isset($stats['external_links'])
        ? [['status' => 'info', 'message' => (int) $stats['external_links'] . ' external link(s), ' . (int) ($stats['nofollow_links'] ?? 0) . ' nofollow.']] : [];

    return [
        $row('title', 'Title', $byCheck['title'] ?? [], (int) $audit['title_score']),
        $row('description', 'Meta description', $byCheck['description'] ?? [], (int) $audit['description_score']),
        $row('h1', 'H1', $byCheck['h1'] ?? [], (int) $audit['h1_score']),
        $row('heading', 'H2 structure', $byCheck['heading'] ?? [], (int) $audit['heading_score']),
        $row('canonical', 'Canonical', $byCheck['canonical'] ?? [], (int) $audit['canonical_score']),
        $row('robots', 'Robots', $byCheck['robots'] ?? [], (int) $audit['robots_score']),
        $row('og', 'OpenGraph', $byCheck['og'] ?? [], (int) $audit['og_score']),
        $row('schema', 'Structured data', $byCheck['schema'] ?? [], (int) $audit['schema_score']),
        $row('alt', 'Image alt text', $byCheck['alt'] ?? [], (int) $audit['alt_score']),
        $row('internal_link', 'Internal links', $byCheck['internal_link'] ?? [], (int) $audit['internal_link_score']),
        $row('external', 'External links', $external, null),
        $row('viewport', 'Mobile / viewport', $viewport, null),
        $row('lang', 'Language attribute', $lang, null),
    ];
}
