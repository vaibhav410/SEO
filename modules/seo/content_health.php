<?php
/**
 * Content health: checks the site's own database content for on-page SEO problems, without any
 * HTTP requests. Feeds the dashboard "SEO issues" panel.
 */

/**
 * @return array<array{severity: string, item: string, type: string, message: string, edit: ?string}>
 */
function content_health_issues(): array
{
    $issues = [];
    $add = function (string $severity, string $type, string $item, string $message, ?string $edit) use (&$issues) {
        $issues[] = compact('severity', 'type', 'item', 'message', 'edit');
    };

    $pages = [];
    foreach (db_all("SELECT id, name AS title, meta_title, meta_description, primary_keyword, content, 'service' AS kind FROM services WHERE status = 'published'") as $r) {
        $pages[] = $r + ['edit' => '/admin/services/edit.php?id=' . $r['id'], 'label' => 'Service'];
    }
    foreach (db_all("SELECT p.id, p.title, p.meta_title, p.meta_description, p.primary_keyword, p.content, p.featured_image, p.featured_image_alt, 'post' AS kind FROM posts p WHERE " . POST_PUBLIC_SQL) as $r) {
        $pages[] = $r + ['edit' => '/admin/posts/edit.php?id=' . $r['id'], 'label' => 'Article'];
    }
    foreach (db_all("SELECT id, title, meta_title, meta_description, primary_keyword, CONCAT_WS(' ', problem, solution, features, benefits, use_cases, content) AS content, 'landing' AS kind FROM landing_pages WHERE status = 'published'") as $r) {
        $pages[] = $r + ['edit' => '/admin/landing-pages/edit.php?id=' . $r['id'], 'label' => 'Landing page'];
    }

    $titles = [];
    $descriptions = [];
    $keywords = [];
    foreach ($pages as $p) {
        $metaTitle = $p['meta_title'] ?: $p['title'];
        $titles[mb_strtolower($metaTitle)][] = $p;
        $item = $p['label'] . ': ' . $p['title'];

        if (!$p['meta_title']) {
            $add('warning', 'Meta title', $item, 'No custom meta title; the page title is used instead.', $p['edit']);
        } elseif (seo_length_status($p['meta_title'], 25, SEO_TITLE_MAX) !== 'ok') {
            $add('warning', 'Meta title', $item, 'Meta title is ' . mb_strlen($p['meta_title']) . ' characters (aim for 25–60).', $p['edit']);
        }
        if (!$p['meta_description']) {
            $add('error', 'Meta description', $item, 'Missing meta description.', $p['edit']);
        } else {
            $descriptions[mb_strtolower($p['meta_description'])][] = $p;
            if (seo_length_status($p['meta_description'], 70, SEO_DESCRIPTION_MAX) !== 'ok') {
                $add('warning', 'Meta description', $item, 'Meta description is ' . mb_strlen($p['meta_description']) . ' characters (aim for 70–160).', $p['edit']);
            }
        }
        if ($p['primary_keyword']) {
            $keywords[mb_strtolower($p['primary_keyword'])][] = $p;
            if (!keyword_in_text(mb_strtolower($p['primary_keyword']), $metaTitle . ' ' . $p['title'])) {
                $add('info', 'Keyword', $item, '“' . $p['primary_keyword'] . '” does not appear in the title.', $p['edit']);
            }
        } elseif ($p['kind'] !== 'service') {
            $add('info', 'Keyword', $item, 'No primary keyword set.', $p['edit']);
        }
        if ($p['kind'] === 'post') {
            $words = str_word_count(markdown_text($p['content']));
            if ($words < 300) {
                $add('warning', 'Thin content', $item, "Only $words words. Consider expanding with genuinely useful detail.", $p['edit']);
            }
            if ($p['featured_image'] && !$p['featured_image_alt']) {
                $add('warning', 'Image alt', $item, 'Featured image has no alt text.', $p['edit']);
            }
        }
    }

    foreach ($titles as $group) {
        if (count($group) > 1) {
            $add('error', 'Duplicate title', implode(' / ', array_column($group, 'title')), 'These pages share the same meta title.', $group[0]['edit']);
        }
    }
    foreach ($descriptions as $group) {
        if (count($group) > 1) {
            $add('error', 'Duplicate description', implode(' / ', array_column($group, 'title')), 'These pages share the same meta description.', $group[0]['edit']);
        }
    }
    foreach ($keywords as $kw => $group) {
        if (count($group) > 1) {
            $add('warning', 'Cannibalisation', '“' . $kw . '”', count($group) . ' pages target the same primary keyword: ' . implode(', ', array_column($group, 'title')) . '. Differentiate them or consolidate.', $group[0]['edit']);
        }
    }

    foreach (db_all("SELECT id, keyword, target_url, status FROM keywords WHERE status IN ('targeting', 'mapped')") as $k) {
        if (!$k['target_url']) {
            $add('warning', 'Keyword map', '“' . $k['keyword'] . '”', 'Active keyword has no target URL.', '/admin/keywords/edit.php?id=' . $k['id']);
        } elseif (!preg_match('#^https?://#', $k['target_url']) && !content_at_path($k['target_url'])) {
            $add('error', 'Keyword map', '“' . $k['keyword'] . '”', 'Target ' . $k['target_url'] . ' is not a published page.', '/admin/keywords/edit.php?id=' . $k['id']);
        }
    }

    $order = ['error' => 0, 'warning' => 1, 'info' => 2];
    usort($issues, fn($a, $b) => $order[$a['severity']] <=> $order[$b['severity']]);
    return $issues;
}
