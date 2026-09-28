<?php
/**
 * OPAC search results - public, no login required.
 */
$q = trim($_GET['q'] ?? '');
$results = $q !== '' ? Title::search($q) : [];
?>
<form class="search-bar" method="get" action="/opac/search">
    <input type="text" name="q" placeholder="Search by title, author, or ISBN" value="<?= htmlspecialchars($q) ?>">
    <button type="submit" class="btn btn-primary">Search</button>
</form>

<?php if ($q === ''): ?>
    <p class="muted">Enter a search term above.</p>
<?php else: ?>
    <p class="muted"><?= count($results) ?> result(s) for "<?= htmlspecialchars($q) ?>"</p>

    <table>
        <thead><tr><th>Title</th><th>Author</th><th>Format</th><th>Availability</th></tr></thead>
        <tbody>
            <?php if (!$results): ?>
                <tr><td colspan="4" class="muted">No matches found.</td></tr>
            <?php endif; ?>
            <?php foreach ($results as $r): ?>
                <tr>
                    <td><a href="/opac/title?id=<?= (int) $r['id'] ?>"><?= htmlspecialchars($r['title']) ?></a></td>
                    <td><?= htmlspecialchars($r['authors'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['format'] ?? '') ?></td>
                    <td>
                        <?php if ((int) $r['total_copies'] === 0): ?>
                            <span class="muted">No copies</span>
                        <?php else: ?>
                            <span class="badge <?= $r['available_copies'] > 0 ? 'badge-available' : 'badge-out' ?>">
                                <?= (int) $r['available_copies'] ?> of <?= (int) $r['total_copies'] ?> available
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>