<?php
/** POST handler for recording a fine payment - full balance or a custom amount. */
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

// One-time form token: a double-click or a refresh must not record the payment twice.
if (!Fine::claimToken($_POST['req_id'] ?? null)) {
    $finish('error', 'That form was already sent or has expired. Check the ledger, then try again.', true);
}

$userId = Fine::parseId($_POST['user_id'] ?? null);
if ($userId === 0) {
    $finish('error', 'Choose a patron first.');
}

$note     = Fine::cleanText($_POST['note'] ?? null);
$amount   = null;
$expected = null;

if (($_POST['full'] ?? '') === '1') {
    // "Pay full" - pays whatever the balance is, but only if it is still the
    // amount the staff member was looking at when they clicked.
    $expected = Fine::parseAmount($_POST['expected'] ?? null);
    if ($expected === null) {
        $finish('error', 'Could not read the balance from that page. Reload and try again.');
    }
} else {
    $amount = Fine::parseAmount($_POST['amount'] ?? null);
    if ($amount === null) {
        $finish('error', 'Enter a valid amount in pesos, like 25 or 25.50 (at most 2 decimals).');
    }
}

$result = Fine::pay($userId, $amount, Auth::id(), $note, $expected);

if ($result['ok']) {
    Audit::log(Auth::id(), 'fine_payment', 'account_transactions', $userId, null, [
        'amount'         => $result['amount'],
        'remaining'      => $result['remaining'],
        'transaction_id' => $result['id'] ?? null,
        'note'           => $note,
    ]);
    $finish('success', 'Recorded a payment of ' . number_format($result['amount'], 2)
        . '. Remaining balance: ' . number_format($result['remaining'], 2) . '.');
}

$finish('error', $result['error']);