<?php
/**
 * Circulation domain logic: "who has it right now, and until when?"
 *
 * Loan status only ever moves active -> returned here; "overdue" is not
 * a stored transition (no scheduled job exists to flip it), it's just
 * due_at < now on an active loan, checked wherever it matters.
 *
 * Other features must not query loans/items/circulation_policies
 * directly - call these static methods instead (see AI_CONTEXT.md).
 */
class Loan
{
    /** Look up a patron (role=patron only) by their id_number for the checkout screen. */
    public static function findPatronByIdNumber(string $idNumber): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT u.id, u.id_number, u.full_name, pp.patron_category_id, pc.name AS category_name
             FROM users u
             JOIN roles r ON r.id = u.role_id AND r.name = 'patron'
             JOIN patron_profiles pp ON pp.user_id = u.id
             JOIN patron_categories pc ON pc.id = pp.patron_category_id
             WHERE u.id_number = ?
             LIMIT 1"
        );
        $stmt->execute([$idNumber]);
        $patron = $stmt->fetch();
        return $patron ?: null;
    }

    /** A patron's current active loans, for the checkout screen's "on loan now" list. */
    public static function patronActiveLoans(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT l.id, l.due_at, br.title, i.barcode
             FROM loans l
             JOIN items i ON i.id = l.item_id
             JOIN editions e ON e.id = i.edition_id
             JOIN bib_records br ON br.id = e.bib_record_id
             WHERE l.user_id = ? AND l.status = 'active'
             ORDER BY l.due_at"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /** Most recent checkouts across every patron, for the checkout screen's idle/default state. */
    public static function recentCheckouts(int $limit = 8): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT l.checked_out_at, u.full_name, u.id_number, br.title, i.barcode
             FROM loans l
             JOIN users u ON u.id = l.user_id
             JOIN items i ON i.id = l.item_id
             JOIN editions e ON e.id = i.edition_id
             JOIN bib_records br ON br.id = e.bib_record_id
             ORDER BY l.checked_out_at DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Every active loan, for the staff-wide returns/overdue screen. Empty $q returns all. */
    public static function activeLoans(string $q = ''): array
    {
        $like = '%' . $q . '%';
        $stmt = Database::connection()->prepare(
            "SELECT l.id, l.due_at, l.renewal_count, u.full_name, u.id_number, br.title, i.barcode
             FROM loans l
             JOIN users u ON u.id = l.user_id
             JOIN items i ON i.id = l.item_id
             JOIN editions e ON e.id = i.edition_id
             JOIN bib_records br ON br.id = e.bib_record_id
             WHERE l.status = 'active'
               AND (u.full_name LIKE ? OR u.id_number LIKE ? OR br.title LIKE ? OR i.barcode LIKE ?)
             ORDER BY l.due_at
             LIMIT 300"
        );
        $stmt->execute([$like, $like, $like, $like]);
        return $stmt->fetchAll();
    }

    /** Checks a barcode out to a patron. Returns ['ok'=>bool, 'error'?, 'loan_id'?, 'title'?, 'due_at'?]. */
    public static function checkout(string $barcode, int $patronUserId, int $staffUserId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            "SELECT i.id, i.status, i.material_type_id, mt.name AS material_type_name, mt.is_circulating, br.title
             FROM items i
             JOIN material_types mt ON mt.id = i.material_type_id
             JOIN editions e ON e.id = i.edition_id
             JOIN bib_records br ON br.id = e.bib_record_id
             WHERE i.barcode = ?
             LIMIT 1"
        );
        $stmt->execute([$barcode]);
        $item = $stmt->fetch();

        if (!$item) {
            return ['ok' => false, 'error' => 'No item found with barcode "' . $barcode . '".'];
        }
        if ($item['status'] !== 'available') {
            return ['ok' => false, 'error' => '"' . $item['title'] . '" is not available (' . $item['status'] . ').'];
        }
        if (!$item['is_circulating']) {
            return ['ok' => false, 'error' => $item['material_type_name'] . ' items are reference-only and cannot be checked out.'];
        }

        $stmt = $pdo->prepare('SELECT patron_category_id FROM patron_profiles WHERE user_id = ?');
        $stmt->execute([$patronUserId]);
        $categoryId = (int) $stmt->fetchColumn();

        $policy = self::policyFor($categoryId, (int) $item['material_type_id']);
        if (!$policy) {
            return ['ok' => false, 'error' => 'No circulation policy is set up for this patron/material combination.'];
        }

        if (self::activeLoanCount($patronUserId) >= $policy['max_concurrent_loans']) {
            return ['ok' => false, 'error' => 'This patron has reached their loan limit (' . $policy['max_concurrent_loans'] . ').'];
        }

        $dueAt = (new DateTime())->modify('+' . $policy['loan_period_days'] . ' days')->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare('INSERT INTO loans (item_id, user_id, checked_out_by, due_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$item['id'], $patronUserId, $staffUserId, $dueAt]);
        $loanId = (int) $pdo->lastInsertId();

        $pdo->prepare("UPDATE items SET status = 'checked_out' WHERE id = ?")->execute([$item['id']]);

        return ['ok' => true, 'loan_id' => $loanId, 'title' => $item['title'], 'due_at' => $dueAt];
    }

    /** Returns a loan by id. Charges an overdue fine automatically if it's late. */
    public static function returnItem(int $loanId, int $staffUserId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            "SELECT l.id, l.user_id, l.due_at, l.item_id, i.material_type_id, pp.patron_category_id, br.title
             FROM loans l
             JOIN items i ON i.id = l.item_id
             JOIN editions e ON e.id = i.edition_id
             JOIN bib_records br ON br.id = e.bib_record_id
             JOIN patron_profiles pp ON pp.user_id = l.user_id
             WHERE l.id = ? AND l.status = 'active'
             LIMIT 1"
        );
        $stmt->execute([$loanId]);
        $loan = $stmt->fetch();

        if (!$loan) {
            return ['ok' => false, 'error' => 'That loan is not active (already returned?).'];
        }

        $pdo->prepare("UPDATE loans SET returned_at = NOW(), returned_to = ?, status = 'returned' WHERE id = ?")
            ->execute([$staffUserId, $loanId]);
        $pdo->prepare("UPDATE items SET status = 'available' WHERE id = ?")->execute([$loan['item_id']]);

        $fine = 0.0;
        $due = new DateTime($loan['due_at']);
        $now = new DateTime();

        if ($now > $due) {
            $daysLate = (int) ceil(($now->getTimestamp() - $due->getTimestamp()) / 86400);
            $policy = self::policyFor((int) $loan['patron_category_id'], (int) $loan['material_type_id']);
            $billableDays = $policy ? max(0, $daysLate - $policy['grace_period_days']) : 0;

            if ($billableDays > 0) {
                $fine = round($billableDays * $policy['fine_rate_per_day'], 2);
                $pdo->prepare(
                    "INSERT INTO account_transactions (user_id, loan_id, type, amount, description, created_by)
                     VALUES (?, ?, 'charge', ?, ?, ?)"
                )->execute([
                    $loan['user_id'],
                    $loanId,
                    $fine,
                    "Overdue fine: {$billableDays} day(s) late returning \"{$loan['title']}\"",
                    $staffUserId,
                ]);
            }
        }

        return ['ok' => true, 'title' => $loan['title'], 'fine' => $fine];
    }

    /** Renews a loan by id, extending due_at from its current due date. */
    public static function renew(int $loanId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            "SELECT l.due_at, l.renewal_count, i.material_type_id, pp.patron_category_id
             FROM loans l
             JOIN items i ON i.id = l.item_id
             JOIN patron_profiles pp ON pp.user_id = l.user_id
             WHERE l.id = ? AND l.status = 'active'
             LIMIT 1"
        );
        $stmt->execute([$loanId]);
        $loan = $stmt->fetch();

        if (!$loan) {
            return ['ok' => false, 'error' => 'That loan is not active.'];
        }
        if (new DateTime($loan['due_at']) < new DateTime()) {
            return ['ok' => false, 'error' => 'This loan is overdue - return it instead of renewing.'];
        }

        $policy = self::policyFor((int) $loan['patron_category_id'], (int) $loan['material_type_id']);
        if (!$policy) {
            return ['ok' => false, 'error' => 'No circulation policy found for this loan.'];
        }
        if ($loan['renewal_count'] >= $policy['max_renewals']) {
            return ['ok' => false, 'error' => 'Maximum renewals (' . $policy['max_renewals'] . ') already reached.'];
        }

        $newDue = (new DateTime($loan['due_at']))->modify('+' . $policy['loan_period_days'] . ' days')->format('Y-m-d H:i:s');
        $pdo->prepare('UPDATE loans SET due_at = ?, renewal_count = renewal_count + 1 WHERE id = ?')
            ->execute([$newDue, $loanId]);

        return ['ok' => true, 'due_at' => $newDue];
    }

    private static function policyFor(int $categoryId, int $materialTypeId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT loan_period_days, max_renewals, max_concurrent_loans, fine_rate_per_day, grace_period_days
             FROM circulation_policies WHERE patron_category_id = ? AND material_type_id = ? LIMIT 1'
        );
        $stmt->execute([$categoryId, $materialTypeId]);
        $policy = $stmt->fetch();
        return $policy ?: null;
    }

    private static function activeLoanCount(int $userId): int
    {
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM loans WHERE user_id = ? AND status = 'active'");
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }
}