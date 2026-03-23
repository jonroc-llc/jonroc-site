<?php
// ============================================================
// Altcha Challenge Endpoint
// GET /altcha-challenge.php — returns a fresh PoW challenge
// ============================================================

header('Content-Type: application/json');
header('Cache-Control: no-store');

define('ALTCHA_HMAC_KEY', '08c3e6b6326436554bbfbb1301d30359d84f43f1d351e7a941b197081fc08fd0');

$salt       = bin2hex(random_bytes(12));
$expires    = time() + 600; // challenge valid for 10 minutes
$saltParam  = $salt . '?expires=' . $expires;
$maxNumber  = 500000;
$secretNum  = random_int(0, $maxNumber);
$challenge  = hash('sha256', $saltParam . $secretNum);
$signature  = hash_hmac('sha256', $challenge, ALTCHA_HMAC_KEY);

echo json_encode([
    'algorithm' => 'SHA-256',
    'challenge' => $challenge,
    'maxnumber' => $maxNumber,
    'salt'      => $saltParam,
    'signature' => $signature,
]);
