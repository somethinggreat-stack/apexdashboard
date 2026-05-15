-- =====================================================================
-- Apex Dashboard — Live database migrations
-- =====================================================================
--
-- Recommended path:
--   Deploy via cPanel. The .cpanel.yml runs `php artisan migrate --force`
--   on every deploy, which applies these changes automatically and keeps
--   the `migrations` table consistent.
--
-- If you must apply changes manually (e.g. cPanel deploy is unavailable),
-- run the blocks below IN ORDER. Each block is idempotent within itself
-- but does NOT guard against being run twice — skip any block whose
-- migration name already appears in your `migrations` table:
--
--   SELECT migration FROM migrations ORDER BY id;
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
-- responsiveness work is pure CSS / Blade. No schema changes needed.
--
-- The blocks below are everything added across the recent dashboard work,
-- in case any haven't been applied to production yet.

-- ---------------------------------------------------------------------
-- 1) credit_monitoring_security_answer on end_users
--    Migration: 2026_05_15_000001_add_credit_monitoring_security_answer_to_end_users
-- ---------------------------------------------------------------------
ALTER TABLE `end_users`
    ADD COLUMN `credit_monitoring_security_answer` TEXT NULL
    AFTER `credit_monitoring_password`;

INSERT INTO `migrations` (`migration`, `batch`)
VALUES (
    '2026_05_15_000001_add_credit_monitoring_security_answer_to_end_users',
    (SELECT COALESCE(MAX(batch), 0) + 1 FROM (SELECT batch FROM `migrations`) AS m)
);

-- ---------------------------------------------------------------------
-- 2) rounds (multi-select JSON) on end_users
--    Migration: 2026_05_15_000002_add_rounds_to_end_users
-- ---------------------------------------------------------------------
ALTER TABLE `end_users`
    ADD COLUMN `rounds` JSON NULL
    AFTER `status`;

INSERT INTO `migrations` (`migration`, `batch`)
VALUES (
    '2026_05_15_000002_add_rounds_to_end_users',
    (SELECT COALESCE(MAX(batch), 0) + 1 FROM (SELECT batch FROM `migrations`) AS m)
);

-- ---------------------------------------------------------------------
-- 3) reply / pin / star / note action columns on messages
--    Migration: 2026_05_15_000003_add_actions_to_messages
-- ---------------------------------------------------------------------
ALTER TABLE `messages`
    ADD COLUMN `reply_to_id` BIGINT UNSIGNED NULL AFTER `body`,
    ADD COLUMN `pinned_at`   TIMESTAMP NULL AFTER `reply_to_id`,
    ADD COLUMN `starred_at`  TIMESTAMP NULL AFTER `pinned_at`,
    ADD COLUMN `note`        TEXT NULL AFTER `starred_at`,
    ADD CONSTRAINT `messages_reply_to_id_foreign`
        FOREIGN KEY (`reply_to_id`) REFERENCES `messages`(`id`) ON DELETE SET NULL;

INSERT INTO `migrations` (`migration`, `batch`)
VALUES (
    '2026_05_15_000003_add_actions_to_messages',
    (SELECT COALESCE(MAX(batch), 0) + 1 FROM (SELECT batch FROM `migrations`) AS m)
);

-- =====================================================================
-- Verification queries (read-only — safe to run anytime)
-- =====================================================================
--
-- Check the end_users columns landed:
--   SHOW COLUMNS FROM `end_users` LIKE 'credit_monitoring_security_answer';
--   SHOW COLUMNS FROM `end_users` LIKE 'rounds';
--
-- Check the messages columns landed:
--   SHOW COLUMNS FROM `messages` LIKE 'reply_to_id';
--   SHOW COLUMNS FROM `messages` LIKE 'pinned_at';
--   SHOW COLUMNS FROM `messages` LIKE 'starred_at';
--   SHOW COLUMNS FROM `messages` LIKE 'note';
--
-- Confirm the migrations table is consistent:
--   SELECT migration, batch FROM `migrations`
--    WHERE migration LIKE '2026_05_15_%'
--    ORDER BY id;
