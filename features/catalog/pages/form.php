<?php
/**
 * Add / edit a title. Same file handles both - edit mode is whenever
 * ?id= is present (true for both the GET that shows the form and the
 * POST that saves it, since the query string travels with either).
 */
$layout = 'staff';
Auth::requireRole(['librarian']);

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$editing = $id !== null;
$title = null;

if ($editing) {
    $title = Title::find($id);
    if (!$title) {
        header('Location: /catalog');
        exit;
    }
}

$errors = [];
$values = [
    'title'            => $title['title'] ?? '',
    'authors'          => $editing ? implode(', ', $title['authors']) : '',
    'isbn'             => $title['isbn'] ?? '',
    'publisher'        => $title['publisher'] ?? '',
    'publication_year' => $title['publication_year'] ?? '',
    'format'           => $title['format'] ?? '',
    'summary'          => $title['summary'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $values = [
            'title'            => trim($_POST['title'] ?? ''),
            'authors'          => trim($_POST['authors'] ?? ''),
            'isbn'             => trim($_POST['isbn'] ?? ''),
            'publisher'        => trim($_POST['publisher'] ?? ''),
            'publication_year' => trim($_POST['publication_year'] ?? ''),
            'format'           => trim($_POST['format'] ?? ''),
            'summary'          => trim($_POST['summary'] ?? ''),
        ];

        if ($values['title'] === '')   $errors[] = 'Title is required.';
        if ($values['authors'] === '') $errors[] = 'At least one author is required.';

        if (!$errors) {
            if ($editing) {
                Title::update($id, $title['edition_id'], $values);
                Audit::log(Auth::id(), 'catalog_update_title', 'bib_records', $id, null, $values);
            } else {
                $id = Title::create($values);
                Audit::log(Auth::id(), 'catalog_create_title', 'bib_records', $id, null, $values);
            }
            header('Location: /catalog/edit?id=' . $id . '&saved=1');
            exit;
        }
    }
}
?>
<h1><?= $editing ? 'Edit title' : 'Add title' ?></h1>

<?php foreach ($errors as $e): ?>
    <p class="error"><?= htmlspecialchars($e) ?></p>
<?php endforeach; ?>
<?php if (!$errors && ($_GET['saved'] ?? '') === '1'): ?>
    <p class="muted">Saved.</p>
<?php endif; ?>
<?php if ($flash = ($_SESSION['flash'] ?? null)): unset($_SESSION['flash']); ?>
    <p class="<?= $flash['type'] === 'error' ? 'error' : 'muted' ?>"><?= htmlspecialchars($flash['text']) ?></p>
<?php endif; ?>

<form method="post" action="<?= $editing ? '/catalog/edit?id=' . $id : '/catalog/new' ?>">
    <?= Csrf::field() ?>
    <label>Title
        <input type="text" name="title" required value="<?= htmlspecialchars($values['title']) ?>">
    </label>
    <label>Author(s) - separate multiple with commas
        <input type="text" name="authors" required value="<?= htmlspecialchars($values['authors']) ?>">
    </label>
    <label>ISBN
        <input type="text" name="isbn" value="<?= htmlspecialchars($values['isbn']) ?>">
    </label>
    <label>Publisher
        <input type="text" name="publisher" value="<?= htmlspecialchars($values['publisher']) ?>">
    </label>
    <label>Publication year
        <input type="number" name="publication_year" min="1400" max="2100" value="<?= htmlspecialchars((string) $values['publication_year']) ?>">
    </label>
    <label>Format
        <select name="format">
            <?php foreach (['' => '-- select --', 'Hardcover' => 'Hardcover', 'Paperback' => 'Paperback', 'E-book' => 'E-book'] as $val => $labelText): ?>
                <option value="<?= htmlspecialchars($val) ?>" <?= $values['format'] === $val ? 'selected' : '' ?>><?= htmlspecialchars($labelText) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Summary
        <textarea name="summary" rows="4"><?= htmlspecialchars($values['summary']) ?></textarea>
    </label>
    <button type="submit" class="btn btn-primary"><?= $editing ? 'Save title' : 'Add title' ?></button>
    <a href="/catalog" class="btn btn-secondary">Cancel</a>
</form>

<?php if ($editing): ?>
    <div class="section-heading"><h2>Copies</h2></div>
    <table>
        <thead><tr><th>Barcode</th><th>Material type</th><th>Collection</th><th>Location</th><th>Status</th></tr></thead>
        <tbody>
            <?php if (!$title['copies']): ?>
                <tr><td colspan="5" class="muted">No copies yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($title['copies'] as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['barcode']) ?></td>
                    <td><?= htmlspecialchars($c['material_type_name']) ?></td>
                    <td><?= htmlspecialchars($c['collection_name']) ?></td>
                    <td><?= htmlspecialchars($c['location_name'] ?? '') ?></td>
                    <td>
                        <?php $badge = $c['status'] === 'available' ? 'badge-available' : 'badge-out'; ?>
                        <span class="badge <?= $badge ?>"><?= htmlspecialchars(str_replace('_', ' ', $c['status'])) ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="section-heading"><h3>Add a copy</h3></div>
    <form method="post" action="/catalog/copy">
        <?= Csrf::field() ?>
        <input type="hidden" name="bib_id" value="<?= $id ?>">
        <input type="hidden" name="edition_id" value="<?= $title['edition_id'] ?>">
        <label>Barcode
            <input type="text" name="barcode" required>
        </label>
        <label>Material type
            <select name="material_type_id" required>
                <?php foreach (Title::materialTypes() as $mt): ?>
                    <option value="<?= (int) $mt['id'] ?>"><?= htmlspecialchars($mt['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Collection
            <select name="collection_id" required>
                <?php foreach (Title::collections() as $col): ?>
                    <option value="<?= (int) $col['id'] ?>"><?= htmlspecialchars($col['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Location (optional)
            <select name="location_id">
                <option value="">-- none --</option>
                <?php foreach (Title::locations() as $loc): ?>
                    <option value="<?= (int) $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Price (optional)
            <input type="number" name="price" step="0.01" min="0">
        </label>
        <button type="submit" class="btn btn-secondary">Add copy</button>
    </form>

    <div class="section-heading"><h2>Digital files</h2></div>
    <table>
        <thead><tr><th>Filename</th><th>Format</th><th>Size</th><th>Downloads</th><th></th></tr></thead>
        <tbody>
            <?php $digitalFiles = DigitalFile::forTitle($id); ?>
            <?php if (!$digitalFiles): ?>
                <tr><td colspan="5" class="muted">No digital files yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($digitalFiles as $df): ?>
                <tr>
                    <td><?= htmlspecialchars($df['filename']) ?></td>
                    <td><?= htmlspecialchars($df['file_format']) ?></td>
                    <td><?= round($df['file_size_bytes'] / 1048576, 1) ?> MB</td>
                    <td><?= (int) $df['download_count'] ?></td>
                    <td>
                        <form method="post" action="/digital/delete" style="display:inline;">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="id" value="<?= (int) $df['id'] ?>">
                            <input type="hidden" name="bib_id" value="<?= $id ?>">
                            <button type="submit" class="btn btn-ghost">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="section-heading"><h3>Upload a file</h3></div>
    <form method="post" action="/digital/upload" enctype="multipart/form-data">
        <?= Csrf::field() ?>
        <input type="hidden" name="bib_id" value="<?= $id ?>">
        <label>PDF or EPUB file
            <input type="file" name="file" accept=".pdf,.epub" required>
        </label>
        <button type="submit" class="btn btn-secondary">Upload</button>
    </form>
<?php endif; ?>
