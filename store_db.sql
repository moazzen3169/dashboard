-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Sep 21, 2025 at 09:46 AM
-- Server version: 8.2.0
-- PHP Version: 8.2.13

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------

--
-- Table structure for table `buyers`
--

DROP TABLE IF EXISTS `buyers`;
CREATE TABLE IF NOT EXISTS `buyers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_phone` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `buyers_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `buyers`
--

INSERT INTO `buyers` (`id`, `name`, `contact_phone`, `contact_email`) VALUES
(1, 'هادی', NULL, NULL),
(2, 'مهیار', NULL, NULL),
(3, 'احمد', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

DROP TABLE IF EXISTS `purchases`;
CREATE TABLE IF NOT EXISTS `purchases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `buyer_id` int unsigned NOT NULL,
  `product_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `total_price` decimal(18,2) GENERATED ALWAYS AS ((`unit_price` * `quantity`)) STORED,
  `purchase_date` date NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `purchases_buyer_id_purchase_date_index` (`buyer_id`,`purchase_date`),
  CONSTRAINT `purchases_buyer_id_foreign` FOREIGN KEY (`buyer_id`) REFERENCES `buyers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchases`
--

INSERT INTO `purchases` (`id`, `buyer_id`, `product_name`, `unit_price`, `quantity`, `purchase_date`, `notes`) VALUES
(1, 1, 'سرهم', 800.00, 10, '2025-09-01', NULL),
(2, 2, 'سرهم', 1500.00, 4, '2025-09-01', NULL),
(3, 2, 'باتری', 2000.00, 10, '2025-09-01', NULL),
(4, 1, 'باتری', 10000.00, 15000, '2025-09-01', NULL),
(5, 1, 'سرهم', 100000.00, 10, '2025-08-01', NULL),
(6, 3, 'باتری', 1600.00, 6, '2025-09-01', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `buyer_id` int unsigned NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `previous_balance` decimal(18,2) NOT NULL DEFAULT 0.00,
  `current_charges` decimal(18,2) NOT NULL DEFAULT 0.00,
  `payments_applied` decimal(18,2) NOT NULL DEFAULT 0.00,
  `balance_due` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','issued','paid','partial','void') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `issued_at` timestamp NULL DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `invoices_buyer_period_unique` (`buyer_id`,`period_start`,`period_end`),
  PRIMARY KEY (`id`),
  KEY `invoices_buyer_id_status_index` (`buyer_id`,`status`),
  CONSTRAINT `invoices_buyer_id_foreign` FOREIGN KEY (`buyer_id`) REFERENCES `buyers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

DROP TABLE IF EXISTS `invoice_items`;
CREATE TABLE IF NOT EXISTS `invoice_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint unsigned NOT NULL,
  `purchase_id` bigint unsigned DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(15,3) NOT NULL DEFAULT 1.000,
  `unit_price` decimal(15,2) NOT NULL,
  `line_total` decimal(18,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `invoice_items_invoice_id_index` (`invoice_id`),
  KEY `invoice_items_purchase_id_index` (`purchase_id`),
  CONSTRAINT `invoice_items_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `invoice_items_purchase_id_foreign` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE IF NOT EXISTS `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `buyer_id` int unsigned NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `payment_date` date NOT NULL,
  `method` enum('cash','card','bank','check','other') COLLATE utf8mb4_unicode_ci DEFAULT 'cash',
  `reference_type` enum('invoice','purchase','manual') COLLATE utf8mb4_unicode_ci DEFAULT 'manual',
  `reference_id` bigint unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `payments_buyer_id_payment_date_index` (`buyer_id`,`payment_date`),
  KEY `payments_reference_type_reference_id_index` (`reference_type`,`reference_id`),
  CONSTRAINT `payments_buyer_id_foreign` FOREIGN KEY (`buyer_id`) REFERENCES `buyers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `buyer_id`, `amount`, `payment_date`, `method`, `reference_type`, `reference_id`, `notes`) VALUES
(1, 1, 5555.00, '2025-09-19', 'bank', 'manual', NULL, 'پرداخت دستی ثبت شده از داشبورد');

-- --------------------------------------------------------

--
-- Table structure for table `invoice_payments`
--

DROP TABLE IF EXISTS `invoice_payments`;
CREATE TABLE IF NOT EXISTS `invoice_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint unsigned NOT NULL,
  `payment_id` bigint unsigned NOT NULL,
  `applied_amount` decimal(18,2) NOT NULL,
  `applied_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_payments_unique` (`invoice_id`,`payment_id`),
  CONSTRAINT `invoice_payments_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `invoice_payments_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `buyer_monthly_balances`
--

DROP TABLE IF EXISTS `buyer_monthly_balances`;
CREATE TABLE IF NOT EXISTS `buyer_monthly_balances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `buyer_id` int unsigned NOT NULL,
  `period_end` date NOT NULL,
  `total_charges_to_date` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_payments_to_date` decimal(18,2) NOT NULL DEFAULT 0.00,
  `balance_to_date` decimal(18,2) NOT NULL DEFAULT 0.00,
  `snapshot_generated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `buyer_monthly_balances_unique` (`buyer_id`,`period_end`),
  CONSTRAINT `buyer_monthly_balances_buyer_id_foreign` FOREIGN KEY (`buyer_id`) REFERENCES `buyers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- View structure for view `buyer_ledger_view`
--

DROP VIEW IF EXISTS `buyer_ledger_view`;
CREATE ALGORITHM=MERGE SQL SECURITY DEFINER VIEW `buyer_ledger_view` AS
SELECT
    b.id AS buyer_id,
    b.name AS buyer_name,
    d.ledger_date,
    d.charge_amount,
    d.payment_amount,
    SUM(d.charge_amount - d.payment_amount) OVER (PARTITION BY b.id ORDER BY d.ledger_date ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS running_balance
FROM `buyers` b
JOIN (
    SELECT p.buyer_id, p.purchase_date AS ledger_date, p.total_price AS charge_amount, 0.00 AS payment_amount
    FROM `purchases` p
    UNION ALL
    SELECT pay.buyer_id, pay.payment_date AS ledger_date, 0.00 AS charge_amount, pay.amount AS payment_amount
    FROM `payments` pay
) AS d ON d.buyer_id = b.id;

SET FOREIGN_KEY_CHECKS = 1;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
