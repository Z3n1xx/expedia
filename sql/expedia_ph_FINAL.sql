-- expedia_ph_SETUP.sql — generated 2026-05-18 12:34:53
-- HOW TO USE ON A NEW MACHINE:
--   1. Open phpMyAdmin
--   2. Create a database named: expedia_ph
--   3. Click Import, choose this file, click Go
--   4. Admin login: admin@expedia.ph / Admin@1234

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) NOT NULL,
  `email` varchar(180) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `role` enum('user','admin','staff') NOT NULL DEFAULT 'user',
  `hotel_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `password`, `phone`, `role`, `created_at`) VALUES
('1', 'Admin', 'User', 'admin@expedia.ph', '$2y$10$i/tp8yCiz4C5StvV9mvJiehJxhgNNlW8ARfws2O85w9tZWsCKRqFa', NULL, 'admin', '2026-04-27 11:01:35'),
('3', 'Sean', 'Boniel', 'z3nz3nnn@gmail.com', '$2y$10$sMlJ6MUTWfugJhk9CoyRYeoDWr2LxFaRVkgWLH.HZ9a.LZgC9Fl7C', '09929583626', 'user', '2026-05-12 01:55:01');

DROP TABLE IF EXISTS `locations`;
CREATE TABLE `locations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `city` varchar(100) NOT NULL,
  `country` varchar(100) NOT NULL DEFAULT 'Philippines',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `locations` (`id`, `city`, `country`) VALUES
('1', 'Makati', 'Philippines'),
('2', 'Cebu City', 'Philippines'),
('3', 'Boracay', 'Philippines'),
('4', 'El Nido', 'Philippines'),
('5', 'Davao', 'Philippines'),
('6', 'Baguio', 'Philippines'),
('7', 'Siargao', 'Philippines'),
('8', 'Batangas', 'Philippines');

DROP TABLE IF EXISTS `hotels`;
CREATE TABLE `hotels` (
  `id` int NOT NULL AUTO_INCREMENT,
  `location_id` int NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text,
  `address` varchar(255) DEFAULT NULL,
  `stars` tinyint unsigned DEFAULT '3',
  `rating` decimal(3,2) DEFAULT '0.00',
  `review_count` int DEFAULT '0',
  `thumbnail` varchar(255) DEFAULT NULL,
  `amenities` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `location_id` (`location_id`),
  CONSTRAINT `hotels_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `hotels` (`id`, `location_id`, `name`, `description`, `address`, `stars`, `rating`, `review_count`, `thumbnail`, `amenities`, `is_active`, `created_at`) VALUES
('1', '1', 'The Raffles Makati', 'An icon of luxury in the heart of Makati CBD. Floor-to-ceiling windows frame breathtaking skyline views while butler service anticipates your every need.', '1 Raffles Drive, Makati CBD', '5', '4.85', '1243', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&q=80', '[\"Free WiFi\", \"Infinity Pool\", \"Spa\", \"Gym\", \"Fine Dining\", \"Bar\", \"24hr Room Service\", \"Airport Shuttle\", \"Valet Parking\", \"Concierge\"]', '1', '2026-04-27 11:01:35'),
('2', '3', 'Shangri-La Boracay', 'Perched dramatically on a clifftop overlooking the Sibuyan Sea, this award-winning resort blends barefoot luxury with panoramic ocean views.', 'Barangay Yapak, Boracay Island', '5', '4.91', '2156', 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=800&q=80', '[\"Free WiFi\", \"Private Beach\", \"Infinity Pool\", \"Cliff Spa\", \"Gym\", \"3 Restaurants\", \"Swim-up Bar\", \"Water Sports\", \"Butler Service\", \"Kids Club\"]', '1', '2026-04-27 11:01:35'),
('3', '4', 'El Nido Resorts Pangulasian', 'A private island sanctuary in Bacuit Bay where pristine beaches and eco-luxury combine for an unforgettable Palawan escape.', 'Pangulasian Island, El Nido, Palawan', '5', '4.95', '678', 'https://images.unsplash.com/photo-1439130490301-25e322d88054?w=800&q=80', '[\"Free WiFi\", \"Private Beach\", \"Pool\", \"Eco Spa\", \"Kayaking\", \"Snorkeling\", \"Island Hopping\", \"Yoga\", \"Restaurant\", \"Open Bar\"]', '1', '2026-04-27 11:01:35'),
('4', '7', 'Nay Palad Hideaway', 'An ultra-exclusive lagoon retreat on Siargao — only 10 villas, no keys, no locks, just barefoot luxury and the sound of the sea.', 'Cloud 9 Area, General Luna, Siargao', '5', '4.97', '342', 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800&q=80', '[\"Free WiFi\", \"Private Pool\", \"Beach Access\", \"All-inclusive Dining\", \"Surf Lessons\", \"Island Hopping\", \"Yoga\", \"Kayaking\", \"Butler\"]', '1', '2026-04-27 11:01:35'),
('5', '1', 'Seda BGC', 'A sleek, design-forward hotel rising above Bonifacio Global City, perfect for business and leisure travellers craving urban sophistication.', '30th St. cor. 11th Ave., BGC, Taguig', '4', '4.55', '3421', 'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?w=800&q=80', '[\"Free WiFi\", \"Rooftop Pool\", \"Gym\", \"All-day Restaurant\", \"Bar\", \"Business Center\", \"Meeting Rooms\", \"Valet Parking\"]', '1', '2026-04-27 11:01:35'),
('6', '6', 'The Manor at Camp John Hay', 'A colonial-era mountain retreat nestled within pine forests in Baguio. Cool air, crackling fireplaces, and old-world charm await.', 'Camp John Hay, Loakan Rd, Baguio City', '4', '4.48', '1876', 'https://images.unsplash.com/photo-1455587734955-081b22074882?w=800&q=80', '[\"Free WiFi\", \"Restaurant\", \"Bar\", \"Golf Course\", \"Spa\", \"Mountain Trails\", \"Fireplace Rooms\", \"Event Spaces\"]', '1', '2026-04-27 11:01:35'),
('7', '2', 'Crimson Resort Mactan', 'A spectacular beachfront resort on Mactan Island where coral reefs meet white sand, and every suite opens to the glittering sea.', 'Seascapes Resort Town, Mactan Island, Cebu', '5', '4.72', '987', 'https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=800&q=80', '[\"Free WiFi\", \"Beach Access\", \"Pool\", \"Spa\", \"Gym\", \"3 Restaurants\", \"Bar\", \"Water Sports\", \"Kids Club\", \"Valet\"]', '1', '2026-04-27 11:01:35'),
('8', '5', 'Marco Polo Davao', 'Mindanao\'s most prestigious address — a towering five-star landmark with panoramic city views and impeccable Filipino hospitality.', 'CM Recto St., Davao City', '5', '4.60', '2103', 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=800&q=80', '[\"Free WiFi\", \"Pool\", \"Gym\", \"Spa\", \"Signature Restaurant\", \"Bar\", \"Ballroom\", \"Business Center\", \"Valet Parking\"]', '1', '2026-04-27 11:01:35');

DROP TABLE IF EXISTS `rooms`;
CREATE TABLE `rooms` (
  `id` int NOT NULL AUTO_INCREMENT,
  `hotel_id` int NOT NULL,
  `room_type` varchar(120) NOT NULL,
  `description` text,
  `max_guests` tinyint unsigned DEFAULT '2',
  `price_per_night` decimal(10,2) NOT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `amenities` json DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `hotel_id` (`hotel_id`),
  CONSTRAINT `rooms_ibfk_1` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `rooms` (`id`, `hotel_id`, `room_type`, `description`, `max_guests`, `price_per_night`, `thumbnail`, `amenities`, `is_available`) VALUES
('1', '1', 'Deluxe City View', 'Elegant 52sqm room with floor-to-ceiling windows framing the Makati skyline.', '2', '9500.00', 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=800&q=80', '[\"King Bed\", \"65-inch TV\", \"Nespresso Machine\", \"Mini Bar\", \"Marble Bath\", \"Bathtub\", \"Rainfall Shower\"]', '1'),
('2', '1', 'Premier Suite', '120sqm suite with separate living area, dining for four, and panoramic city views.', '3', '18500.00', 'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=800&q=80', '[\"King Bed\", \"Living Room\", \"Dining Area\", \"Walk-in Closet\", \"Jacuzzi\", \"Butler\", \"City View Terrace\"]', '1'),
('3', '2', 'Ocean View Room', 'Immerse yourself in the turquoise Sibuyan Sea from this beautifully appointed 58sqm room.', '2', '11200.00', 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=800&q=80', '[\"King Bed\", \"Private Balcony\", \"Ocean View\", \"Mini Bar\", \"Rainfall Shower\", \"Bathtub\"]', '1'),
('4', '2', 'Horizon Cliff Villa', 'Private 180sqm villa with plunge pool, perched on the cliff edge at sunset.', '4', '35000.00', 'https://images.unsplash.com/photo-1439130490301-25e322d88054?w=800&q=80', '[\"2 King Beds\", \"Plunge Pool\", \"Butler\", \"Ocean View\", \"Outdoor Shower\", \"Living Room\"]', '1'),
('5', '3', 'Beach Villa', 'Secluded 95sqm villa steps from the white sand, surrounded by lush tropical gardens.', '2', '28000.00', 'https://images.unsplash.com/photo-1499793983690-e29da59ef1c2?w=800&q=80', '[\"King Bed\", \"Beach View\", \"Outdoor Shower\", \"Kayak Access\", \"Snorkel Gear\", \"Rain Shower\"]', '1'),
('6', '4', 'Garden Cottage', 'An intimate thatched cottage with private pool, wrapped in tropical gardens.', '2', '45000.00', 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800&q=80', '[\"King Bed\", \"Private Pool\", \"Outdoor Shower\", \"Beach Access\", \"All-inclusive\", \"Butler\"]', '1'),
('7', '5', 'Premiere Room', 'Sophisticated 38sqm room with modern interiors and sweeping BGC skyline views.', '2', '5200.00', 'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?w=800&q=80', '[\"Queen Bed\", \"City View\", \"Work Desk\", \"Mini Bar\", \"Rainfall Shower\", \"Smart TV\"]', '1'),
('8', '5', 'Executive Suite', '75sqm suite with private lounge access and a dedicated work area.', '2', '9800.00', 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?w=800&q=80', '[\"King Bed\", \"Living Area\", \"Lounge Access\", \"Bathtub\", \"City View\", \"Espresso Machine\"]', '1'),
('9', '6', 'Superior Pine Room', 'Classic 40sqm colonial-style room with wood accents and mountain air views.', '2', '4500.00', 'https://images.unsplash.com/photo-1455587734955-081b22074882?w=800&q=80', '[\"Queen Bed\", \"Mountain View\", \"Fireplace\", \"Work Desk\", \"Rainfall Shower\"]', '1'),
('10', '7', 'Deluxe Garden Room', '48sqm room with lush garden views and easy pool access.', '2', '6800.00', 'https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=800&q=80', '[\"King Bed\", \"Garden View\", \"Pool Access\", \"Mini Bar\", \"Rainfall Shower\"]', '1'),
('11', '7', 'Beachfront Suite', '80sqm suite with direct beach access and private terrace.', '2', '14500.00', 'https://images.unsplash.com/photo-1506929562872-bb421503ef21?w=800&q=80', '[\"King Bed\", \"Beach Access\", \"Private Terrace\", \"Jacuzzi\", \"Butler\"]', '1'),
('12', '8', 'Deluxe Room', 'Well-appointed 42sqm room with city views and premium bedding.', '2', '4800.00', 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=800&q=80', '[\"King Bed\", \"City View\", \"Mini Bar\", \"Work Desk\", \"Rainfall Shower\", \"Smart TV\"]', '1');

DROP TABLE IF EXISTS `bookings`;
CREATE TABLE `bookings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `hotel_id` int NOT NULL,
  `room_id` int NOT NULL,
  `check_in` date NOT NULL,
  `check_out` date NOT NULL,
  `guests` tinyint unsigned DEFAULT '1',
  `total_nights` tinyint unsigned NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','cancelled','completed') DEFAULT 'confirmed',
  `special_requests` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `payment_id` int DEFAULT NULL,
  `payment_status` enum('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid',
  `payment_method` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `hotel_id` (`hotel_id`),
  KEY `room_id` (`room_id`),
  CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `bookings` (`id`, `user_id`, `hotel_id`, `room_id`, `check_in`, `check_out`, `guests`, `total_nights`, `total_price`, `status`, `special_requests`, `created_at`, `payment_id`, `payment_status`, `payment_method`) VALUES
('6', '3', '4', '6', '2026-05-17', '2026-05-18', '2', '1', '45000.00', 'cancelled', '', '2026-05-12 01:55:40', NULL, 'unpaid', NULL),
('7', '3', '3', '5', '2026-05-18', '2026-05-19', '1', '1', '28000.00', 'cancelled', '', '2026-05-12 02:04:29', NULL, 'unpaid', NULL),
('8', '3', '3', '5', '2026-05-17', '2026-05-18', '1', '1', '28000.00', 'cancelled', '', '2026-05-12 06:58:25', NULL, 'unpaid', NULL),
('9', '3', '3', '5', '2026-05-17', '2026-05-18', '1', '1', '28000.00', 'cancelled', '', '2026-05-12 07:03:19', NULL, 'unpaid', NULL),
('10', '3', '8', '12', '2026-05-17', '2026-05-18', '1', '1', '4800.00', 'cancelled', '', '2026-05-12 07:07:57', NULL, 'unpaid', NULL),
('11', '3', '3', '5', '2026-05-17', '2026-05-18', '2', '1', '28000.00', 'cancelled', '', '2026-05-13 19:06:33', NULL, 'paid', 'gcash'),
('12', '3', '4', '6', '2026-05-17', '2026-05-18', '1', '1', '45000.00', 'confirmed', '', '2026-05-14 19:52:43', NULL, 'paid', 'cash');

DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `hotel_id` int NOT NULL,
  `booking_id` int DEFAULT NULL,
  `rating` tinyint unsigned NOT NULL,
  `comment` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `hotel_id` (`hotel_id`),
  KEY `booking_id` (`booking_id`),
  CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

SET FOREIGN_KEY_CHECKS=1;