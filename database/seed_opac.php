<?php
/**
 * Optional: seeds the OPAC homepage/search with a handful of realistic
 * sample titles and physical copies, spread across the real collections
 * and material types, so browsing/searching has something to show.
 * Checks out two copies to the seeded student patron so availability
 * badges show a realistic mix (not everything sitting at "available").
 *
 * Safe to run more than once - it looks for its own sample titles by
 * name first; copies/checkouts are only added the first time a title
 * is created (re-running won't duplicate copies).
 *
 * Requires database/seed.php to have been run first (for material
 * types, collections, and the LIBRARIAN-0001 / STUDENT-0001 accounts).
 *
 * From the command line:  php database/seed_opac.php
 */
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../core/autoload.php';

$pdo = Database::connection();

$materialIds = [];
foreach ($pdo->query('SELECT id, name FROM material_types')->fetchAll() as $row) {
    $materialIds[$row['name']] = $row['id'];
}
$collectionIds = [];
foreach ($pdo->query('SELECT id, name FROM collections')->fetchAll() as $row) {
    $collectionIds[$row['name']] = $row['id'];
}

$studentId = $pdo->query("SELECT id FROM users WHERE id_number = 'STUDENT-0001'")->fetchColumn();
$librarianId = $pdo->query("SELECT id FROM users WHERE id_number = 'LIBRARIAN-0001'")->fetchColumn();

if (!$studentId || !$librarianId) {
    echo "STUDENT-0001 and/or LIBRARIAN-0001 accounts not found - run database/seed.php first.\n";
    exit(1);
}

// Titles that get one copy checked out to STUDENT-0001, purely so OPAC
// shows a mix of "available" and "checked out" instead of everything open.
$toCheckout = ['Noli Me Tangere', "Merriam-Webster's Collegiate Dictionary"];

$samples = [
    [
        'title' => 'Noli Me Tangere',
        'authors' => 'Rizal, Jose',
        'publisher' => '',
        'publication_year' => 1887,
        'format' => 'Paperback',
        'material' => 'Book',
        'collection' => 'Filipiniana',
        'copies' => 2,
    ],
    [
        'title' => 'El Filibusterismo',
        'authors' => 'Rizal, Jose',
        'publisher' => '',
        'publication_year' => 1891,
        'format' => 'Paperback',
        'material' => 'Book',
        'collection' => 'Filipiniana',
        'copies' => 2,
    ],
    [
        'title' => 'Introduction to Algorithms',
        'authors' => 'Cormen, Thomas H., Leiserson, Charles E., Rivest, Ronald L.',
        'publisher' => 'MIT Press',
        'publication_year' => 2009,
        'format' => 'Hardcover',
        'material' => 'Book',
        'collection' => 'General Circulation',
        'copies' => 2,
    ],
    [
        'title' => "Merriam-Webster's Collegiate Dictionary",
        'authors' => 'Merriam-Webster',
        'publisher' => 'Merriam-Webster',
        'publication_year' => 2020,
        'format' => 'Hardcover',
        'material' => 'Book',
        'collection' => 'Reference',
        'copies' => 1,
    ],
    [
        'title' => 'Statistics for Business and Economics',
        'authors' => 'Anderson, David R., Sweeney, Dennis J.',
        'publisher' => 'Cengage',
        'publication_year' => 2019,
        'format' => 'Paperback',
        'material' => 'Book',
        'collection' => 'Course Reserve',
        'copies' => 1,
    ],
    [
        'title' => 'A Study on Barangay Governance in Rural Communities',
        'authors' => 'Santos, Maria C.',
        'publisher' => '',
        'publication_year' => 2022,
        'format' => '',
        'material' => 'Thesis',
        'collection' => 'Thesis',
        'copies' => 1,
    ],
    [
        'title' => 'Introduction to Programming Concepts (Lecture Series)',
        'authors' => 'Dela Cruz, Juan',
        'publisher' => '',
        'publication_year' => 2021,
        'format' => '',
        'material' => 'AV Material',
        'collection' => 'General Circulation',
        'copies' => 1,
    ],
];

$barcodeSeq = 1;

foreach ($samples as $sample) {
    $stmt = $pdo->prepare('SELECT id FROM bib_records WHERE title = ?');
    $stmt->execute([$sample['title']]);
    $bibId = $stmt->fetchColumn();

    if ($bibId) {
        echo "Title already exists, skipped: {$sample['title']}\n";
        continue;
    }

    $bibId = Title::create([
        'title' => $sample['title'],
        'authors' => $sample['authors'],
        'isbn' => '',
        'publisher' => $sample['publisher'],
        'publication_year' => $sample['publication_year'],
        'format' => $sample['format'],
        'summary' => 'Sample OPAC entry seeded for testing.',
    ]);
    echo "Created title: {$sample['title']}\n";

    if (!isset($materialIds[$sample['material']]) || !isset($collectionIds[$sample['collection']])) {
        echo "  Skipped copies - material type or collection not found (was database/seed.php run?).\n";
        continue;
    }

    $title = Title::find($bibId);
    $firstBarcode = null;

    for ($i = 0; $i < $sample['copies']; $i++) {
        $barcode = 'SEED-' . str_pad((string) $barcodeSeq++, 4, '0', STR_PAD_LEFT);
        Title::addCopy($title['edition_id'], [
            'barcode' => $barcode,
            'material_type_id' => $materialIds[$sample['material']],
            'collection_id' => $collectionIds[$sample['collection']],
            'location_id' => null,
            'price' => null,
        ]);
        $firstBarcode ??= $barcode;
        echo "  Added copy: {$barcode}\n";
    }

    if ($firstBarcode && in_array($sample['title'], $toCheckout, true)) {
        $result = Loan::checkout($firstBarcode, (int) $studentId, (int) $librarianId);
        echo $result['ok']
            ? "  Checked out {$firstBarcode} to STUDENT-0001 for demo variety.\n"
            : "  Could not seed a checkout: {$result['error']}\n";
    }
}

echo "OPAC seeding complete.\n";
