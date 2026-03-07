<?php
/**
 * Jonroc Contact Form Handler
 * Ajax POST → GoHighLevel v2 API
 * 1. Upsert contact
 * 2. Add note with message
 * 3. Create opportunity in General pipeline → Prospect stage
 */

$GHL_TOKEN       = 'pit-7efb619a-4448-4d14-93bf-4acab31ca8ed';
$GHL_LOCATION_ID = 'kc9u2ab26W2B3XRglR6C';
$PIPELINE_ID     = 'ufjw2LHA0eCH7gXMFLL9';
$STAGE_ID        = 'e6c95bb4-aade-4f78-b2f9-cb0a8c347593';

// CORS — only accept from jonroc domains
$allowed = ['https://jonroc.dev', 'https://www.jonroc.dev', 'https://jonroc.com', 'https://www.jonroc.com'];
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden']));
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

// Parse JSON body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid request']));
}

// ── Altcha verification ──
define('ALTCHA_HMAC_KEY', '08c3e6b6326436554bbfbb1301d30359d84f43f1d351e7a941b197081fc08fd0');

function altcha_verify($payload) {
    if (empty($payload)) return false;

    // Payload is base64-encoded JSON
    $decoded = base64_decode($payload, true);
    if (!$decoded) return false;

    $data = json_decode($decoded, true);
    if (!$data) return false;

    $algorithm = $data['algorithm'] ?? '';
    $challenge  = $data['challenge'] ?? '';
    $number     = $data['number']    ?? null;
    $salt       = $data['salt']      ?? '';
    $signature  = $data['signature'] ?? '';

    if ($algorithm !== 'SHA-256' || !$challenge || $number === null || !$salt || !$signature) {
        return false;
    }

    // Check expiry if present in salt params
    $query = parse_url($salt, PHP_URL_QUERY) ?? '';
    parse_str($query, $saltParams);
    if (!empty($saltParams['expires']) && (int)$saltParams['expires'] < time()) {
        return false; // expired
    }

    // Verify the challenge hash
    $expectedChallenge = hash('sha256', $salt . $number);
    if (!hash_equals($challenge, $expectedChallenge)) return false;

    // Verify the HMAC signature
    $expectedSignature = hash_hmac('sha256', $challenge, ALTCHA_HMAC_KEY);
    if (!hash_equals($signature, $expectedSignature)) return false;

    return true;
}

$altchaPayload = trim($input['altcha'] ?? '');
if (!altcha_verify($altchaPayload)) {
    http_response_code(400);
    exit(json_encode(['error' => 'CAPTCHA verification failed. Please try again.']));
}
// ── End Altcha ──

// Accept firstName/lastName directly, or split a combined name field
$firstName = trim($input['firstName'] ?? '');
$lastName  = trim($input['lastName']  ?? '');
if (!$firstName && !empty($input['name'])) {
    $parts     = preg_split('/\s+/', trim($input['name']), 2);
    $firstName = $parts[0];
    $lastName  = $parts[1] ?? '';
}

$email    = trim($input['email']    ?? '');
$company  = trim($input['company']  ?? '');
$phone    = trim($input['phone']    ?? '');
$interest = trim($input['interest'] ?? '');
$message  = trim($input['message']  ?? '');

if (!$firstName || !$email) {
    http_response_code(400);
    exit(json_encode(['error' => 'First name and email are required.']));
}

// Helper: GHL v2 POST
function ghl_post($url, $data, $token, $timeout = 15) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $token",
            'Version: 2021-07-28',
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
    ]);
    $body    = curl_exec($ch);
    $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);
    return ['body' => $body, 'code' => $code, 'error' => $curlErr];
}

// ── 1. Upsert contact ──
$contact = [
    'firstName'  => $firstName,
    'lastName'   => $lastName,
    'email'      => $email,
    'locationId' => $GHL_LOCATION_ID,
    'source'     => 'jonroc.dev contact form',
    'tags'       => ['website-lead'],
];
if ($phone)   $contact['phone']       = $phone;
if ($company) $contact['companyName'] = $company;

$res = ghl_post('https://services.leadconnectorhq.com/contacts/upsert', $contact, $GHL_TOKEN);

if ($res['code'] >= 400 || $res['error']) {
    error_log("GHL contact upsert failed: HTTP {$res['code']} | curl: {$res['error']} | body: {$res['body']}");
    http_response_code(502);
    exit(json_encode(['error' => 'CRM error. Please email ben@jonroc.com.']));
}

$contactId = json_decode($res['body'], true)['contact']['id'] ?? null;

if (!$contactId) {
    error_log("GHL contact upsert: no contactId in response | body: {$res['body']}");
    http_response_code(502);
    exit(json_encode(['error' => 'CRM error. Please email ben@jonroc.com.']));
}

// ── 2. Add note with message ──
if ($interest || $message) {
    $note = '';
    if ($interest) $note .= "Service interest: $interest\n\n";
    if ($message)  $note .= "Message:\n$message";

    $noteRes = ghl_post(
        "https://services.leadconnectorhq.com/contacts/$contactId/notes",
        ['body' => $note, 'userId' => ''],
        $GHL_TOKEN,
        10
    );

    if ($noteRes['code'] >= 400) {
        error_log("GHL note failed: HTTP {$noteRes['code']} | body: {$noteRes['body']}");
    }
}

// ── 3. Create opportunity in General pipeline → Prospect stage ──
$oppName = trim("$firstName $lastName") . ' - Website Lead';
$opp = [
    'pipelineId'      => $PIPELINE_ID,
    'locationId'      => $GHL_LOCATION_ID,
    'name'            => $oppName,
    'pipelineStageId' => $STAGE_ID,
    'status'          => 'open',
    'contactId'       => $contactId,
    'monetaryValue'   => 30000,
];

$oppRes = ghl_post(
    'https://services.leadconnectorhq.com/opportunities/',
    $opp,
    $GHL_TOKEN,
    15
);

if ($oppRes['code'] >= 400 || $oppRes['error']) {
    error_log("GHL opportunity failed: HTTP {$oppRes['code']} | curl: {$oppRes['error']} | body: {$oppRes['body']}");
}

echo json_encode(['success' => true]);
