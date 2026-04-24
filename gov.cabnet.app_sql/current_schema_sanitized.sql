-- gov.cabnet.app sanitized current schema export
-- Source: cabnet_gov_current_SQLbd.sql, with all INSERT/data rows removed.
-- Purpose: GitHub-safe schema reference only. Do not treat as a live backup.

-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 24, 2026 at 06:00 PM
-- Server version: 10.11.16-MariaDB
-- PHP Version: 8.4.20

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cabnet_gov`
--

-- --------------------------------------------------------

--
-- Table structure for table `bolt_raw_payloads`
--

CREATE TABLE `bolt_raw_payloads` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `source_type` varchar(32) NOT NULL DEFAULT 'bolt',
  `source_id` varchar(191) NOT NULL,
  `payload_json` longtext NOT NULL,
  `fetched_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `source_system` varchar(32) NOT NULL DEFAULT 'bolt',
  `source_endpoint` varchar(120) DEFAULT NULL,
  `external_reference` varchar(191) DEFAULT NULL,
  `payload_hash` char(64) DEFAULT NULL,
  `raw_json` longtext DEFAULT NULL,
  `captured_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Data rows removed for GitHub-safe schema-only export.
--


-- --------------------------------------------------------

--
-- Table structure for table `mapping_drivers`
--

CREATE TABLE `mapping_drivers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `source_system` varchar(32) NOT NULL DEFAULT 'bolt',
  `external_driver_id` varchar(191) DEFAULT NULL,
  `external_driver_name` varchar(255) NOT NULL,
  `edxeix_driver_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `driver_phone` varchar(80) DEFAULT NULL,
  `active_vehicle_uuid` varchar(191) DEFAULT NULL,
  `active_vehicle_plate` varchar(32) DEFAULT NULL,
  `raw_payload_json` longtext DEFAULT NULL,
  `last_seen_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Data rows removed for GitHub-safe schema-only export.
--


-- --------------------------------------------------------

--
-- Table structure for table `mapping_starting_points`
--

CREATE TABLE `mapping_starting_points` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `internal_key` varchar(100) NOT NULL,
  `label` varchar(255) NOT NULL,
  `edxeix_starting_point_id` varchar(50) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Data rows removed for GitHub-safe schema-only export.
--


-- --------------------------------------------------------

--
-- Table structure for table `mapping_vehicles`
--

CREATE TABLE `mapping_vehicles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `source_system` varchar(32) NOT NULL DEFAULT 'bolt',
  `external_vehicle_id` varchar(191) DEFAULT NULL,
  `plate` varchar(50) NOT NULL,
  `edxeix_vehicle_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `external_vehicle_name` varchar(255) DEFAULT NULL,
  `vehicle_model` varchar(255) DEFAULT NULL,
  `raw_payload_json` longtext DEFAULT NULL,
  `last_seen_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Data rows removed for GitHub-safe schema-only export.
--


-- --------------------------------------------------------

--
-- Table structure for table `normalized_bookings`
--

CREATE TABLE `normalized_bookings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `source` varchar(50) NOT NULL,
  `source_trip_id` varchar(191) DEFAULT NULL,
  `source_booking_id` varchar(191) DEFAULT NULL,
  `status` varchar(50) NOT NULL,
  `customer_type` varchar(20) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_vat_number` varchar(50) DEFAULT NULL,
  `customer_representative` varchar(255) DEFAULT NULL,
  `driver_external_id` varchar(191) DEFAULT NULL,
  `driver_name` varchar(255) DEFAULT NULL,
  `vehicle_external_id` varchar(191) DEFAULT NULL,
  `vehicle_plate` varchar(50) DEFAULT NULL,
  `starting_point_key` varchar(100) DEFAULT NULL,
  `boarding_point` text NOT NULL,
  `coordinates` varchar(255) DEFAULT NULL,
  `disembark_point` text NOT NULL,
  `drafted_at` datetime NOT NULL,
  `started_at` datetime NOT NULL,
  `ended_at` datetime DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) NOT NULL DEFAULT 'EUR',
  `broker_key` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `dedupe_hash` char(64) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `source_system` varchar(32) NOT NULL DEFAULT 'bolt',
  `external_order_id` varchar(191) DEFAULT NULL,
  `order_reference` varchar(191) DEFAULT NULL,
  `source_trip_reference` varchar(191) DEFAULT NULL,
  `driver_phone` varchar(80) DEFAULT NULL,
  `vehicle_model` varchar(255) DEFAULT NULL,
  `passenger_name` varchar(255) DEFAULT NULL,
  `lessee_name` varchar(255) DEFAULT NULL,
  `pickup_address` text DEFAULT NULL,
  `destination_address` text DEFAULT NULL,
  `order_status` varchar(80) DEFAULT NULL,
  `is_scheduled` tinyint(1) NOT NULL DEFAULT 0,
  `order_created_at` datetime DEFAULT NULL,
  `raw_payload_id` bigint(20) UNSIGNED DEFAULT NULL,
  `normalized_payload_json` longtext DEFAULT NULL,
  `raw_payload_json` longtext DEFAULT NULL,
  `edxeix_payload_json` longtext DEFAULT NULL,
  `edxeix_ready` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Data rows removed for GitHub-safe schema-only export.
--


-- --------------------------------------------------------

--
-- Table structure for table `submission_attempts`
--

CREATE TABLE `submission_attempts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `submission_job_id` bigint(20) UNSIGNED NOT NULL,
  `request_payload_json` longtext NOT NULL,
  `response_status` int(11) DEFAULT NULL,
  `response_headers_json` longtext DEFAULT NULL,
  `response_body` longtext DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `remote_reference` varchar(191) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `submission_jobs`
--

CREATE TABLE `submission_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `normalized_booking_id` bigint(20) UNSIGNED NOT NULL,
  `target_system` varchar(50) NOT NULL DEFAULT 'edxeix',
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `priority` int(11) NOT NULL DEFAULT 100,
  `available_at` datetime NOT NULL,
  `locked_at` datetime DEFAULT NULL,
  `locked_by` varchar(100) DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `retry_count` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bolt_raw_payloads`
--
ALTER TABLE `bolt_raw_payloads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_source_id` (`source_id`),
  ADD KEY `idx_source_type` (`source_type`);

--
-- Indexes for table `mapping_drivers`
--
ALTER TABLE `mapping_drivers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_external_driver_id` (`external_driver_id`),
  ADD KEY `idx_external_driver_name` (`external_driver_name`);

--
-- Indexes for table `mapping_starting_points`
--
ALTER TABLE `mapping_starting_points`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_internal_key` (`internal_key`);

--
-- Indexes for table `mapping_vehicles`
--
ALTER TABLE `mapping_vehicles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_external_vehicle_id` (`external_vehicle_id`),
  ADD KEY `idx_plate` (`plate`);

--
-- Indexes for table `normalized_bookings`
--
ALTER TABLE `normalized_bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_dedupe_hash` (`dedupe_hash`),
  ADD KEY `idx_source_trip_id` (`source_trip_id`),
  ADD KEY `idx_started_at` (`started_at`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `submission_attempts`
--
ALTER TABLE `submission_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_submission_job_id` (`submission_job_id`);

--
-- Indexes for table `submission_jobs`
--
ALTER TABLE `submission_jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status_available` (`status`,`available_at`),
  ADD KEY `idx_booking_id` (`normalized_booking_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bolt_raw_payloads`
--
ALTER TABLE `bolt_raw_payloads`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mapping_drivers`
--
ALTER TABLE `mapping_drivers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mapping_starting_points`
--
ALTER TABLE `mapping_starting_points`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mapping_vehicles`
--
ALTER TABLE `mapping_vehicles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `normalized_bookings`
--
ALTER TABLE `normalized_bookings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `submission_attempts`
--
ALTER TABLE `submission_attempts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `submission_jobs`
--
ALTER TABLE `submission_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `submission_attempts`
--
ALTER TABLE `submission_attempts`
  ADD CONSTRAINT `fk_submission_attempts_job` FOREIGN KEY (`submission_job_id`) REFERENCES `submission_jobs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `submission_jobs`
--
ALTER TABLE `submission_jobs`
  ADD CONSTRAINT `fk_submission_jobs_booking` FOREIGN KEY (`normalized_booking_id`) REFERENCES `normalized_bookings` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
