<?php
/** POST handler for creating a new account (any role). */
Auth::requireRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['csrf_token'] ?? null)) {
    $_SESSION['flash'] = ['type' => 'error', 'text' => 'Your session expired. Please try again.'];
    header('Location: /users');
    exit;
}

$result = User::create($_POST);

if ($result['ok']) {
    Audit::log(Auth::id(), 'user_create', 'users', $result['id'], null, [
        'id_number' => $result['id_number'],
        'role'      => $result['role_name'],
    ]);
    $_SESSION['flash'] = [
        'type' => 'success',
        'text' => 'Account created for ID number ' . $result['id_number']
                . '. Starting password is the ID number - they should change it from My Profile.',
    ];
} else {
    $_SESSION['flash'] = ['type' => 'error', 'text' => $result['error']];
}

header('Location: /users');
exit;
