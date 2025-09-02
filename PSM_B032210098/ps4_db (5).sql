-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Sep 02, 2025 at 11:04 AM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ps4_db`
--

DELIMITER $$
--
-- Procedures
--
DROP PROCEDURE IF EXISTS `cancel_rental_and_check_ban`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `cancel_rental_and_check_ban` (IN `p_rental_ID` VARCHAR(10))   BEGIN
    DECLARE v_customer_ID VARCHAR(10);

    -- Get customer ID
    SELECT customer_ID INTO v_customer_ID FROM rentals WHERE rental_ID = p_rental_ID;

    -- Cancel the rental
    UPDATE rentals SET rental_status = 'cancelled' WHERE rental_ID = p_rental_ID;

    -- Check for ban (same logic as trigger)
    IF (
        SELECT COUNT(*) FROM rentals
        WHERE customer_ID = v_customer_ID
          AND rental_status = 'cancelled'
          AND MONTH(booking_start_time) = MONTH(NOW())
          AND YEAR(booking_start_time) = YEAR(NOW())
    ) > 3 THEN
        UPDATE customers SET status = 'banned' WHERE customer_ID = v_customer_ID;
    END IF;
END$$

DROP PROCEDURE IF EXISTS `create_rental_booking`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `create_rental_booking` (IN `p_customer_ID` VARCHAR(10), IN `p_console_ID` VARCHAR(10), IN `p_number_of_players` INT, IN `p_booking_start_time` DATETIME, IN `p_booking_end_time` DATETIME, IN `p_total_amount` DECIMAL(10,2), IN `p_game_IDs` TEXT)   BEGIN
    DECLARE v_rental_ID VARCHAR(10);
    DECLARE v_count INT;

    -- Generate rental_ID
    SELECT COUNT(*) + 1 INTO v_count FROM rentals;
    SET v_rental_ID = CONCAT('RENT', LPAD(v_count, 4, '0'));

    -- Insert into rentals
    INSERT INTO rentals (rental_ID, customer_ID, console_ID, number_of_players, booking_start_time, booking_end_time, total_amount, rental_status, created_at)
    VALUES (v_rental_ID, p_customer_ID, p_console_ID, p_number_of_players, p_booking_start_time, p_booking_end_time, p_total_amount, 'pending_payment', NOW());

    -- Insert into rental_games (split p_game_IDs by comma)
    WHILE LOCATE(',', p_game_IDs) > 0 DO
        INSERT INTO rental_games (rental_ID, game_ID)
        VALUES (v_rental_ID, SUBSTRING_INDEX(p_game_IDs, ',', 1));
        SET p_game_IDs = SUBSTRING(p_game_IDs, LOCATE(',', p_game_IDs) + 1);
    END WHILE;
    IF LENGTH(p_game_IDs) > 0 THEN
        INSERT INTO rental_games (rental_ID, game_ID)
        VALUES (v_rental_ID, p_game_IDs);
    END IF;
END$$

DROP PROCEDURE IF EXISTS `get_available_consoles`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `get_available_consoles` (IN `p_start` DATETIME, IN `p_end` DATETIME)   BEGIN
    SELECT * FROM consoles
    WHERE consoles_status = 'available'
      AND console_ID NOT IN (
        SELECT console_ID FROM rentals
        WHERE booking_start_time < p_end AND booking_end_time > p_start
          AND rental_status IN ('confirmed','in_progress')
      );
END$$

DROP PROCEDURE IF EXISTS `get_customer_booking_history`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `get_customer_booking_history` (IN `p_customer_ID` VARCHAR(10))   BEGIN
    SELECT r.*, co.console_name
    FROM rentals r
    JOIN consoles co ON r.console_ID = co.console_ID
    WHERE r.customer_ID = p_customer_ID
    ORDER BY r.created_at DESC;
END$$

DROP PROCEDURE IF EXISTS `get_top_customers`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `get_top_customers` ()   BEGIN
    SELECT c.customer_ID, c.customer_full_name, SUM(r.total_amount) AS total_spent
    FROM customers c
    JOIN rentals r ON c.customer_ID = r.customer_ID
    WHERE r.rental_status = 'completed'
    GROUP BY c.customer_ID, c.customer_full_name
    ORDER BY total_spent DESC
    LIMIT 10;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `consoles`
--

DROP TABLE IF EXISTS `consoles`;
CREATE TABLE IF NOT EXISTS `consoles` (
  `console_ID` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `console_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `console_model` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `max_controllers` int NOT NULL DEFAULT '4',
  `consoles_status` enum('available','in_use','maintenance') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `hourly_rate` decimal(8,2) NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`console_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `consoles`
--

INSERT INTO `consoles` (`console_ID`, `console_name`, `console_model`, `location_description`, `max_controllers`, `consoles_status`, `hourly_rate`, `notes`) VALUES
('CONS0001', 'PS4 A', 'PS4', 'Kampus Induk', 4, 'available', 2.00, NULL),
('CONS0003', 'PS4 C', 'PS4', 'Kampus Induk', 4, 'available', 2.00, NULL),
('CONS0004', 'PS4 B', '', '', 4, 'available', 2.00, '');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
CREATE TABLE IF NOT EXISTS `customers` (
  `customer_ID` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_matric_no` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_profile_picture` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','banned','pending_verification') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_verification',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reset_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_expires` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `failed_login_attempts` int NOT NULL DEFAULT '0',
  `last_failed_login` timestamp NULL DEFAULT NULL,
  `verification_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`customer_ID`),
  UNIQUE KEY `customer_username` (`customer_username`),
  UNIQUE KEY `customer_email` (`customer_email`),
  UNIQUE KEY `customer_matric_no` (`customer_matric_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`customer_ID`, `customer_username`, `customer_full_name`, `customer_email`, `customer_matric_no`, `customer_password_hash`, `customer_profile_picture`, `customer_phone`, `status`, `last_login`, `created_at`, `reset_token`, `reset_expires`, `remember_token`, `failed_login_attempts`, `last_failed_login`, `verification_token`) VALUES
('CUST0002', 'kir', 'alicai', 'syaklkl@gmail.com', NULL, '$2y$10$cJjuF1UisUim/RXFC0QAquDO8i5B0IBImRVlqx.Pk19wPBntdlXAu', NULL, 'jjj', 'active', NULL, '2025-06-29 13:25:39', NULL, NULL, NULL, 1, '2025-07-02 08:38:43', NULL),
('CUST0003', 'alex_wong', 'Alex Wong Wei Ming', 'alex.wong@student.edu.my', 'B032210098', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '+60123456794', 'active', NULL, '2025-07-01 23:37:42', NULL, NULL, NULL, 0, NULL, NULL),
('CUST0004', 'siti_rahman', 'Siti Nur Rahman', 'siti.rahman@student.edu.my', 'B052210467', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '+60123456795', 'active', NULL, '2025-07-01 23:37:42', NULL, NULL, NULL, 0, NULL, NULL),
('CUST0005', 'david_lee', 'David Lee Chong Wei', 'david.lee@student.edu.my', 'B072210836', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '+60123456796', 'active', NULL, '2025-07-01 23:37:42', NULL, NULL, NULL, 0, NULL, NULL),
('CUST0006', 'nurul_huda', 'Nurul Huda Binti Ahmad', 'nurul.huda@student.edu.my', 'B092210205', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '+60123456797', 'active', NULL, '2025-07-01 23:37:42', NULL, NULL, NULL, 0, NULL, NULL),
('CUST0007', 'kevin_tan', 'Kevin Tan Boon Kiat', 'kevin.tan@student.edu.my', 'B112210574', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '+60123456798', 'active', NULL, '2025-07-01 23:37:42', NULL, NULL, NULL, 0, NULL, NULL),
('CUST0008', 'fatimah_ali', 'Fatimah Binti Ali', 'fatimah.ali@student.edu.my', 'B132210943', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '+60123456799', 'active', NULL, '2025-07-01 23:37:42', NULL, NULL, NULL, 0, NULL, NULL),
('CUST0009', 'jason_lim', 'Jason Lim Wei Jie', 'jason.lim@student.edu.my', 'B032210312', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '+60123456800', 'active', NULL, '2025-07-01 23:37:42', NULL, NULL, NULL, 0, NULL, NULL),
('CUST0010', 'aisyah_omar', 'Aisyah Binti Omar', 'aisyah.omar@student.edu.my', 'B052210681', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '+60123456801', 'active', NULL, '2025-07-01 23:37:42', NULL, NULL, NULL, 0, NULL, NULL),
('CUST0011', 'ryan_ng', 'Ryan Ng Chee Kuan', 'ryan.ng@student.edu.my', 'B072210050', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '+60123456802', 'active', NULL, '2025-07-01 23:37:42', NULL, NULL, NULL, 0, NULL, NULL),
('CUST0012', 'nur_izzati', 'Nur Izzati Binti Ismail', 'nur.izzati@student.edu.my', 'B092210419', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '+60123456803', 'active', NULL, '2025-07-01 23:37:42', NULL, NULL, NULL, 0, NULL, NULL),
('CUST0013', 'brandon_chew', 'Brandon Chew Wei Hong', 'brandon.chew@student.edu.my', 'B112210788', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '+60123456804', 'active', NULL, '2025-07-01 23:37:42', NULL, NULL, NULL, 0, NULL, NULL),
('CUST0014', 'nurul_ain', 'Nurul Ain Binti Mohd', 'nurul.ain@student.edu.my', 'B132210157', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '+60123456805', 'active', NULL, '2025-07-01 23:37:42', NULL, NULL, NULL, 0, NULL, NULL),
('CUST0015', 'ethan_teo', 'Ethan Teo Zhi Wei', 'ethan.teo@student.edu.my', 'B032210526', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '+60123456806', 'active', NULL, '2025-07-01 23:37:42', NULL, NULL, NULL, 0, NULL, NULL),
('CUST0016', 'nur_farhana', 'Nur Farhana Binti Zulkifli', 'nur.farhana@student.edu.my', 'B052210895', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '+60123456807', 'active', NULL, '2025-07-01 23:37:42', NULL, NULL, NULL, 0, NULL, NULL),
('CUST0017', 'adrian_ong', 'Adrian Ong Kian Ming', 'adrian.ong@student.edu.my', 'B072210264', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '+60123456808', 'active', NULL, '2025-07-01 23:37:42', NULL, NULL, NULL, 0, NULL, NULL),
('CUST0018', 'nurul_syafiqah', 'Nurul Syafiqah Binti Kamal', 'nurul.syafiqah@student.edu.my', 'B092210633', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '+60123456809', 'active', NULL, '2025-07-01 23:37:42', NULL, NULL, NULL, 0, NULL, NULL),
('CUST0019', 'daniel_yeo', 'Daniel Yeo Wei Sheng', 'daniel.yeo@student.edu.my', 'B112210002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '+60123456810', 'active', NULL, '2025-07-01 23:37:42', NULL, NULL, NULL, 0, NULL, NULL),
('CUST0020', 'nur_amirah', 'Nur Amirah Binti Razak', 'nur.amirah@student.edu.my', 'B132210371', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '+60123456811', 'active', NULL, '2025-07-01 23:37:42', NULL, NULL, NULL, 0, NULL, NULL),
('CUST0021', 'justin_lau', 'Justin Lau Wei Jie', 'justin.lau@student.edu.my', 'B032210740', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '+60123456812', 'active', NULL, '2025-07-01 23:37:42', NULL, NULL, NULL, 0, NULL, NULL),
('CUST0022', 'nurul_hidayah', 'Nurul Hidayah Binti Yusof', 'nurul.hidayah@student.edu.my', 'B052210109', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '+60123456813', 'active', NULL, '2025-07-01 23:37:42', NULL, NULL, NULL, 0, NULL, NULL),
('CUST0023', 'christopher_ho', 'Christopher Ho Wei Ming', 'christopher.ho@student.edu.my', 'B072210478', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '+60123456814', 'active', NULL, '2025-07-01 23:37:42', NULL, NULL, NULL, 0, NULL, NULL),
('CUST0024', 'nurul_izzati', 'Nurul Izzati Binti Hashim', 'nurul.izzati@student.edu.my', 'B092210847', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '+60123456815', 'active', NULL, '2025-07-01 23:37:42', NULL, NULL, NULL, 0, NULL, NULL),
('CUST0025', 'nicholas_goh', 'Nicholas Goh Wei Jie', 'nicholas.goh@student.edu.my', 'B112210216', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '+60123456816', 'active', NULL, '2025-07-01 23:37:42', NULL, NULL, NULL, 0, NULL, NULL),
('CUST0026', 'nurul_ain_aziz', 'Nurul Ain Binti Aziz', 'nurul.ainaziz@student.edu.my', 'B132210585', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '+60123456817', 'active', NULL, '2025-07-01 23:44:19', NULL, NULL, NULL, 0, NULL, NULL),
('CUST0027', 'jonathan_chong', 'Jonathan Chong Wei Kiat', 'jonathan.chong@student.edu.my', 'B032210954', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '+60123456818', 'active', NULL, '2025-07-01 23:44:32', NULL, NULL, NULL, 0, NULL, NULL),
('CUST0028', 'nurul_syazwani', 'Nurul Syazwani Binti Omar', 'nurul.syazwani@student.edu.my', 'B052210323', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '+60123456819', 'active', NULL, '2025-07-01 23:44:32', NULL, NULL, NULL, 0, NULL, NULL),
('CUST68a6a6', 'hoceokieee', 'Maisara Siput', 'maisaraphongsukwan@gmail.com', 'B032210110', '$2y$10$OV2cYbIb/7sB7JBiWQp2reALeKfPoQoTOhDlFpi2R4hF2kxjrDNNy', 'uploads/profile_picture/profile_68a6a600c90d50.41192422.jpg', '0174890248', 'active', '2025-08-28 03:57:57', '2025-08-21 04:52:16', NULL, NULL, 'f1253b946a51d9160d65693cbde9532c65218718001e943fdee59ca4b269245e', 0, NULL, NULL);

--
-- Triggers `customers`
--
DROP TRIGGER IF EXISTS `trg_prevent_delete_customer`;
DELIMITER $$
CREATE TRIGGER `trg_prevent_delete_customer` BEFORE DELETE ON `customers` FOR EACH ROW BEGIN
    IF EXISTS (
        SELECT 1 FROM rentals
        WHERE customer_ID = OLD.customer_ID
          AND rental_status IN ('pending_payment', 'confirmed', 'in_progress')
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cannot delete customer with active rentals.';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `games`
--

DROP TABLE IF EXISTS `games`;
CREATE TABLE IF NOT EXISTS `games` (
  `game_ID` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `game_title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `game_description` text COLLATE utf8mb4_unicode_ci,
  `min_players` int NOT NULL DEFAULT '1',
  `max_players` int NOT NULL DEFAULT '4',
  `game_picture` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `game_video_trailer` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`game_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `games`
--

INSERT INTO `games` (`game_ID`, `game_title`, `game_description`, `min_players`, `max_players`, `game_picture`, `game_video_trailer`, `is_available`) VALUES
('GAME0001', 'Crash Team Racing Nitro-Fueled', 'Fast-paced', 1, 4, 'uploads/images/crash_team_racing.jpg', 'uploads/videos/Crash Team Racing Nitro-Fueled.mp4', 1),
('GAME0002', 'FC 24', 'The latest football simulation with realistic gameplay and updated teams.', 1, 4, 'uploads/images/fc24.jpg', 'uploads/videos/FC 24.mp4', 1),
('GAME0003', 'FIFA 23', 'Experience the world’s game with new features, teams, and authentic leagues.', 1, 4, 'uploads/images/fifa23.jpg', 'uploads/videos/FIFA 23.mp4', 1),
('GAME0004', 'God of War 3 Remastered', 'Epic single-player adventure as Kratos battles gods and monsters in ancient Greece.', 1, 1, 'uploads/images/god_war_3_remastered.jpg', 'uploads/videos/God of War 3 Remastered.mp4', 1),
('GAME0005', 'Injustice 2', 'Superhero fighting game featuring DC Comics characters and cinematic battles.', 1, 2, 'uploads/images/injustice2.jpg', 'uploads/videos/Injustice 2.mp4', 1),
('GAME0006', 'NBA 2K24', 'Realistic basketball simulation with updated rosters and multiplayer modes.', 1, 4, 'uploads/images/nba2k24.jpg', 'uploads/videos/NBA 2K24.mp4', 1),
('GAME0007', 'Overcooked! All You Can Eat', 'Chaotic cooperative cooking game with all Overcooked content and new levels.', 1, 4, 'uploads/images/overcooked_all_you_can_eat.jpg', 'uploads/videos/Overcooked! All You Can Eat.mp4', 1),
('GAME0008', 'Overcooked', 'Work together to prepare and serve food in increasingly challenging kitchens.', 1, 4, 'uploads/images/overcooked.jpg', 'uploads/videos/Overcooked.mp4', 1),
('GAME0009', 'Sackboy: A Big Adventure', '3D platforming adventure with creative levels and multiplayer fun.', 1, 4, 'uploads/images/sackboy_a_big_adventure.jpg', 'uploads/videos/Sackboy_ A Big Adventure.mp4', 1),
('GAME0010', 'Tekken 7', 'The latest entry in the legendary fighting series with new characters and moves.', 1, 2, 'uploads/images/tekken_7.jpg', 'uploads/videos/Tekken 7.mp4', 1),
('GAME0011', 'Warhammer: Chaosbane', 'Hack-and-slash action RPG set in the Warhammer Fantasy universe.', 1, 4, 'uploads/images/warhammer_chaosbane.jpg', 'uploads/videos/Warhammer_ Chaosbane.mp4', 1);

-- --------------------------------------------------------

--
-- Table structure for table `game_tags`
--

DROP TABLE IF EXISTS `game_tags`;
CREATE TABLE IF NOT EXISTS `game_tags` (
  `game_ID` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tag_ID` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`game_ID`,`tag_ID`),
  KEY `tag_ID` (`tag_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `game_tags`
--

INSERT INTO `game_tags` (`game_ID`, `tag_ID`) VALUES
('GAME0001', 'TAG0001'),
('GAME0001', 'TAG0006'),
('GAME0001', 'TAG0007'),
('GAME0001', 'TAG0016'),
('GAME0002', 'TAG0002'),
('GAME0002', 'TAG0016'),
('GAME0002', 'TAG0017'),
('GAME0003', 'TAG0002'),
('GAME0003', 'TAG0016'),
('GAME0003', 'TAG0017'),
('GAME0004', 'TAG0003'),
('GAME0004', 'TAG0004'),
('GAME0004', 'TAG0017'),
('GAME0004', 'TAG0019'),
('GAME0005', 'TAG0005'),
('GAME0005', 'TAG0016'),
('GAME0005', 'TAG0017'),
('GAME0006', 'TAG0002'),
('GAME0006', 'TAG0016'),
('GAME0006', 'TAG0017'),
('GAME0007', 'TAG0006'),
('GAME0007', 'TAG0007'),
('GAME0007', 'TAG0016'),
('GAME0007', 'TAG0018'),
('GAME0008', 'TAG0006'),
('GAME0008', 'TAG0007'),
('GAME0008', 'TAG0016'),
('GAME0008', 'TAG0018'),
('GAME0009', 'TAG0004'),
('GAME0009', 'TAG0008'),
('GAME0009', 'TAG0016'),
('GAME0009', 'TAG0017'),
('GAME0009', 'TAG0018'),
('GAME0010', 'TAG0005'),
('GAME0010', 'TAG0016'),
('GAME0010', 'TAG0017'),
('GAME0011', 'TAG0003'),
('GAME0011', 'TAG0009'),
('GAME0011', 'TAG0016'),
('GAME0011', 'TAG0017'),
('GAME0011', 'TAG0019');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE IF NOT EXISTS `payments` (
  `payment_ID` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `staff_ID` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `payment_method` enum('FPX') COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_status` enum('completed','refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `payment_proof` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`payment_ID`),
  KEY `staff_ID` (`staff_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_ID`, `staff_ID`, `amount`, `payment_date`, `payment_method`, `payment_status`, `payment_proof`, `transaction_reference`) VALUES
('PAY0001', NULL, 4.00, '2025-06-25 14:30:15', 'FPX', 'completed', 'uploads/payment_proofs/RENT0003_1751179726.pdf', 'TXN001234567890'),
('PAY0002', NULL, 6.00, '2025-06-26 16:45:22', 'FPX', 'completed', 'uploads/payment_proofs/RENT0004_1751204278.pdf', 'TXN001234567891'),
('PAY0003', NULL, 8.00, '2025-06-27 10:15:33', 'FPX', 'completed', 'uploads/payment_proofs/RENT0005_1751136219.pdf', 'TXN001234567892'),
('PAY0004', NULL, 2.00, '2025-06-28 19:20:45', 'FPX', 'completed', 'uploads/payment_proofs/RENT0006_1751179727.pdf', 'TXN001234567893'),
('PAY0005', NULL, 10.00, '2025-06-29 11:30:12', 'FPX', 'completed', 'uploads/payment_proofs/RENT0007_1751204279.pdf', 'TXN001234567894'),
('PAY0006', NULL, 4.00, '2025-06-30 15:45:28', 'FPX', 'completed', 'uploads/payment_proofs/RENT0008_1751136220.pdf', 'TXN001234567895'),
('PAY0007', NULL, 6.00, '2025-07-01 09:10:55', 'FPX', 'completed', 'uploads/payment_proofs/RENT0009_1751179728.pdf', 'TXN001234567896'),
('PAY0008', NULL, 8.00, '2025-07-02 13:25:18', 'FPX', 'completed', 'uploads/payment_proofs/RENT0010_1751204280.pdf', 'TXN001234567897'),
('PAY0009', NULL, 2.00, '2025-07-03 17:40:42', 'FPX', 'completed', 'uploads/payment_proofs/RENT0011_1751136221.pdf', 'TXN001234567898'),
('PAY0010', NULL, 12.00, '2025-07-04 20:55:33', 'FPX', 'completed', 'uploads/payment_proofs/RENT0012_1751179729.pdf', 'TXN001234567899'),
('PAY0011', NULL, 4.00, '2025-07-05 08:15:27', 'FPX', 'completed', 'uploads/payment_proofs/RENT0013_1751204281.pdf', 'TXN001234567900'),
('PAY0012', NULL, 6.00, '2025-07-06 12:30:44', 'FPX', 'completed', 'uploads/payment_proofs/RENT0014_1751136222.pdf', 'TXN001234567901'),
('PAY0013', NULL, 8.00, '2025-07-07 16:45:19', 'FPX', 'completed', 'uploads/payment_proofs/RENT0015_1751179730.pdf', 'TXN001234567902'),
('PAY0014', NULL, 2.00, '2025-07-08 21:00:36', 'FPX', 'completed', 'uploads/payment_proofs/RENT0016_1751204282.pdf', 'TXN001234567903'),
('PAY0015', NULL, 10.00, '2025-07-09 07:25:53', 'FPX', 'completed', 'uploads/payment_proofs/RENT0017_1751136223.pdf', 'TXN001234567904'),
('PAY0016', NULL, 4.00, '2025-07-10 11:40:08', 'FPX', 'completed', 'uploads/payment_proofs/RENT0018_1751179731.pdf', 'TXN001234567905'),
('PAY0017', NULL, 6.00, '2025-07-11 14:55:25', 'FPX', 'completed', 'uploads/payment_proofs/RENT0019_1751204283.pdf', 'TXN001234567906'),
('PAY0018', NULL, 8.00, '2025-07-12 18:10:47', 'FPX', 'completed', 'uploads/payment_proofs/RENT0020_1751136224.pdf', 'TXN001234567907'),
('PAY0019', NULL, 2.00, '2025-07-13 22:25:14', 'FPX', 'completed', 'uploads/payment_proofs/RENT0021_1751179732.pdf', 'TXN001234567908'),
('PAY0020', NULL, 12.00, '2025-07-14 06:50:31', 'FPX', 'completed', 'uploads/payment_proofs/RENT0022_1751204284.pdf', 'TXN001234567909'),
('PAY0257', NULL, 4.00, '2025-08-22 15:08:03', 'FPX', 'completed', 'uploads/payment_proofs/RENT0025_1755846483.pdf', NULL),
('PAY1026', NULL, 4.00, '2025-07-02 12:09:25', 'FPX', 'completed', 'uploads/payment_proofs/RENT0028_1751429365.pdf', NULL),
('PAY1636', NULL, 4.00, '2025-07-02 12:10:17', 'FPX', 'completed', 'uploads/payment_proofs/RENT0027_1751429417.pdf', NULL),
('PAY2120', NULL, 2.00, '2025-08-28 11:59:59', 'FPX', 'completed', 'uploads/payment_proofs/RENT0026_1756353599.pdf', NULL),
('PAY3905', NULL, 6.00, '2025-08-28 13:00:31', 'FPX', 'completed', 'uploads/payment_proofs/RENT0027_1756357231.pdf', NULL),
('PAY3949', NULL, 2.00, '2025-06-29 14:48:46', 'FPX', 'completed', 'uploads/payment_proofs/RENT0001_1751179726.pdf', NULL),
('PAY4933', NULL, 4.00, '2025-07-02 11:42:19', 'FPX', 'completed', NULL, NULL),
('PAY6100', NULL, 4.00, '2025-07-02 11:35:43', 'FPX', 'completed', 'uploads/payment_proofs/RENT0025_1751427343.pdf', NULL),
('PAY6208', NULL, 8.00, '2025-06-29 21:37:58', 'FPX', 'completed', 'uploads/payment_proofs/RENT0002_1751204278.pdf', NULL),
('PAY7402', NULL, 2.00, '2025-07-02 11:51:10', 'FPX', 'completed', 'uploads/payment_proofs/RENT0026_1751428270.pdf', NULL),
('PAY7760', NULL, 4.00, '2025-07-02 12:08:09', 'FPX', 'completed', 'uploads/payment_proofs/RENT0027_1751429289.pdf', NULL),
('PAY7796', NULL, 6.00, '2025-08-21 14:16:33', 'FPX', 'completed', 'uploads/payment_proofs/RENT0024_1755756993.pdf', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `ratings`
--

DROP TABLE IF EXISTS `ratings`;
CREATE TABLE IF NOT EXISTS `ratings` (
  `rating_ID` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rating_stars` int NOT NULL,
  `review_comment` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `customer_ID` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `game_ID` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`rating_ID`),
  KEY `customer_ID` (`customer_ID`),
  KEY `game_ID` (`game_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ratings`
--

INSERT INTO `ratings` (`rating_ID`, `rating_stars`, `review_comment`, `created_at`, `customer_ID`, `game_ID`) VALUES
('RATE0001', 5, 'Amazing racing game! The graphics are stunning and multiplayer is so much fun.', '2025-06-25 09:30:00', 'CUST0003', 'GAME0001'),
('RATE0002', 4, 'Great cooperative gameplay, perfect for friends!', '2025-06-25 09:35:00', 'CUST0003', 'GAME0007'),
('RATE0003', 5, 'Best football game ever! Realistic gameplay and updated teams.', '2025-06-26 11:15:00', 'CUST0004', 'GAME0002'),
('RATE0004', 4, 'Basketball simulation is very realistic. Love the multiplayer modes.', '2025-06-26 11:20:00', 'CUST0004', 'GAME0006'),
('RATE0005', 5, 'Epic adventure game! The story and graphics are incredible.', '2025-06-27 06:30:00', 'CUST0005', 'GAME0004'),
('RATE0006', 4, 'Fun fighting game with great character roster.', '2025-06-28 12:15:00', 'CUST0006', 'GAME0005'),
('RATE0007', 5, 'Amazing racing experience! Love the classic tracks.', '2025-06-29 08:30:00', 'CUST0007', 'GAME0001'),
('RATE0008', 4, 'Great football game with realistic gameplay.', '2025-06-30 09:45:00', 'CUST0008', 'GAME0002'),
('RATE0009', 5, 'Best cooperative cooking game! So much fun with friends.', '2025-07-01 04:30:00', 'CUST0009', 'GAME0007'),
('RATE0010', 4, 'Amazing platforming adventure! Creative levels and great graphics.', '2025-07-02 09:15:00', 'CUST0010', 'GAME0009'),
('RATE0011', 5, 'Epic single-player experience! The story is incredible.', '2025-07-03 10:30:00', 'CUST0011', 'GAME0004'),
('RATE0012', 4, 'Great racing game for multiplayer sessions.', '2025-07-03 18:30:00', 'CUST0012', 'GAME0001'),
('RATE0013', 5, 'Best basketball simulation! Realistic gameplay.', '2025-07-05 02:15:00', 'CUST0013', 'GAME0006'),
('RATE0014', 4, 'Fun cooperative cooking game! Perfect for groups.', '2025-07-06 07:30:00', 'CUST0014', 'GAME0007'),
('RATE0015', 5, 'Amazing adventure game! Love the creative levels.', '2025-07-07 12:45:00', 'CUST0015', 'GAME0009'),
('RAT6864a44', 5, 'The game is really fun to play with friends', '2025-07-02 03:15:24', 'CUST0001', 'GAME0001');

-- --------------------------------------------------------

--
-- Table structure for table `rentals`
--

DROP TABLE IF EXISTS `rentals`;
CREATE TABLE IF NOT EXISTS `rentals` (
  `rental_ID` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number_of_players` int NOT NULL DEFAULT '1',
  `booking_start_time` datetime NOT NULL,
  `booking_end_time` datetime NOT NULL,
  `actual_start_time` datetime DEFAULT NULL,
  `actual_end_time` datetime DEFAULT NULL,
  `rental_status` enum('pending_payment','confirmed','in_progress','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_payment',
  `customer_ID` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `console_ID` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `staff_ID` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `payment_ID` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`rental_ID`),
  KEY `console_ID` (`console_ID`),
  KEY `staff_ID` (`staff_ID`),
  KEY `fk_rentals_payment` (`payment_ID`),
  KEY `rentals_ibfk_1` (`customer_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rentals`
--

INSERT INTO `rentals` (`rental_ID`, `number_of_players`, `booking_start_time`, `booking_end_time`, `actual_start_time`, `actual_end_time`, `rental_status`, `customer_ID`, `console_ID`, `staff_ID`, `total_amount`, `created_at`, `notes`, `payment_ID`) VALUES
('RENT0002', 1, '2025-06-30 17:00:00', '2025-06-30 21:00:00', NULL, NULL, 'cancelled', 'CUST0002', 'CONS0001', NULL, 8.00, '2025-06-29 13:30:24', NULL, ''),
('RENT0003', 2, '2025-06-25 15:00:00', '2025-06-25 17:00:00', '2025-06-25 15:05:00', '2025-06-25 16:55:00', 'completed', 'CUST0003', 'CONS0001', NULL, 4.00, '2025-06-25 06:25:00', 'Great gaming session!', 'PAY0001'),
('RENT0004', 3, '2025-06-26 16:00:00', '2025-06-26 19:00:00', '2025-06-26 16:10:00', '2025-06-26 18:50:00', 'completed', 'CUST0004', 'CONS0002', NULL, 6.00, '2025-06-26 07:30:00', 'Multiplayer fun!', 'PAY0002'),
('RENT0005', 4, '2025-06-27 10:00:00', '2025-06-27 14:00:00', '2025-06-27 10:15:00', '2025-06-27 13:45:00', 'completed', 'CUST0005', 'CONS0003', NULL, 8.00, '2025-06-27 01:45:00', 'Full team gaming!', 'PAY0003'),
('RENT0006', 1, '2025-06-28 19:00:00', '2025-06-28 20:00:00', '2025-06-28 19:05:00', '2025-06-28 19:55:00', 'completed', 'CUST0006', 'CONS0001', NULL, 2.00, '2025-06-28 10:30:00', 'Solo gaming time', 'PAY0004'),
('RENT0007', 2, '2025-06-29 11:00:00', '2025-06-29 16:00:00', '2025-06-29 11:10:00', '2025-06-29 15:50:00', 'completed', 'CUST0007', 'CONS0002', NULL, 10.00, '2025-06-29 02:30:00', 'Long gaming session', 'PAY0005'),
('RENT0008', 2, '2025-06-30 15:00:00', '2025-06-30 17:00:00', '2025-06-30 15:05:00', '2025-06-30 16:55:00', 'completed', 'CUST0008', 'CONS0003', NULL, 4.00, '2025-06-30 06:30:00', 'Afternoon gaming', 'PAY0006'),
('RENT0009', 3, '2025-07-01 09:00:00', '2025-07-01 12:00:00', '2025-07-01 09:10:00', '2025-07-01 11:50:00', 'completed', 'CUST0009', 'CONS0001', NULL, 6.00, '2025-07-01 00:30:00', 'Morning gaming group', 'PAY0007'),
('RENT0010', 4, '2025-07-02 13:00:00', '2025-07-02 17:00:00', '2025-07-02 13:15:00', '2025-07-02 16:45:00', 'completed', 'CUST0010', 'CONS0002', NULL, 8.00, '2025-07-02 04:30:00', 'Full squad gaming', 'PAY0008'),
('RENT0011', 1, '2025-07-03 17:00:00', '2025-07-03 18:00:00', '2025-07-03 17:05:00', '2025-07-03 17:55:00', 'completed', 'CUST0011', 'CONS0003', NULL, 2.00, '2025-07-03 08:30:00', 'Quick gaming break', 'PAY0009'),
('RENT0012', 2, '2025-07-04 20:00:00', '2025-07-05 02:00:00', '2025-07-04 20:10:00', '2025-07-05 01:50:00', 'completed', 'CUST0012', 'CONS0001', NULL, 12.00, '2025-07-04 11:30:00', 'Late night gaming', 'PAY0010'),
('RENT0013', 2, '2025-07-05 08:00:00', '2025-07-05 10:00:00', '2025-07-05 08:05:00', '2025-07-05 09:55:00', 'completed', 'CUST0013', 'CONS0002', NULL, 4.00, '2025-07-04 23:30:00', 'Early morning gaming', 'PAY0011'),
('RENT0014', 3, '2025-07-06 12:00:00', '2025-07-06 15:00:00', '2025-07-06 12:10:00', '2025-07-06 14:50:00', 'completed', 'CUST0014', 'CONS0003', NULL, 6.00, '2025-07-06 03:30:00', 'Lunch break gaming', 'PAY0012'),
('RENT0015', 4, '2025-07-07 16:00:00', '2025-07-07 20:00:00', '2025-07-07 16:15:00', '2025-07-07 19:45:00', 'completed', 'CUST0015', 'CONS0001', NULL, 8.00, '2025-07-07 07:30:00', 'Evening gaming party', 'PAY0013'),
('RENT0016', 1, '2025-07-08 21:00:00', '2025-07-08 22:00:00', '2025-07-08 21:05:00', '2025-07-08 21:55:00', 'completed', 'CUST0016', 'CONS0002', NULL, 2.00, '2025-07-08 12:30:00', 'Night gaming session', 'PAY0014'),
('RENT0017', 2, '2025-07-09 07:00:00', '2025-07-09 12:00:00', '2025-07-09 07:10:00', '2025-07-09 11:50:00', 'completed', 'CUST0017', 'CONS0003', NULL, 10.00, '2025-07-08 22:30:00', 'Weekend morning gaming', 'PAY0015'),
('RENT0018', 2, '2025-07-10 11:00:00', '2025-07-10 13:00:00', '2025-07-10 11:05:00', '2025-07-10 12:55:00', 'completed', 'CUST0018', 'CONS0001', NULL, 4.00, '2025-07-10 02:30:00', 'Midday gaming break', 'PAY0016'),
('RENT0019', 3, '2025-07-11 14:00:00', '2025-07-11 17:00:00', '2025-07-11 14:10:00', '2025-07-11 16:50:00', 'completed', 'CUST0019', 'CONS0002', NULL, 6.00, '2025-07-11 05:30:00', 'Afternoon gaming group', 'PAY0017'),
('RENT0020', 4, '2025-07-12 18:00:00', '2025-07-12 22:00:00', '2025-07-12 18:15:00', '2025-07-12 21:45:00', 'completed', 'CUST0020', 'CONS0003', NULL, 8.00, '2025-07-12 09:30:00', 'Evening squad gaming', 'PAY0018'),
('RENT0021', 1, '2025-07-13 22:00:00', '2025-07-13 23:00:00', '2025-07-13 22:05:00', '2025-07-13 22:55:00', 'completed', 'CUST0021', 'CONS0001', NULL, 2.00, '2025-07-13 13:30:00', 'Late night solo gaming', 'PAY0019'),
('RENT0022', 2, '2025-07-14 06:00:00', '2025-07-14 12:00:00', '2025-07-14 06:10:00', '2025-07-14 11:50:00', 'completed', 'CUST0022', 'CONS0002', NULL, 12.00, '2025-07-13 21:30:00', 'Early morning gaming marathon', 'PAY0020'),
('RENT0023', 2, '2025-08-21 14:00:00', '2025-08-21 15:00:00', NULL, NULL, 'cancelled', 'CUST68a6a6', 'CONS0001', NULL, 4.00, '2025-08-21 04:53:33', NULL, NULL),
('RENT0024', 3, '2025-08-21 16:00:00', '2025-08-21 17:00:00', NULL, NULL, 'confirmed', 'CUST68a6a6', 'CONS0001', NULL, 6.00, '2025-08-21 06:02:16', NULL, 'PAY7796'),
('RENT0025', 2, '2025-08-22 16:10:00', '2025-08-22 17:10:00', NULL, NULL, 'confirmed', 'CUST68a6a6', 'CONS0001', NULL, 4.00, '2025-08-22 07:07:22', NULL, 'PAY0257'),
('RENT0026', 1, '2025-08-28 13:00:00', '2025-08-28 14:00:00', NULL, NULL, 'completed', 'CUST68a6a6', 'CONS0001', NULL, 2.00, '2025-08-28 03:58:59', NULL, 'PAY2120'),
('RENT0027', 3, '2025-08-28 14:00:00', '2025-08-28 15:00:00', NULL, NULL, 'confirmed', 'CUST68a6a6', 'CONS0001', NULL, 6.00, '2025-08-28 04:58:07', NULL, 'PAY3905');

--
-- Triggers `rentals`
--
DROP TRIGGER IF EXISTS `trg_auto_ban_customer`;
DELIMITER $$
CREATE TRIGGER `trg_auto_ban_customer` AFTER UPDATE ON `rentals` FOR EACH ROW BEGIN
    IF NEW.rental_status = 'cancelled' THEN
        IF (
            SELECT COUNT(*) FROM rentals
            WHERE customer_ID = NEW.customer_ID
              AND rental_status = 'cancelled'
              AND MONTH(booking_start_time) = MONTH(NOW())
              AND YEAR(booking_start_time) = YEAR(NOW())
        ) > 3 THEN
            UPDATE customers
            SET status = 'banned'
            WHERE customer_ID = NEW.customer_ID;
        END IF;
    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_prevent_overlapping_bookings`;
DELIMITER $$
CREATE TRIGGER `trg_prevent_overlapping_bookings` BEFORE INSERT ON `rentals` FOR EACH ROW BEGIN
    IF EXISTS (
        SELECT 1 FROM rentals
        WHERE console_ID = NEW.console_ID
          AND rental_status IN ('confirmed', 'in_progress')
          AND (
                (NEW.booking_start_time BETWEEN booking_start_time AND booking_end_time)
                OR
                (NEW.booking_end_time BETWEEN booking_start_time AND booking_end_time)
                OR
                (booking_start_time BETWEEN NEW.booking_start_time AND NEW.booking_end_time)
             )
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Console is already booked for the selected time period.';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `rental_games`
--

DROP TABLE IF EXISTS `rental_games`;
CREATE TABLE IF NOT EXISTS `rental_games` (
  `rental_ID` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `game_ID` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`rental_ID`,`game_ID`),
  KEY `rental_games_ibfk_1` (`game_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rental_games`
--

INSERT INTO `rental_games` (`rental_ID`, `game_ID`) VALUES
('RENT0002', 'GAME0001'),
('RENT0003', 'GAME0001'),
('RENT0007', 'GAME0001'),
('RENT0012', 'GAME0001'),
('RENT0017', 'GAME0001'),
('RENT0022', 'GAME0001'),
('RENT0026', 'GAME0001'),
('RENT0004', 'GAME0002'),
('RENT0008', 'GAME0002'),
('RENT0013', 'GAME0002'),
('RENT0018', 'GAME0002'),
('RENT0008', 'GAME0003'),
('RENT0018', 'GAME0003'),
('RENT0023', 'GAME0003'),
('RENT0006', 'GAME0004'),
('RENT0011', 'GAME0004'),
('RENT0016', 'GAME0004'),
('RENT0021', 'GAME0004'),
('RENT0007', 'GAME0005'),
('RENT0012', 'GAME0005'),
('RENT0017', 'GAME0005'),
('RENT0022', 'GAME0005'),
('RENT0004', 'GAME0006'),
('RENT0009', 'GAME0006'),
('RENT0013', 'GAME0006'),
('RENT0019', 'GAME0006'),
('RENT0003', 'GAME0007'),
('RENT0005', 'GAME0007'),
('RENT0010', 'GAME0007'),
('RENT0014', 'GAME0007'),
('RENT0020', 'GAME0007'),
('RENT0025', 'GAME0007'),
('RENT0009', 'GAME0008'),
('RENT0014', 'GAME0008'),
('RENT0019', 'GAME0008'),
('RENT0024', 'GAME0008'),
('RENT0027', 'GAME0008'),
('RENT0005', 'GAME0009'),
('RENT0010', 'GAME0009'),
('RENT0015', 'GAME0009'),
('RENT0020', 'GAME0009'),
('RENT0007', 'GAME0010'),
('RENT0012', 'GAME0010'),
('RENT0017', 'GAME0010'),
('RENT0022', 'GAME0010'),
('RENT0010', 'GAME0011'),
('RENT0015', 'GAME0011'),
('RENT0020', 'GAME0011');

-- --------------------------------------------------------

--
-- Table structure for table `staffs`
--

DROP TABLE IF EXISTS `staffs`;
CREATE TABLE IF NOT EXISTS `staffs` (
  `staff_ID` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `staff_username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `staff_full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `staff_email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `staff_password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `staff_profile_picture` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `staff_role` enum('staff','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'staff',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `staff_phone_no` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `failed_login_attempts` int NOT NULL DEFAULT '0',
  `last_failed_login` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','banned','pending_verification') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_verification',
  `verification_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`staff_ID`),
  UNIQUE KEY `staff_username` (`staff_username`),
  UNIQUE KEY `staff_email` (`staff_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staffs`
--

INSERT INTO `staffs` (`staff_ID`, `staff_username`, `staff_full_name`, `staff_email`, `staff_password_hash`, `staff_profile_picture`, `staff_role`, `is_active`, `staff_phone_no`, `created_at`, `failed_login_attempts`, `last_failed_login`, `remember_token`, `status`, `verification_token`) VALUES
('STAFF0001', 'Kir15', 'Mohammad Syakir Iman', 'maisaraphongsukwan@gmail.com', '$2y$10$A8BeNkceDuDvQ7.GV9d2.eKkY9yKybNI4NwPdr3bPmRmGy1gz52l2', 'staff_STAFF0001_1751437707.jpg', 'staff', 1, '0192448373', '2025-06-29 09:56:02', 0, NULL, 'c4f3659a9ebc46425c070ab0cd6bc5a6c731e196ec334ef2936f85accbab05a1', 'pending_verification', NULL),
('STAFF0002', 'Maisara ', 'Maisara Phongsukwan', 'rayn2309@gmail.com', '$2y$10$yHuk0M8D1hdAtHq34Vy7oOaRlwQdvnHr3GzExN8zF0Ck0rVLLBn62', 'uploads/profile_picture/staff_68a6be0b0d31c8.39001029.JPG', 'staff', 1, '0183567892', '2025-08-21 06:34:51', 5, '2025-08-22 07:08:58', NULL, 'active', NULL),
('STAFF0003', 'maisarasiput', 'Maisara Phongsukwan', 'maisarasiput2409@gmail.com', '$2y$10$nnXq2LeoQOK4l4R51DXIdeGNC6rsWXs0e9QeGtxmsozvvABchrXoK', NULL, 'staff', 1, '0182345679', '2025-08-22 07:10:17', 0, NULL, 'f1d6aa106e48f7f489f30051ef963ca26422ee638ab4f3ca979abd33e0023817', 'active', NULL),
('STAFF0004', '00623', 'rohana', 'rohanahashim@utem.edu.my', '$2y$10$3NNSZz/dCwIENhqQzPIgIOOJZjejrQRbUXGwWNvkhrxY6du5ps66.', NULL, 'staff', 1, '0136206007', '2025-08-28 04:19:22', 0, NULL, NULL, 'active', NULL),
('STAFF0005', '00067', 'Noor Rahman Bin Jalil', 'rahman@utem.edu.my', '$2y$10$KtDP1LXbaN.5cM5BdFGw5eNqJuuFYwRwiNwXjq7hKpfP2YgaRM4mS', NULL, 'staff', 1, '0149566411', '2025-08-28 04:33:14', 0, NULL, '9a640aa5cb95745ba116df5e996697e8c1acb4fb494fb2fd11ad35f9230d6d8d', 'active', NULL),
('STAFF0006', '00624', 'syukran', 'niksyukran@utem.edu.my', '$2y$10$vIji6/dpB64MzVgS9zqLPeSYgTF71FO3YKonjmpxqU/sCaUbZRLqi', NULL, 'staff', 1, '0126931818', '2025-08-28 05:11:20', 0, NULL, NULL, 'active', NULL),
('STAFF0007', '02200', 'mohamad tarmizi bin othman', 'mohamadtarmizi@utem.edu.my', '$2y$10$vW2yNWeiChOb3i2O0XUYEuse27Oyl.BlLsRB7oU3pA5WZRyczQasS', NULL, 'staff', 1, '0172116567', '2025-08-28 05:17:32', 0, NULL, NULL, 'active', NULL),
('STAFF0008', '01581', 'Shazley Bin Sahibuddin', 'shazley@utem.edu.my', '$2y$10$rgxxf/hdzkKzaOsh7ncLcusAo3f4bjOsilu6cRp7.dufSxBSfz2P2', NULL, 'staff', 1, '0176576565', '2025-08-28 05:22:54', 0, NULL, NULL, 'active', NULL);

--
-- Triggers `staffs`
--
DROP TRIGGER IF EXISTS `trg_prevent_delete_staff`;
DELIMITER $$
CREATE TRIGGER `trg_prevent_delete_staff` BEFORE DELETE ON `staffs` FOR EACH ROW BEGIN
    IF EXISTS (
        SELECT 1 FROM rentals
        WHERE staff_ID = OLD.staff_ID
          AND rental_status IN ('pending_payment', 'confirmed', 'in_progress')
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cannot delete staff with active rentals.';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `tags`
--

DROP TABLE IF EXISTS `tags`;
CREATE TABLE IF NOT EXISTS `tags` (
  `tag_ID` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tag_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`tag_ID`),
  UNIQUE KEY `tag_name` (`tag_name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tags`
--

INSERT INTO `tags` (`tag_ID`, `tag_name`) VALUES
('TAG0001', 'Racing'),
('TAG0002', 'Sports'),
('TAG0003', 'Action'),
('TAG0004', 'Adventure'),
('TAG0005', 'Fighting'),
('TAG0006', 'Party'),
('TAG0007', 'Co-op'),
('TAG0008', 'Platformer'),
('TAG0009', 'RPG'),
('TAG0010', 'Shooter'),
('TAG0011', 'Strategy'),
('TAG0012', 'Simulation'),
('TAG0013', 'Puzzle'),
('TAG0014', 'Horror'),
('TAG0015', 'Open World'),
('TAG0016', 'Multiplayer'),
('TAG0017', 'Singleplayer'),
('TAG0018', 'Family'),
('TAG0019', 'Fantasy'),
('TAG0020', 'Arcade');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `rentals`
--
ALTER TABLE `rentals`
  ADD CONSTRAINT `rentals_ibfk_1` FOREIGN KEY (`customer_ID`) REFERENCES `customers` (`customer_ID`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `rental_games`
--
ALTER TABLE `rental_games`
  ADD CONSTRAINT `rental_games_ibfk_1` FOREIGN KEY (`game_ID`) REFERENCES `games` (`game_ID`) ON DELETE RESTRICT ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
