<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$pw = $argv[1] ?? '';
if ($pw === '') {
    fwrite(STDERR, "Usage: php scripts/hash_password.php \"your-password\"\n");
    exit(1);
}

echo password_hash($pw, PASSWORD_DEFAULT), "\n";
