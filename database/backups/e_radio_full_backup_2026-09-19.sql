-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: e_radio
-- ------------------------------------------------------
-- Server version	12.2.2-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
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
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Podcast','podcast',NULL,NULL,0,'2026-09-18 11:41:25','2026-09-18 11:41:25'),(2,'News','news',NULL,NULL,1,'2026-09-18 11:41:25','2026-09-18 11:41:25'),(3,'Health','health',NULL,NULL,2,'2026-09-18 11:41:25','2026-09-18 11:41:25'),(4,'Economy/Finance','economyfinance',NULL,NULL,3,'2026-09-18 11:41:25','2026-09-18 11:41:25'),(5,'Song','song',NULL,NULL,4,'2026-09-18 11:41:25','2026-09-18 11:41:25'),(6,'Ambient Music','ambient-music',NULL,NULL,5,'2026-09-18 11:41:25','2026-09-18 11:41:25');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
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
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
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
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
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
-- Table structure for table `media_content_tag`
--

DROP TABLE IF EXISTS `media_content_tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `media_content_tag` (
  `media_content_id` bigint(20) unsigned NOT NULL,
  `tag_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`media_content_id`,`tag_id`),
  KEY `media_content_tag_tag_id_foreign` (`tag_id`),
  CONSTRAINT `media_content_tag_media_content_id_foreign` FOREIGN KEY (`media_content_id`) REFERENCES `media_contents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `media_content_tag_tag_id_foreign` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `media_content_tag`
--

LOCK TABLES `media_content_tag` WRITE;
/*!40000 ALTER TABLE `media_content_tag` DISABLE KEYS */;
/*!40000 ALTER TABLE `media_content_tag` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `media_contents`
--

DROP TABLE IF EXISTS `media_contents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `media_contents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint(20) unsigned NOT NULL,
  `program_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `artist_or_author` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `media_type` enum('audio','video','article') NOT NULL DEFAULT 'audio',
  `stream_url` varchar(255) DEFAULT NULL,
  `thumbnail_url` varchar(255) DEFAULT NULL,
  `duration_seconds` int(10) unsigned DEFAULT NULL,
  `play_count` int(10) unsigned NOT NULL DEFAULT 0,
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `media_contents_slug_unique` (`slug`),
  KEY `media_contents_category_id_foreign` (`category_id`),
  KEY `media_contents_program_id_foreign` (`program_id`),
  CONSTRAINT `media_contents_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `media_contents_program_id_foreign` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `media_contents`
--

LOCK TABLES `media_contents` WRITE;
/*!40000 ALTER TABLE `media_contents` DISABLE KEYS */;
/*!40000 ALTER TABLE `media_contents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_09_18_000001_create_admin_users_table',1),(5,'2026_09_18_000001_create_radio_sceaper_stations_table',1),(6,'2026_09_18_000001_create_radio_scraper_settings_table',1),(7,'2026_09_18_000001_create_radio_tables',1),(8,'2026_09_18_000001_create_roles_and_permissions_tables',1),(9,'2026_09_19_000001_create_categories_table',2),(10,'2026_09_19_000002_create_tags_table',2),(11,'2026_09_19_000003_create_programs_table',2),(12,'2026_09_19_000004_create_media_contents_table',2),(13,'2026_09_19_000005_create_media_content_tag_table',2),(14,'2026_09_19_000001_create_app_users_table',3),(15,'2026_09_19_000002_add_username_to_app_users_table',4),(16,'2026_09_19_000003_restructure_app_users_name_fields',4),(17,'2026_09_19_000006_merge_admin_and_app_users_into_users_table',5);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
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
-- Table structure for table `permission_role`
--

DROP TABLE IF EXISTS `permission_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permission_role` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `permission_role_role_id_foreign` (`role_id`),
  CONSTRAINT `permission_role_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `permission_role_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permission_role`
--

LOCK TABLES `permission_role` WRITE;
/*!40000 ALTER TABLE `permission_role` DISABLE KEYS */;
INSERT INTO `permission_role` VALUES (1,1),(2,1),(3,1),(4,1),(5,1),(6,1);
/*!40000 ALTER TABLE `permission_role` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'Manage Radio Channels','manage-radio-channels','2026-09-18 11:28:33','2026-09-18 11:28:33'),(2,'Manage Users','manage-users','2026-09-18 11:28:33','2026-09-18 11:28:33'),(3,'Manage Roles','manage-roles','2026-09-18 11:28:33','2026-09-18 11:28:33'),(4,'Manage Permissions','manage-permissions','2026-09-18 11:28:33','2026-09-18 11:28:33'),(5,'Manage Settings','manage-settings','2026-09-18 11:28:33','2026-09-18 11:28:33'),(6,'View Dashboard','view-dashboard','2026-09-18 12:30:31','2026-09-18 12:30:31');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `programs`
--

DROP TABLE IF EXISTS `programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `programs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `programs_slug_unique` (`slug`),
  KEY `programs_category_id_foreign` (`category_id`),
  CONSTRAINT `programs_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `programs`
--

LOCK TABLES `programs` WRITE;
/*!40000 ALTER TABLE `programs` DISABLE KEYS */;
/*!40000 ALTER TABLE `programs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `radio_playlists`
--

DROP TABLE IF EXISTS `radio_playlists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `radio_playlists` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `radio_station_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(24) NOT NULL DEFAULT 'general',
  `weight` smallint(5) unsigned NOT NULL DEFAULT 1,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `radio_playlists_radio_station_id_foreign` (`radio_station_id`),
  CONSTRAINT `radio_playlists_radio_station_id_foreign` FOREIGN KEY (`radio_station_id`) REFERENCES `radio_stations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `radio_playlists`
--

LOCK TABLES `radio_playlists` WRITE;
/*!40000 ALTER TABLE `radio_playlists` DISABLE KEYS */;
/*!40000 ALTER TABLE `radio_playlists` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `radio_sceaper_stations`
--

DROP TABLE IF EXISTS `radio_sceaper_stations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `radio_sceaper_stations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `stream_url` varchar(255) NOT NULL,
  `bitrate` smallint(5) unsigned DEFAULT NULL,
  `votes` int(10) unsigned NOT NULL DEFAULT 0,
  `tags` varchar(255) DEFAULT NULL,
  `radio_browser_uuid` varchar(255) DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `radio_sceaper_stations_radio_browser_uuid_unique` (`radio_browser_uuid`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `radio_sceaper_stations`
--

LOCK TABLES `radio_sceaper_stations` WRITE;
/*!40000 ALTER TABLE `radio_sceaper_stations` DISABLE KEYS */;
INSERT INTO `radio_sceaper_stations` VALUES (1,'National Radio of Kampuchea','http://119.82.252.6:8080/broadwave.mp3',0,4935,'','eedb7498-f59d-4f07-9efc-b77853d6abf5',0,'2026-09-18 11:45:32','2026-09-18 11:45:32'),(2,'Abdulbasit Abdulsamad','https://radio.mp3islam.com/listen/abdulbasit/radio.mp3',64,263,'','10c7239a-3404-4a54-82b8-7b4cc9603779',0,'2026-09-18 11:45:32','2026-09-18 11:45:32'),(3,'Pop Radio 21','https://listen.radioking.com/radio/318317/stream/365846',128,52,'asian,folk,khmer,phnom penh,pop','5bfc4b1a-def1-44ab-8d6d-e7c63820f781',0,'2026-09-18 11:45:32','2026-09-18 11:45:32'),(4,'Hang Meas FM 104.5','http://malisresidences.com:89/broadwave.mp3',0,46,'entertainment,khmer,phnom penh,pop','405a8b9c-4e9f-442c-b9c8-f65813905da3',1,'2026-09-18 11:45:32','2026-09-18 11:45:49'),(5,'Family FM 99.5 (Witthyu Krousar)','https://s14.myradiostream.com/10064/;.mp3',64,30,'family,khmer,music,phnom penh','cc20bea1-aeea-4801-8f62-6aaab144f150',1,'2026-09-18 11:45:32','2026-09-18 11:45:49'),(6,'LDP Radio','https://streaming.radio.co/sd48fd711d/listen',192,18,'khmer,phnom penh,politics,talk','053872d7-f972-4db7-b19c-993e6553141d',0,'2026-09-18 11:45:32','2026-09-18 11:45:32'),(7,'RNK FM 96 (Radio National Kampuchea)','http://119.82.252.6:8181/broadwave.mp3',0,18,'khmer,national,news,phnom penh,public','1bf295fa-b2c7-4807-a438-c21105f0cb7a',1,'2026-09-18 11:45:32','2026-09-18 11:45:49'),(8,'Sweet FM 88 (Siem Reap)','https://n12.rcs.revma.com/whn9hqwstk3vv',0,9,'khmer,music,pop,siem reap','e39cc117-47c6-4eae-9d4b-d70c3087831f',0,'2026-09-18 11:45:32','2026-09-18 11:45:32'),(9,'Sangkem Radio','https://live.sangkemtv.com/radio.mp3',0,4,'','65b508f2-e022-4831-9dd5-b98d5a348452',0,'2026-09-18 11:45:32','2026-09-18 11:45:32');
/*!40000 ALTER TABLE `radio_sceaper_stations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `radio_schedules`
--

DROP TABLE IF EXISTS `radio_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `radio_schedules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `radio_station_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `host` varchar(255) DEFAULT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `days` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`days`)),
  `source_type` varchar(24) NOT NULL DEFAULT 'playlist',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `radio_schedules_radio_station_id_foreign` (`radio_station_id`),
  CONSTRAINT `radio_schedules_radio_station_id_foreign` FOREIGN KEY (`radio_station_id`) REFERENCES `radio_stations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `radio_schedules`
--

LOCK TABLES `radio_schedules` WRITE;
/*!40000 ALTER TABLE `radio_schedules` DISABLE KEYS */;
/*!40000 ALTER TABLE `radio_schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `radio_scraper_settings`
--

DROP TABLE IF EXISTS `radio_scraper_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `radio_scraper_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `country` varchar(2) NOT NULL DEFAULT 'KH',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `radio_scraper_settings`
--

LOCK TABLES `radio_scraper_settings` WRITE;
/*!40000 ALTER TABLE `radio_scraper_settings` DISABLE KEYS */;
INSERT INTO `radio_scraper_settings` VALUES (1,0,'KH','2026-09-18 11:22:51','2026-09-18 11:22:51');
/*!40000 ALTER TABLE `radio_scraper_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `radio_stations`
--

DROP TABLE IF EXISTS `radio_stations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `radio_stations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `frequency` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `azuracast_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `radio_stations_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `radio_stations`
--

LOCK TABLES `radio_stations` WRITE;
/*!40000 ALTER TABLE `radio_stations` DISABLE KEYS */;
/*!40000 ALTER TABLE `radio_stations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `radio_streams`
--

DROP TABLE IF EXISTS `radio_streams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `radio_streams` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `radio_station_id` bigint(20) unsigned NOT NULL,
  `protocol` varchar(24) NOT NULL,
  `codec` varchar(16) NOT NULL,
  `bitrate` smallint(5) unsigned DEFAULT NULL,
  `url` varchar(255) NOT NULL,
  `priority` smallint(5) unsigned NOT NULL DEFAULT 100,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `health_status` varchar(24) NOT NULL DEFAULT 'unknown',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `radio_streams_radio_station_id_foreign` (`radio_station_id`),
  CONSTRAINT `radio_streams_radio_station_id_foreign` FOREIGN KEY (`radio_station_id`) REFERENCES `radio_stations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `radio_streams`
--

LOCK TABLES `radio_streams` WRITE;
/*!40000 ALTER TABLE `radio_streams` DISABLE KEYS */;
/*!40000 ALTER TABLE `radio_streams` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_user`
--

DROP TABLE IF EXISTS `role_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_user` (
  `role_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`user_id`),
  KEY `role_user_user_id_foreign` (`user_id`),
  CONSTRAINT `role_user_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_user`
--

LOCK TABLES `role_user` WRITE;
/*!40000 ALTER TABLE `role_user` DISABLE KEYS */;
INSERT INTO `role_user` VALUES (1,1);
/*!40000 ALTER TABLE `role_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Admin','admin','2026-09-18 11:28:33','2026-09-18 11:28:33');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
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
INSERT INTO `sessions` VALUES ('5JftofaMHycJX95YpnpInzMEpeW5hEk5iuNEfSr7',NULL,'127.0.0.1','curl/8.17.0','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiZ01lV2NHRWR5ZTBPd3JWb255akZuUFFGM25sdWNQUlgyY3Z3NXdhcCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9kYXNoYm9hcmQiO3M6NToicm91dGUiO3M6MTU6ImRhc2hib2FyZC5pbmRleCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6MzoidXJsIjthOjE6e3M6ODoiaW50ZW5kZWQiO3M6MzE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9kYXNoYm9hcmQiO319',1789759872),('84gmnI5j8yiwHj7uhPI9I5OMSVUEwIQyvoeMxotF',NULL,'127.0.0.1','curl/8.17.0','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiMXFUblBvdW9iOEY1aUU5YmVXS1gxSEw0dXdLeEhvVHFWVVUxSFpPOCI7czozOiJ1cmwiO2E6MTp7czo4OiJpbnRlbmRlZCI7czozMToiaHR0cDovLzEyNy4wLjAuMTo4MDAwL2Rhc2hib2FyZCI7fXM6OToiX3ByZXZpb3VzIjthOjI6e3M6MzoidXJsIjtzOjMxOiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZGFzaGJvYXJkIjtzOjU6InJvdXRlIjtzOjE1OiJkYXNoYm9hcmQuaW5kZXgiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1789759843),('CaoThhG1NuARKVL8OkAM96DLj5lxnxHBjtIK1sTM',NULL,'127.0.0.1','curl/8.17.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoibjM5bmFlczAzUjJ3MktBWFF3MU9pVmk3UXZWRmNNQVhlbHBJWVh2OCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1789760370),('fu3jv54j4iEOCTKRVUDZtA0OGtKkymUJfi9ESwVZ',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.138.0 Chrome/148.0.7778.280 Electron/42.10.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiQ1VSU1pkUXczcWJVZEd4RDNQaGJMS0JhN0FVQ0pZc2dDUVA2Z3ZQeCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9yYWRpbyI7czo1OiJyb3V0ZSI7czoxMToicmFkaW8uaW5kZXgiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1789757566),('I064RbmwGcAXyTVA04i5J319lBViQ1IjJep1Oe7D',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiWVNmZWxCRkZxUW14QWIwbDVUTVl4ZlVVT3A0Z2xoeWdSNmZ2OENzSyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9sb2dpbiI7czo1OiJyb3V0ZSI7czoxNDoiY3VzdG9tZXIubG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1789758324),('ihNYqz071lXW1viRv1GeCjJvnOBX1MKrthtELrq4',1,'127.0.0.1','curl/8.17.0','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiQ0xaOWF5ajZKY3RUbExnZnZ2Z0VyUnoycGlPNVhmWjhxQ1FZVk5PNyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9kYXNoYm9hcmQiO3M6NToicm91dGUiO3M6MTU6ImRhc2hib2FyZC5pbmRleCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6NTI6ImxvZ2luX2FkbWluXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTt9',1789759886),('jTMOfqhIM7MC0giQoIPfeVwan8V4mg0AT8bE1Xf5',NULL,'127.0.0.1','curl/8.17.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoiTjdrQkwwbGJNRExPYlYycW42ZEtSbEtQTmd4RUx5VW5NM3FTU3RTRCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzM6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hZG1pbi9sb2dpbiI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1789760370),('SPH5nlkk1xtMYHwUs3QJFXMCgRRz8LgzxSisP5aw',NULL,'127.0.0.1','curl/8.17.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoiN29oWlRWVDd5b3A0RGhOTU9Ec1VXOEVySWl2NHVMMTc0TWdRZ0NsSiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1789759843),('U83dYdb8g60XXbEPA4Zphx2wBn2DaMnsCI8DJytj',NULL,'127.0.0.1','curl/8.17.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoiWHdvRnBWMHRrc2tUdnhZOHQyUndjNDdIWmlxTlBPM1dPalg5RjA5ZiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzM6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hZG1pbi9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1789759689),('WmzHlhkGUcjTdvneU3erIEO5BhXPtWCeJOp5ycc9',NULL,'127.0.0.1','curl/8.17.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoiMU5ZRmJKQ0p6YmwwUHVHSDFCTGJSN0pXdjBRTjN5N2Vyam9YdEpaTSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9sb2dpbiI7czo1OiJyb3V0ZSI7czoxNDoiY3VzdG9tZXIubG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1789759689),('xo4YUIWQ41stIsG0rRGgEx0qxO1TF27LTR4ZpAEl',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','YTo0OntzOjY6Il90b2tlbiI7czo0MDoidUpKUTVxa25oSDZyRnFTYmZmRnNJNTlERTdXdmRCa1ZOTXpnVkpwSCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6MzoidXJsIjthOjE6e3M6ODoiaW50ZW5kZWQiO3M6MzE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9kYXNoYm9hcmQiO319',1789762124),('yDm513bfbSp35eIOlyvcce5wNrrzIVWfMtFux0Jd',1,'127.0.0.1','curl/8.17.0','YTo0OntzOjY6Il90b2tlbiI7czo0MDoibHNnVkozRFB3N1Z5MXJjSTJnRjNXMTNPbDRJUzVTZ3RqanNkRGZucCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDI6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hZG1pbi9yYWRpby1jaGFubmVscyI7czo1OiJyb3V0ZSI7czoyNjoiYWRtaW4ucmFkaW8tY2hhbm5lbHMuaW5kZXgiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUyOiJsb2dpbl9hZG1pbl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjE7fQ==',1789760384);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tags`
--

DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tags` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tags_parent_id_slug_unique` (`parent_id`,`slug`),
  CONSTRAINT `tags_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tags`
--

LOCK TABLES `tags` WRITE;
/*!40000 ALTER TABLE `tags` DISABLE KEYS */;
INSERT INTO `tags` VALUES (1,NULL,'Podcast','podcast','2026-09-18 11:41:25','2026-09-18 11:41:25'),(2,1,'Health','health','2026-09-18 11:41:25','2026-09-18 11:41:25'),(3,1,'Economy/Finance','economyfinance','2026-09-18 11:41:25','2026-09-18 11:41:25'),(4,1,'Education','education','2026-09-18 11:41:25','2026-09-18 11:41:25'),(5,1,'Politic','politic','2026-09-18 11:41:25','2026-09-18 11:41:25'),(6,1,'Civilization','civilization','2026-09-18 11:41:25','2026-09-18 11:41:25'),(7,NULL,'Health','health','2026-09-18 11:41:25','2026-09-18 11:41:25'),(8,7,'Mental Health','mental-health','2026-09-18 11:41:25','2026-09-18 11:41:25'),(9,7,'Physical Health','physical-health','2026-09-18 11:41:25','2026-09-18 11:41:25'),(10,NULL,'Economy/Finance','economyfinance','2026-09-18 11:41:25','2026-09-18 11:41:25'),(11,10,'International','international','2026-09-18 11:41:25','2026-09-18 11:41:25'),(12,10,'Local','local','2026-09-18 11:41:25','2026-09-18 11:41:25'),(13,NULL,'News','news','2026-09-18 11:41:25','2026-09-18 11:41:25'),(14,13,'Politic','politic','2026-09-18 11:41:25','2026-09-18 11:41:25'),(15,13,'Economy/Finance','economyfinance','2026-09-18 11:41:25','2026-09-18 11:41:25'),(16,13,'Education','education','2026-09-18 11:41:25','2026-09-18 11:41:25'),(17,13,'Sports','sports','2026-09-18 11:41:25','2026-09-18 11:41:25'),(18,13,'Civilization','civilization','2026-09-18 11:41:25','2026-09-18 11:41:25'),(19,NULL,'Song','song','2026-09-18 11:41:25','2026-09-18 11:41:25'),(20,19,'International','international','2026-09-18 11:41:25','2026-09-18 11:41:25'),(21,20,'Genre','genre','2026-09-18 11:41:25','2026-09-18 11:41:25'),(22,21,'Hiphop/Rap','hiphoprap','2026-09-18 11:41:25','2026-09-18 11:41:25'),(23,21,'Rock','rock','2026-09-18 11:41:25','2026-09-18 11:41:25'),(24,21,'EDM','edm','2026-09-18 11:41:25','2026-09-18 11:41:25'),(25,21,'Country','country','2026-09-18 11:41:25','2026-09-18 11:41:25'),(26,21,'Pop','pop','2026-09-18 11:41:25','2026-09-18 11:41:25'),(27,21,'Folk','folk','2026-09-18 11:41:25','2026-09-18 11:41:25'),(28,20,'Artist','artist','2026-09-18 11:41:25','2026-09-18 11:41:25'),(29,19,'Local','local','2026-09-18 11:41:25','2026-09-18 11:41:25'),(30,29,'Genre','genre','2026-09-18 11:41:25','2026-09-18 11:41:25'),(31,30,'Hiphop/Rap','hiphoprap','2026-09-18 11:41:25','2026-09-18 11:41:25'),(32,30,'Rock','rock','2026-09-18 11:41:25','2026-09-18 11:41:25'),(33,30,'EDM','edm','2026-09-18 11:41:25','2026-09-18 11:41:25'),(34,30,'Country','country','2026-09-18 11:41:25','2026-09-18 11:41:25'),(35,30,'Pop','pop','2026-09-18 11:41:25','2026-09-18 11:41:25'),(36,30,'Folk','folk','2026-09-18 11:41:25','2026-09-18 11:41:25'),(37,29,'Artist','artist','2026-09-18 11:41:25','2026-09-18 11:41:25'),(38,NULL,'Ambient Music','ambient-music','2026-09-18 11:41:25','2026-09-18 11:41:25'),(39,38,'Reading','reading','2026-09-18 11:41:25','2026-09-18 11:41:25'),(40,38,'Studying','studying','2026-09-18 11:41:25','2026-09-18 11:41:25'),(41,38,'Yoga','yoga','2026-09-18 11:41:25','2026-09-18 11:41:25'),(42,38,'Working','working','2026-09-18 11:41:25','2026-09-18 11:41:25'),(43,38,'Lobby/Lounge','lobbylounge','2026-09-18 11:41:25','2026-09-18 11:41:25'),(44,38,'Khmer Traditional Music','khmer-traditional-music','2026-09-18 11:41:25','2026-09-18 11:41:25');
/*!40000 ALTER TABLE `tags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone_number` varchar(255) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_username_unique` (`username`),
  UNIQUE KEY `users_google_id_unique` (`google_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin',NULL,'Admin',NULL,NULL,'Admin',NULL,NULL,NULL,'$2y$12$R.lMcYc1GhRmhPQQwPryneKcWgN8smOs5sULYAtGF3nmkRsIHSqMi',NULL,NULL,NULL,'2026-09-18 11:28:33','2026-09-18 12:44:37');
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

-- Dump completed on 2026-09-19  3:19:32
