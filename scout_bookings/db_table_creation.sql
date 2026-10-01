-- Adminer 5.4.2 MySQL 8.4.11-0ubuntu0.26.04.1 dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

SET NAMES utf8mb4;

DROP DATABASE IF EXISTS `w3_qmscouts`;
CREATE DATABASE `w3_qmscouts` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `w3_qmscouts`;

DROP TABLE IF EXISTS `booking_item_components`;
CREATE TABLE `booking_item_components` (
  `booking_id` int NOT NULL,
  `item_code` varchar(50) NOT NULL,
  `component_code` varchar(10) NOT NULL,
  `source_location_code` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`booking_id`,`item_code`,`component_code`),
  UNIQUE KEY `component_code` (`component_code`),
  KEY `booking_item_components_item` (`item_code`),
  KEY `booking_item_components_location` (`source_location_code`),
  CONSTRAINT `booking_item_components_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE,
  CONSTRAINT `booking_item_components_component` FOREIGN KEY (`component_code`) REFERENCES `components` (`component_code`) ON DELETE CASCADE,
  CONSTRAINT `booking_item_components_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `booking_item_components_item` FOREIGN KEY (`item_code`) REFERENCES `items` (`item_code`) ON DELETE CASCADE,
  CONSTRAINT `booking_item_components_location` FOREIGN KEY (`source_location_code`) REFERENCES `locations` (`location_code`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `booking_items`;
CREATE TABLE `booking_items` (
  `booking_id` int NOT NULL,
  `item_code` varchar(50) NOT NULL,
  `quantity_needed` int NOT NULL,
  `quantity_returned` int DEFAULT NULL,
  `damage_notes_on_return` text,
  PRIMARY KEY (`booking_id`,`item_code`),
  KEY `item_code` (`item_code`),
  CONSTRAINT `booking_items_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`),
  CONSTRAINT `booking_items_ibfk_2` FOREIGN KEY (`item_code`) REFERENCES `items` (`item_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `booking_statuses`;
CREATE TABLE `booking_statuses` (
  `status_id` int NOT NULL AUTO_INCREMENT,
  `status_name` varchar(50) NOT NULL COMMENT 'Pending, Approved, Collected, Returned, Cancelled',
  PRIMARY KEY (`status_id`),
  UNIQUE KEY `status_name` (`status_name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `bookings`;
CREATE TABLE `bookings` (
  `booking_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `event_name` varchar(50) NOT NULL,
  `event_start` date NOT NULL,
  `event_end` date NOT NULL,
  `collection_date` date NOT NULL,
  `return_date` date NOT NULL,
  `group_name` varchar(255) DEFAULT NULL,
  `booking_status_id` int NOT NULL DEFAULT '1',
  `approved_by_user_id` int DEFAULT NULL,
  `approval_datetime` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`booking_id`),
  KEY `user_id` (`user_id`),
  KEY `booking_status_id` (`booking_status_id`),
  KEY `approved_by_user_id` (`approved_by_user_id`),
  CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`booking_status_id`) REFERENCES `booking_statuses` (`status_id`),
  CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`approved_by_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `category_code` varchar(20) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `category_description` text,
  PRIMARY KEY (`category_code`),
  UNIQUE KEY `category_name` (`category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `components`;
CREATE TABLE `components` (
  `component_code` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `item_code` varchar(50) NOT NULL,
  `quantity` int NOT NULL COMMENT 'quantity of components per item unit',
  `item_quality` varchar(255) NOT NULL,
  `quality_desc` text COMMENT 'desc of any damage',
  `return_note` text,
  `replacement_cost` decimal(10,2) DEFAULT NULL,
  `date_purchased` date DEFAULT NULL,
  `end_of_life` date DEFAULT NULL,
  `item_location_code` varchar(50) NOT NULL COMMENT 'location where the specific unit is stored',
  PRIMARY KEY (`component_code`),
  KEY `item_code` (`item_code`),
  KEY `item_location_code` (`item_location_code`),
  CONSTRAINT `components_ibfk_1` FOREIGN KEY (`item_code`) REFERENCES `items` (`item_code`),
  CONSTRAINT `components_ibfk_2` FOREIGN KEY (`item_location_code`) REFERENCES `locations` (`location_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `item_maintenance_log`;
CREATE TABLE `item_maintenance_log` (
  `log_id` int NOT NULL,
  `component_code` varchar(10) DEFAULT NULL,
  `item_code` varchar(50) NOT NULL,
  `log_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `action_type` varchar(50) NOT NULL,
  `description` text,
  `cost_in_gbp` decimal(10,2) DEFAULT NULL,
  `performed_by_user_id` int DEFAULT NULL,
  `reported_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `issue_description` text,
  `action_taken` text,
  `condition_before` varchar(50) DEFAULT 'Unknown',
  `condition_after` varchar(50) DEFAULT 'Needs maintenance',
  `cost` decimal(10,2) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `resolved_by_user_id` int DEFAULT NULL,
  KEY `maintenance_item` (`item_code`),
  KEY `maintenance_user` (`performed_by_user_id`),
  KEY `maintenance_component` (`component_code`),
  KEY `maintenance_resolver` (`resolved_by_user_id`),
  CONSTRAINT `maintenance_component` FOREIGN KEY (`component_code`) REFERENCES `components` (`component_code`) ON DELETE CASCADE,
  CONSTRAINT `maintenance_item` FOREIGN KEY (`item_code`) REFERENCES `items` (`item_code`) ON DELETE CASCADE,
  CONSTRAINT `maintenance_resolver` FOREIGN KEY (`resolved_by_user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `maintenance_user` FOREIGN KEY (`performed_by_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `items`;
CREATE TABLE `items` (
  `item_code` varchar(50) NOT NULL,
  `category_code` varchar(20) NOT NULL,
  `item_type` varchar(50) NOT NULL,
  `item_name` varchar(50) DEFAULT NULL,
  `item_desc` text COMMENT 'desc of item contents',
  `image_1` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`item_code`),
  KEY `category_code` (`category_code`),
  CONSTRAINT `items_ibfk_1` FOREIGN KEY (`category_code`) REFERENCES `categories` (`category_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `locations`;
CREATE TABLE `locations` (
  `location_code` varchar(50) NOT NULL,
  `location_name` varchar(50) NOT NULL,
  PRIMARY KEY (`location_code`),
  UNIQUE KEY `location_name` (`location_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `sections`;
CREATE TABLE `sections` (
  `group_weight` int NOT NULL,
  `group_type` varchar(100) NOT NULL,
  `group_name` varchar(100) NOT NULL,
  PRIMARY KEY (`group_type`,`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE `user_roles` (
  `role_id` int NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `group_type` varchar(100) DEFAULT NULL,
  `group_name` varchar(100) DEFAULT NULL,
  `role_id` int NOT NULL,
  `creation_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `reset_token_hash` varchar(64) DEFAULT NULL,
  `reset_expiry` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  KEY `group_type` (`group_type`,`group_name`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`role_id`),
  CONSTRAINT `users_ibfk_2` FOREIGN KEY (`group_type`, `group_name`) REFERENCES `sections` (`group_type`, `group_name`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb3;


-- 2026-09-23 09:52:30 UTC
