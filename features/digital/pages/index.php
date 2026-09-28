<?php
/**
 * Digital Library - public browse page, no login required to browse
 * (downloading a file does require login - see download.php).
 */
$q = trim($_GET['q'] ?? '');
$files = DigitalFile::browse($q);
?>
<h1>Digital Library</h1>
<p class="muted">Downloadable titles you can keep.</p>

<form class="search-bar digital-search" method="get" action="/digital">
    <input type="text" name="q" placeholder="Search by title or author" value="<?= htmlspecialchars($q) ?>">
    <button type="submit" class="btn btn-primary">Search</button>
</form>

<div class="book-grid digital-grid">
    <?php if (!$files): ?>
        <p class="muted">No digital titles yet.</p>
    <?php endif; ?>
    <?php foreach ($files as $f): ?>
        <div class="book-card">
            <div class="book-cover"><?= htmlspecialchars($f['title']) ?></div>
            <div class="book-title"><?= htmlspecialchars($f['title']) ?></div>
            <div class="book-meta"><?= htmlspecialchars($f['authors'] ?? '') ?></div>
            <div class="book-meta"><?= htmlspecialchars($f['file_format']) ?> &middot; <?= round($f['file_size_bytes'] / 1048576, 1) ?> MB</div>
            <?php if (Auth::check()): ?>
                <a href="/digital/download?id=<?= (int) $f['file_id'] ?>" class="btn btn-primary">Download</a>
            <?php else: ?>
                <a href="/login" class="btn btn-secondary">Log in to download</a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>