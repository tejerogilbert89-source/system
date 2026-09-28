<?php
/**
 * CSRF protection: makes sure a form submission really came from our own
 * site, not from another website tricking a logged-in user's browser.
 * Every form that changes data should include Csrf::field().
 */
class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /** Echo this inside a <form> tag. */
    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . self::token() . '">';
    }

    public static function verify(?string $submittedToken): bool
    {
        if ($submittedToken === null || empty($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $submittedToken);
    }
}
