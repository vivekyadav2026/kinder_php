CREATE DATABASE IF NOT EXISTS `kinder_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `kinder_db`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `name` VARCHAR(100) DEFAULT '',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `baparis` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `mobile` VARCHAR(20) DEFAULT '',
  `address` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `fine_deposits` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `date` DATE NOT NULL,
  `bapari_id` INT NOT NULL,
  `fine_weight` DECIMAL(12, 3) NOT NULL,
  `purity` DECIMAL(5, 2) NOT NULL DEFAULT 100.00,
  `jama_fine` DECIMAL(12, 3) NOT NULL,
  `cash_received` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `remark` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`bapari_id`) REFERENCES `baparis` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `kaj_entries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `date` DATE NOT NULL,
  `bapari_id` INT NOT NULL,
  `cash_bill` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `total_kaj_fine` DECIMAL(12, 3) NOT NULL DEFAULT 0.00,
  `total_profit_fine` DECIMAL(12, 3) NOT NULL DEFAULT 0.00,
  `remark` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`bapari_id`) REFERENCES `baparis` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `kaj_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kaj_entry_id` INT NOT NULL,
  `item` VARCHAR(100) NOT NULL,
  `gross` DECIMAL(12, 3) NOT NULL,
  `less` DECIMAL(12, 3) NOT NULL DEFAULT 0.00,
  `net` DECIMAL(12, 3) NOT NULL,
  `milting` DECIMAL(5, 2) NOT NULL,
  `wastage` DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
  `hisab` DECIMAL(5, 2) NOT NULL,
  `kaj_fine` DECIMAL(12, 3) NOT NULL,
  `profit_fine` DECIMAL(12, 3) NOT NULL DEFAULT 0.00,
  FOREIGN KEY (`kaj_entry_id`) REFERENCES `kaj_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Insert a default user for easy testing
-- Email: admin@admin.com, Password: password123 (hashed using bcrypt)
INSERT INTO `users` (`id`, `email`, `password_hash`, `name`) 
VALUES (1, 'admin@admin.com', '$2y$10$w8uQZ0c1UfU5X1k8v9tW2eW/7GfJ6W5JtS2uTjUa1G0J6HhFz6vZy', 'Suman Kanti Das')
ON DUPLICATE KEY UPDATE `id`=`id`;
