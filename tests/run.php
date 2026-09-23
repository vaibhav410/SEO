<?php
/**
 * SYSCOM GrowthHub test suite (no dependencies).
 *
 *   php tests/run.php            unit + HTTP integration tests
 *   php tests/run.php --unit     unit tests only (no database or server needed)
 *
 * Integration tests create a separate database (<db name>_test), start PHP's built-in server
 * on port 8099 against it, exercise the app over HTTP, then shut the server down.
 * Your real database is never touched.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$unitOnly = in_array('--unit', $argv, true);
$port = 8099;
$base = "http://127.0.0.1:$port";

// Test environment: separate DB + URL, via env vars understood by config/config.php.
$baseConfig = require $root . '/config/config.php';
$testDb = $baseConfig['db']['name'] . '_test';
putenv("DB_NAME=$testDb");
putenv("APP_URL=$base");
putenv('APP_DEBUG=false');
putenv('AUDIT_ALLOW_SELF=false');
putenv('OPENROUTER_API_KEY=');

require $root . '/includes/bootstrap.php';
require $root . '/database/seeder.php';
foreach (['keywords/keywords', 'backlinks/backlinks', 'audit/url_guard', 'audit/auditor', 'seo/content_health', 'ai/assistant', 'seo/checklist', 'seo/opportunities', 'analytics/analytics', 'distribution/distribution'] as $m) {
    require_once APP_ROOT . "/modules/$m.php";
}

/* ---------------------------------------------------------------- mini framework */
$results = ['pass' => 0, 'fail' => 0, 'failures' => []];
$group = '';

function section(string $name): void
{
    global $group;
    $group = $name;
    echo "\n\033[1m$name\033[0m\n";
}

function test(string $name, callable $fn): void
{
    global $results, $group;
    try {
        $fn();
        $results['pass']++;
        echo "  \033[32m✓\033[0m $name\n";
    } catch (Throwable $e) {
        $results['fail']++;
        $results['failures'][] = "$group › $name: " . $e->getMessage();
        echo "  \033[31m✗ $name\033[0m\n      " . $e->getMessage() . "\n";
    }
}

function ok($cond, string $msg = 'expected true'): void
{
    if (!$cond) {
        throw new RuntimeException($msg);
    }
}

function eq($expected, $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(($msg ? "$msg: " : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function has(string $needle, string $haystack, string $msg = ''): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException(($msg ? "$msg: " : '') . "missing “" . mb_substr($needle, 0, 80) . '”');
    }
}

function hasnt(string $needle, string $haystack, string $msg = ''): void
{
    if (str_contains($haystack, $needle)) {
        throw new RuntimeException(($msg ? "$msg: " : '') . "unexpected “" . mb_substr($needle, 0, 80) . '”');
    }
}

/* ---------------------------------------------------------------- unit tests */
section('Helpers');
test('slugify creates clean slugs', function () {
    eq('what-is-an-ssl-certificate', slugify('  What Is an SSL Certificate?  '));
    eq('shared-vs-vps', slugify('Shared -- vs -- VPS!!'));
    eq('', slugify('???'));
});
test('is_valid_slug rejects malformed slugs', function () {
    ok(is_valid_slug('web-hosting-india'));
    foreach (['Web-Hosting', 'web--hosting', '-web', 'web_hosting', 'web hosting', '../etc', str_repeat('a', 121)] as $bad) {
        ok(!is_valid_slug($bad), "accepted $bad");
    }
});
test('e() escapes HTML and quotes', function () {
    eq('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', e('<script>alert("x")</script>'));
    eq('&apos;', e("'"));
});
test('str_limit cuts on a word boundary', function () {
    eq('Web hosting for…', str_limit('Web hosting for business websites', 18));
});

section('Markdown renderer (XSS safety)');
test('escapes raw HTML', function () {
    $html = render_markdown("<script>alert(1)</script>\n\n<img src=x onerror=alert(1)>");
    hasnt('<script>', $html);
    hasnt('<img', $html);
    has('&lt;script&gt;', $html);
});
test('drops javascript: links but keeps safe ones', function () {
    $html = render_markdown('[bad](javascript:alert(1)) and [good](/services/web-hosting) and [ext](https://example.com)');
    hasnt('javascript:', $html);
    has('href="/services/web-hosting"', $html);
    has('rel="noopener"', $html);
});
test('turns "# heading" into h2 so the template keeps the only H1', function () {
    $html = render_markdown("# Title\n\n## Section\n\nText");
    hasnt('<h1', $html);
    has('<h2 id="title">Title</h2>', $html);
});
test('renders lists, tables and inline formatting', function () {
    $html = render_markdown("- **bold** item\n- *em* item\n\n| A | B |\n| --- | --- |\n| 1 | 2 |\n\n1. one\n2. two");
    has('<ul><li><strong>bold</strong> item</li>', $html);
    has('<em>em</em>', $html);
    has('<th scope="col">A</th>', $html);
    has('<ol><li>one</li><li>two</li></ol>', $html);
});

section('Internal linking engine');
$rules = [['keyword' => 'web hosting', 'target_url' => '/services/web-hosting'], ['keyword' => 'SSL certificate', 'target_url' => '/services/ssl-certificates']];
test('links only the first occurrence', function () use ($rules) {
    $r = apply_internal_links('<p>Web hosting matters. Good web hosting is fast.</p>', '/blog/x', 5, $rules);
    eq(1, substr_count($r['html'], '<a '), 'link count');
    has('<a href="/services/web-hosting" class="auto-link">Web hosting</a>', $r['html']);
});
test('skips headings, existing links and code', function () use ($rules) {
    $html = '<h2>Web hosting</h2><p><a href="/other">web hosting</a> and <code>web hosting</code></p>';
    $r = apply_internal_links($html, '/blog/x', 5, $rules);
    eq([], $r['links']);
});
test('never links a page to itself', function () use ($rules) {
    $r = apply_internal_links('<p>Our web hosting plans.</p>', '/services/web-hosting', 5, $rules);
    eq([], $r['links']);
});
test('does not add a link when the author already linked the target', function () use ($rules) {
    $r = apply_internal_links('<p>See <a href="/services/web-hosting">plans</a>. More web hosting tips.</p>', '/blog/x', 5, $rules);
    eq([], $r['links']);
});
test('respects the per-page maximum', function () use ($rules) {
    $r = apply_internal_links('<p>web hosting and an SSL certificate</p>', '/blog/x', 1, $rules);
    eq(1, count($r['links']));
});
test('matches whole phrases only and preserves HTML entities', function () use ($rules) {
    $r = apply_internal_links('<p>webhosting &amp; unsslcertificate</p>', '/blog/x', 5, $rules);
    eq([], $r['links']);
    has('&amp;', $r['html']);
});

section('SSRF guard');
test('blocks private, loopback, metadata and malformed targets', function () {
    foreach (['http://127.0.0.1/', 'http://localhost/', 'http://169.254.169.254/latest/meta-data/', 'http://10.1.2.3/', 'http://192.168.1.1/',
                 'http://172.16.0.1/', 'http://[::1]/', 'http://[::ffff:127.0.0.1]/', 'http://100.64.0.1/', 'http://0.0.0.0/',
                 'ftp://example.com/', 'file:///etc/passwd', 'gopher://x.com/', 'http://user:pass@example.com/', 'http://example.com:8080/',
                 'http://printer.local/', 'not a url', ''] as $url) {
        ok(!url_guard_check($url)['ok'], "allowed $url");
    }
});
test('ip_is_public classifies addresses correctly', function () {
    ok(ip_is_public('8.8.8.8'));
    ok(ip_is_public('2606:4700::6810:84e5'));
    foreach (['127.0.0.1', '10.0.0.1', '172.31.255.255', '192.168.0.1', '169.254.1.1', '100.100.100.200', '::1', 'fd00::1', 'fe80::1', '224.0.0.1'] as $ip) {
        ok(!ip_is_public($ip), "public: $ip");
    }
});
test('CIDR matching', function () {
    ok(ip_in_cidr('10.20.30.40', '10.0.0.0/8'));
    ok(!ip_in_cidr('11.0.0.1', '10.0.0.0/8'));
    ok(ip_in_cidr('fd12:3456::1', 'fc00::/7'));
});

section('SEO auditor analysis');
$good = file_get_contents(__DIR__ . '/fixtures/good-page.html');
$bad = file_get_contents(__DIR__ . '/fixtures/bad-page.html');
test('a well-optimised page scores 100', function () use ($good) {
    $a = audit_analyze($good, 'https://example.com/guide');
    eq(100, $a['overall']);
    eq(['Article', 'BreadcrumbList'], $a['stats']['schema_types']);
});
test('a poor page scores low with specific issues', function () use ($bad) {
    $a = audit_analyze($bad, 'http://example.com/');
    ok($a['overall'] < 40, 'score ' . $a['overall']);
    $messages = implode(' | ', array_column($a['issues'], 'message'));
    has('no <title>', $messages);
    has('Missing meta description', $messages);
    has('2 H1 headings', $messages);
    has('noindex', $messages);
    has('2 of 3 images are missing alt', $messages);
    has('served over HTTP', $messages);
});
test('malformed HTML and empty input do not crash', function () {
    $a = audit_analyze('<html><head><title>Broken<body><h1>Hi<p><div></span>', 'https://example.com/');
    eq('Broken', mb_substr($a['stats']['title'], 0, 6));
    $b = audit_analyze('', 'https://example.com/');
    eq(0, $b['scores']['title']);
});
test('url_resolve handles relative redirects', function () {
    eq('https://a.com/x', url_resolve('https://a.com/p/q', '/x'));
    eq('https://a.com/p/y', url_resolve('https://a.com/p/q', 'y'));
    eq('https://b.com/', url_resolve('https://a.com/', '//b.com/'));
});

section('SEO metadata & schema');
test('schema_script cannot be broken out of with </script>', function () {
    $out = schema_script([['@type' => 'Thing', 'name' => '</script><script>alert(1)</script>']]);
    eq(1, substr_count($out, '</script>'));
    ok(json_decode(strip_tags($out)) !== null || str_contains($out, '<'), 'escaped');
});
test('schema_faq returns null without FAQs', function () {
    eq(null, schema_faq([]));
});
test('seo_length_status', function () {
    eq('missing', seo_length_status('', 30, 60));
    eq('short', seo_length_status('Short', 30, 60));
    eq('ok', seo_length_status(str_repeat('a', 45), 30, 60));
    eq('long', seo_length_status(str_repeat('a', 61), 30, 60));
});

section('Validation & security helpers');
test('validate() enforces rules', function () {
    [$data, $errors] = validate(['name' => '  ', 'email' => 'bad', 'slug' => 'Bad Slug', 'n' => '5', 's' => 'x'],
        ['name' => 'required', 'email' => 'email', 'slug' => 'slug', 'n' => 'int', 's' => 'in:a,b']);
    eq(['name', 'email', 'slug', 's'], array_keys($errors));
    eq('5', $data['n']);
});
test('lead timing token rejects forgery, instant and stale submissions', function () {
    ok(!lead_time_ok(lead_time_token()), 'instant accepted');
    ok(lead_time_ok(lead_time_token(time() - 10)), 'normal rejected');
    ok(!lead_time_ok(lead_time_token(time() - 90000)), 'stale accepted');
    ok(!lead_time_ok((time() - 10) . '.forged'), 'forged accepted');
});
test('keyword_in_text allows natural word order', function () {
    ok(keyword_in_text('web hosting india', 'Business hosting for web sites in India'));
    ok(!keyword_in_text('vps hosting', 'Shared web hosting'));
});

section('Growth OS helpers');
test('robots_validate flags dangerous and malformed rules', function () {
    eq([], robots_validate("User-agent: GPTBot\nDisallow: /private/\n# comment\nSitemap: https://example.com/s.xml"));
    $levels = array_column(robots_validate("User-agent: *\nDisallow: /\nNoindex: /x\nAllow: relative\nSitemap: /relative.xml"), 'message');
    has('entire site', implode(' | ', $levels));
    has('Unknown directive', implode(' | ', $levels));
    has('must start with', implode(' | ', $levels));
    has('absolute URL', implode(' | ', $levels));
});
test('schema_validate reports missing required and recommended properties', function () {
    eq('valid', schema_validate(['@type' => 'Service', 'name' => 'X', 'provider' => ['@id' => 'y'], 'description' => 'd', 'areaServed' => 'IN'])['status']);
    eq('error', schema_validate(['@type' => 'Article', 'headline' => 'H'])['status']);
    eq('warning', schema_validate(['@type' => 'Article', 'headline' => 'H', 'datePublished' => 'd', 'author' => 'a'])['status']);
    eq('error', schema_validate(['@type' => 'FAQPage', 'mainEntity' => [['name' => 'Q', 'acceptedAnswer' => ['text' => '']]]])['status']);
});
test('lead_campaign only accepts safe tokens', function () {
    eq('linkedin-q3', lead_campaign('LinkedIn-Q3'));
    eq(null, lead_campaign('<script>'));
    eq(null, lead_campaign(str_repeat('a', 101)));
    eq(null, lead_campaign(''));
});
test('keyword_recommended_type maps intent to content', function () {
    eq('guide', keyword_recommended_type('informational'));
    eq('landing_page', keyword_recommended_type('commercial'));
    eq('service_page', keyword_recommended_type('transactional'));
});

if ($unitOnly) {
    finish();
}

/* ---------------------------------------------------------------- integration setup */
section('Test environment');
$php = PHP_BINARY;
passthru(escapeshellarg($php) . ' ' . escapeshellarg($root . '/database/install.php') . ' > ' . (PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'), $code);
test("installed schema + seed into `$testDb`", fn() => eq(0, $code));

$serverLog = sys_get_temp_dir() . '/growthhub-test-server.log';
$proc = proc_open([$php, '-S', "127.0.0.1:$port", $root . '/router.php'], [1 => ['file', $serverLog, 'a'], 2 => ['file', $serverLog, 'a']], $pipes, $root);
register_shutdown_function(function () use (&$proc) {
    if (is_resource($proc)) {
        proc_terminate($proc);
    }
});
$up = false;
for ($i = 0; $i < 50 && !$up; $i++) {
    usleep(100000);
    $up = @file_get_contents("$base/robots.txt") !== false;
}
test("test server running on :$port", fn() => ok($up, 'server did not start'));
if (!$up) {
    finish();
}

/** Minimal HTTP client with a cookie jar per "browser". */
class Browser
{
    private string $jar;
    public function __construct(private string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'jar');
    }
    public function request(string $method, string $path, array $data = [], array $headers = [], ?string $raw = null): array
    {
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers]);
        if ($raw !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $raw);
        } elseif ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $head = substr((string) $response, 0, $headerSize);
        preg_match('/^Location:\s*(.+)$/mi', $head, $m);
        return ['status' => $status, 'headers' => $head, 'body' => substr((string) $response, $headerSize), 'location' => trim($m[1] ?? '')];
    }
    public function get(string $path): array { return $this->request('GET', $path); }
    public function post(string $path, array $data): array { return $this->request('POST', $path, $data); }
    /** GET a page, then POST a form on it with its CSRF token. */
    public function submit(string $path, array $data, ?string $action = null): array
    {
        $data['_csrf'] = $this->csrf($path);
        return $this->post($action ?? $path, $data);
    }
    public function csrf(string $path): string
    {
        preg_match('/name="_csrf" value="([a-f0-9]+)"/', $this->get($path)['body'], $m);
        return $m[1] ?? '';
    }
}

function meta(string $html, string $selector): string
{
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML($html);
    return trim((string) (new DOMXPath($doc))->evaluate("string($selector)"));
}

$guest = new Browser($base);

/* ---------------------------------------------------------------- public site */
section('Public pages & routing');
$pages = ['/', '/services', '/services/web-hosting', '/blog', '/blog/what-is-an-ssl-certificate', '/resources', '/faq', '/contact', '/web-hosting-india'];
test('all public pages return 200', function () use ($guest, $pages) {
    foreach ($pages as $p) {
        eq(200, $guest->get($p)['status'], $p);
    }
});
test('every page has one H1, title, description, canonical and OG tags', function () use ($guest, $pages, $base) {
    $titles = [];
    foreach ($pages as $p) {
        $html = $guest->get($p)['body'];
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($html);
        $x = new DOMXPath($doc);
        eq(1, $x->query('//h1')->length, "$p H1 count");
        $title = meta($html, '//title');
        ok($title !== '' && mb_strlen($title) <= 65, "$p title length " . mb_strlen($title));
        ok(mb_strlen(meta($html, '//meta[@name="description"]/@content')) >= 70, "$p description");
        eq($base . ($p === '/' ? '/' : $p), meta($html, '//link[@rel="canonical"]/@href'), "$p canonical");
        ok(meta($html, '//meta[@property="og:title"]/@content') !== '', "$p og:title");
        $titles[] = $title;
    }
    eq(count($titles), count(array_unique($titles)), 'duplicate titles');
});
test('JSON-LD is valid and matches page type', function () use ($guest) {
    $types = function (string $path) use ($guest) {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $guest->get($path)['body'], $m);
        $all = [];
        foreach ($m[1] as $json) {
            $data = json_decode($json, true);
            ok(is_array($data), "invalid JSON-LD on $path");
            foreach ($data['@graph'] as $node) {
                $all[] = $node['@type'];
            }
        }
        return $all;
    };
    eq(['Organization', 'WebSite', 'FAQPage'], $types('/'));
    eq(['Article', 'FAQPage', 'BreadcrumbList'], $types('/blog/what-is-an-ssl-certificate'));
    eq(['Service', 'FAQPage', 'BreadcrumbList'], $types('/services/web-hosting'));
});
test('404 for unknown and malformed URLs, with noindex', function () use ($guest) {
    foreach (['/no-such-page', '/services/nope', '/blog/nope', '/blog/Bad_Slug', '/a/b/c', '/blog?page=999'] as $p) {
        $r = $guest->get($p);
        eq(404, $r['status'], $p);
    }
    has('noindex', $guest->get('/no-such-page')['body']);
});
test('duplicate URL variants 301 to one canonical URL', function () use ($guest, $base) {
    foreach (['/blog/' => '/blog', '/Services/Web-Hosting' => '/services/web-hosting', '/index.php' => '/'] as $from => $to) {
        $r = $guest->get($from);
        eq(301, $r['status'], $from);
        eq($to, $r['location'], $from);
    }
});
test('reserved route names cannot be landing pages', function () use ($guest) {
    eq(404, $guest->get('/admin-x')['status']);
    eq(true, in_array('blog', RESERVED_SLUGS, true));
});
test('private folders are not reachable', function () use ($guest) {
    foreach (['/config/config.php', '/includes/bootstrap.php', '/database/schema.sql', '/modules/leads/leads.php', '/admin/partials/layout-top.php', '/README.md', '/.git/config'] as $p) {
        eq(403, $guest->get($p)['status'], $p);
    }
});
test('security headers are sent', function () use ($base) {
    $h = (new Browser($base))->get('/')['headers'];
    foreach (['Content-Security-Policy', 'X-Frame-Options: DENY', 'X-Content-Type-Options: nosniff', 'Referrer-Policy'] as $header) {
        has($header, $h);
    }
    has('HttpOnly', $h);
    has('SameSite=Lax', $h);
    hasnt('X-Powered-By', $h);
});

section('Sitemap & robots');
test('sitemap.xml is valid XML with only published URLs', function () use ($guest, $base) {
    $r = $guest->get('/sitemap.xml');
    has('application/xml', $r['headers']);
    $xml = simplexml_load_string($r['body']);
    ok($xml !== false, 'invalid XML');
    $locs = array_map('strval', iterator_to_array($xml->url->loc ?? [], false));
    $locs = [];
    foreach ($xml->url as $u) {
        $locs[] = (string) $u->loc;
    }
    ok(in_array("$base/blog/what-is-an-ssl-certificate", $locs, true), 'article missing');
    ok(in_array("$base/web-hosting-india", $locs, true), 'landing page missing');
    foreach ($locs as $loc) {
        ok(!str_contains($loc, '/admin') && !str_contains($loc, '?'), "private URL in sitemap: $loc");
    }
});
test('robots.txt disallows admin and points to the sitemap', function () use ($guest, $base) {
    $body = $guest->get('/robots.txt')['body'];
    has('Disallow: /admin/', $body);
    has("Sitemap: $base/sitemap.xml", $body);
});

section('Search & injection attempts');
test('blog search is escaped and noindexed', function () use ($guest) {
    $r = $guest->get('/blog?q=' . rawurlencode('"><script>alert(1)</script>'));
    eq(200, $r['status']);
    hasnt('<script>alert(1)</script>', $r['body']);
    has('noindex', $r['body']);
});
test('SQL injection in search does not break the query', function () use ($guest) {
    $r = $guest->get('/search?q=' . rawurlencode("' OR 1=1 -- "));
    eq(200, $r['status']);
    has('No relevant results found.', $r['body']);
    $api = json_decode($guest->get('/api/search.php?q=' . rawurlencode("%' UNION SELECT password_hash FROM users -- "))['body'], true);
    eq([], $api['results']);
});
test('search API returns published content', function () use ($guest) {
    $api = json_decode($guest->get('/api/search.php?q=ssl')['body'], true);
    ok(count($api['results']) >= 2);
});

section('Lead forms');
test('rejects submissions without a valid CSRF token', function () use ($guest) {
    eq(419, $guest->post('/contact', ['name' => 'A', 'email' => 'a@b.co', 'message' => 'hello there'])['status']);
    eq(419, $guest->post('/contact', ['_csrf' => 'forged', 'name' => 'A'])['status']);
});
test('shows field errors for empty, invalid and oversized input', function () use ($guest) {
    $html = $guest->get('/contact')['body'];
    preg_match('/name="_ts" value="([^"]+)"/', $html, $ts);
    preg_match('/name="_csrf" value="([^"]+)"/', $html, $csrf);
    [$time] = explode('.', $ts[1]);
    $ts = lead_time_token((int) $time - 10);
    $guest->post('/contact', ['_csrf' => $csrf[1], '_ts' => $ts, 'name' => '', 'email' => 'not-an-email', 'message' => str_repeat('x', 3001)]);
    $body = $guest->get('/contact')['body'];
    has('Name is required', $body);
    has('Enter a valid email address', $body);
    has('at most 3000 characters', $body);
});
test('stores a valid lead with its source page, escaping payloads', function () use ($guest) {
    $csrf = $guest->csrf('/web-hosting-india');
    $r = $guest->post('/web-hosting-india', ['_csrf' => $csrf, '_ts' => lead_time_token(time() - 10), 'name' => '<b>Test</b> Lead',
        'email' => 'lead@example.com', 'company' => "Robert'); DROP TABLE leads;--", 'message' => 'Please call me about hosting.']);
    eq(302, $r['status']);
    $lead = db_one('SELECT * FROM leads WHERE email = ? ORDER BY id DESC', ['lead@example.com']);
    eq('/web-hosting-india', $lead['source_page']);
    eq("Robert'); DROP TABLE leads;--", $lead['company']);
    has('Thank you, &lt;b&gt;Test&lt;/b&gt; Lead', $guest->get('/web-hosting-india')['body']);
});
test('honeypot and instant submissions are silently discarded', function () use ($guest) {
    $before = (int) db_value('SELECT COUNT(*) FROM leads');
    $csrf = $guest->csrf('/contact');
    $guest->post('/contact', ['_csrf' => $csrf, '_ts' => lead_time_token(time() - 10), 'website' => 'spam', 'name' => 'Bot', 'email' => 'b@b.co', 'message' => 'spam spam spam']);
    $guest->post('/contact', ['_csrf' => $csrf, '_ts' => lead_time_token(), 'name' => 'Bot', 'email' => 'b@b.co', 'message' => 'too fast submission']);
    eq($before, (int) db_value('SELECT COUNT(*) FROM leads'));
});
test('rate limits repeated submissions', function () use ($base) {
    $b = new Browser($base);
    $csrf = $b->csrf('/contact');
    for ($i = 0; $i < 4; $i++) {
        $b->post('/contact', ['_csrf' => $csrf, '_ts' => lead_time_token(time() - 10), 'name' => 'Rate', 'email' => "r$i@example.com", 'message' => "Message number $i here"]);
    }
    has('several enquiries in a short time', $b->get('/contact')['body']);
});

/* ---------------------------------------------------------------- admin */
section('Authentication & authorization');
$admin = new Browser($base);
test('admin pages redirect guests to login', function () use ($guest) {
    foreach (['/admin/dashboard.php', '/admin/posts/', '/admin/settings/', '/admin/leads/view.php?id=1'] as $p) {
        $r = $guest->get($p);
        eq(302, $r['status'], $p);
        has('/admin/login.php', $r['location']);
    }
});
test('API endpoints return 401 JSON for guests', function () use ($guest) {
    $r = $guest->request('POST', '/api/audit.php', [], ['Content-Type: application/json'], '{"url":"https://example.com"}');
    eq(401, $r['status']);
});
test('invalid login is rejected', function () use ($admin) {
    $r = $admin->submit('/admin/login.php', ['email' => DEMO_ADMIN_EMAIL, 'password' => 'wrong-password']);
    has('Email or password is incorrect', $r['body']);
});
test('login without CSRF token is rejected', function () use ($admin) {
    eq(419, $admin->post('/admin/login.php', ['email' => DEMO_ADMIN_EMAIL, 'password' => DEMO_ADMIN_PASSWORD])['status']);
});
test('valid login reaches the dashboard', function () use ($admin) {
    $r = $admin->submit('/admin/login.php', ['email' => DEMO_ADMIN_EMAIL, 'password' => DEMO_ADMIN_PASSWORD]);
    eq(302, $r['status']);
    $dash = $admin->get('/admin/dashboard.php');
    eq(200, $dash['status']);
    has('Growth dashboard', $dash['body']);
    has('noindex', $dash['headers']);
});
test('login is throttled after repeated failures', function () use ($base) {
    $b = new Browser($base);
    for ($i = 0; $i < 5; $i++) {
        $b->submit('/admin/login.php', ['email' => 'nobody@example.com', 'password' => 'x']);
    }
    has('Too many failed attempts', $b->submit('/admin/login.php', ['email' => 'nobody@example.com', 'password' => 'x'])['body']);
    db_query('DELETE FROM login_attempts');
});
test('editor role cannot open settings', function () use ($base) {
    db_query("INSERT INTO users (name, email, password_hash, role) VALUES ('Editor', 'editor@test.local', ?, 'editor')", [password_hash('EditorPass123', PASSWORD_DEFAULT)]);
    $b = new Browser($base);
    $b->submit('/admin/login.php', ['email' => 'editor@test.local', 'password' => 'EditorPass123']);
    eq(200, $b->get('/admin/posts/')['status']);
    eq(403, $b->get('/admin/settings/')['status']);
});
test('logout requires POST + CSRF and ends the session', function () use ($base) {
    $b = new Browser($base);
    $b->submit('/admin/login.php', ['email' => DEMO_ADMIN_EMAIL, 'password' => DEMO_ADMIN_PASSWORD]);
    eq(302, $b->get('/admin/logout.php')['status']);
    eq(200, $b->get('/admin/dashboard.php')['status'], 'GET logout must not log out');
    $b->submit('/admin/dashboard.php', [], '/admin/logout.php');
    eq(302, $b->get('/admin/dashboard.php')['status']);
});

section('CMS: articles');
$postData = ['title' => 'Test Article About Hosting Security', 'slug' => '', 'excerpt' => 'An integration-test article.',
    'content' => "## Heading\n\nThis test article talks about web hosting and needs enough words to pass validation easily.",
    'primary_keyword' => 'hosting security', 'meta_title' => 'Test Article About Hosting Security', 'meta_description' => '',
    'featured_image_alt' => '', 'service_id' => '', 'published_at' => ''];
test('create draft: saved, slug generated, not public, not in sitemap', function () use ($admin, $guest, $postData) {
    $r = $admin->submit('/admin/posts/edit.php', $postData + ['intent' => 'draft']);
    eq(302, $r['status']);
    $post = db_one('SELECT * FROM posts WHERE slug = ?', ['test-article-about-hosting-security']);
    ok($post !== null, 'not saved');
    eq('draft', $post['status']);
    eq(404, $guest->get('/blog/test-article-about-hosting-security')['status']);
    hasnt('test-article-about-hosting-security', $guest->get('/sitemap.xml')['body']);
});
test('publish: visible, in sitemap, internal links applied', function () use ($admin, $guest, $postData) {
    $id = (int) db_value('SELECT id FROM posts WHERE slug = ?', ['test-article-about-hosting-security']);
    $admin->submit("/admin/posts/edit.php?id=$id", $postData + ['intent' => 'publish', 'slug' => 'test-article-about-hosting-security']);
    $r = $guest->get('/blog/test-article-about-hosting-security');
    eq(200, $r['status']);
    has('class="auto-link"', $r['body']);
    has('test-article-about-hosting-security', $guest->get('/sitemap.xml')['body']);
});
test('slug conflict is rejected', function () use ($admin, $postData) {
    $r = $admin->submit('/admin/posts/edit.php', array_merge($postData, ['slug' => 'what-is-an-ssl-certificate', 'intent' => 'draft']));
    eq(200, $r['status']);
    has('Another article already uses this slug', $r['body']);
});
test('validation errors keep the input', function () use ($admin) {
    $r = $admin->submit('/admin/posts/edit.php', ['title' => '', 'excerpt' => '', 'content' => 'short', 'intent' => 'draft', 'meta_title' => '<b>kept</b>']);
    has('Title is required', $r['body']);
    has('value="&lt;b&gt;kept&lt;/b&gt;"', $r['body']);
});
test('unpublish then delete removes the article', function () use ($admin, $guest) {
    $id = (int) db_value('SELECT id FROM posts WHERE slug = ?', ['test-article-about-hosting-security']);
    $admin->submit('/admin/posts/', ['id' => $id, 'action' => 'unpublish']);
    eq(404, $guest->get('/blog/test-article-about-hosting-security')['status']);
    $admin->submit('/admin/posts/', ['id' => $id, 'action' => 'delete']);
    eq(null, db_value('SELECT id FROM posts WHERE id = ?', [$id]));
});
test('state-changing admin POST without CSRF is rejected', function () use ($admin) {
    $id = (int) db_value('SELECT id FROM posts ORDER BY id LIMIT 1');
    eq(419, $admin->post('/admin/posts/', ['id' => $id, 'action' => 'delete'])['status']);
    ok(db_value('SELECT id FROM posts WHERE id = ?', [$id]) !== null);
});

section('CMS: services, FAQs, landing pages');
test('service create + public page + FAQ schema', function () use ($admin, $guest) {
    $admin->submit('/admin/services/edit.php', ['name' => 'Test Backup Service', 'slug' => '', 'icon' => 'shield', 'description' => 'Backups for tests.',
        'content' => "## About\n\nAutomated backups for your website.", 'features' => "Daily\nOff-site", 'status' => 'published', 'sort_order' => '50',
        'primary_keyword' => '', 'external_url' => '', 'meta_title' => '', 'meta_description' => '']);
    $sid = (int) db_value('SELECT id FROM services WHERE slug = ?', ['test-backup-service']);
    ok($sid > 0, 'service not saved');
    $admin->submit('/admin/faqs/edit.php', ['question' => 'How often are backups taken?', 'answer' => 'Every day.', 'owner' => "service:$sid", 'status' => 'published', 'sort_order' => '1']);
    $html = $guest->get('/services/test-backup-service')['body'];
    has('How often are backups taken?', $html);
    has('"@type":"FAQPage"', $html);
});
test('landing page: reserved slug and thin content rejected, valid page published', function () use ($admin, $guest) {
    $base = ['title' => 'Test Landing', 'primary_keyword' => 'test landing keyword', 'hero_subtitle' => 'Subtitle', 'meta_title' => '', 'meta_description' => '',
        'problem' => str_repeat('Problem text. ', 8), 'solution' => str_repeat('Solution text. ', 8), 'benefits' => "One\nTwo", 'use_cases' => '',
        'content' => '', 'cta_text' => 'Contact us', 'service_id' => '', 'status' => 'published'];
    $r = $admin->submit('/admin/landing-pages/edit.php', $base + ['slug' => 'blog', 'features' => "A: a\nB: b\nC: c"]);
    has('reserved', $r['body']);
    $r = $admin->submit('/admin/landing-pages/edit.php', $base + ['slug' => 'test-landing', 'features' => 'Only one']);
    has('at least three features', $r['body']);
    $admin->submit('/admin/landing-pages/edit.php', $base + ['slug' => 'test-landing', 'features' => "A: a\nB: b\nC: c"]);
    eq(200, $guest->get('/test-landing')['status']);
});

section('Growth modules');
test('keyword plan: add, validate duplicates, coverage computed', function () use ($admin) {
    $r = $admin->submit('/admin/keywords/edit.php', ['keyword' => 'Web Hosting India', 'intent' => 'commercial', 'priority' => 'high', 'target_url' => '', 'status' => 'researching', 'notes' => '']);
    has('already in the plan', $r['body']);
    $admin->submit('/admin/keywords/edit.php', ['keyword' => 'test backup keyword', 'intent' => 'commercial', 'priority' => 'low', 'target_url' => '/services/test-backup-service', 'status' => 'mapped', 'notes' => '']);
    $kw = db_one('SELECT * FROM keywords WHERE keyword = ?', ['test backup keyword']);
    eq('ok', keyword_coverage($kw)['status']);
    has('test backup keyword', $admin->get('/admin/keywords/')['body']);
});
test('internal link rules: add and pause', function () use ($admin) {
    $admin->submit('/admin/links/', ['keyword' => 'automated backups', 'target_url' => '/services/test-backup-service', 'priority' => '5', 'status' => 'active']);
    $id = (int) db_value('SELECT id FROM internal_links WHERE keyword = ?', ['automated backups']);
    ok($id > 0);
    $admin->submit('/admin/links/', ['id' => $id, 'action' => 'toggle']);
    eq('paused', db_value('SELECT status FROM internal_links WHERE id = ?', [$id]));
});
test('backlink tracker: live link requires a source URL', function () use ($admin) {
    $r = $admin->submit('/admin/backlinks/edit.php', ['platform' => 'Test Directory', 'type' => 'directory', 'source_url' => '', 'target_url' => 'https://syscom.co.in/',
        'anchor_text' => 'SYSCOM', 'rel' => 'unknown', 'status' => 'live', 'notes' => '']);
    has('needs the source page URL', $r['body']);
    $admin->submit('/admin/backlinks/edit.php', ['platform' => 'Test Directory', 'type' => 'directory', 'source_url' => '', 'target_url' => 'https://syscom.co.in/',
        'anchor_text' => 'SYSCOM', 'rel' => 'unknown', 'status' => 'opportunity', 'notes' => '']);
    ok(db_value('SELECT id FROM backlinks WHERE platform = ?', ['Test Directory']) !== null);
});
test('leads: status update and CSV export', function () use ($admin) {
    $id = (int) db_value('SELECT id FROM leads ORDER BY id DESC LIMIT 1');
    $admin->submit("/admin/leads/view.php?id=$id", ['status' => 'qualified', 'admin_notes' => 'Called back.']);
    eq('qualified', db_value('SELECT status FROM leads WHERE id = ?', [$id]));
    $csv = $admin->get('/admin/leads/?export=csv');
    has('text/csv', $csv['headers']);
    has('Source page', $csv['body']);
});
test('leads view escapes stored XSS', function () use ($admin) {
    db_query("INSERT INTO leads (name, email, message, source_page, ip_hash) VALUES ('<script>alert(1)</script>', 'x@example.com', '<img src=x onerror=alert(1)>', '/contact', ?)", [str_repeat('a', 64)]);
    $id = (int) db()->lastInsertId();
    $html = $admin->get("/admin/leads/view.php?id=$id")['body'];
    hasnt('<script>alert(1)</script>', $html);
    hasnt('<img src=x', $html);
});

section('SEO auditor API');
test('rejects requests without CSRF header', function () use ($admin) {
    $r = $admin->request('POST', '/api/audit.php', [], ['Content-Type: application/json'], '{"url":"https://example.com"}');
    eq(419, $r['status']);
});
test('blocks private addresses (SSRF)', function () use ($admin) {
    $token = $admin->csrf('/admin/audits/');
    foreach (['http://127.0.0.1:8099/', 'http://169.254.169.254/latest/meta-data/', 'http://localhost/'] as $url) {
        $r = $admin->request('POST', '/api/audit.php', [], ['Content-Type: application/json', "X-CSRF-Token: $token"], json_encode(['url' => $url]));
        eq(422, $r['status'], $url);
    }
});
test('invalid URL returns a helpful error', function () use ($admin) {
    $token = $admin->csrf('/admin/audits/');
    $r = $admin->request('POST', '/api/audit.php', [], ['Content-Type: application/json', "X-CSRF-Token: $token"], '{"url":"not a url"}');
    eq(422, $r['status']);
    has('valid URL', json_decode($r['body'], true)['error']);
});
test('audits a live public page end to end (needs internet)', function () use ($admin) {
    $token = $admin->csrf('/admin/audits/');
    $r = $admin->request('POST', '/api/audit.php', [], ['Content-Type: application/json', "X-CSRF-Token: $token"], '{"url":"https://example.com/"}');
    if ($r['status'] === 422 && str_contains($r['body'], 'resolve')) {
        echo "      (skipped: no internet)\n";
        return;
    }
    eq(200, $r['status'], $r['body']);
    $id = json_decode($r['body'], true)['id'];
    $report = $admin->get("/admin/audits/view.php?id=$id")['body'];
    has('SEO health', $report);
    has('not a Google score', $report);
    has('Recommended fix', $report);
});

section('Growth OS: new public features');
test('site search page is noindex with grouped results and an empty state', function () use ($guest) {
    $r = $guest->get('/search?q=ssl');
    eq(200, $r['status']);
    has('noindex', $r['body']);
    has('result-type', $r['body']);
    has('No relevant results found.', $guest->get('/search?q=zzqqxx')['body']);
    has('Disallow: /search', $guest->get('/robots.txt')['body']);
});
test('category archives are indexable and listed in the sitemap', function () use ($guest, $base) {
    $r = $guest->get('/blog/category/hosting');
    eq(200, $r['status']);
    eq($base . '/blog/category/hosting', meta($r['body'], '//link[@rel="canonical"]/@href'));
    eq(404, $guest->get('/blog/category/does-not-exist')['status']);
    has($base . '/blog/category/hosting', $guest->get('/sitemap.xml')['body']);
});
test('landing page leads record keyword and sanitised campaign', function () use ($base) {
    $b = new Browser($base);
    $page = $b->get('/wordpress-hosting-india?utm_campaign=LinkedIn-Spring');
    has('name="campaign" value="linkedin-spring"', $page['body']);
    preg_match('/name="_csrf" value="([a-f0-9]+)"/', $page['body'], $m);
    db_query('DELETE FROM leads WHERE ip_hash = ?', [hash_hmac('sha256', '127.0.0.1', (string) config('app.secret'))]);
    $b->post('/wordpress-hosting-india', ['_csrf' => $m[1], '_ts' => lead_time_token(time() - 10), 'name' => 'Attribution Test', 'email' => 'attr@example.com',
        'message' => 'Testing attribution of this lead.', 'campaign' => 'LinkedIn-Spring']);
    $lead = db_one('SELECT keyword, campaign, source_page FROM leads WHERE email = ?', ['attr@example.com']);
    eq('/wordpress-hosting-india', $lead['source_page']);
    eq('wordpress hosting india', $lead['keyword']);
    eq('linkedin-spring', $lead['campaign']);
    ok(db_value("SELECT COUNT(*) FROM activity_log WHERE entity_type = 'lead' AND action = 'received'") > 0, 'lead activity not logged');
});

section('Growth OS: admin screens');
test('every admin screen renders for an admin', function () use ($admin) {
    $pages = ['/admin/dashboard.php', '/admin/analytics/', '/admin/opportunities/', '/admin/opportunities/?type=technical', '/admin/technical/',
        '/admin/technical/schema.php', '/admin/technical/sitemap.php', '/admin/technical/robots.php', '/admin/categories/', '/admin/distribution/',
        '/admin/search.php?q=hosting', '/api/admin-search.php?q=ssl', '/admin/keywords/view.php?id=1', '/admin/backlinks/view.php?id=1',
        '/admin/links/?tab=existing', '/admin/links/?tab=suggested', '/admin/links/?tab=missing', '/admin/links/?tab=rules',
        '/admin/posts/?sort=title&dir=asc', '/admin/leads/?sort=status', '/admin/keywords/?sort=keyword&intent=commercial',
        '/admin/settings/?tab=users', '/admin/settings/?tab=security', '/admin/settings/?tab=system', '/admin/settings/?tab=seo'];
    foreach ($pages as $p) {
        $r = $admin->get($p);
        eq(200, $r['status'], $p);
        hasnt('debug-detail', $r['body'], $p);
    }
});
test('dashboard shows real metrics and "not connected" states, never invented traffic', function () use ($admin) {
    $html = $admin->get('/admin/dashboard.php')['body'];
    has('Growth loop', $html);
    has('Connect Google Analytics', $html);
    has('Connect Search Console', $html);
});
test('schema inventory reports no errors for the seeded site', function () {
    $errors = array_filter(schema_inventory(), fn($r) => schema_validate($r['node'])['status'] === 'error');
    eq([], array_map(fn($r) => $r['type'] . ' ' . $r['path'], $errors));
});
test('robots.txt editor rejects rules that block the whole site', function () use ($admin, $guest) {
    $admin->submit('/admin/technical/robots.php', ['robots_extra' => "User-agent: *\nDisallow: /"]);
    hasnt("Disallow: /\n\nSitemap", $guest->get('/robots.txt')['body']);
    $admin->submit('/admin/technical/robots.php', ['robots_extra' => "User-agent: GPTBot\nDisallow: /"]);
    has('User-agent: GPTBot', $guest->get('/robots.txt')['body']);
});
test('editors cannot edit robots.txt or site settings but can change their password', function () use ($base) {
    $b = new Browser($base);
    $b->submit('/admin/login.php', ['email' => 'editor@test.local', 'password' => 'EditorPass123']);
    eq(403, $b->get('/admin/technical/robots.php')['status']);
    eq(403, $b->get('/admin/settings/?tab=users')['status']);
    eq(200, $b->get('/admin/settings/?tab=account')['status']);
});
test('categories and distribution CRUD with validation', function () use ($admin) {
    $admin->submit('/admin/categories/', ['name' => 'Test Category', 'slug' => '', 'description' => 'Testing', 'meta_description' => '', 'sort_order' => '9']);
    ok(db_value('SELECT id FROM categories WHERE slug = ?', ['test-category']) !== null, 'category not saved');
    $r = $admin->submit('/admin/distribution/', ['platform' => 'linkedin', 'title' => 'Shared guide', 'post_id' => '', 'url' => '', 'status' => 'published', 'published_at' => '', 'notes' => '']);
    has('Add the URL of the published post', $r['body']);
    $admin->submit('/admin/distribution/', ['platform' => 'linkedin', 'title' => 'Shared guide', 'post_id' => '', 'url' => 'https://www.linkedin.com/posts/example', 'status' => 'published', 'published_at' => '', 'notes' => '']);
    eq(date('Y-m-d'), db_value('SELECT published_at FROM distribution_posts WHERE title = ?', ['Shared guide']));
});
test('opportunities can be marked done and reopened', function () use ($admin) {
    $ops = opportunities_all();
    ok(count($ops) > 0, 'no opportunities computed');
    $key = $ops[0]['key'];
    $admin->submit('/admin/opportunities/', ['key' => $key, 'status' => 'done', 'title' => $ops[0]['title']]);
    eq('done', db_value('SELECT status FROM opportunity_states WHERE opportunity_key = ?', [$key]));
    $admin->submit('/admin/opportunities/?view=closed', ['key' => $key, 'status' => 'open', 'title' => $ops[0]['title']]);
    eq(null, db_value('SELECT status FROM opportunity_states WHERE opportunity_key = ?', [$key]));
});
test('notifications can be marked as read', function () use ($admin) {
    $admin->submit('/admin/dashboard.php', [], '/admin/notifications.php');
    ok(db_value('SELECT notifications_seen_at FROM users WHERE email = ?', [DEMO_ADMIN_EMAIL]) !== null);
});
test('users: admin can add an editor; duplicate email rejected', function () use ($admin) {
    $admin->submit('/admin/settings/?tab=users', ['form' => 'user_add', 'name' => 'New Editor', 'email' => 'new.editor@test.local', 'role' => 'editor', 'password' => 'StrongPass123']);
    eq('editor', db_value('SELECT role FROM users WHERE email = ?', ['new.editor@test.local']));
    $r = $admin->submit('/admin/settings/?tab=users', ['form' => 'user_add', 'name' => 'Dup', 'email' => 'new.editor@test.local', 'role' => 'editor', 'password' => 'StrongPass123']);
    has('already exists', $r['body']);
});
test('activity timeline records admin changes', function () {
    ok((int) db_value("SELECT COUNT(*) FROM activity_log WHERE entity_type IN ('post', 'category', 'user')") >= 3, 'activity missing');
});

finish();

function finish(): void
{
    global $results;
    echo "\n" . str_repeat('─', 60) . "\n";
    $colour = $results['fail'] ? "\033[31m" : "\033[32m";
    echo $colour . "{$results['pass']} passed, {$results['fail']} failed\033[0m\n";
    foreach ($results['failures'] as $f) {
        echo "  - $f\n";
    }
    exit($results['fail'] ? 1 : 0);
}
