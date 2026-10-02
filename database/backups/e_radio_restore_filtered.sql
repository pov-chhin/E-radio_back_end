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

LOCK TABLES `permission_role` WRITE;
/*!40000 ALTER TABLE `permission_role` DISABLE KEYS */;
INSERT INTO `permission_role` (`permission_id`, `role_id`) VALUES (1,1),(2,1),(3,1),(4,1),(5,1),(6,1);
/*!40000 ALTER TABLE `permission_role` ENABLE KEYS */;
UNLOCK TABLES;
LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` (`id`, `name`, `slug`, `created_at`, `updated_at`) VALUES (1,'Manage Radio Channels','manage-radio-channels','2026-09-18 11:28:33','2026-09-18 11:28:33'),(2,'Manage Users','manage-users','2026-09-18 11:28:33','2026-09-18 11:28:33'),(3,'Manage Roles','manage-roles','2026-09-18 11:28:33','2026-09-18 11:28:33'),(4,'Manage Permissions','manage-permissions','2026-09-18 11:28:33','2026-09-18 11:28:33'),(5,'Manage Settings','manage-settings','2026-09-18 11:28:33','2026-09-18 11:28:33'),(6,'View Dashboard','view-dashboard','2026-09-18 12:30:31','2026-09-18 12:30:31');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;
LOCK TABLES `radio_sceaper_stations` WRITE;
/*!40000 ALTER TABLE `radio_sceaper_stations` DISABLE KEYS */;
INSERT INTO `radio_sceaper_stations` (`id`, `name`, `stream_url`, `bitrate`, `votes`, `tags`, `radio_browser_uuid`, `is_published`, `created_at`, `updated_at`) VALUES (1,'National Radio of Kampuchea','http://119.82.252.6:8080/broadwave.mp3',0,4935,'','eedb7498-f59d-4f07-9efc-b77853d6abf5',0,'2026-09-18 11:45:32','2026-09-18 11:45:32'),(2,'Abdulbasit Abdulsamad','https://radio.mp3islam.com/listen/abdulbasit/radio.mp3',64,263,'','10c7239a-3404-4a54-82b8-7b4cc9603779',0,'2026-09-18 11:45:32','2026-09-18 11:45:32'),(3,'Pop Radio 21','https://listen.radioking.com/radio/318317/stream/365846',128,52,'asian,folk,khmer,phnom penh,pop','5bfc4b1a-def1-44ab-8d6d-e7c63820f781',0,'2026-09-18 11:45:32','2026-09-18 11:45:32'),(4,'Hang Meas FM 104.5','http://malisresidences.com:89/broadwave.mp3',0,46,'entertainment,khmer,phnom penh,pop','405a8b9c-4e9f-442c-b9c8-f65813905da3',1,'2026-09-18 11:45:32','2026-09-18 11:45:49'),(5,'Family FM 99.5 (Witthyu Krousar)','https://s14.myradiostream.com/10064/;.mp3',64,30,'family,khmer,music,phnom penh','cc20bea1-aeea-4801-8f62-6aaab144f150',1,'2026-09-18 11:45:32','2026-09-18 11:45:49'),(6,'LDP Radio','https://streaming.radio.co/sd48fd711d/listen',192,18,'khmer,phnom penh,politics,talk','053872d7-f972-4db7-b19c-993e6553141d',0,'2026-09-18 11:45:32','2026-09-18 11:45:32'),(7,'RNK FM 96 (Radio National Kampuchea)','http://119.82.252.6:8181/broadwave.mp3',0,18,'khmer,national,news,phnom penh,public','1bf295fa-b2c7-4807-a438-c21105f0cb7a',1,'2026-09-18 11:45:32','2026-09-18 11:45:49'),(8,'Sweet FM 88 (Siem Reap)','https://n12.rcs.revma.com/whn9hqwstk3vv',0,9,'khmer,music,pop,siem reap','e39cc117-47c6-4eae-9d4b-d70c3087831f',0,'2026-09-18 11:45:32','2026-09-18 11:45:32'),(9,'Sangkem Radio','https://live.sangkemtv.com/radio.mp3',0,4,'','65b508f2-e022-4831-9dd5-b98d5a348452',0,'2026-09-18 11:45:32','2026-09-18 11:45:32');
/*!40000 ALTER TABLE `radio_sceaper_stations` ENABLE KEYS */;
UNLOCK TABLES;
LOCK TABLES `role_user` WRITE;
/*!40000 ALTER TABLE `role_user` DISABLE KEYS */;
INSERT INTO `role_user` (`role_id`, `user_id`) VALUES (1,1);
/*!40000 ALTER TABLE `role_user` ENABLE KEYS */;
UNLOCK TABLES;
LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` (`id`, `name`, `slug`, `created_at`, `updated_at`) VALUES (1,'Admin','admin','2026-09-18 11:28:33','2026-09-18 11:28:33');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;
LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` (`id`, `type`, `username`, `name`, `first_name`, `last_name`, `email`, `address`, `phone_number`, `email_verified_at`, `password`, `google_id`, `avatar_url`, `remember_token`, `created_at`, `updated_at`) VALUES (1,'admin',NULL,'Admin',NULL,NULL,'Admin',NULL,NULL,NULL,'$2y$12$R.lMcYc1GhRmhPQQwPryneKcWgN8smOs5sULYAtGF3nmkRsIHSqMi',NULL,NULL,NULL,'2026-09-18 11:28:33','2026-09-18 12:44:37');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-19  3:19:44
