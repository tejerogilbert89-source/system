<?php
/**
 * One shared PDO connection for the whole app.
 * Every feature calls Database::connection() instead of opening its own connection.
 */
class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false, // use real prepared statements
                ]);
            } catch (PDOException $e) {
                // In a school project it's fine to show this directly.
                // In production you would log it and show a generic message instead.
                die('Database connection failed: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }
}
