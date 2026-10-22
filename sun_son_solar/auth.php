<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';










function role_home(string $role): string {
    switch ($role) {
        case 'admin':      return 'admin_dashboard.php';
        case 'customer':   return 'customer_dashboard.php';
        case 'technician': return 'technician_dashboard.php';
        case 'dispatcher': return 'dispatcher_dashboard.php';
        case 'hr':         return 'admin_users.php';
        default:           return 'employee_announcements.php';
    }
}

function role_from_user(string $accountType, ?string $department): string {
    if ($accountType === 'Customer') {
        return 'customer';
    }
    if ($department === 'Technician') {
        return 'technician';
    }
    if ($department === 'Dispatch' || $department === 'Dispatcher') {
        return 'dispatcher';
    }
    if ($department === 'HR') {
        return 'hr';
    }
    return 'staff';
}

function force_logout(string $message, string $page = 'index.php'): void {
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['error'] = $message;
    $_SESSION['active_form'] = 'login';
    header('Location: ' . $page);
    exit();
}





function require_auth(array $allowedRoles): array {
    global $conn;

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $loginPage = in_array('admin', $allowedRoles, true) && count($allowedRoles) === 1 ? 'admin.php' : 'index.php';
    $auth = null;

    if (!empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_id'])) {
        $stmt = $conn->prepare("SELECT id, username, first_name, last_name, status, must_change_password FROM admins WHERE id = ?");
        $stmt->bind_param('i', $_SESSION['admin_id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || $row['status'] !== 'active') {
            force_logout('Your administrator account is no longer active.', 'admin.php');
        }
        $auth = [
            'role' => 'admin', 'id' => (int) $row['id'], 'username' => $row['username'],
            'name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'department' => 'Administration', 'must_change_password' => (int) $row['must_change_password'],
        ];
    } elseif (!empty($_SESSION['user_id'])) {
        $stmt = $conn->prepare("SELECT id, username, first_name, last_name, account_type, department, status, must_change_password FROM users WHERE id = ?");
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || $row['status'] !== 'approved') {
            force_logout('Your account is no longer active. Please contact Sun Son Solar.');
        }
        $auth = [
            'role' => role_from_user($row['account_type'], $row['department']),
            'id' => (int) $row['id'], 'username' => $row['username'],
            'name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'department' => $row['department'], 'must_change_password' => (int) $row['must_change_password'],
        ];
    }

    if ($auth === null) {
        header('Location: ' . $loginPage);
        exit();
    }

    if (!in_array($auth['role'], $allowedRoles, true)) {
        $_SESSION['error'] = 'You do not have permission to access that page.';
        header('Location: ' . role_home($auth['role']));
        exit();
    }

    $GLOBALS['AUTH'] = $auth;
    return $auth;
}
