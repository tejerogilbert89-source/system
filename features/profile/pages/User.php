<?php
/**
 * Account management for every role (patron, librarian, admin).
 *
 * The password rule: a new account's starting password is always its own
 * ID number - the same "ID number is the starting password" idea already
 * used on My Profile's change-password screen. What differs by role is
 * the ID number's length:
 *   - patron              -> exactly 7 digits
 *   - librarian and admin -> exactly 5 digits
 * That length is enforced here, at creation, since this is the only place
 * accounts get made. Roles that are not listed below cannot be created
 * (fail closed) - they never silently fall back to another role's length.
 */
class User
{
    private const ID_LENGTH = [
        'patron'    => 7,
        'librarian' => 5,
        'admin'     => 5,
    ];

    /** Column limits - checked here so the admin gets a clear message instead of a DB error. */
    private const MAX_FULL_NAME = 150;
    private const MAX_EMAIL     = 150;
    private const MAX_PROGRAM   = 100;
    private const MAX_YEAR      = 20;
    private const MAX_CONTACT   = 30;

    /** Required ID length for a role, or 0 if the role is not a known/creatable one. */
    public static function idLength(string $roleName): int
    {
        return self::ID_LENGTH[strtolower($roleName)] ?? 0;
    }

    public static function idHint(string $roleName): string
    {
        $len = self::idLength($roleName);
        return $len > 0 ? 'Exactly ' . $len . ' digits.' : 'This role cannot be created here.';
    }

    /** All roles, for a <select>. */
    public static function roles(): array
    {
        return Database::connection()
            ->query('SELECT id, name FROM roles ORDER BY id')
            ->fetchAll();
    }

    /** Only the roles that have an ID-length rule - these are the ones the form offers. */
    public static function creatableRoles(): array
    {
        return array_values(array_filter(
            self::roles(),
            static fn(array $r): bool => self::idLength((string) $r['name']) > 0
        ));
    }

    /** All patron categories, for a <select> shown only when role = patron. */
    public static function patronCategories(): array
    {
        return Database::connection()
            ->query('SELECT id, name FROM patron_categories ORDER BY name')
            ->fetchAll();
    }

    /** Every account except the one given (normally the logged-in admin). */
    public static function all(int $excludeUserId = 0): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT u.id, u.id_number, u.full_name, u.status, r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id != ?
             ORDER BY r.name, u.full_name"
        );
        $stmt->execute([$excludeUserId]);
        return $stmt->fetchAll();
    }

    /**
     * A trimmed string from submitted data. Anything that is not a plain
     * string/number (e.g. a tampered "field[]=x" array) becomes '' instead
     * of crashing trim() with a TypeError.
     */
    private static function text(array $data, string $key): string
    {
        $v = $data[$key] ?? '';
        return (is_string($v) || is_int($v)) ? trim((string) $v) : '';
    }

    private static function length(string $s): int
    {
        return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
    }

    /**
     * Creates a new account. The starting password is always the ID
     * number itself - the account holder changes it later from My Profile.
     */
    public static function create(array $data): array
    {
        $pdo = Database::connection();

        $rawRole = $data['role_id'] ?? 0;
        $roleId  = is_string($rawRole) || is_int($rawRole) ? (int) $rawRole : 0;
        $roleStmt = $pdo->prepare('SELECT name FROM roles WHERE id = ?');
        $roleStmt->execute([$roleId]);
        $roleName = $roleStmt->fetchColumn();

        if (!$roleName) {
            return ['ok' => false, 'error' => 'Choose a role.'];
        }
        $roleName = strtolower((string) $roleName);

        $requiredLen = self::idLength($roleName);
        if ($requiredLen === 0) {
            return ['ok' => false, 'error' => 'Accounts with that role cannot be created here.'];
        }

        $idNumber = self::text($data, 'id_number');
        $fullName = self::text($data, 'full_name');
        $email    = self::text($data, 'email');
        $program  = self::text($data, 'program');
        $year     = self::text($data, 'year_level');
        $contact  = self::text($data, 'contact_number');

        // \A...\z: ASCII digits only, exact length, no trailing-newline loophole.
        if (!preg_match('/\A\d{' . $requiredLen . '}\z/', $idNumber)) {
            return [
                'ok' => false,
                'error' => ucfirst($roleName) . " ID numbers must be exactly {$requiredLen} digits.",
            ];
        }

        if ($fullName === '') {
            return ['ok' => false, 'error' => 'Enter the full name.'];
        }
        if (self::length($fullName) > self::MAX_FULL_NAME) {
            return ['ok' => false, 'error' => 'The full name is too long (max ' . self::MAX_FULL_NAME . ' characters).'];
        }
        if ($email !== '') {
            if (self::length($email) > self::MAX_EMAIL || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'error' => 'Enter a valid email address, or leave it blank.'];
            }
        }

        $dupe = $pdo->prepare('SELECT COUNT(*) FROM users WHERE id_number = ?');
        $dupe->execute([$idNumber]);
        if ((int) $dupe->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'That ID number is already in use.'];
        }

        $categoryId = null;
        if ($roleName === 'patron') {
            $rawCat     = $data['patron_category_id'] ?? 0;
            $categoryId = is_string($rawCat) || is_int($rawCat) ? (int) $rawCat : 0;
            if ($categoryId <= 0) {
                return ['ok' => false, 'error' => 'Choose a patron category.'];
            }
            $catStmt = $pdo->prepare('SELECT COUNT(*) FROM patron_categories WHERE id = ?');
            $catStmt->execute([$categoryId]);
            if ((int) $catStmt->fetchColumn() === 0) {
                return ['ok' => false, 'error' => 'That patron category does not exist. Choose one from the list.'];
            }
            if (self::length($program) > self::MAX_PROGRAM) {
                return ['ok' => false, 'error' => 'Program / department is too long (max ' . self::MAX_PROGRAM . ' characters).'];
            }
            if (self::length($year) > self::MAX_YEAR) {
                return ['ok' => false, 'error' => 'Year level is too long (max ' . self::MAX_YEAR . ' characters).'];
            }
            if (self::length($contact) > self::MAX_CONTACT) {
                return ['ok' => false, 'error' => 'Contact number is too long (max ' . self::MAX_CONTACT . ' characters).'];
            }
        }

        // Starting password = the ID number, same rule as everywhere else.
        $hash = password_hash($idNumber, PASSWORD_ARGON2ID);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO users (role_id, id_number, full_name, email, password_hash)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$roleId, $idNumber, $fullName, $email ?: null, $hash]);
            $userId = (int) $pdo->lastInsertId();

            if ($roleName === 'patron') {
                $pdo->prepare(
                    "INSERT INTO patron_profiles (user_id, patron_category_id, program, year_level, contact_number)
                     VALUES (?, ?, ?, ?, ?)"
                )->execute([
                    $userId,
                    $categoryId,
                    $program ?: null,
                    $year ?: null,
                    $contact ?: null,
                ]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('User::create failed: ' . $e->getMessage());

            // Two admins creating the same ID at the same moment both pass the
            // check above; the UNIQUE index catches the loser - say so plainly.
            $dupe->execute([$idNumber]);
            if ((int) $dupe->fetchColumn() > 0) {
                return ['ok' => false, 'error' => 'That ID number is already in use.'];
            }
            return ['ok' => false, 'error' => 'Could not create the account. Please try again.'];
        }

        return [
            'ok'        => true,
            'id'        => $userId,
            'id_number' => $idNumber,
            'role_name' => $roleName,
        ];
    }

    /** Returns false for an unknown status value or a user that does not exist. */
    public static function setStatus(int $userId, string $status): bool
    {
        if (!in_array($status, ['active', 'suspended', 'inactive'], true)) {
            return false;
        }
        $pdo = Database::connection();

        $exists = $pdo->prepare('SELECT 1 FROM users WHERE id = ?');
        $exists->execute([$userId]);
        if (!$exists->fetchColumn()) {
            return false;
        }

        $stmt = $pdo->prepare('UPDATE users SET status = ? WHERE id = ?');
        return $stmt->execute([$status, $userId]);
    }
}