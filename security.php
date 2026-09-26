<?php


function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], (string) $token);
}



function login_is_locked($key) {
    if (!isset($_SESSION['login_throttle'][$key])) {
        return false;
    }
    $entry = $_SESSION['login_throttle'][$key];
    return isset($entry['locked_until']) && time() < $entry['locked_until'];
}

function login_lock_seconds_remaining($key) {
    if (!isset($_SESSION['login_throttle'][$key]['locked_until'])) {
        return 0;
    }
    return max(0, $_SESSION['login_throttle'][$key]['locked_until'] - time());
}

function login_register_failure($key, $maxAttempts = 5, $lockoutSeconds = 60) {
    if (!isset($_SESSION['login_throttle'][$key])) {
        $_SESSION['login_throttle'][$key] = ['count' => 0, 'locked_until' => 0];
    }
    $_SESSION['login_throttle'][$key]['count']++;
    if ($_SESSION['login_throttle'][$key]['count'] >= $maxAttempts) {
        $_SESSION['login_throttle'][$key]['locked_until'] = time() + $lockoutSeconds;
        $_SESSION['login_throttle'][$key]['count'] = 0;
    }
}

function login_register_success($key) {
    unset($_SESSION['login_throttle'][$key]);
}
