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
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `complaint_history`
--

LOCK TABLES `complaint_history` WRITE;
/*!40000 ALTER TABLE `complaint_history` DISABLE KEYS */;
INSERT INTO `complaint_history` VALUES (1,1,'in_progress','Plumber is inspecting the issue.','2025-07-28 01:46:52'),(2,2,'pending','Awaiting technician assignment.','2025-07-28 01:46:52'),(3,3,'pending','Complaint received and logged.','2025-07-28 01:50:24'),(4,3,'in_progress','Technician assigned and inspecting.','2025-07-28 01:50:24'),(5,4,'pending','Complaint received and logged.','2025-07-28 01:50:24'),(6,5,'pending','Complaint received and logged.','2025-07-28 01:50:24'),(7,5,'resolved','Issue resolved and closed.','2025-07-28 01:50:24'),(8,6,'pending','Complaint received and logged.','2025-07-28 01:50:24'),(9,6,'in_progress','Technician assigned and inspecting.','2025-07-28 01:50:24'),(10,7,'pending','Complaint received and logged.','2025-07-28 01:50:24'),(11,8,'pending','Complaint received and logged.','2025-07-28 01:50:24'),(12,8,'in_progress','Technician assigned and inspecting.','2025-07-28 01:50:24'),(13,9,'pending','Complaint received and logged.','2025-07-28 01:50:24'),(14,9,'resolved','Issue resolved and closed.','2025-07-28 01:50:24'),(15,10,'pending','Complaint received and logged.','2025-07-28 01:50:24'),(16,11,'pending','Complaint received and logged.','2025-07-28 01:50:24'),(17,11,'in_progress','Technician assigned and inspecting.','2025-07-28 01:50:24'),(18,12,'pending','Complaint received and logged.','2025-07-28 01:50:24'),(19,13,'pending','Complaint received and logged.','2025-07-28 01:50:24'),(20,13,'resolved','Issue resolved and closed.','2025-07-28 01:50:24'),(21,14,'pending','Complaint received and logged.','2025-07-28 01:50:24'),(22,14,'in_progress','Technician assigned and inspecting.','2025-07-28 01:50:24'),(23,15,'pending','Complaint received and logged.','2025-07-28 01:50:24'),(24,16,'pending','Complaint received and logged.','2025-07-28 01:50:24'),(25,16,'resolved','Issue resolved and closed.','2025-07-28 01:50:24'),(26,17,'pending','Complaint received and logged.','2025-07-28 01:50:24'),(27,17,'in_progress','Technician assigned and inspecting.','2025-07-28 01:50:24'),(28,18,'pending','Complaint received and logged.','2025-07-28 01:50:24'),(29,19,'pending','Complaint received and logged.','2025-07-28 01:50:24'),(30,19,'in_progress','Technician assigned and inspecting.','2025-07-28 01:50:24'),(31,20,'pending','Complaint received and logged.','2025-07-28 01:50:24'),(32,20,'resolved','Issue resolved and closed.','2025-07-28 01:50:24'),(34,22,'pending','Admin assigned to technician ID 6 for wifi category','2025-07-28 15:53:31'),(40,28,'pending','Auto assigned to technician ID 6 for wifi category','2025-07-28 16:24:27'),(41,29,'pending','Auto assigned to technician ID 6 for wifi category','2025-07-28 16:26:20'),(42,30,'pending','Auto assigned to technician ID 6 for wifi category','2025-07-28 18:49:31'),(45,34,'pending','Auto assigned to technician ID 4 for plumber category','2025-07-28 20:37:57'),(46,34,'resolved','Complaint resolved successfully','2025-07-28 20:39:36');
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
  `assignment_type` enum('auto_assigned','admin_assigned','manual') DEFAULT 'manual',
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
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `complaints`
--

LOCK TABLES `complaints` WRITE;
/*!40000 ALTER TABLE `complaints` DISABLE KEYS */;
INSERT INTO `complaints` VALUES (1,2,'CPLNT-001','B101','boys','plumber','Leaking pipe in sink','in_progress',4,NULL,'admin_assigned','2025-07-28 01:46:52','2025-07-28 01:46:52'),(2,3,'CPLNT-002','G201','girls','electrician','Tube light flickering','pending',5,NULL,'auto_assigned','2025-07-28 01:46:52','2025-07-28 01:49:19'),(3,2,'CPLNT-003','B202','boys','plumber','Leaking pipe or tap issue','in_progress',4,NULL,'admin_assigned','2025-07-28 01:48:26','2025-07-28 01:48:26'),(4,3,'CPLNT-004','G105','girls','electrician','Electrical socket or light issue','pending',5,NULL,'auto_assigned','2025-07-28 01:48:26','2025-07-28 01:49:19'),(5,2,'CPLNT-005','B120','boys','mess','Food quality complaints','resolved',5,NULL,'manual','2025-07-28 01:48:26','2025-07-28 01:48:26'),(6,3,'CPLNT-006','G210','girls','wifi','Internet is not working properly','in_progress',4,NULL,'auto_assigned','2025-07-28 01:48:26','2025-07-28 16:19:59'),(7,2,'CPLNT-007','B118','boys','carpenter','Broken furniture or bed','pending',7,NULL,'auto_assigned','2025-07-28 01:48:26','2025-07-28 01:52:51'),(8,3,'CPLNT-008','G230','girls','housekeeping','Dustbins not cleaned or floors dirty','in_progress',5,NULL,'auto_assigned','2025-07-28 01:48:26','2025-07-28 01:51:46'),(9,2,'CPLNT-009','B140','boys','plumber','Leaking pipe or tap issue','resolved',4,NULL,'manual','2025-07-28 01:48:26','2025-07-28 01:48:26'),(10,3,'CPLNT-010','G220','girls','wifi','Internet is not working properly','pending',6,NULL,'auto_assigned','2025-07-28 01:48:26','2025-07-28 01:52:51'),(11,2,'CPLNT-011','B250','boys','electrician','Electrical socket or light issue','in_progress',5,NULL,'admin_assigned','2025-07-28 01:48:26','2025-07-28 01:48:26'),(12,3,'CPLNT-012','G110','girls','mess','Food quality complaints','pending',8,NULL,'auto_assigned','2025-07-28 01:48:26','2025-07-28 01:52:51'),(13,2,'CPLNT-013','B199','boys','carpenter','Broken furniture or bed','resolved',4,NULL,'manual','2025-07-28 01:48:26','2025-07-28 01:48:26'),(14,3,'CPLNT-014','G175','girls','housekeeping','Dustbins not cleaned or floors dirty','in_progress',5,NULL,'auto_assigned','2025-07-28 01:48:26','2025-07-28 01:51:46'),(15,2,'CPLNT-015','B122','boys','plumber','Leaking pipe or tap issue','pending',4,NULL,'auto_assigned','2025-07-28 01:48:26','2025-07-28 01:49:19'),(16,3,'CPLNT-016','G188','girls','electrician','Electrical socket or light issue','resolved',4,NULL,'manual','2025-07-28 01:48:26','2025-07-28 01:48:26'),(17,2,'CPLNT-017','B160','boys','wifi','Internet is not working properly','in_progress',5,NULL,'auto_assigned','2025-07-28 01:48:26','2025-07-28 16:19:59'),(18,3,'CPLNT-018','G198','girls','mess','Food quality complaints','pending',8,NULL,'auto_assigned','2025-07-28 01:48:26','2025-07-28 01:52:51'),(19,2,'CPLNT-019','B132','boys','carpenter','Broken furniture or bed','in_progress',4,NULL,'auto_assigned','2025-07-28 01:48:26','2025-07-28 16:19:59'),(20,3,'CPLNT-020','G145','girls','housekeeping','Dustbins not cleaned or floors dirty','resolved',5,NULL,'manual','2025-07-28 01:48:26','2025-07-28 01:48:26'),(22,2,'d20f184e0e9bd4caa914a501621fbde5','104','boys','wifi','No signal','pending',6,NULL,'admin_assigned','2025-07-28 15:53:31','2025-07-28 15:53:31'),(28,2,'d687c5597f5ecd04aa6d9c545b063c48','67','boys','wifi','Test final','pending',6,'','auto_assigned','2025-07-28 16:24:27','2025-07-28 18:57:30'),(29,2,'c8924a19d71e2ba55e5c67c5b537bdcb','56','boys','wifi','Yes','pending',6,'','auto_assigned','2025-07-28 16:26:20','2025-07-28 18:57:30'),(30,2,'7491dc055a5b290b3e3cb9154ec88b59','67','boys','wifi','New','pending',6,'','admin_assigned','2025-07-28 18:49:31','2025-07-28 20:41:05'),(34,6,'2b0037e95cfc635d59541c0a5b388aad','68',NULL,'plumber','No water','resolved',4,'yes','auto_assigned','2025-07-28 20:37:57','2025-07-28 20:39:36');
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hostel_issue_votes`
--

LOCK TABLES `hostel_issue_votes` WRITE;
/*!40000 ALTER TABLE `hostel_issue_votes` DISABLE KEYS */;
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
  `assignment_type` enum('auto_assigned','admin_assigned','manual') DEFAULT 'manual',
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
) ENGINE=InnoDB AUTO_INCREMENT=108 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `security_logs`
--

LOCK TABLES `security_logs` WRITE;
/*!40000 ALTER TABLE `security_logs` DISABLE KEYS */;
INSERT INTO `security_logs` VALUES (1,1,'auto_assignment','complaint_2','{\"type\":\"complaint\",\"item_id\":2,\"technician_id\":5,\"category\":\"electrician\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 01:49:19'),(2,1,'auto_assignment','complaint_4','{\"type\":\"complaint\",\"item_id\":4,\"technician_id\":5,\"category\":\"electrician\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 01:49:19'),(3,1,'auto_assignment','complaint_15','{\"type\":\"complaint\",\"item_id\":15,\"technician_id\":4,\"category\":\"plumber\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 01:49:19'),(4,1,'auto_assignment','complaint_8','{\"type\":\"complaint\",\"item_id\":8,\"technician_id\":8,\"category\":\"housekeeping\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 01:51:30'),(5,1,'auto_assignment','complaint_14','{\"type\":\"complaint\",\"item_id\":14,\"technician_id\":8,\"category\":\"housekeeping\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 01:51:30'),(6,1,'auto_assignment','complaint_8','{\"type\":\"complaint\",\"item_id\":8,\"technician_id\":8,\"category\":\"housekeeping\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 01:51:38'),(7,1,'auto_assignment','complaint_14','{\"type\":\"complaint\",\"item_id\":14,\"technician_id\":8,\"category\":\"housekeeping\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 01:51:38'),(8,1,'auto_assignment','complaint_8','{\"type\":\"complaint\",\"item_id\":8,\"technician_id\":8,\"category\":\"housekeeping\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 01:51:46'),(9,1,'auto_assignment','complaint_14','{\"type\":\"complaint\",\"item_id\":14,\"technician_id\":8,\"category\":\"housekeeping\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 01:51:46'),(10,1,'auto_assignment','complaint_6','{\"type\":\"complaint\",\"item_id\":6,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 01:52:51'),(11,1,'auto_assignment','complaint_17','{\"type\":\"complaint\",\"item_id\":17,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 01:52:51'),(12,1,'auto_assignment','complaint_19','{\"type\":\"complaint\",\"item_id\":19,\"technician_id\":7,\"category\":\"carpenter\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 01:52:51'),(13,1,'auto_assignment','hostel_issue_1','{\"type\":\"hostel_issue\",\"item_id\":1,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 01:52:51'),(14,1,'auto_assignment','complaint_7','{\"type\":\"complaint\",\"item_id\":7,\"technician_id\":7,\"category\":\"carpenter\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 01:52:51'),(15,1,'auto_assignment','complaint_10','{\"type\":\"complaint\",\"item_id\":10,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 01:52:51'),(16,1,'auto_assignment','complaint_12','{\"type\":\"complaint\",\"item_id\":12,\"technician_id\":8,\"category\":\"mess\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 01:52:51'),(17,1,'auto_assignment','complaint_18','{\"type\":\"complaint\",\"item_id\":18,\"technician_id\":8,\"category\":\"mess\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 01:52:51'),(18,1,'auto_assignment','complaint_6','{\"type\":\"complaint\",\"item_id\":6,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 01:53:37'),(19,1,'auto_assignment','complaint_17','{\"type\":\"complaint\",\"item_id\":17,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 01:53:37'),(20,1,'auto_assignment','complaint_19','{\"type\":\"complaint\",\"item_id\":19,\"technician_id\":7,\"category\":\"carpenter\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 01:53:37'),(21,1,'auto_assignment','hostel_issue_1','{\"type\":\"hostel_issue\",\"item_id\":1,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 01:53:37'),(22,1,'auto_assignment','complaint_6','{\"type\":\"complaint\",\"item_id\":6,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 15:54:58'),(23,1,'auto_assignment','complaint_17','{\"type\":\"complaint\",\"item_id\":17,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 15:54:58'),(24,1,'auto_assignment','complaint_19','{\"type\":\"complaint\",\"item_id\":19,\"technician_id\":7,\"category\":\"carpenter\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 15:54:58'),(25,1,'auto_assignment','hostel_issue_1','{\"type\":\"hostel_issue\",\"item_id\":1,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 15:54:58'),(26,1,'auto_assignment','complaint_6','{\"type\":\"complaint\",\"item_id\":6,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 15:55:13'),(27,1,'auto_assignment','complaint_17','{\"type\":\"complaint\",\"item_id\":17,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 15:55:13'),(28,1,'auto_assignment','complaint_19','{\"type\":\"complaint\",\"item_id\":19,\"technician_id\":7,\"category\":\"carpenter\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 15:55:13'),(29,1,'auto_assignment','hostel_issue_1','{\"type\":\"hostel_issue\",\"item_id\":1,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 15:55:13'),(30,1,'auto_assignment','complaint_6','{\"type\":\"complaint\",\"item_id\":6,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 15:59:59'),(31,1,'auto_assignment','complaint_17','{\"type\":\"complaint\",\"item_id\":17,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 15:59:59'),(32,1,'auto_assignment','complaint_19','{\"type\":\"complaint\",\"item_id\":19,\"technician_id\":7,\"category\":\"carpenter\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 15:59:59'),(33,1,'auto_assignment','hostel_issue_1','{\"type\":\"hostel_issue\",\"item_id\":1,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 15:59:59'),(34,1,'auto_assignment','complaint_6','{\"type\":\"complaint\",\"item_id\":6,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 16:04:59'),(35,1,'auto_assignment','complaint_17','{\"type\":\"complaint\",\"item_id\":17,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 16:04:59'),(36,1,'auto_assignment','complaint_19','{\"type\":\"complaint\",\"item_id\":19,\"technician_id\":7,\"category\":\"carpenter\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 16:04:59'),(37,1,'auto_assignment','hostel_issue_1','{\"type\":\"hostel_issue\",\"item_id\":1,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 16:04:59'),(38,1,'auto_assignment','complaint_6','{\"type\":\"complaint\",\"item_id\":6,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 16:09:59'),(39,1,'auto_assignment','complaint_17','{\"type\":\"complaint\",\"item_id\":17,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 16:09:59'),(40,1,'auto_assignment','complaint_19','{\"type\":\"complaint\",\"item_id\":19,\"technician_id\":7,\"category\":\"carpenter\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 16:09:59'),(41,1,'auto_assignment','hostel_issue_1','{\"type\":\"hostel_issue\",\"item_id\":1,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 16:09:59'),(42,1,'auto_assignment','complaint_6','{\"type\":\"complaint\",\"item_id\":6,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 16:14:59'),(43,1,'auto_assignment','complaint_17','{\"type\":\"complaint\",\"item_id\":17,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 16:14:59'),(44,1,'auto_assignment','complaint_19','{\"type\":\"complaint\",\"item_id\":19,\"technician_id\":7,\"category\":\"carpenter\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 16:14:59'),(45,1,'auto_assignment','hostel_issue_1','{\"type\":\"hostel_issue\",\"item_id\":1,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 16:14:59'),(46,1,'auto_assignment','complaint_6','{\"type\":\"complaint\",\"item_id\":6,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 16:19:59'),(47,1,'auto_assignment','complaint_17','{\"type\":\"complaint\",\"item_id\":17,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 16:19:59'),(48,1,'auto_assignment','complaint_19','{\"type\":\"complaint\",\"item_id\":19,\"technician_id\":7,\"category\":\"carpenter\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 16:19:59'),(49,1,'auto_assignment','hostel_issue_1','{\"type\":\"hostel_issue\",\"item_id\":1,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 16:19:59'),(50,1,'auto_assignment','hostel_issue_6','{\"type\":\"auto_assigned\",\"item_id\":6,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 16:24:59'),(51,1,'auto_assignment','hostel_issue_17','{\"type\":\"auto_assigned\",\"item_id\":17,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 16:24:59'),(52,1,'auto_assignment','hostel_issue_19','{\"type\":\"auto_assigned\",\"item_id\":19,\"technician_id\":7,\"category\":\"carpenter\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 16:24:59'),(53,1,'auto_assignment','hostel_issue_1','{\"type\":\"auto_assigned\",\"item_id\":1,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 16:24:59'),(54,1,'auto_assignment','hostel_issue_6','{\"type\":\"auto_assigned\",\"item_id\":6,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 18:57:30'),(55,1,'auto_assignment','hostel_issue_17','{\"type\":\"auto_assigned\",\"item_id\":17,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 18:57:30'),(56,1,'auto_assignment','hostel_issue_19','{\"type\":\"auto_assigned\",\"item_id\":19,\"technician_id\":7,\"category\":\"carpenter\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 18:57:30'),(57,1,'auto_assignment','hostel_issue_1','{\"type\":\"auto_assigned\",\"item_id\":1,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 18:57:30'),(58,1,'auto_assignment','hostel_issue_28','{\"type\":\"auto_assigned\",\"item_id\":28,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 18:57:30'),(59,1,'auto_assignment','hostel_issue_29','{\"type\":\"auto_assigned\",\"item_id\":29,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 18:57:30'),(60,1,'auto_assignment','hostel_issue_6','{\"type\":\"auto_assigned\",\"item_id\":6,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 18:57:33'),(61,1,'auto_assignment','hostel_issue_17','{\"type\":\"auto_assigned\",\"item_id\":17,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 18:57:33'),(62,1,'auto_assignment','hostel_issue_19','{\"type\":\"auto_assigned\",\"item_id\":19,\"technician_id\":7,\"category\":\"carpenter\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 18:57:33'),(63,1,'auto_assignment','hostel_issue_1','{\"type\":\"auto_assigned\",\"item_id\":1,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 18:57:33'),(64,1,'auto_assignment','hostel_issue_6','{\"type\":\"auto_assigned\",\"item_id\":6,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 18:58:45'),(65,1,'auto_assignment','hostel_issue_17','{\"type\":\"auto_assigned\",\"item_id\":17,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 18:58:45'),(66,1,'auto_assignment','hostel_issue_19','{\"type\":\"auto_assigned\",\"item_id\":19,\"technician_id\":7,\"category\":\"carpenter\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 18:58:45'),(67,1,'auto_assignment','hostel_issue_1','{\"type\":\"auto_assigned\",\"item_id\":1,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 18:58:45'),(68,1,'auto_assignment','hostel_issue_30','{\"type\":\"auto_assigned\",\"item_id\":30,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 18:58:45'),(69,1,'auto_assignment','hostel_issue_6','{\"type\":\"auto_assigned\",\"item_id\":6,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 18:58:54'),(70,1,'auto_assignment','hostel_issue_17','{\"type\":\"auto_assigned\",\"item_id\":17,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 18:58:54'),(71,1,'auto_assignment','hostel_issue_19','{\"type\":\"auto_assigned\",\"item_id\":19,\"technician_id\":7,\"category\":\"carpenter\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 18:58:54'),(72,1,'auto_assignment','hostel_issue_1','{\"type\":\"auto_assigned\",\"item_id\":1,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 18:58:54'),(73,1,'auto_assignment','hostel_issue_6','{\"type\":\"auto_assigned\",\"item_id\":6,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:03:55'),(74,1,'auto_assignment','hostel_issue_17','{\"type\":\"auto_assigned\",\"item_id\":17,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:03:55'),(75,1,'auto_assignment','hostel_issue_19','{\"type\":\"auto_assigned\",\"item_id\":19,\"technician_id\":7,\"category\":\"carpenter\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:03:55'),(76,1,'auto_assignment','hostel_issue_1','{\"type\":\"auto_assigned\",\"item_id\":1,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:03:55'),(77,1,'auto_assignment','hostel_issue_6','{\"type\":\"auto_assigned\",\"item_id\":6,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:06:18'),(78,1,'auto_assignment','hostel_issue_17','{\"type\":\"auto_assigned\",\"item_id\":17,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:06:18'),(79,1,'auto_assignment','hostel_issue_19','{\"type\":\"auto_assigned\",\"item_id\":19,\"technician_id\":7,\"category\":\"carpenter\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:06:19'),(80,1,'auto_assignment','hostel_issue_1','{\"type\":\"auto_assigned\",\"item_id\":1,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:06:19'),(81,1,'auto_assignment','hostel_issue_6','{\"type\":\"auto_assigned\",\"item_id\":6,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:06:46'),(82,1,'auto_assignment','hostel_issue_17','{\"type\":\"auto_assigned\",\"item_id\":17,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:06:46'),(83,1,'auto_assignment','hostel_issue_19','{\"type\":\"auto_assigned\",\"item_id\":19,\"technician_id\":7,\"category\":\"carpenter\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:06:46'),(84,1,'auto_assignment','hostel_issue_1','{\"type\":\"auto_assigned\",\"item_id\":1,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:06:46'),(85,1,'auto_assignment','hostel_issue_6','{\"type\":\"auto_assigned\",\"item_id\":6,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:10:31'),(86,1,'auto_assignment','hostel_issue_17','{\"type\":\"auto_assigned\",\"item_id\":17,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:10:31'),(87,1,'auto_assignment','hostel_issue_19','{\"type\":\"auto_assigned\",\"item_id\":19,\"technician_id\":7,\"category\":\"carpenter\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:10:31'),(88,1,'auto_assignment','hostel_issue_1','{\"type\":\"auto_assigned\",\"item_id\":1,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:10:31'),(89,1,'auto_assignment','hostel_issue_6','{\"type\":\"auto_assigned\",\"item_id\":6,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:12:42'),(90,1,'auto_assignment','hostel_issue_17','{\"type\":\"auto_assigned\",\"item_id\":17,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:12:42'),(91,1,'auto_assignment','hostel_issue_19','{\"type\":\"auto_assigned\",\"item_id\":19,\"technician_id\":7,\"category\":\"carpenter\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:12:42'),(92,1,'auto_assignment','hostel_issue_1','{\"type\":\"auto_assigned\",\"item_id\":1,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:12:42'),(93,1,'auto_assignment','hostel_issue_6','{\"type\":\"auto_assigned\",\"item_id\":6,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:17:43'),(94,1,'auto_assignment','hostel_issue_17','{\"type\":\"auto_assigned\",\"item_id\":17,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:17:43'),(95,1,'auto_assignment','hostel_issue_19','{\"type\":\"auto_assigned\",\"item_id\":19,\"technician_id\":7,\"category\":\"carpenter\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:17:43'),(96,1,'auto_assignment','hostel_issue_1','{\"type\":\"auto_assigned\",\"item_id\":1,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:17:43'),(97,1,'auto_assignment','hostel_issue_6','{\"type\":\"auto_assigned\",\"item_id\":6,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:22:43'),(98,1,'auto_assignment','hostel_issue_17','{\"type\":\"auto_assigned\",\"item_id\":17,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:22:43'),(99,1,'auto_assignment','hostel_issue_19','{\"type\":\"auto_assigned\",\"item_id\":19,\"technician_id\":7,\"category\":\"carpenter\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:22:43'),(100,1,'auto_assignment','hostel_issue_1','{\"type\":\"auto_assigned\",\"item_id\":1,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:22:43'),(101,1,'auto_assignment','hostel_issue_6','{\"type\":\"auto_assigned\",\"item_id\":6,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:24:06'),(102,1,'auto_assignment','hostel_issue_17','{\"type\":\"auto_assigned\",\"item_id\":17,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:24:06'),(103,1,'auto_assignment','hostel_issue_19','{\"type\":\"auto_assigned\",\"item_id\":19,\"technician_id\":7,\"category\":\"carpenter\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:24:06'),(104,1,'auto_assignment','hostel_issue_1','{\"type\":\"auto_assigned\",\"item_id\":1,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 19:24:06'),(105,1,'auto_assignment','hostel_issue_6','{\"type\":\"auto_assigned\",\"item_id\":6,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 23:01:18'),(106,1,'auto_assignment','hostel_issue_17','{\"type\":\"auto_assigned\",\"item_id\":17,\"technician_id\":6,\"category\":\"wifi\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 23:01:18'),(107,1,'auto_assignment','hostel_issue_19','{\"type\":\"auto_assigned\",\"item_id\":19,\"technician_id\":7,\"category\":\"carpenter\",\"assignment_method\":\"smart_auto_assignment\"}',NULL,NULL,'2025-07-28 23:01:18');
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
INSERT INTO `special_codes` VALUES ('STU-2025-XYZ','student',NULL,'2025-07-28 01:46:52',NULL,1),('TECH-AC-03','technician','ac','2025-07-28 01:51:03',NULL,1),('TECH-ELEC-02','technician','electrician','2025-07-28 01:46:52',NULL,1),('TECH-HOUSE-05','technician','housekeeping','2025-07-28 01:51:03',NULL,1),('TECH-LAUNDRY-04','technician','laundry','2025-07-28 01:51:03',NULL,1),('TECH-PLUMB-01','technician','plumber','2025-07-28 01:46:52',NULL,1);
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suggestion_votes`
--

LOCK TABLES `suggestion_votes` WRITE;
/*!40000 ALTER TABLE `suggestion_votes` DISABLE KEYS */;
INSERT INTO `suggestion_votes` VALUES (1,1,3,'upvote','2025-07-28 01:46:52'),(2,2,2,'upvote','2025-07-28 01:46:52');
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suggestions`
--

LOCK TABLES `suggestions` WRITE;
/*!40000 ALTER TABLE `suggestions` DISABLE KEYS */;
INSERT INTO `suggestions` VALUES (1,2,'Install More Charging Ports','Need charging points in the common area.','hostel','pending',NULL,0,0,'2025-07-28 01:46:52','2025-07-28 01:46:52'),(2,3,'Improve Mess Food','Include healthier food options and fruits.','mess','approved',NULL,0,0,'2025-07-28 01:46:52','2025-07-28 01:46:52');
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_notifications`
--

LOCK TABLES `user_notifications` WRITE;
/*!40000 ALTER TABLE `user_notifications` DISABLE KEYS */;
INSERT INTO `user_notifications` VALUES (1,2,'CPLNT-001',NULL,'resolved','2025-07-28 01:46:52'),(2,2,'CPLNT-005',NULL,'resolved','2025-07-28 15:53:42'),(3,2,'CPLNT-009',NULL,'resolved','2025-07-28 15:53:42'),(4,2,'CPLNT-013',NULL,'resolved','2025-07-28 15:53:42'),(5,2,'CPLNT-005',NULL,'resolved','2025-07-28 15:53:44'),(6,2,'CPLNT-009',NULL,'resolved','2025-07-28 15:53:44'),(7,2,'CPLNT-013',NULL,'resolved','2025-07-28 15:53:44');
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'System Administrator','0000000000','superadmin','ADMIN001',NULL,'admin','$2y$10$YJ/hSk6uFK4aqN3t4iBai.hJYl7j54/STS.gr3Rvxclqessszkyi6','2025-07-28 01:42:10',NULL,1),(2,'Harkamal','0000000000','student','STU-2025-XYZ',NULL,'student','$2y$10$uNEp32Z0rg85DOKhlSuUFOQHOsj9FRkoMoY7EzZ7f4GlhfiSVyIC6','2025-07-28 01:46:52',NULL,1),(3,'Meena Joshi','0000000000','faculty','STU-2025-XYZ',NULL,'faculty','$2y$10$oJOSbUviDRC.umTx0EPMteq255gI8NcWaua4Z.dMG9Cdn/PicNv/G','2025-07-28 01:46:52',NULL,1),(4,'Rohit Verma','0000000000','technician','TECH-PLUMB-01','plumber','plumber','$2y$10$6leA9O7lSCbMZj1v0xWqhOQP.fNJGCiSbMDGQplyH8HC1aSjtFsO2','2025-07-28 01:46:52',NULL,1),(5,'Sneha Das','0000000000','technician','TECH-ELEC-02','electrician','electric','$2y$10$rm3MEL.x5dqk0bE2EPvYDuGyweHVcIlU8sTHVIG3mZI2P3VMSG18m','2025-07-28 01:46:52',NULL,1),(6,'Imran Khan','0000000000','technician','TECH-AC-03','wifi','wifi','$2y$10$wqR.go7tQdehKIv9MfD6Ne4UONlch1SxtxQcFRAHsTn5MTL0iU9fK','2025-07-28 01:51:17',NULL,1),(7,'Lata Mehra','0000000000','technician','TECH-LAUNDRY-04','carpenter','carpenter','$2y$10$M5Jc9ZllEM5WGfzpadxi4Ow8vLCEOOKEUTMTT52yVhzFJAaaEPS0O','2025-07-28 01:51:17',NULL,1),(8,'Naveen Paul','0000000000','technician','TECH-HOUSE-05','mess','mess','$2y$10$VXU59V6ATuULcfPVdkdPf.UDEtqFxkdU/9ZQgBoen9WsNAadDb9EW','2025-07-28 01:51:17',NULL,1);
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

-- Dump completed on 2025-07-28 23:01:25
