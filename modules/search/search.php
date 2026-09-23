<?php
/**
 * Site search (public) and global admin search. LIKE queries with escaped wildcards and bound params.
 */

function search_like(string $q): string
{
    return '%' . addcslashes($q, '%_\\') . '%';
}

/**
 * Public search across published services, landing pages, articles and FAQs.
 * @return array<array{type: string, title: string, url: string, excerpt: string}>
 */
function site_search(string $q, int $limitPerType = 10): array
{
    $q = trim($q);
    if (mb_strlen($q) < 2) {
        return [];
    }
    $like = search_like($q);
    $lim = (int) $limitPerType;
    $results = [];
    foreach (db_all("SELECT name, slug, description FROM services WHERE status = 'published' AND (name LIKE ? OR description LIKE ? OR content LIKE ?) ORDER BY sort_order LIMIT $lim", [$like, $like, $like]) as $r) {
        $results[] = ['type' => 'Service', 'title' => $r['name'], 'url' => '/services/' . $r['slug'], 'excerpt' => $r['description']];
    }
    foreach (db_all("SELECT title, slug, hero_subtitle FROM landing_pages WHERE status = 'published' AND (title LIKE ? OR primary_keyword LIKE ? OR hero_subtitle LIKE ? OR solution LIKE ?) LIMIT $lim", [$like, $like, $like, $like]) as $r) {
        $results[] = ['type' => 'Solution', 'title' => $r['title'], 'url' => '/' . $r['slug'], 'excerpt' => $r['hero_subtitle']];
    }
    foreach (db_all('SELECT p.title, p.slug, p.excerpt FROM posts p WHERE ' . POST_PUBLIC_SQL . " AND (p.title LIKE ? OR p.excerpt LIKE ? OR p.content LIKE ?) ORDER BY p.published_at DESC LIMIT $lim", [$like, $like, $like]) as $r) {
        $results[] = ['type' => 'Article', 'title' => $r['title'], 'url' => '/blog/' . $r['slug'], 'excerpt' => $r['excerpt']];
    }
    foreach (db_all("SELECT f.question, f.answer, s.slug AS service_slug, p.slug AS post_slug, l.slug AS landing_slug
                     FROM faqs f LEFT JOIN services s ON s.id = f.service_id LEFT JOIN posts p ON p.id = f.post_id LEFT JOIN landing_pages l ON l.id = f.landing_page_id
                     WHERE f.status = 'published' AND (f.question LIKE ? OR f.answer LIKE ?) LIMIT $lim", [$like, $like]) as $r) {
        $url = $r['service_slug'] ? '/services/' . $r['service_slug'] : ($r['post_slug'] ? '/blog/' . $r['post_slug'] : ($r['landing_slug'] ? '/' . $r['landing_slug'] : '/faq'));
        $results[] = ['type' => 'FAQ', 'title' => $r['question'], 'url' => $url, 'excerpt' => str_limit($r['answer'], 160)];
    }
    return $results;
}

/**
 * Admin global search across content, SEO records and leads.
 * @return array<array{type: string, title: string, url: string, meta: string}>
 */
function admin_search(string $q, int $limitPerType = 6): array
{
    $q = trim($q);
    if (mb_strlen($q) < 2) {
        return [];
    }
    $like = search_like($q);
    $lim = (int) $limitPerType;
    $out = [];
    $queries = [
        ['Article', "SELECT id, title, status AS meta FROM posts WHERE title LIKE ? OR slug LIKE ? OR primary_keyword LIKE ? ORDER BY updated_at DESC LIMIT $lim", 3, '/admin/posts/edit.php?id='],
        ['Service', "SELECT id, name AS title, status AS meta FROM services WHERE name LIKE ? OR slug LIKE ? OR primary_keyword LIKE ? LIMIT $lim", 3, '/admin/services/edit.php?id='],
        ['Landing page', "SELECT id, title, CONCAT('/', slug) AS meta FROM landing_pages WHERE title LIKE ? OR slug LIKE ? OR primary_keyword LIKE ? LIMIT $lim", 3, '/admin/landing-pages/edit.php?id='],
        ['Keyword', "SELECT id, keyword AS title, intent AS meta FROM keywords WHERE keyword LIKE ? OR notes LIKE ? LIMIT $lim", 2, '/admin/keywords/view.php?id='],
        ['Lead', "SELECT id, name AS title, CONCAT(email, ' · ', status) AS meta FROM leads WHERE name LIKE ? OR email LIKE ? OR company LIKE ? ORDER BY created_at DESC LIMIT $lim", 3, '/admin/leads/view.php?id='],
        ['Backlink', "SELECT id, platform AS title, status AS meta FROM backlinks WHERE platform LIKE ? OR source_url LIKE ? OR notes LIKE ? LIMIT $lim", 3, '/admin/backlinks/view.php?id='],
        ['FAQ', "SELECT id, question AS title, status AS meta FROM faqs WHERE question LIKE ? OR answer LIKE ? LIMIT $lim", 2, '/admin/faqs/edit.php?id='],
        ['Audit', "SELECT id, url AS title, CONCAT(overall_score, '/100') AS meta FROM seo_audits WHERE url LIKE ? ORDER BY created_at DESC LIMIT $lim", 1, '/admin/audits/view.php?id='],
    ];
    foreach ($queries as [$type, $sql, $n, $link]) {
        foreach (db_all($sql, array_fill(0, $n, $like)) as $r) {
            $out[] = ['type' => $type, 'title' => $r['title'], 'url' => $link . $r['id'], 'meta' => (string) $r['meta']];
        }
    }
    return $out;
}
