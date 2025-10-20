CREATE DATABASE IF NOT EXISTS scout_bookings;

USE scout_bookings;

CREATE TABLE `locations` (
    `location_code` varchar(50) NOT NULL PRIMARY KEY,
    `location_name` varchar(50) NOT NULL UNIQUE
);

CREATE TABLE `categories` (
    `category_code` varchar(10) NOT NULL PRIMARY KEY,
    `category_name` varchar(50) NOT NULL UNIQUE,
    `category_description` text
);

CREATE TABLE `user_roles` (
    `role_id` integer AUTO_INCREMENT PRIMARY KEY,
    `role_name` varchar(50) NOT NULL UNIQUE
);

CREATE TABLE `sections` (
    `group_type` varchar(100) NOT NULL,
    `group_name` varchar(100) NOT NULL,
    PRIMARY KEY (`group_type`, `group_name`)
);

CREATE TABLE `users` (
    `user_id` integer AUTO_INCREMENT PRIMARY KEY,
    `password_hash` varchar(255) NOT NULL,
    `first_name` varchar(50) NOT NULL,
    `last_name` varchar(50) NOT NULL,
    `email` varchar(100) NOT NULL UNIQUE,
    `tel_num` varchar(20) NOT NULL,
    `group_type` varchar(100),
    `group_name` varchar(100),
    `role_id` integer NOT NULL,
    `creation_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`role_id`) REFERENCES `user_roles`(`role_id`),
    FOREIGN KEY (`group_type`, `group_name`) REFERENCES `sections`(`group_type`, `group_name`)
);

CREATE TABLE `items` (
    `item_code` varchar(50) NOT NULL PRIMARY KEY,
    `category_code` varchar(10) NOT NULL,
    `item_type` varchar(50) NOT NULL,
    `item_name` varchar(50) NOT NULL,
    `item_desc` text NOT NULL COMMENT 'desc of item contents',
    `image_1` varchar(255) NOT NULL,
    `image_2` varchar(255),
    `image_3` varchar(255),
    FOREIGN KEY (`category_code`) REFERENCES `categories`(`category_code`)
);

CREATE TABLE `components` (
    `component_code` varchar(10) NOT NULL PRIMARY KEY,
    `item_code` varchar(50) NOT NULL,
    `quantity` integer NOT NULL COMMENT 'quantity of components per item unit',
    `item_quality` varchar(255) NOT NULL,
    `quality_desc` text COMMENT 'desc of any damage',
    `return_note` text,
    `replacement_cost` DECIMAL(10, 2),
    `date_purchased` date,
    `end_of_life` date,
    `item_location_code` varchar(50) NOT NULL COMMENT 'location where the specific unit is stored',
    FOREIGN KEY (`item_code`) REFERENCES `items`(`item_code`),
    FOREIGN KEY (`item_location_code`) REFERENCES `locations`(`location_code`)
);

CREATE TABLE `booking_statuses` (
    `status_id` integer AUTO_INCREMENT PRIMARY KEY,
    `status_name` varchar(50) NOT NULL UNIQUE COMMENT 'Pending, Approved, Collected, Returned, Cancelled'
);

CREATE TABLE `bookings` (
    `booking_id` integer AUTO_INCREMENT PRIMARY KEY,
    `user_id` integer NOT NULL,
    `event_name` varchar(50) NOT NULL,
    `event_start` DATETIME NOT NULL,
    `event_end` DATETIME NOT NULL,
    `collection_date` DATE NOT NULL,
    `return_date` DATE NOT NULL,
    `event_location_code` varchar(50) NOT NULL,
    `booking_status_id` integer NOT NULL DEFAULT 1,
    `approved_by_user_id` integer,
    `approval_datetime` DATETIME,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `last_updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`),
    FOREIGN KEY (`booking_status_id`) REFERENCES `booking_statuses`(`status_id`),
    FOREIGN KEY (`approved_by_user_id`) REFERENCES `users`(`user_id`),
    FOREIGN KEY (`event_location_code`) REFERENCES `locations`(`location_code`)
);

CREATE TABLE `booking_items` (
    `booking_id` integer NOT NULL,
    `item_code` varchar(10) NOT NULL,
    `quantity_needed` integer NOT NULL,
    `quantity_returned` integer,
    `damage_notes_on_return` text,
    PRIMARY KEY (`booking_id`, `item_code`),
    FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`booking_id`),
    FOREIGN KEY (`item_code`) REFERENCES `items`(`item_code`)
);

CREATE TABLE `item_maintenance_log` (
    `log_id` integer AUTO_INCREMENT PRIMARY KEY,
    `item_code` varchar(10) NOT NULL,
    `log_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `action_type` varchar(50) NOT NULL,
    `description` text,
    `cost_in_gbp` DECIMAL(10, 2),
    `performed_by_user_id` integer,
    FOREIGN KEY (`item_code`) REFERENCES `items`(`item_code`),
    FOREIGN KEY (`performed_by_user_id`) REFERENCES `users`(`user_id`)
);