<?php
/**
 * OPAC title detail - public, no login required.
 */
$id = (int) ($_GET['id'] ?? 0);
$title = $id ? Title::find($id) : null;

if (!$title) {
    http_response_code(404);
    ?>
    <p>Title not found.</p>
    <a href="/">&larr; Back to catalog</a>
    <?php
    return;
}
?>
<a href="/opac/search" onclick="if (window.history.length > 1) { history.back(); return false; }">&larr; Back</a>

<h1><?= htmlspecialchars($title['title']) ?></h1>
<p class="muted"><?= htmlspecialchars(implode(', ', $title['authors'])) ?></p>

<p>
    <?php if ($title['publisher']): ?><?= htmlspecialchars($title['publisher']) ?><?php endif; ?>
    <?php if ($title['publication_year']): ?> &middot; <?= htmlspecialchars((string) $title['publication_year']) ?><?php endif; ?>
    <?php if ($title['format']): ?> &middot; <?= htmlspecialchars($title['format']) ?><?php endif; ?>
    <?php if ($title['isbn']): ?> &middot; ISBN <?= htmlspecialchars($title['isbn']) ?><?php endif; ?>
</p>

<?php if ($title['summary']): ?>
    <p><?= nl2br(htmlspecialchars($title['summary'])) ?></p>
<?php endif; ?>

<?php $digitalFiles = DigitalFile::forTitle($id); ?>
<?php if ($digitalFiles): ?>
    <div class="section-heading"><h2>Digital copies</h2></div>
    <table>
        <thead><tr><th>Format</th><th>Size</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($digitalFiles as $df): ?>
                <tr>
                    <td><?= htmlspecialchars($df['file_format']) ?></td>
                    <td><?= round($df['file_size_bytes'] / 1048576, 1) ?> MB</td>
                    <td>
                        <?php if (Auth::check()): ?>
                            <a href="/digital/download?id=<?= (int) $df['id'] ?>" class="btn btn-primary">Download</a>
                        <?php else: ?>
                            <a href="/login" class="btn btn-secondary">Log in to download</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<div class="section-heading"><h2>Copies</h2></div>
<table>
    <thead><tr><th>Collection</th><th>Location</th><th>Status</th></tr></thead>
    <tbody>
        <?php if (!$title['copies']): ?>
            <tr><td colspan="3" class="muted">No copies in the collection yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($title['copies'] as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['collection_name']) ?></td>
                <td><?= htmlspecialchars($c['location_name'] ?? 'Unassigned') ?></td>
                <td>
                    <span class="badge <?= $c['status'] === 'available' ? 'badge-available' : 'badge-out' ?>">
                        <?= htmlspecialchars($c['status'] === 'available' ? 'Available' : 'Checked out') ?>
                    </span>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>