<?php

declare(strict_types=1);

/**
 * Outbound email: SMTP (AUTH LOGIN + optional STARTTLS) or log-to-file for development.
 * Configure via data/secrets.php — see data/secrets.example.php.
 */

/**
 * @return array{
 *   transport:string,
 *   smtp_host:string,
 *   smtp_port:int,
 *   smtp_encryption:string,
 *   smtp_username:string,
 *   smtp_password:string,
 *   smtp_timeout:int,
 *   mail_from_email:string,
 *   mail_from_name:string,
 *   log_path:string
 * }
 */
function mail_app_config(): array
{
    $cfg = app_secrets_array() ?? [];
    $transport = strtolower(trim((string) ($cfg['mail_transport'] ?? 'log')));
    if (!in_array($transport, ['smtp', 'log'], true)) {
        $transport = 'log';
    }
    $encGuess = strtolower(trim((string) ($cfg['smtp_encryption'] ?? 'tls')));
    $defaultPort = $encGuess === 'ssl' ? 465 : 587;
    $port = (int) ($cfg['smtp_port'] ?? $defaultPort);
    if ($port <= 0) {
        $port = 587;
    }
    $enc = strtolower(trim((string) ($cfg['smtp_encryption'] ?? 'tls')));
    if (!in_array($enc, ['none', 'tls', 'ssl'], true)) {
        $enc = 'tls';
    }

    return [
        'transport' => $transport,
        'smtp_host' => trim((string) ($cfg['smtp_host'] ?? '')),
        'smtp_port' => $port,
        'smtp_encryption' => $enc,
        'smtp_username' => (string) ($cfg['smtp_username'] ?? ''),
        'smtp_password' => (string) ($cfg['smtp_password'] ?? ''),
        'smtp_timeout' => max(5, min(120, (int) ($cfg['smtp_timeout'] ?? 20))),
        'mail_from_email' => trim((string) ($cfg['mail_from_email'] ?? '')),
        'mail_from_name' => trim((string) ($cfg['mail_from_name'] ?? 'Email countdown')),
        'log_path' => APP_ROOT . '/data/mail-out.log',
    ];
}

/**
 * @return array{ok:bool, error:string}
 */
function mail_app_send(string $toEmail, string $subject, string $plainBody, ?string $htmlBody = null, int $maxAttempts = 3): array
{
    $start = microtime(true);
    $toEmail = trim($toEmail);
    if ($toEmail === '' || filter_var($toEmail, FILTER_VALIDATE_EMAIL) === false) {
        observability_log('mail.send.invalid_recipient', 'warning', ['subject' => $subject]);
        return ['ok' => false, 'error' => 'Invalid recipient'];
    }
    $cfg = mail_app_config();
    if ($cfg['transport'] === 'log') {
        $r = mail_app_send_log($cfg, $toEmail, $subject, $plainBody, $htmlBody);
        observability_log($r['ok'] ? 'mail.send.logged' : 'mail.send.failed', $r['ok'] ? 'info' : 'error', [
            'transport' => 'log',
            'subject' => $subject,
            'error' => $r['error'],
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
        ]);

        return $r;
    }
    if ($cfg['smtp_host'] === '' || $cfg['mail_from_email'] === '') {
        observability_log('mail.send.misconfigured', 'error', ['transport' => 'smtp', 'subject' => $subject]);
        return ['ok' => false, 'error' => 'SMTP host and mail_from_email must be set in secrets.php'];
    }

    $lastErr = ' SMTP send failed';
    for ($i = 0; $i < $maxAttempts; $i++) {
        $r = mail_app_send_smtp_once($cfg, $toEmail, $subject, $plainBody, $htmlBody);
        if ($r['ok']) {
            observability_log('mail.send.sent', 'info', [
                'transport' => 'smtp',
                'host' => $cfg['smtp_host'],
                'subject' => $subject,
                'attempts' => $i + 1,
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
            ]);

            return $r;
        }
        $lastErr = $r['error'];
        if ($i + 1 < $maxAttempts) {
            usleep(200000 * ($i + 1));
        }
    }

    observability_log('mail.send.failed', 'error', [
        'transport' => 'smtp',
        'host' => $cfg['smtp_host'],
        'subject' => $subject,
        'attempts' => $maxAttempts,
        'error' => $lastErr,
        'duration_ms' => (int) round((microtime(true) - $start) * 1000),
    ]);

    return ['ok' => false, 'error' => $lastErr];
}

/**
 * @param array<string, mixed> $cfg
 * @return array{ok:bool, error:string}
 */
function mail_app_send_log(array $cfg, string $toEmail, string $subject, string $plainBody, ?string $htmlBody): array
{
    $path = (string) ($cfg['log_path'] ?? (APP_ROOT . '/data/mail-out.log'));
    $sep = str_repeat('-', 72) . PHP_EOL;
    $chunk = $sep
        . '[' . gmdate('c') . '] to=' . $toEmail . ' subject=' . $subject . PHP_EOL
        . $plainBody . PHP_EOL;
    if ($htmlBody !== null && $htmlBody !== '') {
        $chunk .= PHP_EOL . '[html]' . PHP_EOL . $htmlBody . PHP_EOL;
    }
    $chunk .= $sep;
    if (@file_put_contents($path, $chunk, FILE_APPEND | LOCK_EX) === false) {
        return ['ok' => false, 'error' => 'Could not write mail log'];
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * @param array<string, mixed> $cfg
 * @return array{ok:bool, error:string}
 */
function mail_app_send_smtp_once(array $cfg, string $toEmail, string $subject, string $plainBody, ?string $htmlBody): array
{
    $host = (string) $cfg['smtp_host'];
    $port = (int) $cfg['smtp_port'];
    $enc = (string) $cfg['smtp_encryption'];
    $timeout = (int) $cfg['smtp_timeout'];
    $user = (string) $cfg['smtp_username'];
    $pass = (string) $cfg['smtp_password'];
    $fromEmail = (string) $cfg['mail_from_email'];
    $fromName = (string) $cfg['mail_from_name'];

    $remote = $enc === 'ssl'
        ? 'ssl://' . $host . ':' . $port
        : 'tcp://' . $host . ':' . $port;

    $errNo = 0;
    $errStr = '';
    $fp = @stream_socket_client($remote, $errNo, $errStr, $timeout, STREAM_CLIENT_CONNECT);
    if (!is_resource($fp)) {
        return ['ok' => false, 'error' => 'SMTP connect failed: ' . $errStr];
    }
    stream_set_timeout($fp, $timeout);

    try {
        $greet = mail_smtp_read_multiline($fp);
        if (!str_starts_with($greet, '220')) {
            return ['ok' => false, 'error' => 'SMTP bad greeting: ' . trim($greet)];
        }
        $ehloHost = 'localhost';
        mail_smtp_cmd($fp, 'EHLO ' . $ehloHost, '250');
        if ($enc === 'tls' && $port !== 465) {
            mail_smtp_cmd($fp, 'STARTTLS', '220');
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_ANY_CLIENT)) {
                return ['ok' => false, 'error' => 'SMTP STARTTLS failed'];
            }
            mail_smtp_cmd($fp, 'EHLO ' . $ehloHost, '250');
        }
        if ($user !== '' || $pass !== '') {
            mail_smtp_cmd($fp, 'AUTH LOGIN', '334');
            mail_smtp_cmd($fp, base64_encode($user), '334');
            mail_smtp_cmd($fp, base64_encode($pass), '235');
        }
        mail_smtp_cmd($fp, 'MAIL FROM:<' . $fromEmail . '>', '250');
        mail_smtp_cmd($fp, 'RCPT TO:<' . $toEmail . '>', '250');
        mail_smtp_cmd($fp, 'DATA', '354');

        $messageId = '<' . bin2hex(random_bytes(12)) . '@' . preg_replace('/[^a-z0-9.-]+/i', '', $host) . '>';
        $date = gmdate('D, d M Y H:i:s') . ' +0000';
        $fromHeader = mail_smtp_format_address($fromEmail, $fromName);
        $subjectEnc = mail_smtp_encode_header($subject);
        $boundary = 'bnd_' . bin2hex(random_bytes(8));
        $plainNorm = str_replace("\r\n", "\n", $plainBody);
        $plainNorm = str_replace("\r", "\n", $plainNorm);

        $mime = '';
        if ($htmlBody !== null && $htmlBody !== '') {
            $htmlNorm = str_replace("\r\n", "\n", $htmlBody);
            $htmlNorm = str_replace("\r", "\n", $htmlNorm);
            $mime .= 'MIME-Version: 1.0' . "\r\n";
            $mime .= 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . "\r\n\r\n";
            $mime .= '--' . $boundary . "\r\n";
            $mime .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
            $mime .= 'Content-Transfer-Encoding: quoted-printable' . "\r\n\r\n";
            $mime .= mail_qp_body($plainNorm) . "\r\n\r\n";
            $mime .= '--' . $boundary . "\r\n";
            $mime .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
            $mime .= 'Content-Transfer-Encoding: quoted-printable' . "\r\n\r\n";
            $mime .= mail_qp_body($htmlNorm) . "\r\n\r\n";
            $mime .= '--' . $boundary . '--' . "\r\n";
        } else {
            $mime .= 'MIME-Version: 1.0' . "\r\n";
            $mime .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
            $mime .= 'Content-Transfer-Encoding: quoted-printable' . "\r\n\r\n";
            $mime .= mail_qp_body($plainNorm) . "\r\n";
        }

        $raw = ''
            . 'From: ' . $fromHeader . "\r\n"
            . 'To: <' . $toEmail . '>' . "\r\n"
            . 'Subject: ' . $subjectEnc . "\r\n"
            . 'Date: ' . $date . "\r\n"
            . 'Message-ID: ' . $messageId . "\r\n"
            . $mime;
        $raw = mail_smtp_dot_stuff($raw);
        if (fwrite($fp, $raw . "\r\n.\r\n") === false) {
            throw new RuntimeException('SMTP could not send message body');
        }
        $endResp = mail_smtp_read_multiline($fp);
        if (!str_starts_with(trim($endResp), '250')) {
            throw new RuntimeException('SMTP DATA failed: ' . trim($endResp));
        }
        mail_smtp_cmd($fp, 'QUIT', '221');
    } catch (Throwable $e) {
        fclose($fp);

        return ['ok' => false, 'error' => $e->getMessage()];
    }
    fclose($fp);

    return ['ok' => true, 'error' => ''];
}

function mail_smtp_format_address(string $email, string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '<' . $email . '>';
    }
    $enc = mail_smtp_encode_header($name);

    return $enc . ' <' . $email . '>';
}

function mail_smtp_encode_header(string $text): string
{
    if (preg_match('/[^\x20-\x7E]/', $text)) {
        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }

    return $text;
}

/**
 * @param resource $fp
 */
function mail_smtp_read_multiline($fp): string
{
    $out = '';
    while (true) {
        $line = fgets($fp, 8192);
        if ($line === false) {
            break;
        }
        $out .= $line;
        if (strlen($line) < 4) {
            break;
        }
        if ($line[3] === ' ') {
            break;
        }
    }

    return $out;
}

/**
 * @param resource $fp
 */
function mail_smtp_cmd($fp, string $command, string $expectPrefix): void
{
    $payload = $command === '' ? '' : ($command . "\r\n");
    if ($payload !== '' && fwrite($fp, $payload) === false) {
        throw new RuntimeException('SMTP write failed');
    }
    $resp = mail_smtp_read_multiline($fp);
    if ($resp === '' || !str_starts_with($resp, $expectPrefix)) {
        throw new RuntimeException('SMTP unexpected response: ' . trim($resp));
    }
}

function mail_smtp_dot_stuff(string $message): string
{
    $message = str_replace("\r\n", "\n", $message);
    $message = str_replace("\r", "\n", $message);
    $lines = explode("\n", $message);
    $out = [];
    foreach ($lines as $line) {
        if ($line !== '' && $line[0] === '.') {
            $out[] = '.' . $line;
        } else {
            $out[] = $line;
        }
    }

    return implode("\r\n", $out);
}

function mail_qp_body(string $text): string
{
    $qp = quoted_printable_encode($text);
    $qp = str_replace("\r\n", "\n", $qp);

    return str_replace("\n", "\r\n", $qp);
}
