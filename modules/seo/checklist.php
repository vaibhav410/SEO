<?php
/**
 * Internal on-page SEO checklist for content stored in the CMS.
 * Used by content lists (score badge) and editors (full checklist). This is an editorial checklist,
 * not a prediction of Google rankings.
 */

/**
 * @param string $type post | service | landing
 * @return array{items: array<string, array{ok: bool, note: string}>, score: int, passed: int, total: int}
 */
function content_checklist(array $page, string $type): array
{
    $title = trim((string) ($page['meta_title'] ?: ($page['title'] ?? $page['name'] ?? '')));
    $h1 = trim((string) ($page['title'] ?? $page['name'] ?? ''));
    $desc = trim((string) ($page['meta_description'] ?? ''));
    $keyword = trim((string) ($page['primary_keyword'] ?? ''));
    $path = match ($type) {
        'post' => '/blog/' . ($page['slug'] ?? ''),
        'service' => '/services/' . ($page['slug'] ?? ''),
        default => '/' . ($page['slug'] ?? ''),
    };
    $body = match ($type) {
        'landing' => implode("\n\n", array_filter([$page['problem'] ?? '', $page['solution'] ?? '', $page['content'] ?? ''])),
        default => (string) ($page['content'] ?? ''),
    };

    $html = apply_internal_links(render_markdown($body), $path, (int) setting('internal_links_max', '5'))['html'];
    $internalLinks = preg_match_all('#href="' . preg_quote(base_path(), '#') . '/[a-z0-9]#', $html);
    if ($type !== 'post' && !empty($page['service_id'])) {
        $internalLinks++; // landing pages link to their related service in the template
    }
    $titleLen = mb_strlen($title);
    $descLen = mb_strlen($desc);
    $canonical = trim((string) ($page['canonical_url'] ?? ''));

    $items = [
        'Title present'            => ['ok' => $title !== '' && $titleLen <= 65, 'note' => $title === '' ? 'Missing' : "$titleLen characters" . ($titleLen > 65 ? ' – likely truncated' : '')],
        'Meta description present' => ['ok' => $descLen >= 70 && $descLen <= 160, 'note' => $descLen === 0 ? 'Missing' : "$descLen characters" . ($descLen < 70 ? ' – too short' : ($descLen > 160 ? ' – too long' : ''))],
        'H1 present'               => ['ok' => $h1 !== '', 'note' => $h1 !== '' ? 'Rendered from the page title' : 'Missing'],
        'Target keyword mapped'    => ['ok' => $keyword !== '' && keyword_in_text(mb_strtolower($keyword), mb_strtolower($title . ' ' . $h1)),
            'note' => $keyword === '' ? 'No primary keyword' : (keyword_in_text(mb_strtolower($keyword), mb_strtolower($title . ' ' . $h1)) ? '“' . $keyword . '” in title' : '“' . $keyword . '” not in title')],
        'Internal links'           => ['ok' => $internalLinks > 0, 'note' => $internalLinks . ' internal link' . ($internalLinks === 1 ? '' : 's')],
        'Image alt text'           => ['ok' => empty($page['featured_image']) || trim((string) ($page['featured_image_alt'] ?? '')) !== '',
            'note' => empty($page['featured_image']) ? 'No featured image' : (trim((string) ($page['featured_image_alt'] ?? '')) !== '' ? 'Alt text set' : 'Alt text missing')],
        'Canonical'                => ['ok' => $canonical === '' || is_http_url($canonical), 'note' => $canonical === '' ? 'Self-referencing (automatic)' : 'Custom: ' . $canonical],
        'OpenGraph'                => ['ok' => $title !== '' && ($desc !== '' || !empty($page['og_description'])), 'note' => !empty($page['og_title']) ? 'Custom OG title' : 'Generated from meta tags'],
        'Structured data'          => ['ok' => $h1 !== '', 'note' => ['post' => ($page['schema_type'] ?? 'Article') . ' + BreadcrumbList', 'service' => 'Service + BreadcrumbList', 'landing' => 'BreadcrumbList + FAQPage'][$type] ?? 'BreadcrumbList'],
    ];
    if ($type === 'post') {
        $words = str_word_count(markdown_text($body));
        $items['Content depth'] = ['ok' => $words >= 300, 'note' => "$words words" . ($words < 300 ? ' – consider expanding' : '')];
    }

    $passed = count(array_filter($items, fn($i) => $i['ok']));
    return ['items' => $items, 'passed' => $passed, 'total' => count($items), 'score' => (int) round(100 * $passed / count($items))];
}

/** Compact "7/10" badge for tables. */
function checklist_badge(array $checklist): string
{
    $tone = score_tone($checklist['score']);
    $missing = array_keys(array_filter($checklist['items'], fn($i) => !$i['ok']));
    $tip = $missing ? 'Needs: ' . implode(', ', $missing) : 'All checks passed';
    return '<span class="score score-' . $tone . '" data-tooltip="' . e($tip) . '" tabindex="0">' . $checklist['passed'] . '/' . $checklist['total'] . '</span>';
}

/** Full checklist list for editor sidebars. */
function checklist_list(array $checklist): string
{
    $html = '<ul class="checklist">';
    foreach ($checklist['items'] as $label => $item) {
        $html .= '<li class="' . ($item['ok'] ? 'ok' : 'bad') . '"><span><strong>' . e($label) . '</strong><br><span class="muted small">' . e($item['note']) . '</span></span></li>';
    }
    return $html . '</ul>';
}
