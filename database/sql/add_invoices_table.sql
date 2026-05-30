-- ============================================================
-- Invoices feature — production migration (MySQL / MariaDB)
--
-- Creates the `invoices` table that stores one row per generated
-- invoice (snapshot of unpaid items at the moment of generation).
--
-- Safe to run once on cPanel/phpMyAdmin. Re-running will fail
-- because the table already exists — that tells you it's applied.
-- ============================================================

CREATE TABLE `invoices` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_id`           BIGINT UNSIGNED NOT NULL,
    `invoice_number`      VARCHAR(50)     NOT NULL,
    `invoice_date`        DATE            NOT NULL,
    `items`               JSON            NOT NULL,
    `total`               DECIMAL(10,2)   NOT NULL,
    `created_by_admin_id` BIGINT UNSIGNED NOT NULL,
    `created_at`          TIMESTAMP NULL DEFAULT NULL,
    `updated_at`          TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `invoices_invoice_number_unique` (`invoice_number`),
    KEY `invoices_invoice_date_index` (`invoice_date`),
    KEY `invoices_client_id_invoice_date_index` (`client_id`, `invoice_date`),
    CONSTRAINT `invoices_client_id_foreign`
        FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `invoices_created_by_admin_id_foreign`
        FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mark the migration as applied so artisan migrate doesn't try to re-run it.
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_05_29_180000_create_invoices_table', COALESCE(MAX(`batch`), 0) + 1
FROM `migrations`;
