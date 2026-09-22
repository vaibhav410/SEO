<?php
/**
 * A small, safe Markdown subset for CMS content.
 *
 * Everything is HTML-escaped first, then a fixed set of constructs is converted, so editors
 * cannot inject scripts or arbitrary markup. Supported:
 *   ## / ### / #### headings (a single "#" becomes h2 - the page template owns the only H1)
 *   paragraphs, - or * bullet lists, 1. numbered lists, > quotes, --- rules, | pipe | tables |
 *   **bold**, *italic*, `code`, [text](url)
 */

function render_markdown(?string $text): string
{
    $lines = preg_split('/\R/', trim((string) $text));
    $html = [];
    $paragraph = [];
    $list = null; // ['tag' => 'ul'|'ol', 'items' => []]
    $quote = [];
    $table = [];

    $flush = function () use (&$html, &$paragraph, &$list, &$quote, &$table) {
        if ($paragraph) {
            $html[] = '<p>' . md_inline(implode(' ', $paragraph)) . '</p>';
            $paragraph = [];
        }
        if ($list) {
            $items = array_map(fn($i) => '<li>' . md_inline($i) . '</li>', $list['items']);
            $html[] = '<' . $list['tag'] . '>' . implode('', $items) . '</' . $list['tag'] . '>';
            $list = null;
        }
        if ($quote) {
            $html[] = '<blockquote><p>' . md_inline(implode(' ', $quote)) . '</p></blockquote>';
            $quote = [];
        }
        if ($table) {
            $html[] = md_table($table);
            $table = [];
        }
    };

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '') {
            $flush();
            continue;
        }
        if (preg_match('/^(#{1,4})\s+(.+)$/', $trimmed, $m)) {
            $flush();
            $level = max(2, strlen($m[1]));
            $html[] = sprintf('<h%d id="%s">%s</h%1$d>', $level, e(slugify(strip_tags($m[2]))), md_inline($m[2]));
            continue;
        }
        if (preg_match('/^(-{3,}|\*{3,})$/', $trimmed)) {
            $flush();
            $html[] = '<hr>';
            continue;
        }
        if (str_starts_with($trimmed, '|')) {
            if (!$table) {
                $flush();
            }
            $table[] = $trimmed;
            continue;
        }
        if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $m) || preg_match('/^\d+[.)]\s+(.+)$/', $trimmed, $m)) {
            $tag = preg_match('/^\d/', $trimmed) ? 'ol' : 'ul';
            if (!$list || $list['tag'] !== $tag) {
                $flush();
                $list = ['tag' => $tag, 'items' => []];
            }
            $list['items'][] = $m[1];
            continue;
        }
        if (preg_match('/^>\s?(.*)$/', $trimmed, $m)) {
            if (!$quote) {
                $flush();
            }
            $quote[] = $m[1];
            continue;
        }
        if ($list && preg_match('/^\s{2,}\S/', $line)) {
            // Indented continuation of the previous list item.
            $list['items'][count($list['items']) - 1] .= ' ' . $trimmed;
            continue;
        }
        if ($list || $quote || $table) {
            $flush();
        }
        $paragraph[] = $trimmed;
    }
    $flush();

    return implode("\n", $html);
}

function md_inline(string $text): string
{
    $text = e($text);

    // Protect inline code from further formatting.
    $codes = [];
    $text = preg_replace_callback('/`([^`]+)`/', function ($m) use (&$codes) {
        $codes[] = '<code>' . $m[1] . '</code>';
        return "\x1A" . (count($codes) - 1) . "\x1A";
    }, $text);

    $text = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) {
        $href = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!md_safe_href($href)) {
            return $m[1];
        }
        $external = preg_match('#^https?://#i', $href) && !str_starts_with($href, config('app.base_url'));
        if (str_starts_with($href, '/')) {
            $href = url($href);
        }
        $rel = $external ? ' rel="noopener"' : '';
        return '<a href="' . e($href) . '"' . $rel . '>' . $m[1] . '</a>';
    }, $text);

    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?!\w)/', '<em>$1</em>', $text);

    return preg_replace_callback("/\x1A(\d+)\x1A/", fn($m) => $codes[(int) $m[1]], $text);
}

function md_safe_href(string $href): bool
{
    return (bool) preg_match('#^(https?://|/|\#|mailto:)#i', $href) && !preg_match('/[\x00-\x1F<>"]/', $href);
}

function md_table(array $rows): string
{
    $cells = fn($row) => array_map('trim', explode('|', trim($row, " |")));
    $rows = array_values(array_filter($rows, fn($r) => !preg_match('/^\|?[\s:|-]+\|?$/', $r)));
    if (!$rows) {
        return '';
    }
    $head = array_shift($rows);
    $out = '<div class="table-wrap"><table><thead><tr>';
    foreach ($cells($head) as $c) {
        $out .= '<th scope="col">' . md_inline($c) . '</th>';
    }
    $out .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $out .= '<tr>' . implode('', array_map(fn($c) => '<td>' . md_inline($c) . '</td>', $cells($row))) . '</tr>';
    }
    return $out . '</tbody></table></div>';
}

/** H2 headings for an on-page table of contents. @return array<array{id:string,text:string}> */
function markdown_toc(?string $text): array
{
    preg_match_all('/^#{1,2}\s+(.+)$/m', (string) $text, $m);
    return array_map(fn($h) => ['id' => slugify(strip_tags($h)), 'text' => trim(str_replace(['**', '`'], '', $h))], $m[1]);
}

/** Plain text version, used for excerpts, word counts and meta fallbacks. */
function markdown_text(?string $text): string
{
    $plain = preg_replace(['/\[([^\]]+)\]\([^)]+\)/', '/[#*`>|]/', '/^\s*[-]\s+/m'], ['$1', '', ''], (string) $text);
    return trim(preg_replace('/\s+/', ' ', $plain));
}
