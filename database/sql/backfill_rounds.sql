-- ============================================================
-- Backfill end_users.rounds = ["1st Round"] for any client
-- whose Round column is empty (showing "—" on the clients list).
--
-- Idempotent: skips rows that already have a round assigned.
-- Safe to re-run.
-- ============================================================

UPDATE end_users
SET rounds = JSON_ARRAY('1st Round'),
    updated_at = NOW()
WHERE rounds IS NULL
   OR JSON_LENGTH(rounds) = 0;
