<?php
/**
 * Adds a physical copy (item) to an existing title's edition.
 * Always redirects back to that title's edit page.
 */
Auth::requireRole(['librarian']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['csrf_token'] ?? null)) {
    header('Location: /catalog');
    exit;
}

$bibId = (int) ($_POST['bib_id'] ?? 0);
$editionId = (int) ($_POST['edition_id'] ?? 0);
$barcode = trim($_POST['barcode'] ?? '');

if ($bibId && $editionId && $barcode !== '') {
    $itemId = Title::addCopy($editionId, [
        'barcode'          => $barcode,
        'material_type_id' => $_POST['material_type_id'] ?? null,
        'collection_id'    => $_POST['collection_id'] ?? null,
        'location_id'      => $_POST['location_id'] ?? null,
        'price'            => $_POST['price'] ?? null,
    ]);
    Audit::log(Auth::id(), 'catalog_add_copy', 'items', $itemId, null, [
        'barcode'    => $barcode,
        'edition_id' => $editionId,
    ]);
}

header('Location: /catalog/edit?id=' . $bibId);
exit;