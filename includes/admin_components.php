<?php
/**
 * Admin design-system components. Each returns an HTML string; all dynamic values are escaped.
 * Charts are inline SVG rendered on the server (no chart library, CSP-safe) with a text alternative.
 */

/** Page title row with optional subtitle and action buttons. */
function page_header(string $title, string $subtitle = '', string $actions = ''): string
{
    return '<div class="page-head"><div><h1>' . e($title) . '</h1>' . ($subtitle !== '' ? '<p>' . e($subtitle) . '</p>' : '') . '</div>'
        . ($actions !== '' ? '<div class="actions">' . $actions . '</div>' : '') . '</div>';
}

/** Trend pill from a trend_count() result: "+12.4% vs previous 30 days". */
function trend_pill(?array $trend, string $period = 'vs previous 30 days'): string
{
    if (!$trend || $trend['change'] === null) {
        return $trend && $trend['current'] > 0 ? '<span class="trend trend-flat" data-tooltip="No data for the previous period">New</span>' : '';
    }
    $c = $trend['change'];
    $cls = $c > 0 ? 'trend-up' : ($c < 0 ? 'trend-down' : 'trend-flat');
    $arrow = $c > 0 ? '▲' : ($c < 0 ? '▼' : '■');
    return '<span class="trend ' . $cls . '" data-tooltip="' . e($trend['current'] . ' now, ' . $trend['previous'] . ' ' . $period) . '">'
        . $arrow . ' ' . ($c > 0 ? '+' : '') . e((string) $c) . '%</span>';
}

/**
 * KPI card. $value null renders a "not connected" state instead of a number.
 * @param array{label: string, value: ?string, suffix?: string, note?: string, icon?: string, href?: string, trend?: ?array, connect?: string} $k
 */
function kpi_card(array $k): string
{
    $tag = !empty($k['href']) ? 'a' : 'div';
    $href = !empty($k['href']) ? ' href="' . e(url($k['href'])) . '"' : '';
    $html = '<' . $tag . ' class="kpi' . ($k['value'] === null ? ' kpi-empty' : '') . '"' . $href . '>'
        . '<div class="kpi-top"><span class="kpi-label">' . e($k['label']) . '</span><span class="kpi-icon">' . icon($k['icon'] ?? 'trending', 'icon icon-sm') . '</span></div>';
    if ($k['value'] === null) {
        $html .= '<div class="kpi-value muted">—</div><p class="kpi-note">' . e($k['connect'] ?? 'Connect analytics data to view real metrics.') . '</p>';
    } else {
        $html .= '<div class="kpi-value">' . e($k['value']) . (isset($k['suffix']) ? '<small>' . e($k['suffix']) . '</small>' : '') . '</div>'
            . '<p class="kpi-note">' . trend_pill($k['trend'] ?? null) . ' ' . e($k['note'] ?? '') . '</p>';
    }
    return $html . '</' . $tag . '>';
}

/**
 * Vertical bar chart. $series = [['label' => 'Jan', 'value' => 3], ...]
 */
function chart_bars(array $series, string $title, string $unit = ''): string
{
    $max = max(1, ...array_map(fn($p) => $p['value'], $series ?: [['value' => 0]]));
    $n = max(1, count($series));
    $w = 600;
    $h = 180;
    $gap = 8;
    $bw = ($w - $gap * ($n + 1)) / $n;
    $bars = '';
    $labels = '';
    foreach ($series as $i => $p) {
        $bh = $p['value'] > 0 ? max(3, ($h - 40) * $p['value'] / $max) : 0;
        $x = $gap + $i * ($bw + $gap);
        $y = $h - 20 - $bh;
        $bars .= '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($bw, 1) . '" height="' . round($bh, 1) . '" rx="3" class="bar-fill"><title>'
            . e($p['label'] . ': ' . $p['value'] . ($unit ? ' ' . $unit : '')) . '</title></rect>';
        if ($p['value'] > 0) {
            $bars .= '<text x="' . round($x + $bw / 2, 1) . '" y="' . round($y - 4, 1) . '" class="bar-value" text-anchor="middle">' . (int) $p['value'] . '</text>';
        }
        $labels .= '<text x="' . round($x + $bw / 2, 1) . '" y="' . ($h - 4) . '" class="bar-label" text-anchor="middle">' . e($p['label']) . '</text>';
    }
    $summary = implode(', ', array_map(fn($p) => $p['label'] . ' ' . $p['value'], $series));
    return '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . e($title . ': ' . $summary) . '" preserveAspectRatio="none">'
        . '<line x1="0" x2="' . $w . '" y1="' . ($h - 20) . '" y2="' . ($h - 20) . '" class="axis"/>' . $bars . $labels . '</svg>';
}

/** Donut chart for a small distribution. $segments = [label => value]. */
function chart_donut(array $segments, string $title): string
{
    $total = array_sum($segments);
    $r = 42;
    $c = 2 * M_PI * $r;
    $offset = 0;
    $arcs = '';
    $legend = '';
    $i = 0;
    foreach ($segments as $label => $value) {
        $tone = 'seg-' . ($i++ % 6);
        if ($total > 0 && $value > 0) {
            $len = $c * $value / $total;
            $arcs .= '<circle r="' . $r . '" cx="60" cy="60" class="' . $tone . '" stroke-dasharray="' . round($len, 2) . ' ' . round($c - $len, 2) . '" stroke-dashoffset="' . round(-$offset, 2) . '"><title>' . e("$label: $value") . '</title></circle>';
            $offset += $len;
        }
        $legend .= '<li><span class="swatch ' . $tone . '"></span>' . e(ucfirst(str_replace('_', ' ', (string) $label))) . ' <strong>' . (int) $value . '</strong></li>';
    }
    return '<div class="donut"><svg viewBox="0 0 120 120" role="img" aria-label="' . e($title . ': ' . implode(', ', array_map(fn($k, $v) => "$k $v", array_keys($segments), $segments))) . '">'
        . '<circle r="' . $r . '" cx="60" cy="60" class="seg-track"/>' . $arcs
        . '<text x="60" y="58" text-anchor="middle" class="donut-total">' . (int) $total . '</text><text x="60" y="74" text-anchor="middle" class="donut-caption">total</text></svg>'
        . '<ul class="legend">' . $legend . '</ul></div>';
}

/** Horizontal progress bar (uses <meter> so it needs no inline styles). */
function progress_row(string $label, int $value, int $max = 100, string $suffix = ''): string
{
    return '<div class="bar"><span>' . e($label) . '</span><meter min="0" max="' . max(1, $max) . '" low="' . (int) ($max * .5) . '" high="' . (int) ($max * .8) . '" optimum="' . $max . '" value="' . $value . '">' . $value . '</meter><strong>' . $value . e($suffix) . '</strong></div>';
}

/** Sortable column header link. $allowed columns are validated by the caller's query. */
function th_sort(string $column, string $label, string $default = ''): string
{
    $current = input('sort', $default, 'get');
    $dir = input('dir', 'desc', 'get') === 'asc' ? 'asc' : 'desc';
    $active = $current === $column;
    $next = $active && $dir === 'desc' ? 'asc' : 'desc';
    $aria = $active ? ' aria-sort="' . ($dir === 'asc' ? 'ascending' : 'descending') . '"' : '';
    return '<th scope="col"' . $aria . '><a class="sort' . ($active ? ' is-active' : '') . '" href="' . e(query_with(['sort' => $column, 'dir' => $next, 'page' => null])) . '">'
        . e($label) . '<span class="sort-icon" aria-hidden="true">' . ($active ? ($dir === 'asc' ? '↑' : '↓') : '↕') . '</span></a></th>';
}

/** ORDER BY clause from ?sort=&dir= restricted to an allow-list [param => SQL expression]. */
function sort_sql(array $allowed, string $default, string $defaultDir = 'desc'): string
{
    $sort = input('sort', $default, 'get');
    $expr = $allowed[$sort] ?? $allowed[$default];
    $dir = strtolower(input('dir', $defaultDir, 'get')) === 'asc' ? 'ASC' : 'DESC';
    return " ORDER BY $expr $dir";
}

/** Tab navigation. $tabs = [href => label] */
function tabs(array $tabs, string $activeHref, string $label = 'Sections'): string
{
    $html = '<nav class="tabs" aria-label="' . e($label) . '">';
    foreach ($tabs as $href => $text) {
        $html .= '<a href="' . e(url($href)) . '"' . ($href === $activeHref ? ' aria-current="page"' : '') . '>' . $text . '</a>';
    }
    return $html . '</nav>';
}

/** Coloured dot + label for health states: healthy / warning / attention. */
function status_indicator(string $state, string $label = ''): string
{
    $labels = ['healthy' => 'Healthy', 'warning' => 'Warning', 'attention' => 'Needs attention', 'valid' => 'Valid', 'error' => 'Errors', 'pass' => 'Passed', 'warn' => 'Warning', 'fail' => 'Issue', 'info' => 'Info'];
    return '<span class="status status-' . e($state) . '"><span class="dot" aria-hidden="true"></span>' . e($label !== '' ? $label : ($labels[$state] ?? ucfirst($state))) . '</span>';
}

/** Loading skeleton lines (shown while JS fetches data, e.g. audits and search). */
function skeleton(int $lines = 3): string
{
    return '<div class="skeleton" aria-hidden="true">' . str_repeat('<span class="skeleton-line"></span>', $lines) . '</div>';
}

/** Dropdown menu built on <details> so it works without JavaScript. */
function dropdown(string $summaryHtml, string $menuHtml, string $class = '', string $label = ''): string
{
    return '<details class="dropdown ' . e($class) . '"><summary' . ($label ? ' aria-label="' . e($label) . '"' : '') . '>' . $summaryHtml . '</summary><div class="dropdown-menu">' . $menuHtml . '</div></details>';
}

/** Definition list of key => value pairs (values already HTML). */
function meta_list(array $pairs): string
{
    $html = '<dl class="meta">';
    foreach ($pairs as $k => $v) {
        $html .= '<dt>' . e($k) . '</dt><dd>' . $v . '</dd>';
    }
    return $html . '</dl>';
}

/** "Not connected" panel for metrics that need an external data source. */
function connect_panel(string $title, string $text): string
{
    return '<div class="connect-panel"><span class="empty-icon">' . icon('trending') . '</span><h3>' . e($title) . '</h3><p>' . e($text) . '</p>'
        . '<p class="small muted">Connect Google Analytics 4 or Search Console to view real metrics. GrowthHub never estimates or invents traffic figures.</p></div>';
}
