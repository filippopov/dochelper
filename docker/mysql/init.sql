-- Gold Rush - Initial Database Setup
-- This file runs automatically when the MySQL container is first created.

-- The database is already created via MYSQL_DATABASE env var,
-- so we just ensure proper charset and add any initial setup here.

ALTER DATABASE gold_rush CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Test database for PHPUnit/Symfony functional tests
CREATE DATABASE IF NOT EXISTS gold_rush_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON gold_rush_test.* TO 'gold_rush_user'@'%';
FLUSH PRIVILEGES;
