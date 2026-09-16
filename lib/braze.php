<?php

declare(strict_types=1);

/**
 * Braze REST helpers + embed/Connected Content snippets.
 */

/** @return array<string, string> label => REST base URL */
function braze_rest_endpoints(): array
{
    return [
        'US-01 (rest.iad-01.braze.com)' => 'https://rest.iad-01.braze.com',
        'US-02 (rest.iad-02.braze.com)' => 'https://rest.iad-02.braze.com',
        'US-03 (rest.iad-03.braze.com)' => 'https://rest.iad-03.braze.com',
        'US-04 (rest.iad-04.braze.com)' => 'https://rest.iad-04.braze.com',
        'US-05 (rest.iad-05.braze.com)' => 'https://rest.iad-05.braze.com',
        'US-06 (rest.iad-06.braze.com)' => 'https://rest.iad-06.braze.com',
        'US-07 (rest.iad-07.braze.com)' => 'https://rest.iad-07.braze.com',
        'US-08 (rest.iad-08.braze.com)' => 'https://rest.iad-08.braze.com',
        'EU-01 (rest.fra-01.braze.eu)' => 'https://rest.fra-01.braze.eu',
        'EU-02 (rest.fra-02.braze.eu)' => 'https://rest.fra-02.braze.eu',
        'AU-01 (rest.au-01.braze.com)' => 'https://rest.au-01.braze.com',
        'ID-01 (rest.id-01.braze.com)' => 'https://rest.id-01.braze.com',
    ];
}

function braze_normalize_rest_endpoint(string $endpoint): string
{
    $endpoint = rtrim(trim($endpoint), '/');
    if ($endpoint === '') {
        return '';
    }
    if (!preg_match('#^https://rest\.[a-z0-9.-]+\.braze\.(com|eu)$#i', $endpoint)) {
        return '';
    }

    return strtolower($endpoint);
}

function braze_api_key_hint(string $apiKey): string
{
    $apiKey = trim($apiKey);
    $len = strlen($apiKey);
    if ($len < 8) {
        return '••••';
    }

    return substr($apiKey, 0, 4) . '…' . substr($apiKey, -4);
}

function braze_hash_token(string $token): string
{
    return hash('sha256', $token);
}

/**
 * Absolute URL for an /api/*.php endpoint.
 */
function braze_absolute_api_url(string $apiFilename): string
{
    $apiFilename = basename(str_replace('\\', '/', $apiFilename));
    $prefix = app_web_path_prefix();
    $path = ($prefix === '' ? '' : $prefix) . '/api/' . $apiFilename;
    if ($path === '' || $path[0] !== '/') {
        $path = '/' . ltrim($path, '/');
    }
    $origin = app_embed_origin();

    return $origin !== '' ? ($origin . $path) : $path;
}

/**
 * @param array<string, mixed> $timer
 */
function braze_timer_image_url(array $timer, bool $dynamicEnd = false): string
{
    $id = (string) ($timer['id'] ?? '');
    $src = app_timer_embed_src_prefix() . rawurlencode($id);
    if ($dynamicEnd) {
        $src .= '&end={{event_properties.end_ts}}';
        $sig = app_timer_signature_for_id($id);
        if ($sig !== '') {
            $src .= '&sig=' . rawurlencode($sig);
        }
    }

    return $src;
}

/**
 * Gmail-safe table embed used in Braze Content Blocks / Connected Content.
 *
 * @param array<string, mixed> $timer
 */
function braze_timer_embed_html(array $timer, bool $dynamicEnd = false, string $ctaUrl = 'https://example.com/cta'): string
{
    $w = (int) ($timer['width'] ?? 480);
    $h = (int) ($timer['height'] ?? 120);
    if ($w < 200) {
        $w = 200;
    }
    if ($h < 80) {
        $h = 80;
    }
    $endsAt = (int) ($timer['ends_at'] ?? 0);
    $alt = app_format_deadline_label($endsAt);
    $alt = str_replace('"', '&quot;', 'Countdown — ' . $alt);
    $src = htmlspecialchars(braze_timer_image_url($timer, $dynamicEnd), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $cta = htmlspecialchars($ctaUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $comment = $dynamicEnd
        ? '<!-- Dynamic Braze countdown: set event_properties.end_ts (unix). Replace CTA href. -->'
        : '<!-- Email countdown via Braze. Replace CTA href. Optional &v=campaign_id for cache isolation. -->';

    return $comment . "\n"
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="' . $w . '" style="border-collapse:collapse;max-width:100%;">' . "\n"
        . '  <tr>' . "\n"
        . '    <td align="center" style="padding:0;">' . "\n"
        . '      <a href="' . $cta . '" target="_blank" style="text-decoration:none;border:0;">' . "\n"
        . '        <img src="' . $src . '" width="' . $w . '" height="' . $h . '" alt="' . $alt . '" style="display:block;border:0;outline:none;text-decoration:none;max-width:100%;height:auto;" />' . "\n"
        . '      </a>' . "\n"
        . '    </td>' . "\n"
        . '  </tr>' . "\n"
        . '</table>';
}

function braze_sanitize_block_name(string $name): string
{
    $name = strtolower(trim($name));
    $name = preg_replace('/[^a-z0-9_]+/', '_', $name) ?? 'countdown';
    $name = trim($name, '_');
    if ($name === '') {
        $name = 'countdown';
    }
    if (!preg_match('/^[a-z]/', $name)) {
        $name = 'c_' . $name;
    }

    return substr($name, 0, 90);
}

/**
 * @return array<string, mixed>|null
 */
function braze_connection_get(PDO $pdo, int $workspaceId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM braze_connections WHERE workspace_id = ? LIMIT 1');
    $stmt->execute([$workspaceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

/**
 * Public status (never includes raw secrets).
 *
 * @return array<string, mixed>
 */
function braze_connection_public_status(PDO $pdo, int $workspaceId): array
{
    $row = braze_connection_get($pdo, $workspaceId);
    $connectedUrl = braze_absolute_api_url('braze_connected.php');
    if ($row === null) {
        return [
            'connected' => false,
            'rest_endpoint' => '',
            'api_key_hint' => '',
            'has_connected_content_token' => false,
            'connected_content_token_hint' => '',
            'connected_content_url' => $connectedUrl,
            'last_tested_at' => 0,
            'last_error' => '',
            'endpoints' => braze_rest_endpoints(),
            'embed_https_ok' => app_embed_is_https_absolute(),
        ];
    }

    return [
        'connected' => true,
        'rest_endpoint' => (string) $row['rest_endpoint'],
        'api_key_hint' => (string) $row['api_key_hint'],
        'has_connected_content_token' => (string) $row['connected_content_token_hash'] !== '',
        'connected_content_token_hint' => (string) $row['connected_content_token_hint'],
        'connected_content_url' => $connectedUrl,
        'last_tested_at' => (int) $row['last_tested_at'],
        'last_error' => (string) $row['last_error'],
        'endpoints' => braze_rest_endpoints(),
        'embed_https_ok' => app_embed_is_https_absolute(),
    ];
}

/**
 * @return array{ok:bool, status:int, body:array<string,mixed>|null, raw:string, error:string}
 */
function braze_rest_request(string $restEndpoint, string $apiKey, string $method, string $path, ?array $jsonBody = null): array
{
    $restEndpoint = braze_normalize_rest_endpoint($restEndpoint);
    if ($restEndpoint === '' || trim($apiKey) === '') {
        return ['ok' => false, 'status' => 0, 'body' => null, 'raw' => '', 'error' => 'Missing Braze endpoint or API key'];
    }
    $url = $restEndpoint . '/' . ltrim($path, '/');
    $headers = [
        'Authorization: Bearer ' . trim($apiKey),
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    $payload = $jsonBody !== null ? json_encode($jsonBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'body' => null, 'raw' => '', 'error' => 'curl_init failed'];
        }
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ];
        if ($payload !== null) {
            $opts[CURLOPT_POSTFIELDS] = $payload;
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            return ['ok' => false, 'status' => $status, 'body' => null, 'raw' => '', 'error' => $cerr !== '' ? $cerr : 'Braze request failed'];
        }
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $headers),
                'content' => $payload ?? '',
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
        if ($raw === false) {
            return ['ok' => false, 'status' => $status, 'body' => null, 'raw' => '', 'error' => 'Braze request failed (allow_url_fopen/curl)'];
        }
    }

    $body = null;
    try {
        $decoded = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
        if (is_array($decoded)) {
            $body = $decoded;
        }
    } catch (Throwable $e) {
        $body = null;
    }

    $ok = $status >= 200 && $status < 300;
    $error = '';
    if (!$ok) {
        $error = is_array($body) ? (string) ($body['message'] ?? $body['error'] ?? ('HTTP ' . $status)) : ('HTTP ' . $status);
    }

    return ['ok' => $ok, 'status' => $status, 'body' => $body, 'raw' => (string) $raw, 'error' => $error];
}

/**
 * @return array{ok:bool, error:string}
 */
function braze_test_connection(PDO $pdo, int $workspaceId): array
{
    $row = braze_connection_get($pdo, $workspaceId);
    if ($row === null) {
        return ['ok' => false, 'error' => 'Braze is not connected'];
    }
    $res = braze_rest_request((string) $row['rest_endpoint'], (string) $row['api_key'], 'GET', 'content_blocks/list?limit=1');
    $now = time();
    if ($res['ok']) {
        $pdo->prepare('UPDATE braze_connections SET last_tested_at = ?, last_error = "", updated_at = ? WHERE workspace_id = ?')
            ->execute([$now, $now, $workspaceId]);

        return ['ok' => true, 'error' => ''];
    }
    $err = $res['error'] !== '' ? $res['error'] : 'Connection test failed';
    $pdo->prepare('UPDATE braze_connections SET last_tested_at = ?, last_error = ?, updated_at = ? WHERE workspace_id = ?')
        ->execute([$now, mb_substr($err, 0, 500), $now, $workspaceId]);

    return ['ok' => false, 'error' => $err];
}

/**
 * @param array<string, mixed> $timer
 * @return array{ok:bool, error:string, content_block_id:string, liquid_tag:string, block_name:string}
 */
function braze_push_timer_content_block(PDO $pdo, int $workspaceId, array $timer, bool $dynamicEnd = false): array
{
    $row = braze_connection_get($pdo, $workspaceId);
    if ($row === null) {
        return ['ok' => false, 'error' => 'Connect Braze first', 'content_block_id' => '', 'liquid_tag' => '', 'block_name' => ''];
    }
    if (!app_embed_is_https_absolute()) {
        return ['ok' => false, 'error' => 'Set public_base_url to https://… before pushing to Braze', 'content_block_id' => '', 'liquid_tag' => '', 'block_name' => ''];
    }

    $timerId = (string) ($timer['id'] ?? '');
    $baseName = braze_sanitize_block_name('countdown_' . (string) ($timer['name'] ?? 'timer') . '_' . substr($timerId, 0, 6));
    $html = braze_timer_embed_html($timer, $dynamicEnd);
    $description = 'Email countdown timer from Email Timer workspace';

    $existing = $pdo->prepare('SELECT content_block_id, block_name, liquid_tag FROM braze_content_blocks WHERE workspace_id = ? AND timer_id = ? LIMIT 1');
    $existing->execute([$workspaceId, $timerId]);
    $prev = $existing->fetch(PDO::FETCH_ASSOC);

    $contentBlockId = is_array($prev) ? (string) ($prev['content_block_id'] ?? '') : '';
    $blockName = is_array($prev) && (string) ($prev['block_name'] ?? '') !== '' ? (string) $prev['block_name'] : $baseName;

    if ($contentBlockId !== '') {
        $res = braze_rest_request((string) $row['rest_endpoint'], (string) $row['api_key'], 'POST', 'content_blocks/update', [
            'content_block_id' => $contentBlockId,
            'name' => $blockName,
            'description' => $description,
            'content' => $html,
            'state' => 'active',
        ]);
        if (!$res['ok']) {
            // Fall through to create if update fails (deleted in Braze, etc.)
            $contentBlockId = '';
        } else {
            $liquid = is_array($res['body']) ? (string) ($res['body']['liquid_tag'] ?? '') : '';
            if ($liquid === '' && is_array($prev)) {
                $liquid = (string) ($prev['liquid_tag'] ?? '');
            }
            if ($liquid === '') {
                $liquid = '{{content_blocks.${' . $blockName . '}}';
            }
            $now = time();
            $pdo->prepare('UPDATE braze_content_blocks SET content_block_id = ?, liquid_tag = ?, block_name = ?, last_pushed_at = ?, last_error = "" WHERE workspace_id = ? AND timer_id = ?')
                ->execute([$contentBlockId, $liquid, $blockName, $now, $workspaceId, $timerId]);

            return ['ok' => true, 'error' => '', 'content_block_id' => $contentBlockId, 'liquid_tag' => $liquid, 'block_name' => $blockName];
        }
    }

    $res = braze_rest_request((string) $row['rest_endpoint'], (string) $row['api_key'], 'POST', 'content_blocks/create', [
        'name' => $blockName,
        'description' => $description,
        'content' => $html,
        'state' => 'active',
    ]);
    if (!$res['ok']) {
        $err = $res['error'] !== '' ? $res['error'] : 'Could not create Content Block';
        // Name collision — retry with suffix
        if (stripos($err, 'name') !== false || stripos($err, 'taken') !== false || stripos($err, 'exists') !== false) {
            $blockName = braze_sanitize_block_name($baseName . '_' . substr(bin2hex(random_bytes(2)), 0, 4));
            $res = braze_rest_request((string) $row['rest_endpoint'], (string) $row['api_key'], 'POST', 'content_blocks/create', [
                'name' => $blockName,
                'description' => $description,
                'content' => $html,
                'state' => 'active',
            ]);
        }
    }
    if (!$res['ok']) {
        $err = $res['error'] !== '' ? $res['error'] : 'Could not create Content Block';
        $pdo->prepare('INSERT INTO braze_content_blocks (workspace_id, timer_id, content_block_id, liquid_tag, block_name, last_pushed_at, last_error)
            VALUES (?,?,?,?,?,?,?)
            ON CONFLICT(workspace_id, timer_id) DO UPDATE SET last_error = excluded.last_error')
            ->execute([$workspaceId, $timerId, '', '', $blockName, 0, mb_substr($err, 0, 500)]);

        return ['ok' => false, 'error' => $err, 'content_block_id' => '', 'liquid_tag' => '', 'block_name' => $blockName];
    }

    $body = is_array($res['body']) ? $res['body'] : [];
    $contentBlockId = (string) ($body['content_block_id'] ?? '');
    $liquid = (string) ($body['liquid_tag'] ?? '');
    if ($liquid === '') {
        $liquid = '{{content_blocks.${' . $blockName . '}}';
    }
    $now = time();
    $pdo->prepare('INSERT INTO braze_content_blocks (workspace_id, timer_id, content_block_id, liquid_tag, block_name, last_pushed_at, last_error)
        VALUES (?,?,?,?,?,?,?)
        ON CONFLICT(workspace_id, timer_id) DO UPDATE SET
            content_block_id = excluded.content_block_id,
            liquid_tag = excluded.liquid_tag,
            block_name = excluded.block_name,
            last_pushed_at = excluded.last_pushed_at,
            last_error = ""')
        ->execute([$workspaceId, $timerId, $contentBlockId, $liquid, $blockName, $now, '']);

    return ['ok' => true, 'error' => '', 'content_block_id' => $contentBlockId, 'liquid_tag' => $liquid, 'block_name' => $blockName];
}

/**
 * Liquid marketers paste into Braze to pull live HTML via Connected Content.
 */
function braze_connected_content_snippet(string $timerId, string $connectedContentUrl, bool $includeAuthHeaderPlaceholder = true): string
{
    $url = rtrim($connectedContentUrl, '?&');
    $sep = str_contains($url, '?') ? '&' : '?';
    $full = $url . $sep . 'timer_id=' . rawurlencode($timerId);
    $lines = [];
    if ($includeAuthHeaderPlaceholder) {
        $lines[] = '{% connected_content ' . $full . ' :method get :headers {"Authorization": "Bearer YOUR_CONNECTED_CONTENT_TOKEN"} :save countdown :retry %}';
    } else {
        $lines[] = '{% connected_content ' . $full . ' :method get :save countdown :retry %}';
    }
    $lines[] = '{{countdown.html}}';

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function braze_list_pushed_blocks(PDO $pdo, int $workspaceId): array
{
    $stmt = $pdo->prepare('SELECT b.timer_id, b.content_block_id, b.liquid_tag, b.block_name, b.last_pushed_at, b.last_error, t.name AS timer_name
        FROM braze_content_blocks b
        LEFT JOIN timers t ON t.id = b.timer_id
        WHERE b.workspace_id = ?
        ORDER BY b.last_pushed_at DESC');
    $stmt->execute([$workspaceId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
