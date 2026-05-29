-- Database setup script for HOABL Ayodhya
-- Project: The Sarayu

CREATE DATABASE IF NOT EXISTS `hoabl_ayodhya_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hoabl_ayodhya_db`;

-- 1. Leads Table Structure
CREATE TABLE IF NOT EXISTS `leads` (
  `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `project_interest` VARCHAR(150) NOT NULL,
  `source_item` VARCHAR(100) DEFAULT 'General',
  `referral_url` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `country_code` VARCHAR(5) DEFAULT NULL,
  `vpn_blocked` INT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Settings Table Structure (Dynamic SMTP/Central override)
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(50) UNIQUE NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default SMTP configurations to prevent first-time startup select exceptions
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('use_smtp', '0'),
('smtp_host', 'smtp.gmail.com'),
('smtp_port', '587'),
('smtp_user', ''),
('smtp_pass', ''),
('notify_to', 'harshmheswry@gmail.com'),
('notify_cc', 'diyarjun9@gmail.com')
ON DUPLICATE KEY UPDATE `updated_at` = CURRENT_TIMESTAMP;
