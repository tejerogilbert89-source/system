<?php
/**
 * Catalog browse/search. "What is this, and what do we call it?"
 */
$layout = 'staff';
Auth::requireRole(['librarian']);

$q = trim($_GET['q'] ?? '');
$titles = Title::search($q);
?>
<h1>Catalog</h1>
<p class="muted">Browse titles, add new ones, and manage their copies.</p>

<div class="section-heading">
    <form method="get" action="/catalog" class="search-bar" style="flex:1; max-width: 480px;">
        <input type="text" name="q" placeholder="Search by title, author, or ISBN" value="<?= htmlspecialchars($q) ?>">
        <button type="submit" class="btn btn-secondary">Search</button>
    </form>
    <a href="/catalog/new" class="btn btn-primary">+ Add title</a>
</div>

<table>
    <thead>
        <tr><th>Title</th><th>Author</th><th>Format</th><th>ISBN</th><th>Copies</th><th></th></tr>
    </thead>
    <tbody>
        <?php if (!$titles): ?>
            <tr><td colspan="6" class="muted">No titles found.</td></tr>
        <?php endif; ?>
        <?php foreach ($titles as $t): ?>
            <tr>
                <td><?= htmlspecialchars($t['title']) ?></td>
                <td><?= htmlspecialchars($t['authors'] ?? '') ?></td>
                <td><?= htmlspecialchars($t['format'] ?? '') ?></td>
                <td><?= htmlspecialchars($t['isbn'] ?? '') ?></td>
                <td>
                    <?php if ((int)$t['total_copies'] === 0): ?>
                        <span class="muted">No copies yet</span>
                    <?php else: ?>
                        <span class="badge <?= $t['available_copies'] > 0 ? 'badge-available' : 'badge-out' ?>">
                            <?= (int)$t['available_copies'] ?> of <?= (int)$t['total_copies'] ?> available
                        </span>
                    <?php endif; ?>
                </td>
                <td><a href="/catalog/edit?id=<?= (int)$t['id'] ?>">Edit</a></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>