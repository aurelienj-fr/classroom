-- Test database used by PHPUnit (DATABASE_URL + "_test" suffix, see config/packages/doctrine.yaml).
CREATE DATABASE IF NOT EXISTS `monstudio_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON `monstudio_test`.* TO 'app'@'%';
FLUSH PRIVILEGES;
