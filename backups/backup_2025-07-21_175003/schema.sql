-- Complaint Portal Master Schema (Cleaned & Final)

-- ---------------------
-- Users Table
-- ---------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(10) NOT NULL,
  `role` ENUM('student','faculty','nonteaching','technician','superadmin','outsourced_vendor') NOT NULL,
  `special_code` VARCHAR(50) NOT NULL,
  `specialization` VARCHAR(50) DEFAULT NULL,
  `username` VARCHAR(50) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `hostel_type` VARCHAR(10) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username_unique` (`username`),
  KEY `idx_role` (`role`),
  KEY `idx_specialization` (`specialization`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------
-- Special Codes Table
-- ---------------------
CREATE TABLE IF NOT EXISTS `special_codes` (
  `code` VARCHAR(50) NOT NULL,
  `role` ENUM('student','faculty','nonteaching','technician','outsourced_vendor') NOT NULL,
  `specialization` VARCHAR(50) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expiry_date` DATETIME DEFAULT NULL,
  `generated_by` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`code`),
  KEY `idx_role` (`role`),
  KEY `idx_expiry` (`expiry_date`),
  CONSTRAINT `fk_special_codes_users` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------
-- Security Logs Table
-- ---------------------
CREATE TABLE IF NOT EXISTS `security_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(50) NOT NULL,
  `target` VARCHAR(255) DEFAULT NULL,
  `details` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_security_logs_users` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------
-- Complaints Table
-- ---------------------
CREATE TABLE IF NOT EXISTS `complaints` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `room_no` VARCHAR(20) NOT NULL,
  `hostel_type` ENUM('boys','girls') DEFAULT NULL,
  `category` ENUM('mess','carpenter','wifi','housekeeping','plumber','electrician','laundry','ac','other') NOT NULL,
  `description` TEXT NOT NULL,
  `status` ENUM('pending','in_progress','resolved','rejected') NOT NULL DEFAULT 'pending',
  `technician_id` INT UNSIGNED DEFAULT NULL,
  `tech_note` TEXT,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_unique` (`token`),
  KEY `idx_status` (`status`),
  KEY `idx_category` (`category`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_technician` (`technician_id`),
  CONSTRAINT `fk_complaints_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_complaints_technician` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------
-- Complaint History Table
-- ---------------------
CREATE TABLE IF NOT EXISTS `complaint_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `complaint_id` INT UNSIGNED NOT NULL,
  `status` ENUM('pending','in_progress','resolved','rejected') NOT NULL,
  `note` TEXT,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_complaint_id` (`complaint_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_complaint_history_complaints` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------
-- Hostel Issues Table
-- ---------------------
CREATE TABLE IF NOT EXISTS `hostel_issues` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `hostel_type` ENUM('boys','girls') NOT NULL,
  `issue_type` ENUM('wifi','water','mess','electricity','cleanliness','other') NOT NULL,
  `status` ENUM('not_assigned','in_progress','resolved') NOT NULL DEFAULT 'not_assigned',
  `technician_id` INT UNSIGNED DEFAULT NULL,
  `tech_remarks` TEXT,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hostel_type` (`hostel_type`),
  KEY `idx_issue_type` (`issue_type`),
  KEY `idx_status` (`status`),
  KEY `idx_technician` (`technician_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_hostel_issues_technician` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------
-- Hostel Issue Votes
-- ---------------------
CREATE TABLE IF NOT EXISTS `hostel_issue_votes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `issue_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `vote_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_vote` (`issue_id`, `user_id`),
  KEY `idx_issue_id` (`issue_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_vote_time` (`vote_time`),
  CONSTRAINT `fk_hostel_issue_votes_issues` FOREIGN KEY (`issue_id`) REFERENCES `hostel_issues` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hostel_issue_votes_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------
-- Suggestions Table
-- ---------------------
CREATE TABLE IF NOT EXISTS `suggestions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(100) NOT NULL,
  `suggestion` TEXT NOT NULL,
  `category` ENUM('academics','infrastructure','hostel','mess','sports','other') NOT NULL DEFAULT 'other',
  `status` ENUM('pending','approved','rejected','implemented') NOT NULL DEFAULT 'pending',
  `admin_remark` TEXT NULL,
  `upvotes` INT UNSIGNED NOT NULL DEFAULT 0,
  `downvotes` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_category` (`category`),
  KEY `idx_status` (`status`),
  KEY `idx_admin_remark` (`admin_remark`(255)),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_suggestions_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------
-- Suggestion Votes Table
-- ---------------------
CREATE TABLE IF NOT EXISTS `suggestion_votes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `suggestion_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `vote_type` ENUM('upvote','downvote') NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_vote` (`suggestion_id`, `user_id`),
  KEY `idx_suggestion_id` (`suggestion_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_vote_type` (`vote_type`),
  CONSTRAINT `fk_suggestion_votes_suggestions` FOREIGN KEY (`suggestion_id`) REFERENCES `suggestions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_suggestion_votes_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------
-- User Notifications
-- ---------------------
CREATE TABLE IF NOT EXISTS `user_notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `complaint_token` VARCHAR(64) DEFAULT NULL,
  `hostel_issue_id` INT UNSIGNED DEFAULT NULL,
  `type` ENUM('resolved','hostel_resolved') NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_complaint_token` (`complaint_token`),
  KEY `idx_hostel_issue_id` (`hostel_issue_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_notifications_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notifications_complaints` FOREIGN KEY (`complaint_token`) REFERENCES `complaints` (`token`) ON DELETE SET NULL,
  CONSTRAINT `fk_notifications_hostel_issues` FOREIGN KEY (`hostel_issue_id`) REFERENCES `hostel_issues` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
