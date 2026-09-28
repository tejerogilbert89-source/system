<?php
/** POST handler for renewing a loan. */
Auth::requireRole(['librarian']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['csrf_token'] ?? null)) {
    header('Location: /circulation/loans');
    exit;
}

$loanId = (int) ($_POST['loan_id'] ?? 0);

if ($loanId) {
    $result = Loan::renew($loanId);

    if ($result['ok']) {
        Audit::log(Auth::id(), 'circulation_renew', 'loans', $loanId, null, ['new_due_at' => $result['due_at']]);
        $_SESSION['flash'] = [
            'type' => 'success',
            'text' => 'Renewed - new due date ' . date('M j, Y', strtotime($result['due_at'])) . '.',
        ];
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'text' => $result['error']];
    }
}

header('Location: /circulation/loans');
exit;