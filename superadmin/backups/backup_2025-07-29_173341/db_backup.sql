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
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `complaint_history`
--

LOCK TABLES `complaint_history` WRITE;
/*!40000 ALTER TABLE `complaint_history` DISABLE KEYS */;
INSERT INTO `complaint_history` VALUES (1,1,'pending','Auto assigned to technician ID 3 for wifi category','2025-07-29 15:31:25'),(2,2,'pending','Auto assigned to technician ID 3 for wifi category','2025-07-29 15:32:57'),(3,3,'pending','Auto assigned to technician ID 3 for wifi category','2025-07-29 15:33:40'),(5,8,'pending','Auto assigned to technician ID 7 for housekeeping category','2025-07-29 15:49:08'),(6,9,'pending','Auto assigned to technician ID 9 for mess category','2025-07-29 15:49:33'),(7,1,'pending','Reassigned to technician ID 11 for wifi category (reason: Technician went offline)','2025-07-29 16:09:34'),(8,2,'pending','Reassigned to technician ID 11 for wifi category (reason: Technician went offline)','2025-07-29 16:09:34'),(9,3,'pending','Reassigned to technician ID 11 for wifi category (reason: Technician went offline)','2025-07-29 16:09:34'),(10,10,'pending','Auto assigned to technician ID 4 for plumber category','2025-07-29 16:14:58'),(11,1,'resolved','Complaint resolved successfully','2025-07-29 16:46:09'),(12,6,'resolved','Complaint resolved successfully','2025-07-29 16:48:29'),(13,3,'resolved','Complaint resolved successfully','2025-07-29 16:49:20'),(14,11,'pending','Auto assigned to technician ID 7 for housekeeping category','2025-07-29 16:51:54'),(15,11,'resolved','Complaint resolved successfully','2025-07-29 16:55:02'),(16,7,'resolved','Complaint resolved successfully','2025-07-29 16:56:11'),(17,2,'resolved','Complaint resolved successfully','2025-07-29 16:56:34'),(18,5,'resolved','Complaint resolved successfully','2025-07-29 17:00:17'),(19,8,'in_progress','Complaint assigned to technician','2025-07-29 17:00:26'),(20,8,'resolved','Complaint resolved successfully','2025-07-29 17:01:01'),(21,52,'pending','Auto assigned to technician ID 9 for mess category','2025-07-29 17:17:27'),(22,53,'in_progress','Technician 4 acknowledged the plumbing issue','2025-07-29 17:17:27'),(23,54,'resolved','Complaint resolved successfully by technician ID 3','2025-07-29 17:17:27'),(24,55,'pending','Auto assigned to technician ID 7 for housekeeping','2025-07-29 17:18:25'),(25,56,'in_progress','Electrician (ID 14) notified for light fix','2025-07-29 17:18:25'),(26,57,'resolved','Resolved by laundry staff (ID 15)','2025-07-29 17:18:25'),(27,58,'pending','Auto assigned to technician ID 14 for AC issue','2025-07-29 17:18:25'),(28,59,'pending','Auto assigned to technician ID 20 for carpenter task','2025-07-29 17:18:25'),(29,60,'pending','Auto assigned to technician ID 3 for wifi category','2025-07-29 17:23:59'),(30,60,'resolved','Complaint resolved successfully','2025-07-29 17:24:47');
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
  `assignment_type` enum('auto_assigned','admin_assigned') DEFAULT 'auto_assigned',
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
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `complaints`
--

LOCK TABLES `complaints` WRITE;
/*!40000 ALTER TABLE `complaints` DISABLE KEYS */;
INSERT INTO `complaints` VALUES (1,2,'2a3e79e8a1549a69dba2cb934f7a6cc2','143','boys','wifi','No signal in hostel','resolved',3,'No remarks provided','auto_assigned','2025-07-29 15:31:25','2025-07-29 16:46:09'),(2,6,'2bbb4998487036f7d902279fbc1a630b','Cafeteria',NULL,'wifi','No signal','resolved',3,'👍','admin_assigned','2025-07-29 15:32:57','2025-07-29 16:56:34'),(3,5,'135b6fdbed426751605c80a271bdb6a3','H142',NULL,'wifi','No signal','resolved',3,'Compelted','auto_assigned','2025-07-29 15:33:40','2025-07-29 16:49:20'),(5,2,'b8f3b8ba4a52eb3a8f5ffbfa85ee210c','143','boys','housekeeping','No room cleaning from last 4 weeks','resolved',7,'Done','auto_assigned','2025-07-29 15:36:49','2025-07-29 17:00:17'),(6,5,'bcf8daf8a87dc1551f953ef38f6edcfd','H212',NULL,'other','Cafeteria do not close on time','resolved',3,'Compelted repair','admin_assigned','2025-07-29 15:39:11','2025-07-29 16:48:29'),(7,6,'21f9ce9bfee16353f4e8b696230a525d','Cafeteria',NULL,'electrician','No electricity from last 24 hrs','resolved',3,'Done','admin_assigned','2025-07-29 15:40:02','2025-07-29 16:56:11'),(8,8,'15910ba447306f2cf5aa2c2f6e325827','56','girls','housekeeping','No cleaning of rooms','resolved',7,'Done','auto_assigned','2025-07-29 15:49:08','2025-07-29 17:01:01'),(9,8,'20cea347b12e47b5c0ad15ed7e08e7c2','67','girls','mess','Food quality is below average','pending',9,NULL,'auto_assigned','2025-07-29 15:49:33','2025-07-29 15:49:33'),(10,2,'697dc0a4698520281cf47cf91d2c25f9','143','boys','plumber','No water supply in hostel','pending',4,NULL,'auto_assigned','2025-07-29 16:14:58','2025-07-29 16:14:58'),(11,3,'1af918602a6af289d6b7b0989983b66b','H343',NULL,'housekeeping','No cleaning','resolved',7,'completed','auto_assigned','2025-07-29 16:51:54','2025-07-29 16:55:02'),(52,13,'CMP_A1B2C3D4','G207','girls','mess','Food served late and cold.','pending',9,NULL,'auto_assigned','2025-07-29 17:17:27','2025-07-29 17:17:27'),(53,16,'CMP_E5F6G7H8','B101','boys','plumber','Water leakage in the bathroom.','in_progress',4,'Investigating leak','auto_assigned','2025-07-29 17:17:27','2025-07-29 17:17:27'),(54,19,'CMP_I9J0K1L2','G305','girls','wifi','Slow internet in room.','resolved',3,'Replaced router','admin_assigned','2025-07-29 17:17:27','2025-07-29 17:17:27'),(55,10,'CMP_M1N2O3P4','G110','girls','housekeeping','Dust buildup under beds not cleaned in weeks.','pending',7,NULL,'auto_assigned','2025-07-29 17:18:25','2025-07-29 17:18:25'),(56,2,'CMP_Q5R6S7T8','B203','boys','electrician','Light not working in study area.','in_progress',14,'Will be fixed today','admin_assigned','2025-07-29 17:18:25','2025-07-29 17:18:25'),(57,8,'CMP_U9V0W1X2','G102','girls','laundry','Wrong clothes returned.','resolved',15,'Issue verified with staff','auto_assigned','2025-07-29 17:18:25','2025-07-29 17:18:25'),(58,19,'CMP_Y3Z4A5B6','G309','girls','ac','AC not cooling effectively.','pending',14,NULL,'auto_assigned','2025-07-29 17:18:25','2025-07-29 17:18:25'),(59,16,'CMP_C7D8E9F0','B101','boys','carpenter','Cupboard door detached.','pending',20,NULL,'auto_assigned','2025-07-29 17:18:25','2025-07-29 17:18:25'),(60,1,'67815231b260d91597eb47a785b46972','h676',NULL,'wifi','no signal','resolved',11,'Done updated lastest','auto_assigned','2025-07-29 17:23:59','2025-07-29 17:24:47');
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hostel_issue_votes`
--

LOCK TABLES `hostel_issue_votes` WRITE;
/*!40000 ALTER TABLE `hostel_issue_votes` DISABLE KEYS */;
INSERT INTO `hostel_issue_votes` VALUES (1,1,2,'2025-07-29 15:42:45'),(2,2,8,'2025-07-29 15:49:55'),(3,2,10,'2025-07-29 15:52:49');
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
  `assignment_type` enum('auto_assigned','admin_assigned') DEFAULT 'auto_assigned',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hostel_type` (`hostel_type`),
  KEY `idx_issue_type` (`issue_type`),
  KEY `idx_status` (`status`),
  KEY `idx_technician` (`technician_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_hostel_issues_technician` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hostel_issues`
--

LOCK TABLES `hostel_issues` WRITE;
/*!40000 ALTER TABLE `hostel_issues` DISABLE KEYS */;
INSERT INTO `hostel_issues` VALUES (1,'boys','mess','resolved',9,'inspection done ','auto_assigned','2025-07-29 15:42:45','2025-07-29 16:59:03'),(2,'girls','water','resolved',4,'new pipes fixed','auto_assigned','2025-07-29 15:49:55','2025-07-29 17:01:45');
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
INSERT INTO `security_logs` VALUES (1,1,'auto_assignment','hostel_issue_5','{\"type\":\"auto_assigned\",\"item_id\":5,\"technician_id\":7,\"category\":\"housekeeping\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-29 15:55:35'),(2,1,'auto_assignment','hostel_issue_1','{\"type\":\"auto_assigned\",\"item_id\":1,\"technician_id\":9,\"category\":\"mess\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-29 15:55:35'),(3,1,'technician_status_toggle','Hitesh kumar','Set to offline','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36','2025-07-29 16:03:23'),(4,1,'auto_assignment','hostel_issue_8','{\"type\":\"auto_assigned\",\"item_id\":8,\"technician_id\":7,\"category\":\"housekeeping\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-29 16:11:12'),(5,1,'technician_status_toggle','Hitesh kumar','Set to online','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36','2025-07-29 16:43:31'),(6,1,'technician_status_toggle','Hitesh kumar','Set to offline','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36','2025-07-29 16:44:57');
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
INSERT INTO `special_codes` VALUES ('2VPL','outsourced_vendor',NULL,'2025-07-29 15:26:05','2025-08-28 11:56:05',1),('BLHQ','student',NULL,'2025-07-29 15:22:26','2025-08-28 11:52:26',1),('K75E','faculty',NULL,'2025-07-29 15:25:13','2025-08-28 11:55:13',1),('TZJJ','technician',NULL,'2025-07-29 15:23:31','2025-08-28 11:53:31',1);
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
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suggestion_votes`
--

LOCK TABLES `suggestion_votes` WRITE;
/*!40000 ALTER TABLE `suggestion_votes` DISABLE KEYS */;
INSERT INTO `suggestion_votes` VALUES (2,1,6,'upvote','2025-07-29 15:40:28'),(3,2,5,'upvote','2025-07-29 15:41:44'),(4,1,5,'upvote','2025-07-29 15:41:50'),(5,2,6,'upvote','2025-07-29 15:42:05'),(8,2,2,'upvote','2025-07-29 15:42:32'),(9,2,8,'upvote','2025-07-29 15:50:08'),(10,1,8,'upvote','2025-07-29 15:50:10'),(11,3,8,'upvote','2025-07-29 15:51:11'),(12,2,10,'upvote','2025-07-29 15:53:01'),(13,3,10,'upvote','2025-07-29 15:53:03'),(15,4,16,'upvote','2025-07-29 17:17:27'),(16,4,10,'upvote','2025-07-29 17:17:27'),(17,5,13,'upvote','2025-07-29 17:17:27'),(18,5,8,'upvote','2025-07-29 17:17:27'),(19,6,2,'upvote','2025-07-29 17:18:25'),(20,6,13,'upvote','2025-07-29 17:18:25'),(21,7,8,'upvote','2025-07-29 17:18:25'),(22,7,19,'upvote','2025-07-29 17:18:25'),(23,8,10,'upvote','2025-07-29 17:18:25'),(24,8,14,'upvote','2025-07-29 17:18:25');
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suggestions`
--

LOCK TABLES `suggestions` WRITE;
/*!40000 ALTER TABLE `suggestions` DISABLE KEYS */;
INSERT INTO `suggestions` VALUES (1,2,'Room cleaning','There should be room cleaning every week properly to keep the rooms clean','hostel','pending',NULL,3,0,'2025-07-29 15:38:19','2025-07-29 15:53:10'),(2,5,'More books in library','Management should bring more books to library that are not related to study only but bring better life skills in students','academics','approved','',5,0,'2025-07-29 15:41:40','2025-07-29 17:32:40'),(3,8,'AC in girls hostel','As the summer seasons comes the temperature becomes intolerable so I request to please implement ac in girls hostel','hostel','pending','will work on it',2,0,'2025-07-29 15:51:08','2025-07-29 17:32:55'),(4,13,'Improve mess timing','Students face delays in food service. Timings should be more fixed.','mess','pending',NULL,0,0,'2025-07-29 17:17:27','2025-07-29 17:17:27'),(5,19,'Wi-Fi upgrade','Install more routers on top floors to improve speed and signal.','hostel','pending',NULL,0,0,'2025-07-29 17:17:27','2025-07-29 17:17:27'),(6,16,'Fix hostel ACs','ACs in boys hostel are old and ineffective. Upgrade needed.','hostel','pending',NULL,0,0,'2025-07-29 17:18:25','2025-07-29 17:18:25'),(7,10,'Better mess hygiene','Mess workers should wear gloves and caps while serving.','mess','pending','vendor has been informed',0,0,'2025-07-29 17:18:25','2025-07-29 17:33:12'),(8,5,'Hostel events calendar','Pin a monthly calendar in hostel with upcoming activities.','infrastructure','pending',NULL,0,0,'2025-07-29 17:18:25','2025-07-29 17:18:25');
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
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_notifications`
--

LOCK TABLES `user_notifications` WRITE;
/*!40000 ALTER TABLE `user_notifications` DISABLE KEYS */;
INSERT INTO `user_notifications` VALUES (1,6,'2bbb4998487036f7d902279fbc1a630b',NULL,'resolved','2025-07-29 16:57:53'),(2,6,'21f9ce9bfee16353f4e8b696230a525d',NULL,'resolved','2025-07-29 16:57:53'),(4,6,'2bbb4998487036f7d902279fbc1a630b',NULL,'resolved','2025-07-29 16:57:55'),(5,6,'21f9ce9bfee16353f4e8b696230a525d',NULL,'resolved','2025-07-29 16:57:55'),(7,2,'2a3e79e8a1549a69dba2cb934f7a6cc2',NULL,'resolved','2025-07-29 16:59:19'),(8,2,NULL,1,'hostel_resolved','2025-07-29 16:59:19'),(9,2,'2a3e79e8a1549a69dba2cb934f7a6cc2',NULL,'resolved','2025-07-29 16:59:23'),(10,2,NULL,1,'hostel_resolved','2025-07-29 16:59:23'),(11,8,'15910ba447306f2cf5aa2c2f6e325827',NULL,'resolved','2025-07-29 17:02:08'),(12,8,NULL,2,'hostel_resolved','2025-07-29 17:02:08'),(13,8,'15910ba447306f2cf5aa2c2f6e325827',NULL,'resolved','2025-07-29 17:02:15'),(14,8,NULL,2,'hostel_resolved','2025-07-29 17:02:15');
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
  `is_online` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1 for online, 0 for offline',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username_unique` (`username`),
  KEY `idx_role` (`role`),
  KEY `idx_specialization` (`specialization`),
  KEY `idx_is_online` (`is_online`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'System Administrator','0000000000','superadmin','ADMIN001',NULL,'admin','$2y$10$5vvE6YDUHCT2OE/CHsTubery1flAjjeGq4FH4xE39KOaoqe4oxUb.','2025-07-29 15:21:13',NULL,1),(2,'Harkamal','0000000001','student','BLHQ',NULL,'Student','$2y$10$zzpQz6hjbE1xFNoBiP2fC.VcW/ukYwW.u3jzkewd0FexxPp2DsCPe','2025-07-29 15:23:14','boys',1),(3,'Hitesh kumar','0000000002','technician','TZJJ','wifi','Wifi','$2y$10$bZqgs3d61KXKoqQkGrsuqefJZGWyZ24ZmFtt3SjCJAlR1lOVjJpJS','2025-07-29 15:24:15',NULL,1),(4,'Rohit verma','0000000003','technician','TZJJ','plumber','Plumber','$2y$10$YXhVBNDbk/G2mym3S0AjveLXC4HbDJqlfwftxdYYedVuai2eVbMqa','2025-07-29 15:24:57',NULL,1),(5,'Mohanty sir','0000000004','faculty','K75E',NULL,'Faculty','$2y$10$WTn2Yswwi90YqnSouNYlIe1bWVWIAGD0EgTiDIZ3wL6vYWRoiTVyO','2025-07-29 15:25:55',NULL,1),(6,'Hemant singh','0000000008','outsourced_vendor','2VPL','cafeteria','cafe','$2y$10$3ZJpRmNBqXPX8UxHfCzcNO8EyCMDfmpUIA9ZyqkMEIfUhc1sG8yYy','2025-07-29 15:28:34',NULL,1),(7,'Sneha verma','9999999999','technician','TZJJ','housekeeping','House','$2y$10$yhHnTUNcjLs4cNr5L48no.Z2copChqwDT52mRgVxO/i3nfWryJ/4a','2025-07-29 15:46:25',NULL,1),(8,'Ayushi','8888888888','student','BLHQ',NULL,'Student2','$2y$10$a6pjktxwPUWCIUUBMpb4LeippWTNOR8QYOE.OQmHV6jgIXrAn0BwO','2025-07-29 15:47:08','girls',1),(9,'Siya aggarwal','6666777777','technician','TZJJ','mess','Mess','$2y$10$y4PmgTprAMRubKbufxaEleSJKVQEh8TXNzRHt0/A.daRjBx9Pt.VO','2025-07-29 15:48:19',NULL,1),(10,'Siya aggarwal','5567576676','student','BLHQ',NULL,'Student3','$2y$10$TW5i53.3DfGZjON/H1bMK.d/5W33lL5M/KzJyIh4msgm.tP.hm8Qq','2025-07-29 15:52:33','girls',1),(11,'Naresh sir','6463737373','technician','TZJJ','wifi','Wifi2','$2y$10$0xggh2HcywCOPCN1auyPfODfJoG3Y0A4sKC09an7R7WC90vHq4try','2025-07-29 16:08:56',NULL,1),(12,'Prakash Jha','7000000001','faculty','K75E',NULL,'ProfJha','$2y$10$K35p6UaK9Ljg3sQ/jOTlAuQy6ZCx4whMx6uixZApplXvytH0RNBXG','2025-07-29 17:01:00',NULL,1),(13,'Tina Sharma','7000000002','student','BLHQ',NULL,'tina_s','$2y$10$Tm5jJfnzUc/32FLH0A6HDOrqnvOY2GH/FVXMcUdsQUgMS1JjHNSO.','2025-07-29 17:01:30','girls',1),(14,'Amit Joshi','7000000003','technician','TZJJ','electrician','ElectricAmit','$2y$10$2RVUz5JcmLBv5J63gXCU3e1SbwRkXEXINPrVnGcl7jicdyUEOw1zS','2025-07-29 17:02:00',NULL,1),(15,'Reena Kapoor','7000000004','outsourced_vendor','2VPL','laundry','ReenaK','$2y$10$e7kE53PbTk7XWyO0A9zMnOY8H52nLODuJTDV8iYQZyyu9iZ3RwkmW','2025-07-29 17:02:30',NULL,1),(16,'Karan Mehta','7000000005','student','BLHQ',NULL,'karan_m','$2y$10$TpTc7plYJzT1qqhv7VheLu.4UqMuJGkPUJPizAUeNhRLkz8Afzh9G','2025-07-29 17:03:00','boys',1),(17,'Geeta Rani','7000000006','technician','TZJJ','laundry','GeetaR','$2y$10$OHfIhzGgERXPoGxilBB5qubkAcmRZhiRUOfwKrVaVXq3EF.CeFQ1a','2025-07-29 17:03:30',NULL,1),(18,'Ravi Chauhan','7000000007','faculty','K75E',NULL,'RaviC','$2y$10$nIYDRIR4UZbbAU5EjKONvOoc1RRBEfQjAq6g4nNleFiZ2RMyHdfdG','2025-07-29 17:04:00',NULL,1),(19,'Nisha Verma','7000000008','student','BLHQ',NULL,'nisha_v','$2y$10$nSKQfRI78OaHoEHeLpUn3uFVCN8u6WaYqU7Y4WpiTSwI7BuTWYz8O','2025-07-29 17:04:30','girls',1),(20,'Rajeev Singh','7000000009','technician','TZJJ','carpenter','RajeevS','$2y$10$t4GZ8cXM2SdnVd04HfO9ZOK9kH9OOnY3J5UuKMyDgOzqjylVtbFJK','2025-07-29 17:05:00',NULL,1),(21,'Deepak Yadav','7000000010','outsourced_vendor','2VPL','stationery','deep_y','$2y$10$ZIQ9JG45Zj98IDZ15py2E.TOMMGvAwCrM2kcp3cbhAWMJdZp2Pciq','2025-07-29 17:05:30',NULL,1);
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

-- Dump completed on 2025-07-29 17:33:41
