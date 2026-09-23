-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 23, 2026 at 05:28 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sun-son-solar`
--

-- --------------------------------------------------------

--
-- Table structure for table `sunsonsolar`
--

CREATE TABLE `sunsonsolar` (
  `ID` int(11) NOT NULL,
  `Customer` varchar(25) NOT NULL,
  `Employee` varchar(25) NOT NULL,
  `First Name` varchar(25) NOT NULL,
  `Middle Name` varchar(25) NOT NULL,
  `Last Name` int(25) NOT NULL,
  `Birthdate` int(25) NOT NULL,
  `Gender` varchar(25) NOT NULL,
  `Email` int(25) NOT NULL,
  `Phone Number` int(25) NOT NULL,
  `Address` varchar(25) NOT NULL,
  `Username` varchar(25) NOT NULL,
  `Password` varchar(25) NOT NULL,
  `Confirm Password` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
