-- MySQL dump 10.13  Distrib 8.0.42, for Linux (aarch64)
--
-- Host: localhost    Database: booking_app
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
-- Table structure for table `appointment_reminders`
--

DROP TABLE IF EXISTS `appointment_reminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `appointment_reminders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `appointment_id` bigint unsigned NOT NULL,
  `type` enum('email','sms') COLLATE utf8mb4_unicode_ci NOT NULL,
  `scheduled_for` datetime NOT NULL,
  `sent_at` datetime DEFAULT NULL,
  `status` enum('pending','sent','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `appointment_reminders_appointment_id_foreign` (`appointment_id`),
  KEY `appointment_reminders_status_scheduled_for_index` (`status`,`scheduled_for`),
  CONSTRAINT `appointment_reminders_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointment_reminders`
--

LOCK TABLES `appointment_reminders` WRITE;
/*!40000 ALTER TABLE `appointment_reminders` DISABLE KEYS */;
/*!40000 ALTER TABLE `appointment_reminders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appointments`
--

DROP TABLE IF EXISTS `appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `appointments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `service_id` bigint unsigned NOT NULL,
  `staff_id` bigint unsigned NOT NULL,
  `scheduled_date` timestamp NOT NULL,
  `status` enum('pending','confirmed','cancelled','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `final_price` decimal(10,2) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `google_event_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `appointments_user_id_foreign` (`user_id`),
  KEY `appointments_service_id_foreign` (`service_id`),
  KEY `appointments_staff_id_foreign` (`staff_id`),
  KEY `appointments_status_index` (`status`),
  KEY `appointments_scheduled_date_index` (`scheduled_date`),
  CONSTRAINT `appointments_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointments_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointments`
--

LOCK TABLES `appointments` WRITE;
/*!40000 ALTER TABLE `appointments` DISABLE KEYS */;
INSERT INTO `appointments` VALUES (1,3,1,4,'2026-05-20 10:00:00','confirmed',75.00,'Prenotazione demo confermata.',NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(2,3,2,4,'2026-05-14 11:00:00','completed',45.00,'Appuntamento demo completato.',NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37');
/*!40000 ALTER TABLE `appointments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `availability_rules`
--

DROP TABLE IF EXISTS `availability_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `availability_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `day_of_week` tinyint unsigned NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `start_time_2` time DEFAULT NULL,
  `end_time_2` time DEFAULT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `availability_rules_user_id_foreign` (`user_id`),
  CONSTRAINT `availability_rules_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `availability_rules`
--

LOCK TABLES `availability_rules` WRITE;
/*!40000 ALTER TABLE `availability_rules` DISABLE KEYS */;
INSERT INTO `availability_rules` VALUES (1,4,1,'09:00:00','17:00:00',NULL,NULL,1,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(2,4,2,'09:00:00','17:00:00',NULL,NULL,1,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(3,4,3,'10:00:00','18:00:00',NULL,NULL,1,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(4,4,4,'09:00:00','17:00:00',NULL,NULL,1,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(5,4,5,'09:00:00','15:00:00',NULL,NULL,1,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(6,5,1,'09:00:00','17:00:00',NULL,NULL,1,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(7,5,2,'09:00:00','17:00:00',NULL,NULL,1,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(8,5,3,'10:00:00','18:00:00',NULL,NULL,1,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(9,5,4,'09:00:00','17:00:00',NULL,NULL,1,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(10,5,5,'09:00:00','15:00:00',NULL,NULL,1,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(11,2,1,'09:00:00','17:00:00',NULL,NULL,1,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(12,2,2,'09:00:00','17:00:00',NULL,NULL,1,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(13,2,3,'10:00:00','18:00:00',NULL,NULL,1,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(14,2,4,'09:00:00','17:00:00',NULL,NULL,1,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(15,2,5,'09:00:00','15:00:00',NULL,NULL,1,'2026-05-14 13:42:36','2026-05-14 13:42:36');
/*!40000 ALTER TABLE `availability_rules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('booking-app-cache-livewire-rate-limiter:1a22f7dcb0fc21fe8266ec40661ae9e22c5811ca','i:1;',1778766224),('booking-app-cache-livewire-rate-limiter:1a22f7dcb0fc21fe8266ec40661ae9e22c5811ca:timer','i:1778766224;',1778766224),('booking-app-cache-spatie.permission.cache','a:3:{s:5:\"alias\";a:0:{}s:11:\"permissions\";a:0:{}s:5:\"roles\";a:0:{}}',1778852565);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (19,'0001_01_01_000000_create_users_table',1),(20,'0001_01_01_000001_create_cache_table',1),(21,'0001_01_01_000002_create_jobs_table',1),(22,'2026_05_08_000010_create_services_table',1),(23,'2026_05_08_000011_create_availability_rules_table',1),(24,'2026_05_08_000012_create_appointments_table',1),(25,'2026_05_08_000013_create_time_slots_table',1),(26,'2026_05_08_000014_create_appointment_reminders_table',1),(27,'2026_05_08_000015_create_payments_table',1),(28,'2026_05_08_000016_create_service_staff_table',1),(29,'2026_05_08_000017_create_user_preferences_table',1),(30,'2026_05_08_090355_create_permission_tables',1),(31,'2026_05_08_141532_create_personal_access_tokens_table',1),(32,'2026_05_13_000001_add_internal_notes_to_users_table',1),(33,'2026_05_14_074525_add_split_times_to_availability_rules_table',1),(34,'2026_05_14_075918_make_start_time_end_time_nullable_in_availability_rules',1),(35,'2026_05_14_105128_add_slot_duration_minutes_to_user_preferences_table',1),(36,'2026_05_14_200000_create_system_settings_table',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_permissions`
--

DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_permissions`
--

LOCK TABLES `model_has_permissions` WRITE;
/*!40000 ALTER TABLE `model_has_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `model_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_roles`
--

DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_roles`
--

LOCK TABLES `model_has_roles` WRITE;
/*!40000 ALTER TABLE `model_has_roles` DISABLE KEYS */;
INSERT INTO `model_has_roles` VALUES (1,'App\\Models\\User',1),(2,'App\\Models\\User',2),(3,'App\\Models\\User',3),(2,'App\\Models\\User',4),(2,'App\\Models\\User',5);
/*!40000 ALTER TABLE `model_has_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `appointment_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','completed','refunded','failed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `stripe_transaction_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_response` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_stripe_transaction_id_unique` (`stripe_transaction_id`),
  KEY `payments_appointment_id_foreign` (`appointment_id`),
  KEY `payments_user_id_foreign` (`user_id`),
  CONSTRAINT `payments_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,1,3,75.00,'completed','pi_demo_1','{\"id\": \"pi_demo_1\", \"object\": \"payment_intent\", \"status\": \"succeeded\", \"livemode\": false}','2026-05-14 13:42:37','2026-05-14 13:42:37'),(2,2,3,45.00,'completed','pi_demo_2','{\"id\": \"pi_demo_2\", \"object\": \"payment_intent\", \"status\": \"succeeded\", \"livemode\": false}','2026-05-14 13:42:37','2026-05-14 13:42:37');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_has_permissions`
--

DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_has_permissions`
--

LOCK TABLES `role_has_permissions` WRITE;
/*!40000 ALTER TABLE `role_has_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `role_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'admin','web','2026-05-14 13:42:34','2026-05-14 13:42:34'),(2,'staff','web','2026-05-14 13:42:34','2026-05-14 13:42:34'),(3,'customer','web','2026-05-14 13:42:34','2026-05-14 13:42:34');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_staff`
--

DROP TABLE IF EXISTS `service_staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_staff` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `service_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_staff_service_id_user_id_unique` (`service_id`,`user_id`),
  KEY `service_staff_user_id_foreign` (`user_id`),
  CONSTRAINT `service_staff_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
  CONSTRAINT `service_staff_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_staff`
--

LOCK TABLES `service_staff` WRITE;
/*!40000 ALTER TABLE `service_staff` DISABLE KEYS */;
INSERT INTO `service_staff` VALUES (1,1,4,NULL,NULL),(2,1,5,NULL,NULL),(3,2,4,NULL,NULL),(4,2,2,NULL,NULL),(5,3,5,NULL,NULL),(6,3,2,NULL,NULL);
/*!40000 ALTER TABLE `service_staff` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `services` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `duration_minutes` int unsigned NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `services`
--

LOCK TABLES `services` WRITE;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
INSERT INTO `services` VALUES (1,'Consulenza iniziale','Primo incontro per valutare esigenze, obiettivi e disponibilita.',60,75.00,1,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(2,'Controllo periodico','Appuntamento di follow-up per clienti gia registrati.',30,45.00,1,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(3,'Pianificazione avanzata','Sessione operativa per definire piano, priorita e prossime attivita.',60,95.00,1,'2026-05-14 13:42:36','2026-05-14 13:42:36');
/*!40000 ALTER TABLE `services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slot_generation_weeks` int unsigned NOT NULL DEFAULT '4',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES (1,5,'2026-05-14 13:42:35','2026-05-14 13:43:05');
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `time_slots`
--

DROP TABLE IF EXISTS `time_slots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `time_slots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT '1',
  `appointment_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `time_slots_appointment_id_foreign` (`appointment_id`),
  KEY `time_slots_user_id_date_index` (`user_id`,`date`),
  CONSTRAINT `time_slots_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `time_slots_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1065 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `time_slots`
--

LOCK TABLES `time_slots` WRITE;
/*!40000 ALTER TABLE `time_slots` DISABLE KEYS */;
INSERT INTO `time_slots` VALUES (1,4,'2026-05-11','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(2,4,'2026-05-11','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(3,4,'2026-05-11','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(4,4,'2026-05-11','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(5,4,'2026-05-11','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(6,4,'2026-05-11','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(7,4,'2026-05-11','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(8,4,'2026-05-11','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(9,4,'2026-05-12','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(10,4,'2026-05-12','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(11,4,'2026-05-12','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(12,4,'2026-05-12','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(13,4,'2026-05-12','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(14,4,'2026-05-12','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(15,4,'2026-05-12','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(16,4,'2026-05-12','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(17,4,'2026-05-13','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(18,4,'2026-05-13','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(19,4,'2026-05-13','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(20,4,'2026-05-13','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(21,4,'2026-05-13','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(22,4,'2026-05-13','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(23,4,'2026-05-13','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(24,4,'2026-05-13','17:00:00','18:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(25,4,'2026-05-14','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(26,4,'2026-05-14','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(27,4,'2026-05-14','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(28,4,'2026-05-14','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(29,4,'2026-05-14','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(30,4,'2026-05-14','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(31,4,'2026-05-14','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(32,4,'2026-05-14','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(33,4,'2026-05-15','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(34,4,'2026-05-15','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(35,4,'2026-05-15','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(36,4,'2026-05-15','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(37,4,'2026-05-15','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(38,4,'2026-05-15','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(39,4,'2026-05-18','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(40,4,'2026-05-18','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(41,4,'2026-05-18','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(42,4,'2026-05-18','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(43,4,'2026-05-18','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(44,4,'2026-05-18','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(45,4,'2026-05-18','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(46,4,'2026-05-18','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(47,4,'2026-05-19','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(48,4,'2026-05-19','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(49,4,'2026-05-19','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(50,4,'2026-05-19','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(51,4,'2026-05-19','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(52,4,'2026-05-19','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(53,4,'2026-05-19','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(54,4,'2026-05-19','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(55,4,'2026-05-20','10:00:00','11:00:00',0,1,'2026-05-14 13:42:36','2026-05-14 13:42:37'),(56,4,'2026-05-20','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(57,4,'2026-05-20','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(58,4,'2026-05-20','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(59,4,'2026-05-20','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(60,4,'2026-05-20','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(61,4,'2026-05-20','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(62,4,'2026-05-20','17:00:00','18:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(63,4,'2026-05-21','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(64,4,'2026-05-21','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(65,4,'2026-05-21','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(66,4,'2026-05-21','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(67,4,'2026-05-21','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(68,4,'2026-05-21','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(69,4,'2026-05-21','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(70,4,'2026-05-21','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(71,4,'2026-05-22','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(72,4,'2026-05-22','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(73,4,'2026-05-22','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(74,4,'2026-05-22','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(75,4,'2026-05-22','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(76,4,'2026-05-22','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(77,4,'2026-05-25','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(78,4,'2026-05-25','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(79,4,'2026-05-25','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(80,4,'2026-05-25','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(81,4,'2026-05-25','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(82,4,'2026-05-25','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(83,4,'2026-05-25','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(84,4,'2026-05-25','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(85,4,'2026-05-26','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(86,4,'2026-05-26','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(87,4,'2026-05-26','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(88,4,'2026-05-26','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(89,4,'2026-05-26','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(90,4,'2026-05-26','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(91,4,'2026-05-26','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(92,4,'2026-05-26','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(93,4,'2026-05-27','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(94,4,'2026-05-27','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(95,4,'2026-05-27','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(96,4,'2026-05-27','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(97,4,'2026-05-27','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(98,4,'2026-05-27','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(99,4,'2026-05-27','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(100,4,'2026-05-27','17:00:00','18:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(101,4,'2026-05-28','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(102,4,'2026-05-28','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(103,4,'2026-05-28','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(104,4,'2026-05-28','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(105,4,'2026-05-28','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(106,4,'2026-05-28','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(107,4,'2026-05-28','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(108,4,'2026-05-28','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(109,4,'2026-05-29','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(110,4,'2026-05-29','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(111,4,'2026-05-29','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(112,4,'2026-05-29','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(113,4,'2026-05-29','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(114,4,'2026-05-29','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(115,4,'2026-06-01','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(116,4,'2026-06-01','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(117,4,'2026-06-01','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(118,4,'2026-06-01','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(119,4,'2026-06-01','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(120,4,'2026-06-01','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(121,4,'2026-06-01','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(122,4,'2026-06-01','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(123,4,'2026-06-02','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(124,4,'2026-06-02','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(125,4,'2026-06-02','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(126,4,'2026-06-02','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(127,4,'2026-06-02','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(128,4,'2026-06-02','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(129,4,'2026-06-02','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(130,4,'2026-06-02','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(131,4,'2026-06-03','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(132,4,'2026-06-03','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(133,4,'2026-06-03','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(134,4,'2026-06-03','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(135,4,'2026-06-03','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(136,4,'2026-06-03','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(137,4,'2026-06-03','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(138,4,'2026-06-03','17:00:00','18:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(139,4,'2026-06-04','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(140,4,'2026-06-04','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(141,4,'2026-06-04','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(142,4,'2026-06-04','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(143,4,'2026-06-04','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(144,4,'2026-06-04','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(145,4,'2026-06-04','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(146,4,'2026-06-04','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(147,4,'2026-06-05','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(148,4,'2026-06-05','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(149,4,'2026-06-05','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(150,4,'2026-06-05','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(151,4,'2026-06-05','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(152,4,'2026-06-05','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(153,4,'2026-06-08','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(154,4,'2026-06-08','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(155,4,'2026-06-08','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(156,4,'2026-06-08','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(157,4,'2026-06-08','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(158,4,'2026-06-08','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(159,4,'2026-06-08','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(160,4,'2026-06-08','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(161,4,'2026-06-09','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(162,4,'2026-06-09','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(163,4,'2026-06-09','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(164,4,'2026-06-09','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(165,4,'2026-06-09','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(166,4,'2026-06-09','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(167,4,'2026-06-09','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(168,4,'2026-06-09','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(169,4,'2026-06-10','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(170,4,'2026-06-10','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(171,4,'2026-06-10','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(172,4,'2026-06-10','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(173,4,'2026-06-10','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(174,4,'2026-06-10','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(175,4,'2026-06-10','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(176,4,'2026-06-10','17:00:00','18:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(177,4,'2026-06-11','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(178,4,'2026-06-11','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(179,4,'2026-06-11','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(180,4,'2026-06-11','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(181,4,'2026-06-11','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(182,4,'2026-06-11','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(183,4,'2026-06-11','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(184,4,'2026-06-11','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(185,4,'2026-06-12','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(186,4,'2026-06-12','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(187,4,'2026-06-12','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(188,4,'2026-06-12','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(189,4,'2026-06-12','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(190,4,'2026-06-12','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(191,4,'2026-06-15','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(192,4,'2026-06-15','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(193,4,'2026-06-15','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(194,4,'2026-06-15','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(195,4,'2026-06-15','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(196,4,'2026-06-15','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(197,4,'2026-06-15','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(198,4,'2026-06-15','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(199,4,'2026-06-16','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(200,4,'2026-06-16','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(201,4,'2026-06-16','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(202,4,'2026-06-16','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(203,4,'2026-06-16','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(204,4,'2026-06-16','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(205,4,'2026-06-16','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(206,4,'2026-06-16','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(207,4,'2026-06-17','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(208,4,'2026-06-17','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(209,4,'2026-06-17','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(210,4,'2026-06-17','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(211,4,'2026-06-17','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(212,4,'2026-06-17','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(213,4,'2026-06-17','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(214,4,'2026-06-17','17:00:00','18:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(215,4,'2026-06-18','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(216,4,'2026-06-18','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(217,4,'2026-06-18','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(218,4,'2026-06-18','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(219,4,'2026-06-18','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(220,4,'2026-06-18','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(221,4,'2026-06-18','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(222,4,'2026-06-18','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(223,4,'2026-06-19','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(224,4,'2026-06-19','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(225,4,'2026-06-19','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(226,4,'2026-06-19','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(227,4,'2026-06-19','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(228,4,'2026-06-19','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(229,5,'2026-05-11','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(230,5,'2026-05-11','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(231,5,'2026-05-11','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(232,5,'2026-05-11','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(233,5,'2026-05-11','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(234,5,'2026-05-11','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(235,5,'2026-05-11','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(236,5,'2026-05-11','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(237,5,'2026-05-12','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(238,5,'2026-05-12','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(239,5,'2026-05-12','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(240,5,'2026-05-12','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(241,5,'2026-05-12','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(242,5,'2026-05-12','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(243,5,'2026-05-12','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(244,5,'2026-05-12','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(245,5,'2026-05-13','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(246,5,'2026-05-13','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(247,5,'2026-05-13','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(248,5,'2026-05-13','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(249,5,'2026-05-13','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(250,5,'2026-05-13','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(251,5,'2026-05-13','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(252,5,'2026-05-13','17:00:00','18:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(253,5,'2026-05-14','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(254,5,'2026-05-14','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(255,5,'2026-05-14','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(256,5,'2026-05-14','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(257,5,'2026-05-14','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(258,5,'2026-05-14','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(259,5,'2026-05-14','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(260,5,'2026-05-14','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(261,5,'2026-05-15','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(262,5,'2026-05-15','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(263,5,'2026-05-15','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(264,5,'2026-05-15','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(265,5,'2026-05-15','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(266,5,'2026-05-15','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(267,5,'2026-05-18','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(268,5,'2026-05-18','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(269,5,'2026-05-18','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(270,5,'2026-05-18','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(271,5,'2026-05-18','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(272,5,'2026-05-18','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(273,5,'2026-05-18','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(274,5,'2026-05-18','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(275,5,'2026-05-19','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(276,5,'2026-05-19','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(277,5,'2026-05-19','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(278,5,'2026-05-19','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(279,5,'2026-05-19','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(280,5,'2026-05-19','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(281,5,'2026-05-19','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(282,5,'2026-05-19','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(283,5,'2026-05-20','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(284,5,'2026-05-20','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(285,5,'2026-05-20','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(286,5,'2026-05-20','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(287,5,'2026-05-20','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(288,5,'2026-05-20','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(289,5,'2026-05-20','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(290,5,'2026-05-20','17:00:00','18:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(291,5,'2026-05-21','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(292,5,'2026-05-21','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(293,5,'2026-05-21','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(294,5,'2026-05-21','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(295,5,'2026-05-21','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(296,5,'2026-05-21','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(297,5,'2026-05-21','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(298,5,'2026-05-21','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(299,5,'2026-05-22','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(300,5,'2026-05-22','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(301,5,'2026-05-22','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(302,5,'2026-05-22','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(303,5,'2026-05-22','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(304,5,'2026-05-22','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(305,5,'2026-05-25','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(306,5,'2026-05-25','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(307,5,'2026-05-25','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(308,5,'2026-05-25','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(309,5,'2026-05-25','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(310,5,'2026-05-25','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(311,5,'2026-05-25','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(312,5,'2026-05-25','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(313,5,'2026-05-26','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(314,5,'2026-05-26','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(315,5,'2026-05-26','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(316,5,'2026-05-26','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(317,5,'2026-05-26','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(318,5,'2026-05-26','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(319,5,'2026-05-26','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(320,5,'2026-05-26','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(321,5,'2026-05-27','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(322,5,'2026-05-27','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(323,5,'2026-05-27','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(324,5,'2026-05-27','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(325,5,'2026-05-27','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(326,5,'2026-05-27','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(327,5,'2026-05-27','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(328,5,'2026-05-27','17:00:00','18:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(329,5,'2026-05-28','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(330,5,'2026-05-28','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(331,5,'2026-05-28','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(332,5,'2026-05-28','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(333,5,'2026-05-28','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(334,5,'2026-05-28','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(335,5,'2026-05-28','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(336,5,'2026-05-28','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(337,5,'2026-05-29','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(338,5,'2026-05-29','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(339,5,'2026-05-29','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(340,5,'2026-05-29','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(341,5,'2026-05-29','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(342,5,'2026-05-29','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(343,5,'2026-06-01','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(344,5,'2026-06-01','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(345,5,'2026-06-01','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(346,5,'2026-06-01','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(347,5,'2026-06-01','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(348,5,'2026-06-01','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(349,5,'2026-06-01','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(350,5,'2026-06-01','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(351,5,'2026-06-02','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(352,5,'2026-06-02','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(353,5,'2026-06-02','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(354,5,'2026-06-02','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(355,5,'2026-06-02','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(356,5,'2026-06-02','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(357,5,'2026-06-02','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(358,5,'2026-06-02','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(359,5,'2026-06-03','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(360,5,'2026-06-03','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(361,5,'2026-06-03','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(362,5,'2026-06-03','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(363,5,'2026-06-03','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(364,5,'2026-06-03','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(365,5,'2026-06-03','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(366,5,'2026-06-03','17:00:00','18:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(367,5,'2026-06-04','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(368,5,'2026-06-04','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(369,5,'2026-06-04','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(370,5,'2026-06-04','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(371,5,'2026-06-04','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(372,5,'2026-06-04','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(373,5,'2026-06-04','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(374,5,'2026-06-04','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(375,5,'2026-06-05','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(376,5,'2026-06-05','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(377,5,'2026-06-05','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(378,5,'2026-06-05','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(379,5,'2026-06-05','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(380,5,'2026-06-05','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(381,5,'2026-06-08','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(382,5,'2026-06-08','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(383,5,'2026-06-08','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(384,5,'2026-06-08','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(385,5,'2026-06-08','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(386,5,'2026-06-08','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(387,5,'2026-06-08','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(388,5,'2026-06-08','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(389,5,'2026-06-09','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(390,5,'2026-06-09','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(391,5,'2026-06-09','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(392,5,'2026-06-09','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(393,5,'2026-06-09','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(394,5,'2026-06-09','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(395,5,'2026-06-09','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(396,5,'2026-06-09','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(397,5,'2026-06-10','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(398,5,'2026-06-10','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(399,5,'2026-06-10','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(400,5,'2026-06-10','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(401,5,'2026-06-10','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(402,5,'2026-06-10','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(403,5,'2026-06-10','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(404,5,'2026-06-10','17:00:00','18:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(405,5,'2026-06-11','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(406,5,'2026-06-11','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(407,5,'2026-06-11','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(408,5,'2026-06-11','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(409,5,'2026-06-11','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(410,5,'2026-06-11','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(411,5,'2026-06-11','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(412,5,'2026-06-11','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(413,5,'2026-06-12','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(414,5,'2026-06-12','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(415,5,'2026-06-12','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(416,5,'2026-06-12','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(417,5,'2026-06-12','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(418,5,'2026-06-12','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(419,5,'2026-06-15','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(420,5,'2026-06-15','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(421,5,'2026-06-15','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(422,5,'2026-06-15','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(423,5,'2026-06-15','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(424,5,'2026-06-15','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(425,5,'2026-06-15','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(426,5,'2026-06-15','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(427,5,'2026-06-16','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(428,5,'2026-06-16','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(429,5,'2026-06-16','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(430,5,'2026-06-16','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(431,5,'2026-06-16','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(432,5,'2026-06-16','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(433,5,'2026-06-16','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(434,5,'2026-06-16','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(435,5,'2026-06-17','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(436,5,'2026-06-17','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(437,5,'2026-06-17','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(438,5,'2026-06-17','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(439,5,'2026-06-17','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(440,5,'2026-06-17','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(441,5,'2026-06-17','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(442,5,'2026-06-17','17:00:00','18:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(443,5,'2026-06-18','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(444,5,'2026-06-18','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(445,5,'2026-06-18','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(446,5,'2026-06-18','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(447,5,'2026-06-18','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(448,5,'2026-06-18','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(449,5,'2026-06-18','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(450,5,'2026-06-18','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(451,5,'2026-06-19','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(452,5,'2026-06-19','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(453,5,'2026-06-19','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(454,5,'2026-06-19','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(455,5,'2026-06-19','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(456,5,'2026-06-19','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(457,2,'2026-05-11','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(458,2,'2026-05-11','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(459,2,'2026-05-11','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(460,2,'2026-05-11','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(461,2,'2026-05-11','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(462,2,'2026-05-11','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(463,2,'2026-05-11','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(464,2,'2026-05-11','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(465,2,'2026-05-12','09:00:00','10:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(466,2,'2026-05-12','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(467,2,'2026-05-12','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(468,2,'2026-05-12','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(469,2,'2026-05-12','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(470,2,'2026-05-12','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(471,2,'2026-05-12','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(472,2,'2026-05-12','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(473,2,'2026-05-13','10:00:00','11:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(474,2,'2026-05-13','11:00:00','12:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(475,2,'2026-05-13','12:00:00','13:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(476,2,'2026-05-13','13:00:00','14:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(477,2,'2026-05-13','14:00:00','15:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(478,2,'2026-05-13','15:00:00','16:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(479,2,'2026-05-13','16:00:00','17:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(480,2,'2026-05-13','17:00:00','18:00:00',1,NULL,'2026-05-14 13:42:37','2026-05-14 13:42:37'),(685,2,'2026-05-11','09:00:00','09:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(686,2,'2026-05-11','09:30:00','10:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(687,2,'2026-05-11','10:00:00','10:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(688,2,'2026-05-11','10:30:00','11:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(689,2,'2026-05-11','11:00:00','11:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(690,2,'2026-05-11','11:30:00','12:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(691,2,'2026-05-11','12:00:00','12:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(692,2,'2026-05-11','12:30:00','13:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(693,2,'2026-05-11','13:00:00','13:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(694,2,'2026-05-11','13:30:00','14:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(695,2,'2026-05-11','14:00:00','14:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(696,2,'2026-05-11','14:30:00','15:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(697,2,'2026-05-11','15:00:00','15:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(698,2,'2026-05-11','15:30:00','16:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(699,2,'2026-05-11','16:00:00','16:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(700,2,'2026-05-11','16:30:00','17:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(701,2,'2026-05-12','09:00:00','09:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(702,2,'2026-05-12','09:30:00','10:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(703,2,'2026-05-12','10:00:00','10:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(704,2,'2026-05-12','10:30:00','11:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(705,2,'2026-05-12','11:00:00','11:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(706,2,'2026-05-12','11:30:00','12:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(707,2,'2026-05-12','12:00:00','12:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(708,2,'2026-05-12','12:30:00','13:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(709,2,'2026-05-12','13:00:00','13:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(710,2,'2026-05-12','13:30:00','14:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(711,2,'2026-05-12','14:00:00','14:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(712,2,'2026-05-12','14:30:00','15:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(713,2,'2026-05-12','15:00:00','15:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(714,2,'2026-05-12','15:30:00','16:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(715,2,'2026-05-12','16:00:00','16:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(716,2,'2026-05-12','16:30:00','17:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(717,2,'2026-05-13','10:00:00','10:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(718,2,'2026-05-13','10:30:00','11:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(719,2,'2026-05-13','11:00:00','11:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(720,2,'2026-05-13','11:30:00','12:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(721,2,'2026-05-13','12:00:00','12:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(722,2,'2026-05-13','12:30:00','13:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(723,2,'2026-05-13','13:00:00','13:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(724,2,'2026-05-13','13:30:00','14:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(725,2,'2026-05-13','14:00:00','14:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(726,2,'2026-05-13','14:30:00','15:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(727,2,'2026-05-13','15:00:00','15:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(728,2,'2026-05-13','15:30:00','16:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(729,2,'2026-05-13','16:00:00','16:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(730,2,'2026-05-13','16:30:00','17:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(731,2,'2026-05-13','17:00:00','17:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(732,2,'2026-05-13','17:30:00','18:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(733,2,'2026-05-14','09:00:00','09:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(734,2,'2026-05-14','09:30:00','10:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(735,2,'2026-05-14','10:00:00','10:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(736,2,'2026-05-14','10:30:00','11:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(737,2,'2026-05-14','11:00:00','11:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(738,2,'2026-05-14','11:30:00','12:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(739,2,'2026-05-14','12:00:00','12:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(740,2,'2026-05-14','12:30:00','13:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(741,2,'2026-05-14','13:00:00','13:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(742,2,'2026-05-14','13:30:00','14:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(743,2,'2026-05-14','14:00:00','14:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(744,2,'2026-05-14','14:30:00','15:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(745,2,'2026-05-14','15:00:00','15:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(746,2,'2026-05-14','15:30:00','16:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(747,2,'2026-05-14','16:00:00','16:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(748,2,'2026-05-14','16:30:00','17:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(749,2,'2026-05-15','09:00:00','09:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(750,2,'2026-05-15','09:30:00','10:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(751,2,'2026-05-15','10:00:00','10:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(752,2,'2026-05-15','10:30:00','11:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(753,2,'2026-05-15','11:00:00','11:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(754,2,'2026-05-15','11:30:00','12:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(755,2,'2026-05-15','12:00:00','12:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(756,2,'2026-05-15','12:30:00','13:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(757,2,'2026-05-15','13:00:00','13:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(758,2,'2026-05-15','13:30:00','14:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(759,2,'2026-05-15','14:00:00','14:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(760,2,'2026-05-15','14:30:00','15:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(761,2,'2026-05-18','09:00:00','09:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(762,2,'2026-05-18','09:30:00','10:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(763,2,'2026-05-18','10:00:00','10:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(764,2,'2026-05-18','10:30:00','11:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(765,2,'2026-05-18','11:00:00','11:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(766,2,'2026-05-18','11:30:00','12:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(767,2,'2026-05-18','12:00:00','12:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(768,2,'2026-05-18','12:30:00','13:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(769,2,'2026-05-18','13:00:00','13:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(770,2,'2026-05-18','13:30:00','14:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(771,2,'2026-05-18','14:00:00','14:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(772,2,'2026-05-18','14:30:00','15:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(773,2,'2026-05-18','15:00:00','15:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(774,2,'2026-05-18','15:30:00','16:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(775,2,'2026-05-18','16:00:00','16:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(776,2,'2026-05-18','16:30:00','17:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(777,2,'2026-05-19','09:00:00','09:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(778,2,'2026-05-19','09:30:00','10:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(779,2,'2026-05-19','10:00:00','10:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(780,2,'2026-05-19','10:30:00','11:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(781,2,'2026-05-19','11:00:00','11:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(782,2,'2026-05-19','11:30:00','12:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(783,2,'2026-05-19','12:00:00','12:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(784,2,'2026-05-19','12:30:00','13:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(785,2,'2026-05-19','13:00:00','13:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(786,2,'2026-05-19','13:30:00','14:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(787,2,'2026-05-19','14:00:00','14:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(788,2,'2026-05-19','14:30:00','15:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(789,2,'2026-05-19','15:00:00','15:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(790,2,'2026-05-19','15:30:00','16:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(791,2,'2026-05-19','16:00:00','16:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(792,2,'2026-05-19','16:30:00','17:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(793,2,'2026-05-20','10:00:00','10:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(794,2,'2026-05-20','10:30:00','11:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(795,2,'2026-05-20','11:00:00','11:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(796,2,'2026-05-20','11:30:00','12:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(797,2,'2026-05-20','12:00:00','12:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(798,2,'2026-05-20','12:30:00','13:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(799,2,'2026-05-20','13:00:00','13:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(800,2,'2026-05-20','13:30:00','14:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(801,2,'2026-05-20','14:00:00','14:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(802,2,'2026-05-20','14:30:00','15:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(803,2,'2026-05-20','15:00:00','15:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(804,2,'2026-05-20','15:30:00','16:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(805,2,'2026-05-20','16:00:00','16:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(806,2,'2026-05-20','16:30:00','17:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(807,2,'2026-05-20','17:00:00','17:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(808,2,'2026-05-20','17:30:00','18:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(809,2,'2026-05-21','09:00:00','09:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(810,2,'2026-05-21','09:30:00','10:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(811,2,'2026-05-21','10:00:00','10:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(812,2,'2026-05-21','10:30:00','11:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(813,2,'2026-05-21','11:00:00','11:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(814,2,'2026-05-21','11:30:00','12:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(815,2,'2026-05-21','12:00:00','12:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(816,2,'2026-05-21','12:30:00','13:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(817,2,'2026-05-21','13:00:00','13:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(818,2,'2026-05-21','13:30:00','14:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(819,2,'2026-05-21','14:00:00','14:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(820,2,'2026-05-21','14:30:00','15:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(821,2,'2026-05-21','15:00:00','15:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(822,2,'2026-05-21','15:30:00','16:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(823,2,'2026-05-21','16:00:00','16:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(824,2,'2026-05-21','16:30:00','17:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(825,2,'2026-05-22','09:00:00','09:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(826,2,'2026-05-22','09:30:00','10:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(827,2,'2026-05-22','10:00:00','10:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(828,2,'2026-05-22','10:30:00','11:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(829,2,'2026-05-22','11:00:00','11:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(830,2,'2026-05-22','11:30:00','12:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(831,2,'2026-05-22','12:00:00','12:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(832,2,'2026-05-22','12:30:00','13:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(833,2,'2026-05-22','13:00:00','13:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(834,2,'2026-05-22','13:30:00','14:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(835,2,'2026-05-22','14:00:00','14:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(836,2,'2026-05-22','14:30:00','15:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(837,2,'2026-05-25','09:00:00','09:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(838,2,'2026-05-25','09:30:00','10:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(839,2,'2026-05-25','10:00:00','10:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(840,2,'2026-05-25','10:30:00','11:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(841,2,'2026-05-25','11:00:00','11:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(842,2,'2026-05-25','11:30:00','12:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(843,2,'2026-05-25','12:00:00','12:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(844,2,'2026-05-25','12:30:00','13:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(845,2,'2026-05-25','13:00:00','13:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(846,2,'2026-05-25','13:30:00','14:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(847,2,'2026-05-25','14:00:00','14:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(848,2,'2026-05-25','14:30:00','15:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(849,2,'2026-05-25','15:00:00','15:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(850,2,'2026-05-25','15:30:00','16:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(851,2,'2026-05-25','16:00:00','16:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(852,2,'2026-05-25','16:30:00','17:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(853,2,'2026-05-26','09:00:00','09:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(854,2,'2026-05-26','09:30:00','10:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(855,2,'2026-05-26','10:00:00','10:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(856,2,'2026-05-26','10:30:00','11:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(857,2,'2026-05-26','11:00:00','11:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(858,2,'2026-05-26','11:30:00','12:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(859,2,'2026-05-26','12:00:00','12:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(860,2,'2026-05-26','12:30:00','13:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(861,2,'2026-05-26','13:00:00','13:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(862,2,'2026-05-26','13:30:00','14:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(863,2,'2026-05-26','14:00:00','14:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(864,2,'2026-05-26','14:30:00','15:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(865,2,'2026-05-26','15:00:00','15:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(866,2,'2026-05-26','15:30:00','16:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(867,2,'2026-05-26','16:00:00','16:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(868,2,'2026-05-26','16:30:00','17:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(869,2,'2026-05-27','10:00:00','10:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(870,2,'2026-05-27','10:30:00','11:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(871,2,'2026-05-27','11:00:00','11:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(872,2,'2026-05-27','11:30:00','12:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(873,2,'2026-05-27','12:00:00','12:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(874,2,'2026-05-27','12:30:00','13:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(875,2,'2026-05-27','13:00:00','13:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(876,2,'2026-05-27','13:30:00','14:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(877,2,'2026-05-27','14:00:00','14:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(878,2,'2026-05-27','14:30:00','15:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(879,2,'2026-05-27','15:00:00','15:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(880,2,'2026-05-27','15:30:00','16:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(881,2,'2026-05-27','16:00:00','16:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(882,2,'2026-05-27','16:30:00','17:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(883,2,'2026-05-27','17:00:00','17:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(884,2,'2026-05-27','17:30:00','18:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(885,2,'2026-05-28','09:00:00','09:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(886,2,'2026-05-28','09:30:00','10:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(887,2,'2026-05-28','10:00:00','10:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(888,2,'2026-05-28','10:30:00','11:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(889,2,'2026-05-28','11:00:00','11:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(890,2,'2026-05-28','11:30:00','12:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(891,2,'2026-05-28','12:00:00','12:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(892,2,'2026-05-28','12:30:00','13:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(893,2,'2026-05-28','13:00:00','13:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(894,2,'2026-05-28','13:30:00','14:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(895,2,'2026-05-28','14:00:00','14:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(896,2,'2026-05-28','14:30:00','15:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(897,2,'2026-05-28','15:00:00','15:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(898,2,'2026-05-28','15:30:00','16:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(899,2,'2026-05-28','16:00:00','16:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(900,2,'2026-05-28','16:30:00','17:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(901,2,'2026-05-29','09:00:00','09:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(902,2,'2026-05-29','09:30:00','10:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(903,2,'2026-05-29','10:00:00','10:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(904,2,'2026-05-29','10:30:00','11:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(905,2,'2026-05-29','11:00:00','11:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(906,2,'2026-05-29','11:30:00','12:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(907,2,'2026-05-29','12:00:00','12:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(908,2,'2026-05-29','12:30:00','13:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(909,2,'2026-05-29','13:00:00','13:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(910,2,'2026-05-29','13:30:00','14:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(911,2,'2026-05-29','14:00:00','14:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(912,2,'2026-05-29','14:30:00','15:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(913,2,'2026-06-01','09:00:00','09:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(914,2,'2026-06-01','09:30:00','10:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(915,2,'2026-06-01','10:00:00','10:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(916,2,'2026-06-01','10:30:00','11:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(917,2,'2026-06-01','11:00:00','11:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(918,2,'2026-06-01','11:30:00','12:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(919,2,'2026-06-01','12:00:00','12:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(920,2,'2026-06-01','12:30:00','13:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(921,2,'2026-06-01','13:00:00','13:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(922,2,'2026-06-01','13:30:00','14:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(923,2,'2026-06-01','14:00:00','14:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(924,2,'2026-06-01','14:30:00','15:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(925,2,'2026-06-01','15:00:00','15:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(926,2,'2026-06-01','15:30:00','16:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(927,2,'2026-06-01','16:00:00','16:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(928,2,'2026-06-01','16:30:00','17:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(929,2,'2026-06-02','09:00:00','09:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(930,2,'2026-06-02','09:30:00','10:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(931,2,'2026-06-02','10:00:00','10:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(932,2,'2026-06-02','10:30:00','11:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(933,2,'2026-06-02','11:00:00','11:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(934,2,'2026-06-02','11:30:00','12:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(935,2,'2026-06-02','12:00:00','12:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(936,2,'2026-06-02','12:30:00','13:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(937,2,'2026-06-02','13:00:00','13:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(938,2,'2026-06-02','13:30:00','14:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(939,2,'2026-06-02','14:00:00','14:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(940,2,'2026-06-02','14:30:00','15:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(941,2,'2026-06-02','15:00:00','15:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(942,2,'2026-06-02','15:30:00','16:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(943,2,'2026-06-02','16:00:00','16:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(944,2,'2026-06-02','16:30:00','17:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(945,2,'2026-06-03','10:00:00','10:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(946,2,'2026-06-03','10:30:00','11:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(947,2,'2026-06-03','11:00:00','11:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(948,2,'2026-06-03','11:30:00','12:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(949,2,'2026-06-03','12:00:00','12:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(950,2,'2026-06-03','12:30:00','13:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(951,2,'2026-06-03','13:00:00','13:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(952,2,'2026-06-03','13:30:00','14:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(953,2,'2026-06-03','14:00:00','14:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(954,2,'2026-06-03','14:30:00','15:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(955,2,'2026-06-03','15:00:00','15:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(956,2,'2026-06-03','15:30:00','16:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(957,2,'2026-06-03','16:00:00','16:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(958,2,'2026-06-03','16:30:00','17:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(959,2,'2026-06-03','17:00:00','17:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(960,2,'2026-06-03','17:30:00','18:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(961,2,'2026-06-04','09:00:00','09:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(962,2,'2026-06-04','09:30:00','10:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(963,2,'2026-06-04','10:00:00','10:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(964,2,'2026-06-04','10:30:00','11:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(965,2,'2026-06-04','11:00:00','11:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(966,2,'2026-06-04','11:30:00','12:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(967,2,'2026-06-04','12:00:00','12:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(968,2,'2026-06-04','12:30:00','13:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(969,2,'2026-06-04','13:00:00','13:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(970,2,'2026-06-04','13:30:00','14:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(971,2,'2026-06-04','14:00:00','14:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(972,2,'2026-06-04','14:30:00','15:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(973,2,'2026-06-04','15:00:00','15:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(974,2,'2026-06-04','15:30:00','16:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(975,2,'2026-06-04','16:00:00','16:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(976,2,'2026-06-04','16:30:00','17:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(977,2,'2026-06-05','09:00:00','09:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(978,2,'2026-06-05','09:30:00','10:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(979,2,'2026-06-05','10:00:00','10:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(980,2,'2026-06-05','10:30:00','11:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(981,2,'2026-06-05','11:00:00','11:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(982,2,'2026-06-05','11:30:00','12:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(983,2,'2026-06-05','12:00:00','12:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(984,2,'2026-06-05','12:30:00','13:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(985,2,'2026-06-05','13:00:00','13:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(986,2,'2026-06-05','13:30:00','14:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(987,2,'2026-06-05','14:00:00','14:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(988,2,'2026-06-05','14:30:00','15:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(989,2,'2026-06-08','09:00:00','09:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(990,2,'2026-06-08','09:30:00','10:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(991,2,'2026-06-08','10:00:00','10:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(992,2,'2026-06-08','10:30:00','11:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(993,2,'2026-06-08','11:00:00','11:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(994,2,'2026-06-08','11:30:00','12:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(995,2,'2026-06-08','12:00:00','12:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(996,2,'2026-06-08','12:30:00','13:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(997,2,'2026-06-08','13:00:00','13:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(998,2,'2026-06-08','13:30:00','14:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(999,2,'2026-06-08','14:00:00','14:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1000,2,'2026-06-08','14:30:00','15:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1001,2,'2026-06-08','15:00:00','15:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1002,2,'2026-06-08','15:30:00','16:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1003,2,'2026-06-08','16:00:00','16:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1004,2,'2026-06-08','16:30:00','17:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1005,2,'2026-06-09','09:00:00','09:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1006,2,'2026-06-09','09:30:00','10:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1007,2,'2026-06-09','10:00:00','10:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1008,2,'2026-06-09','10:30:00','11:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1009,2,'2026-06-09','11:00:00','11:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1010,2,'2026-06-09','11:30:00','12:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1011,2,'2026-06-09','12:00:00','12:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1012,2,'2026-06-09','12:30:00','13:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1013,2,'2026-06-09','13:00:00','13:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1014,2,'2026-06-09','13:30:00','14:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1015,2,'2026-06-09','14:00:00','14:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1016,2,'2026-06-09','14:30:00','15:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1017,2,'2026-06-09','15:00:00','15:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1018,2,'2026-06-09','15:30:00','16:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1019,2,'2026-06-09','16:00:00','16:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1020,2,'2026-06-09','16:30:00','17:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1021,2,'2026-06-10','10:00:00','10:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1022,2,'2026-06-10','10:30:00','11:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1023,2,'2026-06-10','11:00:00','11:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1024,2,'2026-06-10','11:30:00','12:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1025,2,'2026-06-10','12:00:00','12:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1026,2,'2026-06-10','12:30:00','13:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1027,2,'2026-06-10','13:00:00','13:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1028,2,'2026-06-10','13:30:00','14:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1029,2,'2026-06-10','14:00:00','14:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1030,2,'2026-06-10','14:30:00','15:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1031,2,'2026-06-10','15:00:00','15:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1032,2,'2026-06-10','15:30:00','16:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1033,2,'2026-06-10','16:00:00','16:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1034,2,'2026-06-10','16:30:00','17:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1035,2,'2026-06-10','17:00:00','17:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1036,2,'2026-06-10','17:30:00','18:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1037,2,'2026-06-11','09:00:00','09:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1038,2,'2026-06-11','09:30:00','10:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1039,2,'2026-06-11','10:00:00','10:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1040,2,'2026-06-11','10:30:00','11:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1041,2,'2026-06-11','11:00:00','11:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1042,2,'2026-06-11','11:30:00','12:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1043,2,'2026-06-11','12:00:00','12:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1044,2,'2026-06-11','12:30:00','13:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1045,2,'2026-06-11','13:00:00','13:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1046,2,'2026-06-11','13:30:00','14:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1047,2,'2026-06-11','14:00:00','14:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1048,2,'2026-06-11','14:30:00','15:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1049,2,'2026-06-11','15:00:00','15:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1050,2,'2026-06-11','15:30:00','16:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1051,2,'2026-06-11','16:00:00','16:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1052,2,'2026-06-11','16:30:00','17:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1053,2,'2026-06-12','09:00:00','09:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1054,2,'2026-06-12','09:30:00','10:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1055,2,'2026-06-12','10:00:00','10:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1056,2,'2026-06-12','10:30:00','11:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1057,2,'2026-06-12','11:00:00','11:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1058,2,'2026-06-12','11:30:00','12:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1059,2,'2026-06-12','12:00:00','12:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1060,2,'2026-06-12','12:30:00','13:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1061,2,'2026-06-12','13:00:00','13:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1062,2,'2026-06-12','13:30:00','14:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1063,2,'2026-06-12','14:00:00','14:30:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25'),(1064,2,'2026-06-12','14:30:00','15:00:00',1,NULL,'2026-05-14 13:43:25','2026-05-14 13:43:25');
/*!40000 ALTER TABLE `time_slots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_preferences`
--

DROP TABLE IF EXISTS `user_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_preferences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `receive_email_reminders` tinyint(1) NOT NULL DEFAULT '1',
  `receive_sms_reminders` tinyint(1) NOT NULL DEFAULT '0',
  `phone_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timezone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UTC',
  `preferred_staff` bigint unsigned DEFAULT NULL,
  `slot_duration_minutes` int NOT NULL DEFAULT '60',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_preferences_user_id_foreign` (`user_id`),
  KEY `user_preferences_preferred_staff_foreign` (`preferred_staff`),
  CONSTRAINT `user_preferences_preferred_staff_foreign` FOREIGN KEY (`preferred_staff`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_preferences_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_preferences`
--

LOCK TABLES `user_preferences` WRITE;
/*!40000 ALTER TABLE `user_preferences` DISABLE KEYS */;
INSERT INTO `user_preferences` VALUES (1,3,1,0,'+39123456789','UTC',4,60,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(2,4,1,0,NULL,'UTC',NULL,60,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(3,5,1,0,NULL,'UTC',NULL,60,'2026-05-14 13:42:36','2026-05-14 13:42:36'),(4,2,1,0,NULL,'UTC',NULL,30,'2026-05-14 13:42:36','2026-05-14 13:43:20');
/*!40000 ALTER TABLE `user_preferences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `internal_notes` text COLLATE utf8mb4_unicode_ci,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Admin','admin@test.com',NULL,'$2y$12$yU7rstlYZu24c9IZSNmShunyIWYr8A5Mc6pkHxwqO8UoTRUPiC0QG',NULL,NULL,'2026-05-14 13:42:34','2026-05-14 13:42:34'),(2,'Staff Demo','staff@test.com',NULL,'$2y$12$8NBSCHVvjOtoM9FW4mI3n.VskxUn7Eliczur0ktuUkoVlAzoCEESS',NULL,NULL,'2026-05-14 13:42:34','2026-05-14 13:42:35'),(3,'Cliente Demo','customer@test.com',NULL,'$2y$12$SIJt5oukHeUjeP9wcrI8/.3y.LWaimnmD5OL4TC0og.wbCSYm9KQu',NULL,NULL,'2026-05-14 13:42:35','2026-05-14 13:42:36'),(4,'Giulia Bianchi','giulia.staff@test.com',NULL,'$2y$12$Dghp40CPcCNtNKkAHrKUSeUZuNwDqDHZxToY3dbHP5jHff0JDp2sO',NULL,NULL,'2026-05-14 13:42:35','2026-05-14 13:42:35'),(5,'Marco Verdi','marco.staff@test.com',NULL,'$2y$12$rBHY1pIrMEIlVOI/Nc2QauNFVbpCvQL22S.CQ7Ws1yneSX5kwEMee',NULL,NULL,'2026-05-14 13:42:35','2026-05-14 13:42:35');
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

-- Dump completed on 2026-05-14 14:01:08
