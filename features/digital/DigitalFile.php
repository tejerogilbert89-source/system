<?php
/**
 * Digital Library: downloadable files (PDF/EPUB) attached to a title.
 *
 * A digital file is not a copy - no barcode, no checkout/return, no
 * due date, unlimited simultaneous "borrowers". It just belongs to a
 * bib_record the same way a physical copy does.
 *
 * Other features must not query digital_files directly - call these
 * static methods instead (see AI_CONTEXT.md).
 */
class DigitalFile
{
    private const ALLOWED_EXTENSIONS = ['pdf' => 'PDF', 'epub' => 'EPUB'];

    /** Files attached to one title, for Catalog's edit screen and the OPAC title page. */
    public static function forTitle(int $bibRecordId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, filename, file_format, file_size_bytes, download_count, uploaded_at
             FROM digital_files WHERE bib_record_id = ? ORDER BY uploaded_at DESC'
        );
        $stmt->execute([$bibRecordId]);
        return $stmt->fetchAll();
    }

    /** One file's metadata, for the download handler. */
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM digital_files WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $file = $stmt->fetch();
        return $file ?: null;
    }

    /** Titles that have at least one digital file, for the public Digital Library browse page. */
    public static function browse(string $q = ''): array
    {
        $like = '%' . $q . '%';
        $stmt = Database::connection()->prepare(
            "SELECT br.id AS bib_record_id, br.title,
                    (SELECT GROUP_CONCAT(a.name SEPARATOR ', ')
                       FROM bib_authors ba JOIN authors a ON a.id = ba.author_id
                      WHERE ba.bib_record_id = br.id) AS authors,
                    df.id AS file_id, df.file_format, df.file_size_bytes
             FROM bib_records br
             JOIN digital_files df ON df.bib_record_id = br.id
             WHERE br.title LIKE ?
                OR EXISTS (SELECT 1 FROM bib_authors ba2 JOIN authors a2 ON a2.id = ba2.author_id
                            WHERE ba2.bib_record_id = br.id AND a2.name LIKE ?)
             ORDER BY br.title
             LIMIT 200"
        );
        $stmt->execute([$like, $like]);
        return $stmt->fetchAll();
    }

    /** True if $filename's extension is one Digital Library accepts. */
    public static function isAllowedFilename(string $filename): bool
    {
        return isset(self::ALLOWED_EXTENSIONS[strtolower(pathinfo($filename, PATHINFO_EXTENSION))]);
    }

    /** Stores an uploaded $_FILES entry and links it to a title. Returns the new row id. */
    public static function upload(int $bibRecordId, array $file, int $staffUserId): int
    {
        if (!is_dir(DIGITAL_STORAGE_PATH)) {
            mkdir(DIGITAL_STORAGE_PATH, 0775, true);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $format = self::ALLOWED_EXTENSIONS[$ext];
        $storedName = bin2hex(random_bytes(16)) . '.' . $ext;

        move_uploaded_file($file['tmp_name'], DIGITAL_STORAGE_PATH . '/' . $storedName);

        $stmt = Database::connection()->prepare(
            'INSERT INTO digital_files (bib_record_id, filename, stored_path, file_format, file_size_bytes, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$bibRecordId, $file['name'], $storedName, $format, $file['size'], $staffUserId]);

        return (int) Database::connection()->lastInsertId();
    }

    /** Like upload(), but for seed scripts: writes raw bytes instead of moving an HTTP upload. */
    public static function seed(int $bibRecordId, string $filename, string $contents, int $staffUserId): int
    {
        if (!is_dir(DIGITAL_STORAGE_PATH)) {
            mkdir(DIGITAL_STORAGE_PATH, 0775, true);
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $format = self::ALLOWED_EXTENSIONS[$ext] ?? strtoupper($ext);
        $storedName = bin2hex(random_bytes(16)) . '.' . $ext;

        file_put_contents(DIGITAL_STORAGE_PATH . '/' . $storedName, $contents);

        $stmt = Database::connection()->prepare(
            'INSERT INTO digital_files (bib_record_id, filename, stored_path, file_format, file_size_bytes, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$bibRecordId, $filename, $storedName, $format, strlen($contents), $staffUserId]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function recordDownload(int $id): void
    {
        Database::connection()->prepare('UPDATE digital_files SET download_count = download_count + 1 WHERE id = ?')
            ->execute([$id]);
    }

    /** Deletes a file's row and its copy on disk. */
    public static function delete(int $id): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT stored_path FROM digital_files WHERE id = ?');
        $stmt->execute([$id]);
        $storedPath = $stmt->fetchColumn();

        $pdo->prepare('DELETE FROM digital_files WHERE id = ?')->execute([$id]);

        if ($storedPath) {
            $full = DIGITAL_STORAGE_PATH . '/' . $storedPath;
            if (is_file($full)) {
                unlink($full);
            }
        }
    }
}