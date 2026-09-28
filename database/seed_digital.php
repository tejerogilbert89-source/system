<?php
/**
 * Optional: adds a few sample titles with real (tiny, valid) placeholder
 * PDFs attached, so Digital Library has something to browse/download
 * while testing. Safe to run more than once - it looks for its own
 * sample titles by name before creating them.
 *
 * Requires Digital Library to already be installed: the digital_files
 * table, features/digital/DigitalFile.php, and DIGITAL_STORAGE_PATH in
 * config/config.php. Run database/seed.php first if you haven't.
 *
 * From the command line:  php database/seed_digital.php
 */
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../core/autoload.php';

if (!class_exists('DigitalFile')) {
    echo "Digital Library isn't installed yet (DigitalFile class not found) - nothing to seed.\n";
    exit(1);
}
if (!defined('DIGITAL_STORAGE_PATH')) {
    echo "DIGITAL_STORAGE_PATH isn't defined in config/config.php - add it before seeding.\n";
    exit(1);
}

$pdo = Database::connection();

try {
    $pdo->query('SELECT 1 FROM digital_files LIMIT 1');
} catch (PDOException $e) {
    echo "The digital_files table doesn't exist yet - run its CREATE TABLE statement from schema.sql first.\n";
    exit(1);
}

$adminId = $pdo->query("SELECT id FROM users WHERE id_number = 'ADMIN-0001'")->fetchColumn();
if (!$adminId) {
    echo "No admin account found - run database/seed.php first.\n";
    exit(1);
}

/** Builds a small, genuinely valid single-page PDF (no library needed). */
function makeSimplePdf(string $title): string
{
    $safe = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $title);

    $objects = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
    $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

    $stream = "BT /F1 20 Tf 72 700 Td ({$safe}) Tj ET\n"
        . "BT /F1 12 Tf 72 670 Td (Placeholder file seeded for testing the Digital Library feature.) Tj ET";
    $objects[5] = "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream";
    $objects[3] = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> '
        . '/MediaBox [0 0 612 792] /Contents 5 0 R >>';

    ksort($objects);

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $num => $body) {
        $offsets[$num] = strlen($pdf);
        $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $count = count($objects) + 1;
    $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
}

$samples = [
    [
        'title' => 'Fundamentals of Physics',
        'authors' => 'Halliday, David, Resnick, Robert',
        'isbn' => '',
        'publisher' => 'Wiley',
        'publication_year' => 2018,
        'format' => 'E-book',
        'summary' => 'Sample digital library entry seeded for testing.',
    ],
    [
        'title' => 'Philippine History and Government',
        'authors' => 'Agoncillo, Teodoro',
        'isbn' => '',
        'publisher' => '',
        'publication_year' => 1990,
        'format' => 'E-book',
        'summary' => 'Sample digital library entry seeded for testing.',
    ],
    [
        'title' => 'Basic Statistics for Students',
        'authors' => 'Bluman, Allan',
        'isbn' => '',
        'publisher' => '',
        'publication_year' => 2015,
        'format' => 'E-book',
        'summary' => 'Sample digital library entry seeded for testing.',
    ],
];

foreach ($samples as $sample) {
    $stmt = $pdo->prepare('SELECT id FROM bib_records WHERE title = ?');
    $stmt->execute([$sample['title']]);
    $bibId = $stmt->fetchColumn();

    if (!$bibId) {
        $bibId = Title::create($sample);
        echo "Created title: {$sample['title']}\n";
    } else {
        echo "Title already exists, reusing: {$sample['title']}\n";
    }

    $stmt = $pdo->prepare('SELECT id FROM digital_files WHERE bib_record_id = ?');
    $stmt->execute([$bibId]);
    if ($stmt->fetchColumn()) {
        echo "  Already has a digital file, skipped.\n";
        continue;
    }

    $filename = preg_replace('/[^a-z0-9]+/i', '-', strtolower($sample['title'])) . '.pdf';
    DigitalFile::seed((int) $bibId, $filename, makeSimplePdf($sample['title']), (int) $adminId);
    echo "  Added digital file: {$filename}\n";
}

echo "Digital library seeding complete.\n";
