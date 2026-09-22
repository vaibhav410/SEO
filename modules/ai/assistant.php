<?php
/**
 * Optional AI writing assistant (OpenRouter chat completions API).
 *
 * It only drafts suggestions that an editor reviews and edits; nothing is saved or published
 * automatically. Disabled unless ai.api_key is configured (config.local.php / OPENROUTER_API_KEY).
 */

function ai_enabled(): bool
{
    return trim((string) config('ai.api_key')) !== '';
}

/**
 * Call the model and return decoded JSON from its reply.
 * @return array{ok: bool, data: ?array, error: ?string}
 */
function ai_json(string $system, string $user): array
{
    if (!ai_enabled()) {
        return ['ok' => false, 'data' => null, 'error' => 'The AI assistant is not configured.'];
    }
    $payload = json_encode([
        'model'       => config('ai.model'),
        'temperature' => 0.4,
        'max_tokens'  => 700,
        'response_format' => ['type' => 'json_object'],
        'messages'    => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
    ]);

    $ch = curl_init((string) config('ai.endpoint'));
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => (int) config('ai.timeout'),
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . config('ai.api_key'),
            'HTTP-Referer: ' . absolute_url('/'),
            'X-Title: SYSCOM GrowthHub',
        ],
    ]);
    if (defined('CURLSSLOPT_NATIVE_CA')) {
        curl_setopt($ch, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
    }
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $status >= 400) {
        log_error("AI request failed ($status): " . ($error ?: substr((string) $raw, 0, 300)));
        return ['ok' => false, 'data' => null, 'error' => 'The AI service is unavailable right now. Please try again later.'];
    }
    $content = json_decode((string) $raw, true)['choices'][0]['message']['content'] ?? '';
    // Some models wrap JSON in a code fence.
    $content = trim(preg_replace('/^```(?:json)?|```$/m', '', (string) $content));
    $data = json_decode($content, true);
    return is_array($data)
        ? ['ok' => true, 'data' => $data, 'error' => null]
        : ['ok' => false, 'data' => null, 'error' => 'The AI reply could not be read. Please try again.'];
}

/** Suggest a meta title and description for a page. */
function ai_suggest_meta(string $title, string $keyword, string $content): array
{
    $system = 'You are an SEO editor for SYSCOM, an Indian provider of domains, web hosting, VPS, dedicated servers, business email and SSL. '
        . 'Write accurate, helpful search snippets. Never invent prices, statistics, awards, guarantees or ranking claims. '
        . 'Avoid keyword stuffing and clickbait. Reply only with JSON: {"meta_title": string (max 60 chars), "meta_description": string (120-155 chars)}.';
    $user = "Page title: $title\nPrimary keyword: " . ($keyword ?: '(none)') . "\nPage content:\n" . str_limit(markdown_text($content), 3000);
    $result = ai_json($system, $user);
    if ($result['ok']) {
        $result['data'] = [
            'meta_title' => str_limit((string) ($result['data']['meta_title'] ?? ''), 70, ''),
            'meta_description' => str_limit((string) ($result['data']['meta_description'] ?? ''), 170, ''),
        ];
    }
    return $result;
}

/** Suggest an article outline for a keyword: a draft structure for a human writer. */
function ai_suggest_outline(string $keyword, string $intent): array
{
    $system = 'You help plan genuinely useful articles for SYSCOM (domains, hosting, business email, SSL in India). '
        . 'Plan content that fully answers the searcher\'s question. Do not invent statistics or claims. '
        . 'Reply only with JSON: {"title": string, "sections": [string, ...] (5-8 H2 headings), "faqs": [string, ...] (3 questions)}.';
    $result = ai_json($system, "Keyword: $keyword\nSearch intent: $intent");
    if ($result['ok']) {
        $d = $result['data'];
        $result['data'] = [
            'title' => (string) ($d['title'] ?? ''),
            'sections' => array_slice(array_map('strval', (array) ($d['sections'] ?? [])), 0, 10),
            'faqs' => array_slice(array_map('strval', (array) ($d['faqs'] ?? [])), 0, 5),
        ];
    }
    return $result;
}
