<?php
/** POST handler: attaches an uploaded file to a title. Called from Catalog's edit page. */
Auth::requireRole(['librarian']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['csrf_token'] ?? null)) {
    header('Location: /catalog');
    exit;
}

$bibId = (int) ($_POST['bib_id'] ?? 0);

if ($bibId && !empty($_FILES['file']['name'])) {
    if (!DigitalFile::isAllowedFilename($_FILES['file']['name'])) {
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'Only PDF and EPUB files are allowed.'];
    } elseif ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'Upload failed - the file may be too large for this server.'];
    } else {
        $fileId = DigitalFile::upload($bibId, $_FILES['file'], Auth::id());
        Audit::log(Auth::id(), 'digital_upload', 'digital_files', $fileId, null, [
            'bib_record_id' => $bibId,
            'filename' => $_FILES['file']['name'],
        ]);
        $_SESSION['flash'] = ['type' => 'success', 'text' => 'File uploaded.'];
    }
}

header('Location: /catalog/edit?id=' . $bibId);
exit;