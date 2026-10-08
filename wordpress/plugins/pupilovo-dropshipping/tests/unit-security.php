<?php
/** Run inside WordPress container. No persistent fixtures are created. */
if (PHP_SAPI !== 'cli') { exit; }
require '/var/www/html/wp-load.php';

use Pupilovo\SupplierHub\Infrastructure\Security\HeaderPolicy;
use Pupilovo\SupplierHub\Infrastructure\Security\SecretCipher;
use Pupilovo\SupplierHub\Infrastructure\Security\SourceUrlGuard;

$checks = 0;
function security_check($condition, string $label): void {
    if (!$condition) { throw new RuntimeException('FAIL: ' . $label); }
    ++$GLOBALS['checks']; echo 'PASS: ' . $label . PHP_EOL;
}

$cipher = new SecretCipher();
$plain = ['type' => 'bearer', 'token' => 'very-secret-test-token'];
$encrypted = $cipher->encrypt($plain);
security_check(!str_contains($encrypted, $plain['token']), 'ciphertext does not contain plaintext token');
security_check($cipher->decrypt($encrypted) === $plain, 'secret cipher round trip');
$tampered = substr($encrypted, 0, -2) . 'xx';
try { $cipher->decrypt($tampered); security_check(false, 'tampered ciphertext rejected'); }
catch (RuntimeException) { security_check(true, 'tampered ciphertext rejected'); }

$headers = new HeaderPolicy();
security_check($headers->sanitize(['X-Api-Key' => 'secret']) === ['x-api-key' => 'secret'], 'custom authentication header accepted');
foreach ([['Host' => 'internal'], ['Cookie' => 'session=x'], ['X-Test' => "ok\r\nHost: evil"]] as $unsafe) {
    try { $headers->sanitize($unsafe); security_check(false, 'unsafe header rejected'); }
    catch (InvalidArgumentException) { security_check(true, 'unsafe header rejected'); }
}

$resolver = static fn(string $host): array => match ($host) {
    'public.example' => ['93.184.216.34'],
    'mixed.example' => ['93.184.216.34', '127.0.0.1'],
    default => [],
};
$guard = new SourceUrlGuard($resolver);
security_check(!is_wp_error($guard->validate('https://public.example/feed.xml')), 'public HTTPS source accepted');
security_check(is_wp_error($guard->validate('http://public.example/feed.xml')), 'HTTP requires explicit opt-in');
security_check(!is_wp_error($guard->validate('http://public.example/feed.xml', true)), 'HTTP accepted only with explicit opt-in');
foreach (['http://localhost/feed', 'https://127.0.0.1/feed', 'https://169.254.169.254/latest/meta-data', 'file:///etc/passwd', 'https://user:pass@public.example/feed', 'https://mixed.example/feed'] as $url) {
    security_check(is_wp_error($guard->validate($url, true)), 'unsafe URL rejected: ' . $url);
}

echo $checks . ' security unit checks passed.' . PHP_EOL;
