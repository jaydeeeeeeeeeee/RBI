-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 11, 2026 at 04:52 AM
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
-- Database: `projectrbi`
--

-- --------------------------------------------------------

--
-- Table structure for table `access_log`
--

CREATE TABLE `access_log` (
  `id` int(11) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `detail` text DEFAULT NULL,
  `performed_by` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `role` varchar(50) NOT NULL DEFAULT 'secretary'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `access_log`
--

INSERT INTO `access_log` (`id`, `event_type`, `detail`, `performed_by`, `ip_address`, `created_at`, `role`) VALUES
(1, 'LOGIN', 'User logged in', 'admin', '::1', '2026-04-27 21:41:52', 'secretary'),
(2, 'LOGIN', 'User logged in', 'admin', '::1', '2026-04-27 21:44:09', 'secretary'),
(3, 'LOGIN', 'admin logged in', 'admin', '::1', '2026-04-29 11:15:18', 'secretary'),
(4, 'LOGIN', 'admin logged in', 'admin', '::1', '2026-04-29 11:15:59', 'secretary'),
(5, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-04-29 11:16:12', 'secretary'),
(6, 'LOGIN', 'admin logged in', 'admin', '::1', '2026-04-29 11:16:32', 'secretary'),
(7, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-04-29 11:17:06', 'secretary'),
(8, 'LOGIN', 'admin logged in', 'admin', '::1', '2026-04-29 11:18:45', 'secretary'),
(9, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-04-29 11:21:05', 'secretary'),
(10, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-04-29 13:40:35', 'captain'),
(11, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-04-30 13:41:03', 'captain'),
(12, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-04-30 13:41:32', 'secretary'),
(13, 'LOGIN', 'Guest Viewer logged in', 'guest', '::1', '2026-04-30 13:41:52', 'guest'),
(14, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-04-30 13:51:40', 'captain'),
(15, 'LOGIN', 'Jose Los logged in', 'Joseph', '::1', '2026-04-30 14:21:46', 'guest'),
(16, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-04-30 14:22:06', 'secretary'),
(17, 'UNLOCK', 'Secretary verified password', 'secretary', '::1', '2026-04-30 14:46:22', 'secretary'),
(18, 'UNLOCK', 'Secretary verified password', 'secretary', '::1', '2026-04-30 14:46:37', 'secretary'),
(19, 'UNLOCK', 'Secretary verified password', 'secretary', '::1', '2026-04-30 14:48:47', 'secretary'),
(20, 'UNLOCK', 'Secretary verified password', 'secretary', '::1', '2026-04-30 15:20:22', 'secretary'),
(21, 'UNLOCK', 'Secretary verified password', 'secretary', '::1', '2026-04-30 15:58:18', 'secretary'),
(22, 'UNLOCK', 'Secretary verified password', 'secretary', '::1', '2026-04-30 15:58:49', 'secretary'),
(23, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-04-30 16:09:19', 'captain'),
(24, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-04-30 16:23:17', 'secretary'),
(25, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-04-30 16:42:53', 'captain'),
(26, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-01 15:12:19', 'captain'),
(27, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-05-01 15:12:59', 'secretary'),
(28, 'LOGIN', 'Guest Viewer logged in', 'guest', '::1', '2026-05-03 22:34:22', 'guest'),
(29, 'UNLOCK', 'Secretary verified password', 'guest', '::1', '2026-05-03 22:37:21', 'secretary'),
(30, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-03 22:39:57', 'captain'),
(31, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-05-03 22:42:03', 'secretary'),
(32, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-05-03 22:44:59', 'secretary'),
(33, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-03 22:45:03', 'captain'),
(34, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-05-03 22:46:04', 'secretary'),
(35, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-05-03 22:54:33', 'secretary'),
(36, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-05-03 22:56:10', 'secretary'),
(37, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-05-03 22:58:28', 'secretary'),
(38, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-03 23:05:00', 'captain'),
(39, 'LOGIN', 'Guest Viewer logged in', 'guest', '::1', '2026-05-03 23:07:17', 'guest'),
(40, 'UNLOCK', 'Secretary verified password', 'guest', '::1', '2026-05-03 23:07:32', 'secretary'),
(41, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-03 23:22:04', 'captain'),
(42, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-05-03 23:28:12', 'secretary'),
(43, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-04 16:43:41', 'captain'),
(44, 'LOGIN', 'Guest Viewer logged in', 'guest', '::1', '2026-05-04 16:44:02', 'guest'),
(45, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-04 16:46:20', 'captain'),
(46, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-05-04 17:00:05', 'secretary'),
(47, 'LOGIN', 'Guest Viewer logged in', 'guest', '::1', '2026-05-04 17:10:23', 'guest'),
(48, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-05-04 17:10:57', 'secretary'),
(49, 'UNLOCK', 'Secretary verified password', 'secretary', '::1', '2026-05-04 17:11:09', 'secretary'),
(50, 'UNLOCK', 'Secretary verified password', 'secretary', '::1', '2026-05-04 17:14:06', 'secretary'),
(51, 'UNLOCK', 'Secretary verified password', 'secretary', '::1', '2026-05-04 17:14:20', 'secretary'),
(52, 'LOGIN', 'Guest Viewer logged in', 'guest', '::1', '2026-05-04 17:24:05', 'guest'),
(53, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-04 17:28:48', 'captain'),
(54, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-05-04 17:31:00', 'secretary'),
(55, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-05-06 08:02:55', 'secretary'),
(56, 'LOGIN', 'Guest Viewer logged in', 'guest', '::1', '2026-05-06 08:05:20', 'guest'),
(57, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-05-06 08:47:17', 'secretary'),
(58, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-06 08:57:11', 'captain'),
(59, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-06 10:12:03', 'captain'),
(60, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-05-06 10:14:41', 'secretary'),
(61, 'LOGIN', 'Guest Viewer logged in', 'guest', '::1', '2026-05-06 10:20:05', 'guest'),
(62, 'LOGIN', 'Guest Viewer logged in', 'guest', '::1', '2026-05-06 12:28:40', 'guest'),
(63, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-06 12:30:15', 'captain'),
(64, 'LOGIN', 'Guest Viewer logged in', 'guest', '::1', '2026-05-06 12:30:59', 'guest'),
(65, 'LOGIN', 'Guest Viewer logged in', 'guest', '::1', '2026-05-06 12:31:10', 'guest'),
(66, 'LOGIN', 'Guest Viewer logged in', 'guest', '::1', '2026-05-06 12:31:48', 'guest'),
(67, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-06 12:35:31', 'captain'),
(68, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-05-06 12:39:09', 'secretary'),
(69, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-05-10 17:37:23', 'secretary'),
(70, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-10 17:37:41', 'captain'),
(71, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-10 17:58:35', 'captain'),
(72, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-10 18:22:19', 'captain'),
(73, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-12 10:26:46', 'captain'),
(74, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-05-12 10:27:29', 'secretary'),
(75, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-12 14:20:31', 'captain'),
(76, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-05-13 10:56:00', 'secretary'),
(77, 'UNLOCK', 'Secretary verified password', 'secretary', '::1', '2026-05-13 10:56:25', 'secretary'),
(78, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-05-17 21:21:15', 'secretary'),
(79, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-05-19 22:04:49', 'secretary'),
(80, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-05-19 22:08:28', 'secretary'),
(81, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-19 22:17:59', 'captain'),
(82, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-19 22:18:21', 'captain'),
(83, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-20 10:38:17', 'captain'),
(84, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-20 19:50:56', 'captain'),
(85, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-20 21:28:33', 'captain'),
(86, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-05-20 21:42:34', 'secretary'),
(87, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-05-20 21:49:22', 'secretary'),
(88, 'UNLOCK', 'Secretary verified password', 'secretary', '::1', '2026-05-20 21:54:50', 'secretary'),
(89, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-20 22:20:04', 'captain'),
(90, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-21 10:17:26', 'captain'),
(91, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-05-21 10:57:20', 'secretary'),
(92, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-21 13:26:37', 'captain'),
(93, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-05-21 13:31:10', 'secretary'),
(94, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-21 15:26:50', 'captain'),
(95, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-05-21 15:36:22', 'secretary'),
(96, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-21 16:06:45', 'captain'),
(97, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-05-21 16:15:23', 'secretary'),
(98, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-21 23:33:53', 'captain'),
(99, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-05-21 23:52:36', 'secretary'),
(100, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-05-22 00:04:05', 'secretary'),
(101, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-05-22 00:38:31', 'secretary'),
(102, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-05-22 01:06:01', 'secretary'),
(103, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-05-22 01:35:17', 'secretary'),
(104, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-22 10:21:49', 'captain'),
(105, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-05-22 10:22:05', 'secretary'),
(106, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-22 14:52:58', 'captain'),
(107, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-05-22 15:06:33', 'secretary'),
(108, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-05-22 15:07:09', 'secretary'),
(109, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-05-22 15:30:11', 'secretary'),
(110, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-05-22 15:40:01', 'secretary'),
(111, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-05-22 15:52:09', 'secretary'),
(112, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-22 16:37:20', 'captain'),
(113, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-22 16:48:09', 'captain'),
(114, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-05-22 16:50:36', 'secretary'),
(115, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-22 17:45:35', 'captain'),
(116, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-23 06:28:01', 'captain'),
(117, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-23 09:59:44', 'captain'),
(118, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-24 13:19:56', 'captain'),
(119, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-05-24 13:20:53', 'secretary'),
(120, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-05-24 16:52:44', 'secretary'),
(121, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-05-24 17:46:31', 'secretary'),
(122, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-05-24 18:26:04', 'secretary'),
(123, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-05-24 18:40:16', 'secretary'),
(124, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-05-24 20:11:18', 'secretary'),
(125, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-05-24 20:47:12', 'secretary'),
(126, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-05-24 21:25:02', 'secretary'),
(127, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-25 21:21:15', 'captain'),
(128, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-05-29 13:57:44', 'secretary'),
(129, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-30 19:50:12', 'captain'),
(130, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-30 20:17:08', 'captain'),
(131, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-30 20:57:31', 'captain'),
(132, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-05-31 17:44:34', 'captain'),
(133, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-05-31 17:55:47', 'secretary'),
(134, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-06-01 09:51:35', 'captain'),
(135, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-06-01 09:57:59', 'secretary'),
(136, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-06-01 11:28:29', 'secretary'),
(137, 'UNLOCK', 'Secretary verified password', 'secretary', '::1', '2026-06-01 11:33:09', 'secretary'),
(138, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-06-01 11:50:53', 'captain'),
(139, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-06-01 11:52:27', 'secretary'),
(140, 'UNLOCK', 'Secretary verified password', 'secretary', '::1', '2026-06-01 11:53:07', 'secretary'),
(141, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-06-01 12:27:57', 'captain'),
(142, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-06-01 22:33:14', 'secretary'),
(143, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-06-01 23:02:01', 'secretary'),
(144, 'UNLOCK', 'Secretary verified password', 'secretary', '::1', '2026-06-01 23:11:14', 'secretary'),
(145, 'UNLOCK', 'Secretary verified password', 'secretary', '::1', '2026-06-01 23:11:55', 'secretary'),
(146, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-06-01 23:54:06', 'captain'),
(147, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-06-01 23:58:40', 'secretary'),
(148, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-06-01 23:59:29', 'secretary'),
(149, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-06-02 14:01:33', 'captain'),
(150, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-06-02 14:06:45', 'captain'),
(151, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-06-02 14:09:56', 'secretary'),
(152, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-06-02 14:11:00', 'secretary'),
(153, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-06-02 20:02:37', 'captain'),
(154, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-06-02 23:55:35', 'captain'),
(155, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-06-03 00:10:55', 'captain'),
(156, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-06-03 00:41:55', 'secretary'),
(157, 'UNLOCK', 'Secretary verified password', 'secretary', '::1', '2026-06-03 00:47:42', 'secretary'),
(158, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-06-03 00:49:40', 'secretary'),
(159, 'UNLOCK', 'Secretary verified password', 'secretary', '::1', '2026-06-03 00:51:56', 'secretary'),
(160, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-06-03 08:45:18', 'secretary'),
(161, 'UNLOCK', 'Secretary verified password', 'secretary', '::1', '2026-06-03 08:47:24', 'secretary'),
(162, 'UNLOCK', 'Secretary verified password', 'secretary', '::1', '2026-06-03 08:48:40', 'secretary'),
(163, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-06-03 09:03:27', 'secretary'),
(164, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-06-03 09:13:35', 'secretary'),
(165, 'UNLOCK', 'Secretary verified password', 'secretary', '::1', '2026-06-03 09:16:37', 'secretary'),
(166, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-06-03 09:36:46', 'secretary'),
(167, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-06-03 09:37:29', 'captain'),
(168, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-06-03 16:07:41', 'captain'),
(169, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-06-03 16:16:19', 'secretary'),
(170, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-06-06 16:57:51', 'secretary'),
(171, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-06-07 22:39:55', 'captain'),
(172, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-06-07 22:41:02', 'secretary'),
(173, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-06-08 13:06:01', 'secretary'),
(174, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-06-08 13:06:21', 'captain'),
(175, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-06-08 17:19:39', 'secretary'),
(176, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-06-08 17:32:10', 'captain'),
(177, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-06-08 17:44:04', 'captain'),
(178, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-06-08 17:44:25', 'secretary'),
(179, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-06-08 17:45:34', 'secretary'),
(180, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-06-09 17:28:39', 'captain'),
(181, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-06-09 21:49:03', 'secretary'),
(182, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-06-09 21:49:55', 'captain'),
(183, 'UNLOCK', 'Secretary verified password', 'admin', '::1', '2026-06-09 21:50:24', 'secretary'),
(184, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-06-09 21:51:06', 'secretary'),
(185, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-06-09 21:51:32', 'captain'),
(186, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-06-09 21:53:58', 'secretary'),
(187, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-06-09 22:23:49', 'captain'),
(188, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-06-10 12:01:22', 'secretary'),
(189, 'SA_CLEAR_LOCKS', 'All login lockouts cleared', 'superadmin', '::1', '2026-06-10 14:43:02', 'secretary'),
(190, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-06-10 14:43:24', 'secretary'),
(191, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-06-10 15:37:35', 'secretary'),
(192, 'LOGIN', 'Barangay Captain logged in', 'admin', '::1', '2026-06-10 22:51:33', 'captain'),
(193, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-06-10 23:13:16', 'secretary'),
(194, 'UNLOCK', 'Secretary verified password', 'secretary', '::1', '2026-06-10 23:33:00', 'secretary'),
(195, 'UNLOCK', 'Secretary verified password', 'secretary', '::1', '2026-06-10 23:33:11', 'secretary'),
(196, 'UNLOCK', 'Secretary verified password', 'secretary', '::1', '2026-06-10 23:33:40', 'secretary'),
(197, 'UNLOCK', 'Secretary verified password', 'secretary', '::1', '2026-06-10 23:36:25', 'secretary'),
(198, 'UNLOCK', 'Secretary verified password', 'secretary', '::1', '2026-06-10 23:39:17', 'secretary'),
(199, 'LOGIN', 'Barangay Secretary logged in', 'secretary', '::1', '2026-06-11 08:11:26', 'secretary');

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `account_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','secretary','staff') DEFAULT 'secretary'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`account_id`, `username`, `password`, `role`) VALUES
(1, 'admin01', 'hashed_password1', 'admin'),
(2, 'secretary01', 'hashed_password2', 'secretary'),
(3, 'staff01', 'hashed_password3', 'staff');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `role` enum('captain','secretary') NOT NULL DEFAULT 'secretary',
  `full_name` varchar(150) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `created_at`, `role`, `full_name`, `is_active`, `expires_at`) VALUES
(1, 'admin', '$2y$10$E6jt.dPJLiSMyEkGopINyeb0xKy0VwilMdQ47jjgjS3O8u7Q.zQ5a', '2026-04-27 21:39:58', 'captain', 'Barangay Captain', 1, NULL),
(2, 'secretary', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-04-29 11:20:37', 'secretary', 'Barangay Secretary', 1, NULL),
(10, 'Chairman', '$2y$10$IJaO/pPJXDlufKjwbey0WeH01KWqsPDXGqwPUZi2/kc9KWzqVsJt6', '2026-06-11 09:54:57', 'captain', 'Marvin Santiago', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `action` enum('CREATE','UPDATE','DELETE') NOT NULL,
  `table_name` varchar(100) DEFAULT 'residents',
  `record_id` int(11) NOT NULL,
  `resident_name` varchar(255) DEFAULT NULL,
  `field_changed` varchar(100) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `performed_by` varchar(100) NOT NULL,
  `performed_at` datetime DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `action`, `table_name`, `record_id`, `resident_name`, `field_changed`, `old_value`, `new_value`, `performed_by`, `performed_at`, `ip_address`, `notes`) VALUES
(1, 'CREATE', 'residents', 1, 'Jerome Dedase', NULL, NULL, NULL, 'secretary', '2026-04-30 14:46:22', '::1', 'New resident registered'),
(2, 'CREATE', 'residents', 2, 'Jerome Dedase', NULL, NULL, NULL, 'secretary', '2026-04-30 15:58:18', '::1', 'New resident registered'),
(3, 'UPDATE', 'residents', 2, 'Jerome Dedase', NULL, NULL, NULL, 'secretary', '2026-04-30 15:59:18', '::1', 'Resident record edited'),
(4, 'CREATE', 'residents', 3, 'Jose Chan', NULL, NULL, NULL, 'admin', '2026-05-03 22:54:33', '::1', 'New resident registered'),
(5, 'CREATE', 'residents', 4, 'Eprol Santi', NULL, NULL, NULL, 'secretary', '2026-05-04 17:14:06', '::1', 'New resident registered'),
(6, 'UPDATE', 'residents', 4, 'Eprol Santi', NULL, NULL, NULL, 'secretary', '2026-05-04 17:14:26', '::1', 'Resident record edited'),
(7, 'CREATE', 'residents', 5, 'Jamo Sin', NULL, NULL, NULL, 'admin', '2026-05-22 15:06:33', '::1', 'New resident registered'),
(8, 'UPDATE', 'residents', 5, 'Jamo Sin', NULL, NULL, NULL, 'admin', '2026-05-22 15:07:19', '::1', 'Resident record edited'),
(9, 'UPDATE', 'residents', 5, 'Jamo Sin', NULL, NULL, NULL, 'admin', '2026-05-22 15:18:41', '::1', 'Resident record edited'),
(10, 'UPDATE', 'residents', 5, 'Jamo Sin', NULL, NULL, NULL, 'admin', '2026-05-22 15:19:41', '::1', 'Resident record edited'),
(11, 'CREATE', 'residents', 6, 'Ancuhfaw Akcjnwaiufa', NULL, NULL, NULL, 'admin', '2026-05-22 15:30:11', '::1', 'New resident registered'),
(12, 'CREATE', 'residents', 7, 'Fuhsefaofiwa Ckncwufwa', NULL, NULL, NULL, 'admin', '2026-05-22 15:40:01', '::1', 'New resident registered'),
(13, 'CREATE', 'residents', 8, 'akcnawiuvh asjcnauv', NULL, NULL, NULL, 'admin', '2026-05-24 20:47:13', '::1', 'Bulk registration'),
(14, 'CREATE', 'residents', 9, 'ajjnahuvi ajnaevhua', NULL, NULL, NULL, 'admin', '2026-05-24 20:47:13', '::1', 'Bulk registration'),
(15, 'CREATE', 'residents', 10, 'anua7ea ajcavhue', NULL, NULL, NULL, 'admin', '2026-05-24 20:47:13', '::1', 'Bulk registration'),
(16, 'CREATE', 'residents', 11, 'Clude Buban', NULL, NULL, NULL, 'admin', '2026-06-01 09:57:59', '::1', 'New resident registered'),
(17, 'UPDATE', 'residents', 8, 'akcnawiuvh asjcnauv', NULL, NULL, NULL, 'secretary', '2026-06-01 23:12:07', '::1', 'Resident record edited'),
(18, 'CREATE', 'residents', 12, 'Jomar Yin', NULL, NULL, NULL, 'admin', '2026-06-01 23:58:41', '::1', 'New resident registered'),
(19, 'UPDATE', 'residents', 11, 'Clude Buban', NULL, NULL, NULL, 'admin', '2026-06-01 23:59:48', '::1', 'Resident record edited'),
(20, 'CREATE', 'residents', 13, 'Kjnskhviah Akjnckwa', NULL, NULL, NULL, 'admin', '2026-06-02 14:09:56', '::1', 'New resident registered'),
(21, 'UPDATE', 'residents', 5, 'Jamo Sin', NULL, NULL, NULL, 'admin', '2026-06-02 14:11:38', '::1', 'Resident record edited'),
(22, 'UPDATE', 'residents', 6, 'Justin Akcjnwaiufa', NULL, NULL, NULL, 'secretary', '2026-06-03 08:48:48', '::1', 'Resident record edited'),
(23, 'UPDATE', 'residents', 8, 'Alex Manoban', NULL, NULL, NULL, 'secretary', '2026-06-10 23:33:29', '::1', 'Resident record edited'),
(24, 'UPDATE', 'residents', 5, 'Jamo Sin', NULL, NULL, NULL, 'secretary', '2026-06-10 23:36:49', '::1', 'Resident record edited');

-- --------------------------------------------------------

--
-- Table structure for table `barangay_officials`
--

CREATE TABLE `barangay_officials` (
  `id` int(11) NOT NULL,
  `role_key` varchar(60) NOT NULL,
  `role_label` varchar(120) NOT NULL,
  `full_name` varchar(200) NOT NULL DEFAULT '',
  `title` varchar(200) NOT NULL DEFAULT '',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barangay_officials`
--

INSERT INTO `barangay_officials` (`id`, `role_key`, `role_label`, `full_name`, `title`, `sort_order`, `updated_at`) VALUES
(1, 'captain', 'Punong Barangay', 'JUAN DELA CRUZ', 'Punong Barangay', 1, '2026-06-02 00:05:55'),
(2, 'secretary', 'Barangay Secretary', 'GREGORIA DEL PILAR', 'Barangay Secretary', 2, '2026-05-31 19:01:04');

-- --------------------------------------------------------

--
-- Table structure for table `blotter`
--

CREATE TABLE `blotter` (
  `id` int(11) NOT NULL,
  `case_no` varchar(30) NOT NULL,
  `complainant` varchar(200) NOT NULL,
  `respondent` varchar(200) NOT NULL,
  `incident_type` enum('Noise Complaint','Physical Altercation','Property Dispute','Theft','Threat','Domestic Dispute','Trespassing','Others') DEFAULT 'Others',
  `other_type` varchar(100) DEFAULT NULL,
  `incident_date` date NOT NULL,
  `incident_location` varchar(255) DEFAULT NULL,
  `narrative` text DEFAULT NULL,
  `status` enum('Filed','Summons Issued','Notice of Hearing','Under Mediation','Settled','Escalated','Dismissed') DEFAULT 'Filed',
  `action_taken` text DEFAULT NULL,
  `filed_by` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blotter_cases`
--

CREATE TABLE `blotter_cases` (
  `id` int(11) NOT NULL,
  `case_id` varchar(50) NOT NULL,
  `complainant_first` varchar(100) NOT NULL,
  `complainant_middle` varchar(100) DEFAULT NULL,
  `complainant_last` varchar(100) NOT NULL,
  `complainant_age` int(11) DEFAULT NULL,
  `complainant_address` varchar(255) DEFAULT NULL,
  `respondent_first` varchar(100) NOT NULL,
  `respondent_middle` varchar(100) DEFAULT NULL,
  `respondent_last` varchar(100) NOT NULL,
  `respondent_address` varchar(255) DEFAULT NULL,
  `when_incident` date DEFAULT NULL,
  `where_incident` varchar(255) DEFAULT NULL,
  `brief_case` text DEFAULT NULL,
  `disposition` varchar(50) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `summons_done` tinyint(1) DEFAULT 0,
  `notice_done` tinyint(1) DEFAULT 0,
  `mediation_done` tinyint(1) DEFAULT 0,
  `hearing_date` date DEFAULT NULL,
  `mediation_outcome` varchar(100) DEFAULT NULL,
  `mediation_reason` text DEFAULT NULL,
  `last_updated_by` varchar(150) DEFAULT NULL,
  `last_updated_at` datetime DEFAULT NULL,
  `created_by` varchar(150) DEFAULT NULL,
  `last_exported_by` varchar(150) DEFAULT NULL,
  `last_exported_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blotter_cases`
--

INSERT INTO `blotter_cases` (`id`, `case_id`, `complainant_first`, `complainant_middle`, `complainant_last`, `complainant_age`, `complainant_address`, `respondent_first`, `respondent_middle`, `respondent_last`, `respondent_address`, `when_incident`, `where_incident`, `brief_case`, `disposition`, `status`, `summons_done`, `notice_done`, `mediation_done`, `hearing_date`, `mediation_outcome`, `mediation_reason`, `last_updated_by`, `last_updated_at`, `created_by`, `last_exported_by`, `last_exported_at`, `created_at`, `updated_at`) VALUES
(1, 'BRGY 410 - 0001-2026', 'Mark', '', 'Uy', 100, 'afakjnaekfiusefhe', 'Shellsi', '', 'Hmosin', 'sjfcnekanvie', '2026-03-01', 'yahab', 'akvnehfa', 'Record', 'Ongoing', 1, 1, 1, NULL, NULL, NULL, NULL, NULL, 'Barangay Secretary', 'Barangay Secretary', '2026-05-22 13:45:34', '2026-05-21 22:49:25', '2026-05-22 13:45:34'),
(2, 'BRGY 410 - 0002-2026', 'bogart', '', 'bogart', 18, 'acajciuawh', 'shelsi', '', 'hamon', 'achjafca', '2026-05-23', 'yahab', 'klkjnkjnjknk', 'Complain', 'Ongoing', 1, 1, 0, NULL, NULL, NULL, NULL, NULL, 'Barangay Captain', 'Barangay Secretary', '2026-06-03 09:22:57', '2026-05-23 13:21:33', '2026-06-03 09:22:57'),
(3, 'BRGY 410 - 0003-2026', 'Carl', '', 'Jester', 21, '66 agnos ext. tatalon quezon city', 'Dioriel', '', 'Mapanao', 'achuwcfae', '2026-06-03', 'Kanto ng 410', 'Away', 'Complain', 'Ongoing', 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Barangay Captain', 'Barangay Captain', '2026-06-03 16:32:24', '2026-06-03 16:28:59', '2026-06-03 16:32:24'),
(4, 'BRGY 410 - 0004-2026', 'Jerome', 'R.', 'Dedase', 19, 'afw', 'JEROME', 'RIVERA', 'DEDASE', 'adw', '2026-06-11', 'yahab', 'afwa', 'Complain', 'Ongoing', 1, 0, 0, NULL, NULL, NULL, NULL, NULL, 'Barangay Secretary', 'Barangay Secretary', '2026-06-11 08:45:13', '2026-06-11 08:44:37', '2026-06-11 08:45:13');

-- --------------------------------------------------------

--
-- Table structure for table `case_mediation`
--

CREATE TABLE `case_mediation` (
  `id` int(11) NOT NULL,
  `case_id` varchar(50) NOT NULL,
  `mediation_date` date DEFAULT NULL,
  `settlement` text DEFAULT NULL,
  `mediator` varchar(150) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `saved_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `agreement_text` text DEFAULT NULL,
  `saved_by` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `case_mediation`
--

INSERT INTO `case_mediation` (`id`, `case_id`, `mediation_date`, `settlement`, `mediator`, `created_at`, `saved_at`, `updated_at`, `agreement_text`, `saved_by`) VALUES
(1, 'BRGY 410 - 0001-2026', NULL, NULL, NULL, '2026-05-22 07:01:28', '2026-05-22 07:01:28', '2026-05-22 07:01:28', '', 'Barangay Secretary'),
(2, 'BRGY 410 - 0001-2026', NULL, NULL, NULL, '2026-05-22 07:01:36', '2026-05-22 07:01:36', '2026-05-22 07:01:36', '', 'Barangay Secretary'),
(3, 'BRGY 410 - 0001-2026', NULL, NULL, NULL, '2026-05-22 07:52:23', '2026-05-22 07:52:23', '2026-05-22 07:52:23', '', 'Barangay Secretary'),
(4, 'BRGY 410 - 0001-2026', NULL, NULL, NULL, '2026-05-22 07:52:27', '2026-05-22 07:52:27', '2026-05-22 07:52:27', '', 'Barangay Secretary'),
(5, 'BRGY 410 - 0001-2026', NULL, NULL, NULL, '2026-05-22 08:04:15', '2026-05-22 08:04:15', '2026-05-22 08:04:15', '', 'Barangay Secretary'),
(6, 'BRGY 410 - 0001-2026', NULL, NULL, NULL, '2026-05-22 08:04:18', '2026-05-22 08:04:18', '2026-05-22 08:04:18', '', 'Barangay Secretary');

-- --------------------------------------------------------

--
-- Table structure for table `case_notice`
--

CREATE TABLE `case_notice` (
  `id` int(11) NOT NULL,
  `case_id` varchar(50) NOT NULL,
  `notice_to` varchar(200) DEFAULT NULL,
  `hearing_date` date DEFAULT NULL,
  `hearing_time` varchar(20) DEFAULT NULL,
  `issued_by` varchar(150) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `saved_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `hear_day` varchar(20) DEFAULT NULL,
  `hear_mo` varchar(30) DEFAULT NULL,
  `hear_yr` varchar(10) DEFAULT NULL,
  `hear_time` varchar(20) DEFAULT NULL,
  `notif_day` varchar(20) DEFAULT NULL,
  `notif_mo` varchar(30) DEFAULT NULL,
  `notif_yr` varchar(10) DEFAULT NULL,
  `saved_by` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `case_notice`
--

INSERT INTO `case_notice` (`id`, `case_id`, `notice_to`, `hearing_date`, `hearing_time`, `issued_by`, `created_at`, `saved_at`, `updated_at`, `hear_day`, `hear_mo`, `hear_yr`, `hear_time`, `notif_day`, `notif_mo`, `notif_yr`, `saved_by`) VALUES
(1, 'BRGY 410 - 0001-2026', NULL, NULL, NULL, NULL, '2026-05-22 07:51:44', '2026-05-22 07:51:44', '2026-05-22 07:51:44', '______', '____________', '__', '____', '______', '____________', '__', 'Barangay Secretary'),
(2, 'BRGY 410 - 0001-2026', NULL, NULL, NULL, NULL, '2026-05-22 07:51:47', '2026-05-22 07:51:47', '2026-05-22 07:51:47', '______', '____________', '__', '____', '______', '____________', '__', 'Barangay Secretary'),
(3, 'BRGY 410 - 0001-2026', NULL, NULL, NULL, NULL, '2026-05-22 08:03:35', '2026-05-22 08:03:35', '2026-05-22 08:03:35', '______', '____________', '__', '____', '______', '____________', '__', 'Barangay Secretary'),
(4, 'BRGY 410 - 0001-2026', NULL, NULL, NULL, NULL, '2026-05-22 08:03:39', '2026-05-22 08:03:39', '2026-05-22 08:03:39', '______', '____________', '__', '____', '______', '____________', '__', 'Barangay Secretary'),
(5, 'BRGY 410 - 0001-2026', NULL, NULL, NULL, NULL, '2026-05-22 13:45:30', '2026-05-22 13:45:30', '2026-05-22 13:45:30', '______', '____________', '__', '____', '______', '____________', '__', 'Barangay Secretary'),
(6, 'BRGY 410 - 0001-2026', NULL, NULL, NULL, NULL, '2026-05-22 13:45:34', '2026-05-22 13:45:34', '2026-05-22 13:45:34', '______', '____________', '__', '____', '______', '____________', '__', 'Barangay Secretary'),
(7, 'BRGY 410 - 0002-2026', NULL, NULL, NULL, NULL, '2026-05-28 22:24:47', '2026-05-28 22:24:47', '2026-05-28 22:24:47', '______', '____________', '__', '____', '______', '____________', '__', 'Barangay Captain'),
(8, 'BRGY 410 - 0002-2026', NULL, NULL, NULL, NULL, '2026-05-28 22:24:52', '2026-05-28 22:24:52', '2026-05-28 22:24:52', '______', '____________', '__', '____', '______', '____________', '__', 'Barangay Captain'),
(9, 'BRGY 410 - 0002-2026', NULL, NULL, NULL, NULL, '2026-06-03 09:22:52', '2026-06-03 09:22:52', '2026-06-03 09:22:52', '______', '____________', '__', '____', '______', '____________', '__', 'Barangay Secretary'),
(10, 'BRGY 410 - 0002-2026', NULL, NULL, NULL, NULL, '2026-06-03 09:22:57', '2026-06-03 09:22:57', '2026-06-03 09:22:57', '______', '____________', '__', '____', '______', '____________', '__', 'Barangay Secretary');

-- --------------------------------------------------------

--
-- Table structure for table `case_summons`
--

CREATE TABLE `case_summons` (
  `id` int(11) NOT NULL,
  `case_id` varchar(50) NOT NULL,
  `to_name` varchar(255) DEFAULT NULL,
  `issued_to` varchar(200) DEFAULT NULL,
  `hearing_date` date DEFAULT NULL,
  `hearing_time` varchar(20) DEFAULT NULL,
  `issued_by` varchar(150) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `hearing_day` varchar(20) DEFAULT NULL,
  `hearing_mo` varchar(30) DEFAULT NULL,
  `hearing_yr` varchar(10) DEFAULT NULL,
  `this_day` varchar(20) DEFAULT NULL,
  `this_mo` varchar(30) DEFAULT NULL,
  `this_yr` varchar(10) DEFAULT NULL,
  `or_respondent` varchar(255) DEFAULT NULL,
  `or_day` varchar(20) DEFAULT NULL,
  `or_mo` varchar(30) DEFAULT NULL,
  `or_yr` varchar(10) DEFAULT NULL,
  `or_opt1` varchar(20) DEFAULT NULL,
  `or_opt2` varchar(20) DEFAULT NULL,
  `or_opt3` varchar(20) DEFAULT NULL,
  `or_name3` varchar(255) DEFAULT NULL,
  `or_opt4` varchar(20) DEFAULT NULL,
  `or_name4` varchar(255) DEFAULT NULL,
  `saved_by` varchar(150) DEFAULT NULL,
  `saved_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `case_summons`
--

INSERT INTO `case_summons` (`id`, `case_id`, `to_name`, `issued_to`, `hearing_date`, `hearing_time`, `issued_by`, `created_at`, `hearing_day`, `hearing_mo`, `hearing_yr`, `this_day`, `this_mo`, `this_yr`, `or_respondent`, `or_day`, `or_mo`, `or_yr`, `or_opt1`, `or_opt2`, `or_opt3`, `or_name3`, `or_opt4`, `or_name4`, `saved_by`, `saved_at`, `updated_at`) VALUES
(1, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 03:49:25', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 03:49:25', '2026-05-22 03:49:25'),
(2, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 03:49:30', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 03:49:30', '2026-05-22 03:49:30'),
(3, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 03:58:26', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 03:58:26', '2026-05-22 03:58:26'),
(4, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 03:58:30', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 03:58:30', '2026-05-22 03:58:30'),
(5, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 04:04:19', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 04:04:19', '2026-05-22 04:04:19'),
(6, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 04:04:26', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 04:04:26', '2026-05-22 04:04:26'),
(7, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 04:07:24', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 04:07:24', '2026-05-22 04:07:24'),
(8, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 04:07:28', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 04:07:28', '2026-05-22 04:07:28'),
(9, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 04:11:25', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 04:11:25', '2026-05-22 04:11:25'),
(10, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 04:11:31', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 04:11:31', '2026-05-22 04:11:31'),
(11, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 04:18:03', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 04:18:03', '2026-05-22 04:18:03'),
(12, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 04:18:08', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 04:18:08', '2026-05-22 04:18:08'),
(13, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 04:21:46', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 04:21:46', '2026-05-22 04:21:46'),
(14, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 04:21:51', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 04:21:51', '2026-05-22 04:21:51'),
(15, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 04:28:32', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 04:28:32', '2026-05-22 04:28:32'),
(16, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 04:28:37', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 04:28:37', '2026-05-22 04:28:37'),
(17, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 04:42:07', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'Barangay Secretary', '2026-05-22 04:42:07', '2026-05-22 04:42:07'),
(18, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 04:42:11', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'Barangay Secretary', '2026-05-22 04:42:11', '2026-05-22 04:42:11'),
(19, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 04:47:46', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 04:47:46', '2026-05-22 04:47:46'),
(20, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 04:47:50', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 04:47:50', '2026-05-22 04:47:50'),
(21, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 04:48:32', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 04:48:32', '2026-05-22 04:48:32'),
(22, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 04:48:35', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 04:48:35', '2026-05-22 04:48:35'),
(23, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 04:49:09', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 04:49:09', '2026-05-22 04:49:09'),
(24, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 04:49:17', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 04:49:17', '2026-05-22 04:49:17'),
(25, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 07:51:04', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 07:51:04', '2026-05-22 07:51:04'),
(26, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 07:51:11', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 07:51:11', '2026-05-22 07:51:11'),
(27, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 08:01:21', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 08:01:21', '2026-05-22 08:01:21'),
(28, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 08:01:24', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 08:01:24', '2026-05-22 08:01:24'),
(29, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 08:02:57', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 08:02:57', '2026-05-22 08:02:57'),
(30, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 08:03:01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 08:03:01', '2026-05-22 08:03:01'),
(31, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 08:09:58', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 08:09:58', '2026-05-22 08:09:58'),
(32, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 08:10:01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 08:10:01', '2026-05-22 08:10:01'),
(33, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 08:13:17', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 08:13:17', '2026-05-22 08:13:17'),
(34, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 08:13:25', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 08:13:25', '2026-05-22 08:13:25'),
(35, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 08:14:26', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 08:14:26', '2026-05-22 08:14:26'),
(36, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 08:14:33', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 08:14:33', '2026-05-22 08:14:33'),
(37, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 08:17:22', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 08:17:22', '2026-05-22 08:17:22'),
(38, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 08:17:32', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 08:17:32', '2026-05-22 08:17:32'),
(39, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 08:17:45', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 08:17:45', '2026-05-22 08:17:45'),
(40, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 08:17:50', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 08:17:50', '2026-05-22 08:17:50'),
(41, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 08:21:53', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 08:21:53', '2026-05-22 08:21:53'),
(42, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 08:21:57', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 08:21:57', '2026-05-22 08:21:57'),
(43, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 08:25:22', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 08:25:22', '2026-05-22 08:25:22'),
(44, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 08:25:26', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 08:25:26', '2026-05-22 08:25:26'),
(45, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 08:25:49', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 08:25:49', '2026-05-22 08:25:49'),
(46, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 08:25:55', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 08:25:55', '2026-05-22 08:25:55'),
(47, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 08:30:54', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 08:30:54', '2026-05-22 08:30:54'),
(48, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 08:30:58', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 08:30:58', '2026-05-22 08:30:58'),
(49, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 08:33:41', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 08:33:41', '2026-05-22 08:33:41'),
(50, 'BRGY 410 - 0001-2026', 'Shellsi  Hmosin', NULL, NULL, '', NULL, '2026-05-22 08:33:45', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-05-22 08:33:45', '2026-05-22 08:33:45'),
(51, 'BRGY 410 - 0002-2026', 'shelsi  hamon', NULL, NULL, '', NULL, '2026-05-23 13:22:30', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'Barangay Captain', '2026-05-23 13:22:30', '2026-05-23 13:22:30'),
(52, 'BRGY 410 - 0002-2026', 'shelsi  hamon', NULL, NULL, '', NULL, '2026-05-23 13:22:36', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'Barangay Captain', '2026-05-23 13:22:36', '2026-05-23 13:22:36'),
(53, 'BRGY 410 - 0002-2026', 'shelsi  hamon', NULL, NULL, '', NULL, '2026-05-23 13:23:30', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'Barangay Captain', '2026-05-23 13:23:30', '2026-05-23 13:23:30'),
(54, 'BRGY 410 - 0002-2026', 'shelsi  hamon', NULL, NULL, '', NULL, '2026-05-23 13:23:35', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'Barangay Captain', '2026-05-23 13:23:35', '2026-05-23 13:23:35'),
(55, 'BRGY 410 - 0002-2026', 'shelsi  hamon', NULL, NULL, '', NULL, '2026-05-23 13:24:02', 'ghhg', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'Barangay Captain', '2026-05-23 13:24:02', '2026-05-23 13:24:02'),
(56, 'BRGY 410 - 0002-2026', 'shelsi  hamon', NULL, NULL, '', NULL, '2026-05-23 13:24:29', '', '27 ', '', '', '', '', '', '', '', '', '', '', '', '', 'admin', '', 'Barangay Captain', '2026-05-23 13:24:29', '2026-05-23 13:24:29'),
(57, 'BRGY 410 - 0002-2026', 'shelsi  hamon', NULL, NULL, '', NULL, '2026-05-23 13:24:34', '', '27', '', '', '', '', '', '', '', '', '', '', '', '', 'admin', '', 'Barangay Captain', '2026-05-23 13:24:34', '2026-05-23 13:24:34'),
(58, 'BRGY 410 - 0002-2026', 'shelsi  hamon', NULL, NULL, '', NULL, '2026-05-23 13:24:42', '', '27', '', '', '', '', '', '', '', '', '', '', '', '', 'admin', '', 'Barangay Captain', '2026-05-23 13:24:42', '2026-05-23 13:24:42'),
(59, 'BRGY 410 - 0002-2026', 'shelsi  hamon', NULL, NULL, '', NULL, '2026-05-28 22:38:53', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'admin', '', 'Barangay Captain', '2026-05-28 22:38:53', '2026-05-28 22:38:53'),
(60, 'BRGY 410 - 0002-2026', 'shelsi  hamon', NULL, NULL, '', NULL, '2026-05-28 22:38:58', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'admin', '', 'Barangay Captain', '2026-05-28 22:38:58', '2026-05-28 22:38:58'),
(61, 'BRGY 410 - 0002-2026', 'shelsi  hamon', NULL, NULL, '', NULL, '2026-05-30 21:44:27', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'admin', '', 'Barangay Captain', '2026-05-30 21:44:27', '2026-05-30 21:44:27'),
(62, 'BRGY 410 - 0002-2026', 'shelsi  hamon', NULL, NULL, '', NULL, '2026-05-30 21:44:32', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'admin', '', 'Barangay Captain', '2026-05-30 21:44:32', '2026-05-30 21:44:32'),
(63, 'BRGY 410 - 0002-2026', 'shelsi  hamon', NULL, NULL, '', NULL, '2026-06-03 00:56:36', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-06-03 00:56:36', '2026-06-03 00:56:36'),
(64, 'BRGY 410 - 0002-2026', 'shelsi  hamon', NULL, NULL, '', NULL, '2026-06-03 00:56:40', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-06-03 00:56:40', '2026-06-03 00:56:40'),
(65, 'BRGY 410 - 0002-2026', 'shelsi  hamon', NULL, NULL, '', NULL, '2026-06-03 09:22:23', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-06-03 09:22:23', '2026-06-03 09:22:23'),
(66, 'BRGY 410 - 0002-2026', 'shelsi  hamon', NULL, NULL, '', NULL, '2026-06-03 09:22:28', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-06-03 09:22:28', '2026-06-03 09:22:28'),
(67, 'BRGY 410 - 0003-2026', 'Dioriel  Mapanao', NULL, NULL, '', NULL, '2026-06-03 16:32:18', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'admin', '', 'Barangay Captain', '2026-06-03 16:32:18', '2026-06-03 16:32:18'),
(68, 'BRGY 410 - 0003-2026', 'Dioriel  Mapanao', NULL, NULL, '', NULL, '2026-06-03 16:32:24', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'admin', '', 'Barangay Captain', '2026-06-03 16:32:24', '2026-06-03 16:32:24'),
(69, 'BRGY 410 - 0004-2026', 'JEROME RIVERA DEDASE', NULL, NULL, '', NULL, '2026-06-11 08:44:58', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-06-11 08:44:58', '2026-06-11 08:44:58'),
(70, 'BRGY 410 - 0004-2026', 'JEROME RIVERA DEDASE', NULL, NULL, '', NULL, '2026-06-11 08:45:13', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'secretary', '', 'Barangay Secretary', '2026-06-11 08:45:13', '2026-06-11 08:45:13');

-- --------------------------------------------------------

--
-- Table structure for table `certificate_requests`
--

CREATE TABLE `certificate_requests` (
  `id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `purpose` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected','Released') DEFAULT 'Pending',
  `requested_at` datetime DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `requested_by` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certificate_requests`
--

INSERT INTO `certificate_requests` (`id`, `resident_id`, `template_id`, `purpose`, `status`, `requested_at`, `approved_at`, `released_at`, `requested_by`) VALUES
(1, 3, 1, 'requiremnet', 'Released', '2026-05-22 10:30:10', '2026-05-22 10:30:46', '2026-05-31 17:48:36', 'admin'),
(3, 3, 1, 'jhjhbu', 'Pending', '2026-05-22 16:51:01', NULL, NULL, 'admin'),
(4, 2, 3, 'jbyg', 'Pending', '2026-05-26 23:53:03', NULL, NULL, 'admin'),
(5, 2, 3, 'jbyg', 'Pending', '2026-05-28 21:44:39', NULL, NULL, 'admin'),
(6, 3, 3, 'Studies', 'Pending', '2026-05-31 19:02:47', NULL, NULL, 'secretary'),
(7, 11, 5, 'Business', 'Released', '2026-06-02 00:02:51', '2026-06-02 00:03:54', '2026-06-02 00:04:02', 'admin'),
(8, 11, 5, 'Business', 'Pending', '2026-06-02 00:06:41', NULL, NULL, 'admin'),
(9, 11, 5, 'Business', 'Pending', '2026-06-02 00:09:26', NULL, NULL, 'admin'),
(10, 11, 1, 'Clearance', 'Rejected', '2026-06-02 00:09:48', NULL, NULL, 'admin'),
(13, 11, 5, 'Siomai Business', 'Pending', '2026-06-11 00:01:03', NULL, NULL, 'secretary');

-- --------------------------------------------------------

--
-- Table structure for table `certificate_templates`
--

CREATE TABLE `certificate_templates` (
  `id` int(11) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certificate_templates`
--

INSERT INTO `certificate_templates` (`id`, `template_name`, `created_at`) VALUES
(1, 'Barangay Clearance', '2026-05-22 10:10:49'),
(2, 'Certificate of Residency', '2026-05-22 10:10:49'),
(3, 'Certificate of Indigency', '2026-05-22 10:10:49'),
(4, 'Certificate of Good Moral Character', '2026-05-22 10:10:49'),
(5, 'Business Permit', '2026-05-22 10:10:49');

-- --------------------------------------------------------

--
-- Table structure for table `document_requests`
--

CREATE TABLE `document_requests` (
  `id` int(11) NOT NULL,
  `request_code` varchar(30) NOT NULL,
  `resident_id` int(11) DEFAULT NULL,
  `resident_name` varchar(255) NOT NULL,
  `document_type` enum('Barangay Clearance','Certificate of Residency','Certificate of Indigency','Business Permit','Certificate of Good Moral','Other') NOT NULL,
  `other_document` varchar(255) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `status` enum('Pending','Processing','Ready','Released','Rejected') DEFAULT 'Pending',
  `requested_by` varchar(100) NOT NULL,
  `requested_at` datetime DEFAULT current_timestamp(),
  `released_at` datetime DEFAULT NULL,
  `released_by` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `document_requests`
--

INSERT INTO `document_requests` (`id`, `request_code`, `resident_id`, `resident_name`, `document_type`, `other_document`, `purpose`, `status`, `requested_by`, `requested_at`, `released_at`, `released_by`, `remarks`) VALUES
(1, 'REQ-20260525-0001', NULL, 'Jamo Sin', 'Certificate of Residency', '', 'klkj', 'Pending', 'admin', '2026-05-25 21:23:15', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `eb_audit_log`
--

CREATE TABLE `eb_audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `case_id` varchar(50) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `eb_audit_log`
--

INSERT INTO `eb_audit_log` (`id`, `user_id`, `username`, `full_name`, `action`, `case_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 2, 'secretary', 'Barangay Secretary', 'ADD_CASE', 'BRGY 410 - 0001-2026', 'Added by Barangay Secretary', '::1', '2026-05-21 22:49:25'),
(2, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 03:24:46'),
(3, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 03:26:21'),
(4, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 03:26:35'),
(5, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 03:42:48'),
(6, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 03:43:01'),
(7, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 03:44:35'),
(8, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 03:49:25'),
(9, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 03:49:30'),
(10, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 03:58:26'),
(11, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 03:58:30'),
(12, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 04:04:19'),
(13, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 04:04:26'),
(14, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 04:07:24'),
(15, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 04:07:28'),
(16, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 04:11:25'),
(17, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 04:11:31'),
(18, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 04:18:03'),
(19, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 04:18:08'),
(20, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 04:21:46'),
(21, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 04:21:51'),
(22, 2, 'secretary', 'Barangay Secretary', 'EXPORT_NOTICE', 'BRGY 410 - 0001-2026', 'Notice of Hearing PDF exported', '::1', '2026-05-22 04:27:00'),
(23, 2, 'secretary', 'Barangay Secretary', 'EXPORT_MEDIATION', 'BRGY 410 - 0001-2026', 'Mediation Minutes PDF exported', '::1', '2026-05-22 04:27:24'),
(24, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 04:28:32'),
(25, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 04:28:37'),
(26, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 04:42:07'),
(27, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 04:42:11'),
(28, 2, 'secretary', 'Barangay Secretary', 'EXPORT_NOTICE', 'BRGY 410 - 0001-2026', 'Notice of Hearing PDF exported', '::1', '2026-05-22 04:42:46'),
(29, 2, 'secretary', 'Barangay Secretary', 'EXPORT_NOTICE', 'BRGY 410 - 0001-2026', 'Notice of Hearing PDF exported', '::1', '2026-05-22 04:47:09'),
(30, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 04:47:46'),
(31, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 04:47:50'),
(32, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 04:48:32'),
(33, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 04:48:35'),
(34, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 04:49:09'),
(35, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 04:49:16'),
(36, 2, 'secretary', 'Barangay Secretary', 'EXPORT_NOTICE', 'BRGY 410 - 0001-2026', 'Notice of Hearing PDF exported', '::1', '2026-05-22 04:50:11'),
(37, 2, 'secretary', 'Barangay Secretary', 'EXPORT_MEDIATION', 'BRGY 410 - 0001-2026', 'Mediation Minutes PDF exported', '::1', '2026-05-22 04:50:58'),
(38, 2, 'secretary', 'Barangay Secretary', 'EXPORT_MEDIATION', 'BRGY 410 - 0001-2026', 'Mediation Minutes PDF exported', '::1', '2026-05-22 07:01:28'),
(39, 2, 'secretary', 'Barangay Secretary', 'EXPORT_MEDIATION', 'BRGY 410 - 0001-2026', 'Mediation Minutes PDF exported', '::1', '2026-05-22 07:01:36'),
(40, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 07:51:04'),
(41, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 07:51:11'),
(42, 2, 'secretary', 'Barangay Secretary', 'EXPORT_NOTICE', 'BRGY 410 - 0001-2026', 'Notice of Hearing PDF exported', '::1', '2026-05-22 07:51:44'),
(43, 2, 'secretary', 'Barangay Secretary', 'EXPORT_NOTICE', 'BRGY 410 - 0001-2026', 'Notice of Hearing PDF exported', '::1', '2026-05-22 07:51:47'),
(44, 2, 'secretary', 'Barangay Secretary', 'EXPORT_MEDIATION', 'BRGY 410 - 0001-2026', 'Mediation Minutes PDF exported', '::1', '2026-05-22 07:52:23'),
(45, 2, 'secretary', 'Barangay Secretary', 'EXPORT_MEDIATION', 'BRGY 410 - 0001-2026', 'Mediation Minutes PDF exported', '::1', '2026-05-22 07:52:27'),
(46, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 08:01:21'),
(47, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 08:01:24'),
(48, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 08:02:57'),
(49, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 08:03:01'),
(50, 2, 'secretary', 'Barangay Secretary', 'EXPORT_NOTICE', 'BRGY 410 - 0001-2026', 'Notice of Hearing PDF exported', '::1', '2026-05-22 08:03:35'),
(51, 2, 'secretary', 'Barangay Secretary', 'EXPORT_NOTICE', 'BRGY 410 - 0001-2026', 'Notice of Hearing PDF exported', '::1', '2026-05-22 08:03:39'),
(52, 2, 'secretary', 'Barangay Secretary', 'EXPORT_MEDIATION', 'BRGY 410 - 0001-2026', 'Mediation Minutes PDF exported', '::1', '2026-05-22 08:04:15'),
(53, 2, 'secretary', 'Barangay Secretary', 'EXPORT_MEDIATION', 'BRGY 410 - 0001-2026', 'Mediation Minutes PDF exported', '::1', '2026-05-22 08:04:18'),
(54, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 08:09:58'),
(55, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 08:10:01'),
(56, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 08:13:17'),
(57, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 08:13:25'),
(58, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 08:14:26'),
(59, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 08:14:33'),
(60, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 08:17:22'),
(61, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 08:17:32'),
(62, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 08:17:45'),
(63, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 08:17:50'),
(64, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 08:21:53'),
(65, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 08:21:57'),
(66, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 08:25:22'),
(67, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 08:25:26'),
(68, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 08:25:49'),
(69, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 08:25:55'),
(70, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 08:30:54'),
(71, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 08:30:58'),
(72, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 08:33:41'),
(73, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0001-2026', 'Summons PDF exported', '::1', '2026-05-22 08:33:45'),
(74, 2, 'secretary', 'Barangay Secretary', 'EXPORT_NOTICE', 'BRGY 410 - 0001-2026', 'Notice of Hearing PDF exported', '::1', '2026-05-22 13:45:30'),
(75, 2, 'secretary', 'Barangay Secretary', 'EXPORT_NOTICE', 'BRGY 410 - 0001-2026', 'Notice of Hearing PDF exported', '::1', '2026-05-22 13:45:34'),
(76, 1, 'admin', 'Barangay Captain', 'ADD_CASE', 'BRGY 410 - 0002-2026', 'Added by Barangay Captain', '::1', '2026-05-23 13:21:33'),
(77, 1, 'admin', 'Barangay Captain', 'EXPORT_SUMMONS', 'BRGY 410 - 0002-2026', 'Summons PDF exported', '::1', '2026-05-23 13:22:30'),
(78, 1, 'admin', 'Barangay Captain', 'EXPORT_SUMMONS', 'BRGY 410 - 0002-2026', 'Summons PDF exported', '::1', '2026-05-23 13:22:36'),
(79, 1, 'admin', 'Barangay Captain', 'EXPORT_SUMMONS', 'BRGY 410 - 0002-2026', 'Summons PDF exported', '::1', '2026-05-23 13:23:30'),
(80, 1, 'admin', 'Barangay Captain', 'EXPORT_SUMMONS', 'BRGY 410 - 0002-2026', 'Summons PDF exported', '::1', '2026-05-23 13:23:35'),
(81, 1, 'admin', 'Barangay Captain', 'EXPORT_SUMMONS', 'BRGY 410 - 0002-2026', 'Summons PDF exported', '::1', '2026-05-23 13:24:34'),
(82, 1, 'admin', 'Barangay Captain', 'EXPORT_SUMMONS', 'BRGY 410 - 0002-2026', 'Summons PDF exported', '::1', '2026-05-23 13:24:42'),
(83, 1, 'admin', 'Barangay Captain', 'EXPORT_NOTICE', 'BRGY 410 - 0002-2026', 'Notice of Hearing PDF exported', '::1', '2026-05-28 22:24:47'),
(84, 1, 'admin', 'Barangay Captain', 'EXPORT_NOTICE', 'BRGY 410 - 0002-2026', 'Notice of Hearing PDF exported', '::1', '2026-05-28 22:24:52'),
(85, 1, 'admin', 'Barangay Captain', 'EXPORT_SUMMONS', 'BRGY 410 - 0002-2026', 'Summons PDF exported', '::1', '2026-05-28 22:38:53'),
(86, 1, 'admin', 'Barangay Captain', 'EXPORT_SUMMONS', 'BRGY 410 - 0002-2026', 'Summons PDF exported', '::1', '2026-05-28 22:38:58'),
(87, 1, 'admin', 'Barangay Captain', 'EXPORT_SUMMONS', 'BRGY 410 - 0002-2026', 'Summons PDF exported', '::1', '2026-05-30 21:44:27'),
(88, 1, 'admin', 'Barangay Captain', 'EXPORT_SUMMONS', 'BRGY 410 - 0002-2026', 'Summons PDF exported', '::1', '2026-05-30 21:44:32'),
(89, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0002-2026', 'Summons PDF exported', '::1', '2026-06-03 00:56:36'),
(90, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0002-2026', 'Summons PDF exported', '::1', '2026-06-03 00:56:40'),
(91, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0002-2026', 'Summons PDF exported', '::1', '2026-06-03 09:22:23'),
(92, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0002-2026', 'Summons PDF exported', '::1', '2026-06-03 09:22:28'),
(93, 2, 'secretary', 'Barangay Secretary', 'EXPORT_NOTICE', 'BRGY 410 - 0002-2026', 'Notice of Hearing PDF exported', '::1', '2026-06-03 09:22:52'),
(94, 2, 'secretary', 'Barangay Secretary', 'EXPORT_NOTICE', 'BRGY 410 - 0002-2026', 'Notice of Hearing PDF exported', '::1', '2026-06-03 09:22:57'),
(95, 1, 'admin', 'Barangay Captain', 'ADD_CASE', 'BRGY 410 - 0003-2026', 'Added by Barangay Captain', '::1', '2026-06-03 16:28:59'),
(96, 1, 'admin', 'Barangay Captain', 'EXPORT_SUMMONS', 'BRGY 410 - 0003-2026', 'Summons PDF exported', '::1', '2026-06-03 16:32:18'),
(97, 1, 'admin', 'Barangay Captain', 'EXPORT_SUMMONS', 'BRGY 410 - 0003-2026', 'Summons PDF exported', '::1', '2026-06-03 16:32:24'),
(98, 2, 'secretary', 'Barangay Secretary', 'ADD_CASE', 'BRGY 410 - 0004-2026', 'Added by Barangay Secretary', '::1', '2026-06-11 08:44:37'),
(99, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0004-2026', 'Summons PDF exported', '::1', '2026-06-11 08:44:58'),
(100, 2, 'secretary', 'Barangay Secretary', 'EXPORT_SUMMONS', 'BRGY 410 - 0004-2026', 'Summons PDF exported', '::1', '2026-06-11 08:45:13');

-- --------------------------------------------------------

--
-- Table structure for table `eb_case_sequence`
--

CREATE TABLE `eb_case_sequence` (
  `seq_year` char(4) NOT NULL,
  `seq_next` int(10) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `eb_case_sequence`
--

INSERT INTO `eb_case_sequence` (`seq_year`, `seq_next`) VALUES
('2026', 5);

-- --------------------------------------------------------

--
-- Table structure for table `eb_signer_settings`
--

CREATE TABLE `eb_signer_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `signer_name` varchar(200) NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `eb_signer_settings`
--

INSERT INTO `eb_signer_settings` (`id`, `signer_name`, `updated_at`) VALUES
(1, 'JUAN DELA CRUZ', '2026-06-02 00:05:55');

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

CREATE TABLE `equipment` (
  `id` int(11) NOT NULL,
  `item_code` varchar(30) NOT NULL,
  `item_name` varchar(200) NOT NULL,
  `category` enum('Audio/Visual','Furniture','Cleaning','Sports','Medical','Office','Others') DEFAULT 'Others',
  `description` text DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `available` int(11) DEFAULT 1,
  `condition_status` enum('Good','Fair','Needs Repair','Retired') DEFAULT 'Good',
  `added_by` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `equipment`
--

INSERT INTO `equipment` (`id`, `item_code`, `item_name`, `category`, `description`, `quantity`, `available`, `condition_status`, `added_by`, `created_at`) VALUES
(1, 'SPEAKER-411', 'Speaker', 'Audio/Visual', 'with Microphone', 4, 4, 'Good', 'admin', '2026-05-28 22:51:31'),
(2, 'Chair Blocks-410', 'Plastic Chairs', 'Furniture', '', 50, 50, 'Good', 'admin', '2026-05-28 22:52:11'),
(3, 'Proj-410', 'Projector', 'Audio/Visual', '', 2, 2, 'Fair', 'admin', '2026-05-28 22:52:38'),
(4, 'EXTENSION.C-410', 'Extension Chord', 'Others', '2 meters in length', 1, 0, 'Good', 'admin', '2026-05-28 22:53:22'),
(5, 'LDDR-410', 'Metal Ladder', 'Furniture', '', 10, 10, 'Good', 'admin', '2026-05-28 22:54:08');

-- --------------------------------------------------------

--
-- Table structure for table `equipment_borrowing`
--

CREATE TABLE `equipment_borrowing` (
  `id` int(11) NOT NULL,
  `borrow_code` varchar(30) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `senior_citizen_id` int(11) DEFAULT NULL,
  `borrower_name` varchar(200) NOT NULL,
  `borrower_contact` varchar(50) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `borrow_date` date NOT NULL,
  `return_date` date NOT NULL,
  `actual_return` date DEFAULT NULL,
  `status` enum('Pending','Approved','Returned','Overdue','Rejected') DEFAULT 'Pending',
  `approved_by` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `equipment_borrowing`
--

INSERT INTO `equipment_borrowing` (`id`, `borrow_code`, `equipment_id`, `senior_citizen_id`, `borrower_name`, `borrower_contact`, `purpose`, `borrow_date`, `return_date`, `actual_return`, `status`, `approved_by`, `remarks`, `created_at`) VALUES
(1, 'BRW-260528-F22E5', 2, 1, 'Chan, Jose', '', 'Birthday', '2026-05-28', '2026-05-31', '2026-05-28', 'Returned', 'admin', NULL, '2026-05-28 22:58:08'),
(2, 'BRW-260528-C4737', 4, 2, 'Sin, Jamo', '', 'Birthday', '2026-05-28', '2026-05-31', '2026-06-11', 'Returned', 'admin', NULL, '2026-05-28 22:59:11'),
(3, 'BRW-260603-D2401', 5, NULL, 'anua7ea ajcavhue', '', 'Birthday', '2026-06-03', '2026-06-04', '2026-06-11', 'Returned', 'admin', NULL, '2026-06-03 16:34:23'),
(4, 'BRW-260610-48CB3', 4, 6, 'Manoban, Alex', '', 'Meeting', '2026-06-10', '2026-06-11', NULL, 'Approved', 'secretary', NULL, '2026-06-11 00:12:39');

-- --------------------------------------------------------

--
-- Table structure for table `failed_attempts`
--

CREATE TABLE `failed_attempts` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempts` int(11) DEFAULT 1,
  `locked_until` datetime DEFAULT NULL,
  `last_attempt` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `attempts` int(11) DEFAULT 1,
  `last_attempt` datetime DEFAULT current_timestamp(),
  `locked_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_requests`
--

CREATE TABLE `password_reset_requests` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `requested_at` datetime DEFAULT current_timestamp(),
  `status` enum('pending','resolved') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pets`
--

CREATE TABLE `pets` (
  `id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `pet_name` varchar(100) DEFAULT '',
  `pet_age` varchar(20) DEFAULT '',
  `pet_sex` varchar(20) DEFAULT '',
  `pet_color` varchar(50) DEFAULT '',
  `pet_type` varchar(50) DEFAULT '',
  `breeder_status` varchar(10) DEFAULT '',
  `other_pets` varchar(255) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pets`
--

INSERT INTO `pets` (`id`, `resident_id`, `pet_name`, `pet_age`, `pet_sex`, `pet_color`, `pet_type`, `breeder_status`, `other_pets`) VALUES
(11, 7, 'je', '6', 'Female', 'white', 'Cat', 'No', ''),
(12, 12, 'Cardo', '1', 'Male', 'blue', 'Other', 'No', 'Parrot'),
(13, 13, 'Boagrt', '2', 'Male', 'green', 'Other', 'No', 'Snake'),
(17, 6, 'jumbo', '12', 'Female', 'armygreen', 'Dog', 'No', 'crocodile'),
(18, 5, 'bog', '12', 'Male', 'black', 'Dog', 'No', ''),
(19, 5, 'rat', '7', 'Female', 'cookies and cream', 'Cat', 'No', ''),
(20, 5, 'bire', '11', 'Male', 'blue', 'Dog', 'Yes', '');

-- --------------------------------------------------------

--
-- Table structure for table `residents`
--

CREATE TABLE `residents` (
  `id` int(11) NOT NULL,
  `resident_code` varchar(20) DEFAULT NULL,
  `family_code` varchar(20) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT '',
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT '',
  `head_of_family` varchar(10) DEFAULT '',
  `relationship` varchar(100) DEFAULT '',
  `head_first_name` varchar(100) DEFAULT '',
  `head_middle_name` varchar(100) DEFAULT '',
  `head_last_name` varchar(100) DEFAULT '',
  `head_suffix` varchar(20) DEFAULT '',
  `perm_address` varchar(255) DEFAULT '',
  `prov_address` varchar(255) DEFAULT '',
  `house_owner` varchar(10) DEFAULT '',
  `house_details` varchar(255) DEFAULT '',
  `years_in_barangay` int(11) DEFAULT 0,
  `voter` varchar(10) DEFAULT '',
  `precinct_no` varchar(50) DEFAULT '',
  `mobile` varchar(20) DEFAULT '',
  `landline` varchar(20) DEFAULT '',
  `email` varchar(150) DEFAULT '',
  `birthdate` date DEFAULT NULL,
  `gender` varchar(30) DEFAULT '',
  `marital_status` varchar(30) DEFAULT '',
  `religion` varchar(100) DEFAULT '',
  `citizenship` varchar(100) DEFAULT '',
  `education` varchar(100) DEFAULT '',
  `employment_status` varchar(50) DEFAULT '',
  `occupation` varchar(150) DEFAULT '',
  `employer` varchar(150) DEFAULT '',
  `work_hours` varchar(50) DEFAULT '',
  `grade_level` varchar(50) DEFAULT '',
  `school_name` varchar(150) DEFAULT '',
  `out_of_school_youth` varchar(10) DEFAULT '',
  `has_car` varchar(10) DEFAULT '',
  `car_brand` varchar(100) DEFAULT '',
  `car_model` varchar(100) DEFAULT '',
  `car_color` varchar(50) DEFAULT '',
  `car_plate` varchar(30) DEFAULT '',
  `has_motorcycle` varchar(10) DEFAULT '',
  `motor_brand` varchar(100) DEFAULT '',
  `motor_model` varchar(100) DEFAULT '',
  `motor_color` varchar(50) DEFAULT '',
  `motor_plate` varchar(30) DEFAULT '',
  `is_senior` varchar(10) DEFAULT '',
  `osca_id` varchar(50) DEFAULT '',
  `pwd_status` varchar(10) DEFAULT 'No',
  `pwd_id` varchar(50) DEFAULT '',
  `disability_type` varchar(100) DEFAULT '',
  `solo_parent_status` varchar(10) DEFAULT 'No',
  `solo_parent_id` varchar(50) DEFAULT '',
  `has_pets` tinyint(1) DEFAULT 0,
  `is_hidden` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `residents`
--

INSERT INTO `residents` (`id`, `resident_code`, `family_code`, `first_name`, `middle_name`, `last_name`, `suffix`, `head_of_family`, `relationship`, `head_first_name`, `head_middle_name`, `head_last_name`, `head_suffix`, `perm_address`, `prov_address`, `house_owner`, `house_details`, `years_in_barangay`, `voter`, `precinct_no`, `mobile`, `landline`, `email`, `birthdate`, `gender`, `marital_status`, `religion`, `citizenship`, `education`, `employment_status`, `occupation`, `employer`, `work_hours`, `grade_level`, `school_name`, `out_of_school_youth`, `has_car`, `car_brand`, `car_model`, `car_color`, `car_plate`, `has_motorcycle`, `motor_brand`, `motor_model`, `motor_color`, `motor_plate`, `is_senior`, `osca_id`, `pwd_status`, `pwd_id`, `disability_type`, `solo_parent_status`, `solo_parent_id`, `has_pets`, `is_hidden`, `created_at`, `lat`, `lng`) VALUES
(2, '04302026000001', NULL, 'Jerome', 'Rivera', 'Dedase', 'VI', 'No', 'Uncle', 'Rond', 'Colla', 'Dedase', 'V', '123st. Brgy. 410 Sampaloc Manila', 'Metro Manila', 'No', 'rented', 12, 'Yes', '1220', '09178054763', '', 'jeromededase9@gmail.com', '1983-07-17', 'Male', 'Married', 'Roman Catholic', 'Filipino', 'College Graduate', 'Employed', 'analyst', 'Government', '', '', '', 'No', 'No', '', '', '', '', 'No', '', '', '', '', 'No', '', 'No', '', '', 'No', '', 0, 0, '2026-04-30 15:58:18', NULL, NULL),
(3, '05032026000001', NULL, 'Jose', 'Marie', 'Chan', 'Jr.', 'Yes', '', 'Jose', 'Marie', 'Chan', 'Jr.', '66 Agno Ext., Tatalon Quezon City', 'Metro Manila', 'Yes', '', 13, 'Yes', '122', '', '', 'dedase.23402094m.bsap@gmail.com', '1972-07-29', 'Male', 'Widowed', 'Iglesia ni Cristo', 'American', 'Postgraduate', 'Retired', '', '', '', '', '', 'No', 'No', '', '', '', '', 'No', '', '', '', '', 'Yes', '12301203', 'Yes', '1230120310230123', 'Pilay', 'No', '', 0, 0, '2026-05-03 22:54:33', NULL, NULL),
(4, '26-0504-001', NULL, 'Eprol', 'Jane', 'Santi', '', 'Yes', '', 'Eprol', 'Jane', 'Santi', '', '66 Agno Ext., Tatalon Quezon City', 'Metro Manila', 'No', 'borrowed', 5, 'Yes', '223', '', '', '', '1999-05-05', 'Female', 'Annulled', 'Buddhism', 'Filipino', 'College Graduate', 'Employed', 'secretary', 'Government', 'None', '', '', 'No', 'No', '', '', '', '', 'No', '', '', '', '', 'No', '', 'No', '', '', 'No', '', 0, 0, '2026-05-04 17:14:06', NULL, NULL),
(5, '040522-102026-01001', NULL, 'Jamo', 'men', 'Sin', 'II', 'Yes', '', 'Jamo', 'men', 'Sin', 'II', 'Sampaloc Manila', 'Metro Manila', 'Yes', '', 7, 'No', '', '', '', '', '1971-03-03', 'Male', 'Divorced', 'Muslim', 'Filipino', 'No Formal Education', 'Retired', '', '', '', '', '', 'No', 'Yes', 'Lambo', '', '', 'jkl-067', 'No', '', '', '', '', 'No', '098765432', 'No', '', '', 'No', '', 1, 0, '2026-05-22 15:06:33', NULL, NULL),
(6, '040522-102026-01002', NULL, 'Justin', '', 'Akcjnwaiufa', 'Jr.', 'No', 'father', 'Jamo', '', 'Sin', '', 'akjfnwaufhaw', 'akcnfwaifa', 'No', 'rented', 1, 'No', '', '', '', '', '2011-09-09', 'Male', 'Single', 'None', 'Filipino', 'No Formal Education', 'Student', '', '', '', '', '', 'No', 'No', '', '', '', '', 'Yes', 'Honda', '', '', '123-456-789', 'No', '', 'No', '', '', 'No', '', 1, 0, '2026-05-22 15:30:11', NULL, NULL),
(7, '040522-102026-01003', NULL, 'Fuhsefaofiwa', '', 'Ckncwufwa', 'Iii', 'Yes', '', 'Fuhsefaofiwa', '', 'Ckncwufwa', 'Iii', 'akjfnwaufhaw', 'akcnfwaifa', 'Yes', '', 1, 'Yes', '111', '', '', '', '1967-03-31', 'Female', 'Widowed', 'Iglesia ni Cristo', 'Filipino', 'Postgraduate', 'Self-Employed', 'CEO', '', '', '', 'UST', 'No', 'Yes', 'Ferrari', '', 'Gold', '67899876', 'No', '', '', '', '', '', '', 'No', '', '', 'No', '', 1, 1, '2026-05-22 15:40:01', NULL, NULL),
(8, '04-0526-1001-00', '04-0526-1001-00', 'Alex', '', 'Manoban', '', 'Yes', '', 'Alex', '', 'Manoban', '', '', '', 'Yes', '', 0, 'No', '', '', '', '', '1892-02-02', 'Male', 'Single', '', 'Filipino', 'No Formal Education', 'Employed', '', '', '', '', '', '', 'Yes', '', '', '', '', 'Yes', '', '', '', '', 'No', '', 'No', '', '', 'No', '', 0, 0, '2026-05-24 20:47:13', NULL, NULL),
(9, '04-0526-1002-00', '04-0526-1001-00', 'ajjnahuvi', '', 'ajnaevhua', '', 'No', '', '', '', '', '', '', '', '', '', 0, 'No', '', '', '', '', '1894-02-12', 'Female', 'Single', '', 'Filipino', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'No', '', 'No', '', '', 'No', '', 0, 1, '2026-05-24 20:47:13', NULL, NULL),
(10, '04-0526-1003-00', '04-0526-1001-00', 'anua7ea', '', 'ajcavhue', '', 'No', '', '', '', '', '', '', '', '', '', 0, 'No', '', '', '', '', '1900-03-10', 'Male', 'Single', '', 'Filipino', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'No', '', 'No', '', '', 'No', '', 0, 1, '2026-05-24 20:47:13', NULL, NULL),
(11, '04-0626-1001-00', NULL, 'Clude', '', 'Buban', 'Jr.', 'Yes', '', 'Clude', '', 'Buban', 'Jr.', 'Jhocson st. brgy 410 sampaloc manila', 'Metro manila', 'No', 'Rented', 10, 'Yes', '0341', '0987654321', '', '', '1998-05-05', 'Male', 'Single', 'Roman Catholic', 'Filipino', 'College Undergraduate', 'Student', '', '', '', '', '', 'No', 'No', '', '', '', '', 'No', '', '', '', '', 'No', '', 'No', '', '', 'No', '', 0, 0, '2026-06-01 09:57:59', NULL, NULL),
(12, '04-0626-1002-00', NULL, 'Jomar', '', 'Yin', '', 'Yes', '', 'Jomar', '', 'Yin', '', '166 Jhocson st. Brgy.410 Zone 42 Sampaloc Manila', 'Metro Manila', 'No', '', 7, 'No', '', '', '', '', '1999-01-01', 'Male', 'Single', 'Roman Catholic', 'American', 'Vocational', 'Employed', 'Contractor', 'Private', 'Morning', '', '', 'No', 'No', '', '', '', '', 'Yes', 'Motor', 'A1', 'Black', '111-11111', 'No', '', 'No', '', '', 'No', '', 1, 0, '2026-06-01 23:58:41', NULL, NULL),
(13, '04-0626-1003-00', NULL, 'Kjnskhviah', '', 'Akjnckwa', '', 'No', 'aunt', 'Jamo', '', 'Sin', '', 'KDXNCKAJHEC', 'METRO MANILA', 'No', 'rented', 1, 'No', '', '', '', '', '2007-05-02', 'Male', 'Widowed', 'Roman Catholic', 'Finnish', 'High School Undergraduate', 'Unemployed', '', '', '', '', '', 'Yes', 'No', '', '', '', '', 'No', '', '', '', '', 'No', '', 'No', '', '', 'No', '', 1, 0, '2026-06-02 14:09:56', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sa_audit_log`
--

CREATE TABLE `sa_audit_log` (
  `id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `detail` text DEFAULT NULL,
  `performed_by` varchar(100) DEFAULT 'superadmin',
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sa_audit_log`
--

INSERT INTO `sa_audit_log` (`id`, `action`, `detail`, `performed_by`, `ip_address`, `created_at`) VALUES
(1, 'SA_CLEAR_LOCKS', 'All login lockouts cleared', 'superadmin', '::1', '2026-06-10 14:43:02');

-- --------------------------------------------------------

--
-- Table structure for table `sa_login_attempts`
--

CREATE TABLE `sa_login_attempts` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempts` int(11) DEFAULT 1,
  `locked_until` datetime DEFAULT NULL,
  `last_attempt` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `senior_citizens`
--

CREATE TABLE `senior_citizens` (
  `id` int(11) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `gender` enum('Male','Female') DEFAULT NULL,
  `birth_month` tinyint(4) NOT NULL,
  `birth_day` tinyint(4) NOT NULL,
  `birth_year` smallint(6) NOT NULL,
  `address` varchar(300) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `status` enum('Active','Deceased') DEFAULT 'Active',
  `added_by` varchar(150) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `senior_citizens`
--

INSERT INTO `senior_citizens` (`id`, `last_name`, `first_name`, `middle_name`, `gender`, `birth_month`, `birth_day`, `birth_year`, `address`, `contact_number`, `status`, `added_by`, `created_at`) VALUES
(1, 'Chan', 'Jose', 'Marie', 'Male', 7, 29, 1972, '66 Agno Ext., Tatalon Quezon City', '', 'Active', 'Barangay Captain', '2026-05-21 13:26:44'),
(2, 'Sin', 'Jamo', '', 'Male', 3, 3, 1971, 'ijnciahfieuva', '', 'Active', 'Barangay Captain', '2026-05-22 15:06:33'),
(3, 'Mabini', 'Apolinario', '', 'Male', 2, 2, 1892, '', '', 'Deceased', 'Barangay Captain', '2026-05-24 20:47:13'),
(4, 'Derullo', 'Jason', '', 'Female', 2, 12, 1894, '', '', 'Active', 'Barangay Captain', '2026-05-24 20:47:13'),
(5, 'Dela Cruz', 'Jasper', '', 'Male', 3, 10, 1900, '', '', 'Deceased', 'Barangay Captain', '2026-05-24 20:47:13'),
(6, 'Manoban', 'Alex', '', 'Male', 2, 2, 1892, '', '', 'Active', 'Barangay Secretary', '2026-06-10 23:33:29');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`key`, `value`, `updated_at`) VALUES
('cert_secretary', '{\"name\":\"\"}', '2026-05-24 17:04:29'),
('cert_signatory', '{\"name\":\"Jose Chan\",\"title\":\"Punong Barangay\"}', '2026-05-24 17:04:29');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `access_log`
--
ALTER TABLE `access_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`account_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `barangay_officials`
--
ALTER TABLE `barangay_officials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_key` (`role_key`);

--
-- Indexes for table `blotter`
--
ALTER TABLE `blotter`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `case_no` (`case_no`);

--
-- Indexes for table `blotter_cases`
--
ALTER TABLE `blotter_cases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `case_id` (`case_id`);

--
-- Indexes for table `case_mediation`
--
ALTER TABLE `case_mediation`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `case_notice`
--
ALTER TABLE `case_notice`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `case_summons`
--
ALTER TABLE `case_summons`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `certificate_requests`
--
ALTER TABLE `certificate_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `resident_id` (`resident_id`),
  ADD KEY `template_id` (`template_id`);

--
-- Indexes for table `certificate_templates`
--
ALTER TABLE `certificate_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_code` (`request_code`);

--
-- Indexes for table `eb_audit_log`
--
ALTER TABLE `eb_audit_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `eb_case_sequence`
--
ALTER TABLE `eb_case_sequence`
  ADD PRIMARY KEY (`seq_year`);

--
-- Indexes for table `eb_signer_settings`
--
ALTER TABLE `eb_signer_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `equipment`
--
ALTER TABLE `equipment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_code` (`item_code`);

--
-- Indexes for table `equipment_borrowing`
--
ALTER TABLE `equipment_borrowing`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `borrow_code` (`borrow_code`),
  ADD KEY `equipment_id` (`equipment_id`);

--
-- Indexes for table `failed_attempts`
--
ALTER TABLE `failed_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_ip` (`ip_address`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ip` (`ip`);

--
-- Indexes for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pets`
--
ALTER TABLE `pets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `resident_id` (`resident_id`);

--
-- Indexes for table `residents`
--
ALTER TABLE `residents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_family_code` (`family_code`),
  ADD KEY `idx_name` (`last_name`,`first_name`),
  ADD KEY `idx_hidden` (`is_hidden`),
  ADD KEY `idx_resident_code` (`resident_code`);

--
-- Indexes for table `sa_audit_log`
--
ALTER TABLE `sa_audit_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sa_login_attempts`
--
ALTER TABLE `sa_login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ip_address` (`ip_address`);

--
-- Indexes for table `senior_citizens`
--
ALTER TABLE `senior_citizens`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`key`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `access_log`
--
ALTER TABLE `access_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=200;

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `account_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `barangay_officials`
--
ALTER TABLE `barangay_officials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=595;

--
-- AUTO_INCREMENT for table `blotter`
--
ALTER TABLE `blotter`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blotter_cases`
--
ALTER TABLE `blotter_cases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `case_mediation`
--
ALTER TABLE `case_mediation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `case_notice`
--
ALTER TABLE `case_notice`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `case_summons`
--
ALTER TABLE `case_summons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `certificate_requests`
--
ALTER TABLE `certificate_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `certificate_templates`
--
ALTER TABLE `certificate_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `document_requests`
--
ALTER TABLE `document_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `eb_audit_log`
--
ALTER TABLE `eb_audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `equipment_borrowing`
--
ALTER TABLE `equipment_borrowing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `failed_attempts`
--
ALTER TABLE `failed_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pets`
--
ALTER TABLE `pets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `residents`
--
ALTER TABLE `residents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `sa_audit_log`
--
ALTER TABLE `sa_audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sa_login_attempts`
--
ALTER TABLE `sa_login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `senior_citizens`
--
ALTER TABLE `senior_citizens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `equipment_borrowing`
--
ALTER TABLE `equipment_borrowing`
  ADD CONSTRAINT `equipment_borrowing_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`);

--
-- Constraints for table `pets`
--
ALTER TABLE `pets`
  ADD CONSTRAINT `pets_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;