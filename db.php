<?php
/**
 * データベース接続およびスキーマ初期化ヘルパー
 */
function db_config(): array
{
    static $config;
    if ($config === null) {
        $path = __DIR__ . '/config.php';
        if (!file_exists($path)) {
            throw new RuntimeException('config.php が見つかりません。');
        }
        $config = require $path;
    }
    return $config;
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $config = db_config();
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $config['host'], $config['dbname'], $config['charset']);
    try {
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        throw new RuntimeException('データベースに接続できませんでした: ' . $e->getMessage(), 0, $e);
    }
    return $pdo;
}

function db_initialize(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS reservations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            room ENUM("large","small") NOT NULL,
            datetime DATETIME NOT NULL,
            name VARCHAR(100) NOT NULL,
            note TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY room_datetime_unique (room, datetime)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}
