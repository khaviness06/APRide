-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jan 11, 2026 at 04:46 AM
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
-- Database: `apride`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

DROP TABLE IF EXISTS `admin`;
CREATE TABLE IF NOT EXISTS `admin` (
  `admin_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`admin_id`, `user_id`) VALUES
(1, 1),
(2, 2);

-- --------------------------------------------------------

--
-- Table structure for table `booking`
--

DROP TABLE IF EXISTS `booking`;
CREATE TABLE IF NOT EXISTS `booking` (
  `booking_id` int NOT NULL AUTO_INCREMENT,
  `ride_id` int NOT NULL,
  `customer_id` int NOT NULL,
  `status` varchar(100) NOT NULL,
  PRIMARY KEY (`booking_id`),
  KEY `ride_id` (`ride_id`),
  KEY `customer_id` (`customer_id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `booking`
--

INSERT INTO `booking` (`booking_id`, `ride_id`, `customer_id`, `status`) VALUES
(1, 1, 3, 'Confirmed'),
(2, 2, 4, 'Completed'),
(3, 3, 3, 'Completed'),
(4, 4, 3, 'Completed'),
(5, 6, 3, 'Completed'),
(6, 5, 3, 'Completed');

-- --------------------------------------------------------

--
-- Table structure for table `co2_report`
--

DROP TABLE IF EXISTS `co2_report`;
CREATE TABLE IF NOT EXISTS `co2_report` (
  `C02_report_id` int NOT NULL AUTO_INCREMENT,
  `tracker_id` int NOT NULL,
  `admin_id` int NOT NULL,
  `CO2_report_date` date NOT NULL,
  PRIMARY KEY (`C02_report_id`),
  KEY `tracker_id` (`tracker_id`),
  KEY `admin_id` (`admin_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `co2_report`
--

INSERT INTO `co2_report` (`C02_report_id`, `tracker_id`, `admin_id`, `CO2_report_date`) VALUES
(1, 1, 1, '2026-01-11'),
(2, 2, 2, '2026-01-11');

-- --------------------------------------------------------

--
-- Table structure for table `co2_tracker`
--

DROP TABLE IF EXISTS `co2_tracker`;
CREATE TABLE IF NOT EXISTS `co2_tracker` (
  `tracker_id` int NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
  `CO2_emitted` decimal(10,4) NOT NULL COMMENT 'C02 amount in kg/unit',
  `ride_id` int NOT NULL,
  PRIMARY KEY (`tracker_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `co2_tracker`
--

INSERT INTO `co2_tracker` (`tracker_id`, `CO2_emitted`, `ride_id`) VALUES
(1, 0.8500, 0),
(2, 1.3200, 0);

-- --------------------------------------------------------

--
-- Table structure for table `customer`
--

DROP TABLE IF EXISTS `customer`;
CREATE TABLE IF NOT EXISTS `customer` (
  `customer_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `points_balance` int NOT NULL,
  `membership_status` varchar(100) DEFAULT NULL,
  `wallet` int NOT NULL,
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `customer`
--

INSERT INTO `customer` (`customer_id`, `user_id`, `points_balance`, `membership_status`, `wallet`) VALUES
(1, 3, 550, 'Gold', 0),
(2, 4, 120, 'Silver', 0),
(3, 7, 50, 'Silver', 1042);

-- --------------------------------------------------------

--
-- Table structure for table `driver`
--

DROP TABLE IF EXISTS `driver`;
CREATE TABLE IF NOT EXISTS `driver` (
  `driver_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `vehicle_type` varchar(100) NOT NULL,
  `license_plate` varchar(20) NOT NULL,
  `max_passengers` int NOT NULL,
  `status` varchar(100) NOT NULL,
  PRIMARY KEY (`driver_id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `license_plate` (`license_plate`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `driver`
--

INSERT INTO `driver` (`driver_id`, `user_id`, `vehicle_type`, `license_plate`, `max_passengers`, `status`) VALUES
(1, 5, 'Sedan', 'ABC-123', 4, '0'),
(2, 6, 'SUV', 'DEF-456', 6, '0'),
(3, 9, 'Sedan', 'EV2426', 4, '0'),
(4, 10, 'SUV', 'EV2420', 5, 'Approved');

-- --------------------------------------------------------

--
-- Table structure for table `points`
--

DROP TABLE IF EXISTS `points`;
CREATE TABLE IF NOT EXISTS `points` (
  `points_id` int NOT NULL AUTO_INCREMENT,
  `customer_id` int NOT NULL,
  `ride_id` int NOT NULL,
  `points_earned` int NOT NULL,
  `redeemed` tinyint(1) NOT NULL,
  PRIMARY KEY (`points_id`),
  KEY `customer_id` (`customer_id`),
  KEY `ride_id` (`ride_id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `points`
--

INSERT INTO `points` (`points_id`, `customer_id`, `ride_id`, `points_earned`, `redeemed`) VALUES
(1, 3, 1, 15, 0),
(2, 4, 2, 40, 0),
(3, 3, 3, 50, 0),
(4, 3, 4, 50, 0),
(5, 3, 6, 50, 0),
(6, 3, 5, 50, 0);

-- --------------------------------------------------------

--
-- Table structure for table `redemption`
--

DROP TABLE IF EXISTS `redemption`;
CREATE TABLE IF NOT EXISTS `redemption` (
  `redemption_id` int NOT NULL AUTO_INCREMENT,
  `customer_id` int NOT NULL,
  `reward_id` int NOT NULL,
  `date_redeemed` datetime NOT NULL,
  PRIMARY KEY (`redemption_id`),
  KEY `customer_id` (`customer_id`),
  KEY `reward_id` (`reward_id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `redemption`
--

INSERT INTO `redemption` (`redemption_id`, `customer_id`, `reward_id`, `date_redeemed`) VALUES
(1, 3, 1, '2025-12-15 14:00:00'),
(2, 4, 2, '2025-12-16 09:15:00'),
(3, 3, 2, '2026-01-10 11:41:44');

-- --------------------------------------------------------

--
-- Table structure for table `reward`
--

DROP TABLE IF EXISTS `reward`;
CREATE TABLE IF NOT EXISTS `reward` (
  `reward_id` int NOT NULL AUTO_INCREMENT,
  `product_title` varchar(100) NOT NULL,
  `description` int DEFAULT NULL,
  `points_required` int NOT NULL,
  PRIMARY KEY (`reward_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `reward`
--

INSERT INTO `reward` (`reward_id`, `product_title`, `description`, `points_required`) VALUES
(1, '$10 Voucher', 0, 500),
(2, 'Coffee Coupon', 0, 100);

-- --------------------------------------------------------

--
-- Table structure for table `ride`
--

DROP TABLE IF EXISTS `ride`;
CREATE TABLE IF NOT EXISTS `ride` (
  `ride_id` int NOT NULL AUTO_INCREMENT,
  `driver_id` int NOT NULL,
  `destination` varchar(100) NOT NULL,
  `date_time` datetime NOT NULL,
  `available_seats` int NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `distance_km` decimal(10,2) NOT NULL,
  PRIMARY KEY (`ride_id`),
  KEY `driver_id` (`driver_id`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `ride`
--

INSERT INTO `ride` (`ride_id`, `driver_id`, `destination`, `date_time`, `available_seats`, `price`, `distance_km`) VALUES
(1, 1, 'Downtown HQ', '2026-01-10 08:30:00', 3, 15.50, 8.20),
(2, 2, 'Airport Terminal 1', '2026-01-10 10:00:00', 5, 45.00, 25.10),
(3, 4, 'LRT Bukit Jalil → KL Sentral', '2026-08-10 10:00:00', 0, 13.00, 10.00),
(4, 4, 'Pavilion Bukit Jalil → APU Main Campus', '2026-02-10 10:00:00', 0, 15.00, 10.00),
(5, 4, 'Pavilion Bukit Jalil → Technology Park Malaysia', '2026-12-02 10:00:00', 0, 15.00, 10.00),
(6, 4, 'APU Main Campus → KL Sentral', '2026-10-12 10:20:00', 0, 15.00, 12.00),
(7, 4, 'APU Main Campus → LRT Bukit Jalil', '2026-01-11 15:45:00', 4, 5.00, 3.00);

-- --------------------------------------------------------

--
-- Table structure for table `ride_history`
--

DROP TABLE IF EXISTS `ride_history`;
CREATE TABLE IF NOT EXISTS `ride_history` (
  `history_id` int NOT NULL AUTO_INCREMENT,
  `ride_id` int NOT NULL,
  `admin_id` int NOT NULL,
  PRIMARY KEY (`history_id`),
  KEY `ride_id` (`ride_id`),
  KEY `admin_id` (`admin_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `ride_history`
--

INSERT INTO `ride_history` (`history_id`, `ride_id`, `admin_id`) VALUES
(1, 1, 1),
(2, 2, 2);

-- --------------------------------------------------------

--
-- Table structure for table `transaction_report`
--

DROP TABLE IF EXISTS `transaction_report`;
CREATE TABLE IF NOT EXISTS `transaction_report` (
  `transaction_id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `admin_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_status` varchar(50) NOT NULL,
  PRIMARY KEY (`transaction_id`),
  UNIQUE KEY `booking_id` (`booking_id`),
  KEY `admin_id` (`admin_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `transaction_report`
--

INSERT INTO `transaction_report` (`transaction_id`, `booking_id`, `admin_id`, `amount`, `payment_status`) VALUES
(1, 2, 1, 45.00, 'Paid'),
(2, 1, 2, 15.50, 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
CREATE TABLE IF NOT EXISTS `user` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `TP_Number` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `e_mail` varchar(100) DEFAULT NULL,
  `role` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `TP_Number` (`TP_Number`),
  UNIQUE KEY `e_mail` (`e_mail`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `TP_Number`, `password`, `e_mail`, `role`, `name`) VALUES
(1, 'jdoe', 'admin', 'jdoe@mail.com', '', ''),
(2, 'asmith', 'admin', 'asmith@mail.com', '', ''),
(3, 'mcust', 'customer', 'mcust@mail.com', '', ''),
(4, 'bcust', 'customer', 'bcust@mail.com', '', ''),
(5, 'tdrive', 'driver', 'tdrive@mail.com', '', ''),
(6, 'sdrive', 'driver', 'sdrive@mail.com', '', ''),
(7, 'TP081989', '123456', NULL, 'customer', ''),
(8, 'TP082427', '1234', NULL, 'Driver', ''),
(9, 'TP083435', '1234', NULL, 'Driver', ''),
(10, 'TP081987', '1234', NULL, 'Driver', '');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
