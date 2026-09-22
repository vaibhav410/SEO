<?php
/**
 * Blog articles: public queries, search and admin persistence.
 */

const POSTS_PER_PAGE = 9;

/** Published = status published AND publish date reached (supports scheduling). */
const POST_PUBLIC_SQL = "p.status = 'published' AND p.published_at IS NOT NULL AND p.published_at <= NOW()";

function posts_published_count(string $search = ''): int
{
    [$where, $params] = post_search_clause($search);
    return (int) db_value('SELECT COUNT(*) FROM posts p WHERE ' . POST_PUBLIC_SQL . $where, $params);
}

function posts_published(int $limit, int $offset = 0, string $search = ''): array
{
    [$where, $params] = post_search_clause($search);
    return db_all(
        'SELECT p.id, p.title, p.slug, p.excerpt, p.content, p.featured_image, p.featured_image_alt, p.published_at
         FROM posts p WHERE ' . POST_PUBLIC_SQL . $where . '
         ORDER BY p.published_at DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
        $params
    );
}

/** LIKE search on title/excerpt; wildcards in user input are escaped. */
function post_search_clause(string $search): array
{
    $search = trim($search);
    if ($search === '') {
        return ['', []];
    }
    $like = '%' . addcslashes($search, '%_\\') . '%';
    return [' AND (p.title LIKE ? OR p.excerpt LIKE ? OR p.primary_keyword LIKE ?)', [$like, $like, $like]];
}

function post_by_slug(string $slug): ?array
{
    return db_one(
        'SELECT p.*, u.name AS author_name, s.name AS service_name, s.slug AS service_slug
         FROM posts p
         LEFT JOIN users u ON u.id = p.author_id
         LEFT JOIN services s ON s.id = p.service_id AND s.status = \'published\'
         WHERE p.slug = ? AND ' . POST_PUBLIC_SQL,
        [$slug]
    );
}

/** Related articles: same service first, then most recent. */
function posts_related(array $post, int $limit = 3): array
{
    return db_all(
        'SELECT p.id, p.title, p.slug, p.excerpt, p.content, p.featured_image, p.featured_image_alt, p.published_at
         FROM posts p WHERE ' . POST_PUBLIC_SQL . ' AND p.id <> ?
         ORDER BY (p.service_id <=> ?) DESC, p.published_at DESC LIMIT ' . (int) $limit,
        [$post['id'], $post['service_id']]
    );
}

function post_find(int $id): ?array
{
    return db_one('SELECT * FROM posts WHERE id = ?', [$id]);
}

function posts_admin_list(string $status, string $search, int $limit, int $offset): array
{
    [$where, $params] = posts_admin_filter($status, $search);
    return db_all(
        'SELECT p.id, p.title, p.slug, p.status, p.published_at, p.updated_at, p.meta_title, p.meta_description, p.primary_keyword, u.name AS author
         FROM posts p LEFT JOIN users u ON u.id = p.author_id' . $where . '
         ORDER BY p.updated_at DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
        $params
    );
}

function posts_admin_count(string $status, string $search): int
{
    [$where, $params] = posts_admin_filter($status, $search);
    return (int) db_value('SELECT COUNT(*) FROM posts p' . $where, $params);
}

function posts_admin_filter(string $status, string $search): array
{
    $clauses = [];
    $params = [];
    if (in_array($status, ['draft', 'published'], true)) {
        $clauses[] = 'p.status = ?';
        $params[] = $status;
    }
    if ($search !== '') {
        $clauses[] = 'p.title LIKE ?';
        $params[] = '%' . addcslashes($search, '%_\\') . '%';
    }
    return [$clauses ? ' WHERE ' . implode(' AND ', $clauses) : '', $params];
}

/** @return array{0: array, 1: array} */
function post_validate(array $input, ?int $id = null): array
{
    $input['slug'] = ($input['slug'] ?? '') !== '' ? slugify($input['slug']) : slugify($input['title'] ?? '');
    [$data, $errors] = validate($input, [
        'title'              => 'required|max:200',
        'slug'               => 'required|slug|max:120',
        'excerpt'            => 'required|max:300',
        'content'            => 'required|min:50',
        'primary_keyword'    => 'max:150',
        'meta_title'         => 'max:70',
        'meta_description'   => 'max:170',
        'featured_image_alt' => 'max:200',
        'service_id'         => 'int',
        'status'             => 'required|in:draft,published',
        'published_at'       => 'max:25',
    ], ['content' => 'Article content']);

    if (!isset($errors['slug']) && db_slug_taken('posts', $data['slug'], $id)) {
        $errors['slug'] = 'Another article already uses this slug. Choose a different one.';
    }
    if ($data['published_at']) {
        $ts = strtotime($data['published_at']);
        $ts === false ? $errors['published_at'] = 'Enter a valid publish date.' : $data['published_at'] = date('Y-m-d H:i:s', $ts);
    }
    if ($data['status'] === 'published' && !$data['published_at']) {
        $data['published_at'] = date('Y-m-d H:i:s');
    }
    $data['service_id'] = $data['service_id'] ? (int) $data['service_id'] : null;
    if ($data['service_id'] && !service_find($data['service_id'])) {
        $errors['service_id'] = 'Choose a valid service.';
    }
    return [$data, $errors];
}

function post_save(array $data, ?int $id, int $authorId): int
{
    if ($id) {
        db_update('posts', $id, $data);
        return $id;
    }
    $data['author_id'] = $authorId;
    return db_insert('posts', $data);
}

function post_delete(int $id): void
{
    $post = post_find($id);
    db_delete('posts', $id);
    if ($post && $post['featured_image']) {
        delete_upload($post['featured_image']);
    }
}
