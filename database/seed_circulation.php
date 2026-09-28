<?php
/**
 * Optional: seeds Circulation with loans covering every state the
 * feature handles - active/on-time, renewed, overdue, returned on
 * time, and returned late (with an automatic fine) - spread across
 * patrons in every category, since loan periods/limits differ by
 * category. Good for demoing /circulation/loans without hand-testing
 * every state yourself.
 *
 * This is all-or-nothing: it checks for its own first sample title
 * and does nothing if it's already there, rather than trying to
 * partially re-run (the scenarios below are multi-step - checkout,
 * then renew/return - so re-running halfway through isn't safe).
 *
 * Requires database/seed.php to have been run first.
 *
 * From the command line:  php database/seed_circulation.php
 */
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../core/autoload.php';

$pdo = Database::connection();

$stmt = $pdo->prepare('SELECT id FROM bib_records WHERE title = ?');
$stmt->execute(['Data Structures and Algorithms']);
if ($stmt->fetchColumn()) {
    echo "Already seeded (found 'Data Structures and Algorithms') - nothing to do.\n";
    exit(0);
}

$materialIds = [];
foreach ($pdo->query('SELECT id, name FROM material_types')->fetchAll() as $row) {
    $materialIds[$row['name']] = $row['id'];
}
$collectionIds = [];
foreach ($pdo->query('SELECT id, name FROM collections')->fetchAll() as $row) {
    $collectionIds[$row['name']] = $row['id'];
}
$categoryIds = [];
foreach ($pdo->query('SELECT id, name FROM patron_categories')->fetchAll() as $row) {
    $categoryIds[$row['name']] = $row['id'];
}
$patronRoleId = $pdo->query("SELECT id FROM roles WHERE name = 'patron'")->fetchColumn();
$librarianId = $pdo->query("SELECT id FROM users WHERE id_number = 'LIBRARIAN-0001'")->fetchColumn();
$studentId = $pdo->query("SELECT id FROM users WHERE id_number = 'STUDENT-0001'")->fetchColumn();

if (!$patronRoleId || !$librarianId || !$studentId) {
    echo "Base accounts/roles not found - run database/seed.php first.\n";
    exit(1);
}

// --- One extra patron per remaining category, so every loan period/limit gets exercised ---
$newPatrons = [
    ['id_number' => 'GRAD-0001',    'full_name' => 'Liza Fernandez',  'category' => 'Graduate'],
    ['id_number' => 'FACULTY-0001', 'full_name' => 'Dr. Ramon Cruz',  'category' => 'Faculty'],
    ['id_number' => 'STAFF-0001',   'full_name' => 'Roberto Reyes',   'category' => 'Staff'],
];
$patronIds = ['Undergraduate' => $studentId];

foreach ($newPatrons as $p) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE id_number = ?');
    $stmt->execute([$p['id_number']]);
    $userId = $stmt->fetchColumn();

    if (!$userId) {
        $pdo->prepare(
            "INSERT INTO users (role_id, id_number, full_name, email, password_hash)
             VALUES (?, ?, ?, ?, ?)"
        )->execute([
            $patronRoleId,
            $p['id_number'],
            $p['full_name'],
            strtolower(str_replace(' ', '.', $p['full_name'])) . '@example.edu',
            password_hash('patron123', PASSWORD_ARGON2ID),
        ]);
        $userId = $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO patron_profiles (user_id, patron_category_id) VALUES (?, ?)')
            ->execute([$userId, $categoryIds[$p['category']]]);

        echo "Created patron: {$p['id_number']} ({$p['category']}), password patron123\n";
    }

    $patronIds[$p['category']] = $userId;
}

// --- Sample titles + copies to loan out ---
$titles = [
    'Data Structures and Algorithms' => ['copies' => 3, 'material' => 'Book', 'collection' => 'General Circulation'],
    'Organic Chemistry'              => ['copies' => 2, 'material' => 'Book', 'collection' => 'General Circulation'],
    'Educational Psychology'         => ['copies' => 1, 'material' => 'Book', 'collection' => 'General Circulation'],
];
$barcodes = []; // title => [barcode, barcode, ...]
$barcodeSeq = 1;

foreach ($titles as $titleName => $info) {
    $bibId = Title::create([
        'title' => $titleName,
        'authors' => 'Sample Author',
        'isbn' => '',
        'publisher' => '',
        'publication_year' => 2020,
        'format' => 'Paperback',
        'summary' => 'Sample circulation entry seeded for testing.',
    ]);
    $title = Title::find($bibId);
    echo "Created title: {$titleName}\n";

    $barcodes[$titleName] = [];
    for ($i = 0; $i < $info['copies']; $i++) {
        $barcode = 'CIRC-' . str_pad((string) $barcodeSeq++, 4, '0', STR_PAD_LEFT);
        Title::addCopy($title['edition_id'], [
            'barcode' => $barcode,
            'material_type_id' => $materialIds[$info['material']],
            'collection_id' => $collectionIds[$info['collection']],
            'location_id' => null,
            'price' => null,
        ]);
        $barcodes[$titleName][] = $barcode;
    }
}

/** Pushes a loan's dates into the past directly - only ever done here, in a seed script. */
function backdateLoan(PDO $pdo, int $loanId, int $daysAgoCheckedOut, int $daysAgoDue): void
{
    $pdo->prepare(
        'UPDATE loans SET checked_out_at = DATE_SUB(NOW(), INTERVAL ? DAY), due_at = DATE_SUB(NOW(), INTERVAL ? DAY) WHERE id = ?'
    )->execute([$daysAgoCheckedOut, $daysAgoDue, $loanId]);
}

// 1) Graduate patron, active loan, on time.
$r = Loan::checkout($barcodes['Data Structures and Algorithms'][0], (int) $patronIds['Graduate'], (int) $librarianId);
echo $r['ok'] ? "Checked out {$barcodes['Data Structures and Algorithms'][0]} to GRAD-0001 (active, on time).\n" : "  Failed: {$r['error']}\n";

// 2) Faculty patron, checked out then renewed once - still active, on time, renewal_count=1.
$r = Loan::checkout($barcodes['Organic Chemistry'][0], (int) $patronIds['Faculty'], (int) $librarianId);
if ($r['ok']) {
    Loan::renew($r['loan_id']);
    echo "Checked out {$barcodes['Organic Chemistry'][0]} to FACULTY-0001 and renewed it (active, renewed).\n";
} else {
    echo "  Failed: {$r['error']}\n";
}

// 3) Undergraduate patron, active loan backdated to overdue.
$r = Loan::checkout($barcodes['Data Structures and Algorithms'][1], (int) $patronIds['Undergraduate'], (int) $librarianId);
if ($r['ok']) {
    backdateLoan($pdo, $r['loan_id'], 10, 3); // checked out 10 days ago, was due 3 days ago
    echo "Checked out {$barcodes['Data Structures and Algorithms'][1]} to STUDENT-0001, backdated to overdue.\n";
} else {
    echo "  Failed: {$r['error']}\n";
}

// 4) Staff patron, checked out and returned the same day - returned, no fine.
$r = Loan::checkout($barcodes['Educational Psychology'][0], (int) $patronIds['Staff'], (int) $librarianId);
if ($r['ok']) {
    $result = Loan::returnItem($r['loan_id'], (int) $librarianId);
    echo "Checked out and returned {$barcodes['Educational Psychology'][0]} for STAFF-0001 (returned, no fine).\n";
} else {
    echo "  Failed: {$r['error']}\n";
}

// 5) Undergraduate patron, backdated overdue, then returned late - exercises the automatic fine.
$r = Loan::checkout($barcodes['Organic Chemistry'][1], (int) $patronIds['Undergraduate'], (int) $librarianId);
if ($r['ok']) {
    backdateLoan($pdo, $r['loan_id'], 14, 5); // checked out 14 days ago, was due 5 days ago
    $result = Loan::returnItem($r['loan_id'], (int) $librarianId);
    echo "Checked out, backdated, and returned {$barcodes['Organic Chemistry'][1]} for STUDENT-0001";
    echo $result['fine'] > 0 ? " (returned late, fine of {$result['fine']} charged).\n" : " (returned, no fine - unexpected).\n";
} else {
    echo "  Failed: {$r['error']}\n";
}

echo "Circulation seeding complete.\n";
