<?php
/**
 * Blog articles: public queries, search and admin persistence.
 */

const POSTS_PER_PAGE = 9;

/** Published = status published AND publish date reached (supports scheduling). */
const POST_PUBLIC_SQL = "p.status = 'published' AND p.published_at IS NOT NULL AND p.published_at <= NOW()";

function posts_published_count(string $search = '', ?int $categoryId = null): int
{
    [$where, $params] = post_search_clause($search, $categoryId);
    return (int) db_value('SELECT COUNT(*) FROM posts p WHERE ' . POST_PUBLIC_SQL . $where, $params);
}

function posts_published(int $limit, int $offset = 0, string $search = '', ?int $categoryId = null): array
{
    [$where, $params] = post_search_clause($search, $categoryId);
    return db_all(
        'SELECT p.id, p.title, p.slug, p.excerpt, p.content, p.featured_image, p.featured_image_alt, p.published_at, c.name AS category_name, c.slug AS category_slug
         FROM posts p LEFT JOIN categories c ON c.id = p.category_id WHERE ' . POST_PUBLIC_SQL . $where . '
         ORDER BY p.published_at DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
        $params
    );
}

/** LIKE search on title/excerpt; wildcards in user input are escaped. */
function post_search_clause(string $search, ?int $categoryId = null): array
{
    $search = trim($search);
    $category = $categoryId ? [' AND p.category_id = ?', [$categoryId]] : ['', []];
    if ($search === '') {
        return $category;
    }
    $like = '%' . addcslashes($search, '%_\\') . '%';
    return [$category[0] . ' AND (p.title LIKE ? OR p.excerpt LIKE ? OR p.primary_keyword LIKE ?)', [...$category[1], $like, $like, $like]];
}

function post_by_slug(string $slug): ?array
{
    return db_one(
        'SELECT p.*, u.name AS author_name, s.name AS service_name, s.slug AS service_slug, c.name AS category_name, c.slug AS category_slug
         FROM posts p
         LEFT JOIN users u ON u.id = p.author_id
         LEFT JOIN categories c ON c.id = p.category_id
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

const POST_SCHEMA_TYPES = ['Article', 'BlogPosting', 'TechArticle'];
const POST_SORTS = ['title' => 'p.title', 'status' => 'p.status', 'published' => 'p.published_at', 'updated' => 'p.updated_at', 'category' => 'c.name'];

/** @param array $f status, q, category (id) */
function posts_admin_list(array $f, int $limit, int $offset, string $orderBy = ' ORDER BY p.updated_at DESC'): array
{
    [$where, $params] = posts_admin_filter($f);
    return db_all(
        'SELECT p.*, u.name AS author, c.name AS category_name
         FROM posts p LEFT JOIN users u ON u.id = p.author_id LEFT JOIN categories c ON c.id = p.category_id' . $where
        . $orderBy . ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
        $params
    );
}

function posts_admin_count(array $f): int
{
    [$where, $params] = posts_admin_filter($f);
    return (int) db_value('SELECT COUNT(*) FROM posts p LEFT JOIN categories c ON c.id = p.category_id' . $where, $params);
}

function posts_admin_filter(array $f): array
{
    $clauses = [];
    $params = [];
    $status = $f['status'] ?? '';
    if ($status === 'scheduled') {
        $clauses[] = "p.status = 'published' AND p.published_at > NOW()";
    } elseif (in_array($status, ['draft', 'published'], true)) {
        $clauses[] = 'p.status = ?';
        $params[] = $status;
    }
    if (($f['q'] ?? '') !== '') {
        $clauses[] = '(p.title LIKE ? OR p.primary_keyword LIKE ?)';
        $like = '%' . addcslashes($f['q'], '%_\\') . '%';
        array_push($params, $like, $like);
    }
    if (!empty($f['category'])) {
        $clauses[] = 'p.category_id = ?';
        $params[] = (int) $f['category'];
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
        'canonical_url'      => 'url|max:255',
        'og_title'           => 'max:100',
        'og_description'     => 'max:200',
        'schema_type'        => 'in:' . implode(',', POST_SCHEMA_TYPES),
        'featured_image_alt' => 'max:200',
        'category_id'        => 'int',
        'service_id'         => 'int',
        'status'             => 'required|in:draft,published',
        'published_at'       => 'max:25',
    ], ['content' => 'Article content', 'canonical_url' => 'Canonical URL', 'og_title' => 'OG title', 'og_description' => 'OG description']);

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
    $data['schema_type'] = $data['schema_type'] ?: 'Article';
    foreach (['service_id' => 'services', 'category_id' => 'categories'] as $field => $table) {
        $data[$field] = $data[$field] ? (int) $data[$field] : null;
        if ($data[$field] && !db_value("SELECT 1 FROM $table WHERE id = ?", [$data[$field]])) {
            $errors[$field] = 'Choose a valid option.';
        }
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
