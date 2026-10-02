<?php
/**
 * Fines: the overdue-fine rule plus the ledger-backed balance, payment and
 * waiver operations used by the /fines screens.
 *
 * THE RULE: PHP 5.00 for every overdue day, and Saturdays and Sundays do
 * not count. The overdue days are the days AFTER the due date up to and
 * including the return date; only Monday-Friday among them are charged.
 *   due Fri, returned Mon  -> Sat, Sun, Mon      -> 1 day  -> 5.00
 *   due Mon, returned Mon+7 -> Tue..Fri, Mon      -> 5 days -> 25.00
 *   due Fri, returned Sat/Sun                     -> 0 days -> 0.00
 *
 * Money is compared in whole centavos (integers), never as raw floats.
 */
class Fine
{
    public const RATE_PER_DAY = 5.00;
    private const TZ = 'Asia/Manila';
    private const MAX_NOTE = 255;

    /* ------------------------------------------------------------------
     * The rule
     * ---------------------------------------------------------------- */

    /** Date-only (Y-m-d) from any date/datetime string; null if unparseable. */
    private static function day(string $s): ?DateTimeImmutable
    {
        if (!preg_match('/^\s*(\d{4})-(\d{2})-(\d{2})/', $s, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }
        // UTC midnight: pure calendar arithmetic, immune to DST/timezone shifts.
        return new DateTimeImmutable($m[1] . '-' . $m[2] . '-' . $m[3] . ' 00:00:00', new DateTimeZone('UTC'));
    }

    /** Chargeable overdue days: weekdays strictly after $due, up to and including $returned. */
    public static function overdueDays(string $due, string $returned): int
    {
        $d = self::day($due);
        $r = self::day($returned);
        if ($d === null || $r === null) {
            throw new InvalidArgumentException('Invalid date.');
        }
        if ($r <= $d) {
            return 0;
        }
        $n     = (int) $d->diff($r)->days;      // calendar days after the due date
        $count = intdiv($n, 7) * 5;             // every full week has exactly 5 weekdays
        $day   = $d->modify('+' . (intdiv($n, 7) * 7 + 1) . ' days');
        for ($i = 0, $left = $n % 7; $i < $left; $i++) {
            if ((int) $day->format('N') <= 5) { // 1=Mon .. 5=Fri
                $count++;
            }
            $day = $day->modify('+1 day');
        }
        return $count;
    }

    /** Fine in pesos for a loan due on $due and returned on $returned. */
    public static function calculate(string $due, string $returned): float
    {
        return round(self::overdueDays($due, $returned) * self::RATE_PER_DAY, 2);
    }

    /** Today's date in the library's timezone (Y-m-d). */
    public static function today(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone(self::TZ)))->format('Y-m-d');
    }

    private static function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone(self::TZ)))->format('Y-m-d H:i:s');
    }

    /* ------------------------------------------------------------------
     * Small helpers shared by the handlers
     * ---------------------------------------------------------------- */

    private static function cents(float $pesos): int
    {
        return (int) round($pesos * 100);
    }

    private static function pesos(int $cents): float
    {
        return $cents / 100;
    }

    /** Strict money parser: "12", "12.5", "12.50" only. Returns pesos or null. */
    public static function parseAmount($v): ?float
    {
        if (!is_string($v)) {
            return null;
        }
        $v = trim($v);
        if (!preg_match('/^\d{1,7}(\.\d{1,2})?$/', $v)) {
            return null;
        }
        $f = (float) $v;
        return $f >= 0.01 ? $f : null;
    }

    /** Trimmed single-line text, capped at $max characters; non-strings become ''. */
    public static function cleanText($v, int $max = self::MAX_NOTE): string
    {
        if (!is_string($v)) {
            return '';
        }
        $v = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $v) ?? '');
        return function_exists('mb_substr') ? mb_substr($v, 0, $max, 'UTF-8') : substr($v, 0, $max);
    }

    /** Positive integer from a request value, or 0 (arrays/garbage -> 0, never "1"). */
    public static function parseId($v): int
    {
        $i = filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $i === false ? 0 : (int) $i;
    }

    /**
     * Only ever redirect to /fines, /fines?..., or /fines/patron?user_id=N.
     * Anything else (other paths, absolute URLs, CR/LF, arrays) -> /fines.
     */
    public static function safeBack($v): string
    {
        if (is_string($v) && preg_match('#^/fines(?:/patron\?user_id=\d{1,10}|\?[A-Za-z0-9=&%_.+-]{0,200})?$#D', $v)) {
            return $v;
        }
        return '/fines';
    }

    /** One-time form token: stops a double-click / refresh from recording a payment twice. */
    public static function pageToken(): string
    {
        static $token = null;
        if ($token === null) {
            $token = bin2hex(random_bytes(12));
            $_SESSION['fine_tokens'][$token] = time();
            $_SESSION['fine_tokens'] = array_slice($_SESSION['fine_tokens'], -40, null, true);
        }
        return $token;
    }

    public static function claimToken($t): bool
    {
        if (!is_string($t) || !isset($_SESSION['fine_tokens'][$t])) {
            return false;
        }
        unset($_SESSION['fine_tokens'][$t]);
        return true;
    }

    /* ------------------------------------------------------------------
     * Reads
     * ---------------------------------------------------------------- */

    private const SIGNED = "CASE t.type WHEN 'payment' THEN -t.amount WHEN 'waiver' THEN -t.amount ELSE t.amount END";

    public static function patronInfo(int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT u.id, u.id_number, u.full_name, pc.name AS category_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN patron_profiles pp ON pp.user_id = u.id
             LEFT JOIN patron_categories pc ON pc.id = pp.patron_category_id
             WHERE u.id = ? AND r.name = 'patron'
             LIMIT 1"
        );
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public static function balanceFor(int $userId): float
    {
        return self::pesos(self::balanceCents(Database::connection(), $userId));
    }

    private static function balanceCents(PDO $pdo, int $userId): int
    {
        $stmt = $pdo->prepare('SELECT ROUND(COALESCE(SUM(' . self::SIGNED . '), 0), 2) FROM account_transactions t WHERE t.user_id = ?');
        $stmt->execute([$userId]);
        return self::cents((float) $stmt->fetchColumn());
    }

    public static function ledgerFor(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT t.id, t.type, t.amount, t.description, t.created_at, s.full_name AS staff_name
             FROM account_transactions t
             LEFT JOIN users s ON s.id = t.staff_id
             WHERE t.user_id = ?
             ORDER BY t.created_at DESC, t.id DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /** Outstanding = sum of what patrons OWE (a patron's credit never cancels someone else's debt). */
    public static function summary(): array
    {
        $pdo = Database::connection();
        $row = $pdo->query(
            "SELECT COALESCE(SUM(CASE WHEN b > 0 THEN b ELSE 0 END), 0) AS outstanding,
                    COALESCE(SUM(CASE WHEN b > 0 THEN 1 ELSE 0 END), 0)  AS patrons_owing
             FROM (SELECT ROUND(SUM(" . self::SIGNED . "), 2) AS b
                   FROM account_transactions t GROUP BY t.user_id) x"
        )->fetch();
        $collected = $pdo->query(
            "SELECT COALESCE(SUM(CASE t.type WHEN 'payment' THEN t.amount WHEN 'refund' THEN -t.amount ELSE 0 END), 0)
             FROM account_transactions t"
        )->fetchColumn();
        return [
            'outstanding'   => round((float) $row['outstanding'], 2),
            'patrons_owing' => (int) $row['patrons_owing'],
            'collected'     => round((float) $collected, 2),
        ];
    }

    /** Patrons with any fine history, optionally searched and filtered. */
    public static function patrons(string $q, string $filter): array
    {
        $having = ['unpaid' => 'HAVING balance > 0', 'settled' => 'HAVING balance <= 0'][$filter] ?? '';
        $like   = '%' . strtr($q, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        $stmt = Database::connection()->prepare(
            "SELECT u.id AS user_id, u.full_name, u.id_number, pc.name AS category_name,
                    ROUND(COALESCE(SUM(CASE WHEN t.type = 'charge'  THEN t.amount END), 0), 2) AS charged,
                    ROUND(COALESCE(SUM(CASE WHEN t.type = 'payment' THEN t.amount END), 0), 2) AS paid,
                    ROUND(COALESCE(SUM(CASE WHEN t.type = 'waiver'  THEN t.amount END), 0), 2) AS waived,
                    ROUND(COALESCE(SUM(" . self::SIGNED . "), 0), 2) AS balance
             FROM account_transactions t
             JOIN users u ON u.id = t.user_id
             LEFT JOIN patron_profiles pp ON pp.user_id = u.id
             LEFT JOIN patron_categories pc ON pc.id = pp.patron_category_id
             WHERE (? = '' OR u.full_name LIKE ? ESCAPE '!' OR u.id_number LIKE ? ESCAPE '!')
             GROUP BY u.id, u.full_name, u.id_number, pc.name
             {$having}
             ORDER BY balance DESC, u.full_name"
        );
        $stmt->execute([$q, $like, $like]);
        return $stmt->fetchAll();
    }

    /* ------------------------------------------------------------------
     * Writes
     * ---------------------------------------------------------------- */

    private static function insert(PDO $pdo, int $userId, ?int $loanId, string $type, float $amount, string $desc, ?int $staffId): int
    {
        $pdo->prepare(
            'INSERT INTO account_transactions (user_id, loan_id, type, amount, description, staff_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$userId, $loanId, $type, number_format($amount, 2, '.', ''), $desc ?: null, $staffId, self::now()]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Lock the patron, read the balance, then run $work - all in one
     * transaction, so two staff members acting at the same moment cannot
     * both spend the same balance. (A no-op UPDATE takes the write lock on
     * both MySQL and SQLite before the balance is read.)
     */
    private static function locked(int $userId, callable $work): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE users SET id = id WHERE id = ?')->execute([$userId]);
            $result = $work($pdo, self::balanceCents($pdo, $userId));
            if ($result['ok']) {
                $pdo->commit();
            } else {
                $pdo->rollBack();
            }
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Fine transaction failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not save that. Please try again.'];
        }
    }

    /**
     * Record a payment. $amount = null means "pay the whole balance"; then
     * $expectedBalance (what the screen showed) must still match, so staff
     * never collect a different amount than the one they were looking at.
     */
    public static function pay(int $userId, ?float $amount, int $staffId, string $note, ?float $expectedBalance = null): array
    {
        if (!self::patronInfo($userId)) {
            return ['ok' => false, 'error' => 'Patron not found.'];
        }
        $note = self::cleanText($note);

        return self::locked($userId, function (PDO $pdo, int $balance) use ($userId, $amount, $staffId, $note, $expectedBalance): array {
            if ($balance <= 0) {
                return ['ok' => false, 'error' => 'This patron has no balance to pay.'];
            }
            if ($amount === null) {
                if ($expectedBalance !== null && self::cents($expectedBalance) !== $balance) {
                    return ['ok' => false, 'error' => 'The balance changed to ' . number_format(self::pesos($balance), 2) . ' since that page loaded. Check it and try again.'];
                }
                $pay = $balance;
            } else {
                $pay = self::cents($amount);
                if ($pay < 1) {
                    return ['ok' => false, 'error' => 'Enter an amount of at least 0.01.'];
                }
                if ($pay > $balance) {
                    return ['ok' => false, 'error' => 'The payment cannot be more than the balance of ' . number_format(self::pesos($balance), 2) . '.'];
                }
            }
            $id = self::insert($pdo, $userId, null, 'payment', self::pesos($pay), $note !== '' ? $note : 'Payment received', $staffId);
            return ['ok' => true, 'id' => $id, 'amount' => self::pesos($pay), 'remaining' => self::pesos($balance - $pay)];
        });
    }

    public static function waive(int $userId, float $amount, int $staffId, string $reason): array
    {
        if (!self::patronInfo($userId)) {
            return ['ok' => false, 'error' => 'Patron not found.'];
        }
        $reason = self::cleanText($reason);
        if ($reason === '') {
            return ['ok' => false, 'error' => 'A reason is required to waive a fine.'];
        }

        return self::locked($userId, function (PDO $pdo, int $balance) use ($userId, $amount, $staffId, $reason): array {
            $w = self::cents($amount);
            if ($balance <= 0) {
                return ['ok' => false, 'error' => 'This patron has no balance to waive.'];
            }
            if ($w < 1) {
                return ['ok' => false, 'error' => 'Enter an amount of at least 0.01.'];
            }
            if ($w > $balance) {
                return ['ok' => false, 'error' => 'You cannot waive more than the balance of ' . number_format(self::pesos($balance), 2) . '.'];
            }
            $id = self::insert($pdo, $userId, null, 'waiver', self::pesos($w), $reason, $staffId);
            return ['ok' => true, 'id' => $id, 'amount' => self::pesos($w), 'remaining' => self::pesos($balance - $w)];
        });
    }

    /**
     * Called by circulation when a loan is returned (Loan::returnItem).
     * Charges 5.00 per overdue weekday. Returns null when nothing is owed
     * (on time, or only weekend days late) or when this loan was already
     * charged - so calling it twice for one loan can never double-charge.
     */
    public static function chargeOverdue(int $userId, int $loanId, string $title, string $due, string $returned, ?int $staffId = null): ?array
    {
        $days = self::overdueDays($due, $returned);
        if ($days === 0) {
            return null;
        }
        $amount = round($days * self::RATE_PER_DAY, 2);
        $result = self::locked($userId, function (PDO $pdo, int $balance) use ($userId, $loanId, $title, $days, $amount, $staffId): array {
            $dupe = $pdo->prepare("SELECT COUNT(*) FROM account_transactions WHERE loan_id = ? AND type = 'charge'");
            $dupe->execute([$loanId]);
            if ((int) $dupe->fetchColumn() > 0) {
                return ['ok' => false, 'error' => 'Already charged.'];
            }
            $desc = self::cleanText('Overdue: ' . $title . ' (' . $days . ' weekday' . ($days === 1 ? '' : 's') . ' x ' . number_format(self::RATE_PER_DAY, 2) . ')');
            $id = self::insert($pdo, $userId, $loanId, 'charge', $amount, $desc, $staffId);
            return ['ok' => true, 'id' => $id, 'amount' => $amount, 'days' => $days];
        });
        return $result['ok'] ? $result : null;
    }
}