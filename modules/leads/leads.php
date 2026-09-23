<?php
/**
 * Lead capture (public forms) and lead management (admin).
 *
 * Spam defences, cheapest first: CSRF token, honeypot field, minimum fill time,
 * per-IP rate limit (hashed IP), then strict validation.
 */

const LEAD_STATUSES = ['new', 'contacted', 'qualified', 'converted', 'closed', 'spam'];
const LEAD_RATE_LIMIT = 3;          // submissions ...
const LEAD_RATE_WINDOW_MINUTES = 10; // ... per this many minutes per visitor
const LEAD_MIN_SECONDS = 3;          // humans take longer than this to fill the form

/** Values the form needs to render: old input, errors, timing token. */
function lead_form_state(): array
{
    [$old, $errors] = take_form_state();
    return ['old' => $old, 'errors' => $errors, 'ts' => lead_time_token(), 'interests' => array_column(services_published(), 'name'),
        'campaign' => lead_campaign(input('utm_campaign', '', 'get') ?: input('utm_source', '', 'get'))];
}

/** Campaign label from utm_* parameters, reduced to a safe token (letters, digits, - _ .). */
function lead_campaign(string $value): ?string
{
    $value = strtolower(trim($value));
    return preg_match('/^[a-z0-9._-]{1,100}$/', $value) ? $value : null;
}

/** Signed timestamp so the minimum-fill-time check cannot be forged. */
function lead_time_token(?int $time = null): string
{
    $time ??= time();
    return $time . '.' . hash_hmac('sha256', (string) $time, (string) config('app.secret'));
}

function lead_time_ok(string $token): bool
{
    [$time, $sig] = array_pad(explode('.', $token, 2), 2, '');
    if (!ctype_digit($time) || !hash_equals(hash_hmac('sha256', $time, (string) config('app.secret')), $sig)) {
        return false;
    }
    $age = time() - (int) $time;
    return $age >= LEAD_MIN_SECONDS && $age <= 86400;
}

/**
 * Handle a POSTed lead form and redirect back (Post/Redirect/Get).
 * $sourcePage and $keyword are decided by the server from the route, never trusted from the form.
 */
function lead_handle_submission(string $sourcePage, ?string $keyword = null): void
{
    csrf_verify();
    $back = $sourcePage . '#lead-form';

    // Bots fill hidden fields and submit instantly. Pretend success so they learn nothing.
    if (input('website') !== '' || !lead_time_ok(input('_ts'))) {
        flash('success', 'Thank you. Your message has been received.');
        redirect($back);
    }

    $recent = (int) db_value(
        'SELECT COUNT(*) FROM leads WHERE ip_hash = ? AND created_at > (NOW() - INTERVAL ' . LEAD_RATE_WINDOW_MINUTES . ' MINUTE)',
        [ip_hash()]
    );
    if ($recent >= LEAD_RATE_LIMIT) {
        flash('error', 'You have sent several enquiries in a short time. Please wait a few minutes and try again.');
        remember_form(lead_old_input(), []);
        redirect($back);
    }

    [$data, $errors] = lead_validate($_POST);
    if ($errors) {
        flash('error', 'Please correct the highlighted fields.');
        remember_form(lead_old_input(), $errors);
        redirect($back);
    }

    $data['source_page'] = mb_substr($sourcePage, 0, 255);
    $data['keyword'] = $keyword !== null ? mb_substr($keyword, 0, 150) : null;
    $data['campaign'] = lead_campaign(input('campaign'));
    $data['ip_hash'] = ip_hash();
    $id = db_insert('leads', $data);
    log_activity('received', 'lead', $id, 'New lead from ' . $data['name'] . ' via ' . $sourcePage, null);

    flash('success', 'Thank you, ' . $data['name'] . '. Our team will get back to you within one business day.');
    redirect($back);
}

/** @return array{0: array, 1: array} */
function lead_validate(array $input): array
{
    [$data, $errors] = validate($input, [
        'name'     => 'required|min:2|max:100',
        'email'    => 'required|email|max:190',
        'phone'    => 'phone|max:20',
        'company'  => 'max:150',
        'interest' => 'max:120',
        'message'  => 'required|min:10|max:3000',
    ], ['message' => 'Message']);

    if ($data['interest'] && !in_array($data['interest'], array_column(services_published(), 'name'), true) && $data['interest'] !== 'Other') {
        $data['interest'] = null;
    }
    // Strip control characters that have no place in a contact form.
    foreach (['name', 'company', 'message'] as $field) {
        if ($data[$field] !== null) {
            $data[$field] = preg_replace('/[^\P{C}\n\t]/u', '', $data[$field]);
        }
    }
    return [$data, $errors];
}

function lead_old_input(): array
{
    return array_intersect_key(array_map(fn($v) => is_string($v) ? mb_substr($v, 0, 3000) : '', $_POST),
        array_flip(['name', 'email', 'phone', 'company', 'interest', 'message']));
}

const LEAD_SORTS = ['name' => 'name', 'status' => "FIELD(status, 'new', 'contacted', 'qualified', 'converted', 'closed', 'spam')", 'created' => 'created_at', 'source' => 'source_page'];

function leads_admin_list(string $status, string $search, int $limit, int $offset, string $source = '', string $orderBy = ' ORDER BY created_at DESC'): array
{
    [$where, $params] = leads_admin_filter($status, $search, $source);
    return db_all('SELECT id, name, email, phone, company, interest, source_page, keyword, campaign, status, created_at FROM leads'
        . $where . $orderBy . ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset, $params);
}

/** Human label for where a lead came from. */
function lead_source_label(array $lead): string
{
    if (!empty($lead['campaign'])) {
        return 'Campaign: ' . $lead['campaign'];
    }
    $page = (string) $lead['source_page'];
    return match (true) {
        $page === '/contact' => 'Contact form',
        str_starts_with($page, '/blog/') => 'Blog guide',
        str_starts_with($page, '/services/') => 'Service page',
        default => 'Landing page',
    };
}

function leads_admin_count(string $status, string $search, string $source = ''): int
{
    [$where, $params] = leads_admin_filter($status, $search, $source);
    return (int) db_value('SELECT COUNT(*) FROM leads' . $where, $params);
}

function leads_admin_filter(string $status, string $search, string $source = ''): array
{
    $clauses = [];
    $params = [];
    if ($source !== '') {
        $clauses[] = 'source_page = ?';
        $params[] = $source;
    }
    if (in_array($status, LEAD_STATUSES, true)) {
        $clauses[] = 'status = ?';
        $params[] = $status;
    }
    if ($search !== '') {
        $clauses[] = '(name LIKE ? OR email LIKE ? OR company LIKE ? OR keyword LIKE ?)';
        $like = '%' . addcslashes($search, '%_\\') . '%';
        array_push($params, $like, $like, $like, $like);
    }
    return [$clauses ? ' WHERE ' . implode(' AND ', $clauses) : '', $params];
}

/** Which pages generate leads: the core "content -> lead" feedback loop. */
function leads_by_source(int $limit = 8): array
{
    return db_all(
        "SELECT source_page, COUNT(*) AS total, SUM(status IN ('qualified','converted')) AS qualified
         FROM leads WHERE status <> 'spam' GROUP BY source_page ORDER BY total DESC LIMIT " . (int) $limit
    );
}
