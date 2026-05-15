-- =====================================================================
-- Apex Dashboard — Intake Form migrations (idempotent)
-- =====================================================================
--
-- Run this file in phpMyAdmin (SQL tab) against the apexgrow_apex
-- database. Every block checks INFORMATION_SCHEMA first, so it is
-- SAFE TO RUN ANY NUMBER OF TIMES. Already-applied changes will print
-- a status row instead of raising "Duplicate column" / "Duplicate
-- entry" errors.
--
-- What this file adds:
--   1) `clients` gets   intake_token (unique), intake_logo_path,
--                       intake_display_name
--   2) Existing client rows get a random 48-char intake_token via
--      SUBSTRING(CONCAT(MD5(UUID()), MD5(UUID())), 1, 48) — UUID()
--      guarantees per-row uniqueness with ~190 bits of entropy.
--   3) `end_users` gets middle_name, intake_status,
--                       intake_submitted_ip, intake_submitted_at
--   4) Migration ledger rows inserted so `php artisan migrate` will
--      not re-attempt these on the next deploy.
--
-- =====================================================================
-- 0) STATUS CHECK (read-only)
-- =====================================================================
SELECT migration AS already_applied
  FROM `migrations`
 WHERE migration IN (
       '2026_05_15_000004_add_intake_to_clients',
       '2026_05_15_000005_add_middle_name_and_intake_to_end_users'
       )
 ORDER BY id;

-- =====================================================================
-- 1) clients.intake_token  (nullable first, backfilled, then unique)
-- =====================================================================
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'clients'
       AND COLUMN_NAME  = 'intake_token'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `clients` ADD COLUMN `intake_token` VARCHAR(64) NULL AFTER `id`',
    'SELECT ''clients.intake_token already exists'' AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill any NULL tokens with random 48-char hex values.
-- Each row gets a different value because UUID() varies per call.
UPDATE `clients`
   SET `intake_token` = SUBSTRING(CONCAT(MD5(UUID()), MD5(UUID())), 1, 48)
 WHERE `intake_token` IS NULL;

-- Add the unique constraint only if not present.
SET @uniq_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'clients'
       AND INDEX_NAME   = 'clients_intake_token_unique'
);
SET @sql := IF(@uniq_exists = 0,
    'ALTER TABLE `clients` ADD UNIQUE INDEX `clients_intake_token_unique` (`intake_token`)',
    'SELECT ''clients.intake_token unique index already exists'' AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =====================================================================
-- 2) clients.intake_logo_path
-- =====================================================================
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'clients'
       AND COLUMN_NAME  = 'intake_logo_path'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `clients` ADD COLUMN `intake_logo_path` VARCHAR(255) NULL',
    'SELECT ''clients.intake_logo_path already exists'' AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =====================================================================
-- 3) clients.intake_display_name
-- =====================================================================
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'clients'
       AND COLUMN_NAME  = 'intake_display_name'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `clients` ADD COLUMN `intake_display_name` VARCHAR(255) NULL',
    'SELECT ''clients.intake_display_name already exists'' AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =====================================================================
-- 4) end_users.middle_name
-- =====================================================================
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'end_users'
       AND COLUMN_NAME  = 'middle_name'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `end_users` ADD COLUMN `middle_name` VARCHAR(100) NULL AFTER `first_name`',
    'SELECT ''end_users.middle_name already exists'' AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =====================================================================
-- 5) end_users.intake_status
-- =====================================================================
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'end_users'
       AND COLUMN_NAME  = 'intake_status'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `end_users` ADD COLUMN `intake_status` VARCHAR(32) NULL AFTER `status`',
    'SELECT ''end_users.intake_status already exists'' AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =====================================================================
-- 6) end_users.intake_submitted_ip
-- =====================================================================
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'end_users'
       AND COLUMN_NAME  = 'intake_submitted_ip'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `end_users` ADD COLUMN `intake_submitted_ip` VARCHAR(45) NULL',
    'SELECT ''end_users.intake_submitted_ip already exists'' AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =====================================================================
-- 7) end_users.intake_submitted_at
-- =====================================================================
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'end_users'
       AND COLUMN_NAME  = 'intake_submitted_at'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `end_users` ADD COLUMN `intake_submitted_at` TIMESTAMP NULL',
    'SELECT ''end_users.intake_submitted_at already exists'' AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =====================================================================
-- 8) Migration ledger entries (so php artisan migrate skips these later)
-- =====================================================================
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_05_15_000004_add_intake_to_clients',
       (SELECT COALESCE(MAX(batch), 0) + 1 FROM (SELECT batch FROM `migrations`) AS m)
 WHERE NOT EXISTS (
       SELECT 1 FROM `migrations`
        WHERE migration = '2026_05_15_000004_add_intake_to_clients'
       );

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_05_15_000005_add_middle_name_and_intake_to_end_users',
       (SELECT COALESCE(MAX(batch), 0) + 1 FROM (SELECT batch FROM `migrations`) AS m)
 WHERE NOT EXISTS (
       SELECT 1 FROM `migrations`
        WHERE migration = '2026_05_15_000005_add_middle_name_and_intake_to_end_users'
       );

-- =====================================================================
-- 9) VERIFICATION (read-only) — use SHOW so it's portable
-- =====================================================================
-- Run these one at a time. Each should return rows for the new columns.

SHOW COLUMNS FROM `clients`   LIKE 'intake\_%';
SHOW COLUMNS FROM `end_users` LIKE 'middle\_name';
SHOW COLUMNS FROM `end_users` LIKE 'intake\_%';

-- Confirm every existing client has a token (none should be NULL).
SELECT COUNT(*) AS clients_missing_token FROM `clients` WHERE `intake_token` IS NULL;

-- Show the migration ledger so you can confirm both rows are recorded.
SELECT `migration`, `batch` FROM `migrations`
 WHERE `migration` IN (
       '2026_05_15_000004_add_intake_to_clients',
       '2026_05_15_000005_add_middle_name_and_intake_to_end_users'
       )
 ORDER BY `id`;

-- Show first few tokens so you can confirm backfill worked.
SELECT `id`, `business_name`, LEFT(`intake_token`, 12) AS token_preview
  FROM `clients` ORDER BY `id` LIMIT 5;
