<?php
/**
 * Reusable public UI pieces: icons, cards, FAQ lists and the lead form.
 */

/** Inline SVG icons (decorative, hidden from screen readers). */
function icon(string $name, string $class = 'icon'): string
{
    $paths = [
        'server'    => '<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 7.5h.01M7 16.5h.01"/>',
        'wordpress' => '<circle cx="12" cy="12" r="9"/><path d="M5 9l3.5 10L12 10l3.5 9L19 9M4 9h5M15 9h5"/>',
        'layers'    => '<path d="M12 3l9 5-9 5-9-5 9-5z"/><path d="M3 13l9 5 9-5"/>',
        'cpu'       => '<rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/>',
        'globe'     => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/>',
        'mail'      => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'lock'      => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/>',
        'users'     => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0113 0M16 4.5a3.5 3.5 0 010 7M18 14a6 6 0 013.5 6"/>',
        'check'     => '<path d="M5 12.5l4.5 4.5L19 7"/>',
        'arrow'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'shield'    => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3z"/><path d="M9 12l2 2 4-4"/>',
        'zap'       => '<path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z"/>',
        'headset'   => '<path d="M4 14v-2a8 8 0 0116 0v2"/><rect x="3" y="14" width="4" height="6" rx="1.5"/><rect x="17" y="14" width="4" height="6" rx="1.5"/>',
        'trending'  => '<path d="M3 17l6-6 4 4 8-8M15 7h6v6"/>',
        'book'      => '<path d="M4 5a2 2 0 012-2h13v16H6a2 2 0 00-2 2V5z"/><path d="M4 19a2 2 0 012-2h13"/>',
        'menu'      => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'search'    => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
        'external'  => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 01-1 1H5a1 1 0 01-1-1V7a1 1 0 011-1h5"/>',
    ];
    $path = $paths[$name] ?? $paths['server'];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
}

function service_card(array $s): string
{
    return '<article class="card service-card">'
        . '<div class="card-icon">' . icon($s['icon']) . '</div>'
        . '<h3><a href="' . e(url('/services/' . $s['slug'])) . '" class="stretched">' . e($s['name']) . '</a></h3>'
        . '<p>' . e($s['description']) . '</p>'
        . '<span class="card-link" aria-hidden="true">Learn more ' . icon('arrow', 'icon icon-sm') . '</span>'
        . '</article>';
}

function post_card(array $p): string
{
    $image = '';
    if (!empty($p['featured_image'])) {
        $image = '<img src="' . e(url('/' . ltrim($p['featured_image'], '/'))) . '" alt="' . e($p['featured_image_alt'] ?: $p['title'])
            . '" loading="lazy" width="640" height="360" class="post-card-image">';
    }
    return '<article class="card post-card">' . $image
        . '<p class="meta"><time datetime="' . e(iso_date($p['published_at'])) . '">' . e(format_date($p['published_at'])) . '</time>'
        . ' · ' . reading_time($p['content'] ?? $p['excerpt']) . ' min read</p>'
        . '<h3><a href="' . e(url('/blog/' . $p['slug'])) . '" class="stretched">' . e($p['title']) . '</a></h3>'
        . '<p>' . e($p['excerpt']) . '</p>'
        . '</article>';
}

/** Accessible FAQ list using native <details>; the same data feeds FAQPage schema. */
function faq_list(array $faqs, string $headingTag = 'h3'): string
{
    if (!$faqs) {
        return '';
    }
    $html = '<div class="faq-list">';
    foreach ($faqs as $f) {
        $html .= '<details class="faq-item"><summary><' . $headingTag . ' class="faq-q">' . e($f['question']) . '</' . $headingTag . '></summary>'
            . '<div class="faq-a"><p>' . nl2br(e($f['answer'])) . '</p></div></details>';
    }
    return $html . '</div>';
}

/**
 * "Title: description" lines (landing page features/use cases) => [['title' => , 'text' => ]]
 */
function titled_lines(?string $text): array
{
    return array_map(function ($line) {
        $parts = array_map('trim', explode(':', $line, 2));
        return count($parts) === 2 && $parts[1] !== ''
            ? ['title' => $parts[0], 'text' => mb_strtoupper(mb_substr($parts[1], 0, 1)) . mb_substr($parts[1], 1)]
            : ['title' => $line, 'text' => ''];
    }, lines($text));
}

/** Lead capture form; $state comes from lead_form_state(). */
function lead_form(array $state, string $heading = 'Tell us what you need', string $button = 'Send enquiry'): string
{
    return render_partial(APP_ROOT . '/pages/partials/lead-form.php', compact('state', 'heading', 'button'));
}
