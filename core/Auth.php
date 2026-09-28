<?php
/**
 * Handles login, logout, and "who is logged in" checks.
 * Uses PHP sessions - nothing fancy, easy to explain in a defense.
 */
class Auth
{
    /**
     * Try to log a user in using their ID number and password.
     * Returns true and starts the session on success.
     */
    public static function attempt(string $idNumber, string $password): bool
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            "SELECT u.*, r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id_number = ? AND u.status = 'active'"
        );
        $stmt->execute([$idNumber]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            self::login($user);
            return true;
        }

        return false;
    }

    public static function login(array $user): void
    {
        // Regenerating the session ID after login prevents session fixation attacks.
        session_regenerate_id(true);

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['role']      = $user['role_name'];
        $_SESSION['full_name'] = $user['full_name'];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function id(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function role(): ?string
    {
        return $_SESSION['role'] ?? null;
    }

    public static function name(): ?string
    {
        return $_SESSION['full_name'] ?? null;
    }

    /** Call at the top of any page that requires login. */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: /login');
            exit;
        }
    }

    /** Call at the top of any page that requires a specific role, e.g. ['admin','librarian']. */
    public static function requireRole(array $roles): void
    {
        self::requireLogin();

        if (!in_array(self::role(), $roles, true)) {
            http_response_code(403);
            die('Access denied. Your account does not have permission to view this page.');
        }
    }
}
