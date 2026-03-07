<?php
// ============================================================
// Jonroc Deploy Button — jonroc.dev/deploy/
// ============================================================
// Password-protected page with a single "Push to jonroc.com"
// button. Runs push-to-production.sh on the server.
// ============================================================

define('DEPLOY_TOKEN', 'ffc41183fe983badcc1ac89b07779f8377640291419801ac');
define('DEPLOY_SCRIPT', __DIR__ . '/push-to-production.sh');

session_start();

$error   = '';
$success = '';
$output  = '';

// ── Auth ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'])) {
    if (!hash_equals(DEPLOY_TOKEN, $_POST['token'])) {
        $error = 'Invalid token.';
    } elseif (isset($_POST['action']) && $_POST['action'] === 'deploy') {
        // Run the deploy script
        $cmd    = 'bash ' . escapeshellarg(DEPLOY_SCRIPT) . ' 2>&1';
        $output = shell_exec($cmd);
        if (str_contains($output, '✅')) {
            $success = 'Push complete — jonroc.com is now live with the latest build.';
        } else {
            $error = 'Deploy script encountered an issue. Check output below.';
        }
    }
}

// ── Auth gate ────────────────────────────────────────────────
$authed = isset($_POST['token']) && hash_equals(DEPLOY_TOKEN, $_POST['token']) && !$error;
$token  = htmlspecialchars(DEPLOY_TOKEN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Deploy — Jonroc</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: system-ui, -apple-system, sans-serif;
      background: #0A0A0A;
      color: #F0F0F0;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem;
    }
    .card {
      background: #111111;
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 16px;
      padding: 2.5rem;
      max-width: 480px;
      width: 100%;
    }
    .logo {
      color: #C9A84C;
      font-size: 1.1rem;
      font-weight: 700;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      margin-bottom: 1.5rem;
    }
    h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; }
    .sub { color: #9CA3AF; font-size: 0.9rem; margin-bottom: 2rem; line-height: 1.6; }
    label { display: block; font-size: 0.8rem; font-weight: 600; color: #9CA3AF; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; }
    input[type="password"] {
      width: 100%;
      background: #1A1A1A;
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 10px;
      padding: 0.75rem 1rem;
      color: #F0F0F0;
      font-size: 0.95rem;
      margin-bottom: 1.25rem;
      outline: none;
      transition: border-color 0.2s;
    }
    input[type="password"]:focus { border-color: rgba(201,168,76,0.6); }
    .btn {
      width: 100%;
      padding: 0.9rem;
      border-radius: 9999px;
      border: none;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      transition: transform 0.15s, opacity 0.15s;
    }
    .btn:active { transform: scale(0.98); }
    .btn-auth {
      background: #1A1A1A;
      border: 1px solid rgba(201,168,76,0.4);
      color: #C9A84C;
    }
    .btn-auth:hover { border-color: #C9A84C; }
    .btn-deploy {
      background: linear-gradient(to bottom, #f5e27a, #c9a84c, #7a5c1e);
      color: #0A0A0A;
      margin-top: 0.5rem;
    }
    .btn-deploy:hover { opacity: 0.9; }
    .alert {
      padding: 0.75rem 1rem;
      border-radius: 10px;
      font-size: 0.875rem;
      margin-bottom: 1.25rem;
      line-height: 1.5;
    }
    .alert-error   { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #FCA5A5; }
    .alert-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); color: #86EFAC; }
    .output {
      background: #0A0A0A;
      border: 1px solid rgba(255,255,255,0.05);
      border-radius: 10px;
      padding: 1rem;
      font-family: monospace;
      font-size: 0.8rem;
      color: #9CA3AF;
      white-space: pre-wrap;
      margin-top: 1.25rem;
      max-height: 200px;
      overflow-y: auto;
    }
    .divider { border: none; border-top: 1px solid rgba(255,255,255,0.06); margin: 1.5rem 0; }
    .meta { font-size: 0.8rem; color: #4B5563; text-align: center; margin-top: 1.5rem; }
  </style>
</head>
<body>
<div class="card">
  <div class="logo">⚡ Jonroc Deploy</div>
  <h1>Push to jonroc.com</h1>
  <p class="sub">Syncs the current jonroc.dev build to the live production site. This is instant — make sure you've reviewed the staging site first.</p>

  <hr class="divider" />

  <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <?php if (!$authed): ?>
    <!-- Auth form -->
    <form method="POST">
      <label for="pw">Deploy Token</label>
      <input type="password" id="pw" name="token" placeholder="Enter deploy token" autofocus required />
      <button type="submit" class="btn btn-auth">Unlock →</button>
    </form>
  <?php else: ?>
    <!-- Deploy form -->
    <form method="POST">
      <input type="hidden" name="token" value="<?= $token ?>" />
      <input type="hidden" name="action" value="deploy" />
      <button type="submit" class="btn btn-deploy">🚀 Push to jonroc.com</button>
    </form>
    <?php if ($output): ?>
      <div class="output"><?= htmlspecialchars($output) ?></div>
    <?php endif; ?>
  <?php endif; ?>

  <p class="meta">jonroc.dev → jonroc.com &nbsp;·&nbsp; <?= date('D M j, Y') ?></p>
</div>
</body>
</html>
