-- ============================================================
-- Backfill paid Round 1 payments for Charli Nikia's clients.
--
-- Source data:
--   Invoice 1  (2026-05-15, $48)   — 4 clients
--   Invoice 2  (2026-05-18, $132)  — 11 clients
--   Standalone (today)             — 3 clients
--
-- Total: 18 clients × $12 = $216
--
-- Uses INSERT IGNORE on the (end_user_id, round) unique index,
-- so re-running is safe — it'll skip anything already recorded.
--
-- Match is by email so name typos don't break it. If an email
-- lookup misses (e.g. you've edited the address), nothing happens
-- for that row — verify with the SELECT at the bottom.
-- ============================================================

-- Resolve Charli's BO row + the admin who owns it
SET @charli_id := (SELECT id FROM clients WHERE business_name LIKE '%Charli%' LIMIT 1);
SET @admin_id  := (SELECT admin_id FROM clients WHERE id = @charli_id);

-- =====================================================
-- Invoice 1 — May 15, 2026  ($48, 4 clients)
-- =====================================================
INSERT IGNORE INTO client_payments
    (end_user_id, round, amount, paid_at, method, notes, created_by_admin_id, created_at, updated_at)
SELECT eu.id, 1, 12.00, '2026-05-15', 'Invoice', 'Backfilled from Invoice 5/15/2026',
       @admin_id, NOW(), NOW()
FROM end_users eu
WHERE eu.client_id = @charli_id
  AND eu.email IN (
    'marymyasol@gmail.com',           -- Mary May Yasol
    'shamaria.williams@ymail.com',    -- Shamaria Williams
    'cortneywilliams84@gmail.com',    -- Cortney Williams
    'glamber.35@gmail.com'            -- Gloria HENRY MICHELLE
);

-- =====================================================
-- Invoice 2 — May 18, 2026  ($132, 11 clients)
-- =====================================================
INSERT IGNORE INTO client_payments
    (end_user_id, round, amount, paid_at, method, notes, created_by_admin_id, created_at, updated_at)
SELECT eu.id, 1, 12.00, '2026-05-18', 'Invoice', 'Backfilled from Invoice AGS-05182026-001',
       @admin_id, NOW(), NOW()
FROM end_users eu
WHERE eu.client_id = @charli_id
  AND eu.email IN (
    'brandysmyth83@yahoo.com',        -- Brandie Jervey
    'yolanda8280@yahoo.com',          -- Yolanda Gaines
    'williamsmalika305@gmail.com',    -- Malika Williams
    'struggs.lonnie@icloud.com',      -- Lonnie Struggs
    'tash.scott933@gmail.com',        -- Tashara Scotland
    'soimegato@gmail.com',            -- Soime Gato
    'nirvaaugustin0512@gmail.com',    -- Nirva Augustin
    'dericklove1@aol.com',            -- Derrick Love
    'antwionmorris1@gmail.com',       -- Antwin Morris
    'larauncewalker@yahoo.com',       -- Laraunce Walker
    'boodramtillery@yahoo.com'        -- Joella Tillery (= "Joella Rebekah" on invoice)
);

-- =====================================================
-- Standalone — Heather, Shannon, Carmen
-- Date defaults to 2026-05-29 (today). If you have actual dates,
-- run UPDATE statements after to correct.
-- =====================================================
INSERT IGNORE INTO client_payments
    (end_user_id, round, amount, paid_at, method, notes, created_by_admin_id, created_at, updated_at)
SELECT eu.id, 1, 12.00, '2026-05-29', NULL, 'Backfilled from message',
       @admin_id, NOW(), NOW()
FROM end_users eu
WHERE eu.client_id = @charli_id
  AND eu.email IN (
    'heatherlpearson@yahoo.com',       -- Heather Pearson
    'Shannondroberts2014@gmail.com',   -- Shannon Roberts
    'rekhakanhai0@gmail.com'           -- Carmen Rayo
);

-- =====================================================
-- VERIFICATION — run this after to confirm everything landed.
-- Expected: 18 rows.
-- =====================================================
SELECT eu.first_name, eu.last_name, eu.email, cp.round, cp.amount, cp.paid_at, cp.notes
FROM client_payments cp
JOIN end_users eu ON eu.id = cp.end_user_id
WHERE eu.client_id = @charli_id
ORDER BY cp.paid_at, eu.last_name;
