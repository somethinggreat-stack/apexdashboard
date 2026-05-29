-- ============================================================
-- Payments feature — production migration (MySQL / MariaDB)
--
-- Adds payment configuration columns to `clients` and creates
-- three new tables: client_payments, time_entries, time_payouts.
--
-- Safe to run once on cPanel/phpMyAdmin. Re-running will fail
-- on existing columns/tables (that's intentional — it tells
-- you it's already applied).
-- ============================================================

-- 1) BO payment configuration columns
ALTER TABLE `clients`
    ADD COLUMN `compensation_model`  VARCHAR(20)    NOT NULL DEFAULT 'per_round' AFTER `monthly_fee`,
    ADD COLUMN `per_round_fee`       DECIMAL(8,2)   NULL     AFTER `compensation_model`,
    ADD COLUMN `hourly_rate`         DECIMAL(8,2)   NULL     AFTER `per_round_fee`,
    ADD COLUMN `weekly_hours_target` INT UNSIGNED   NULL     AFTER `hourly_rate`,
    ADD COLUMN `pay_cycle`           VARCHAR(20)    NULL     AFTER `weekly_hours_target`,
    ADD COLUMN `pay_cycle_anchor`    DATE           NULL     AFTER `pay_cycle`;


-- 2) Per-round payments table
CREATE TABLE `client_payments` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `end_user_id`         BIGINT UNSIGNED NOT NULL,
    `round`               TINYINT UNSIGNED NOT NULL,
    `amount`              DECIMAL(8,2)    NOT NULL,
    `paid_at`             DATE            NOT NULL,
    `method`              VARCHAR(50)     NULL,
    `notes`               TEXT            NULL,
    `created_by_admin_id` BIGINT UNSIGNED NOT NULL,
    `created_at`          TIMESTAMP NULL DEFAULT NULL,
    `updated_at`          TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `client_payments_end_user_id_round_unique` (`end_user_id`, `round`),
    KEY `client_payments_paid_at_index` (`paid_at`),
    CONSTRAINT `client_payments_end_user_id_foreign`
        FOREIGN KEY (`end_user_id`) REFERENCES `end_users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `client_payments_created_by_admin_id_foreign`
        FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 3) Hourly time entries table
CREATE TABLE `time_entries` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_id`           BIGINT UNSIGNED NOT NULL,
    `work_date`           DATE            NOT NULL,
    `hours`               DECIMAL(5,2)    NOT NULL,
    `description`         TEXT            NULL,
    `created_by_admin_id` BIGINT UNSIGNED NOT NULL,
    `created_at`          TIMESTAMP NULL DEFAULT NULL,
    `updated_at`          TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `time_entries_client_id_work_date_index` (`client_id`, `work_date`),
    CONSTRAINT `time_entries_client_id_foreign`
        FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `time_entries_created_by_admin_id_foreign`
        FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 4) Hourly payouts table
CREATE TABLE `time_payouts` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_id`           BIGINT UNSIGNED NOT NULL,
    `period_start`        DATE            NOT NULL,
    `period_end`          DATE            NOT NULL,
    `hours_in_period`     DECIMAL(7,2)    NOT NULL,
    `amount_paid`         DECIMAL(9,2)    NOT NULL,
    `paid_at`             DATE            NOT NULL,
    `method`              VARCHAR(50)     NULL,
    `notes`               TEXT            NULL,
    `created_by_admin_id` BIGINT UNSIGNED NOT NULL,
    `created_at`          TIMESTAMP NULL DEFAULT NULL,
    `updated_at`          TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `time_payouts_client_id_period_start_period_end_unique`
        (`client_id`, `period_start`, `period_end`),
    KEY `time_payouts_paid_at_index` (`paid_at`),
    CONSTRAINT `time_payouts_client_id_foreign`
        FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `time_payouts_created_by_admin_id_foreign`
        FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 5) Mark the migration as applied so artisan migrate doesn't try to re-run it.
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_05_29_120000_create_payments_tables', COALESCE(MAX(`batch`), 0) + 1
FROM `migrations`;
