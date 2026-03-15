<?php

declare(strict_types=1);

function request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        return true;
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = strtolower(trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
        if ($proto === 'https') {
            return true;
        }
    }

    return false;
}

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (request_is_https()) {
    ini_set('session.cookie_secure', '1');
}

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => request_is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

const ROOT_DIR = __DIR__;
const JOB_DIR_NAME = '.vp_jobs';
const LOG_FILE_NAME = 'vp.log';
const AUTH_FILE_NAME = '.vp_auth.php';
const LOGIN_GUARD_FILE_NAME = '.vp_login_guard.json';
const LOGIN_MAX_IDLE_DAYS = 30;
const LOGIN_MAX_FAILED_ATTEMPTS = 100;
const MAX_ERROR_KEEP = 10;

function detect_language(): string
{
    $explicit = strtolower((string) ($_GET['lang'] ?? ''));
    if ($explicit === 'ja' || $explicit === 'en') {
        return $explicit;
    }

    $accept = (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    if ($accept !== '') {
        $candidates = [];
        foreach (explode(',', $accept) as $index => $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $langPart = $part;
            $q = 1.0;
            if (strpos($part, ';') !== false) {
                [$langPart, $params] = array_map('trim', explode(';', $part, 2));
                if (preg_match('/q=([0-9.]+)/i', $params, $m)) {
                    $q = (float) $m[1];
                }
            }
            $primary = strtolower(explode('-', $langPart)[0]);
            if ($primary === 'ja' || $primary === 'en') {
                $candidates[] = ['lang' => $primary, 'q' => $q, 'i' => $index];
            }
        }
        if (!empty($candidates)) {
            usort($candidates, static function (array $a, array $b): int {
                if ($a['q'] === $b['q']) {
                    return $a['i'] <=> $b['i'];
                }
                return ($a['q'] > $b['q']) ? -1 : 1;
            });
            return (string) $candidates[0]['lang'];
        }
    }

    return 'en';
}

function app_lang(): string
{
    static $lang = null;
    if ($lang === null) {
        $lang = detect_language();
    }
    return $lang;
}

function t(string $key): string
{
    static $messages = [
        'en' => [
            'current_location' => 'Current location',
            'logout' => 'Logout',
            'setup_warning' => 'Please set an admin password in initial setup.',
            'setup_title' => 'Initial Setup',
            'setup_desc' => 'Set an admin password (8 characters or more).',
            'setup_failed' => 'Setup failed',
            'login_title' => 'Login',
            'login_failed' => 'Login failed',
            'sync_title' => 'Folder Sync',
            'start_sync' => 'Start Sync',
            'test_sync' => 'Test Run (No Write)',
            'retry_failed' => 'Retry Failed Only',
            'sync_meta' => 'Concurrency: 10 / Max retry: none / Start Sync uses skip checks / Test Run does not write',
            'progress_idle' => 'Idle',
            'progress_fmt' => 'Done %d/%d | Fail %d | Processing: %s',
            'dirs_title' => 'Folder List',
            'dirs_reload' => 'Reload',
            'dirs_empty' => 'No folders',
            'files_not_selected' => 'No files selected',
            'upload_blocked_hint' => 'Some files could not be uploaded. If failures continue, try re-selecting the folder.',
            'drop_anywhere_hint' => 'Drag & drop files/folders anywhere on this page.',
            'drop_overlay' => 'Drop to select files for sync',
            'selection_status' => 'Selected files: %d',
            'drop_invalid_selection' => 'Drop exactly one folder.',
            'drop_selected' => 'Selected by drag & drop: %d file(s)',
            'load_failed' => 'Load failed',
            'retry_unavailable' => 'cannot retry because it is not found in the current selection',
            'login_locked_idle' => 'Login is locked due to long inactivity. Recover by deleting public_html/.vp_login_guard.json via FTP.',
            'login_locked_failures' => 'Login is locked because the failed-attempt limit was reached. Recover by deleting public_html/.vp_login_guard.json via FTP.',
            'login_locked' => 'Login is locked. Recover by deleting public_html/.vp_login_guard.json via FTP.',
            'unicode_filename_mismatch' => 'On some servers, non-ASCII filenames may cause issues.',
        ],
        'ja' => [
            'current_location' => '現在の場所',
            'logout' => 'Logout',
            'setup_warning' => '初回セットアップで管理パスワードを設定してください。',
            'setup_title' => '初回セットアップ',
            'setup_desc' => '管理パスワードを設定してください（8文字以上）',
            'setup_failed' => 'セットアップ失敗',
            'login_title' => 'ログイン',
            'login_failed' => 'ログイン失敗',
            'sync_title' => 'フォルダー同期',
            'start_sync' => '同期開始',
            'test_sync' => 'テスト実行(書き込みなし)',
            'retry_failed' => '失敗のみ再送',
            'sync_meta' => '同時送信数: 10 / 最大リトライ: なし / 同期開始はskip判定あり / テスト実行は書き込みなし',
            'progress_idle' => '待機中',
            'progress_fmt' => '完了 %d/%d | 失敗 %d | 処理中: %s',
            'dirs_title' => 'フォルダー一覧',
            'dirs_reload' => '再読み込み',
            'dirs_empty' => 'フォルダーなし',
            'files_not_selected' => 'ファイルが選択されていません',
            'upload_blocked_hint' => 'アップロードできないファイルがありました。繰り返し失敗する場合は、フォルダーを選択し直してみてください。',
            'drop_anywhere_hint' => 'このページ全体にファイル/フォルダーをドラッグ&ドロップできます。',
            'drop_overlay' => 'ここにドロップして同期対象を選択',
            'selection_status' => '選択中ファイル: %d',
            'drop_invalid_selection' => 'フォルダー1つだけドロップしてください。',
            'drop_selected' => 'ドラッグ&ドロップで選択: %d ファイル',
            'load_failed' => '読み込み失敗',
            'retry_unavailable' => '現在の選択に見つからないため再送不可',
            'login_locked_idle' => '長期間未使用のためログインがロックされています。FTP等で public_html/.vp_login_guard.json を削除して復旧してください。',
            'login_locked_failures' => 'ログイン失敗回数の上限に達したためロックされています。FTP等で public_html/.vp_login_guard.json を削除して復旧してください。',
            'login_locked' => 'ログインがロックされています。FTP等で public_html/.vp_login_guard.json を削除して復旧してください。',
            'unicode_filename_mismatch' => '一部のサーバーでは日本語ファイル名が問題になることがあります。',
        ],
    ];
    $lang = app_lang();
    if (isset($messages[$lang][$key])) {
        return $messages[$lang][$key];
    }
    if (isset($messages['en'][$key])) {
        return $messages['en'][$key];
    }
    return $key;
}

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

function auth_file_path(): string
{
    return ROOT_DIR . DIRECTORY_SEPARATOR . AUTH_FILE_NAME;
}

function login_guard_path(): string
{
    return ROOT_DIR . DIRECTORY_SEPARATOR . LOGIN_GUARD_FILE_NAME;
}

function should_block_for_idle(?int $lastLoginAtTs, int $nowTs, int $limitDays): bool
{
    if ($lastLoginAtTs === null || $limitDays <= 0) {
        return false;
    }

    return ($nowTs - $lastLoginAtTs) >= ($limitDays * 86400);
}

function should_block_for_failed_attempts(int $failedCount, int $maxAttempts): bool
{
    if ($maxAttempts <= 0) {
        return false;
    }

    return $failedCount >= $maxAttempts;
}

/**
 * @return array{allowed:bool,error?:string,blocked_now?:bool}
 */
function evaluate_login_guard_after_password_verify(): array
{
    $path = login_guard_path();
    $fp = @fopen($path, 'c+b');
    if ($fp === false) {
        return ['allowed' => false, 'error' => 'guard_unavailable'];
    }

    if (!@flock($fp, LOCK_EX)) {
        @fclose($fp);
        return ['allowed' => false, 'error' => 'guard_unavailable'];
    }

    $raw = stream_get_contents($fp);
    $state = [];
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $state = $decoded;
        }
    }

    $nowIso = now_iso();
    $nowTs = time();
    $lastLoginTs = null;
    if (isset($state['last_login_at']) && is_string($state['last_login_at'])) {
        $parsed = strtotime($state['last_login_at']);
        if (is_int($parsed) && $parsed > 0) {
            $lastLoginTs = $parsed;
        }
    }

    $isBlocked = !empty($state['blocked']);
    $blockReason = (string) ($state['block_reason'] ?? '');
    $blockedNow = false;
    if (!$isBlocked && should_block_for_idle($lastLoginTs, $nowTs, LOGIN_MAX_IDLE_DAYS)) {
        $isBlocked = true;
        $blockedNow = true;
        $state['blocked_at'] = $nowIso;
        $state['block_reason'] = 'idle_exceeded';
        $blockReason = 'idle_exceeded';
    }

    $state['blocked'] = $isBlocked;
    $state['last_login_at'] = $nowIso;
    if (!$isBlocked) {
        $state['failed_count'] = 0;
    }

    $encoded = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $writeOk = is_string($encoded) && @rewind($fp) && @ftruncate($fp, 0) && @fwrite($fp, $encoded) !== false && @fflush($fp);
    @flock($fp, LOCK_UN);
    @fclose($fp);

    if (!$writeOk) {
        return ['allowed' => false, 'error' => 'guard_unavailable'];
    }

    if ($isBlocked) {
        $error = 'login_blocked';
        if ($blockedNow && $blockReason === 'idle_exceeded') {
            $error = 'login_blocked_idle';
        } elseif ($blockReason === 'too_many_failures') {
            $error = 'login_blocked_failures';
        }
        return [
            'allowed' => false,
            'error' => $error,
            'blocked_now' => $blockedNow,
        ];
    }

    return ['allowed' => true];
}

/**
 * @return array{allowed:bool,error:string,blocked_now?:bool,remaining_attempts?:int}
 */
function evaluate_login_guard_after_password_failure(): array
{
    $path = login_guard_path();
    $fp = @fopen($path, 'c+b');
    if ($fp === false) {
        return ['allowed' => false, 'error' => 'guard_unavailable'];
    }
    if (!@flock($fp, LOCK_EX)) {
        @fclose($fp);
        return ['allowed' => false, 'error' => 'guard_unavailable'];
    }

    $raw = stream_get_contents($fp);
    $state = [];
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $state = $decoded;
        }
    }

    $nowIso = now_iso();
    $isBlocked = !empty($state['blocked']);
    $blockReason = (string) ($state['block_reason'] ?? '');
    $blockedNow = false;
    $error = 'invalid_password';
    $remainingAttempts = null;

    if ($isBlocked) {
        $error = ($blockReason === 'too_many_failures') ? 'login_blocked_failures' : 'login_blocked';
    } else {
        $failedCount = max(0, (int) ($state['failed_count'] ?? 0)) + 1;
        $state['failed_count'] = $failedCount;
        $state['last_failed_at'] = $nowIso;
        $remainingAttempts = max(0, LOGIN_MAX_FAILED_ATTEMPTS - $failedCount);

        if (should_block_for_failed_attempts($failedCount, LOGIN_MAX_FAILED_ATTEMPTS)) {
            $state['blocked'] = true;
            $state['blocked_at'] = $nowIso;
            $state['block_reason'] = 'too_many_failures';
            $isBlocked = true;
            $blockedNow = true;
            $error = 'login_blocked_failures';
        }
    }

    $encoded = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $writeOk = is_string($encoded) && @rewind($fp) && @ftruncate($fp, 0) && @fwrite($fp, $encoded) !== false && @fflush($fp);
    @flock($fp, LOCK_UN);
    @fclose($fp);

    if (!$writeOk) {
        return ['allowed' => false, 'error' => 'guard_unavailable'];
    }

    return [
        'allowed' => false,
        'error' => $error,
        'blocked_now' => $blockedNow,
        'remaining_attempts' => $remainingAttempts,
    ];
}

function load_password_hash_from_file(): ?string
{
    $path = auth_file_path();
    if (!is_file($path)) {
        return null;
    }

    $data = @require $path;
    if (!is_array($data)) {
        return null;
    }

    $hash = $data['hash'] ?? null;
    return is_string($hash) && $hash !== '' ? $hash : null;
}

function save_password_hash_file(string $hash): bool
{
    $path = auth_file_path();
    $content = "<?php\nreturn ['hash' => " . var_export($hash, true) . "];\n";
    return @file_put_contents($path, $content, LOCK_EX) !== false;
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

function to_relpath_from_full(string $fullPath): ?string
{
    $root = realpath(ROOT_DIR);
    if ($root === false) {
        return null;
    }

    $rootNorm = rtrim(str_replace('\\', '/', $root), '/');
    $fullNorm = str_replace('\\', '/', $fullPath);
    if ($fullNorm === $rootNorm) {
        return null;
    }
    if (strpos($fullNorm, $rootNorm . '/') !== 0) {
        return null;
    }

    $rel = substr($fullNorm, strlen($rootNorm) + 1);
    return normalize_relpath($rel);
}

function has_unicode_filename_mismatch(string $expectedRelpath, string $actualFullPath): bool
{
    $expected = normalize_relpath($expectedRelpath);
    if ($expected === null) {
        return true;
    }

    $actual = to_relpath_from_full($actualFullPath);
    if ($actual === null) {
        return true;
    }

    return $expected !== $actual;
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
    if ($parentReal === false) {
        // In dry-run, parent may not exist yet; keep it safe by checking
        // the normalized candidate path stays under ROOT_DIR.
        if (!$ensure_parent) {
            if (!is_within_root($full)) {
                return null;
            }
            return $full;
        }
        return null;
    }
    if (!is_within_root($parentReal)) {
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

function write_atomic(string $targetPath, string $data, ?int $mtime = null): string
{
    $fp = @fopen($targetPath, 'c+b');
    if ($fp === false) {
        return 'write_failed';
    }

    if (!@flock($fp, LOCK_EX | LOCK_NB)) {
        @fclose($fp);
        return 'locked_busy';
    }

    $len = strlen($data);
    $offset = 0;
    $ok = @rewind($fp);

    while ($ok && $offset < $len) {
        $chunk = @fwrite($fp, substr($data, $offset));
        if (!is_int($chunk) || $chunk <= 0) {
            $ok = false;
            break;
        }
        $offset += $chunk;
    }

    if ($ok) {
        $ok = ($offset === $len) && @ftruncate($fp, $len) && @fflush($fp);
    }

    @flock($fp, LOCK_UN);
    @fclose($fp);

    if (!$ok) {
        return 'write_failed';
    }

    if ($mtime !== null && $mtime > 0) {
        @touch($targetPath, $mtime);
    }

    return 'ok';
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

$effectiveHash = load_password_hash_from_file() ?? '';
$setupRequired = $effectiveHash === '';
$action = (string) ($_REQUEST['action'] ?? '');

if ($action !== '') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
    }

    if ($action !== 'login' && $action !== 'logout' && $action !== 'setup_auth') {
        require_auth();
    }

    if ($action === 'setup_auth' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$setupRequired) {
            json_response(['ok' => false, 'error' => 'already_configured'], 409);
        }

        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
        if ($password === '' || strlen($password) < 8) {
            json_response(['ok' => false, 'error' => 'password_too_short'], 400);
        }
        if ($password !== $passwordConfirm) {
            json_response(['ok' => false, 'error' => 'password_mismatch'], 400);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash) || $hash === '') {
            json_response(['ok' => false, 'error' => 'hash_failed'], 500);
        }
        if (!save_password_hash_file($hash)) {
            json_response(['ok' => false, 'error' => 'save_failed'], 500);
        }

        session_regenerate_id(true);
        $_SESSION['authed'] = true;
        ensure_csrf_token();
        log_audit('setup_completed');
        json_response(['ok' => true]);
    }

    if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($setupRequired) {
            json_response(['ok' => false, 'error' => 'setup_required'], 400);
        }

        $password = (string) ($_POST['password'] ?? '');
        if (password_verify($password, $effectiveHash)) {
            $guard = evaluate_login_guard_after_password_verify();
            if (empty($guard['allowed'])) {
                if (($guard['error'] ?? '') === 'login_blocked_idle') {
                    log_audit('login_blocked_idle');
                } elseif (($guard['error'] ?? '') === 'login_blocked_failures') {
                    log_audit('login_blocked_failures');
                } elseif (($guard['error'] ?? '') === 'login_blocked') {
                    log_audit('login_blocked_persisted');
                } else {
                    log_audit('login_guard_unavailable');
                }
                $guardError = (string) ($guard['error'] ?? 'login_blocked');
                if ($guardError === 'login_blocked_idle') {
                    json_response(['ok' => false, 'error' => t('login_locked_idle')], 403);
                }
                if ($guardError === 'login_blocked_failures') {
                    json_response(['ok' => false, 'error' => t('login_locked_failures')], 403);
                }
                if ($guardError === 'login_blocked') {
                    json_response(['ok' => false, 'error' => t('login_locked')], 403);
                }
                json_response(['ok' => false, 'error' => $guardError], 403);
            }

            session_regenerate_id(true);
            $_SESSION['authed'] = true;
            ensure_csrf_token();
            json_response(['ok' => true]);
        }

        $guardFailure = evaluate_login_guard_after_password_failure();
        $error = (string) ($guardFailure['error'] ?? 'invalid_password');
        if ($error === 'login_blocked_failures') {
            if (!empty($guardFailure['blocked_now'])) {
                log_audit('login_blocked_failures_now');
            } else {
                log_audit('login_blocked_failures_persisted');
            }
            json_response(['ok' => false, 'error' => t('login_locked_failures')], 403);
        }
        if ($error === 'login_blocked') {
            log_audit('login_blocked_persisted');
            json_response(['ok' => false, 'error' => t('login_locked')], 403);
        }
        if ($error === 'guard_unavailable') {
            log_audit('login_guard_unavailable');
            json_response(['ok' => false, 'error' => $error], 503);
        }

        log_audit('login_failed');
        $remainingAttempts = isset($guardFailure['remaining_attempts']) ? (int) $guardFailure['remaining_attempts'] : null;
        if ($remainingAttempts !== null && $remainingAttempts >= 0) {
            json_response(['ok' => false, 'error' => "invalid_password (remaining: {$remainingAttempts})"], 401);
        }
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

        $mtime = isset($_REQUEST['mtime']) ? (int) $_REQUEST['mtime'] : null;
        $size = isset($_REQUEST['size']) ? (int) $_REQUEST['size'] : null;
        $dryRun = (string) ($_REQUEST['dry_run'] ?? '0') === '1';
        $forceOverwrite = (string) ($_REQUEST['force'] ?? '0') === '1';
        $target = resolve_safe_path($relpath, !$dryRun);
        if ($target === null) {
            json_response(['ok' => false, 'error' => 'unsafe_path'], 400);
        }

        $raw = file_get_contents('php://input');
        if ($raw === false) {
            $raw = '';
        }
        if ($raw === '' && $size !== null && $size > 0) {
            json_response(['ok' => false, 'error' => 'empty_body'], 400);
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
            $writeStatus = write_atomic($target, $content, $mtime);
            if ($writeStatus !== 'ok') {
                $result = 'fail';
                $message = $writeStatus;
            } else {
                $writtenReal = realpath($target);
                if (!is_string($writtenReal) || has_unicode_filename_mismatch($relpath, $writtenReal)) {
                    $result = 'fail';
                    $message = 'unicode_filename_mismatch';
                }
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
            'original_relpath' => $relpath,
            'resolved_target' => $target,
            'result' => $result,
            'message' => $message,
            'dry_run' => $dryRun,
            'force' => $forceOverwrite,
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? '-',
        ]);

        json_response([
            'ok' => $result !== 'fail',
            'status' => $result,
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
$initialDirs = is_authed() ? scan_dirs() : [];
$lang = app_lang();
$uiText = [
    'setup_failed' => t('setup_failed'),
    'login_failed' => t('login_failed'),
    'progress_fmt' => t('progress_fmt'),
    'files_not_selected' => t('files_not_selected'),
    'upload_blocked_hint' => t('upload_blocked_hint'),
    'selection_status' => t('selection_status'),
    'drop_invalid_selection' => t('drop_invalid_selection'),
    'drop_selected' => t('drop_selected'),
    'dirs_empty' => t('dirs_empty'),
    'load_failed' => t('load_failed'),
    'retry_unavailable' => t('retry_unavailable'),
    'unicode_filename_mismatch' => t('unicode_filename_mismatch'),
];
$requestPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$scriptBaseUrl = rtrim(str_replace('\\', '/', dirname($requestPath !== '' ? $requestPath : '/')), '/');
?>
<!doctype html>
<html lang="<?= h($lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VibePushr</title>
<style>
:root {
    --bg: #f6f8fa;
    --card: #ffffff;
    --line: #d0d7de;
    --line-strong: #8c959f;
    --text: #24292f;
    --muted: #57606a;
    --accent: #2da44e;
    --accent-hover: #2c974b;
    --danger: #cf222e;
}
* { box-sizing: border-box; }
body {
    margin: 0;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Noto Sans JP", "Hiragino Kaku Gothic ProN", Meiryo, sans-serif;
    color: var(--text);
    background: var(--bg);
}
.container {
    max-width: 980px;
    margin: 22px auto;
    padding: 0 12px 36px;
}
.card {
    background: var(--card);
    border: 1px solid var(--line);
    border-radius: 6px;
    padding: 14px;
    margin-bottom: 12px;
    box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
}
h1 { margin: 0 0 6px; font-size: 1.6rem; }
h2 { margin: 0 0 10px; font-size: 1.1rem; }
.small { font-size: 0.9rem; color: var(--muted); }
.row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
input, button {
    border: 1px solid var(--line-strong);
    border-radius: 8px;
    font-size: 14px;
    padding: 8px 10px;
}
button { background: #f6f8fa; cursor: pointer; }
button.primary { background: var(--accent); border-color: rgba(27, 31, 36, 0.15); color: #fff; }
button.primary:hover { background: var(--accent-hover); }
button:disabled { opacity: 0.6; cursor: not-allowed; }
#dirTable { width: 100%; border-collapse: collapse; }
#dirTable th, #dirTable td {
    border-bottom: 1px solid var(--line);
    text-align: left;
    padding: 8px 6px;
    font-size: 0.94rem;
}
#dirTable th.num, #dirTable td.num {
    text-align: right;
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
.log-line { margin: 0 0 2px; }
.log-error { color: var(--danger); }
.log-sync-start {
    font-weight: 700;
    color: #0b6b57;
}
.log-dryrun-start {
    font-weight: 700;
    color: #0b4f8c;
}
.error { color: var(--danger); }
.warn { color: #9a6700; }
.drop-hint {
    margin-top: 8px;
    border: 1px dashed var(--line-strong);
    border-radius: 8px;
    background: #f8fafc;
    padding: 8px 10px;
}
.drop-overlay {
    position: fixed;
    inset: 0;
    z-index: 999;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(9, 105, 218, 0.16);
    border: 3px dashed rgba(9, 105, 218, 0.45);
    color: #0550ae;
    font-weight: 700;
    font-size: 1.2rem;
    pointer-events: none;
}
.drop-overlay.active {
    display: flex;
}
</style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="small"><?= h(t('current_location')) ?>: <?= h(rtrim(str_replace('\\', '/', ROOT_DIR), '/') . '/') ?></div>
        <?php if (is_authed()): ?>
        <form id="logoutForm" method="post" class="row" style="margin-top:8px;">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <button type="submit"><?= h(t('logout')) ?></button>
        </form>
        <?php endif; ?>
        <?php if ($setupRequired): ?>
        <div class="small warn"><?= h(t('setup_warning')) ?></div>
        <?php endif; ?>
    </div>

    <?php if (!is_authed()): ?>
    <div class="card">
        <?php if ($setupRequired): ?>
        <h2><?= h(t('setup_title')) ?></h2>
        <div class="small"><?= h(t('setup_desc')) ?></div>
        <form id="setupForm" method="post">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <div class="row">
                <input type="password" name="password" placeholder="new password" required minlength="8">
                <input type="password" name="password_confirm" placeholder="confirm password" required minlength="8">
                <button class="primary" type="submit">Setup</button>
            </div>
            <div id="setupError" class="small error"></div>
        </form>
        <?php else: ?>
        <h2><?= h(t('login_title')) ?></h2>
        <form id="loginForm" method="post">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <div class="row">
                <input type="password" name="password" placeholder="password" required>
                <button class="primary" type="submit">Login</button>
            </div>
            <div id="loginError" class="small error"></div>
        </form>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div id="dropOverlay" class="drop-overlay"><?= h(t('drop_overlay')) ?></div>
    <div class="card">
        <h2><?= h(t('sync_title')) ?></h2>
        <div class="row">
            <input type="file" id="folderInput" webkitdirectory directory multiple>
            <button class="primary" id="startSync" type="button"><?= h(t('start_sync')) ?></button>
            <button id="testSync" type="button"><?= h(t('test_sync')) ?></button>
            <button id="retryFailed" type="button" disabled><?= h(t('retry_failed')) ?></button>
        </div>
        <div class="small drop-hint" id="dropHint"><?= h(t('drop_anywhere_hint')) ?></div>
        <div class="small" id="selectionStatus"><?= h(str_replace('%d', '0', t('selection_status'))) ?></div>
        <div class="small"><?= h(t('sync_meta')) ?></div>
        <div style="margin-top:10px;"><progress id="progressBar" value="0" max="1"></progress></div>
        <div class="small" id="progressText"><?= h(t('progress_idle')) ?></div>
        <div id="log"></div>
    </div>

    <div class="card">
        <div class="row" style="justify-content:space-between;">
            <h2><?= h(t('dirs_title')) ?></h2>
            <button id="refreshDirs" type="button"><?= h(t('dirs_reload')) ?></button>
        </div>
        <table id="dirTable">
            <thead>
                <tr><th>Path</th><th class="num">Files</th><th class="num">Bytes</th></tr>
            </thead>
            <tbody id="dirBody">
            <?php if (empty($initialDirs)): ?>
                <tr><td colspan="3"><?= h(t('dirs_empty')) ?></td></tr>
            <?php else: ?>
                <?php foreach ($initialDirs as $row): ?>
                <?php
                    $path = (string) ($row['path'] ?? '');
                    $segments = array_filter(explode('/', $path), static fn($segment) => $segment !== '');
                    $encodedPath = implode('/', array_map('rawurlencode', $segments));
                    $dirHref = ($scriptBaseUrl !== '' ? $scriptBaseUrl : '') . '/' . $encodedPath . '/';
                ?>
                <tr>
                    <td><a href="<?= h($dirHref) ?>" target="_blank" rel="noopener noreferrer"><?= h(rtrim($path, '/') . '/') ?></a></td>
                    <td class="num"><?= (int) ($row['file_count'] ?? 0) ?></td>
                    <td class="num"><?= (int) ($row['total_bytes'] ?? 0) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php endif; ?>
</div>

<script>
(() => {
    const csrfToken = <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const isAuthed = <?= is_authed() ? 'true' : 'false' ?>;
    const scriptBaseUrl = <?= json_encode($scriptBaseUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const i18n = <?= json_encode($uiText, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

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
        const setupForm = document.getElementById('setupForm');
        const setupError = document.getElementById('setupError');
        if (setupForm) {
            setupForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (setupError) setupError.textContent = '';
                try {
                    await api('setup_auth', { method: 'POST', body: new FormData(setupForm) });
                    location.reload();
                } catch (error) {
                    if (setupError) setupError.textContent = `${i18n.setup_failed}: ${error.message}`;
                }
            });
            return;
        }

        const loginForm = document.getElementById('loginForm');
        const loginError = document.getElementById('loginError');
        if (!loginForm || !loginError) {
            return;
        }

        loginForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            loginError.textContent = '';
            try {
                await api('login', { method: 'POST', body: new FormData(loginForm) });
                location.reload();
            } catch (error) {
                loginError.textContent = `${i18n.login_failed}: ${error.message}`;
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
    const dropOverlay = document.getElementById('dropOverlay');
    const selectionStatus = document.getElementById('selectionStatus');

    let failedRelpaths = [];
    let hasClientReadErrorSinceSelection = false;
    let dragDepth = 0;
    let droppedFiles = [];
    const relpathByFile = new WeakMap();

    function escapeHtml(value) {
        return String(value).replace(/[&<>\"']/g, (ch) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[ch]));
    }

    function buildFolderHref(path) {
        const encoded = String(path || '')
            .split('/')
            .filter((segment) => segment !== '')
            .map((segment) => encodeURIComponent(segment))
            .join('/');
        return `${scriptBaseUrl}/${encoded}/`;
    }

    function appendLog(line, isError = false, emphasisClass = '') {
        const ts = new Date().toLocaleTimeString();
        const text = `[${ts}] ${line}`;
        const row = document.createElement('div');
        row.className = 'log-line';
        if (isError) {
            row.classList.add('log-error');
        }
        if (emphasisClass) {
            row.classList.add(emphasisClass);
        }
        row.textContent = text;
        logEl.appendChild(row);
        logEl.scrollTop = logEl.scrollHeight;
    }

    function localizeErrorMessage(message) {
        const key = String(message || '');
        if (Object.prototype.hasOwnProperty.call(i18n, key)) {
            return i18n[key];
        }
        return key;
    }

    function setProgress(done, total, currentPath, fail) {
        progressBar.max = Math.max(total, 1);
        progressBar.value = done;
        progressText.textContent = i18n.progress_fmt
            .replace('%d', String(done))
            .replace('%d', String(total))
            .replace('%d', String(fail))
            .replace('%s', currentPath || '-');
    }

    async function refreshSelectionIfNeeded() {
        return true;
    }

    function selectedFiles() {
        if (droppedFiles.length > 0) {
            return droppedFiles;
        }
        return Array.from(folderInput.files || []);
    }

    function relpathOf(file) {
        return relpathByFile.get(file) || file.webkitRelativePath || file.name;
    }

    function updateSelectionStatus() {
        if (!selectionStatus) {
            return;
        }
        selectionStatus.textContent = i18n.selection_status.replace('%d', String(selectedFiles().length));
    }

    function currentFileMap() {
        const map = new Map();
        for (const file of selectedFiles()) {
            const relpath = relpathOf(file);
            map.set(relpath, file);
        }
        return map;
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

    function isClientReadError(error) {
        const message = String(error && error.message ? error.message : error || '');
        return message.startsWith('file_read_failed:');
    }

    function sleep(ms) {
        return new Promise((resolve) => setTimeout(resolve, ms));
    }

    async function sendOne(jobId, file, relpath, options = {}) {
        const dryRun = options.dryRun === true;
        const force = options.force === true;
        let packed;
        try {
            packed = await gzipIfUseful(file, relpath);
        } catch (error) {
            const reason = error && error.message ? error.message : String(error || 'unknown');
            throw new Error(`file_read_failed:${reason}`);
        }
        const query = new URLSearchParams({
            job_id: jobId,
            relpath,
            size: String(file.size),
            mtime: String(Math.floor(file.lastModified / 1000)),
            dry_run: dryRun ? '1' : '0',
            force: force ? '1' : '0'
        }).toString();

        return await api('sync_put', {
            method: 'POST',
            query,
            headers: {
                'X-Vibe-Encoding': packed.encoding,
                'Content-Type': 'application/octet-stream'
            },
            body: packed.bytes
        });
    }

    async function runSync(files, options = {}) {
        const dryRun = options.dryRun === true;
        const force = options.force === true;
        const trackFailed = options.trackFailed !== false;
        const modeLabel = dryRun ? 'dry-run' : 'sync';
        const previousRetryDisabled = retryFailedBtn.disabled;
        const wasClientReadErrorLocked = hasClientReadErrorSinceSelection;
        let unresolvedClientReadError = false;

        if (!files.length) {
            appendLog(i18n.files_not_selected, true);
            return;
        }

        startSyncBtn.disabled = true;
        testSyncBtn.disabled = true;
        if (!dryRun) {
            retryFailedBtn.disabled = true;
        }
        logEl.classList.remove('error');

        if (trackFailed) {
            failedRelpaths = [];
        }
        setProgress(0, files.length, '', 0);

        try {
            const fd = new FormData();
            fd.set('total_files', String(files.length));
            const init = await api('sync_init', { method: 'POST', body: fd });
            const jobId = init.job_id;
            if (dryRun) {
                appendLog(`🧪 dry-run started: ${jobId}`, false, 'log-dryrun-start');
            } else {
                appendLog(`▶ sync started: ${jobId}`, false, 'log-sync-start');
            }

            const concurrency = 10;
            let cursor = 0;
            let done = 0;
            let fail = 0;

            async function worker() {
                while (cursor < files.length) {
                    const index = cursor++;
                    const file = files[index];
                    const relpath = relpathOf(file);

                    setProgress(done, files.length, relpath, fail);

                    try {
                        let activeFile = file;
                        let syncResult;
                        try {
                            syncResult = await sendOne(jobId, activeFile, relpath, { dryRun, force });
                        } catch (firstError) {
                            if (!isClientReadError(firstError)) {
                                throw firstError;
                            }

                            // A short retry helps recover from transient OS/file locks.
                            await sleep(50);
                            const latest = currentFileMap().get(relpath);
                            if (latest) {
                                activeFile = latest;
                            }
                            syncResult = await sendOne(jobId, activeFile, relpath, { dryRun, force });
                        }
                        appendLog(`${syncResult.status}: ${relpath}`);
                    } catch (error) {
                        if (isClientReadError(error)) {
                            unresolvedClientReadError = true;
                        }
                        fail++;
                        if (trackFailed && !failedRelpaths.includes(relpath)) {
                            failedRelpaths.push(relpath);
                        }
                        appendLog(`fail: ${relpath} (${localizeErrorMessage(error.message)})`, true);
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
            const displayedFail = Math.max(Number(s.fail || 0), fail);

            appendLog(`${modeLabel} finished: ok=${s.ok}, skip=${s.skip}, fail=${displayedFail}`);
            setProgress(s.done, s.total, s.current_path || '', displayedFail);
        } finally {
            hasClientReadErrorSinceSelection = unresolvedClientReadError;
            if (hasClientReadErrorSinceSelection) {
                if (!wasClientReadErrorLocked) {
                    appendLog(i18n.upload_blocked_hint, true);
                }
                startSyncBtn.disabled = false;
                retryFailedBtn.disabled = failedRelpaths.length === 0;
            } else {
                startSyncBtn.disabled = false;
                if (dryRun) {
                    retryFailedBtn.disabled = previousRetryDisabled;
                } else {
                    retryFailedBtn.disabled = failedRelpaths.length === 0;
                }
            }
            testSyncBtn.disabled = false;
        }
    }

    async function loadDirs() {
        dirBody.innerHTML = '<tr><td colspan="3">loading...</td></tr>';

        try {
            const res = await api('list_dirs');
            if (!res.dirs || res.dirs.length === 0) {
                dirBody.innerHTML = `<tr><td colspan="3">${escapeHtml(i18n.dirs_empty)}</td></tr>`;
                return;
            }

            dirBody.innerHTML = res.dirs.map((row) => {
                const safePath = escapeHtml(`${String(row.path || '').replace(/\/+$/, '')}/`);
                const safeHref = escapeHtml(buildFolderHref(row.path));
                return `<tr><td><a href="${safeHref}" target="_blank" rel="noopener noreferrer">${safePath}</a></td><td class="num">${row.file_count}</td><td class="num">${row.total_bytes}</td></tr>`;
            }).join('');
        } catch (error) {
            dirBody.innerHTML = `<tr><td colspan="3">${escapeHtml(i18n.load_failed)}: ${escapeHtml(error.message)}</td></tr>`;
        }
    }

    refreshDirs.addEventListener('click', () => {
        loadDirs();
    });

    folderInput.addEventListener('change', () => {
        droppedFiles = [];
        hasClientReadErrorSinceSelection = false;
        startSyncBtn.disabled = false;
        retryFailedBtn.disabled = failedRelpaths.length === 0;
        updateSelectionStatus();
    });

    function readEntries(reader) {
        return new Promise((resolve, reject) => {
            reader.readEntries(resolve, reject);
        });
    }

    function readFileFromEntry(entry) {
        return new Promise((resolve, reject) => {
            entry.file(resolve, reject);
        });
    }

    async function collectEntryFiles(entry, parentRelpath = '') {
        if (entry.isFile) {
            const file = await readFileFromEntry(entry);
            const relpath = `${parentRelpath}${entry.name}`;
            relpathByFile.set(file, relpath);
            return [file];
        }

        if (!entry.isDirectory) {
            return [];
        }

        const nextParent = `${parentRelpath}${entry.name}/`;
        const reader = entry.createReader();
        const files = [];

        for (;;) {
            const entries = await readEntries(reader);
            if (!entries.length) {
                break;
            }
            for (const child of entries) {
                const childFiles = await collectEntryFiles(child, nextParent);
                files.push(...childFiles);
            }
        }

        return files;
    }

    function resetDropState() {
        dragDepth = 0;
        if (dropOverlay) {
            dropOverlay.classList.remove('active');
        }
    }

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((eventName) => {
        document.addEventListener(eventName, (event) => {
            event.preventDefault();
        });
    });

    document.addEventListener('dragenter', () => {
        dragDepth += 1;
        if (dropOverlay) {
            dropOverlay.classList.add('active');
        }
    });

    document.addEventListener('dragleave', () => {
        dragDepth = Math.max(0, dragDepth - 1);
        if (dragDepth === 0 && dropOverlay) {
            dropOverlay.classList.remove('active');
        }
    });

    document.addEventListener('drop', (event) => {
        resetDropState();
        const items = Array.from(event.dataTransfer?.items || []);
        const entries = items
            .map((item) => (typeof item.webkitGetAsEntry === 'function' ? item.webkitGetAsEntry() : null))
            .filter((entry) => entry !== null);

        if (entries.length !== 1 || !entries[0].isDirectory) {
            appendLog(i18n.drop_invalid_selection, true);
            return;
        }

        collectEntryFiles(entries[0]).then((files) => {
            droppedFiles = files;
            try {
                folderInput.value = '';
            } catch (_) {
            }
            hasClientReadErrorSinceSelection = false;
            startSyncBtn.disabled = false;
            retryFailedBtn.disabled = failedRelpaths.length === 0;
            updateSelectionStatus();
            appendLog(i18n.drop_selected.replace('%d', String(files.length)));
        }).catch(() => {
            appendLog(i18n.drop_invalid_selection, true);
        });
    });

    startSyncBtn.addEventListener('click', async () => {
        try {
            if (!(await refreshSelectionIfNeeded())) {
                return;
            }
            await runSync(selectedFiles(), { dryRun: false, force: false, trackFailed: true });
        } catch (error) {
            appendLog(`sync error: ${localizeErrorMessage(error.message)}`, true);
        }
    });

    testSyncBtn.addEventListener('click', async () => {
        try {
            if (!(await refreshSelectionIfNeeded())) {
                return;
            }
            await runSync(selectedFiles(), { dryRun: true, force: false, trackFailed: false });
        } catch (error) {
            appendLog(`dry-run error: ${localizeErrorMessage(error.message)}`, true);
        }
    });

    retryFailedBtn.addEventListener('click', async () => {
        if (failedRelpaths.length === 0) return;

        if (!(await refreshSelectionIfNeeded())) {
            return;
        }

        const byPath = currentFileMap();
        const retry = [];
        const missing = [];

        for (const relpath of failedRelpaths) {
            const file = byPath.get(relpath);
            if (file) retry.push(file);
            else missing.push(relpath);
        }
        failedRelpaths = [];

        for (const relpath of missing) {
            appendLog(`skip: ${relpath} (${i18n.retry_unavailable})`, true);
        }
        if (retry.length === 0) {
            retryFailedBtn.disabled = true;
            return;
        }

        try {
            await runSync(retry, { dryRun: false, force: false, trackFailed: true });
        } catch (error) {
            appendLog(`retry error: ${localizeErrorMessage(error.message)}`, true);
        }
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
    updateSelectionStatus();
})();
</script>
</body>
</html>
