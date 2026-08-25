<?php

namespace App\Repositories;

use App\Utils\Config;
use PDO;

final class DatabaseConnection
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $host = Config::get('database.host', '127.0.0.1');
            $port = Config::get('database.port', '3306');
            $database = Config::get('database.database', 'usorecords');
            $username = Config::get('database.username', 'root');
            $password = Config::get('database.password', '');

            $maxRetries = 10;
            $retryCount = 0;
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

            while (true) {
                try {
                    self::$instance = new PDO($dsn, $username, $password);
                    self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                    break;
                } catch (\PDOException $e) {
                    $retryCount++;
                    if ($retryCount >= $maxRetries) {
                        throw $e;
                    }
                    sleep(2);
                }
            }

            self::initializeSchema(self::$instance);
        }

        return self::$instance;
    }

    private static function initializeSchema(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS audios (
                id VARCHAR(26) PRIMARY KEY,
                source_url TEXT NOT NULL,
                storage_path VARCHAR(255) NOT NULL,
                file_name VARCHAR(255),
                mime_type VARCHAR(100),
                file_size BIGINT,
                status VARCHAR(50) NOT NULL,
                created_at VARCHAR(50) NOT NULL,
                updated_at VARCHAR(50) NOT NULL,
                expires_at VARCHAR(50)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}
