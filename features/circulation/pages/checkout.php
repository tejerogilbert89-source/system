<?php
/** POST handler for checking out a scanned barcode to a looked-up patron. */
Auth::requireRole(['librarian']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['csrf_token'] ?? null)) {
    header('Location: /circulation');
    exit;
}

$patronUserId = (int) ($_POST['patron_user_id'] ?? 0);
$idNumber = trim($_POST['patron_id_number'] ?? '');
$barcode = trim($_POST['barcode'] ?? '');

if ($patronUserId && $barcode !== '') {
    $result = Loan::checkout($barcode, $patronUserId, Auth::id());

    if ($result['ok']) {
        Audit::log(Auth::id(), 'circulation_checkout', 'loans', $result['loan_id'], null, [
            'barcode' => $barcode,
            'patron_user_id' => $patronUserId,
        ]);
        $_SESSION['flash'] = [
            'type' => 'success',
            'text' => 'Checked out "' . $result['title'] . '", due ' . date('M j, Y', strtotime($result['due_at'])) . '.',
        ];
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'text' => $result['error']];
    }
}

header('Location: /circulation?patron=' . urlencode($idNumber));
exit;