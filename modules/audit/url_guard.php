<?php
/**
 * SSRF protection for every outbound request the app makes (SEO auditor, backlink checker).
 *
 * A URL is only fetched when:
 *   - scheme is http/https, no embedded credentials, default ports only
 *   - the host resolves, and EVERY resolved address is public (no loopback, private, link-local,
 *     carrier-grade NAT, cloud metadata, multicast, reserved or documentation ranges)
 * The fetcher then pins the connection to the vetted IP (CURLOPT_RESOLVE), so a DNS answer
 * cannot change between the check and the request (DNS rebinding), and re-checks every redirect hop.
 */

/** CIDR ranges that must never be fetched, beyond PHP's own private/reserved flags. */
const BLOCKED_CIDRS = [
    '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
    '192.0.0.0/24', '192.0.2.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24',
    '224.0.0.0/4', '240.0.0.0/4', '255.255.255.255/32',
    '::/128', '::1/128', '64:ff9b::/96', '100::/64', '2001:db8::/32', 'fc00::/7', 'fe80::/10', 'ff00::/8',
];

/**
 * @return array{ok: bool, error: ?string, url: string, host: string, port: int, ip: ?string}
 */
function url_guard_check(string $url): array
{
    $fail = fn(string $error) => ['ok' => false, 'error' => $error, 'url' => $url, 'host' => '', 'port' => 0, 'ip' => null];

    $url = trim($url);
    if ($url === '' || strlen($url) > 2000 || preg_match('/[\x00-\x20\x7F]/', $url)) {
        return $fail('Enter a valid URL.');
    }
    $parts = parse_url($url);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
        return $fail('Enter a full URL including http:// or https://');
    }
    $scheme = strtolower($parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return $fail('Only http and https URLs can be audited.');
    }
    if (isset($parts['user']) || isset($parts['pass'])) {
        return $fail('URLs with embedded usernames or passwords are not allowed.');
    }

    $host = strtolower(rtrim($parts['host'], '.'));
    $host = trim($host, '[]'); // IPv6 literal
    $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

    // Optional: allow this application's own origin (useful on localhost during development).
    if (config('audit.allow_self') && url_guard_is_self($scheme, $host, $port)) {
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : (gethostbyname($host) ?: null);
        return ['ok' => true, 'error' => null, 'url' => $url, 'host' => $host, 'port' => $port, 'ip' => $ip];
    }

    if (!in_array($port, [80, 443], true)) {
        return $fail('Only standard web ports (80 and 443) can be audited.');
    }

    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $ips = [$host];
    } else {
        if (!str_contains($host, '.') || in_array($host, ['localhost', 'localhost.localdomain'], true)
            || preg_match('/\.(local|localhost|internal|intranet|lan|home|corp)$/', $host)) {
            return $fail('Internal host names cannot be audited.');
        }
        $ips = url_guard_resolve($host);
        if (!$ips) {
            return $fail('The domain could not be resolved. Check the spelling.');
        }
    }

    foreach ($ips as $ip) {
        if (!ip_is_public($ip)) {
            return $fail('This address points to a private or reserved network and cannot be audited.');
        }
    }

    return ['ok' => true, 'error' => null, 'url' => $url, 'host' => $host, 'port' => $port, 'ip' => $ips[0]];
}

/** A and AAAA records for a host. */
function url_guard_resolve(string $host): array
{
    $ips = @gethostbynamel($host) ?: [];
    $aaaa = @dns_get_record($host, DNS_AAAA) ?: [];
    foreach ($aaaa as $record) {
        if (!empty($record['ipv6'])) {
            $ips[] = $record['ipv6'];
        }
    }
    return array_values(array_unique($ips));
}

function ip_is_public(string $ip): bool
{
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
    }
    // IPv4-mapped / compatible IPv6 (::ffff:127.0.0.1) - judge the embedded IPv4 address.
    if (preg_match('/^::(ffff:)?(\d+\.\d+\.\d+\.\d+)$/i', $ip, $m)) {
        return ip_is_public($m[2]);
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return false;
    }
    foreach (BLOCKED_CIDRS as $cidr) {
        if (ip_in_cidr($ip, $cidr)) {
            return false;
        }
    }
    return true;
}

function ip_in_cidr(string $ip, string $cidr): bool
{
    [$subnet, $bits] = explode('/', $cidr);
    $ipBin = @inet_pton($ip);
    $subnetBin = @inet_pton($subnet);
    if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
        return false;
    }
    $bits = (int) $bits;
    $bytes = intdiv($bits, 8);
    if (strncmp($ipBin, $subnetBin, $bytes) !== 0) {
        return false;
    }
    $remaining = $bits % 8;
    if ($remaining === 0) {
        return true;
    }
    $mask = chr((0xFF << (8 - $remaining)) & 0xFF);
    return (ord($ipBin[$bytes]) & ord($mask)) === (ord($subnetBin[$bytes]) & ord($mask));
}

function url_guard_is_self(string $scheme, string $host, int $port): bool
{
    $base = parse_url(config('app.base_url'));
    $baseScheme = strtolower($base['scheme'] ?? 'http');
    $basePort = (int) ($base['port'] ?? ($baseScheme === 'https' ? 443 : 80));
    return $scheme === $baseScheme && $host === strtolower($base['host'] ?? '') && $port === $basePort;
}
