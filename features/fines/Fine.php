<?php
/**
 * Fines domain logic, built on the account_transactions ledger.
 *
 * The ledger is append-only (see database/schema.sql): a patron's balance
 * is never stored directly, it's always computed by summing their rows.
 * 'charge' and 'adjustment' add to the balance; 'payment', 'waiver' and
 * 'refund' subtract from it. account_transactions.amount is always stored
 * as a positive DECIMAL(10,2) for those types - the sign is applied here,
 * not on the way in.
 *
 * Overdue charges themselves are created by circulation's Loan::returnItem()
 * at the moment a late item is returned - this class only reads that
 * history back and records payments/waivers against it.
 */
class Fine
{
    /** SQL fragment: this row's signed contribution to a patron's balance. */
    private const BALANCE_CASE = "CASE
        WHEN type IN ('charge', 'adjustment') THEN amount
        ELSE -amount
    END";

    /** Totals for the Fines screen's stat row. */
    public static function summary(): array
    {
        $pdo = Database::connection();
        $case = self::BALANCE_CASE;

        $row = $pdo->query(
            "SELECT
                COALESCE(SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END), 0) AS collected,
                COUNT(DISTINCT CASE WHEN type = 'charge' THEN user_id END) AS ever_charged
             FROM account_transactions"
        )->fetch();

        $outstanding = $pdo->query(
            "SELECT COALESCE(SUM(balance), 0), COUNT(*) FROM (
                SELECT user_id, SUM($case) AS balance
                FROM account_transactions
                GROUP BY user_id
                HAVING balance > 0
             ) t"
        )->fetch(PDO::FETCH_NUM);

        return [
            'outstanding'   => (float) $outstanding[0],
            'patrons_owing' => (int) $outstanding[1],
            'collected'     => (float) $row['collected'],
        ];
    }

    /**
     * Patrons with any fine history, aggregated to one row each.
     * $filter: 'all' | 'unpaid' | 'settled'.
     */
    public static function patrons(string $q = '', string $filter = 'all'): array
    {
        $pdo = Database::connection();
        $case = self::BALANCE_CASE;
        $like = '%' . $q . '%';

        $having = '';
        if ($filter === 'unpaid') {
            $having = 'HAVING balance > 0.001';
        } elseif ($filter === 'settled') {
            $having = 'HAVING balance <= 0.001';
        }

        $stmt = $pdo->prepare(
            "SELECT u.id AS user_id, u.full_name, u.id_number, pc.name AS category_name,
                    SUM(CASE WHEN t.type = 'charge' THEN t.amount ELSE 0 END) AS charged,
                    SUM(CASE WHEN t.type = 'payment' THEN t.amount ELSE 0 END) AS paid,
                    SUM($case) AS balance,
                    MAX(t.created_at) AS last_activity
             FROM account_transactions t
             JOIN users u ON u.id = t.user_id
             LEFT JOIN patron_profiles pp ON pp.user_id = u.id
             LEFT JOIN patron_categories pc ON pc.id = pp.patron_category_id
             WHERE (:q = '' OR u.full_name LIKE :like1 OR u.id_number LIKE :like2)
             GROUP BY u.id, u.full_name, u.id_number, pc.name
             $having
             ORDER BY balance DESC, last_activity DESC
             LIMIT 300"
        );
        $stmt->execute(['q' => $q, 'like1' => $like, 'like2' => $like]);
        return $stmt->fetchAll();
    }

    /** One patron's basic info, for the ledger page header. */
    public static function patronInfo(int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT u.id, u.full_name, u.id_number, pc.name AS category_name
             FROM users u
             LEFT JOIN patron_profiles pp ON pp.user_id = u.id
             LEFT JOIN patron_categories pc ON pc.id = pp.patron_category_id
             WHERE u.id = ?
             LIMIT 1"
        );
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    /** Full transaction history for one patron, newest first. */
    public static function ledgerFor(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT t.*, s.full_name AS staff_name
             FROM account_transactions t
             LEFT JOIN users s ON s.id = t.created_by
             WHERE t.user_id = ?
             ORDER BY t.created_at DESC, t.id DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function balanceFor(int $userId): float
    {
        $case = self::BALANCE_CASE;
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM($case), 0) FROM account_transactions WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        return round((float) $stmt->fetchColumn(), 2);
    }

    /** Records a payment. Pass $amount = null to pay the full current balance. */
    public static function pay(int $userId, ?float $amount, int $staffUserId, string $note = ''): array
    {
        $balance = self::balanceFor($userId);

        if ($balance <= 0) {
            return ['ok' => false, 'error' => 'This patron has no outstanding balance.'];
        }

        $amount = $amount ?? $balance;

        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'Enter an amount greater than zero.'];
        }
        if ($amount > $balance + 0.001) {
            return ['ok' => false, 'error' => 'That payment (' . number_format($amount, 2) . ') is more than the balance owed (' . number_format($balance, 2) . ').'];
        }

        $amount = round($amount, 2);
        $pdo = Database::connection();
        $pdo->prepare(
            "INSERT INTO account_transactions (user_id, type, amount, description, created_by)
             VALUES (?, 'payment', ?, ?, ?)"
        )->execute([$userId, $amount, $note !== '' ? $note : 'Fine payment', $staffUserId]);

        return ['ok' => true, 'amount' => $amount, 'remaining' => round($balance - $amount, 2)];
    }

    /** Waives (forgives) part or all of a patron's balance. Always requires a reason. */
    public static function waive(int $userId, float $amount, int $staffUserId, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            return ['ok' => false, 'error' => 'Enter a reason for the waiver.'];
        }

        $balance = self::balanceFor($userId);
        if ($balance <= 0) {
            return ['ok' => false, 'error' => 'This patron has no outstanding balance.'];
        }
        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'Enter an amount greater than zero.'];
        }
        if ($amount > $balance + 0.001) {
            return ['ok' => false, 'error' => 'That waiver (' . number_format($amount, 2) . ') is more than the balance owed (' . number_format($balance, 2) . ').'];
        }

        $amount = round($amount, 2);
        $pdo = Database::connection();
        $pdo->prepare(
            "INSERT INTO account_transactions (user_id, type, amount, description, created_by)
             VALUES (?, 'waiver', ?, ?, ?)"
        )->execute([$userId, $amount, $reason, $staffUserId]);

        return ['ok' => true, 'amount' => $amount, 'remaining' => round($balance - $amount, 2)];
    }
}
