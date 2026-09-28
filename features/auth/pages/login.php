<?php
/**
 * Login page. GET shows the form, POST processes it.
 * Every login attempt (success or failure) is written to the audit log.
 */
$error = null;
$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch' || ($_POST['ajax'] ?? '') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $idNumber = trim($_POST['id_number'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($idNumber === '' || $password === '') {
            $error = 'Please enter your ID number and password.';
        } elseif (Auth::attempt($idNumber, $password)) {
            Audit::log(Auth::id(), 'login', 'users', Auth::id());
            // Staff go to their dashboard. Patrons go to the OPAC homepage -
            // they don't have access to /dashboard anymore (see Auth::requireRole).
            $destination = in_array(Auth::role(), ['admin', 'librarian'], true) ? '/dashboard' : '/';

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => true, 'redirect' => $destination]);
                exit;
            }
            header('Location: ' . $destination);
            exit;
        } else {
            Audit::log(null, 'login_failed', 'users', null, null, ['id_number' => $idNumber]);
            $error = 'Wrong ID number or password.';
        }
    }

    // Reaching here means it failed (success already exited above). The
    // login modal submits via fetch with this header - answer it in JSON
    // so it can show the error without leaving the page; a real form post
    // (no-JS, or on the standalone /login page) falls through to the
    // normal full-page re-render below instead.
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $error]);
        exit;
    }
}
?>
<?php require __DIR__ . '/../login-form.php'; ?>