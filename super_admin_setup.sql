-- Create super_admins table
CREATE TABLE IF NOT EXISTS `super_admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default super admin
-- Default credentials:
-- Username: superadmin
-- Password: Admin@123
-- Email: superadmin@addwise.com
INSERT INTO `super_admins` (`username`, `password`, `email`, `created_at`, `is_active`) 
VALUES (
    'superadmin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- This is the hashed version of 'Admin@123'
    'superadmin@addwise.com',
    CURRENT_TIMESTAMP,
    1
) ON DUPLICATE KEY UPDATE `is_active` = 1; 