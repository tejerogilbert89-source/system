<?php
/**
 * Check out. "Who has it right now, and until when?"
 */
$layout = 'staff';
Auth::requireRole(['librarian']);

$idNumber = trim($_GET['patron'] ?? '');
$patron = null;
$lookupError = null;

if ($idNumber !== '') {
    $patron = Loan::findPatronByIdNumber($idNumber);
    if (!$patron) {
        $lookupError = 'No patron found with ID "' . $idNumber . '".';
    }
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$currentLoans = $patron ? Loan::patronActiveLoans($patron['id']) : [];
?>
<div class="section-heading">
    <h1>Check out</h1>
</div>

<?php if ($flash): ?>
    <p class="<?= $flash['type'] === 'error' ? 'error' : 'muted' ?>"><?= htmlspecialchars($flash['text']) ?></p>
<?php endif; ?>

<form method="get" action="/circulation" class="search-bar" style="max-width: 420px;">
    <input type="text" name="patron" placeholder="Patron ID number" value="<?= htmlspecialchars($idNumber) ?>">
    <button type="submit" class="btn btn-secondary">Find</button>
</form>

<?php if ($idNumber === ''): ?>
    <div class="section-heading" style="margin-top: 2.5rem;">
        <h2>Recent checkouts</h2>
    </div>
    <table>
        <thead><tr><th>Time</th><th>Patron</th><th>Title</th><th>Barcode</th></tr></thead>
        <tbody>
            <?php $recent = Loan::recentCheckouts(8); ?>
            <?php if (!$recent): ?>
                <tr><td colspan="4" class="muted">No checkouts yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($recent as $r): ?>
                <tr>
                    <td><?= htmlspecialchars(date('M j, g:i A', strtotime($r['checked_out_at']))) ?></td>
                    <td><?= htmlspecialchars($r['full_name']) ?> (<?= htmlspecialchars($r['id_number']) ?>)</td>
                    <td><?= htmlspecialchars($r['title']) ?></td>
                    <td><?= htmlspecialchars($r['barcode']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if ($lookupError): ?>
    <p class="error"><?= htmlspecialchars($lookupError) ?></p>
<?php elseif ($patron): ?>
    <div class="section-heading">
        <p><strong><?= htmlspecialchars($patron['full_name']) ?></strong>
            &middot; <?= htmlspecialchars($patron['category_name']) ?>
            &middot; <?= count($currentLoans) ?> item(s) on loan</p>
    </div>

    <form method="post" action="/circulation/checkout" class="search-bar" style="max-width: 420px;">
        <?= Csrf::field() ?>
        <input type="hidden" name="patron_user_id" value="<?= (int) $patron['id'] ?>">
        <input type="hidden" name="patron_id_number" value="<?= htmlspecialchars($idNumber) ?>">
        <input type="text" name="barcode" placeholder="Scan or enter barcode" autofocus required>
        <button type="submit" class="btn btn-primary">Check out</button>
    </form>

    <table>
        <thead><tr><th>Title</th><th>Barcode</th><th>Due</th></tr></thead>
        <tbody>
            <?php if (!$currentLoans): ?>
                <tr><td colspan="3" class="muted">No items currently on loan.</td></tr>
            <?php endif; ?>
            <?php foreach ($currentLoans as $l): ?>
                <tr>
                    <td><?= htmlspecialchars($l['title']) ?></td>
                    <td><?= htmlspecialchars($l['barcode']) ?></td>
                    <td><?= htmlspecialchars(date('M j, Y', strtotime($l['due_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p style="margin-top: 2rem;"><a href="/circulation/loans">View all active loans &amp; returns &rarr;</a></p>