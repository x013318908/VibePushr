<?php

declare(strict_types=1);

session_start();

const ROOT_DIR = __DIR__;
const JOB_DIR_NAME = '.vp_jobs';
const LOG_FILE_NAME = 'vp.log';
const APP_PASSWORD_HASH = '__REPLACE_WITH_PASSWORD_HASH__';
const MAX_ERROR_KEEP = 10;

function now_iso(): string
{
    return gmdate('c');
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ensure_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function require_csrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (!is_string($token) || $token === '') {
        json_response(['ok' => false, 'error' => 'invalid_csrf'], 403);
    }

    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
    if ($sessionToken === '' || !hash_equals($sessionToken, $token)) {
        json_response(['ok' => false, 'error' => 'invalid_csrf'], 403);
    }
}

function is_authed(): bool
{
    return !empty($_SESSION['authed']);
}

function require_auth(): void
{
    if (!is_authed()) {
        json_response(['ok' => false, 'error' => 'unauthorized'], 401);
    }
}

function jobs_dir(): string
{
    $dir = ROOT_DIR . DIRECTORY_SEPARATOR . JOB_DIR_NAME;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return $dir;
}

function log_audit(string $message): void
{
    $line = sprintf('[%s] %s %s' . "\n", now_iso(), $_SERVER['REMOTE_ADDR'] ?? '-', $message);
    @file_put_contents(ROOT_DIR . DIRECTORY_SEPARATOR . LOG_FILE_NAME, $line, FILE_APPEND | LOCK_EX);
}

function normalize_relpath(string $relpath): ?string
{
    if (strpos($relpath, "\0") !== false) {
        return null;
    }

    $relpath = trim(str_replace('\\', '/', $relpath));
    if ($relpath === '' || $relpath[0] === '/') {
        return null;
    }

    $segments = explode('/', $relpath);
    $clean = [];

    foreach ($segments as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }

        if ($segment === '..') {
            return null;
        }

        $clean[] = $segment;
    }

    if (empty($clean)) {
        return null;
    }

    return implode('/', $clean);
}

function is_within_root(string $path): bool
{
    $root = realpath(ROOT_DIR);
    if ($root === false) {
        return false;
    }

    $root = rtrim(str_replace('\\', '/', $root), '/');
    $target = str_replace('\\', '/', $path);

    return $target === $root || strpos($target, $root . '/') === 0;
}

function resolve_safe_path(string $relpath, bool $ensure_parent = false): ?string
{
    $norm = normalize_relpath($relpath);
    if ($norm === null) {
        return null;
    }

    $rootReal = realpath(ROOT_DIR);
    if ($rootReal === false) {
        return null;
    }

    $full = $rootReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $norm);
    $parent = dirname($full);

    if ($ensure_parent && !is_dir($parent)) {
        @mkdir($parent, 0755, true);
    }

    $parentReal = realpath($parent);
    if ($parentReal === false || !is_within_root($parentReal)) {
        return null;
    }

    if (file_exists($full)) {
        $real = realpath($full);
        if ($real === false || !is_within_root($real)) {
            return null;
        }

        return $real;
    }

    $candidate = $parentReal . DIRECTORY_SEPARATOR . basename($full);
    if (!is_within_root($candidate)) {
        return null;
    }

    return $candidate;
}

function write_atomic(string $targetPath, string $data, ?int $mtime = null): bool
{
    $ok = @file_put_contents($targetPath, $data, LOCK_EX) !== false;

    if ($ok && $mtime !== null && $mtime > 0) {
        @touch($targetPath, $mtime);
    }

    return $ok;
}

function append_job_log(string $jobId, array $entry): void
{
    $path = jobs_dir() . DIRECTORY_SEPARATOR . $jobId . '.jsonl';
    $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
}

function job_state_path(string $jobId): string
{
    return jobs_dir() . DIRECTORY_SEPARATOR . $jobId . '.state.json';
}

function save_job_state(string $jobId, array $state): void
{
    @file_put_contents(
        job_state_path($jobId),
        json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

function load_job_state(string $jobId): ?array
{
    $path = job_state_path($jobId);
    if (!is_file($path)) {
        return null;
    }

    $raw = @file_get_contents($path);
    if ($raw === false) {
        return null;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function scan_dirs(): array
{
    $root = realpath(ROOT_DIR);
    if ($root === false) {
        return [];
    }

    $stats = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($it as $info) {
        /** @var SplFileInfo $info */
        $full = $info->getPathname();

        if (strpos($full, DIRECTORY_SEPARATOR . JOB_DIR_NAME . DIRECTORY_SEPARATOR) !== false) {
            continue;
        }

        $rel = ltrim(substr($full, strlen($root)), DIRECTORY_SEPARATOR);
        $rel = str_replace('\\', '/', $rel);

        if ($rel === '' || $rel === LOG_FILE_NAME || $rel === basename(__FILE__)) {
            continue;
        }
        if (path_has_hidden_segment($rel)) {
            continue;
        }

        if ($info->isDir()) {
            if (!isset($stats[$rel])) {
                $stats[$rel] = ['path' => $rel, 'file_count' => 0, 'total_bytes' => 0];
            }
            continue;
        }

        if (!$info->isFile()) {
            continue;
        }

        $size = (int) $info->getSize();
        $dir = dirname($rel);

        while ($dir !== '.' && $dir !== '') {
            if (!isset($stats[$dir])) {
                $stats[$dir] = ['path' => $dir, 'file_count' => 0, 'total_bytes' => 0];
            }

            $stats[$dir]['file_count']++;
            $stats[$dir]['total_bytes'] += $size;
            $dir = dirname($dir);
        }
    }

    ksort($stats, SORT_NATURAL);

    return array_values($stats);
}

function path_has_hidden_segment(string $path): bool
{
    $segments = explode('/', $path);
    foreach ($segments as $segment) {
        if ($segment !== '' && $segment[0] === '.') {
            return true;
        }
    }
    return false;
}

if (defined('VP_UNIT_TEST_MODE') && VP_UNIT_TEST_MODE === true) {
    return;
}

$action = (string) ($_REQUEST['action'] ?? '');

if ($action !== '') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
    }

    if ($action !== 'login' && $action !== 'logout') {
        require_auth();
    }

    if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = (string) ($_POST['password'] ?? '');
        if (APP_PASSWORD_HASH !== '__REPLACE_WITH_PASSWORD_HASH__' && password_verify($password, APP_PASSWORD_HASH)) {
            session_regenerate_id(true);
            $_SESSION['authed'] = true;
            ensure_csrf_token();
            json_response(['ok' => true]);
        }

        log_audit('login_failed');
        json_response(['ok' => false, 'error' => 'invalid_password'], 401);
    }

    if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $_SESSION = [];
        session_destroy();
        json_response(['ok' => true]);
    }

    if ($action === 'list_dirs' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        json_response(['ok' => true, 'dirs' => scan_dirs()]);
    }

    if ($action === 'sync_init' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $totalFiles = max(0, (int) ($_POST['total_files'] ?? 0));
        $jobId = bin2hex(random_bytes(8));
        $state = [
            'job_id' => $jobId,
            'total' => $totalFiles,
            'done' => 0,
            'ok' => 0,
            'skip' => 0,
            'fail' => 0,
            'current_path' => '',
            'errors' => [],
            'created_at' => now_iso(),
            'updated_at' => now_iso(),
        ];

        save_job_state($jobId, $state);
        append_job_log($jobId, ['ts' => now_iso(), 'event' => 'init', 'total' => $totalFiles]);
        json_response(['ok' => true, 'job_id' => $jobId]);
    }

    if ($action === 'progress' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $jobId = (string) ($_GET['job_id'] ?? '');
        if (!preg_match('/^[a-f0-9]{16}$/', $jobId)) {
            json_response(['ok' => false, 'error' => 'invalid_job'], 400);
        }

        $state = load_job_state($jobId);
        if ($state === null) {
            json_response(['ok' => false, 'error' => 'job_not_found'], 404);
        }

        json_response(['ok' => true, 'progress' => $state]);
    }

    if ($action === 'sync_put' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $jobId = (string) ($_REQUEST['job_id'] ?? '');
        if (!preg_match('/^[a-f0-9]{16}$/', $jobId)) {
            json_response(['ok' => false, 'error' => 'invalid_job'], 400);
        }

        $state = load_job_state($jobId);
        if ($state === null) {
            json_response(['ok' => false, 'error' => 'job_not_found'], 404);
        }

        $relpath = normalize_relpath((string) ($_REQUEST['relpath'] ?? ''));
        if ($relpath === null) {
            json_response(['ok' => false, 'error' => 'invalid_relpath'], 400);
        }

        $target = resolve_safe_path($relpath, true);
        if ($target === null) {
            json_response(['ok' => false, 'error' => 'unsafe_path'], 400);
        }

        $raw = file_get_contents('php://input');
        if ($raw === false) {
            $raw = '';
        }

        $encoding = strtolower((string) ($_SERVER['HTTP_X_VIBE_ENCODING'] ?? ($_REQUEST['encoding'] ?? 'identity')));
        $content = $raw;
        if ($encoding === 'gzip') {
            $decoded = gzdecode($raw);
            if ($decoded === false) {
                json_response(['ok' => false, 'error' => 'gzip_decode_failed'], 400);
            }
            $content = $decoded;
        }

        $mtime = isset($_REQUEST['mtime']) ? (int) $_REQUEST['mtime'] : null;
        $size = isset($_REQUEST['size']) ? (int) $_REQUEST['size'] : null;
        $dryRun = (string) ($_REQUEST['dry_run'] ?? '0') === '1';
        $forceOverwrite = (string) ($_REQUEST['force'] ?? '0') === '1';

        $result = 'ok';
        $message = 'stored';

        if (!$forceOverwrite && is_file($target) && $size !== null && $mtime !== null) {
            $existingSize = (int) @filesize($target);
            $existingMtime = (int) @filemtime($target);
            if ($existingSize === $size && abs($existingMtime - $mtime) <= 1) {
                $result = 'skip';
                $message = 'same_size_mtime';
            }
        }

        if ($dryRun && $result === 'ok') {
            $message = 'dry_run_no_write';
        } elseif ($result === 'ok') {
            $saved = write_atomic($target, $content, $mtime);
            if (!$saved) {
                $result = 'fail';
                $message = 'write_failed';
            }
        }

        $state['done'] = (int) $state['done'] + 1;
        $state['current_path'] = $relpath;
        $state['updated_at'] = now_iso();

        if ($result === 'ok') {
            $state['ok'] = (int) $state['ok'] + 1;
        } elseif ($result === 'skip') {
            $state['skip'] = (int) $state['skip'] + 1;
        } else {
            $state['fail'] = (int) $state['fail'] + 1;
            $state['errors'][] = ['path' => $relpath, 'error' => $message];
            if (count($state['errors']) > MAX_ERROR_KEEP) {
                $state['errors'] = array_slice($state['errors'], -MAX_ERROR_KEEP);
            }
        }

        save_job_state($jobId, $state);
        append_job_log($jobId, [
            'ts' => now_iso(),
            'path' => $relpath,
            'result' => $result,
            'message' => $message,
            'dry_run' => $dryRun,
            'force' => $forceOverwrite,
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? '-',
        ]);

        json_response([
            'ok' => $result !== 'fail',
            'result' => $result,
            'message' => $message,
        ], $result === 'fail' ? 500 : 200);
    }

    if ($action === 'sync_finish' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $jobId = (string) ($_POST['job_id'] ?? '');
        if (!preg_match('/^[a-f0-9]{16}$/', $jobId)) {
            json_response(['ok' => false, 'error' => 'invalid_job'], 400);
        }

        $state = load_job_state($jobId);
        if ($state === null) {
            json_response(['ok' => false, 'error' => 'job_not_found'], 404);
        }

        append_job_log($jobId, ['ts' => now_iso(), 'event' => 'finish']);
        json_response(['ok' => true, 'summary' => $state]);
    }

    if ($action === 'stat' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $relpath = normalize_relpath((string) ($_POST['relpath'] ?? ''));
        if ($relpath === null) {
            json_response(['ok' => false, 'error' => 'invalid_relpath'], 400);
        }

        $target = resolve_safe_path($relpath, false);
        if ($target === null) {
            json_response(['ok' => false, 'error' => 'unsafe_path'], 400);
        }

        $size = isset($_POST['size']) ? (int) $_POST['size'] : null;
        $mtime = isset($_POST['mtime']) ? (int) $_POST['mtime'] : null;

        $exists = is_file($target);
        $sameFast = false;

        if ($exists && $size !== null && $mtime !== null) {
            $sameFast = ((int) @filesize($target) === $size) && (abs((int) @filemtime($target) - $mtime) <= 1);
        }

        json_response(['ok' => true, 'exists' => $exists, 'same_fast' => $sameFast]);
    }

    json_response(['ok' => false, 'error' => 'unknown_action'], 404);
}

$csrfToken = ensure_csrf_token();
$hashPlaceholder = APP_PASSWORD_HASH === '__REPLACE_WITH_PASSWORD_HASH__';
$initialDirs = is_authed() ? scan_dirs() : [];
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VibePushr</title>
<style>
:root {
    --bg: #f4f7f8;
    --card: #ffffff;
    --line: #dce3e5;
    --text: #1b2730;
    --muted: #5a6c76;
    --accent: #0b6b57;
    --danger: #b42318;
}
* { box-sizing: border-box; }
body {
    margin: 0;
    font-family: "Segoe UI", "Hiragino Kaku Gothic ProN", Meiryo, sans-serif;
    color: var(--text);
    background: linear-gradient(150deg, #ecf6ff 0%, var(--bg) 38%, #f7fdf7 100%);
}
.container {
    max-width: 980px;
    margin: 22px auto;
    padding: 0 12px 36px;
}
.card {
    background: var(--card);
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 14px;
    margin-bottom: 12px;
    box-shadow: 0 6px 24px rgba(9, 26, 36, 0.05);
}
h1 { margin: 0 0 6px; font-size: 1.6rem; }
h2 { margin: 0 0 10px; font-size: 1.1rem; }
.small { font-size: 0.9rem; color: var(--muted); }
.row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
input, button {
    border: 1px solid var(--line);
    border-radius: 8px;
    font-size: 14px;
    padding: 8px 10px;
}
button { background: #fff; cursor: pointer; }
button.primary { background: var(--accent); border-color: var(--accent); color: #fff; }
button:disabled { opacity: 0.6; cursor: not-allowed; }
#dirTable { width: 100%; border-collapse: collapse; }
#dirTable th, #dirTable td {
    border-bottom: 1px solid var(--line);
    text-align: left;
    padding: 8px 6px;
    font-size: 0.94rem;
}
#progressBar { width: 100%; height: 14px; }
#log {
    margin-top: 8px;
    min-height: 80px;
    max-height: 200px;
    overflow-y: auto;
    border: 1px dashed var(--line);
    border-radius: 8px;
    background: #fafcfd;
    padding: 8px;
    white-space: pre-wrap;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 12px;
}
.error { color: var(--danger); }
.warn { color: #9a6700; }
</style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>VibePushr</h1>
        <div class="small">ROOT_DIR: <?= h(ROOT_DIR) ?></div>
        <?php if ($hashPlaceholder): ?>
        <div class="small warn">`APP_PASSWORD_HASH` がプレースホルダーです。実運用前に差し替えてください。</div>
        <?php endif; ?>
    </div>

    <?php if (!is_authed()): ?>
    <div class="card">
        <h2>ログイン</h2>
        <form id="loginForm" method="post">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <div class="row">
                <input type="password" name="password" placeholder="password" required>
                <button class="primary" type="submit">Login</button>
            </div>
            <div id="loginError" class="small error"></div>
        </form>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="row" style="justify-content:space-between;">
            <h2>フォルダー一覧</h2>
            <button id="refreshDirs" type="button">再読み込み</button>
        </div>
        <table id="dirTable">
            <thead>
                <tr><th>Path</th><th>Files</th><th>Bytes</th></tr>
            </thead>
            <tbody id="dirBody">
            <?php if (empty($initialDirs)): ?>
                <tr><td colspan="3">フォルダーなし</td></tr>
            <?php else: ?>
                <?php foreach ($initialDirs as $row): ?>
                <tr>
                    <td><?= h((string) ($row['path'] ?? '')) ?></td>
                    <td><?= (int) ($row['file_count'] ?? 0) ?></td>
                    <td><?= (int) ($row['total_bytes'] ?? 0) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>フォルダー同期</h2>
        <div class="row">
            <input type="file" id="folderInput" webkitdirectory directory multiple>
            <button class="primary" id="startSync" type="button">同期開始</button>
            <button id="testSync" type="button">テスト実行(書き込みなし)</button>
            <button id="retryFailed" type="button" disabled>失敗のみ再送</button>
        </div>
        <div class="small">同時送信数: 3 / 最大リトライ: 3 / 同期開始はskip判定あり / テスト実行は書き込みなし</div>
        <div style="margin-top:10px;"><progress id="progressBar" value="0" max="1"></progress></div>
        <div class="small" id="progressText">待機中</div>
        <div id="log"></div>
    </div>

    <div class="card">
        <form id="logoutForm" method="post" class="row">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <button type="submit">Logout</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
(() => {
    const csrfToken = <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const isAuthed = <?= is_authed() ? 'true' : 'false' ?>;

    async function api(action, options = {}) {
        const method = options.method || 'GET';
        const headers = Object.assign({}, options.headers || {});

        if (method !== 'GET') {
            headers['X-CSRF-Token'] = csrfToken;
        }

        const query = options.query ? '&' + options.query : '';
        const response = await fetch(`?action=${encodeURIComponent(action)}${query}`, {
            method,
            headers,
            body: options.body || null
        });

        let data = {};
        try {
            data = await response.json();
        } catch (_) {
            throw new Error(`http_${response.status}`);
        }

        if (!response.ok || data.ok === false) {
            throw new Error(data.error || data.message || `http_${response.status}`);
        }

        return data;
    }

    if (!isAuthed) {
        const loginForm = document.getElementById('loginForm');
        const loginError = document.getElementById('loginError');

        loginForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            loginError.textContent = '';
            try {
                await api('login', { method: 'POST', body: new FormData(loginForm) });
                location.reload();
            } catch (error) {
                loginError.textContent = `ログイン失敗: ${error.message}`;
            }
        });

        return;
    }

    const TEXT_EXT = new Set(['js', 'css', 'html', 'json', 'txt', 'md', 'svg', 'php', 'xml', 'yml', 'yaml', 'ts', 'tsx', 'jsx']);

    const dirBody = document.getElementById('dirBody');
    const refreshDirs = document.getElementById('refreshDirs');
    const folderInput = document.getElementById('folderInput');
    const startSyncBtn = document.getElementById('startSync');
    const testSyncBtn = document.getElementById('testSync');
    const retryFailedBtn = document.getElementById('retryFailed');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const logEl = document.getElementById('log');
    const logoutForm = document.getElementById('logoutForm');

    let failedFiles = [];

    function escapeHtml(value) {
        return String(value).replace(/[&<>\"']/g, (ch) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[ch]));
    }

    function appendLog(line, isError = false) {
        const ts = new Date().toLocaleTimeString();
        const text = `[${ts}] ${line}`;
        logEl.textContent += (logEl.textContent ? '\n' : '') + text;
        logEl.scrollTop = logEl.scrollHeight;
        if (isError) {
            logEl.classList.add('error');
        }
    }

    function setProgress(done, total, currentPath, fail) {
        progressBar.max = Math.max(total, 1);
        progressBar.value = done;
        progressText.textContent = `完了 ${done}/${total} | 失敗 ${fail} | 処理中: ${currentPath || '-'}`;
    }

    function extOf(path) {
        const idx = path.lastIndexOf('.');
        return idx >= 0 ? path.slice(idx + 1).toLowerCase() : '';
    }

    async function gzipIfUseful(file, relpath) {
        const source = await file.arrayBuffer();
        const ext = extOf(relpath);

        if (!TEXT_EXT.has(ext) || source.byteLength < 8192 || !('CompressionStream' in window)) {
            return { encoding: 'identity', bytes: source };
        }

        try {
            const stream = new Blob([source]).stream().pipeThrough(new CompressionStream('gzip'));
            const compressed = await new Response(stream).arrayBuffer();
            if (compressed.byteLength < source.byteLength * 0.9) {
                return { encoding: 'gzip', bytes: compressed };
            }
        } catch (_) {
        }

        return { encoding: 'identity', bytes: source };
    }

    async function sendOne(jobId, file, relpath, options = {}, attempt = 1) {
        const dryRun = options.dryRun === true;
        const force = options.force === true;
        const packed = await gzipIfUseful(file, relpath);
        const query = new URLSearchParams({
            job_id: jobId,
            relpath,
            size: String(file.size),
            mtime: String(Math.floor(file.lastModified / 1000)),
            dry_run: dryRun ? '1' : '0',
            force: force ? '1' : '0'
        }).toString();

        try {
            return await api('sync_put', {
                method: 'POST',
                query,
                headers: { 'X-Vibe-Encoding': packed.encoding },
                body: packed.bytes
            });
        } catch (error) {
            if (attempt < 3) {
                appendLog(`retry ${attempt} -> ${attempt + 1}: ${relpath}`);
                return sendOne(jobId, file, relpath, options, attempt + 1);
            }
            throw error;
        }
    }

    async function runSync(files, options = {}) {
        const dryRun = options.dryRun === true;
        const force = options.force === true;
        const trackFailed = options.trackFailed !== false;
        const modeLabel = dryRun ? 'dry-run' : 'sync';

        if (!files.length) {
            appendLog('ファイルが選択されていません', true);
            return;
        }

        startSyncBtn.disabled = true;
        testSyncBtn.disabled = true;
        retryFailedBtn.disabled = true;
        logEl.classList.remove('error');

        if (trackFailed) {
            failedFiles = [];
        }
        setProgress(0, files.length, '', 0);

        const fd = new FormData();
        fd.set('total_files', String(files.length));
        const init = await api('sync_init', { method: 'POST', body: fd });
        const jobId = init.job_id;
        appendLog(`${modeLabel} started: ${jobId}`);

        const concurrency = 3;
        let cursor = 0;
        let done = 0;
        let fail = 0;

        async function worker() {
            while (cursor < files.length) {
                const index = cursor++;
                const file = files[index];
                const relpath = file.webkitRelativePath || file.name;

                setProgress(done, files.length, relpath, fail);

                try {
                    const result = await sendOne(jobId, file, relpath, { dryRun, force });
                    appendLog(`${result.result}: ${relpath}`);
                } catch (error) {
                    fail++;
                    if (trackFailed) {
                        failedFiles.push(file);
                    }
                    appendLog(`fail: ${relpath} (${error.message})`, true);
                } finally {
                    done++;
                    setProgress(done, files.length, relpath, fail);
                }
            }
        }

        await Promise.all(Array.from({ length: Math.min(concurrency, files.length) }, worker));

        const finishFd = new FormData();
        finishFd.set('job_id', jobId);
        const finished = await api('sync_finish', { method: 'POST', body: finishFd });
        const s = finished.summary;

        appendLog(`${modeLabel} finished: ok=${s.ok}, skip=${s.skip}, fail=${s.fail}`);
        setProgress(s.done, s.total, s.current_path || '', s.fail);

        retryFailedBtn.disabled = dryRun || failedFiles.length === 0;
        startSyncBtn.disabled = false;
        testSyncBtn.disabled = false;
    }

    async function loadDirs() {
        dirBody.innerHTML = '<tr><td colspan="3">loading...</td></tr>';

        try {
            const res = await api('list_dirs');
            if (!res.dirs || res.dirs.length === 0) {
                dirBody.innerHTML = '<tr><td colspan="3">フォルダーなし</td></tr>';
                return;
            }

            dirBody.innerHTML = res.dirs.map((row) => {
                return `<tr><td>${escapeHtml(row.path)}</td><td>${row.file_count}</td><td>${row.total_bytes}</td></tr>`;
            }).join('');
        } catch (error) {
            dirBody.innerHTML = `<tr><td colspan="3">読み込み失敗: ${escapeHtml(error.message)}</td></tr>`;
        }
    }

    refreshDirs.addEventListener('click', () => {
        loadDirs();
    });

    startSyncBtn.addEventListener('click', async () => {
        await runSync(Array.from(folderInput.files || []), { dryRun: false, force: false, trackFailed: true });
    });

    testSyncBtn.addEventListener('click', async () => {
        await runSync(Array.from(folderInput.files || []), { dryRun: true, force: false, trackFailed: false });
    });

    retryFailedBtn.addEventListener('click', async () => {
        if (failedFiles.length === 0) return;
        const retry = failedFiles.slice();
        failedFiles = [];
        await runSync(retry, { dryRun: false, force: false, trackFailed: true });
    });

    logoutForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
            await api('logout', { method: 'POST', body: new FormData(logoutForm) });
        } finally {
            location.reload();
        }
    });

    loadDirs();
})();
</script>
</body>
</html>
