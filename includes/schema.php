<?php
/**
 * JSON-LD structured data builders. Each builder only describes content that is visible on the page.
 */

function schema_organization(): array
{
    $org = [
        '@type' => 'Organization',
        '@id'   => absolute_url('/#organization'),
        'name'  => setting('site_name', 'SYSCOM'),
        'url'   => absolute_url('/'),
        'logo'  => absolute_url('/' . (setting('logo_path') ?: 'assets/images/logo.png')),
    ];
    $sameAs = lines(setting('social_profiles', ''));
    if ($sameAs) {
        $org['sameAs'] = $sameAs;
    }
    $email = setting('contact_email', '');
    if ($email !== '') {
        $org['contactPoint'] = ['@type' => 'ContactPoint', 'contactType' => 'sales', 'email' => $email, 'areaServed' => 'IN'];
    }
    return $org;
}

function schema_website(): array
{
    return [
        '@type'     => 'WebSite',
        '@id'       => absolute_url('/#website'),
        'name'      => setting('site_name', 'SYSCOM'),
        'url'       => absolute_url('/'),
        'publisher' => ['@id' => absolute_url('/#organization')],
        'inLanguage'=> 'en-IN',
    ];
}

/** @param array $crumbs [label => path] */
function schema_breadcrumbs(array $crumbs): array
{
    $items = [];
    $position = 1;
    foreach ($crumbs as $label => $path) {
        $items[] = ['@type' => 'ListItem', 'position' => $position++, 'name' => $label, 'item' => absolute_url($path)];
    }
    return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
}

function schema_article(array $post): array
{
    $article = [
        '@type'            => $post['schema_type'] ?? 'Article',
        'headline'         => str_limit($post['title'], 110),
        'description'      => $post['meta_description'] ?: $post['excerpt'],
        'datePublished'    => iso_date($post['published_at']),
        'dateModified'     => iso_date($post['updated_at']),
        'mainEntityOfPage' => absolute_url('/blog/' . $post['slug']),
        'author'           => ['@type' => 'Person', 'name' => $post['author_name'] ?? 'SYSCOM Team'],
        'publisher'        => ['@id' => absolute_url('/#organization')],
    ];
    if (!empty($post['featured_image'])) {
        $article['image'] = absolute_url('/' . ltrim($post['featured_image'], '/'));
    }
    return $article;
}

function schema_service(array $service): array
{
    return [
        '@type'       => 'Service',
        'name'        => $service['name'],
        'description' => $service['meta_description'] ?: $service['description'],
        'url'         => absolute_url('/services/' . $service['slug']),
        'provider'    => ['@id' => absolute_url('/#organization')],
        'areaServed'  => ['@type' => 'Country', 'name' => 'India'],
    ];
}

/** Only call with FAQs that are rendered on the same page. */
function schema_faq(array $faqs): ?array
{
    if (!$faqs) {
        return null;
    }
    return [
        '@type'      => 'FAQPage',
        'mainEntity' => array_map(fn($f) => [
            '@type'          => 'Question',
            'name'           => $f['question'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['answer']],
        ], $faqs),
    ];
}

function schema_script(array $nodes): string
{
    $nodes = array_values(array_filter($nodes));
    $data = ['@context' => 'https://schema.org', '@graph' => $nodes];
    // HEX flags keep "</script>" and quotes inside content from breaking out of the script block.
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    return '<script type="application/ld+json">' . $json . '</script>';
}
