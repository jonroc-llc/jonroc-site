<?php
/**
 * Jonroc Contact Form Handler
 * Ajax POST -> VPS mail relay -> Gmail SMTP -> benjamin@jonroc.com
 */

define('TO_EMAIL',       'benjamin@jonroc.com');
define('RELAY_URL',      'https://api.jonroc.dev/mail-relay.php');
define('RELAY_SECRET',   '64f1e1dfe157ebe3b1b6c89c2816053f04262e9d7cf63a86');
define('ALTCHA_HMAC_KEY','08c3e6b6326436554bbfbb1301d30359d84f43f1d351e7a941b197081fc08fd0');

$allowed = ['https://jonroc.dev','https://www.jonroc.dev','https://jonroc.com','https://www.jonroc.com'];
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    http_response_code(403); exit(json_encode(['error'=>'Forbidden']));
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); exit(json_encode(['error'=>'Method not allowed'])); }

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { http_response_code(400); exit(json_encode(['error'=>'Invalid request'])); }

if (!empty($input['website_url'])) { exit(json_encode(['success'=>true])); }

function altcha_verify($payload) {
    if (empty($payload)) return false;
    $d = json_decode(base64_decode($payload, true), true);
    if (!$d) return false;
    if (($d['algorithm']??'') !== 'SHA-256' || empty($d['challenge']) || !isset($d['number']) || empty($d['salt']) || empty($d['signature'])) return false;
    parse_str(parse_url($d['salt'], PHP_URL_QUERY) ?? '', $sp);
    if (!empty($sp['expires']) && (int)$sp['expires'] < time()) return false;
    if (!hash_equals(hash('sha256', $d['salt'].$d['number']), $d['challenge'])) return false;
    if (!hash_equals(hash_hmac('sha256', $d['challenge'], ALTCHA_HMAC_KEY), $d['signature'])) return false;
    return true;
}

if (!altcha_verify(trim($input['altcha'] ?? ''))) {
    http_response_code(400); exit(json_encode(['error'=>'CAPTCHA verification failed. Please try again.']));
}

$firstName = trim($input['firstName'] ?? '');
$lastName  = trim($input['lastName']  ?? '');
$email     = trim($input['email']     ?? '');
$phone     = trim($input['phone']     ?? '');
$company   = trim($input['company']   ?? '');
$message   = trim($input['message']   ?? '');

if (!$firstName || !$email)             { http_response_code(400); exit(json_encode(['error'=>'First name and email are required.'])); }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { http_response_code(400); exit(json_encode(['error'=>'Invalid email address.'])); }

function is_spam_email(string $e): bool {
    $at = strrpos($e,'@'); if ($at===false) return false;
    $local = strtolower(substr($e,0,$at)); $domain = strtolower(substr($e,$at+1));
    if ($domain==='gmail.com') {
        $p=explode('.',$local);
        if (count($p)>=4) { $n=$s=0; foreach($p as $x){ if(ctype_digit($x))$n++; if(strlen($x)<=2)$s++; } if($n>=1&&$s>=3) return true; }
    }
    return false;
}
function is_spam_name(string $n): bool {
    $c=strtolower(preg_replace('/[^a-zA-Z]/','',  $n)); if(strlen($c)<4) return false;
    $v=preg_match_all('/[aeiou]/',$c); return (strlen($c)-$v)/strlen($c)>0.80;
}

if (is_spam_email($email)||is_spam_name($firstName)||is_spam_name($lastName)) {
    error_log("jonroc-contact: spam blocked -- $firstName $lastName <$email>");
    exit(json_encode(['success'=>true]));
}

$safeName    = htmlspecialchars(trim("$firstName $lastName"), ENT_QUOTES,'UTF-8');
$safeEmail   = htmlspecialchars($email,   ENT_QUOTES,'UTF-8');
$safePhone   = htmlspecialchars($phone,   ENT_QUOTES,'UTF-8');
$safeCompany = htmlspecialchars($company, ENT_QUOTES,'UTF-8');
$safeMsg     = nl2br(htmlspecialchars($message, ENT_QUOTES,'UTF-8'));
$subject     = "New Contact: $firstName $lastName".($company?" -- $company":'');

$rows  = "<tr><td style='padding:8px 12px;font-weight:600;color:#6B7280;vertical-align:top'>Name</td><td style='padding:8px 12px'>$safeName</td></tr>";
$rows .= "<tr><td style='padding:8px 12px;font-weight:600;color:#6B7280'>Email</td><td style='padding:8px 12px'><a href='mailto:$safeEmail' style='color:#C9A84C'>$safeEmail</a></td></tr>";
if ($safePhone)   $rows .= "<tr><td style='padding:8px 12px;font-weight:600;color:#6B7280'>Phone</td><td style='padding:8px 12px'>$safePhone</td></tr>";
if ($safeCompany) $rows .= "<tr><td style='padding:8px 12px;font-weight:600;color:#6B7280'>Company</td><td style='padding:8px 12px'>$safeCompany</td></tr>";
$msgBlock = $message ? "<div style='margin-top:20px'><p style='font-weight:600;color:#6B7280;margin:0 0 8px'>Message</p><div style='background:#F9FAFB;border:1px solid #E5E7EB;border-radius:8px;padding:16px;line-height:1.7;color:#111'>$safeMsg</div></div>" : '';

$html = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='margin:0;padding:24px;background:#F3F4F6;font-family:system-ui,sans-serif'><div style='max-width:580px;margin:0 auto;background:#fff;border-radius:12px;border:1px solid #E5E7EB;overflow:hidden'><div style='background:#0A0A0A;padding:20px 28px'><span style='color:#C9A84C;font-weight:700;font-size:18px'>JONROC</span><span style='color:#9CA3AF;font-size:14px;margin-left:12px'>New Contact Form Submission</span></div><div style='padding:28px'><table style='width:100%;border-collapse:collapse'>$rows</table>$msgBlock<hr style='border:none;border-top:1px solid #E5E7EB;margin:24px 0'><p style='margin:0;font-size:12px;color:#9CA3AF'>Submitted via jonroc.com contact form &nbsp;·&nbsp; Reply directly to this email to respond.</p></div></div></body></html>";

$payload = json_encode(['secret'=>RELAY_SECRET,'replyTo'=>"$firstName $lastName <$email>",'subject'=>$subject,'html'=>$html]);

$ch = curl_init(RELAY_URL);
curl_setopt_array($ch,[
    CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload,
    CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>20,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
]);
$relayResp = curl_exec($ch);
$relayCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr   = curl_error($ch);
curl_close($ch);

if ($curlErr || $relayCode !== 200) {
    error_log("jonroc-contact: relay failed -- HTTP $relayCode, curl: $curlErr, body: $relayResp");
    http_response_code(500);
    exit(json_encode(['error'=>'Failed to send message. Please email benjamin@jonroc.com directly or call us.']));
}

error_log("jonroc-contact: sent via relay for $firstName $lastName <$email>");
echo json_encode(['success'=>true]);
