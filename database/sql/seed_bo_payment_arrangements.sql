-- ============================================================
-- Seed Payment Arrangements for the four existing BOs.
-- Run AFTER add_payments_tables.sql.
--
-- Rates from user:
--   Charli Nikia      → per_round, $12
--   Kenisha Johnson   → per_round, $12
--   Victoria Love     → per_round, $15
--   Clinecea Phillips → hourly, $5.00/hr, 30 hrs/wk, bi-weekly
--
-- Matched by `business_name` LIKE patterns to survive capitalization
-- and surname-variation typos. Re-run-safe: it just overwrites the
-- config with the same values.
-- ============================================================

-- Charli Nikia — $12/round
UPDATE `clients`
SET `compensation_model`  = 'per_round',
    `per_round_fee`       = 12.00,
    `hourly_rate`         = NULL,
    `weekly_hours_target` = NULL,
    `pay_cycle`           = NULL,
    `pay_cycle_anchor`    = NULL,
    `updated_at`          = NOW()
WHERE `business_name` LIKE '%Charli%';

-- Kenisha Johnson — $12/round
UPDATE `clients`
SET `compensation_model`  = 'per_round',
    `per_round_fee`       = 12.00,
    `hourly_rate`         = NULL,
    `weekly_hours_target` = NULL,
    `pay_cycle`           = NULL,
    `pay_cycle_anchor`    = NULL,
    `updated_at`          = NOW()
WHERE `business_name` LIKE '%Kenisha%';

-- Victoria Love — $15/round
UPDATE `clients`
SET `compensation_model`  = 'per_round',
    `per_round_fee`       = 15.00,
    `hourly_rate`         = NULL,
    `weekly_hours_target` = NULL,
    `pay_cycle`           = NULL,
    `pay_cycle_anchor`    = NULL,
    `updated_at`          = NOW()
WHERE `business_name` LIKE '%Victoria%';

-- Clinecea Phillips — $5.00/hr, 30 hrs/wk, bi-weekly
-- pay_cycle_anchor set to a recent Monday so the calendar lines up.
-- VA can change the anchor + cycle in Payment Settings on the Payments tab
-- if you'd rather use monthly or a different start date.
UPDATE `clients`
SET `compensation_model`  = 'hourly',
    `per_round_fee`       = NULL,
    `hourly_rate`         = 5.00,
    `weekly_hours_target` = 30,
    `pay_cycle`           = 'biweekly',
    `pay_cycle_anchor`    = '2026-05-18',
    `updated_at`          = NOW()
WHERE `business_name` LIKE '%Clinecea%'
   OR `business_name` LIKE '%Clincea%'
   OR `business_name` LIKE '%Clinccea%';
