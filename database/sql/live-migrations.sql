-- =====================================================================
-- Apex Dashboard — Live database migrations (idempotent)
-- =====================================================================
--
-- Recommended path:
--   Deploy via cPanel. The .cpanel.yml runs `php artisan migrate --force`
--   on every deploy, which applies these changes automatically and keeps
--   the `migrations` table consistent.
--
-- This file is the manual fallback. Every block checks whether the
-- change already exists before applying it, so it is SAFE TO RUN
-- REPEATEDLY in phpMyAdmin or any MySQL client. Re-running will print
-- "OK already applied" rows instead of raising duplicate-column /
-- duplicate-key errors.
--
-- =====================================================================
-- THIS TURN'S WORK (Status Report tab + WhatsApp read receipts + mobile)
-- =====================================================================
--
-- NO SQL CHANGES REQUIRED FOR THIS TURN.
--
-- The Status Report tab is generated entirely from existing process_steps
-- data. The WhatsApp-style read-receipt ticks read from the existing
-- messages.admin_read_at / messages.client_read_at columns. The mobile
-- responsiveness work is pure CSS / Blade.
--
-- The blocks below are everything added across the recent dashboard work,
-- in case any haven't been applied to production yet.

-- ---------------------------------------------------------------------
-- 0) STATUS: what's already applied? (read-only)
-- ---------------------------------------------------------------------
SELECT migration AS already_applied
  FROM `migrations`
 WHERE migration IN (
       '2026_05_15_000001_add_credit_monitoring_security_answer_to_end_users',
       '2026_05_15_000002_add_rounds_to_end_users',
       '2026_05_15_000003_add_actions_to_messages'
       )
 ORDER BY id;

-- ---------------------------------------------------------------------
-- 1) end_users.credit_monitoring_security_answer
-- ---------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'end_users'
       AND COLUMN_NAME  = 'credit_monitoring_security_answer'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `end_users` ADD COLUMN `credit_monitoring_security_answer` TEXT NULL AFTER `credit_monitoring_password`',
    'SELECT ''end_users.credit_monitoring_security_answer already exists'' AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_05_15_000001_add_credit_monitoring_security_answer_to_end_users',
       (SELECT COALESCE(MAX(batch), 0) + 1 FROM (SELECT batch FROM `migrations`) AS m)
 WHERE NOT EXISTS (
       SELECT 1 FROM `migrations`
        WHERE migration = '2026_05_15_000001_add_credit_monitoring_security_answer_to_end_users'
       );

-- ---------------------------------------------------------------------
-- 2) end_users.rounds (JSON multi-select)
-- ---------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'end_users'
       AND COLUMN_NAME  = 'rounds'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `end_users` ADD COLUMN `rounds` JSON NULL AFTER `status`',
    'SELECT ''end_users.rounds already exists'' AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_05_15_000002_add_rounds_to_end_users',
       (SELECT COALESCE(MAX(batch), 0) + 1 FROM (SELECT batch FROM `migrations`) AS m)
 WHERE NOT EXISTS (
       SELECT 1 FROM `migrations`
        WHERE migration = '2026_05_15_000002_add_rounds_to_end_users'
       );

-- ---------------------------------------------------------------------
-- 3) messages: reply_to_id / pinned_at / starred_at / note (+ FK)
-- ---------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'messages'
       AND COLUMN_NAME  = 'reply_to_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `messages` ADD COLUMN `reply_to_id` BIGINT UNSIGNED NULL AFTER `body`',
    'SELECT ''messages.reply_to_id already exists'' AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'messages'
       AND COLUMN_NAME  = 'pinned_at'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `messages` ADD COLUMN `pinned_at` TIMESTAMP NULL AFTER `reply_to_id`',
    'SELECT ''messages.pinned_at already exists'' AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'messages'
       AND COLUMN_NAME  = 'starred_at'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `messages` ADD COLUMN `starred_at` TIMESTAMP NULL AFTER `pinned_at`',
    'SELECT ''messages.starred_at already exists'' AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'messages'
       AND COLUMN_NAME  = 'note'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `messages` ADD COLUMN `note` TEXT NULL AFTER `starred_at`',
    'SELECT ''messages.note already exists'' AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Foreign key on reply_to_id (only if it doesn't already exist)
SET @fk_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA    = DATABASE()
       AND TABLE_NAME      = 'messages'
       AND CONSTRAINT_NAME = 'messages_reply_to_id_foreign'
       AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE `messages` ADD CONSTRAINT `messages_reply_to_id_foreign` FOREIGN KEY (`reply_to_id`) REFERENCES `messages`(`id`) ON DELETE SET NULL',
    'SELECT ''messages_reply_to_id_foreign already exists'' AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_05_15_000003_add_actions_to_messages',
       (SELECT COALESCE(MAX(batch), 0) + 1 FROM (SELECT batch FROM `migrations`) AS m)
 WHERE NOT EXISTS (
       SELECT 1 FROM `migrations`
        WHERE migration = '2026_05_15_000003_add_actions_to_messages'
       );

-- =====================================================================
-- Verification (read-only — safe to run anytime)
-- =====================================================================
SELECT 'end_users.credit_monitoring_security_answer' AS object,
       IF(COUNT(*) = 1, 'present', 'MISSING') AS state
  FROM INFORMATION_SCHEMA.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME   = 'end_users'
   AND COLUMN_NAME  = 'credit_monitoring_security_answer'
UNION ALL
SELECT 'end_users.rounds',
       IF(COUNT(*) = 1, 'present', 'MISSING')
  FROM INFORMATION_SCHEMA.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME   = 'end_users'
   AND COLUMN_NAME  = 'rounds'
UNION ALL
SELECT 'messages.reply_to_id',
       IF(COUNT(*) = 1, 'present', 'MISSING')
  FROM INFORMATION_SCHEMA.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME   = 'messages'
   AND COLUMN_NAME  = 'reply_to_id'
UNION ALL
SELECT 'messages.pinned_at',
       IF(COUNT(*) = 1, 'present', 'MISSING')
  FROM INFORMATION_SCHEMA.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME   = 'messages'
   AND COLUMN_NAME  = 'pinned_at'
UNION ALL
SELECT 'messages.starred_at',
       IF(COUNT(*) = 1, 'present', 'MISSING')
  FROM INFORMATION_SCHEMA.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME   = 'messages'
   AND COLUMN_NAME  = 'starred_at'
UNION ALL
SELECT 'messages.note',
       IF(COUNT(*) = 1, 'present', 'MISSING')
  FROM INFORMATION_SCHEMA.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME   = 'messages'
   AND COLUMN_NAME  = 'note'
UNION ALL
SELECT 'messages.reply_to_id FK',
       IF(COUNT(*) = 1, 'present', 'MISSING')
  FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
 WHERE TABLE_SCHEMA    = DATABASE()
   AND TABLE_NAME      = 'messages'
   AND CONSTRAINT_NAME = 'messages_reply_to_id_foreign'
   AND CONSTRAINT_TYPE = 'FOREIGN KEY';
