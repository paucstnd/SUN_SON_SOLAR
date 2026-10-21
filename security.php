<?php


function csrf_token() {
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify($token): bool {
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || !is_string($token) || $token === '') {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}



function login_is_locked(mysqli $conn, string $username, string $ipAddress): bool {
    $stmt = $conn->prepare(
        'SELECT locked_until > NOW() AS is_locked FROM login_attempts WHERE username = ? AND ip_address = ?'
    );
    $stmt->bind_param('ss', $username, $ipAddress);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $result && (bool) $result['is_locked'];
}

function login_lock_seconds_remaining(mysqli $conn, string $username, string $ipAddress): int {
    $stmt = $conn->prepare(
        'SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), locked_until)) AS seconds_remaining FROM login_attempts WHERE username = ? AND ip_address = ?'
    );
    $stmt->bind_param('ss', $username, $ipAddress);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($result['seconds_remaining'] ?? 0);
}

function login_register_failure(mysqli $conn, string $username, string $ipAddress, int $maxAttempts = 5, int $lockoutSeconds = 60): void {
    $conn->begin_transaction();
    try {
        $insert = $conn->prepare(
            'INSERT IGNORE INTO login_attempts (username, ip_address, failed_attempts) VALUES (?, ?, 0)'
        );
        $insert->bind_param('ss', $username, $ipAddress);
        $insert->execute();
        $insert->close();

        $select = $conn->prepare(
            'SELECT failed_attempts, locked_until > NOW() AS is_locked, locked_until <= NOW() AS lock_expired FROM login_attempts WHERE username = ? AND ip_address = ? FOR UPDATE'
        );
        $select->bind_param('ss', $username, $ipAddress);
        $select->execute();
        $entry = $select->get_result()->fetch_assoc();
        $select->close();

        if (!$entry) {
            throw new RuntimeException('Login attempt record could not be loaded.');
        }

        if (!$entry['is_locked']) {
            $failures = $entry['lock_expired'] ? 1 : (int) $entry['failed_attempts'] + 1;
            $lock = $failures >= $maxAttempts ? 1 : 0;
            $update = $conn->prepare(
                'UPDATE login_attempts SET failed_attempts = ?, locked_until = IF(? = 1, DATE_ADD(NOW(), INTERVAL ? SECOND), NULL), last_attempt = NOW() WHERE username = ? AND ip_address = ?'
            );
            $update->bind_param('iiiss', $failures, $lock, $lockoutSeconds, $username, $ipAddress);
            $update->execute();
            $update->close();
        }

        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }
}

function login_register_success(mysqli $conn, string $username, string $ipAddress): void {
    $stmt = $conn->prepare('DELETE FROM login_attempts WHERE username = ? AND ip_address = ?');
    $stmt->bind_param('ss', $username, $ipAddress);
    $stmt->execute();
    $stmt->close();
}

/* ---------- Shared helpers (added) ---------- */

function e($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Stop the request unless it is a POST carrying a valid CSRF token. */
function require_post_csrf(string $redirect = ''): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? '')) {
        if ($redirect !== '') {
            $_SESSION['error'] = 'Your session expired. Please try again.';
            header('Location: ' . $redirect);
            exit();
        }
        http_response_code(403);
        exit('Invalid request.');
    }
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit();
}

function flash_success(string $msg): void {
    $_SESSION['success'] = $msg;
    log_activity($msg);
}

/** Record who did what (shown in Admin > Activity log). Never breaks the page if logging fails. */
function log_activity(string $action, ?array $actor = null): void {
    global $conn;
    $a = $actor ?? ($GLOBALS['AUTH'] ?? null);
    if (!$a || !isset($conn)) {
        return;
    }
    try {
        $role = (string) $a['role'];
        $id = (int) $a['id'];
        $name = (string) ($a['name'] ?? '');
        $action = function_exists('mb_substr') ? mb_substr($action, 0, 255) : substr($action, 0, 255);
        $page = substr(basename($_SERVER['SCRIPT_NAME'] ?? ''), 0, 60);
        $ip = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
        $stmt = $conn->prepare('INSERT INTO activity_log (actor_role, actor_id, actor_name, action, page, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('sissss', $role, $id, $name, $action, $page, $ip);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        // table may not exist yet (migration not run) - ignore
    }
}
function flash_error(string $msg): void { $_SESSION['error'] = $msg; }
