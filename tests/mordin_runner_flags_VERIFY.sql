-- Run after: source mordin_runner_flags_UPDATE.sql; CALL mordin_runner_flags_UPDATE();
-- Expected: leaks = 0; flag aggregates mostly non-zero; today's card titles match tier codes.

SELECT
    SUM(outclassed_handicapper = 1) AS outclassed,
    SUM(outclassed_handicapper IS NULL) AS outclassed_unknown,
    SUM(eliminate_juvenile) AS juv_elim,
    SUM(downgrade_northern_form) AS northern,
    SUM(high_value_maiden_placing) AS clear_2nd,
    SUM(yarmouth_newmarket_pocket) AS yar_pocket,
    SUM(salisbury_lambourn_pocket) AS sal_pocket,
    SUM(debut_penalty) AS debut_pen,
    SUM(meets_class_requirement = 1) AS class_ok,
    SUM(trainer_group_elite) AS elite_trainer
FROM mordin_runner_flags;

SELECT COUNT(*) AS leaks
FROM mordin_runner_flags f
JOIN mordin_runner_perf p ON p.runner_id = f.runner_id AND p.race_id = f.race_id
WHERE f.meeting_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  AND f.career_runs > (
    SELECT COUNT(*) FROM mordin_runner_perf q
    WHERE q.runner_id = f.runner_id AND q.meeting_date < f.meeting_date
);

SELECT f.race_id, f.runner_id, f.meeting_date, f.race_tier_code, f.outclassed_handicapper,
       f.debut_penalty, f.trainer_group_elite, dracb.race_title
FROM mordin_runner_flags f
JOIN daily_races_beta dracb ON dracb.race_id = f.race_id
WHERE f.meeting_date = CURDATE()
ORDER BY f.race_id
LIMIT 40;
