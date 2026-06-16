<?php
require_once __DIR__ . '/config.php';

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function get_latest_chronicles(int $limit = 12, int $offset = 0): array {
    $db = get_db();
    $stmt = $db->prepare(
        'SELECT id, episode_number, title, excerpt, season, clan, character_name,
                published_at, slug
         FROM chronicles
         WHERE published = 1
         ORDER BY published_at DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_chronicle_by_slug(string $slug): ?array {
    $db = get_db();
    $stmt = $db->prepare(
        'SELECT * FROM chronicles WHERE slug = :slug AND published = 1 LIMIT 1'
    );
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_chronicle_by_id(int $id): ?array {
    $db = get_db();
    $stmt = $db->prepare(
        'SELECT * FROM chronicles WHERE id = :id AND published = 1 LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function count_chronicles(): int {
    return (int) get_db()->query('SELECT COUNT(*) FROM chronicles WHERE published = 1')->fetchColumn();
}

function save_subscriber(string $email): bool {
    $db = get_db();
    try {
        $stmt = $db->prepare(
            'INSERT IGNORE INTO subscribers (email, subscribed_at) VALUES (:email, NOW())'
        );
        $stmt->execute([':email' => strtolower(trim($email))]);
        return $stmt->rowCount() > 0;
    } catch (PDOException) {
        return false;
    }
}
