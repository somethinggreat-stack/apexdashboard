-- ============================================================
-- Backfill missing process_steps so clients no longer show
-- "Incomplete" purely because nothing was logged for the week
-- they should already be on.
--
-- Idempotent: skips clients that already have a step for that
-- week. Safe to re-run.
--
-- Schedule used (matches the Incomplete badge logic):
--   day  1+ : Week 1 step expected
--   day  8+ : Week 2 step expected
--   day 15+ : Week 3 step expected
--   day 22+ : Week 4 step expected
--
-- step_date = LEAST(start_date + week offset, CURDATE())
-- created_by_admin_id = the admin who owns the client's BO
-- ============================================================

-- WEEK 1 backfill
INSERT INTO process_steps
    (end_user_id, round, week, step_type, step_date,
     created_by_admin_id, created_at, updated_at)
SELECT
    eu.id,
    1,
    1,
    'ex_tu_eq_letters_generated',
    LEAST(DATE_ADD(eu.start_date, INTERVAL 1 DAY), CURDATE()),
    c.admin_id,
    NOW(),
    NOW()
FROM end_users eu
JOIN clients c ON c.id = eu.client_id
WHERE NOT EXISTS (
    SELECT 1 FROM process_steps ps
    WHERE ps.end_user_id = eu.id AND ps.week = 1
);

-- WEEK 2 backfill (only clients past day 7)
INSERT INTO process_steps
    (end_user_id, round, week, step_type, step_date,
     created_by_admin_id, created_at, updated_at)
SELECT
    eu.id,
    1,
    2,
    'tu_ex_call_followups',
    LEAST(DATE_ADD(eu.start_date, INTERVAL 8 DAY), CURDATE()),
    c.admin_id,
    NOW(),
    NOW()
FROM end_users eu
JOIN clients c ON c.id = eu.client_id
WHERE DATEDIFF(CURDATE(), eu.start_date) + 1 > 7
  AND NOT EXISTS (
        SELECT 1 FROM process_steps ps
        WHERE ps.end_user_id = eu.id AND ps.week = 2
  );

-- WEEK 3 backfill (only clients past day 14)
INSERT INTO process_steps
    (end_user_id, round, week, step_type, step_date,
     created_by_admin_id, created_at, updated_at)
SELECT
    eu.id,
    1,
    3,
    'aggressive_bureau_followup',
    LEAST(DATE_ADD(eu.start_date, INTERVAL 15 DAY), CURDATE()),
    c.admin_id,
    NOW(),
    NOW()
FROM end_users eu
JOIN clients c ON c.id = eu.client_id
WHERE DATEDIFF(CURDATE(), eu.start_date) + 1 > 14
  AND NOT EXISTS (
        SELECT 1 FROM process_steps ps
        WHERE ps.end_user_id = eu.id AND ps.week = 3
  );

-- WEEK 4 backfill (only clients past day 21)
INSERT INTO process_steps
    (end_user_id, round, week, step_type, step_date,
     created_by_admin_id, created_at, updated_at)
SELECT
    eu.id,
    1,
    4,
    'pull_latest_report',
    LEAST(DATE_ADD(eu.start_date, INTERVAL 22 DAY), CURDATE()),
    c.admin_id,
    NOW(),
    NOW()
FROM end_users eu
JOIN clients c ON c.id = eu.client_id
WHERE DATEDIFF(CURDATE(), eu.start_date) + 1 > 21
  AND NOT EXISTS (
        SELECT 1 FROM process_steps ps
        WHERE ps.end_user_id = eu.id AND ps.week = 4
  );
