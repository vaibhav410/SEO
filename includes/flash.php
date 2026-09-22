<?php
/**
 * One-time messages and old form input carried across a redirect (Post/Redirect/Get).
 */

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flash_messages(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

function render_flash(): string
{
    $html = '';
    foreach (flash_messages() as $f) {
        $role = $f['type'] === 'error' ? 'alert' : 'status';
        $html .= '<div class="alert alert-' . e($f['type']) . '" role="' . $role . '">' . e($f['message']) . '</div>';
    }
    return $html;
}

/** Keep submitted values and errors for re-displaying a form after redirect. */
function remember_form(array $old, array $errors): void
{
    $_SESSION['_old'] = $old;
    $_SESSION['_errors'] = $errors;
}

function take_form_state(): array
{
    $state = [$_SESSION['_old'] ?? [], $_SESSION['_errors'] ?? []];
    unset($_SESSION['_old'], $_SESSION['_errors']);
    return $state;
}
