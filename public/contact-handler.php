<?php
/**
 * Jonroc Contact Form Handler
 * Ajax POST → email to benjamin@jonroc.com
 */

// ── Config ──────────────────────────────────────────────────
define('TO_EMAIL',        'benjamin@jonroc.com');
define('FROM_DOMAIN',     'jonroc.com');
define('ALTCHA_HMAC_KEY', '08c3e6b6326436554bbfbb1301d30359d84f43f1d351e7a941b197081fc08fd0');

// ── CORS ─────────────────────────────────────────────────────
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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); exit(json_encode(['error' => 'Method not allowed'])); }

// ── Parse input ──────────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { http_response_code(400); exit(json_encode(['error' => 'Invalid request'])); }

// ── Honeypot check ───────────────────────────────────────────
// Hidden field bots fill in — humans never see it
if (!empty($input['website_url'])) {
    // Silently succeed so bots don't know they were blocked
    exit(json_encode(['success' => true]));
}

// ── Altcha verification ──────────────────────────────────────
function altcha_verify($payload) {
    if (empty($payload)) return false;
    $decoded = base64_decode($payload, true);
    if (!$decoded) return false;
    $data = json_decode($decoded, true);
    if (!$data) return false;

    $algorithm = $data['algorithm'] ?? '';
    $challenge  = $data['challenge'] ?? '';
    $number     = $data['number']    ?? null;
    $salt       = $data['salt']      ?? '';
    $signature  = $data['signature'] ?? '';

    if ($algorithm !== 'SHA-256' || !$challenge || $number === null || !$salt || !$signature) return false;

    $query = parse_url($salt, PHP_URL_QUERY) ?? '';
    parse_str($query, $saltParams);
    if (!empty($saltParams['expires']) && (int)$saltParams['expires'] < time()) return false;

    if (!hash_equals(hash('sha256', $salt . $number), $challenge)) return false;
    if (!hash_equals(hash_hmac('sha256', $challenge, ALTCHA_HMAC_KEY), $signature)) return false;

    return true;
}

$altchaPayload = trim($input['altcha'] ?? '');
if (!altcha_verify($altchaPayload)) {
    http_response_code(400);
    exit(json_encode(['error' => 'CAPTCHA verification failed. Please try again.']));
}

// ── Extract and validate fields ───────────────────────────────
$firstName = trim($input['firstName'] ?? '');
$lastName  = trim($input['lastName']  ?? '');
if (!$firstName && !empty($input['name'])) {
    $parts     = preg_split('/\s+/', trim($input['name']), 2);
    $firstName = $parts[0];
    $lastName  = $parts[1] ?? '';
}

$email   = trim($input['email']   ?? '');
$phone   = trim($input['phone']   ?? '');
$company = trim($input['company'] ?? '');
$message = trim($input['message'] ?? '');

if (!$firstName || !$email) {
    http_response_code(400);
    exit(json_encode(['error' => 'First name and email are required.']));
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid email address.']));
}

// ── Spam detection ────────────────────────────────────────────

/**
 * Detect dotted/numbered Gmail spam pattern.
 * Examples that get through: e.k.i.j.umuba.x.7.5@gmail.com
 *                             a.m.olu.luhe.j.67@gmail.com
 * Pattern: 4+ dot-separated segments where short segments and numbers dominate.
 */
function is_spam_email(string $email): bool {
    $atPos  = strrpos($email, '@');
    if ($atPos === false) return false;
    $local  = strtolower(substr($email, 0, $atPos));
    $domain = strtolower(substr($email, $atPos + 1));

    if ($domain === 'gmail.com') {
        $parts = explode('.', $local);
        if (count($parts) >= 4) {
            $numericCount = 0;
            $shortCount   = 0;
            foreach ($parts as $p) {
                if (ctype_digit($p))  $numericCount++;
                if (strlen($p) <= 2)  $shortCount++;
            }
            // Any numeric segment + lots of short segments = spam
            if ($numericCount >= 1 && $shortCount >= 3) return true;
        }
    }
    return false;
}

/**
 * Detect bot-generated random names by consonant ratio.
 * Legit names: 35-65% consonants. Spam names: often >80%.
 * Examples: Xztne, Vbfpkatae, Uzqzxtxr, Mhdsekxu
 */
function is_spam_name(string $name): bool {
    $clean = strtolower(preg_replace('/[^a-zA-Z]/', '', $name));
    if (strlen($clean) < 4) return false;
    $vowels     = preg_match_all('/[aeiou]/', $clean);
    $consonants = strlen($clean) - $vowels;
    return ($consonants / strlen($clean)) > 0.80;
}

$isSpam = is_spam_email($email)
       || is_spam_name($firstName)
       || is_spam_name($lastName);

if ($isSpam) {
    error_log("jonroc-contact: spam blocked — {$firstName} {$lastName} <{$email}>");
    // Silently succeed so bots don't adapt
    exit(json_encode(['success' => true]));
}

// ── Build and send email ─────────────────────────────────────
$safeFirst   = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
$safeLast    = htmlspecialchars($lastName,  ENT_QUOTES, 'UTF-8');
$safeName    = trim("$safeFirst $safeLast");
$safeEmail   = htmlspecialchars($email,     ENT_QUOTES, 'UTF-8');
$safePhone   = htmlspecialchars($phone,     ENT_QUOTES, 'UTF-8');
$safeCompany = htmlspecialchars($company,   ENT_QUOTES, 'UTF-8');
$safeMsg     = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

$subject = "New Contact: $firstName $lastName" . ($company ? " — $company" : '');

$rows = "<tr><td style='padding:8px 12px;font-weight:600;color:#6B7280;white-space:nowrap;vertical-align:top;'>Name</td><td style='padding:8px 12px;'>$safeName</td></tr>";
$rows .= "<tr><td style='padding:8px 12px;font-weight:600;color:#6B7280;'>Email</td><td style='padding:8px 12px;'><a href='mailto:$safeEmail' style='color:#C9A84C;'>$safeEmail</a></td></tr>";
if ($safePhone)   $rows .= "<tr><td style='padding:8px 12px;font-weight:600;color:#6B7280;'>Phone</td><td style='padding:8px 12px;'><a href='tel:$safePhone' style='color:#C9A84C;'>$safePhone</a></td></tr>";
if ($safeCompany) $rows .= "<tr><td style='padding:8px 12px;font-weight:600;color:#6B7280;'>Company</td><td style='padding:8px 12px;'>$safeCompany</td></tr>";

$msgBlock = $message
    ? "<div style='margin-top:20px;'><p style='font-weight:600;color:#6B7280;margin:0 0 8px;'>Message</p><div style='background:#F9FAFB;border:1px solid #E5E7EB;border-radius:8px;padding:16px;line-height:1.7;color:#111;'>$safeMsg</div></div>"
    : '';

$html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>New Contact</title></head>
<body style="margin:0;padding:24px;background:#F3F4F6;font-family:system-ui,-apple-system,sans-serif;">
  <div style="max-width:580px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #E5E7EB;">
    <div style="background:#0A0A0A;padding:20px 28px;display:flex;align-items:center;gap:12px;">
      <span style="color:#C9A84C;font-weight:700;font-size:18px;letter-spacing:0.05em;">JONROC</span>
      <span style="color:#9CA3AF;font-size:14px;">New Contact Form Submission</span>
    </div>
    <div style="padding:28px;">
      <table style="width:100%;border-collapse:collapse;">$rows</table>
      $msgBlock
      <hr style="border:none;border-top:1px solid #E5E7EB;margin:24px 0;">
      <p style="margin:0;font-size:12px;color:#9CA3AF;">Submitted via jonroc.com contact form &nbsp;·&nbsp; Reply directly to this email to respond.</p>
    </div>
  </div>
</body>
</html>
HTML;

$fromEmail = 'noreply@' . FROM_DOMAIN;
$headers   = implode("\r\n", [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    "From: Jonroc Website <{$fromEmail}>",
    "Reply-To: {$firstName} {$lastName} <{$email}>",
    'X-Mailer: PHP/' . PHP_VERSION,
    'X-Priority: 1',
]);

$sent = mail(TO_EMAIL, $subject, $html, $headers);

if (!$sent) {
    error_log("jonroc-contact: mail() failed for <$email>");
    http_response_code(500);
    exit(json_encode(['error' => 'Failed to send message. Please email benjamin@jonroc.com directly or call us.']));
}

error_log("jonroc-contact: sent email for {$firstName} {$lastName} <{$email}>");
echo json_encode(['success' => true]);
