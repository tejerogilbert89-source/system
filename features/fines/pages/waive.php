<?php
/** POST handler for waiving part or all of a patron's fine balance. */
Auth::requireRole(['librarian']);

$back = $_POST['back'] ?? '/fines';
if (strpos($back, '/fines') !== 0) {
    $back = '/fines';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['csrf_token'] ?? null)) {
    header('Location: ' . $back);
    exit;
}

$userId = (int) ($_POST['user_id'] ?? 0);
$amount = (float) ($_POST['amount'] ?? 0);
$reason = trim($_POST['reason'] ?? '');

if ($userId) {
    $result = Fine::waive($userId, $amount, Auth::id(), $reason);

    if ($result['ok']) {
        Audit::log(Auth::id(), 'fine_waiver', 'account_transactions', $userId, null, [
            'amount' => $result['amount'],
            'remaining' => $result['remaining'],
            'reason' => $reason,
        ]);
        $_SESSION['flash'] = [
            'type' => 'success',
            'text' => 'Waived ' . number_format($result['amount'], 2)
                    . '. Remaining balance: ' . number_format($result['remaining'], 2) . '.',
        ];
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'text' => $result['error']];
    }
}

header('Location: ' . $back);
exit;
