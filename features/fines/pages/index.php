<?php
/**
 * Fines - patron balances built from the account_transactions ledger.
 * Charges are created automatically by circulation when a late item is
 * returned (see features/circulation/Loan.php::returnItem). This screen
 * is for collecting on them and reviewing history.
 */
$layout = 'staff';
Auth::requireRole(['librarian']);

$q      = trim($_GET['q'] ?? '');
$filter = in_array($_GET['f'] ?? '', ['unpaid', 'settled'], true) ? $_GET['f'] : 'all';

$summary = Fine::summary();
$rows    = Fine::patrons($q, $filter);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$tabs = ['all' => 'All', 'unpaid' => 'Unpaid', 'settled' => 'Settled'];
$qs = fn(string $f) => '?f=' . $f . ($q !== '' ? '&q=' . urlencode($q) : '');
?>
<h1>Fines</h1>
<p class="muted">Overdue fines are charged automatically when a late item is returned.</p>

<div class="stat-row">
    <div>
        <div class="stat-number"><?= number_format($summary['outstanding'], 2) ?></div>
        <div class="stat-label">Outstanding (₱)</div>
    </div>
    <div>
        <div class="stat-number"><?= $summary['patrons_owing'] ?></div>
        <div class="stat-label">Patrons with a balance</div>
    </div>
    <div>
        <div class="stat-number"><?= number_format($summary['collected'], 2) ?></div>
        <div class="stat-label">Collected all-time (₱)</div>
    </div>
</div>

<?php if ($flash): ?>
    <p class="<?= $flash['type'] === 'error' ? 'error' : 'muted' ?>"><?= htmlspecialchars($flash['text']) ?></p>
<?php endif; ?>

<div class="section-heading">
    <div>
        <?php foreach ($tabs as $key => $label): ?>
            <a href="<?= $qs($key) ?>"><?= $filter === $key ? "<strong>{$label}</strong>" : $label ?></a>
            <?= $key !== array_key_last($tabs) ? ' &middot; ' : '' ?>
        <?php endforeach; ?>
    </div>
</div>

<form method="get" action="/fines" class="search-bar" style="max-width: 420px;">
    <input type="hidden" name="f" value="<?= htmlspecialchars($filter) ?>">
    <input type="text" name="q" placeholder="Search by patron or ID number" value="<?= htmlspecialchars($q) ?>">
    <button type="submit" class="btn btn-secondary">Search</button>
</form>

<table>
    <thead>
        <tr><th>Patron</th><th>Category</th><th>Charged</th><th>Paid</th><th>Balance</th><th></th></tr>
    </thead>
    <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="6" class="muted">No matching fine history.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <?php $balance = (float) $r['balance']; ?>
            <tr>
                <td>
                    <a href="/fines/patron?user_id=<?= (int) $r['user_id'] ?>">
                        <?= htmlspecialchars($r['full_name']) ?>
                    </a>
                    <div class="muted" style="font-size: 0.85em;"><?= htmlspecialchars($r['id_number']) ?></div>
                </td>
                <td><?= htmlspecialchars($r['category_name'] ?? '—') ?></td>
                <td><?= number_format((float) $r['charged'], 2) ?></td>
                <td><?= number_format((float) $r['paid'], 2) ?></td>
                <td>
                    <?= number_format($balance, 2) ?>
                    <?php if ($balance > 0.001): ?>
                        <span class="badge badge-overdue">Unpaid</span>
                    <?php else: ?>
                        <span class="badge badge-available">Settled</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($balance > 0.001): ?>
                        <form method="post" action="/fines/pay" style="display:inline;">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="user_id" value="<?= (int) $r['user_id'] ?>">
                            <input type="hidden" name="full" value="1">
                            <input type="hidden" name="back" value="/fines<?= htmlspecialchars($qs($filter)) ?>">
                            <button type="submit" class="btn btn-ghost">Pay full (<?= number_format($balance, 2) ?>)</button>
                        </form>
                    <?php endif; ?>
                    <a href="/fines/patron?user_id=<?= (int) $r['user_id'] ?>">Ledger</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
