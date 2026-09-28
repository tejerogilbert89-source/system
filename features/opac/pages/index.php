<?php
/**
 * OPAC homepage - the public catalog, no login required.
 */
$recentTitles = Title::recentlyAdded(6);
?>
<div class="opac-hero">
    <h1>Find your next book.</h1>

    <form class="search-bar" method="get" action="/opac/search">
        <input type="text" name="q" placeholder="Search by title, author, or subject">
        <button type="submit" class="btn btn-primary">Search</button>
    </form>

    <div class="browse-links">
        <a href="#">Fiction</a>
        <a href="#">Non-Fiction</a>
        <a href="#">Reference</a>
        <a href="#">Periodicals</a>
        <a href="#">Thesis &amp; Dissertations</a>
        <a href="#">AV Materials</a>
    </div>
</div>

<div class="section-heading">
    <h2>Recently added</h2>
    <a href="#">View all</a>
</div>

<div class="book-grid">
    <?php if (!$recentTitles): ?>
        <p class="muted">No titles in the catalog yet.</p>
    <?php endif; ?>
    <?php foreach ($recentTitles as $book): ?>
        <a href="/opac/title?id=<?= (int) $book['id'] ?>" class="book-card">
            <div class="book-cover"><?= htmlspecialchars($book['title']) ?></div>
            <div class="book-title"><?= htmlspecialchars($book['title']) ?></div>
            <div class="book-meta"><?= htmlspecialchars($book['authors'] ?? '') ?></div>
        </a>
    <?php endforeach; ?>
</div>