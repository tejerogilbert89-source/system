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
 * accounts get made.
 */
class User
{
    private const ID_LENGTH = [
        'patron'    => 7,
        'librarian' => 5,
        'admin'     => 5,
    ];

    public static function idLength(string $roleName): int
    {
        return self::ID_LENGTH[$roleName] ?? 5;
    }

    public static function idHint(string $roleName): string
    {
        return 'Exactly ' . self::idLength($roleName) . ' digits.';
    }

    /** All roles, for a <select>. */
    public static function roles(): array
    {
        return Database::connection()
            ->query('SELECT id, name FROM roles ORDER BY id')
            ->fetchAll();
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
     * Creates a new account. The starting password is always the ID
     * number itself - the account holder changes it later from My Profile.
     */
    public static function create(array $data): array
    {
        $pdo = Database::connection();

        $roleId = (int) ($data['role_id'] ?? 0);
        $roleStmt = $pdo->prepare('SELECT name FROM roles WHERE id = ?');
        $roleStmt->execute([$roleId]);
        $roleName = $roleStmt->fetchColumn();

        if (!$roleName) {
            return ['ok' => false, 'error' => 'Choose a role.'];
        }

        $idNumber = trim($data['id_number'] ?? '');
        $fullName = trim($data['full_name'] ?? '');
        $email    = trim($data['email'] ?? '');

        $requiredLen = self::idLength($roleName);
        if (!preg_match('/^\d{' . $requiredLen . '}$/', $idNumber)) {
            return [
                'ok' => false,
                'error' => ucfirst($roleName) . " ID numbers must be exactly {$requiredLen} digits.",
            ];
        }

        if ($fullName === '') {
            return ['ok' => false, 'error' => 'Enter the full name.'];
        }

        $dupe = $pdo->prepare('SELECT COUNT(*) FROM users WHERE id_number = ?');
        $dupe->execute([$idNumber]);
        if ((int) $dupe->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'That ID number is already in use.'];
        }

        $categoryId = null;
        if ($roleName === 'patron') {
            $categoryId = (int) ($data['patron_category_id'] ?? 0);
            if ($categoryId <= 0) {
                return ['ok' => false, 'error' => 'Choose a patron category.'];
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
                    trim($data['program'] ?? '') ?: null,
                    trim($data['year_level'] ?? '') ?: null,
                    trim($data['contact_number'] ?? '') ?: null,
                ]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Could not create the account. Please try again.'];
        }

        return [
            'ok'        => true,
            'id'        => $userId,
            'id_number' => $idNumber,
            'role_name' => $roleName,
        ];
    }

    public static function setStatus(int $userId, string $status): bool
    {
        if (!in_array($status, ['active', 'suspended', 'inactive'], true)) {
            return false;
        }
        $stmt = Database::connection()->prepare('UPDATE users SET status = ? WHERE id = ?');
        return $stmt->execute([$status, $userId]);
    }
}
