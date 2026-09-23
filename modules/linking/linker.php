<?php
/**
 * Internal linking engine.
 *
 * Takes rendered article HTML and turns the FIRST natural occurrence of each mapped phrase into a
 * link. It works on the DOM (not regex over HTML), so it never touches attributes, never nests links,
 * and skips headings, code and existing links. A per-article cap keeps content readable.
 */

function internal_links_active(): array
{
    static $cache = null;
    return $cache ??= db_all(
        "SELECT keyword, target_url FROM internal_links WHERE status = 'active'
         ORDER BY priority DESC, CHAR_LENGTH(keyword) DESC"
    );
}

/**
 * @param string $html        trusted HTML from render_markdown()
 * @param string $currentPath the page being rendered; never link a page to itself
 * @param array|null $rules   [['keyword' => , 'target_url' => ]] - defaults to active DB rules
 * @return array{html: string, links: array<string>}
 */
function apply_internal_links(string $html, string $currentPath, int $max = 5, ?array $rules = null): array
{
    $rules ??= internal_links_active();
    if ($html === '' || $max < 1 || !$rules) {
        return ['html' => $html, 'links' => []];
    }

    $doc = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $xpath = new DOMXPath($doc);
    // Text nodes in normal prose only.
    $query = '//text()[normalize-space()][not(ancestor::a or ancestor::h1 or ancestor::h2 or ancestor::h3 or ancestor::h4 '
        . 'or ancestor::code or ancestor::pre or ancestor::th or ancestor::script or ancestor::style)]';

    // Targets already linked by the author count as used, so we never add a duplicate link.
    $linkedTargets = [];
    foreach ($xpath->query('//a[@href]') as $a) {
        $linkedTargets[linker_normalize_path($a->getAttribute('href'))] = true;
    }

    $added = [];
    foreach ($rules as $rule) {
        if (count($added) >= $max) {
            break;
        }
        $target = linker_normalize_path($rule['target_url']);
        if ($target === linker_normalize_path($currentPath) || isset($linkedTargets[$target])) {
            continue;
        }
        $pattern = '/(?<![\p{L}\p{N}-])' . preg_quote($rule['keyword'], '/') . '(?![\p{L}\p{N}-])/iu';

        foreach ($xpath->query($query) as $textNode) {
            if (!preg_match($pattern, $textNode->nodeValue, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            // Byte offset -> split the text node into before / <a>match</a> / after.
            $before = substr($textNode->nodeValue, 0, $m[0][1]);
            $after = substr($textNode->nodeValue, $m[0][1] + strlen($m[0][0]));

            $link = $doc->createElement('a');
            $link->setAttribute('href', preg_match('#^https?://#', $rule['target_url']) ? $rule['target_url'] : url($rule['target_url']));
            $link->setAttribute('class', 'auto-link');
            $link->appendChild($doc->createTextNode($m[0][0]));

            $parent = $textNode->parentNode;
            $parent->insertBefore($doc->createTextNode($before), $textNode);
            $parent->insertBefore($link, $textNode);
            $parent->insertBefore($doc->createTextNode($after), $textNode);
            $parent->removeChild($textNode);

            $linkedTargets[$target] = true;
            $added[] = $rule['keyword'];
            break; // first occurrence only
        }
    }

    $root = $doc->getElementById('__root');
    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return ['html' => $out, 'links' => $added];
}

/** Compare links by app path regardless of base path, trailing slash or case. */
function linker_normalize_path(string $href): string
{
    $path = (string) (parse_url($href, PHP_URL_PATH) ?? '');
    if (base_path() !== '' && str_starts_with($path, base_path())) {
        $path = substr($path, strlen(base_path()));
    }
    return strtolower(rtrim($path, '/')) ?: '/';
}

function internal_links_admin_list(): array
{
    return db_all('SELECT * FROM internal_links ORDER BY status, priority DESC, keyword');
}

/** @return array{0: array, 1: array} */
function internal_link_validate(array $input, ?int $id = null): array
{
    [$data, $errors] = validate($input, [
        'keyword'    => 'required|min:3|max:150',
        'target_url' => 'required|path_or_url|max:255',
        'priority'   => 'required|int',
        'status'     => 'required|in:active,paused',
    ], ['keyword' => 'Phrase', 'target_url' => 'Target URL']);
    $data['priority'] = max(1, min(10, (int) $data['priority']));
    if (!isset($errors['keyword']) && db_value('SELECT 1 FROM internal_links WHERE keyword = ? AND id <> ?', [$data['keyword'], $id ?? 0])) {
        $errors['keyword'] = 'A rule for this phrase already exists.';
    }
    return [$data, $errors];
}

/**
 * How many published articles link to each app path (manual links + automatic rules), computed from
 * the HTML the public site actually renders. Cached for the request.
 * @return array<string, int> path => number of linking articles
 */
function internal_inbound_map(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $map = [];
    foreach (db_all('SELECT p.slug, p.content FROM posts p WHERE ' . POST_PUBLIC_SQL) as $p) {
        $html = apply_internal_links(render_markdown($p['content']), '/blog/' . $p['slug'], (int) setting('internal_links_max', '5'))['html'];
        preg_match_all('#href="' . preg_quote(base_path(), '#') . '(/[^"\#?]*)#', $html, $m);
        foreach (array_unique(array_map(fn($t) => rtrim($t, '/') ?: '/', $m[1])) as $target) {
            $map[$target] = ($map[$target] ?? 0) + 1;
        }
    }
    return $map;
}
