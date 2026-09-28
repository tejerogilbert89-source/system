<?php
/**
 * One patron's full fine ledger, with forms to record a custom payment
 * or waive part of the balance. "Pay full" quick actions live on the
 * main /fines list; this page is for partial payments and exceptions.
 */
$layout = 'staff';
Auth::requireRole(['librarian']);

$userId = (int) ($_GET['user_id'] ?? 0);
$patron = $userId ? Fine::patronInfo($userId) : null;

if (!$patron) {
    http_response_code(404);
    echo '<h1>Patron not found</h1><p class="muted">No account matches that ID.</p>';
    return;
}

$balance = Fine::balanceFor($userId);
$ledger  = Fine::ledgerFor($userId);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$typeLabels = [
    'charge'     => ['Charge', 'badge-overdue'],
    'payment'    => ['Payment', 'badge-available'],
    'waiver'     => ['Waiver', 'badge-hold'],
    'adjustment' => ['Adjustment', 'badge-out'],
    'refund'     => ['Refund', 'badge-available'],
];
?>
<div class="section-heading">
    <h1><?= htmlspecialchars($patron['full_name']) ?></h1>
    <a href="/fines">&larr; Back to Fines</a>
</div>
<p class="muted">
    <?= htmlspecialchars($patron['id_number']) ?>
    <?= $patron['category_name'] ? ' &middot; ' . htmlspecialchars($patron['category_name']) : '' ?>
</p>

<?php if ($flash): ?>
    <p class="<?= $flash['type'] === 'error' ? 'error' : 'muted' ?>"><?= htmlspecialchars($flash['text']) ?></p>
<?php endif; ?>

<div class="stat-row">
    <div>
        <div class="stat-number"><?= number_format($balance, 2) ?></div>
        <div class="stat-label">Current balance (₱)</div>
    </div>
</div>

<?php if ($balance > 0.001): ?>
<div class="card" style="max-width: 480px;">
    <h3>Record a payment</h3>
    <form method="post" action="/fines/pay">
        <?= Csrf::field() ?>
        <input type="hidden" name="user_id" value="<?= (int) $userId ?>">
        <input type="hidden" name="back" value="/fines/patron?user_id=<?= (int) $userId ?>">
        <label>
            Amount (₱, up to <?= number_format($balance, 2) ?>)
            <input type="number" name="amount" step="0.01" min="0.01" max="<?= number_format($balance, 2, '.', '') ?>" required>
        </label>
        <label>
            Note (optional)
            <input type="text" name="note" maxlength="255" placeholder="e.g. Paid at circulation desk">
        </label>
        <button type="submit" class="btn btn-primary">Record payment</button>
    </form>
</div>

<div class="card" style="max-width: 480px; margin-top: 1rem;">
    <h3>Waive a balance</h3>
    <form method="post" action="/fines/waive">
        <?= Csrf::field() ?>
        <input type="hidden" name="user_id" value="<?= (int) $userId ?>">
        <input type="hidden" name="back" value="/fines/patron?user_id=<?= (int) $userId ?>">
        <label>
            Amount to waive (₱, up to <?= number_format($balance, 2) ?>)
            <input type="number" name="amount" step="0.01" min="0.01" max="<?= number_format($balance, 2, '.', '') ?>" required>
        </label>
        <label>
            Reason (required)
            <input type="text" name="reason" maxlength="255" required placeholder="e.g. Lost book replaced by patron">
        </label>
        <button type="submit" class="btn btn-secondary">Waive</button>
    </form>
</div>
<?php endif; ?>

<div class="section-heading">
    <h2>Transaction history</h2>
</div>
<table>
    <thead>
        <tr><th>Date</th><th>Type</th><th>Description</th><th>Amount</th><th>Recorded by</th></tr>
    </thead>
    <tbody>
        <?php if (!$ledger): ?>
            <tr><td colspan="5" class="muted">No fine activity yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($ledger as $t): ?>
            <?php [$label, $badgeClass] = $typeLabels[$t['type']] ?? [ucfirst($t['type']), 'badge-out']; ?>
            <tr>
                <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($t['created_at']))) ?></td>
                <td><span class="badge <?= $badgeClass ?>"><?= $label ?></span></td>
                <td><?= htmlspecialchars($t['description'] ?? '—') ?></td>
                <td><?= number_format((float) $t['amount'], 2) ?></td>
                <td><?= htmlspecialchars($t['staff_name'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
