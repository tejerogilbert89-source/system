<?php
/** POST handler for recording a fine payment - full balance or a custom amount. */
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
$isFull = ($_POST['full'] ?? '') === '1';
$amount = $isFull ? null : (float) ($_POST['amount'] ?? 0);
$note   = trim($_POST['note'] ?? '');

if ($userId) {
    $result = Fine::pay($userId, $amount, Auth::id(), $note);

    if ($result['ok']) {
        Audit::log(Auth::id(), 'fine_payment', 'account_transactions', $userId, null, [
            'amount' => $result['amount'],
            'remaining' => $result['remaining'],
        ]);
        $_SESSION['flash'] = [
            'type' => 'success',
            'text' => 'Recorded a payment of ' . number_format($result['amount'], 2)
                    . '. Remaining balance: ' . number_format($result['remaining'], 2) . '.',
        ];
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'text' => $result['error']];
    }
}

header('Location: ' . $back);
exit;
