<?php
/**
 * Builds the demo dataset as ordered [table, row] pairs with explicit ids, so the same data can be
 * inserted through PDO (install.php) or written out as plain SQL (build-seed-sql.php).
 */

const DEMO_ADMIN_EMAIL = 'admin@syscom.local';
const DEMO_ADMIN_PASSWORD = 'Admin@12345';

function seed_rows(?string $adminHash = null): array
{
    $dir = __DIR__ . '/seed';
    $services = require $dir . '/services.php';
    $posts = require $dir . '/posts.php';
    $landing = require $dir . '/landing.php';
    $misc = require $dir . '/misc.php';

    $rows = [];
    $now = time();
    $at = fn(int $daysAgo, int $hour = 10) => date('Y-m-d H:i:s', strtotime("-{$daysAgo} days {$hour}:00", $now));

    $rows[] = ['users', [
        'id' => 1, 'name' => 'Site Admin', 'email' => DEMO_ADMIN_EMAIL, 'role' => 'admin',
        'password_hash' => $adminHash ?? password_hash(DEMO_ADMIN_PASSWORD, PASSWORD_DEFAULT),
    ]];

    foreach ($misc['settings'] as $key => $value) {
        $rows[] = ['settings', ['setting_key' => $key, 'setting_value' => $value]];
    }

    $serviceIds = [];
    foreach ($services as $i => $s) {
        $id = $i + 1;
        $serviceIds[$s['slug']] = $id;
        $rows[] = ['services', [
            'id' => $id, 'name' => $s['name'], 'slug' => $s['slug'], 'icon' => $s['icon'],
            'description' => $s['description'], 'content' => $s['content'], 'features' => $s['features'],
            'primary_keyword' => $s['primary_keyword'], 'external_url' => $s['external_url'],
            'meta_title' => $s['meta_title'], 'meta_description' => $s['meta_description'],
            'sort_order' => $id, 'status' => 'published',
        ]];
    }

    $faqOrder = 0;
    foreach ($misc['faqs'] as [$q, $a]) {
        $rows[] = ['faqs', ['question' => $q, 'answer' => $a, 'sort_order' => ++$faqOrder, 'status' => 'published']];
    }
    foreach ($misc['service_faqs'] as $slug => $faqs) {
        foreach ($faqs as $n => [$q, $a]) {
            $rows[] = ['faqs', ['service_id' => $serviceIds[$slug], 'question' => $q, 'answer' => $a, 'sort_order' => $n + 1, 'status' => 'published']];
        }
    }

    foreach ($posts as $i => $p) {
        $id = $i + 1;
        $published = $at($p['days_ago']);
        $rows[] = ['posts', [
            'id' => $id, 'title' => $p['title'], 'slug' => $p['slug'], 'excerpt' => $p['excerpt'],
            'content' => $p['content'], 'primary_keyword' => $p['primary_keyword'],
            'meta_title' => $p['meta_title'], 'meta_description' => $p['meta_description'],
            'service_id' => $serviceIds[$p['service']] ?? null, 'author_id' => 1, 'status' => 'published',
            'published_at' => $published, 'created_at' => $published, 'updated_at' => $published,
        ]];
        foreach ($p['faqs'] ?? [] as $n => [$q, $a]) {
            $rows[] = ['faqs', ['post_id' => $id, 'question' => $q, 'answer' => $a, 'sort_order' => $n + 1, 'status' => 'published']];
        }
    }

    foreach ($landing as $i => $l) {
        $id = $i + 1;
        $rows[] = ['landing_pages', [
            'id' => $id, 'title' => $l['title'], 'slug' => $l['slug'], 'primary_keyword' => $l['primary_keyword'],
            'meta_title' => $l['meta_title'], 'meta_description' => $l['meta_description'],
            'hero_subtitle' => $l['hero_subtitle'], 'problem' => $l['problem'], 'solution' => $l['solution'],
            'features' => $l['features'], 'benefits' => $l['benefits'], 'use_cases' => $l['use_cases'],
            'content' => $l['content'], 'cta_text' => $l['cta_text'],
            'service_id' => $serviceIds[$l['service']] ?? null, 'status' => 'published',
        ]];
        foreach ($l['faqs'] as $n => [$q, $a]) {
            $rows[] = ['faqs', ['landing_page_id' => $id, 'question' => $q, 'answer' => $a, 'sort_order' => $n + 1, 'status' => 'published']];
        }
    }

    foreach ($misc['keywords'] as [$keyword, $intent, $priority, $target, $status]) {
        $rows[] = ['keywords', [
            'keyword' => $keyword, 'intent' => $intent, 'priority' => $priority, 'target_url' => $target, 'status' => $status,
            'notes' => 'Seed keyword. Search volume not yet researched - validate in Google Keyword Planner or Search Console before prioritising.',
        ]];
    }

    foreach ($misc['internal_links'] as [$keyword, $target, $priority]) {
        $rows[] = ['internal_links', ['keyword' => $keyword, 'target_url' => $target, 'priority' => $priority, 'status' => 'active']];
    }

    foreach ($misc['backlinks'] as [$platform, $type, $source, $target, $anchor, $notes]) {
        $rows[] = ['backlinks', [
            'platform' => $platform, 'type' => $type, 'source_url' => $source, 'target_url' => $target,
            'anchor_text' => $anchor, 'status' => 'opportunity', 'notes' => 'Demo seed record. ' . $notes,
        ]];
    }

    foreach ($misc['leads'] as [$name, $email, $phone, $company, $interest, $message, $source, $status, $daysAgo]) {
        $rows[] = ['leads', [
            'name' => $name, 'email' => $email, 'phone' => $phone, 'company' => $company, 'interest' => $interest,
            'message' => $message, 'source_page' => $source, 'status' => $status,
            'ip_hash' => str_repeat('0', 64), 'created_at' => $at($daysAgo, 11),
        ]];
    }

    return $rows;
}

/** Split a SQL script into statements, respecting quotes and comments. */
function sql_split(string $sql): array
{
    $statements = [];
    $buffer = '';
    $quote = null;
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        if ($quote === null && $ch === '-' && ($sql[$i + 1] ?? '') === '-') {
            $i = strpos($sql, "\n", $i) ?: $len;
            continue;
        }
        if ($quote !== null) {
            $buffer .= $ch;
            if ($ch === '\\') {
                $buffer .= $sql[++$i] ?? '';
            } elseif ($ch === $quote) {
                $quote = null;
            }
            continue;
        }
        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $quote = $ch;
        }
        if ($ch === ';') {
            if (trim($buffer) !== '') {
                $statements[] = trim($buffer);
            }
            $buffer = '';
            continue;
        }
        $buffer .= $ch;
    }
    if (trim($buffer) !== '') {
        $statements[] = trim($buffer);
    }
    return $statements;
}
