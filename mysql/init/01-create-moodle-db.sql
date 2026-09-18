CREATE DATABASE IF NOT EXISTS db_moodle
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'moodle_user'@'%' IDENTIFIED BY 'moodle_pass';
GRANT ALL PRIVILEGES ON db_moodle.* TO 'moodle_user'@'%';
FLUSH PRIVILEGES;
