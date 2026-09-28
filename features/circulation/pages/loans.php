<?php
/**
 * All active loans, for returns and renewals. Overdue rows are flagged.
 */
$layout = 'staff';
Auth::requireRole(['librarian']);

$q = trim($_GET['q'] ?? '');
$loans = Loan::activeLoans($q);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<div class="section-heading">
    <h1>Active loans</h1>
    <a href="/circulation">+ New checkout</a>
</div>

<?php if ($flash): ?>
    <p class="<?= $flash['type'] === 'error' ? 'error' : 'muted' ?>"><?= htmlspecialchars($flash['text']) ?></p>
<?php endif; ?>

<form method="get" action="/circulation/loans" class="search-bar" style="max-width: 480px;">
    <input type="text" name="q" placeholder="Search by patron, title, or barcode" value="<?= htmlspecialchars($q) ?>">
    <button type="submit" class="btn btn-secondary">Search</button>
</form>

<table>
    <thead>
        <tr><th>Patron</th><th>Title</th><th>Barcode</th><th>Due</th><th>Status</th><th></th></tr>
    </thead>
    <tbody>
        <?php if (!$loans): ?>
            <tr><td colspan="6" class="muted">No active loans.</td></tr>
        <?php endif; ?>
        <?php foreach ($loans as $l): ?>
            <?php $overdue = strtotime($l['due_at']) < time(); ?>
            <tr>
                <td><?= htmlspecialchars($l['full_name']) ?> (<?= htmlspecialchars($l['id_number']) ?>)</td>
                <td><?= htmlspecialchars($l['title']) ?></td>
                <td><?= htmlspecialchars($l['barcode']) ?></td>
                <td><?= htmlspecialchars(date('M j, Y', strtotime($l['due_at']))) ?></td>
                <td>
                    <span class="badge <?= $overdue ? 'badge-overdue' : 'badge-available' ?>">
                        <?= $overdue ? 'Overdue' : 'On time' ?>
                    </span>
                </td>
                <td>
                    <form method="post" action="/circulation/return" style="display:inline;">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="loan_id" value="<?= (int) $l['id'] ?>">
                        <button type="submit" class="btn btn-ghost">Return</button>
                    </form>
                    <form method="post" action="/circulation/renew" style="display:inline;">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="loan_id" value="<?= (int) $l['id'] ?>">
                        <button type="submit" class="btn btn-ghost">Renew</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>