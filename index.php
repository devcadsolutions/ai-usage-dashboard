<?php
declare(strict_types=1);
session_start();

$dataDir = __DIR__ . '/data';
$dataFile = $dataDir . '/usage.json';
if (!is_dir($dataDir)) mkdir($dataDir, 0700, true);
if (!is_file($dataFile)) file_put_contents($dataFile, json_encode(['openai' => [], 'claude' => [], 'updated_at' => null], JSON_PRETTY_PRINT), LOCK_EX);

$appPassword = getenv('USAGE_APP_PASSWORD') ?: '';
$ingestToken = getenv('USAGE_INGEST_TOKEN') ?: '';

function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function loadUsage(string $file): array {
    $raw = @file_get_contents($file);
    $data = json_decode($raw ?: '', true);
    return is_array($data) ? $data : ['openai' => [], 'claude' => [], 'updated_at' => null];
}
function saveUsage(string $file, array $data): void {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'login') {
        if ($appPassword !== '' && hash_equals($appPassword, (string)($_POST['password'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['authenticated'] = true;
        } else {
            $_SESSION['error'] = 'Invalid password.';
        }
        header('Location: /'); exit;
    }
    if ($action === 'logout') { session_destroy(); header('Location: /'); exit; }
}

$authenticated = !empty($_SESSION['authenticated']);
if (!$authenticated && $action !== 'ingest') {
    $error = $_SESSION['error'] ?? ($appPassword === '' ? 'The server password is not configured yet.' : '');
    unset($_SESSION['error']);
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>AI Usage — Private</title><link rel="stylesheet" href="/style.css"></head><body><main class="login"><div class="brand">AI Usage</div><h1>Private dashboard</h1><p>Sign in to view usage data.</p><?php if ($error): ?><div class="error"><?=h($error)?></div><?php endif; ?><form method="post"><input type="hidden" name="action" value="login"><label>Password<input type="password" name="password" autocomplete="current-password" required></label><button type="submit">Sign in</button></form></main></body></html><?php exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'manual_update') {
    $provider = ($_POST['provider'] ?? '') === 'claude' ? 'claude' : 'openai';
    $usage = loadUsage($dataFile);
    $usage[$provider] = ['used' => trim((string)($_POST['used'] ?? '')), 'remaining' => trim((string)($_POST['remaining'] ?? '')), 'reset' => trim((string)($_POST['reset'] ?? '')), 'source' => 'manual'];
    $usage['updated_at'] = gmdate('c'); saveUsage($dataFile, $usage); header('Location: /'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'ingest') {
    $provided = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($ingestToken === '' || !hash_equals('Bearer ' . $ingestToken, $provided)) { http_response_code(401); header('Content-Type: application/json'); echo json_encode(['error' => 'Unauthorized']); exit; }
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload) || !in_array($payload['provider'] ?? '', ['openai', 'claude'], true)) { http_response_code(400); echo json_encode(['error' => 'Invalid payload']); exit; }
    $usage = loadUsage($dataFile); $provider = $payload['provider']; unset($payload['provider']); $usage[$provider] = $payload; $usage['updated_at'] = gmdate('c'); saveUsage($dataFile, $usage); header('Content-Type: application/json'); echo json_encode(['ok' => true]); exit;
}

$usage = loadUsage($dataFile);
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>AI Usage Dashboard</title><link rel="stylesheet" href="/style.css"></head><body><header><div><div class="eyebrow">PRIVATE DASHBOARD</div><h1>AI Usage</h1></div><form method="post"><input type="hidden" name="action" value="logout"><button class="secondary" type="submit">Sign out</button></form></header><main><div class="notice">Automatic provider sync is not connected yet. Manual values are available while the private browser connector is being added.</div><section class="grid"><?php foreach (['openai' => 'OpenAI / Codex', 'claude' => 'Claude'] as $key => $label): $item = is_array($usage[$key] ?? null) ? $usage[$key] : []; ?><article class="card"><div class="card-head"><h2><?=h($label)?></h2><span class="pill"><?=h($item['source'] ?? 'Not connected')?></span></div><div class="metric"><span>Remaining</span><strong><?=h($item['remaining'] ?? '—')?></strong></div><dl><div><dt>Used</dt><dd><?=h($item['used'] ?? '—')?></dd></div><div><dt>Reset</dt><dd><?=h($item['reset'] ?? '—')?></dd></div></dl><details><summary>Update manually</summary><form method="post" class="small-form"><input type="hidden" name="action" value="manual_update"><input type="hidden" name="provider" value="<?=h($key)?>"><label>Used<input name="used" value="<?=h($item['used'] ?? '')?>"></label><label>Remaining<input name="remaining" value="<?=h($item['remaining'] ?? '')?>"></label><label>Reset time<input name="reset" value="<?=h($item['reset'] ?? '')?>"></label><button type="submit">Save</button></form></details></article><?php endforeach; ?></section><p class="updated">Last updated: <?=h($usage['updated_at'] ?? 'never')?></p></main></body></html>
