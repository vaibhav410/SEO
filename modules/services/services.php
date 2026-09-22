<?php
/**
 * Services: public queries and admin persistence.
 */

const SERVICE_ICONS = ['server', 'wordpress', 'layers', 'cpu', 'globe', 'mail', 'lock', 'users', 'shield', 'zap'];

/** Published services for navigation, footer and listings (cached for the request). */
function services_published(): array
{
    static $cache = null;
    return $cache ??= db_all(
        "SELECT id, name, slug, icon, description FROM services WHERE status = 'published' ORDER BY sort_order, name"
    );
}

function service_by_slug(string $slug): ?array
{
    return db_one("SELECT * FROM services WHERE slug = ? AND status = 'published'", [$slug]);
}

function service_find(int $id): ?array
{
    return db_one('SELECT * FROM services WHERE id = ?', [$id]);
}

function services_admin_list(): array
{
    return db_all(
        'SELECT s.id, s.name, s.slug, s.status, s.sort_order, s.meta_title, s.meta_description, s.updated_at,
                (SELECT COUNT(*) FROM faqs f WHERE f.service_id = s.id) AS faq_count
         FROM services s ORDER BY s.sort_order, s.name'
    );
}

/** @return array{0: array, 1: array} [clean data, errors] */
function service_validate(array $input, ?int $id = null): array
{
    $input['slug'] = ($input['slug'] ?? '') !== '' ? slugify($input['slug']) : slugify($input['name'] ?? '');
    [$data, $errors] = validate($input, [
        'name'             => 'required|max:150',
        'slug'             => 'required|slug|max:120',
        'icon'             => 'required|in:' . implode(',', SERVICE_ICONS),
        'description'      => 'required|max:300',
        'content'          => 'required',
        'features'         => 'max:3000',
        'primary_keyword'  => 'max:150',
        'external_url'     => 'url|max:255',
        'meta_title'       => 'max:70',
        'meta_description' => 'max:170',
        'sort_order'       => 'int',
        'status'           => 'required|in:draft,published',
    ]);
    if (!isset($errors['slug']) && db_slug_taken('services', $data['slug'], $id)) {
        $errors['slug'] = 'Another service already uses this slug.';
    }
    $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
    return [$data, $errors];
}

function service_save(array $data, ?int $id = null): int
{
    if ($id) {
        db_update('services', $id, $data);
        return $id;
    }
    return db_insert('services', $data);
}
