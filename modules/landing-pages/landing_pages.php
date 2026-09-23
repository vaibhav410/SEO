<?php
/**
 * SEO landing pages rendered at /{slug} with the fixed section template in pages/landing.php.
 */

/** Slugs that are real routes or private folders and can never be landing pages. */
const RESERVED_SLUGS = ['services', 'blog', 'resources', 'faq', 'contact', 'admin', 'api', 'assets',
    'config', 'includes', 'modules', 'pages', 'database', 'storage', 'tests', 'docs', 'index', 'search',
    'sitemap', 'robots', 'login', 'logout', 'thank-you', 'router'];

function landing_pages_published(): array
{
    static $cache = null;
    return $cache ??= db_all(
        "SELECT id, title, slug, primary_keyword, hero_subtitle, meta_description, updated_at FROM landing_pages
         WHERE status = 'published' ORDER BY title"
    );
}

function landing_by_slug(string $slug): ?array
{
    return db_one(
        "SELECT l.*, s.name AS service_name, s.slug AS service_slug
         FROM landing_pages l LEFT JOIN services s ON s.id = l.service_id AND s.status = 'published'
         WHERE l.slug = ? AND l.status = 'published'",
        [$slug]
    );
}

function landing_find(int $id): ?array
{
    return db_one('SELECT * FROM landing_pages WHERE id = ?', [$id]);
}

function landing_admin_list(): array
{
    return db_all(
        'SELECT l.*, s.name AS service_name, k.intent,
                (SELECT COUNT(*) FROM leads d WHERE d.source_page = CONCAT(\'/\', l.slug)) AS lead_count,
                (SELECT COUNT(*) FROM faqs f WHERE f.landing_page_id = l.id) AS faq_count
         FROM landing_pages l
         LEFT JOIN services s ON s.id = l.service_id
         LEFT JOIN keywords k ON k.keyword = l.primary_keyword
         ORDER BY l.updated_at DESC'
    );
}

/** @return array{0: array, 1: array} */
function landing_validate(array $input, ?int $id = null): array
{
    $input['slug'] = ($input['slug'] ?? '') !== '' ? slugify($input['slug']) : slugify($input['primary_keyword'] ?? '');
    [$data, $errors] = validate($input, [
        'title'            => 'required|max:200',
        'slug'             => 'required|slug|max:120',
        'primary_keyword'  => 'required|max:150',
        'meta_title'       => 'max:70',
        'meta_description' => 'max:170',
        'hero_subtitle'    => 'required|max:300',
        'problem'          => 'required|min:80',
        'solution'         => 'required|min:80',
        'features'         => 'required',
        'benefits'         => 'required',
        'use_cases'        => 'max:5000',
        'content'          => 'max:60000',
        'cta_text'         => 'required|max:150',
        'canonical_url'    => 'url|max:255',
        'og_title'         => 'max:100',
        'og_description'   => 'max:200',
        'service_id'       => 'int',
        'status'           => 'required|in:draft,published',
    ], ['problem' => 'The problem section', 'solution' => 'The solution section']);

    if (!isset($errors['slug'])) {
        if (in_array($data['slug'], RESERVED_SLUGS, true)) {
            $errors['slug'] = 'This slug is reserved by the site. Choose another.';
        } elseif (db_slug_taken('landing_pages', $data['slug'], $id)) {
            $errors['slug'] = 'Another landing page already uses this slug.';
        }
    }
    // Guard against thin/doorway pages: require a minimum number of real feature points.
    if (!isset($errors['features']) && count(lines($data['features'])) < 3) {
        $errors['features'] = 'Add at least three features (one per line) so the page is genuinely useful.';
    }
    $data['service_id'] = $data['service_id'] ? (int) $data['service_id'] : null;
    $data['hero_subtitle'] = (string) $data['hero_subtitle'];
    return [$data, $errors];
}

function landing_save(array $data, ?int $id = null): int
{
    if ($id) {
        db_update('landing_pages', $id, $data);
        return $id;
    }
    return db_insert('landing_pages', $data);
}
