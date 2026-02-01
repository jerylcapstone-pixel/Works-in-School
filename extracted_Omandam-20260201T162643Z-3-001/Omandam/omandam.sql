-- MySQL dump 10.13  Distrib 8.0.43, for Win64 (x86_64)
--
-- Host: 172.27.0.1    Database: techpro
-- ------------------------------------------------------
-- Server version	8.0.43

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `bookings`
--

LOCK TABLES `bookings` WRITE;
/*!40000 ALTER TABLE `bookings` DISABLE KEYS */;
INSERT INTO `bookings` VALUES (1,2,'Hardware Repair','2025-09-10','12:45:00','','Confirmed','2025-09-02 14:43:09'),(2,2,'Data Recovery','2025-09-12','15:00:00','issue data corrupted','Pending','2025-09-03 13:41:56'),(3,2,'Data Recovery','2025-09-18','13:03:00','','Cancelled','2025-09-03 14:01:55'),(4,2,'Data Recovery','2025-10-02','21:14:00','','Cancelled','2025-09-03 14:14:42'),(5,2,'Data Recovery','2025-09-19','22:22:00','','Cancelled','2025-09-03 14:22:32'),(6,2,'Virus Removal','2025-09-04','22:30:00','','Cancelled','2025-09-03 14:30:56'),(7,2,'Data Recovery','2025-09-11','20:40:00','','Cancelled','2025-09-03 15:03:03');
/*!40000 ALTER TABLE `bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2y$10$htjZDJ3QvS8I6eA7jK8Kuea0rS4E7e0j7yZfY8kXzKzY9V5fY0a3e','admin@example.com',NULL,NULL,NULL,'admin','2025-09-02 07:56:56'),(2,'krajer','$2y$10$iGiNrPWDbzsSxXXfTmN.xOvSMW2D3DdGilY7rbT0xtkGEf7j02boe','markjerylomandam@gmail.com','966574808','Purok 2 Carangan Ozamiz City','uploads/2.jpg','user','2025-09-02 08:03:37'),(3,'jeryl','$2y$10$VRSWR.PBoTARfectJCkeFuBslp0GfBtFIvrzgipvyaon5ghgXAVli','jerylomandam@gmail.com','966574808','Purok 2 Carangan Ozamiz City','uploads/3.jpg','admin','2025-09-02 08:17:03');
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

-- Dump completed on 2025-09-04  0:06:11
