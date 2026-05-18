<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=kabutech_iot', 'root', '');
$pdo->exec("CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
echo 'Table created (or already exists).' . PHP_EOL;
