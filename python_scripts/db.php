<?php
/**
 * CoinorChronicles — Database Connection
 * Singleton PDO instance
 */

function getDB(): PDO {
    static $db = null;
    
    if ($db === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $db = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log("CoinorChronicles DB Error: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    return $db;
}
