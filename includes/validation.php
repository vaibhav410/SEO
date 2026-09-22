<?php
/**
 * Minimal server-side validation.
 *
 *   [$clean, $errors] = validate($_POST, [
 *       'title' => 'required|max:200',
 *       'email' => 'required|email',
 *       'status'=> 'required|in:draft,published',
 *   ]);
 *
 * Rules: required, max:N, min:N, email, slug, url (absolute http/https), path_or_url, int, in:a,b,c, phone
 * Values are trimmed; empty optional values become null.
 */
function validate(array $input, array $rules, array $labels = []): array
{
    $clean = [];
    $errors = [];

    foreach ($rules as $field => $ruleString) {
        $raw = $input[$field] ?? '';
        $value = is_string($raw) ? trim(str_replace("\0", '', $raw)) : '';
        $label = $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
        $ruleList = explode('|', $ruleString);

        if ($value === '') {
            if (in_array('required', $ruleList, true)) {
                $errors[$field] = "$label is required.";
            }
            $clean[$field] = null;
            continue;
        }

        foreach ($ruleList as $rule) {
            [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
            $error = match ($name) {
                'max'   => mb_strlen($value) > (int) $arg ? "$label must be at most $arg characters." : null,
                'min'   => mb_strlen($value) < (int) $arg ? "$label must be at least $arg characters." : null,
                'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid email address.',
                'slug'  => is_valid_slug($value) ? null : "$label may only contain lowercase letters, numbers and single hyphens.",
                'url'   => is_http_url($value) ? null : "$label must be a full http:// or https:// URL.",
                'path_or_url' => (is_http_url($value) || preg_match('#^/[A-Za-z0-9/_\-.]*$#', $value)) ? null : "$label must be a site path like /services/web-hosting or a full URL.",
                'int'   => filter_var($value, FILTER_VALIDATE_INT) !== false ? null : "$label must be a whole number.",
                'in'    => in_array($value, explode(',', (string) $arg), true) ? null : "Choose a valid $label.",
                'phone' => preg_match('/^\+?[0-9 ()-]{7,20}$/', $value) ? null : 'Enter a valid phone number.',
                default => null,
            };
            if ($error) {
                $errors[$field] = $error;
                break;
            }
        }
        $clean[$field] = $value;
    }

    return [$clean, $errors];
}

function is_http_url(string $value): bool
{
    return (bool) filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $value);
}

/** Error message markup for a field, linked with aria-describedby="{field}-error". */
function field_error(array $errors, string $field): string
{
    return isset($errors[$field])
        ? '<p class="field-error" id="' . e($field) . '-error">' . e($errors[$field]) . '</p>'
        : '';
}

/** aria attributes for an input that may have an error. */
function field_aria(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="' . e($field) . '-error"' : '';
}
