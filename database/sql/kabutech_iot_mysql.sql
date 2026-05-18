-- KABUTECH / Mushroom Monitoring — IoT tables for MySQL / MariaDB (XAMPP, etc.)
-- Import: phpMyAdmin → Import this file, OR: mysql -u root -p < kabutech_iot_mysql.sql
-- Laravel: set DB_CONNECTION=mysql and DB_DATABASE=kabutech_iot in .env, then run: php artisan migrate
--          (If you use migrate, you do NOT need to import this file — migrations create the same schema.)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `kabutech_iot`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `kabutech_iot`;

-- Every POST /api/sensor-data from the ESP32 inserts one row (all fields the device sends).
DROP TABLE IF EXISTS `sensor_data`;
CREATE TABLE `sensor_data` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `temperature` decimal(5,2) DEFAULT NULL,
  `humidity` decimal(5,2) DEFAULT NULL,
  `misting_system` tinyint(1) NOT NULL DEFAULT 0,
  `recorded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `wifi_rssi` int DEFAULT NULL,
  `misting_source` varchar(16) DEFAULT NULL,
  `misting_reason` varchar(32) DEFAULT NULL,
  `misting_total_ms` bigint unsigned DEFAULT NULL,
  `misting_last_burst_ms` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sensor_data_recorded_at_index` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Latest desired misting mode from the dashboard (single logical row id = 1).
DROP TABLE IF EXISTS `misting_control`;
CREATE TABLE `misting_control` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `desired_on` tinyint(1) NOT NULL DEFAULT 0,
  `desired_mode` varchar(16) NOT NULL DEFAULT 'auto',
  `desired_profile` varchar(16) NOT NULL DEFAULT 'fruiting',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `misting_control` (`id`, `desired_on`, `desired_mode`, `desired_profile`, `created_at`, `updated_at`)
VALUES (1, 0, 'auto', 'fruiting', NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;
