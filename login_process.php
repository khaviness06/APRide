<?php
require_once 'includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('login.php');
}
csrf_check();

$tp = trim($_POST['tp_number'] ?? '');
$pass = (string)($_POST['password'] ?? '');

function login_failed($tp, $message)
{
    $_SESSION['old_tp'] = $tp;
    flash('error', $message);
    redirect('login.php');
}

$user = db_one("SELECT user_id, password, role FROM user WHERE TP_Number = ?", 's', [$tp]);
if (!$user) {
    login_failed($tp, 'Invalid TP number or password.');
}

$stored = $user['password'];
if (password_verify($pass, $stored)) {
    $valid = true;
} elseif (password_get_info($stored)['algoName'] === 'unknown' && hash_equals($stored, $pass)) {
    // Older accounts still have plain-text passwords: accept once, then store a hash instead.
    $valid = true;
    db_exec("UPDATE user SET password = ? WHERE user_id = ?", 'si', [password_hash($pass, PASSWORD_DEFAULT), $user['user_id']]);
} else {
    $valid = false;
}
if (!$valid) {
    login_failed($tp, 'Invalid TP number or password.');
}

$uid = (int)$user['user_id'];
$role = role_for_user($uid);

if ($role === 'driver') {
    $status = db_value("SELECT status FROM driver WHERE user_id = ?", 'i', [$uid]);
    if (strcasecmp($status, 'Rejected') === 0) {
        login_failed($tp, 'Your driver registration was rejected by the admin. Please contact the APRide team.');
    }
    if (strcasecmp($status, 'Approved') !== 0) {
        login_failed($tp, 'Your driver registration is waiting for admin approval. You can log in once it is approved.');
    }
}

if ($role === null) {
    $message = strcasecmp($user['role'], 'Driver') === 0
        ? 'Your driver registration is incomplete (no vehicle details on file). Please contact the admin.'
        : 'Your account has no APRide profile yet. Please contact the admin.';
    login_failed($tp, $message);
}

session_regenerate_id(true);
$_SESSION['user_id'] = $uid;
redirect(home_for_role($role));
