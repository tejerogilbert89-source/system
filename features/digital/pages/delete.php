<?php
/** POST handler: removes a digital file. Called from Catalog's edit page. */
Auth::requireRole(['librarian']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['csrf_token'] ?? null)) {
    header('Location: /catalog');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$bibId = (int) ($_POST['bib_id'] ?? 0);

if ($id) {
    DigitalFile::delete($id);
    Audit::log(Auth::id(), 'digital_delete', 'digital_files', $id, null, null);
    $_SESSION['flash'] = ['type' => 'success', 'text' => 'File removed.'];
}

header('Location: /catalog/edit?id=' . $bibId);
exit;