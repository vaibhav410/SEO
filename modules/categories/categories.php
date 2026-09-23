<?php
/**
 * Blog categories (/blog/category/{slug}).
 */

function categories_all(): array
{
    static $cache = null;
    return $cache ??= db_all(
        'SELECT c.*, (SELECT COUNT(*) FROM posts p WHERE p.category_id = c.id AND ' . POST_PUBLIC_SQL . ') AS post_count
         FROM categories c ORDER BY c.sort_order, c.name'
    );
}

function category_by_slug(string $slug): ?array
{
    return db_one('SELECT * FROM categories WHERE slug = ?', [$slug]);
}

function category_find(int $id): ?array
{
    return db_one('SELECT * FROM categories WHERE id = ?', [$id]);
}

/** @return array{0: array, 1: array} */
function category_validate(array $input, ?int $id = null): array
{
    $input['slug'] = ($input['slug'] ?? '') !== '' ? slugify($input['slug']) : slugify($input['name'] ?? '');
    [$data, $errors] = validate($input, [
        'name'             => 'required|max:80',
        'slug'             => 'required|slug|max:80',
        'description'      => 'max:300',
        'meta_description' => 'max:170',
        'sort_order'       => 'int',
    ]);
    if (!isset($errors['slug']) && db_slug_taken('categories', $data['slug'], $id)) {
        $errors['slug'] = 'Another category already uses this slug.';
    }
    $data['description'] = (string) $data['description'];
    $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
    return [$data, $errors];
}
