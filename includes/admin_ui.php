<?php
/**
 * Admin UI helpers: layout, form controls, tables and pagination.
 */

/**
 * @param array $crumbs [label => admin path] shown in the top bar; the last item is the current page
 */
function admin_header(string $title, string $active = '', array $crumbs = []): void
{
    echo render_partial(APP_ROOT . '/admin/partials/layout-top.php', ['title' => $title, 'active' => $active, 'crumbs' => $crumbs, 'user' => current_user()]);
}

function admin_footer(): void
{
    echo render_partial(APP_ROOT . '/admin/partials/layout-bottom.php');
}

/** Label + input + error. $attrs are extra HTML attributes (values escaped). */
function form_input(string $name, string $label, $value, array $errors = [], array $attrs = []): string
{
    $type = $attrs['type'] ?? 'text';
    unset($attrs['type']);
    $help = $attrs['help'] ?? '';
    unset($attrs['help']);
    return '<div class="field"><label for="f-' . e($name) . '">' . e($label) . '</label>'
        . '<input type="' . e($type) . '" id="f-' . e($name) . '" name="' . e($name) . '" value="' . e($value) . '"'
        . form_attrs($attrs) . field_aria($errors, $name) . '>'
        . ($help ? '<p class="help">' . $help . '</p>' : '')
        . field_error($errors, $name) . '</div>';
}

function form_textarea(string $name, string $label, $value, array $errors = [], array $attrs = []): string
{
    $help = $attrs['help'] ?? '';
    unset($attrs['help']);
    return '<div class="field"><label for="f-' . e($name) . '">' . e($label) . '</label>'
        . '<textarea id="f-' . e($name) . '" name="' . e($name) . '"' . form_attrs($attrs) . field_aria($errors, $name) . '>' . e($value) . '</textarea>'
        . ($help ? '<p class="help">' . $help . '</p>' : '')
        . field_error($errors, $name) . '</div>';
}

/** @param array $options [value => label] */
function form_select(string $name, string $label, $value, array $options, array $errors = [], array $attrs = []): string
{
    $html = '<div class="field"><label for="f-' . e($name) . '">' . e($label) . '</label>'
        . '<select id="f-' . e($name) . '" name="' . e($name) . '"' . form_attrs($attrs) . field_aria($errors, $name) . '>';
    foreach ($options as $optValue => $optLabel) {
        $selected = (string) $optValue === (string) $value ? ' selected' : '';
        $html .= '<option value="' . e($optValue) . '"' . $selected . '>' . e($optLabel) . '</option>';
    }
    return $html . '</select>' . field_error($errors, $name) . '</div>';
}

function form_attrs(array $attrs): string
{
    $out = '';
    foreach ($attrs as $key => $val) {
        if ($val === true) {
            $out .= ' ' . e($key);
        } elseif ($val !== false && $val !== null) {
            $out .= ' ' . e($key) . '="' . e($val) . '"';
        }
    }
    return $out;
}

/** Small POST form with a single button (delete, status change) - never a GET link for state changes. */
function action_button(string $action, string $label, array $fields = [], string $class = 'btn-link', string $confirm = ''): string
{
    $html = '<form method="post" action="' . e($action) . '" class="inline-form"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field();
    foreach ($fields as $k => $v) {
        $html .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }
    return $html . '<button type="submit" class="btn ' . e($class) . '">' . e($label) . '</button></form>';
}

function admin_pagination(array $pager): string
{
    if ($pager['pages'] <= 1) {
        return '';
    }
    $html = '<nav class="pagination" aria-label="Pages">';
    for ($i = 1; $i <= $pager['pages']; $i++) {
        $html .= $i === $pager['page']
            ? '<span aria-current="page">' . $i . '</span>'
            : '<a href="' . e(query_with(['page' => $i])) . '">' . $i . '</a>';
    }
    return $html . '</nav>';
}

/** Character counter hint for SEO fields (enhanced live by admin.js). */
function seo_counter(string $field, int $min, int $max): string
{
    return '<span class="counter" data-count-for="f-' . e($field) . '" data-min="' . $min . '" data-max="' . $max . '"></span>';
}

function admin_empty(string $message, string $actionUrl = '', string $actionLabel = '', string $icon = 'search', string $title = ''): string
{
    return '<div class="empty-state"><span class="empty-icon">' . icon($icon) . '</span>'
        . ($title !== '' ? '<h3>' . e($title) . '</h3>' : '')
        . '<p>' . e($message) . '</p>'
        . ($actionUrl ? '<a class="btn btn-primary" href="' . e($actionUrl) . '">' . e($actionLabel) . '</a>' : '') . '</div>';
}
