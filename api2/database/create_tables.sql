-- api2 MySQL schema. Create the database, then the tables, then load mongodb_data.sql.
--
--   sudo mysql -e "CREATE DATABASE IF NOT EXISTS book_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
--   sudo mysql book_store < database/create_tables.sql
--   sudo mysql book_store < database/mongodb_data.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `postal_code` varchar(255) DEFAULT NULL,
  `payment_info` json DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customers_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `publishers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `settings` json DEFAULT NULL,
  `commission_owed` decimal(14,2) NOT NULL DEFAULT 0.00,
  `commission_accrued_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `commission_paid_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `commission_balances` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `warehouses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `location` json DEFAULT NULL,
  `payment_methods` json DEFAULT NULL,
  `shipping_options` json DEFAULT NULL,
  `manager_id` varchar(255) DEFAULT NULL,
  `publisher_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `warehouses_manager_id_index` (`manager_id`),
  KEY `warehouses_publisher_id_index` (`publisher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employees` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL,
  `warehouse_id` varchar(255) DEFAULT NULL,
  `warehouse_ids` json DEFAULT NULL,
  `publisher_id` varchar(255) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employees_email_unique` (`email`),
  KEY `employees_role_index` (`role`),
  KEY `employees_warehouse_id_index` (`warehouse_id`),
  KEY `employees_publisher_id_index` (`publisher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `authors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `biography` text DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `date_of_death` date DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `authors_name_index` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dewey_code` varchar(255) DEFAULT NULL,
  `subject_title_en` varchar(255) DEFAULT NULL,
  `subject_title_ar` varchar(255) DEFAULT NULL,
  `subject_number` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `categories_dewey_code_index` (`dewey_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `books` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `author_ids` json DEFAULT NULL,
  `category_id` varchar(255) DEFAULT NULL,
  `size` varchar(255) DEFAULT NULL,
  `weight` decimal(10,3) DEFAULT NULL,
  `cover_image` text DEFAULT NULL,
  `cover_image_thumb` text DEFAULT NULL,
  `has_cover` tinyint(1) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `prices` json DEFAULT NULL,
  `pages` int unsigned DEFAULT NULL,
  `isbn` varchar(255) DEFAULT NULL,
  `publish_year` smallint unsigned DEFAULT NULL,
  `edition_number` int unsigned DEFAULT NULL,
  `binding_type` varchar(255) DEFAULT NULL,
  `paper_type` varchar(255) DEFAULT NULL,
  `publisher_id` varchar(255) DEFAULT NULL,
  `publisher_ids` json DEFAULT NULL,
  `warehouse_id` varchar(255) DEFAULT NULL,
  `stock_quantity` int NOT NULL DEFAULT 0,
  `discount_percent` decimal(8,2) DEFAULT NULL,
  `condition` varchar(255) NOT NULL DEFAULT 'new',
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `is_sold` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `books_title_index` (`title`),
  KEY `books_category_id_index` (`category_id`),
  KEY `books_has_cover_index` (`has_cover`),
  KEY `books_isbn_index` (`isbn`),
  KEY `books_publisher_id_index` (`publisher_id`),
  KEY `books_warehouse_id_index` (`warehouse_id`),
  KEY `books_condition_index` (`condition`),
  KEY `books_is_visible_index` (`is_visible`),
  KEY `books_is_sold_index` (`is_sold`),
  KEY `books_catalog_idx` (`has_cover`, `stock_quantity`, `is_visible`, `is_sold`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `author_book` (
  `author_id` varchar(255) NOT NULL,
  `book_id` varchar(255) NOT NULL,
  PRIMARY KEY (`author_id`, `book_id`),
  KEY `author_book_author_id_index` (`author_id`),
  KEY `author_book_book_id_index` (`book_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_publisher` (
  `publisher_id` varchar(255) NOT NULL,
  `book_id` varchar(255) NOT NULL,
  PRIMARY KEY (`publisher_id`, `book_id`),
  KEY `book_publisher_publisher_id_index` (`publisher_id`),
  KEY `book_publisher_book_id_index` (`book_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `carts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` varchar(255) NOT NULL,
  `items` json DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `carts_customer_id_index` (`customer_id`),
  KEY `carts_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` varchar(255) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `is_direct_sale` tinyint(1) NOT NULL DEFAULT 0,
  `employee_id` varchar(255) DEFAULT NULL,
  `warehouse_id` varchar(255) DEFAULT NULL,
  `items` json DEFAULT NULL,
  `status` varchar(255) NOT NULL,
  `stock_reserved` tinyint(1) NOT NULL DEFAULT 0,
  `books_subtotal` decimal(14,2) DEFAULT NULL,
  `shipping_fee` decimal(14,2) DEFAULT NULL,
  `shipping_method` varchar(255) DEFAULT NULL,
  `total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `currency_code` varchar(8) NOT NULL DEFAULT 'USD',
  `shipping_address` json DEFAULT NULL,
  `payment_info` json DEFAULT NULL,
  `payment_method` varchar(255) DEFAULT NULL,
  `payment_status` varchar(255) DEFAULT NULL,
  `stripe_checkout_session_id` varchar(255) DEFAULT NULL,
  `stripe_checkout_session_expires_at` bigint unsigned DEFAULT NULL,
  `platform_commission_percent` decimal(8,2) DEFAULT NULL,
  `platform_commission_amount` decimal(14,2) DEFAULT NULL,
  `publisher_payout_amount` decimal(14,2) DEFAULT NULL,
  `payout_paypal_email` varchar(255) DEFAULT NULL,
  `payout_paypal_merchant_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `orders_customer_id_index` (`customer_id`),
  KEY `orders_is_direct_sale_index` (`is_direct_sale`),
  KEY `orders_employee_id_index` (`employee_id`),
  KEY `orders_warehouse_id_index` (`warehouse_id`),
  KEY `orders_status_index` (`status`),
  KEY `orders_payment_status_index` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` varchar(255) DEFAULT NULL,
  `user_id` varchar(255) DEFAULT NULL,
  `amount` decimal(14,2) DEFAULT NULL,
  `currency` varchar(8) DEFAULT NULL,
  `method` varchar(255) DEFAULT NULL,
  `payment_method` varchar(255) DEFAULT NULL,
  `payment_status` varchar(255) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payments_order_id_index` (`order_id`),
  KEY `payments_user_id_index` (`user_id`),
  KEY `payments_transaction_id_index` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `publisher_commission_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `publisher_id` varchar(255) NOT NULL,
  `order_id` varchar(255) DEFAULT NULL,
  `type` varchar(255) NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `currency_code` varchar(8) NOT NULL DEFAULT 'USD',
  `note` text DEFAULT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `created_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `publisher_commission_entries_publisher_id_index` (`publisher_id`),
  KEY `publisher_commission_entries_order_id_index` (`order_id`),
  KEY `publisher_commission_entries_type_index` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `value` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `countries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(8) DEFAULT NULL,
  `translations` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `countries_name_index` (`name`),
  KEY `countries_code_index` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `country_id` varchar(255) NOT NULL,
  `translations` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cities_name_index` (`name`),
  KEY `cities_country_id_index` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `currencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(8) NOT NULL,
  `symbol` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `nameArabic` varchar(255) DEFAULT NULL,
  `exchangeRate` decimal(16,6) NOT NULL DEFAULT 1.000000,
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `isDefault` tinyint(1) NOT NULL DEFAULT 0,
  `countryCode` varchar(8) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `currencies_code_unique` (`code`),
  KEY `currencies_countryCode_index` (`countryCode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_24_000001_create_mysql_schema', 1
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_24_000001_create_mysql_schema'
);

SET FOREIGN_KEY_CHECKS = 1;
