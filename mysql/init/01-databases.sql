-- Создание базы данных для WordPress
CREATE DATABASE IF NOT EXISTS db_wordpress CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Создание базы данных для Moodle
CREATE DATABASE IF NOT EXISTS db_moodle CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Создание пользователя WordPress
CREATE USER IF NOT EXISTS 'wp_user'@'%' IDENTIFIED BY 'wp_pass';
GRANT ALL PRIVILEGES ON db_wordpress.* TO 'wp_user'@'%';

-- Создание пользователя Moodle
CREATE USER IF NOT EXISTS 'moodle_user'@'%' IDENTIFIED BY 'moodle_pass';
GRANT ALL PRIVILEGES ON db_moodle.* TO 'moodle_user'@'%';

FLUSH PRIVILEGES;