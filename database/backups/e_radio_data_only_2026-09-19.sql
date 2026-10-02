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
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `icon`, `sort_order`, `created_at`, `updated_at`) VALUES (1,'Podcast','podcast',NULL,NULL,0,'2026-09-18 11:41:25','2026-09-18 11:41:25'),(2,'News','news',NULL,NULL,1,'2026-09-18 11:41:25','2026-09-18 11:41:25'),(3,'Health','health',NULL,NULL,2,'2026-09-18 11:41:25','2026-09-18 11:41:25'),(4,'Economy/Finance','economyfinance',NULL,NULL,3,'2026-09-18 11:41:25','2026-09-18 11:41:25'),(5,'Song','song',NULL,NULL,4,'2026-09-18 11:41:25','2026-09-18 11:41:25'),(6,'Ambient Music','ambient-music',NULL,NULL,5,'2026-09-18 11:41:25','2026-09-18 11:41:25');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `media_content_tag`
--

LOCK TABLES `media_content_tag` WRITE;
/*!40000 ALTER TABLE `media_content_tag` DISABLE KEYS */;
/*!40000 ALTER TABLE `media_content_tag` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `media_contents`
--

LOCK TABLES `media_contents` WRITE;
/*!40000 ALTER TABLE `media_contents` DISABLE KEYS */;
/*!40000 ALTER TABLE `media_contents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `permission_role`
--

LOCK TABLES `permission_role` WRITE;
/*!40000 ALTER TABLE `permission_role` DISABLE KEYS */;
INSERT INTO `permission_role` (`permission_id`, `role_id`) VALUES (1,1),(2,1),(3,1),(4,1),(5,1),(6,1);
/*!40000 ALTER TABLE `permission_role` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` (`id`, `name`, `slug`, `created_at`, `updated_at`) VALUES (1,'Manage Radio Channels','manage-radio-channels','2026-09-18 11:28:33','2026-09-18 11:28:33'),(2,'Manage Users','manage-users','2026-09-18 11:28:33','2026-09-18 11:28:33'),(3,'Manage Roles','manage-roles','2026-09-18 11:28:33','2026-09-18 11:28:33'),(4,'Manage Permissions','manage-permissions','2026-09-18 11:28:33','2026-09-18 11:28:33'),(5,'Manage Settings','manage-settings','2026-09-18 11:28:33','2026-09-18 11:28:33'),(6,'View Dashboard','view-dashboard','2026-09-18 12:30:31','2026-09-18 12:30:31');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `programs`
--

LOCK TABLES `programs` WRITE;
/*!40000 ALTER TABLE `programs` DISABLE KEYS */;
/*!40000 ALTER TABLE `programs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `radio_playlists`
--

LOCK TABLES `radio_playlists` WRITE;
/*!40000 ALTER TABLE `radio_playlists` DISABLE KEYS */;
/*!40000 ALTER TABLE `radio_playlists` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `radio_sceaper_stations`
--

LOCK TABLES `radio_sceaper_stations` WRITE;
/*!40000 ALTER TABLE `radio_sceaper_stations` DISABLE KEYS */;
INSERT INTO `radio_sceaper_stations` (`id`, `name`, `stream_url`, `bitrate`, `votes`, `tags`, `radio_browser_uuid`, `is_published`, `created_at`, `updated_at`) VALUES (1,'National Radio of Kampuchea','http://119.82.252.6:8080/broadwave.mp3',0,4935,'','eedb7498-f59d-4f07-9efc-b77853d6abf5',0,'2026-09-18 11:45:32','2026-09-18 11:45:32'),(2,'Abdulbasit Abdulsamad','https://radio.mp3islam.com/listen/abdulbasit/radio.mp3',64,263,'','10c7239a-3404-4a54-82b8-7b4cc9603779',0,'2026-09-18 11:45:32','2026-09-18 11:45:32'),(3,'Pop Radio 21','https://listen.radioking.com/radio/318317/stream/365846',128,52,'asian,folk,khmer,phnom penh,pop','5bfc4b1a-def1-44ab-8d6d-e7c63820f781',0,'2026-09-18 11:45:32','2026-09-18 11:45:32'),(4,'Hang Meas FM 104.5','http://malisresidences.com:89/broadwave.mp3',0,46,'entertainment,khmer,phnom penh,pop','405a8b9c-4e9f-442c-b9c8-f65813905da3',1,'2026-09-18 11:45:32','2026-09-18 11:45:49'),(5,'Family FM 99.5 (Witthyu Krousar)','https://s14.myradiostream.com/10064/;.mp3',64,30,'family,khmer,music,phnom penh','cc20bea1-aeea-4801-8f62-6aaab144f150',1,'2026-09-18 11:45:32','2026-09-18 11:45:49'),(6,'LDP Radio','https://streaming.radio.co/sd48fd711d/listen',192,18,'khmer,phnom penh,politics,talk','053872d7-f972-4db7-b19c-993e6553141d',0,'2026-09-18 11:45:32','2026-09-18 11:45:32'),(7,'RNK FM 96 (Radio National Kampuchea)','http://119.82.252.6:8181/broadwave.mp3',0,18,'khmer,national,news,phnom penh,public','1bf295fa-b2c7-4807-a438-c21105f0cb7a',1,'2026-09-18 11:45:32','2026-09-18 11:45:49'),(8,'Sweet FM 88 (Siem Reap)','https://n12.rcs.revma.com/whn9hqwstk3vv',0,9,'khmer,music,pop,siem reap','e39cc117-47c6-4eae-9d4b-d70c3087831f',0,'2026-09-18 11:45:32','2026-09-18 11:45:32'),(9,'Sangkem Radio','https://live.sangkemtv.com/radio.mp3',0,4,'','65b508f2-e022-4831-9dd5-b98d5a348452',0,'2026-09-18 11:45:32','2026-09-18 11:45:32');
/*!40000 ALTER TABLE `radio_sceaper_stations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `radio_schedules`
--

LOCK TABLES `radio_schedules` WRITE;
/*!40000 ALTER TABLE `radio_schedules` DISABLE KEYS */;
/*!40000 ALTER TABLE `radio_schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `radio_scraper_settings`
--

LOCK TABLES `radio_scraper_settings` WRITE;
/*!40000 ALTER TABLE `radio_scraper_settings` DISABLE KEYS */;
INSERT INTO `radio_scraper_settings` (`id`, `enabled`, `country`, `created_at`, `updated_at`) VALUES (1,0,'KH','2026-09-18 11:22:51','2026-09-18 11:22:51');
/*!40000 ALTER TABLE `radio_scraper_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `radio_stations`
--

LOCK TABLES `radio_stations` WRITE;
/*!40000 ALTER TABLE `radio_stations` DISABLE KEYS */;
/*!40000 ALTER TABLE `radio_stations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `radio_streams`
--

LOCK TABLES `radio_streams` WRITE;
/*!40000 ALTER TABLE `radio_streams` DISABLE KEYS */;
/*!40000 ALTER TABLE `radio_streams` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `role_user`
--

LOCK TABLES `role_user` WRITE;
/*!40000 ALTER TABLE `role_user` DISABLE KEYS */;
INSERT INTO `role_user` (`role_id`, `user_id`) VALUES (1,1);
/*!40000 ALTER TABLE `role_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` (`id`, `name`, `slug`, `created_at`, `updated_at`) VALUES (1,'Admin','admin','2026-09-18 11:28:33','2026-09-18 11:28:33');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tags`
--

LOCK TABLES `tags` WRITE;
/*!40000 ALTER TABLE `tags` DISABLE KEYS */;
INSERT INTO `tags` (`id`, `parent_id`, `name`, `slug`, `created_at`, `updated_at`) VALUES (1,NULL,'Podcast','podcast','2026-09-18 11:41:25','2026-09-18 11:41:25'),(2,1,'Health','health','2026-09-18 11:41:25','2026-09-18 11:41:25'),(3,1,'Economy/Finance','economyfinance','2026-09-18 11:41:25','2026-09-18 11:41:25'),(4,1,'Education','education','2026-09-18 11:41:25','2026-09-18 11:41:25'),(5,1,'Politic','politic','2026-09-18 11:41:25','2026-09-18 11:41:25'),(6,1,'Civilization','civilization','2026-09-18 11:41:25','2026-09-18 11:41:25'),(7,NULL,'Health','health','2026-09-18 11:41:25','2026-09-18 11:41:25'),(8,7,'Mental Health','mental-health','2026-09-18 11:41:25','2026-09-18 11:41:25'),(9,7,'Physical Health','physical-health','2026-09-18 11:41:25','2026-09-18 11:41:25'),(10,NULL,'Economy/Finance','economyfinance','2026-09-18 11:41:25','2026-09-18 11:41:25'),(11,10,'International','international','2026-09-18 11:41:25','2026-09-18 11:41:25'),(12,10,'Local','local','2026-09-18 11:41:25','2026-09-18 11:41:25'),(13,NULL,'News','news','2026-09-18 11:41:25','2026-09-18 11:41:25'),(14,13,'Politic','politic','2026-09-18 11:41:25','2026-09-18 11:41:25'),(15,13,'Economy/Finance','economyfinance','2026-09-18 11:41:25','2026-09-18 11:41:25'),(16,13,'Education','education','2026-09-18 11:41:25','2026-09-18 11:41:25'),(17,13,'Sports','sports','2026-09-18 11:41:25','2026-09-18 11:41:25'),(18,13,'Civilization','civilization','2026-09-18 11:41:25','2026-09-18 11:41:25'),(19,NULL,'Song','song','2026-09-18 11:41:25','2026-09-18 11:41:25'),(20,19,'International','international','2026-09-18 11:41:25','2026-09-18 11:41:25'),(21,20,'Genre','genre','2026-09-18 11:41:25','2026-09-18 11:41:25'),(22,21,'Hiphop/Rap','hiphoprap','2026-09-18 11:41:25','2026-09-18 11:41:25'),(23,21,'Rock','rock','2026-09-18 11:41:25','2026-09-18 11:41:25'),(24,21,'EDM','edm','2026-09-18 11:41:25','2026-09-18 11:41:25'),(25,21,'Country','country','2026-09-18 11:41:25','2026-09-18 11:41:25'),(26,21,'Pop','pop','2026-09-18 11:41:25','2026-09-18 11:41:25'),(27,21,'Folk','folk','2026-09-18 11:41:25','2026-09-18 11:41:25'),(28,20,'Artist','artist','2026-09-18 11:41:25','2026-09-18 11:41:25'),(29,19,'Local','local','2026-09-18 11:41:25','2026-09-18 11:41:25'),(30,29,'Genre','genre','2026-09-18 11:41:25','2026-09-18 11:41:25'),(31,30,'Hiphop/Rap','hiphoprap','2026-09-18 11:41:25','2026-09-18 11:41:25'),(32,30,'Rock','rock','2026-09-18 11:41:25','2026-09-18 11:41:25'),(33,30,'EDM','edm','2026-09-18 11:41:25','2026-09-18 11:41:25'),(34,30,'Country','country','2026-09-18 11:41:25','2026-09-18 11:41:25'),(35,30,'Pop','pop','2026-09-18 11:41:25','2026-09-18 11:41:25'),(36,30,'Folk','folk','2026-09-18 11:41:25','2026-09-18 11:41:25'),(37,29,'Artist','artist','2026-09-18 11:41:25','2026-09-18 11:41:25'),(38,NULL,'Ambient Music','ambient-music','2026-09-18 11:41:25','2026-09-18 11:41:25'),(39,38,'Reading','reading','2026-09-18 11:41:25','2026-09-18 11:41:25'),(40,38,'Studying','studying','2026-09-18 11:41:25','2026-09-18 11:41:25'),(41,38,'Yoga','yoga','2026-09-18 11:41:25','2026-09-18 11:41:25'),(42,38,'Working','working','2026-09-18 11:41:25','2026-09-18 11:41:25'),(43,38,'Lobby/Lounge','lobbylounge','2026-09-18 11:41:25','2026-09-18 11:41:25'),(44,38,'Khmer Traditional Music','khmer-traditional-music','2026-09-18 11:41:25','2026-09-18 11:41:25');
/*!40000 ALTER TABLE `tags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` (`id`, `type`, `username`, `name`, `first_name`, `last_name`, `email`, `address`, `phone_number`, `email_verified_at`, `password`, `google_id`, `avatar_url`, `remember_token`, `created_at`, `updated_at`) VALUES (1,'admin',NULL,'Admin',NULL,NULL,'Admin',NULL,NULL,NULL,'$2y$12$R.lMcYc1GhRmhPQQwPryneKcWgN8smOs5sULYAtGF3nmkRsIHSqMi',NULL,NULL,NULL,'2026-09-18 11:28:33','2026-09-18 12:44:37');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-19  3:19:44
