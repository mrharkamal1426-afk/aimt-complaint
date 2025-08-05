-- MySQL dump 10.13  Distrib 8.0.42, for Win64 (x86_64)
--
-- Host: localhost    Database: complaint_portal
-- ------------------------------------------------------
-- Server version	8.0.42

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `complaint_history`
--

DROP TABLE IF EXISTS `complaint_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `complaint_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `complaint_id` int unsigned NOT NULL,
  `status` enum('pending','in_progress','resolved','rejected') NOT NULL,
  `note` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_complaint_id` (`complaint_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_complaint_history_complaints` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=102 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `complaint_history`
--

LOCK TABLES `complaint_history` WRITE;
/*!40000 ALTER TABLE `complaint_history` DISABLE KEYS */;
INSERT INTO `complaint_history` VALUES (1,3,'resolved','Complaint resolved successfully','2025-06-30 13:02:49'),(2,5,'in_progress','Complaint assigned to technician','2025-06-30 21:40:09'),(3,5,'resolved','Complaint resolved successfully','2025-06-30 21:41:49'),(4,5,'pending','Status changed to pending','2025-07-13 20:30:35'),(5,13,'resolved','Complaint resolved successfully','2025-07-13 21:01:35'),(6,16,'resolved','Complaint resolved successfully','2025-07-14 14:15:55'),(100,3,'resolved','Complaint resolved successfully','2025-06-30 13:02:49'),(101,17,'resolved','Complaint resolved successfully','2025-07-19 16:37:23');
/*!40000 ALTER TABLE `complaint_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `complaints`
--

DROP TABLE IF EXISTS `complaints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `complaints` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `token` varchar(64) NOT NULL,
  `room_no` varchar(20) NOT NULL,
  `hostel_type` enum('boys','girls') DEFAULT NULL,
  `category` enum('mess','carpenter','wifi','housekeeping','plumber','electrician','laundry','ac','other') NOT NULL,
  `description` text NOT NULL,
  `status` enum('pending','in_progress','resolved','rejected') NOT NULL DEFAULT 'pending',
  `technician_id` int unsigned DEFAULT NULL,
  `tech_note` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_unique` (`token`),
  KEY `idx_status` (`status`),
  KEY `idx_category` (`category`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_technician` (`technician_id`),
  KEY `fk_complaints_users` (`user_id`),
  CONSTRAINT `fk_complaints_technician` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_complaints_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `complaints`
--

LOCK TABLES `complaints` WRITE;
/*!40000 ALTER TABLE `complaints` DISABLE KEYS */;
INSERT INTO `complaints` VALUES (1,2,'3defbeccdd13ec681f62644cf4ca1b04','163','boys','plumber','Not working','pending',NULL,NULL,'2025-06-30 13:00:39','2025-06-30 13:00:39'),(2,2,'3b75d56e96fdb02da6840503a2bf49f4','737','boys','wifi','Not working','pending',NULL,NULL,'2025-06-30 13:01:31','2025-06-30 13:01:31'),(3,4,'51f60726add1c5326c84955b2221a75c','363',NULL,'wifi','yes','resolved',NULL,'No remarks provided','2025-06-30 13:02:00','2025-06-30 13:02:49'),(4,4,'aad47743c4e1df97f937f568f67b4d15','123',NULL,'wifi','yesysy','pending',3,'','2025-06-30 13:03:09','2025-07-19 16:36:10'),(5,5,'05740ebebf1aa42252f6b7b0f9b41ca5','cafe',NULL,'other','hello no light here','pending',6,'Compelted','2025-06-30 21:37:55','2025-07-13 20:30:35'),(6,2,'4b9bd90837ac16ca44ace3603346464b','68','boys','wifi','yes','pending',NULL,NULL,'2025-07-02 15:36:28','2025-07-02 15:36:28'),(7,7,'1514473ef321aa9ddbd8f4090f8d3514','101','boys','wifi','Test complaint from student','pending',6,'','2025-07-13 20:41:06','2025-07-19 13:36:57'),(8,9,'66fa4dd34d538db4593b151b53711f4c','101',NULL,'wifi','Test complaint from outsourced_vendor','pending',NULL,NULL,'2025-07-13 20:41:07','2025-07-13 20:41:07'),(9,10,'ba3e3559863286196147cba2e63eca4a','101',NULL,'wifi','Test complaint from faculty','pending',NULL,NULL,'2025-07-13 20:41:08','2025-07-13 20:41:08'),(10,11,'e04dcce0f0082ca8cf175615ccf541c6','101','boys','wifi','Test complaint from student','pending',NULL,NULL,'2025-07-13 20:43:09','2025-07-13 20:43:09'),(11,13,'5185171ecf34e56175115137bdc6b78d','101',NULL,'wifi','Test complaint from outsourced_vendor','pending',NULL,NULL,'2025-07-13 20:43:10','2025-07-13 20:43:10'),(12,14,'823794f011f70cb6cefcc0ac66c52ab2','101',NULL,'wifi','Test complaint from faculty','pending',NULL,NULL,'2025-07-13 20:43:10','2025-07-13 20:43:10'),(13,1,'1b55a010826fd0b7c1192cb488847565','director office',NULL,'wifi','not working','resolved',NULL,'Done','2025-07-13 20:53:37','2025-07-13 21:01:35'),(14,1,'53769045b6c278c46500057296cb5620','t678',NULL,'plumber','not','pending',NULL,NULL,'2025-07-13 20:56:48','2025-07-13 20:56:48'),(15,4,'80240a60d8d909e86b6c01e856a23059','T-67',NULL,'plumber','No water supply','pending',NULL,NULL,'2025-07-13 21:10:31','2025-07-13 21:10:31'),(16,5,'73ca202f900c1b470c0a837ca5e5181d','Cafe',NULL,'plumber','Not good','resolved',NULL,'Complete','2025-07-14 14:14:15','2025-07-14 14:15:55'),(17,15,'daa6585165268f733a2d06011d5e95f2','Mess',NULL,'plumber','Water leak','resolved',6,'','2025-07-14 14:30:50','2025-07-19 16:37:23');
/*!40000 ALTER TABLE `complaints` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `complaint_status_trigger` AFTER UPDATE ON `complaints` FOR EACH ROW BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO complaint_history (complaint_id, status, note, created_at)
        VALUES (NEW.id, NEW.status, 
                CASE 
                    WHEN NEW.status = 'in_progress' THEN 'Complaint assigned to technician'
                    WHEN NEW.status = 'resolved' THEN 'Complaint resolved successfully'
                    WHEN NEW.status = 'rejected' THEN 'Complaint rejected'
                    ELSE CONCAT('Status changed to ', NEW.status)
                END,
                NOW());
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `hostel_issue_votes`
--

DROP TABLE IF EXISTS `hostel_issue_votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hostel_issue_votes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `issue_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `vote_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_vote` (`issue_id`,`user_id`),
  KEY `idx_issue_id` (`issue_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_vote_time` (`vote_time`),
  CONSTRAINT `fk_hostel_issue_votes_issues` FOREIGN KEY (`issue_id`) REFERENCES `hostel_issues` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hostel_issue_votes_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hostel_issue_votes`
--

LOCK TABLES `hostel_issue_votes` WRITE;
/*!40000 ALTER TABLE `hostel_issue_votes` DISABLE KEYS */;
INSERT INTO `hostel_issue_votes` VALUES (1,1,2,'2025-07-02 15:37:07');
/*!40000 ALTER TABLE `hostel_issue_votes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hostel_issues`
--

DROP TABLE IF EXISTS `hostel_issues`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hostel_issues` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `hostel_type` enum('boys','girls') NOT NULL,
  `issue_type` enum('wifi','water','mess','electricity','cleanliness','other') NOT NULL,
  `status` enum('not_assigned','in_progress','resolved') NOT NULL DEFAULT 'not_assigned',
  `technician_id` int unsigned DEFAULT NULL,
  `tech_remarks` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hostel_type` (`hostel_type`),
  KEY `idx_issue_type` (`issue_type`),
  KEY `idx_status` (`status`),
  KEY `idx_technician` (`technician_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_hostel_issues_technician` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hostel_issues`
--

LOCK TABLES `hostel_issues` WRITE;
/*!40000 ALTER TABLE `hostel_issues` DISABLE KEYS */;
INSERT INTO `hostel_issues` VALUES (1,'boys','wifi','resolved',3,'','2025-07-02 15:37:07','2025-07-02 15:42:46');
/*!40000 ALTER TABLE `hostel_issues` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `security_logs`
--

DROP TABLE IF EXISTS `security_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `security_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int unsigned DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `target` varchar(255) DEFAULT NULL,
  `details` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_security_logs_users` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `security_logs`
--

LOCK TABLES `security_logs` WRITE;
/*!40000 ALTER TABLE `security_logs` DISABLE KEYS */;
INSERT INTO `security_logs` VALUES (1,1,'update_profile',NULL,'Profile updated for admin: ','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36','2025-07-19 15:27:24'),(2,1,'update_profile',NULL,'Profile updated for admin: ','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36','2025-07-19 15:27:31'),(3,1,'update_profile',NULL,'Profile updated for admin: ','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36','2025-07-19 15:27:55'),(4,1,'update_profile',NULL,'Profile updated for admin: ','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36','2025-07-19 15:27:58'),(5,1,'add_admin','admin2','Created superadmin account: registrar (admin2)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36','2025-07-19 16:13:06'),(6,16,'update_profile',NULL,'Profile updated for admin: ','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36','2025-07-19 16:36:50');
/*!40000 ALTER TABLE `security_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `special_codes`
--

DROP TABLE IF EXISTS `special_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `special_codes` (
  `code` varchar(50) NOT NULL,
  `role` enum('student','faculty','nonteaching','technician','outsourced_vendor') NOT NULL,
  `specialization` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expiry_date` datetime DEFAULT NULL,
  `generated_by` int unsigned NOT NULL,
  PRIMARY KEY (`code`),
  KEY `idx_role` (`role`),
  KEY `idx_expiry` (`expiry_date`),
  KEY `fk_special_codes_users` (`generated_by`),
  CONSTRAINT `fk_special_codes_users` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `special_codes`
--

LOCK TABLES `special_codes` WRITE;
/*!40000 ALTER TABLE `special_codes` DISABLE KEYS */;
INSERT INTO `special_codes` VALUES ('7WV3','technician','wifi','2025-06-30 12:57:06','2025-07-30 09:27:06',1),('BPK4','student',NULL,'2025-06-30 12:56:50','2025-07-30 09:26:50',1),('FKBU','technician',NULL,'2025-07-13 20:27:25','2025-08-12 16:57:25',1),('LKBE','faculty',NULL,'2025-06-30 12:56:56','2025-07-30 09:26:56',1),('WPVN','outsourced_vendor',NULL,'2025-06-30 21:01:55','2025-07-30 17:31:55',1);
/*!40000 ALTER TABLE `special_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suggestion_votes`
--

DROP TABLE IF EXISTS `suggestion_votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suggestion_votes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `suggestion_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `vote_type` enum('upvote','downvote') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_vote` (`suggestion_id`,`user_id`),
  KEY `idx_suggestion_id` (`suggestion_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_vote_type` (`vote_type`),
  CONSTRAINT `fk_suggestion_votes_suggestions` FOREIGN KEY (`suggestion_id`) REFERENCES `suggestions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_suggestion_votes_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suggestion_votes`
--

LOCK TABLES `suggestion_votes` WRITE;
/*!40000 ALTER TABLE `suggestion_votes` DISABLE KEYS */;
INSERT INTO `suggestion_votes` VALUES (14,3,2,'upvote','2025-07-14 14:09:24'),(16,3,15,'upvote','2025-07-14 14:31:35');
/*!40000 ALTER TABLE `suggestion_votes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suggestions`
--

DROP TABLE IF EXISTS `suggestions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suggestions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `title` varchar(100) NOT NULL,
  `suggestion` text NOT NULL,
  `category` enum('academics','infrastructure','hostel','mess','sports','other') NOT NULL DEFAULT 'other',
  `status` enum('pending','approved','rejected','implemented') NOT NULL DEFAULT 'pending',
  `admin_remark` text,
  `upvotes` int unsigned NOT NULL DEFAULT '0',
  `downvotes` int unsigned NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_category` (`category`),
  KEY `idx_status` (`status`),
  KEY `idx_admin_remark` (`admin_remark`(255)),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_suggestions_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suggestions`
--

LOCK TABLES `suggestions` WRITE;
/*!40000 ALTER TABLE `suggestions` DISABLE KEYS */;
INSERT INTO `suggestions` VALUES (2,5,'cafeteria','ac should be added','infrastructure','pending','',0,0,'2025-06-30 21:55:45','2025-07-14 14:13:26'),(3,2,'Mess food','Quality is very bad','mess','pending','will work on it',2,0,'2025-07-14 12:31:14','2025-07-14 14:31:35');
/*!40000 ALTER TABLE `suggestions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_notifications`
--

DROP TABLE IF EXISTS `user_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_notifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `complaint_token` varchar(64) DEFAULT NULL,
  `hostel_issue_id` int unsigned DEFAULT NULL,
  `type` enum('resolved','hostel_resolved') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_complaint_token` (`complaint_token`),
  KEY `idx_hostel_issue_id` (`hostel_issue_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_notifications_complaints` FOREIGN KEY (`complaint_token`) REFERENCES `complaints` (`token`) ON DELETE SET NULL,
  CONSTRAINT `fk_notifications_hostel_issues` FOREIGN KEY (`hostel_issue_id`) REFERENCES `hostel_issues` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notifications_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_notifications`
--

LOCK TABLES `user_notifications` WRITE;
/*!40000 ALTER TABLE `user_notifications` DISABLE KEYS */;
INSERT INTO `user_notifications` VALUES (1,5,'05740ebebf1aa42252f6b7b0f9b41ca5',NULL,'resolved','2025-06-30 21:48:59'),(2,5,'05740ebebf1aa42252f6b7b0f9b41ca5',NULL,'resolved','2025-06-30 21:49:03'),(3,4,'51f60726add1c5326c84955b2221a75c',NULL,'resolved','2025-07-13 21:08:46'),(4,4,'51f60726add1c5326c84955b2221a75c',NULL,'resolved','2025-07-13 21:08:48'),(5,15,'daa6585165268f733a2d06011d5e95f2',NULL,'resolved','2025-07-19 16:37:41'),(6,15,'daa6585165268f733a2d06011d5e95f2',NULL,'resolved','2025-07-19 16:37:44');
/*!40000 ALTER TABLE `user_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(10) NOT NULL,
  `role` enum('student','faculty','nonteaching','technician','superadmin','outsourced_vendor') NOT NULL,
  `special_code` varchar(50) NOT NULL,
  `specialization` varchar(50) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `hostel_type` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username_unique` (`username`),
  KEY `idx_role` (`role`),
  KEY `idx_specialization` (`specialization`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'System Administrator','0000000000','superadmin','ADMIN001',NULL,'admin','$2y$10$WLJRU.XmNeEKTzpM3wEQzO/XG3OiB8D/7LPTSBgwKTe17TG4MeMBm','2025-07-19 16:23:22',NULL),(2,'Harkamal','6373839202','student','BPK4',NULL,'Student','$2y$10$0rt1BZLTML6DXiYAQuoi8eg8dT/1C9FtIaqWiYHHu6DueJ8WNz.1y','2025-06-30 12:58:44','boys'),(3,'Naresh sir','6383929393','technician','7WV3','wifi','Wifi','$2y$10$stQDHo9JOQ/bnCLnQ805heRtncTp6DuKhm4k4moPohs5PwwD/o0uG','2025-06-30 12:59:24',NULL),(4,'Mohanty sir','5383939393','faculty','LKBE',NULL,'faculty','$2y$10$s77uRC42gp7ENlOwEptNsepHQApK5n.KgDnFNj0wlBMMjdU.hxCv6','2025-06-30 13:00:07',NULL),(5,'Cafeteria','6373839939','outsourced_vendor','WPVN','cafeteria','Cafeteria','$2y$10$LjAc5yyu0i9fRY31DdDtZuWiDPaXxj5DpjQt0fNJHG8WYTrACXPp6','2025-06-30 21:04:36',NULL),(6,'plumber','6733939393','technician','FKBU','plumber','plumber','$2y$10$Z3H9XM5P0JozqoBLENPhfuBVCE7oCyqocsg7VVO00dRLTUPl2TGa2','2025-07-13 20:29:20',NULL),(7,'Student Test','9222000919','student','BPK4',NULL,'student_1e00f65a','$2y$10$JIv/K/gqJIOsvIt8D0617e3mDUpnZ8om3ybkOLlMVjmmKVP3dO5bm','2025-07-13 20:41:06','boys'),(8,'Technician Test','9125582172','technician','FKBU','wifi','technician_0da7fd30','$2y$10$/M5wdwQJeWz3W.K8ON5zFeJnO5XysS0SWlsS8Q9g14MCEBjcTTHQK','2025-07-13 20:41:07',NULL),(9,'Outsourced_vendor Test','9594021097','outsourced_vendor','WPVN','mess','outsourced_vendor_a9b87bed','$2y$10$jmbn3UeccQyeJWB.rh/gbO47M3oX6DIiVnGLNlS4nOKJ3gV7n2O0a','2025-07-13 20:41:07',NULL),(10,'Faculty Test','9859191836','faculty','LKBE',NULL,'faculty_5db4d9d3','$2y$10$dE.513FHdH.nj3a8.gzKLOJqTf1iPaKchwY4iwk8pqvBWwYdVCxui','2025-07-13 20:41:07',NULL),(11,'Student Test','9859231077','student','BPK4',NULL,'student_312055dc','$2y$10$FktrbIS2yRkLO0REOjJfDeF2dzXhGPzMbYS6CzDRRrC/qgtkBl9V.','2025-07-13 20:43:09','boys'),(12,'Technician Test','9372323308','technician','FKBU','wifi','technician_5c3a3d33','$2y$10$6XXNbDkIA5ot6Gcijg5hPu0oJMpcItgmCHOulT1mgNyMpu7QulyF.','2025-07-13 20:43:09',NULL),(13,'Outsourced_vendor Test','9877928755','outsourced_vendor','WPVN','mess','outsourced_vendor_0bd37a91','$2y$10$Cp7HZ0I8cXGlWsiEVjdqau0dgrY8XDK4M9wxdPi5Yu/TAGuTmvADC','2025-07-13 20:43:10',NULL),(14,'Faculty Test','9468073351','faculty','LKBE',NULL,'faculty_7d8a2aba','$2y$10$PeW5JgftrmvrGx5bOwTUZOwLrC9ZJ1WZ2fAvxOVIAeOy0TCc7CrmS','2025-07-13 20:43:10',NULL),(15,'Mess','5788679967','outsourced_vendor','WPVN','mess','Mess','$2y$10$H0JDNO4kUt3wacSNFRFvPONK0aKbXzuMYoWQZhpGlPRtSFSqvLS3G','2025-07-14 14:30:16',NULL),(16,'registrar','7899265345','superadmin','ADMIN8A5202',NULL,'admin2','$2y$10$DL5JO9FAVmz6gLUTz8Xn6eF0W0vwyjategF8YxyfN.LJnGHJDwA0O','2025-07-19 16:13:06',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-07-21 17:50:03
