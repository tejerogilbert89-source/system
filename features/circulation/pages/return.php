<?php
/** POST handler for returning a loan (charges an overdue fine automatically if late). */
Auth::requireRole(['librarian']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['csrf_token'] ?? null)) {
    header('Location: /circulation/loans');
    exit;
}

$loanId = (int) ($_POST['loan_id'] ?? 0);

if ($loanId) {
    $result = Loan::returnItem($loanId, Auth::id());

    if ($result['ok']) {
        Audit::log(Auth::id(), 'circulation_return', 'loans', $loanId, null, ['fine' => $result['fine']]);
        $text = 'Returned "' . $result['title'] . '"';
        $text .= $result['fine'] > 0 ? ' - fine of ' . number_format($result['fine'], 2) . ' recorded.' : '.';
        $_SESSION['flash'] = ['type' => 'success', 'text' => $text];
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'text' => $result['error']];
    }
}

header('Location: /circulation/loans');
exit;