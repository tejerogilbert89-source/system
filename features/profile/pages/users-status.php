<?php
/** POST handler for suspending or reactivating an account. */
Auth::requireRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /users');
    exit;
}

if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    $_SESSION['flash'] = ['type' => 'error', 'text' => 'Your session expired. Please try again.'];
    header('Location: /users');
    exit;
}

// A tampered "user_id[]=..." array must not be silently cast to 1, and a
// tampered "status[]=..." array must not crash the typed setStatus() call.
$userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$status = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';

if ($userId === false) {
    $_SESSION['flash'] = ['type' => 'error', 'text' => 'Could not update that account.'];
} elseif ($userId === Auth::id()) {
    $_SESSION['flash'] = ['type' => 'error', 'text' => 'You cannot change your own account status.'];
} elseif (User::setStatus($userId, $status)) {
    Audit::log(Auth::id(), 'user_status_change', 'users', $userId, null, ['status' => $status]);
    $_SESSION['flash'] = ['type' => 'success', 'text' => 'Account status updated.'];
} else {
    $_SESSION['flash'] = ['type' => 'error', 'text' => 'Could not update that account.'];
}

header('Location: /users');
exit;