<?php
/**
 * Streams a digital file to a logged-in user and records the download.
 * Bypasses the normal page layout entirely - this response is the file.
 */
Auth::requireLogin();

$id = (int) ($_GET['id'] ?? 0);
$file = $id ? DigitalFile::find($id) : null;

if (!$file) {
    http_response_code(404);
    die('File not found.');
}

$fullPath = DIGITAL_STORAGE_PATH . '/' . $file['stored_path'];
if (!is_file($fullPath)) {
    http_response_code(404);
    die('File not found on disk.');
}

DigitalFile::recordDownload($id);
Audit::log(Auth::id(), 'digital_download', 'digital_files', $id, null, ['filename' => $file['filename']]);

// Discard the layout's output buffer so we can send raw bytes instead of an HTML page.
while (ob_get_level() > 0) {
    ob_end_clean();
}

$mime = $file['file_format'] === 'PDF' ? 'application/pdf' : 'application/epub+zip';
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($file['filename']) . '"');
header('Content-Length: ' . $file['file_size_bytes']);
readfile($fullPath);
exit;