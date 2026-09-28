<?php
/**
 * Run this once after importing schema.sql to add starting data.
 * From the command line:  php database/seed.php
 * This script is safe to run more than once (it will not create duplicates).
 */
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../core/autoload.php';

$pdo = Database::connection();

// Roles
// Roles - kept deliberately simple:
//   admin     - manages staff accounts + system settings, plus everything librarian can do
//   librarian - day-to-day library work (circulation, catalog, patrons, holds, fines, inventory, reports)
//   patron    - students and faculty alike. Whether they're a student or faculty member
//               is tracked separately, via patron_categories - that's about borrowing
//               limits, not screen access, so it isn't a separate role.
foreach (['admin', 'librarian', 'patron'] as $role) {
    $pdo->prepare("INSERT IGNORE INTO roles (name) VALUES (?)")->execute([$role]);
}

// Patron categories (used later to decide loan periods, fine rates, etc.)
foreach (['Undergraduate', 'Graduate', 'Faculty', 'Staff'] as $cat) {
    $pdo->prepare("INSERT IGNORE INTO patron_categories (name) VALUES (?)")->execute([$cat]);
}

// Material types
$materials = [
    ['Book', 1],
    ['Thesis', 0],
    ['Periodical', 0],
    ['AV Material', 1],
];
foreach ($materials as [$name, $circulating]) {
    $pdo->prepare("INSERT IGNORE INTO material_types (name, is_circulating) VALUES (?, ?)")
        ->execute([$name, $circulating]);
}

// Collections
foreach (['General Circulation', 'Reference', 'Course Reserve', 'Filipiniana', 'Thesis'] as $c) {
    $pdo->prepare("INSERT IGNORE INTO collections (name) VALUES (?)")->execute([$c]);
}

// Circulation policies - one row per patron category x material type combo.
// Loan::checkout() looks up a row here before allowing any checkout at all,
// so every combination needs one - even non-circulating material types, in
// case that ever changes.
$policies = [
    // category         material        loan_days  renewals  max_loans  fine/day  grace_days
    ['Undergraduate',  'Book',         7,  1, 3,  5.00, 0],
    ['Undergraduate',  'AV Material',  3,  1, 1,  5.00, 0],
    ['Undergraduate',  'Thesis',       7,  0, 1,  5.00, 0],
    ['Undergraduate',  'Periodical',   3,  0, 2,  5.00, 0],

    ['Graduate',       'Book',         14, 2, 5,  5.00, 1],
    ['Graduate',       'AV Material',  7,  1, 2,  5.00, 1],
    ['Graduate',       'Thesis',       14, 1, 2,  5.00, 1],
    ['Graduate',       'Periodical',   7,  0, 3,  5.00, 1],

    ['Faculty',        'Book',         30, 3, 10, 5.00, 2],
    ['Faculty',        'AV Material',  14, 2, 3,  5.00, 2],
    ['Faculty',        'Thesis',       30, 2, 5,  5.00, 2],
    ['Faculty',        'Periodical',   14, 1, 5,  5.00, 2],

    ['Staff',          'Book',         14, 2, 5,  5.00, 1],
    ['Staff',          'AV Material',  7,  1, 2,  5.00, 1],
    ['Staff',          'Thesis',       14, 1, 2,  5.00, 1],
    ['Staff',          'Periodical',   7,  0, 3,  5.00, 1],
];

foreach ($policies as [$category, $material, $days, $renewals, $maxLoans, $fine, $grace]) {
    $pdo->prepare(
        "INSERT IGNORE INTO circulation_policies
            (patron_category_id, material_type_id, loan_period_days, max_renewals, max_concurrent_loans, fine_rate_per_day, grace_period_days)
         SELECT pc.id, mt.id, ?, ?, ?, ?, ?
         FROM patron_categories pc, material_types mt
         WHERE pc.name = ? AND mt.name = ?"
    )->execute([$days, $renewals, $maxLoans, $fine, $grace, $category, $material]);
}
echo "Circulation policies seeded (" . count($policies) . " category/material combinations).\n";

// One admin account so you can log in
$adminRoleId = $pdo->query("SELECT id FROM roles WHERE name = 'admin'")->fetchColumn();

$check = $pdo->prepare("SELECT id FROM users WHERE id_number = ?");
$check->execute(['ADMIN-0001']);

if (!$check->fetch()) {
    $pdo->prepare(
        "INSERT INTO users (role_id, id_number, full_name, email, password_hash)
         VALUES (?, ?, ?, ?, ?)"
    )->execute([
        $adminRoleId,
        'ADMIN-0001',
        'System Administrator',
        'admin@example.edu',
        password_hash('admin123', PASSWORD_ARGON2ID),
    ]);
    echo "Admin account created.\n";
    echo "  ID Number: ADMIN-0001\n";
    echo "  Password:  admin123\n";
} else {
    echo "Admin account already exists, skipped.\n";
}

// One librarian account, so you can test that librarians can reach staff
// pages (circulation, catalog...) but NOT admin-only ones, once those exist.
$librarianRoleId = $pdo->query("SELECT id FROM roles WHERE name = 'librarian'")->fetchColumn();

$check->execute(['LIBRARIAN-0001']);
if (!$check->fetch()) {
    $pdo->prepare(
        "INSERT INTO users (role_id, id_number, full_name, email, password_hash)
         VALUES (?, ?, ?, ?, ?)"
    )->execute([
        $librarianRoleId,
        'LIBRARIAN-0001',
        'Maria Santos',
        'librarian@example.edu',
        password_hash('librarian123', PASSWORD_ARGON2ID),
    ]);
    echo "Librarian account created.\n";
    echo "  ID Number: LIBRARIAN-0001\n";
    echo "  Password:  librarian123\n";
} else {
    echo "Librarian account already exists, skipped.\n";
}

// One patron account, so you can test that patrons get blocked from staff
// pages (should see "Access denied", not the dashboard).
$patronRoleId = $pdo->query("SELECT id FROM roles WHERE name = 'patron'")->fetchColumn();
$undergradCategoryId = $pdo->query("SELECT id FROM patron_categories WHERE name = 'Undergraduate'")->fetchColumn();

$check->execute(['STUDENT-0001']);
if (!$check->fetch()) {
    $pdo->prepare(
        "INSERT INTO users (role_id, id_number, full_name, email, password_hash)
         VALUES (?, ?, ?, ?, ?)"
    )->execute([
        $patronRoleId,
        'STUDENT-0001',
        'Juan dela Cruz',
        'student@example.edu',
        password_hash('student123', PASSWORD_ARGON2ID),
    ]);
    $newPatronId = $pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO patron_profiles (user_id, patron_category_id) VALUES (?, ?)"
    )->execute([$newPatronId, $undergradCategoryId]);

    echo "Patron account created.\n";
    echo "  ID Number: STUDENT-0001\n";
    echo "  Password:  student123\n";
} else {
    echo "Patron account already exists, skipped.\n";
}

echo "Seeding complete.\n";