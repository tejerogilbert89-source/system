<?php
/** POST handler for waiving part or all of a patron's fine balance. */
Auth::requireRole(['librarian']);

// Only ever redirect to a /fines page (never an outside URL, never a tampered value).
$back = Fine::safeBack($_POST['back'] ?? null);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $back);
    exit;
}

/** Flash a message and go back. $keepExisting stops a duplicate submit from hiding the first one's result. */
$finish = function (string $type, string $text, bool $keepExisting = false) use ($back): void {
    if (!($keepExisting && isset($_SESSION['flash']))) {
        $_SESSION['flash'] = ['type' => $type, 'text' => $text];
    }
    header('Location: ' . $back);
    exit;
};

if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    $finish('error', 'Your session expired. Please try again.');
}

// One-time form token: a double-click or a refresh must not record the waiver twice.
if (!Fine::claimToken($_POST['req_id'] ?? null)) {
    $finish('error', 'That form was already sent or has expired. Check the ledger, then try again.', true);
}

$userId = Fine::parseId($_POST['user_id'] ?? null);
if ($userId === 0) {
    $finish('error', 'Choose a patron first.');
}

$amount = Fine::parseAmount($_POST['amount'] ?? null);
if ($amount === null) {
    $finish('error', 'Enter a valid amount in pesos, like 25 or 25.50 (at most 2 decimals).');
}

$reason = Fine::cleanText($_POST['reason'] ?? null);
if ($reason === '') {
    $finish('error', 'A reason is required to waive a fine.');
}

$result = Fine::waive($userId, $amount, Auth::id(), $reason);

if ($result['ok']) {
    Audit::log(Auth::id(), 'fine_waiver', 'account_transactions', $userId, null, [
        'amount'         => $result['amount'],
        'remaining'      => $result['remaining'],
        'reason'         => $reason,
        'transaction_id' => $result['id'] ?? null,
    ]);
    $finish('success', 'Waived ' . number_format($result['amount'], 2)
        . '. Remaining balance: ' . number_format($result['remaining'], 2) . '.');
}

$finish('error', $result['error']);