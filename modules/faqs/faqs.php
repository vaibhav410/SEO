<?php
/**
 * FAQs attached to a service, article, landing page, or general (/faq).
 */

const FAQ_OWNERS = ['service' => 'service_id', 'post' => 'post_id', 'landing' => 'landing_page_id'];

/** Published FAQs for one owner, or general FAQs when $type is 'general'. */
function faqs_for(string $type, ?int $id = null): array
{
    if ($type === 'general') {
        return db_all(
            "SELECT id, question, answer FROM faqs WHERE service_id IS NULL AND post_id IS NULL AND landing_page_id IS NULL
             AND status = 'published' ORDER BY sort_order, id"
        );
    }
    $column = FAQ_OWNERS[$type] ?? null;
    if (!$column) {
        return [];
    }
    return db_all("SELECT id, question, answer FROM faqs WHERE {$column} = ? AND status = 'published' ORDER BY sort_order, id", [$id]);
}

/** FAQs of every published service, grouped by service for the /faq page. */
function faqs_by_service(): array
{
    $rows = db_all(
        "SELECT f.question, f.answer, s.name, s.slug FROM faqs f JOIN services s ON s.id = f.service_id
         WHERE f.status = 'published' AND s.status = 'published' ORDER BY s.sort_order, f.sort_order, f.id"
    );
    $groups = [];
    foreach ($rows as $row) {
        $groups[$row['slug']]['name'] = $row['name'];
        $groups[$row['slug']]['faqs'][] = $row;
    }
    return $groups;
}

function faqs_admin_list(string $owner = ''): array
{
    $where = '';
    if (isset(FAQ_OWNERS[$owner])) {
        $where = ' WHERE f.' . FAQ_OWNERS[$owner] . ' IS NOT NULL';
    } elseif ($owner === 'general') {
        $where = ' WHERE f.service_id IS NULL AND f.post_id IS NULL AND f.landing_page_id IS NULL';
    }
    return db_all(
        'SELECT f.*, s.name AS service_name, p.title AS post_title, l.title AS landing_title
         FROM faqs f
         LEFT JOIN services s ON s.id = f.service_id
         LEFT JOIN posts p ON p.id = f.post_id
         LEFT JOIN landing_pages l ON l.id = f.landing_page_id' . $where . '
         ORDER BY f.updated_at DESC'
    );
}

function faq_find(int $id): ?array
{
    return db_one('SELECT * FROM faqs WHERE id = ?', [$id]);
}

/**
 * The form sends a single "owner" value like "service:3", "post:5", "landing:1" or "general".
 * @return array{0: array, 1: array}
 */
function faq_validate(array $input): array
{
    [$data, $errors] = validate($input, [
        'question'   => 'required|max:255',
        'answer'     => 'required|max:3000',
        'sort_order' => 'int',
        'status'     => 'required|in:draft,published',
    ]);
    $data += ['service_id' => null, 'post_id' => null, 'landing_page_id' => null];
    $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

    $owner = (string) ($input['owner'] ?? 'general');
    if ($owner !== 'general') {
        [$type, $ownerId] = array_pad(explode(':', $owner, 2), 2, '');
        $tables = ['service' => 'services', 'post' => 'posts', 'landing' => 'landing_pages'];
        if (!isset($tables[$type]) || !ctype_digit($ownerId) || !db_value("SELECT 1 FROM {$tables[$type]} WHERE id = ?", [(int) $ownerId])) {
            $errors['owner'] = 'Choose where this FAQ should appear.';
        } else {
            $data[FAQ_OWNERS[$type]] = (int) $ownerId;
        }
    }
    return [$data, $errors];
}

function faq_owner_value(array $faq): string
{
    foreach (FAQ_OWNERS as $type => $column) {
        if (!empty($faq[$column])) {
            return $type . ':' . $faq[$column];
        }
    }
    return 'general';
}
