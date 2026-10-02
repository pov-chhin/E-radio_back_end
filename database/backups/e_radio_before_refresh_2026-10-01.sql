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
-- Table structure for table `artists`
--

DROP TABLE IF EXISTS `artists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `artists` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `stage_name` varchar(255) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `cover` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `social_links` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`social_links`)),
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `artists_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `artists`
--

LOCK TABLES `artists` WRITE;
/*!40000 ALTER TABLE `artists` DISABLE KEYS */;
INSERT INTO `artists` VALUES (2,'Chhin Pov','hello-world','chhin Pov','artists/photos/fe43ee29-504b-461c-b145-beadcc94e805.webp','artists/covers/94e3a4c9-d261-433a-b139-e7fd411c6773.webp',NULL,1,'[]','active','2026-09-18 16:38:18','2026-09-18 17:15:44');
/*!40000 ALTER TABLE `artists` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `azuracast_settings`
--

DROP TABLE IF EXISTS `azuracast_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `azuracast_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `url` varchar(255) DEFAULT NULL,
  `api_key` varchar(255) DEFAULT NULL,
  `station_id` varchar(255) NOT NULL DEFAULT 'azuratest',
  `timeout` int(10) unsigned NOT NULL DEFAULT 5,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `azuracast_settings`
--

LOCK TABLES `azuracast_settings` WRITE;
/*!40000 ALTER TABLE `azuracast_settings` DISABLE KEYS */;
INSERT INTO `azuracast_settings` VALUES (1,'http://172.30.30.206','f8a89caa996d8050:e679625c84fe382e1fb2199ccfbd265a','ams_fm97radio',5,'2026-09-22 23:53:00','2026-09-23 00:19:03');
/*!40000 ALTER TABLE `azuracast_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bookmarks`
--

DROP TABLE IF EXISTS `bookmarks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bookmarks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `bookmarkable_type` varchar(255) NOT NULL,
  `bookmarkable_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bookmarks_user_id_bookmarkable_type_bookmarkable_id_unique` (`user_id`,`bookmarkable_type`,`bookmarkable_id`),
  CONSTRAINT `bookmarks_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookmarks`
--

LOCK TABLES `bookmarks` WRITE;
/*!40000 ALTER TABLE `bookmarks` DISABLE KEYS */;
/*!40000 ALTER TABLE `bookmarks` ENABLE KEYS */;
UNLOCK TABLES;

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
INSERT INTO `cache` VALUES ('ams-e-radio-cache-5c785c036466adea360111aa28563bfd556b5fba','i:9;',1790564600),('ams-e-radio-cache-5c785c036466adea360111aa28563bfd556b5fba:timer','i:1790564600;',1790564600),('ams-e-radio-cache-password-forgot437218d08c14592712b5bbaa2ca8f1dcf6f80010','i:4;',1790565724),('ams-e-radio-cache-password-forgot437218d08c14592712b5bbaa2ca8f1dcf6f80010:timer','i:1790565724;',1790565724),('ams-e-radio-cache-password-forgot5c785c036466adea360111aa28563bfd556b5fba','i:1;',1790564705),('ams-e-radio-cache-password-forgot5c785c036466adea360111aa28563bfd556b5fba:timer','i:1790564705;',1790564705),('ams-e-radio-cache-password-forgote49a839787fb18fa5d62c8fcdfc95d267712b625','i:1;',1790823264),('ams-e-radio-cache-password-forgote49a839787fb18fa5d62c8fcdfc95d267712b625:timer','i:1790823264;',1790823264),('ams-e-radio-cache-password-reset:code:34a65ba254dafa262649e7ea2b558bda118a6dd4','a:3:{s:4:\"hash\";s:60:\"$2y$12$/EojXtC8h294tGUdN1hSMOlgb/GaqNbR3sXIC4xZ2JbAyKO3EqET.\";s:8:\"attempts\";i:0;s:10:\"expires_at\";i:1790823264;}',1790823264),('ams-e-radio-cache-password-reset:code:d6565b26bd59b98688b1c9c3787a03b3b81d60e6','a:3:{s:4:\"hash\";s:60:\"$2y$12$kx0JQ7HXk7FPIVwLiyllt.IaDUqVRA7bhwtDc7AEmOybRW8i2tOCq\";s:8:\"attempts\";i:1;s:10:\"expires_at\";i:1790564661;}',1790564661),('ams-e-radio-cache-password-reset:cooldown:1bb43dd6d1e0326a09b468f2b7279c11c8bd8d26','b:1;',1790564675),('ams-e-radio-cache-password-reset:cooldown:34a65ba254dafa262649e7ea2b558bda118a6dd4','b:1;',1790823264),('ams-e-radio-cache-password-reset:cooldown:d6565b26bd59b98688b1c9c3787a03b3b81d60e6','b:1;',1790564570),('ams-e-radio-cache-password-reset5c785c036466adea360111aa28563bfd556b5fba','i:3;',1790564709),('ams-e-radio-cache-password-reset5c785c036466adea360111aa28563bfd556b5fba:timer','i:1790564709;',1790564709),('ams-e-radio-cache-password-verify5c785c036466adea360111aa28563bfd556b5fba','i:3;',1790564707),('ams-e-radio-cache-password-verify5c785c036466adea360111aa28563bfd556b5fba:timer','i:1790564707;',1790564707);
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
INSERT INTO `categories` VALUES (1,'Podcast','podcast',NULL,NULL,0,'2026-09-18 13:20:05','2026-09-18 13:20:05'),(2,'News','news',NULL,NULL,1,'2026-09-18 13:20:05','2026-09-18 13:20:05'),(3,'Health','health',NULL,NULL,2,'2026-09-18 13:20:05','2026-09-18 13:20:05'),(4,'Economy/Finance','economyfinance',NULL,NULL,3,'2026-09-18 13:20:05','2026-09-18 13:20:05'),(5,'Song','song',NULL,NULL,4,'2026-09-18 13:20:05','2026-09-18 13:20:05'),(6,'Ambient Music','ambient-music',NULL,NULL,5,'2026-09-18 13:20:05','2026-09-18 13:20:05');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comments`
--

DROP TABLE IF EXISTS `comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `commentable_type` varchar(255) NOT NULL,
  `commentable_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `comments_user_id_foreign` (`user_id`),
  KEY `comments_commentable_type_commentable_id_index` (`commentable_type`,`commentable_id`),
  CONSTRAINT `comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comments`
--

LOCK TABLES `comments` WRITE;
/*!40000 ALTER TABLE `comments` DISABLE KEYS */;
INSERT INTO `comments` VALUES (1,'station',5,10,'Great station!','2026-09-21 00:54:43','2026-09-21 00:54:43');
/*!40000 ALTER TABLE `comments` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
INSERT INTO `media_content_tag` VALUES (8,1),(12,1),(8,2),(8,3),(8,4),(8,5),(8,6),(12,6),(13,7),(13,8),(4,10),(4,12),(10,12),(11,13),(11,17),(5,19),(7,19),(5,20),(7,20),(5,21),(7,21),(5,25),(7,25),(7,27),(4,38),(10,39),(7,40),(4,42),(7,42);
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
  `artist_id` bigint(20) unsigned DEFAULT NULL,
  `author_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `media_type` enum('audio','video','article') NOT NULL DEFAULT 'audio',
  `stream` varchar(255) DEFAULT NULL,
  `processing_status` varchar(20) NOT NULL DEFAULT 'ready',
  `source_path` varchar(255) DEFAULT NULL,
  `processing_error` text DEFAULT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `duration_seconds` int(10) unsigned DEFAULT NULL,
  `play_count` int(10) unsigned NOT NULL DEFAULT 0,
  `favorite_count` int(10) unsigned NOT NULL DEFAULT 0,
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `media_contents_slug_unique` (`slug`),
  KEY `media_contents_category_id_foreign` (`category_id`),
  KEY `media_contents_program_id_foreign` (`program_id`),
  KEY `media_contents_artist_id_foreign` (`artist_id`),
  KEY `media_contents_author_id_foreign` (`author_id`),
  CONSTRAINT `media_contents_artist_id_foreign` FOREIGN KEY (`artist_id`) REFERENCES `artists` (`id`) ON DELETE SET NULL,
  CONSTRAINT `media_contents_author_id_foreign` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `media_contents_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `media_contents_program_id_foreign` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `media_contents`
--

LOCK TABLES `media_contents` WRITE;
/*!40000 ALTER TABLE `media_contents` DISABLE KEYS */;
INSERT INTO `media_contents` VALUES (4,5,NULL,2,1,'Top 20 Pop Songs 2026 ♫ Justin Bieber','Top-20-Pop-Songs-2026-Justin-Bieber','<p>test teat</p>','audio','media-contents/streams/d2cc93b5-187c-469a-8613-e512dbcd954b.opus','ready',NULL,NULL,'media-contents/thumbnails/5dcd0659-bdbe-40cf-b4ca-189754384452.webp',NULL,0,0,1,'2026-09-18 19:31:26','2026-09-18 19:31:26','2026-09-20 21:03:00'),(5,5,NULL,2,1,'test 333','test-333','<p>test 333</p>','audio','media-contents/streams/0eaa3c20-47f4-4d1d-a653-5bf90cfc35f1.opus','ready',NULL,NULL,'media-contents/thumbnails/95067ed7-1ea3-41c3-bcb8-9953affbae48.webp',NULL,0,0,1,'2026-09-20 20:59:16','2026-09-20 20:59:16','2026-09-20 20:59:43'),(7,5,NULL,2,1,'Duration (seconds)','Duration-seconds','<p><strong style=\"background-color: rgb(255, 255, 255); color: rgb(23, 25, 39);\">Duration (seconds)</strong></p>','audio','media-contents/streams/f0488089-abd6-4e01-bc42-6fc37774097b.opus','ready',NULL,NULL,'media-contents/thumbnails/4fe882b9-5a29-4963-86d4-cffdffa2e8d2.webp',1164,0,0,1,'2026-09-20 23:45:00','2026-09-20 23:14:44','2026-09-28 02:32:45'),(8,1,2,2,1,'ffffffffffffff','ffffffffffffffff','<p>ggggggggggggggggggg</p>','audio','media-contents/streams/bb33fd73-e9c0-49d5-84db-f7a024460f14.opus','ready',NULL,NULL,'media-contents/thumbnails/6f7a368d-b369-4a9b-b17d-9c0988e129d7.webp',2458,0,0,1,'2026-09-27 23:32:20','2026-09-27 21:15:26','2026-09-27 23:32:20'),(9,1,3,NULL,1,'episode episode','episode-episode','<p><strong style=\"color: rgb(61, 63, 77);\"> episode episode episode episode episode</strong></p>','audio','media-contents/streams/a8ac608e-7483-4b44-92c7-47f2e0545c1d.opus','ready',NULL,NULL,'media-contents/thumbnails/e02485c9-4515-4276-8301-efd3239f83bd.webp',305,0,0,1,'2026-09-27 23:46:17','2026-09-27 23:46:17','2026-09-27 23:46:21'),(10,4,NULL,NULL,1,'test1','episode-episode-episode-episode-episode','<p><strong style=\"color: rgb(61, 63, 77);\"> episode episode episode episode episode episode episode episode</strong></p>','audio','media-contents/streams/65f31b67-202b-4703-b3e1-943926697c4e.opus','ready',NULL,NULL,'media-contents/thumbnails/47a2d48f-8fe2-49ea-830a-ef51f04b0ea9.webp',305,0,0,1,'2026-09-27 23:48:04','2026-09-27 23:48:04','2026-09-28 01:44:41'),(11,2,NULL,NULL,1,'sport','sport','<p>xxxxxxxxxxxxxxxxxxxxxxxx</p>','audio','media-contents/streams/25d32962-51d2-4072-a7e0-add87e91d5d0.opus','ready',NULL,NULL,'media-contents/thumbnails/768f5299-1d08-45a7-9e9b-cf8d39b0cd98.webp',239,0,0,1,'2026-09-28 02:07:43','2026-09-28 02:07:43','2026-09-28 02:13:50'),(12,1,4,2,1,'test21','test21','<p>ssssss</p>','audio','media-contents/streams/cacd9694-870a-479f-94ec-ebeecdbc8b00.opus','ready',NULL,NULL,'media-contents/thumbnails/36a701dd-75e9-4add-bd0c-cd3fb24d9955.webp',239,0,0,1,'2026-09-28 02:24:16','2026-09-28 02:24:16','2026-09-28 02:24:18'),(13,3,NULL,2,1,'Health test','health-test','<p>Health test</p><p>Health testHealth testHealth test</p>','audio','media-contents/streams/b5dc4b0c-fe12-4c73-8683-0abbe1be561c.opus','ready',NULL,NULL,'media-contents/thumbnails/e968ea99-0180-41cd-ac7f-fdd26f85c55c.webp',239,0,0,1,'2026-09-28 02:31:48','2026-09-28 02:31:48','2026-09-28 02:31:53');
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
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_09_18_000001_create_radio_sceaper_stations_table',1),(5,'2026_09_18_000001_create_radio_scraper_settings_table',1),(6,'2026_09_18_000001_create_radio_tables',1),(7,'2026_09_18_000001_create_roles_and_permissions_tables',1),(8,'2026_09_19_000001_create_app_users_table',1),(9,'2026_09_19_000001_create_categories_table',1),(10,'2026_09_19_000002_add_username_to_app_users_table',1),(11,'2026_09_19_000002_create_tags_table',1),(12,'2026_09_19_000003_create_programs_table',1),(13,'2026_09_19_000003_restructure_app_users_name_fields',1),(14,'2026_09_19_000004_create_media_contents_table',1),(15,'2026_09_19_000005_create_media_content_tag_table',1),(16,'2026_09_19_000006_seed_catalog_permissions',2),(17,'2026_09_19_000007_create_notifications_table',3),(18,'2026_09_19_000008_make_stream_url_nullable',4),(19,'2026_09_19_000009_create_artists_table',5),(20,'2026_09_19_000010_remove_artist_tag_branches',5),(21,'2026_09_19_000011_add_cover_to_radio_sceaper_stations_table',6),(22,'2026_09_19_000012_add_description_to_radio_sceaper_stations_table',7),(23,'2026_09_19_000013_drop_location_genre_language_from_artists_table',8),(24,'2026_09_19_000014_rename_media_content_url_columns',9),(25,'2026_09_19_000015_add_artist_and_author_to_media_contents_table',10),(26,'2026_09_19_000016_add_favorite_count_to_media_contents_table',11),(27,'2026_09_19_033834_create_personal_access_tokens_table',12),(28,'2026_09_21_000017_add_processing_fields_to_media_contents_table',13),(29,'2026_09_21_000018_create_playback_progress_table',14),(30,'2026_09_21_000018_create_storage_settings_table',15),(31,'2026_09_21_000018_create_comments_table',16),(32,'2026_09_21_000019_create_bookmarks_table',16),(33,'2026_09_23_000019_create_azuracast_settings_table',17),(34,'2026_09_28_000018_add_category_id_to_tags_table',18),(35,'2026_09_28_000019_add_tag_id_to_programs_table',19),(36,'2026_09_29_000001_split_manage_permissions_into_actions',20),(37,'2026_09_29_000002_add_edit_users_permission',21),(38,'2026_09_29_000002_add_is_active_to_users_table',21),(39,'2026_09_29_000003_add_view_customers_permission',21);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) unsigned NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES ('0033298e-1a7d-4c60-b713-f694090eeac3','Src\\Notification\\Notifications\\RadioChannelsSynced','Src\\User\\User',1,'{\"title\":\"Radio channels synced\",\"meta\":\"Added 0, updated 0 (from 9 station(s))\",\"tone\":\"green\"}','2026-09-18 19:29:17','2026-09-18 16:26:33','2026-09-18 19:29:17'),('109332e2-ee43-49da-bd91-f4d4c1162bd9','Src\\Notification\\Notifications\\MediaContentPublished','Src\\User\\User',1,'{\"title\":\"Media content published\",\"meta\":\"Audio \\u2014 Health test\",\"tone\":\"blue\"}',NULL,'2026-09-28 02:31:48','2026-09-28 02:31:48'),('14a12e99-a315-4b3d-b1cf-51f57021281f','Src\\Notification\\Notifications\\RadioChannelsSynced','Src\\User\\User',1,'{\"title\":\"Radio channels synced\",\"meta\":\"Added 0, updated 0 (from 10 station(s))\",\"tone\":\"green\"}','2026-09-20 23:33:51','2026-09-20 21:30:57','2026-09-20 23:33:51'),('17465c55-173e-499d-bb04-8f152bd92d1f','Src\\Notification\\Notifications\\MediaContentPublished','Src\\User\\User',1,'{\"title\":\"Media content published\",\"meta\":\"Audio \\u2014 episode episode\",\"tone\":\"blue\"}',NULL,'2026-09-27 23:46:17','2026-09-27 23:46:17'),('1ddbb17f-fbb6-4bb6-a687-920c3f091c5a','Src\\Notification\\Notifications\\MediaContentPublished','Src\\User\\User',1,'{\"title\":\"Media content published\",\"meta\":\"Audio \\u2014 testsong\",\"tone\":\"blue\"}','2026-09-18 19:29:17','2026-09-18 19:05:24','2026-09-18 19:29:17'),('26a75b5b-7d73-411d-992f-035e3f7b056f','Src\\Notification\\Notifications\\MediaContentPublished','Src\\User\\User',1,'{\"title\":\"Media content published\",\"meta\":\"Audio \\u2014 Duration (seconds) s\",\"tone\":\"blue\"}','2026-09-20 23:33:51','2026-09-20 23:10:41','2026-09-20 23:33:51'),('2d0f1c6d-0107-48f8-9d4a-3d34f0d2dbfb','Src\\Notification\\Notifications\\MediaContentPublished','Src\\User\\User',1,'{\"title\":\"Media content published\",\"meta\":\"Audio \\u2014 test teat\",\"tone\":\"blue\"}','2026-09-20 23:33:51','2026-09-18 19:31:26','2026-09-20 23:33:51'),('3020faea-fcb9-4da6-b548-071fb5b5214e','Src\\Notification\\Notifications\\RadioChannelsSynced','Src\\User\\User',1,'{\"title\":\"Radio channels synced\",\"meta\":\"Added 0, updated 0 (from 9 station(s))\",\"tone\":\"green\"}',NULL,'2026-09-28 02:48:20','2026-09-28 02:48:20'),('30ee7bcb-6e09-4939-a7a1-af42db345a74','Src\\Notification\\Notifications\\MediaContentPublished','Src\\User\\User',1,'{\"title\":\"Media content published\",\"meta\":\"Audio \\u2014 Duration (seconds)\",\"tone\":\"blue\"}','2026-09-20 23:33:51','2026-09-20 23:16:26','2026-09-20 23:33:51'),('3d8c3e6d-1ff8-4300-b6bf-1901f950ced7','Src\\Notification\\Notifications\\RadioChannelsSynced','Src\\User\\User',1,'{\"title\":\"Radio channels synced\",\"meta\":\"Added 0, updated 0 (from 9 station(s))\",\"tone\":\"green\"}','2026-09-18 19:29:17','2026-09-18 14:42:05','2026-09-18 19:29:17'),('3fc59dbc-ef71-469b-aae2-f0cb488fe394','Src\\Notification\\Notifications\\MediaContentPublished','Src\\User\\User',1,'{\"title\":\"Media content published\",\"meta\":\"Audio \\u2014 ffffffffffffff\",\"tone\":\"blue\"}',NULL,'2026-09-27 23:32:20','2026-09-27 23:32:20'),('417c4d3d-1d2a-49ce-afcf-aaddc7178b62','Src\\Notification\\Notifications\\RadioChannelsSynced','Src\\User\\User',1,'{\"title\":\"Radio channels synced\",\"meta\":\"Added 0, updated 0 (from 10 station(s))\",\"tone\":\"green\"}',NULL,'2026-09-21 00:39:20','2026-09-21 00:39:20'),('4a6fe46c-55f0-4de9-aab4-fc43b5fbbdc4','Src\\Notification\\Notifications\\RadioChannelsSynced','Src\\User\\User',1,'{\"title\":\"Radio channels synced\",\"meta\":\"Added 0, updated 0 (from 9 station(s))\",\"tone\":\"green\"}','2026-09-18 19:29:17','2026-09-18 17:26:16','2026-09-18 19:29:17'),('5612fb41-ad67-4639-a593-5a1d790784d9','Src\\Notification\\Notifications\\MediaContentPublished','Src\\User\\User',1,'{\"title\":\"Media content published\",\"meta\":\"Audio \\u2014 test21\",\"tone\":\"blue\"}',NULL,'2026-09-28 02:24:16','2026-09-28 02:24:16'),('57c8be2d-d243-4fff-90d2-49a28a0b0e59','Src\\Notification\\Notifications\\RadioChannelsSynced','Src\\User\\User',1,'{\"title\":\"Radio channels synced\",\"meta\":\"Added 0, updated 0 (from 9 station(s))\",\"tone\":\"green\"}','2026-09-18 19:29:17','2026-09-18 14:42:36','2026-09-18 19:29:17'),('5b93c17d-43a6-409a-b58f-78548ddb8201','Src\\Notification\\Notifications\\MediaContentPublished','Src\\User\\User',1,'{\"title\":\"Media content published\",\"meta\":\"Audio \\u2014 sport\",\"tone\":\"blue\"}',NULL,'2026-09-28 02:07:43','2026-09-28 02:07:43'),('61656be1-e179-46a4-8f53-838d229c69ad','Src\\Notification\\Notifications\\RadioChannelsSynced','Src\\User\\User',1,'{\"title\":\"Radio channels synced\",\"meta\":\"Added 0, updated 0 (from 10 station(s))\",\"tone\":\"green\"}','2026-09-20 23:33:51','2026-09-20 21:31:56','2026-09-20 23:33:51'),('732864ac-70d0-4535-85a0-b1eefeaae905','Src\\Notification\\Notifications\\RadioChannelsSynced','Src\\User\\User',1,'{\"title\":\"Radio channels synced\",\"meta\":\"Added 0, updated 0 (from 9 station(s))\",\"tone\":\"green\"}','2026-09-18 19:29:17','2026-09-18 16:28:40','2026-09-18 19:29:17'),('7d6274c2-7039-4015-941e-ce91ea0c026c','Src\\Notification\\Notifications\\LiveSourceToggled','Src\\User\\User',1,'{\"title\":\"Live source connected\",\"meta\":\"Studio A is now on air\",\"tone\":\"green\"}',NULL,'2026-09-22 21:07:38','2026-09-22 21:07:38'),('8159c5c1-2e9d-4243-9af5-a4fd0e431a26','Src\\Notification\\Notifications\\RadioChannelsSynced','Src\\User\\User',1,'{\"title\":\"Radio channels synced\",\"meta\":\"Added 0, updated 0 (from 10 station(s))\",\"tone\":\"green\"}','2026-09-20 23:33:51','2026-09-20 21:31:00','2026-09-20 23:33:51'),('859996b9-f5ac-41cf-92dd-bce3fcb26f43','Src\\Notification\\Notifications\\RadioChannelsSynced','Src\\User\\User',1,'{\"title\":\"Radio channels synced\",\"meta\":\"Added 0, updated 0 (from 10 station(s))\",\"tone\":\"green\"}','2026-09-20 23:33:51','2026-09-20 21:30:56','2026-09-20 23:33:51'),('88faa5ba-baec-42ad-ba7c-c24ddc3710cb','Src\\Notification\\Notifications\\LiveSourceToggled','Src\\User\\User',1,'{\"title\":\"Live source stopped\",\"meta\":\"AutoDJ fallback is now on air\",\"tone\":\"amber\"}',NULL,'2026-09-22 20:46:32','2026-09-22 20:46:32'),('a0bdf033-369f-4f93-b803-6621c525a14d','Src\\Notification\\Notifications\\RadioChannelsSynced','Src\\User\\User',1,'{\"title\":\"Radio channels synced\",\"meta\":\"Added 0, updated 0 (from 9 station(s))\",\"tone\":\"green\"}','2026-09-20 23:33:51','2026-09-18 20:58:33','2026-09-20 23:33:51'),('a61e6889-5be4-4891-ba32-3adfc10a5097','Src\\Notification\\Notifications\\MediaContentPublished','Src\\User\\User',1,'{\"title\":\"Media content published\",\"meta\":\"Audio \\u2014 episode1  episode episode episode episode\",\"tone\":\"blue\"}',NULL,'2026-09-27 23:48:04','2026-09-27 23:48:04'),('af49876c-9546-4a3f-9bf9-c2daec178358','Src\\Notification\\Notifications\\RadioChannelsSynced','Src\\User\\User',1,'{\"title\":\"Radio channels synced\",\"meta\":\"Added 0, updated 0 (from 10 station(s))\",\"tone\":\"green\"}','2026-09-20 23:33:51','2026-09-20 21:31:55','2026-09-20 23:33:51'),('b62c5786-c9c8-415f-a03e-a686e751b792','Src\\Notification\\Notifications\\RadioChannelsSynced','Src\\User\\User',1,'{\"title\":\"Radio channels synced\",\"meta\":\"Added 0, updated 0 (from 9 station(s))\",\"tone\":\"green\"}','2026-09-18 19:29:17','2026-09-18 16:14:06','2026-09-18 19:29:17'),('d5f95532-f574-4222-ad77-a6d120711de4','Src\\Notification\\Notifications\\RadioChannelsSynced','Src\\User\\User',1,'{\"title\":\"Radio channels synced\",\"meta\":\"Added 0, updated 0 (from 9 station(s))\",\"tone\":\"green\"}','2026-09-18 19:29:17','2026-09-18 16:26:14','2026-09-18 19:29:17'),('e60851fc-3456-4f9b-aee3-ed7198fa3140','Src\\Notification\\Notifications\\MediaContentPublished','Src\\User\\User',1,'{\"title\":\"Media content published\",\"meta\":\"Audio \\u2014 test 333\",\"tone\":\"blue\"}','2026-09-20 23:33:51','2026-09-20 20:59:16','2026-09-20 23:33:51'),('e786778a-44c6-48a3-9720-5269b87c4778','Src\\Notification\\Notifications\\RadioChannelsSynced','Src\\User\\User',1,'{\"title\":\"Radio channels synced\",\"meta\":\"Added 0, updated 0 (from 9 station(s))\",\"tone\":\"green\"}','2026-09-18 19:29:17','2026-09-18 17:17:23','2026-09-18 19:29:17'),('ebe15008-4750-4c97-989c-c7125df37a82','Src\\Notification\\Notifications\\RadioChannelsSynced','Src\\User\\User',1,'{\"title\":\"Radio channels synced\",\"meta\":\"Added 1, updated 0 (from 10 station(s))\",\"tone\":\"green\"}','2026-09-20 23:33:51','2026-09-20 21:30:52','2026-09-20 23:33:51'),('f159beff-f893-454d-b44b-9e1c6c47c145','Src\\Notification\\Notifications\\RadioChannelsSynced','Src\\User\\User',1,'{\"title\":\"Radio channels synced\",\"meta\":\"Added 0, updated 0 (from 9 station(s))\",\"tone\":\"green\"}','2026-09-18 19:29:17','2026-09-18 16:12:20','2026-09-18 19:29:17'),('fe47e84d-caef-4dec-a125-4177ed0c744c','Src\\Notification\\Notifications\\RadioChannelsSynced','Src\\User\\User',1,'{\"title\":\"Radio channels synced\",\"meta\":\"Added 0, updated 0 (from 9 station(s))\",\"tone\":\"green\"}','2026-09-18 19:29:17','2026-09-18 16:06:22','2026-09-18 19:29:17');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
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
INSERT INTO `permission_role` VALUES (5,1),(6,1),(12,1),(13,1),(14,1),(15,1),(16,1),(17,1),(18,1),(19,1),(20,1),(21,1),(22,1),(23,1),(24,1),(25,1),(26,1),(27,1),(28,1),(29,1),(30,1),(31,1),(32,1),(33,1),(34,1),(35,1),(36,1),(37,1),(38,1),(39,1),(40,1),(41,1),(42,1),(43,1),(44,1),(45,1),(46,1),(47,1);
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
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (5,'Manage Settings','manage-settings','2026-09-18 11:28:33','2026-09-18 11:28:33'),(6,'View Dashboard','view-dashboard','2026-09-18 12:30:31','2026-09-18 12:30:31'),(12,'View Radio Channels','view-radio-channels','2026-09-28 23:11:40','2026-09-28 23:11:40'),(13,'Create Radio Channels','create-radio-channels','2026-09-28 23:11:40','2026-09-28 23:11:40'),(14,'Edit Radio Channels','edit-radio-channels','2026-09-28 23:11:40','2026-09-28 23:11:40'),(15,'Delete Radio Channels','delete-radio-channels','2026-09-28 23:11:40','2026-09-28 23:11:40'),(16,'View Categories','view-categories','2026-09-28 23:11:40','2026-09-28 23:11:40'),(17,'Create Categories','create-categories','2026-09-28 23:11:40','2026-09-28 23:11:40'),(18,'Edit Categories','edit-categories','2026-09-28 23:11:40','2026-09-28 23:11:40'),(19,'Delete Categories','delete-categories','2026-09-28 23:11:40','2026-09-28 23:11:40'),(20,'View Tags','view-tags','2026-09-28 23:11:40','2026-09-28 23:11:40'),(21,'Create Tags','create-tags','2026-09-28 23:11:40','2026-09-28 23:11:40'),(22,'Edit Tags','edit-tags','2026-09-28 23:11:40','2026-09-28 23:11:40'),(23,'Delete Tags','delete-tags','2026-09-28 23:11:40','2026-09-28 23:11:40'),(24,'View Artists','view-artists','2026-09-28 23:11:40','2026-09-28 23:11:40'),(25,'Create Artists','create-artists','2026-09-28 23:11:40','2026-09-28 23:11:40'),(26,'Edit Artists','edit-artists','2026-09-28 23:11:40','2026-09-28 23:11:40'),(27,'Delete Artists','delete-artists','2026-09-28 23:11:40','2026-09-28 23:11:40'),(28,'View Programs','view-programs','2026-09-28 23:11:40','2026-09-28 23:11:40'),(29,'Create Programs','create-programs','2026-09-28 23:11:40','2026-09-28 23:11:40'),(30,'Edit Programs','edit-programs','2026-09-28 23:11:40','2026-09-28 23:11:40'),(31,'Delete Programs','delete-programs','2026-09-28 23:11:40','2026-09-28 23:11:40'),(32,'View Media Content','view-media-content','2026-09-28 23:11:40','2026-09-28 23:11:40'),(33,'Create Media Content','create-media-content','2026-09-28 23:11:40','2026-09-28 23:11:40'),(34,'Edit Media Content','edit-media-content','2026-09-28 23:11:40','2026-09-28 23:11:40'),(35,'Delete Media Content','delete-media-content','2026-09-28 23:11:40','2026-09-28 23:11:40'),(36,'View Roles','view-roles','2026-09-28 23:11:40','2026-09-28 23:11:40'),(37,'Create Roles','create-roles','2026-09-28 23:11:40','2026-09-28 23:11:40'),(38,'Edit Roles','edit-roles','2026-09-28 23:11:40','2026-09-28 23:11:40'),(39,'Delete Roles','delete-roles','2026-09-28 23:11:40','2026-09-28 23:11:40'),(40,'View Permissions','view-permissions','2026-09-28 23:11:40','2026-09-28 23:11:40'),(41,'Create Permissions','create-permissions','2026-09-28 23:11:40','2026-09-28 23:11:40'),(42,'Delete Permissions','delete-permissions','2026-09-28 23:11:40','2026-09-28 23:11:40'),(43,'View Users','view-users','2026-09-28 23:11:40','2026-09-28 23:11:40'),(44,'Create Users','create-users','2026-09-28 23:11:40','2026-09-28 23:11:40'),(45,'Delete Users','delete-users','2026-09-28 23:11:40','2026-09-28 23:11:40'),(46,'Edit Users','edit-users','2026-09-30 19:52:09','2026-09-30 19:52:09'),(47,'View Customers','view-customers','2026-09-30 19:52:09','2026-09-30 19:52:09');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
INSERT INTO `personal_access_tokens` VALUES (1,'Src\\CmsAdmin\\Models\\User',3,'e-radio-app','de31c5f347fc9e933a1f6929e1847489b3868a628c88b574186e40a1eaf31949','[\"*\"]',NULL,NULL,'2026-09-18 20:40:38','2026-09-18 20:40:38'),(2,'Src\\CmsAdmin\\Models\\User',3,'e-radio-app','61d754f7f0877d7eab23e2da00a72b06279d2e4719556822f82eab985d31a3d1','[\"*\"]',NULL,NULL,'2026-09-18 20:40:39','2026-09-18 20:40:39'),(4,'Src\\CmsAdmin\\Models\\User',4,'e-radio-app','1ec16ea90a7ac297ca9eff0bdeb5bc3aca8f31e3857841790ff38ef33f0e1aa9','[\"*\"]','2026-09-23 00:50:16',NULL,'2026-09-18 21:55:45','2026-09-23 00:50:16'),(5,'Src\\CmsAdmin\\Models\\User',5,'e-radio-app','161172135d68d6b5f425fa5cf4dd055840333f8a8b3c87632fb73ed0f2a4ed1e','[\"*\"]','2026-09-18 22:00:57',NULL,'2026-09-18 22:00:56','2026-09-18 22:00:57'),(6,'Src\\CmsAdmin\\Models\\User',6,'e-radio-app','8a7fd12afcf0ddfc8fbca222a302560b62e94b023995b28bacc89b44a50d0623','[\"*\"]','2026-09-21 01:42:18',NULL,'2026-09-20 21:07:40','2026-09-21 01:42:18'),(7,'Src\\CmsAdmin\\Models\\User',9,'e-radio-app','fbfe72470de9d7300ca84fedeb2433ce4f7c57bcc941d5174d1acb2138e3184a','[\"*\"]',NULL,NULL,'2026-09-21 00:54:31','2026-09-21 00:54:31'),(8,'Src\\CmsAdmin\\Models\\User',10,'e-radio-app','a2de7ec13a59be774f8177f7510fe752ad9ed2c94ba00ff11e0f40876acddec7','[\"*\"]','2026-09-21 00:54:45',NULL,'2026-09-21 00:54:43','2026-09-21 00:54:45'),(11,'Src\\CmsAdmin\\Models\\User',13,'e-radio-app','925b3b8e19c6cc366c01f76dc74d3ac6f67a52cd0a6c9ab8763dd62f8dc428e1','[\"*\"]','2026-09-27 20:02:27',NULL,'2026-09-27 20:02:20','2026-09-27 20:02:27'),(12,'Src\\CmsAdmin\\Models\\User',13,'e-radio-app','1842fe982b93486002eeaf08d9103bb67641d2a3c74fc05b0ec0f6bf421e2c43','[\"*\"]',NULL,NULL,'2026-09-27 20:02:26','2026-09-27 20:02:26'),(13,'Src\\CmsAdmin\\Models\\User',14,'e-radio-app','3cd2267bdfd428696a598e23f023217530aeca3547b6a8c6c58e2ca01c981ba0','[\"*\"]','2026-09-27 20:03:00',NULL,'2026-09-27 20:02:55','2026-09-27 20:03:00'),(14,'Src\\CmsAdmin\\Models\\User',14,'e-radio-app','34a860727034e15b94bc7bc5490e42049ad2417db88c80d0d90ae62173e5cfa3','[\"*\"]',NULL,NULL,'2026-09-27 20:02:59','2026-09-27 20:02:59'),(16,'Src\\CmsAdmin\\Models\\User',15,'e-radio-app','6d9e25012e03a20faf580f3a0cf6a5fe9cea64b5b987d024763a8df0f98fa314','[\"*\"]',NULL,NULL,'2026-09-27 20:04:11','2026-09-27 20:04:11'),(17,'Src\\CmsAdmin\\Models\\User',16,'e-radio-app','a2130b2103f91f3067c245d650df2bf30c56c209b0a5fc3ff6ced015f092b56a','[\"*\"]',NULL,NULL,'2026-09-28 02:34:38','2026-09-28 02:34:38');
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `playback_progress`
--

DROP TABLE IF EXISTS `playback_progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `playback_progress` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `media_content_id` bigint(20) unsigned NOT NULL,
  `position_seconds` int(10) unsigned NOT NULL,
  `duration_seconds` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `playback_progress_user_id_media_content_id_unique` (`user_id`,`media_content_id`),
  KEY `playback_progress_media_content_id_foreign` (`media_content_id`),
  CONSTRAINT `playback_progress_media_content_id_foreign` FOREIGN KEY (`media_content_id`) REFERENCES `media_contents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `playback_progress_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `playback_progress`
--

LOCK TABLES `playback_progress` WRITE;
/*!40000 ALTER TABLE `playback_progress` DISABLE KEYS */;
INSERT INTO `playback_progress` VALUES (1,4,5,175,2458,'2026-09-20 21:39:43','2026-09-20 21:43:08'),(2,4,4,127,4379,'2026-09-20 21:40:16','2026-09-20 21:42:51');
/*!40000 ALTER TABLE `playback_progress` ENABLE KEYS */;
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
  `tag_id` bigint(20) unsigned DEFAULT NULL,
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
  KEY `programs_tag_id_foreign` (`tag_id`),
  CONSTRAINT `programs_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `programs_tag_id_foreign` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `programs`
--

LOCK TABLES `programs` WRITE;
/*!40000 ALTER TABLE `programs` DISABLE KEYS */;
INSERT INTO `programs` VALUES (2,3,2,'Health','Health','Songssssssssssssssssssssssss','programs/covers/14019c5b-4ac1-4e91-aa4a-86f7c0653b8e.webp',1,'2026-09-21 00:15:53','2026-09-27 23:40:27'),(3,1,5,'Podcast','Podcast','aaaaaaaaaaaaaaaaaaaaaaaaaa','programs/covers/0e9cd240-67a9-4b87-8f06-c535ace5b1fe.webp',1,'2026-09-27 22:56:43','2026-09-28 02:19:03'),(4,1,2,'Test Health','Duration-seconds','wwwwwwwwwwwwwwwwwwwwwwwwwwwwwww','programs/covers/bed0ff44-251b-441f-ab49-d73b62f9704f.webp',1,'2026-09-27 23:06:49','2026-09-28 02:24:41');
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
  `description` text DEFAULT NULL,
  `stream_url` varchar(255) DEFAULT NULL,
  `cover` varchar(255) DEFAULT NULL,
  `bitrate` smallint(5) unsigned DEFAULT NULL,
  `votes` int(10) unsigned NOT NULL DEFAULT 0,
  `tags` varchar(255) DEFAULT NULL,
  `radio_browser_uuid` varchar(255) DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `radio_sceaper_stations_radio_browser_uuid_unique` (`radio_browser_uuid`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `radio_sceaper_stations`
--

LOCK TABLES `radio_sceaper_stations` WRITE;
/*!40000 ALTER TABLE `radio_sceaper_stations` DISABLE KEYS */;
INSERT INTO `radio_sceaper_stations` VALUES (1,'National Radio of Kampuchea',NULL,'http://119.82.252.6:8080/broadwave.mp3','radio-channels/covers/27e4f476-b004-466f-8bc8-85dc54329928.webp',0,4968,NULL,'eedb7498-f59d-4f07-9efc-b77853d6abf5',1,'2026-09-18 11:45:32','2026-09-28 18:56:12'),(2,'Abdulbasit Abdulsamad',NULL,'https://radio.mp3islam.com/listen/abdulbasit/radio.mp3',NULL,64,269,'','10c7239a-3404-4a54-82b8-7b4cc9603779',0,'2026-09-18 11:45:32','2026-09-28 02:48:20'),(3,'Pop Radio 21',NULL,'https://listen.radioking.com/radio/318317/stream/365846',NULL,128,58,'asian,folk,khmer,phnom penh,pop','5bfc4b1a-def1-44ab-8d6d-e7c63820f781',0,'2026-09-18 11:45:32','2026-09-28 02:50:05'),(4,'Hang Meas FM 104.5',NULL,'http://malisresidences.com:89/broadwave.mp3','radio-channels/covers/b50fb4f0-356b-4d64-881b-3f57f0a8970c.webp',0,47,'entertainment,khmer,phnom penh,pop','405a8b9c-4e9f-442c-b9c8-f65813905da3',1,'2026-09-18 11:45:32','2026-09-28 18:55:33'),(5,'Family FM 99.5 (Witthyu Krousar)',NULL,'https://s14.myradiostream.com/10064/;.mp3',NULL,64,32,'family,khmer,music,phnom penh','cc20bea1-aeea-4801-8f62-6aaab144f150',0,'2026-09-18 11:45:32','2026-09-28 02:50:04'),(6,'LDP Radio',NULL,'https://streaming.radio.co/sd48fd711d/listen',NULL,192,19,'khmer,phnom penh,politics,talk','053872d7-f972-4db7-b19c-993e6553141d',0,'2026-09-18 11:45:32','2026-09-28 02:50:02'),(7,'RNK FM 96 (Radio National Kampuchea)',NULL,'http://119.82.252.6:8181/broadwave.mp3',NULL,0,20,'khmer,national,news,phnom penh,public','1bf295fa-b2c7-4807-a438-c21105f0cb7a',0,'2026-09-18 11:45:32','2026-09-28 02:50:01'),(8,'Sweet FM 88 (Siem Reap)',NULL,'https://n12.rcs.revma.com/whn9hqwstk3vv',NULL,0,10,'khmer,music,pop,siem reap','e39cc117-47c6-4eae-9d4b-d70c3087831f',0,'2026-09-18 11:45:32','2026-09-28 02:51:46'),(9,'Sangkem Radio',NULL,'https://live.sangkemtv.com/radio.mp3',NULL,0,5,'','65b508f2-e022-4831-9dd5-b98d5a348452',0,'2026-09-18 11:45:32','2026-09-28 02:49:58'),(10,'Phnom Penh Radio FM 103',NULL,'http://radio99.servradio.com:9294/',NULL,96,54,'khmer,news,phnom penh,talk,variety','a8a82fd2-8c28-43e2-bbe2-ed3120862175',0,'2026-09-20 21:30:52','2026-09-28 02:49:55'),(11,'FM 97',NULL,'http://172.30.13.25/hls/ams_fm97radio/live.m3u8','radio-channels/covers/a1dde34b-d2bd-496a-9766-b753bb9b276c.webp',NULL,0,NULL,NULL,0,'2026-09-22 21:32:48','2026-09-28 02:49:34');
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
INSERT INTO `radio_scraper_settings` VALUES (1,1,'KH','2026-09-18 13:20:04','2026-09-21 00:31:47');
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
INSERT INTO `sessions` VALUES ('CktqskCgrKaKoQN2zD4afjkYyngbsc5ja4nHWusF',NULL,'172.30.30.206','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiZlNsNHI5TVhXTkx1R2dvM042RHBiSGR6S1R5UFNVRUpXNHlpeFZYayI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHA6Ly8xNzIuMzAuMzAuMjA2OjgwMDAvbG9naW4iO3M6NToicm91dGUiO3M6NToibG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjM6InVybCI7YToxOntzOjg6ImludGVuZGVkIjtzOjMxOiJodHRwOi8vMTcyLjMwLjMwLjIwNjo4MDAwL2FkbWluIjt9fQ==',1790646290),('D6zn3qYE4jL81M0JZFGyzoSJ934kPC6Cnfp5ElLQ',1,'172.30.30.206','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiTVl3R0dBbzg5S2Nmb1dLOGFZNnppbHU2Z1ViUHl2MlcyN2NzaFF3NCI7czo1MjoibG9naW5fYWRtaW5fNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxO3M6OToiX3ByZXZpb3VzIjthOjI6e3M6MzoidXJsIjtzOjQ3OiJodHRwOi8vMTcyLjMwLjMwLjIwNjo4MDAwL2FkbWluL3Byb2dyYW1zLzQvZWRpdCI7czo1OiJyb3V0ZSI7czoxOToiYWRtaW4ucHJvZ3JhbXMuZWRpdCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1790585950),('HD9R9bvD8VllIgu7FPGVMFAN0jpuSLFOhdQna3NP',1,'172.30.30.206','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiODM1M1JDWm9LaDE3enhLWk9xUVhmdFlkMUFJVVJrcURsdHA4NTRsZCI7czo1MjoibG9naW5fYWRtaW5fNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxO3M6OToiX3ByZXZpb3VzIjthOjI6e3M6MzoidXJsIjtzOjM3OiJodHRwOi8vMTcyLjMwLjMwLjIwNjo4MDAwL2FkbWluL3JvbGVzIjtzOjU6InJvdXRlIjtzOjE3OiJhZG1pbi5yb2xlcy5pbmRleCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1790647477),('KXUDo9YERiyRClUmZdpDHo2UUgS1gUNo6IsmqqSz',NULL,'172.30.30.206','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiU3pIVENRb0E4YjVXY1p4czBwMG5QZExxU2dtVE1meHFOM3I1WGdVUiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHA6Ly8xNzIuMzAuMzAuMjA2OjgwMDAvbG9naW4iO3M6NToicm91dGUiO3M6NToibG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1790823259),('tehAzh4Hyc4CeZExH8tmtnVKpwj8YAmvcJmJ3YVV',1,'172.30.30.206','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiM1BrRnVWbG1Cd3J3eE5OV1lSWllXNzIzRDFpektnYzBNSlZIZm1HOSI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MjoibG9naW5fYWRtaW5fNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxO3M6OToiX3ByZXZpb3VzIjthOjI6e3M6MzoidXJsIjtzOjU4OiJodHRwOi8vMTcyLjMwLjMwLjIwNjo4MDAwL2FkbWluL3Byb2dyYW1zP3R5cGU9Y2l2aWxpemF0aW9uIjtzOjU6InJvdXRlIjtzOjIwOiJhZG1pbi5wcm9ncmFtcy5pbmRleCI7fX0=',1790589538);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `storage_settings`
--

DROP TABLE IF EXISTS `storage_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `storage_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `image_directory` varchar(255) NOT NULL DEFAULT 'media-contents/thumbnails',
  `audio_directory` varchar(255) NOT NULL DEFAULT 'media-contents/streams',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `storage_settings`
--

LOCK TABLES `storage_settings` WRITE;
/*!40000 ALTER TABLE `storage_settings` DISABLE KEYS */;
INSERT INTO `storage_settings` VALUES (1,'media-contents/thumbnails','media-contents/streams','2026-09-21 00:27:41','2026-09-21 00:27:41');
/*!40000 ALTER TABLE `storage_settings` ENABLE KEYS */;
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
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tags_parent_id_slug_unique` (`parent_id`,`slug`),
  KEY `tags_category_id_foreign` (`category_id`),
  CONSTRAINT `tags_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tags_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tags`
--

LOCK TABLES `tags` WRITE;
/*!40000 ALTER TABLE `tags` DISABLE KEYS */;
INSERT INTO `tags` VALUES (1,NULL,1,'Podcast','podcast','2026-09-18 13:20:05','2026-09-18 13:20:05'),(2,1,1,'Health','health','2026-09-18 13:20:05','2026-09-18 13:20:05'),(3,1,1,'Economy/Finance','economyfinance','2026-09-18 13:20:05','2026-09-18 13:20:05'),(4,1,1,'Education','education','2026-09-18 13:20:05','2026-09-18 13:20:05'),(5,1,1,'Politic','politic','2026-09-18 13:20:05','2026-09-18 13:20:05'),(6,1,1,'Civilization','civilization','2026-09-18 13:20:05','2026-09-18 13:20:05'),(7,NULL,3,'Health','health','2026-09-18 13:20:05','2026-09-18 13:20:05'),(8,7,3,'Mental Health','mental-health','2026-09-18 13:20:05','2026-09-18 13:20:05'),(9,7,3,'Physical Health','physical-health','2026-09-18 13:20:05','2026-09-18 13:20:05'),(10,NULL,4,'Economy/Finance','economyfinance','2026-09-18 13:20:05','2026-09-18 13:20:05'),(11,10,4,'International','international','2026-09-18 13:20:05','2026-09-18 13:20:05'),(12,10,4,'Local','local','2026-09-18 13:20:05','2026-09-18 13:20:05'),(13,NULL,2,'News','news','2026-09-18 13:20:05','2026-09-18 13:20:05'),(14,13,2,'Politic','politic','2026-09-18 13:20:05','2026-09-18 13:20:05'),(15,13,2,'Economy/Finance','economyfinance','2026-09-18 13:20:05','2026-09-18 13:20:05'),(16,13,2,'Education','education','2026-09-18 13:20:05','2026-09-18 13:20:05'),(17,13,2,'Sports','sports','2026-09-18 13:20:05','2026-09-18 13:20:05'),(18,13,2,'Civilization','civilization','2026-09-18 13:20:05','2026-09-18 13:20:05'),(19,NULL,5,'Song','song','2026-09-18 13:20:05','2026-09-18 13:20:05'),(20,19,5,'International','international','2026-09-18 13:20:05','2026-09-18 13:20:05'),(21,20,5,'Genre','genre','2026-09-18 13:20:05','2026-09-18 13:20:05'),(22,21,5,'Hiphop/Rap','hiphoprap','2026-09-18 13:20:05','2026-09-18 13:20:05'),(23,21,5,'Rock','rock','2026-09-18 13:20:05','2026-09-18 13:20:05'),(24,21,5,'EDM','edm','2026-09-18 13:20:05','2026-09-18 13:20:05'),(25,21,5,'Country','country','2026-09-18 13:20:05','2026-09-18 13:20:05'),(26,21,5,'Pop','pop','2026-09-18 13:20:05','2026-09-18 13:20:05'),(27,21,5,'Folk','folk','2026-09-18 13:20:05','2026-09-18 13:20:05'),(29,19,5,'Local','local','2026-09-18 13:20:05','2026-09-18 13:20:05'),(30,29,5,'Genre','genre','2026-09-18 13:20:05','2026-09-18 13:20:05'),(31,30,5,'Hiphop/Rap','hiphoprap','2026-09-18 13:20:05','2026-09-18 13:20:05'),(32,30,5,'Rock','rock','2026-09-18 13:20:05','2026-09-18 13:20:05'),(33,30,5,'EDM','edm','2026-09-18 13:20:05','2026-09-18 13:20:05'),(34,30,5,'Country','country','2026-09-18 13:20:05','2026-09-18 13:20:05'),(35,30,5,'Pop','pop','2026-09-18 13:20:05','2026-09-18 13:20:05'),(36,30,5,'Folk','folk','2026-09-18 13:20:05','2026-09-18 13:20:05'),(38,NULL,6,'Ambient Music','ambient-music','2026-09-18 13:20:05','2026-09-18 13:20:05'),(39,38,6,'Reading','reading','2026-09-18 13:20:05','2026-09-18 13:20:05'),(40,38,6,'Studying','studying','2026-09-18 13:20:05','2026-09-18 13:20:05'),(41,38,6,'Yoga','yoga','2026-09-18 13:20:05','2026-09-18 13:20:05'),(42,38,6,'Working','working','2026-09-18 13:20:05','2026-09-18 13:20:05'),(43,38,6,'Lobby/Lounge','lobbylounge','2026-09-18 13:20:05','2026-09-18 13:20:05'),(44,38,6,'Khmer Traditional Music','khmer-traditional-music','2026-09-18 13:20:05','2026-09-18 13:20:05');
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
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `username` varchar(255) DEFAULT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
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
  UNIQUE KEY `users_google_id_unique` (`google_id`),
  UNIQUE KEY `users_username_unique` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin',1,NULL,NULL,NULL,'Admin','admin@eradio.com',NULL,NULL,NULL,'$2y$12$R.lMcYc1GhRmhPQQwPryneKcWgN8smOs5sULYAtGF3nmkRsIHSqMi',NULL,NULL,'QIg6Aq6EsEJbrVa8iQPAtOmXLQLkby1pmdIasO1qXXcWHEdvixykGz1eHE7c','2026-09-18 11:28:33','2026-09-18 12:44:37'),(4,'customer',1,NULL,'jack','son',NULL,'chhinpov2025@gmail.com',NULL,NULL,NULL,'$2y$12$VXiGaVYAUwkCKIPalREHgOZsueeuBS2WNqd45lZbLZzOqPqVTqL7q',NULL,NULL,NULL,'2026-09-18 21:55:45','2026-09-18 21:55:45'),(6,'customer',1,NULL,'AMS','ERadio',NULL,'ams@gmai.com',NULL,NULL,NULL,'$2y$12$HLEpqdUjoRnMbsSvfSzCHec0m0qOWh9e28SoNu5M/UD4aP3iw/4nu',NULL,NULL,NULL,'2026-09-20 21:07:40','2026-09-20 21:07:40'),(7,'customer',1,NULL,'Smoke','Test',NULL,'smoketest_1789977208@example.com',NULL,NULL,NULL,'$2y$12$Y.SH9zhIyU5fLrpDddS2g.3kTzhhhpd8U7ZSpeyTj9K3fHAf2skq6',NULL,NULL,NULL,'2026-09-21 00:53:29','2026-09-21 00:53:29'),(8,'customer',1,NULL,'Smoke','Test',NULL,'smoketest2@example.com',NULL,NULL,NULL,'$2y$12$IFujStVaOwjh6qcAcKzp0OCCVRnIRhMJG.7j1w2m5Q904nzRHXvum',NULL,NULL,NULL,'2026-09-21 00:54:08','2026-09-21 00:54:08'),(9,'customer',1,NULL,'Smoke','Test',NULL,'smoketest_1789977270@example.com',NULL,NULL,NULL,'$2y$12$S0UJMJXWr/jZvtNdgVtBTOE2hW7ms5rYhGqqIZrv/5UxX9PzJF3lq',NULL,NULL,NULL,'2026-09-21 00:54:31','2026-09-21 00:54:31'),(10,'customer',1,NULL,'Smoke','Two',NULL,'smoketest_1789977282_2@example.com',NULL,NULL,NULL,'$2y$12$.f5v4w9wY/7TSxn2W/1BT.e2/RairEhGMI7N2UOXIApwRLE.mtKmK',NULL,NULL,NULL,'2026-09-21 00:54:43','2026-09-21 00:54:43'),(11,'customer',1,NULL,'pov','chhin',NULL,'povchhin@gmail.com',NULL,NULL,NULL,'$2y$12$yncULEoX2xgQEzB.G7u2O.xRfaJwghw7eUYd8EkRwpK65SoEIw/Dm',NULL,NULL,NULL,'2026-09-27 19:12:47','2026-09-27 19:12:47'),(12,'customer',1,NULL,'Jack','Son',NULL,'jack@gamail.com',NULL,NULL,NULL,'$2y$12$wDeoljVYoraTZWd6u4n9qOZyYhg9nQ5iMqt4k2DRjJ2rnSDN66yxu',NULL,NULL,NULL,'2026-09-27 19:30:36','2026-09-27 19:30:36'),(16,'customer',1,NULL,'pov','chhin',NULL,'pov@gmail.com',NULL,NULL,NULL,'$2y$12$0txLM4OkuLdLCLkefKDhTuZiaRfvXTq4lYx9xEXv.qHQf12ijtXZC',NULL,NULL,NULL,'2026-09-28 02:34:38','2026-09-28 02:34:38');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'e_radio'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-01  9:57:59
