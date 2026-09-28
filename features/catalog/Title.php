<?php
/**
 * Catalog domain logic: "what is this, and what do we call it?"
 *
 * A Title here is really a bib_record + its one edition (this project
 * keeps one edition per title for now - the schema allows more later,
 * nothing here prevents extending to that). A Title can have zero or
 * more physical copies (items).
 *
 * Other features must not query bib_records/editions/items/authors
 * directly - call these static methods instead (see AI_CONTEXT.md).
 */
class Title
{
    /** Search titles by title/author/ISBN. Empty $q returns everything. */
    public static function search(string $q = ''): array
    {
        $pdo = Database::connection();
        $like = '%' . $q . '%';

        $stmt = $pdo->prepare(
            "SELECT br.id, br.title,
                    e.id AS edition_id, e.isbn, e.format,
                    (SELECT GROUP_CONCAT(a.name SEPARATOR ', ')
                       FROM bib_authors ba JOIN authors a ON a.id = ba.author_id
                      WHERE ba.bib_record_id = br.id) AS authors,
                    COUNT(i.id) AS total_copies,
                    SUM(CASE WHEN i.status = 'available' THEN 1 ELSE 0 END) AS available_copies
             FROM bib_records br
             LEFT JOIN editions e ON e.bib_record_id = br.id
             LEFT JOIN items i ON i.edition_id = e.id
             WHERE br.title LIKE ? OR e.isbn LIKE ?
                OR EXISTS (SELECT 1 FROM bib_authors ba2 JOIN authors a2 ON a2.id = ba2.author_id
                            WHERE ba2.bib_record_id = br.id AND a2.name LIKE ?)
             GROUP BY br.id, e.id
             ORDER BY br.title
             LIMIT 200"
        );
        $stmt->execute([$like, $like, $like]);
        return $stmt->fetchAll();
    }

    /** One title (bib_record + its edition) plus its authors and copies. Null if not found. */
    public static function find(int $bibRecordId): ?array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            "SELECT br.id, br.title, br.summary,
                    e.id AS edition_id, e.isbn, e.publisher, e.publication_year, e.format
             FROM bib_records br
             JOIN editions e ON e.bib_record_id = br.id
             WHERE br.id = ?
             LIMIT 1"
        );
        $stmt->execute([$bibRecordId]);
        $title = $stmt->fetch();

        if (!$title) {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT a.name FROM bib_authors ba JOIN authors a ON a.id = ba.author_id
             WHERE ba.bib_record_id = ? ORDER BY a.name"
        );
        $stmt->execute([$bibRecordId]);
        $title['authors'] = array_column($stmt->fetchAll(), 'name');

        $stmt = $pdo->prepare(
            "SELECT i.id, i.barcode, i.status, i.item_condition, i.price,
                    mt.name AS material_type_name, c.name AS collection_name, l.name AS location_name
             FROM items i
             JOIN material_types mt ON mt.id = i.material_type_id
             JOIN collections c ON c.id = i.collection_id
             LEFT JOIN locations l ON l.id = i.location_id
             WHERE i.edition_id = ?
             ORDER BY i.barcode"
        );
        $stmt->execute([$title['edition_id']]);
        $title['copies'] = $stmt->fetchAll();

        return $title;
    }

    /** Create a new title. $data keys: title, authors (comma-separated string), isbn, publisher, publication_year, format, summary. Returns the new bib_record id. */
    public static function create(array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('INSERT INTO bib_records (title, summary) VALUES (?, ?)');
        $stmt->execute([$data['title'], $data['summary'] ?: null]);
        $bibId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO editions (bib_record_id, isbn, publisher, publication_year, format)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $bibId,
            $data['isbn'] ?: null,
            $data['publisher'] ?: null,
            $data['publication_year'] ?: null,
            $data['format'] ?: null,
        ]);

        self::saveAuthors($bibId, $data['authors']);

        return $bibId;
    }

    /** Update an existing title and its edition. */
    public static function update(int $bibId, int $editionId, array $data): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('UPDATE bib_records SET title = ?, summary = ? WHERE id = ?');
        $stmt->execute([$data['title'], $data['summary'] ?: null, $bibId]);

        $stmt = $pdo->prepare(
            'UPDATE editions SET isbn = ?, publisher = ?, publication_year = ?, format = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['isbn'] ?: null,
            $data['publisher'] ?: null,
            $data['publication_year'] ?: null,
            $data['format'] ?: null,
            $editionId,
        ]);

        self::saveAuthors($bibId, $data['authors']);
    }

    /** Add a physical copy to an edition. $data keys: barcode, material_type_id, collection_id, location_id, price. Returns the new item id. */
    public static function addCopy(int $editionId, array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            "INSERT INTO items (edition_id, material_type_id, collection_id, location_id, barcode, status, item_condition, date_acquired, price)
             VALUES (?, ?, ?, ?, ?, 'available', 'good', CURDATE(), ?)"
        );
        $stmt->execute([
            $editionId,
            $data['material_type_id'],
            $data['collection_id'],
            $data['location_id'] ?: null,
            $data['barcode'],
            $data['price'] ?: null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** Most recently added titles, for the OPAC homepage. */
    public static function recentlyAdded(int $limit = 6): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT br.id,
                    br.title,
                    (SELECT GROUP_CONCAT(a.name SEPARATOR ', ')
                       FROM bib_authors ba JOIN authors a ON a.id = ba.author_id
                      WHERE ba.bib_record_id = br.id) AS authors
             FROM bib_records br
             JOIN editions e ON e.bib_record_id = br.id
             ORDER BY br.created_at DESC, br.id DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function materialTypes(): array
    {
        return Database::connection()->query('SELECT id, name FROM material_types ORDER BY name')->fetchAll();
    }

    public static function collections(): array
    {
        return Database::connection()->query('SELECT id, name FROM collections ORDER BY name')->fetchAll();
    }

    public static function locations(): array
    {
        return Database::connection()->query('SELECT id, name FROM locations ORDER BY name')->fetchAll();
    }

    /** Replaces this title's author links from a comma-separated name string, creating authors that don't exist yet. */
    private static function saveAuthors(int $bibId, string $namesCsv): void
    {
        $pdo = Database::connection();

        $pdo->prepare('DELETE FROM bib_authors WHERE bib_record_id = ?')->execute([$bibId]);

        $names = array_filter(array_map('trim', explode(',', $namesCsv)));
        foreach ($names as $name) {
            $stmt = $pdo->prepare('SELECT id FROM authors WHERE name = ?');
            $stmt->execute([$name]);
            $authorId = $stmt->fetchColumn();

            if ($authorId === false) {
                $stmt = $pdo->prepare('INSERT INTO authors (name) VALUES (?)');
                $stmt->execute([$name]);
                $authorId = $pdo->lastInsertId();
            }

            $pdo->prepare('INSERT IGNORE INTO bib_authors (bib_record_id, author_id) VALUES (?, ?)')
                ->execute([$bibId, $authorId]);
        }
    }
}
