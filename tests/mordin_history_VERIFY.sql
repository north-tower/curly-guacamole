-- Run after: source mordin_history_UPDATE.sql; CALL mordin_history_UPDATE();
-- Sanity checks from master plan Task 3 (manual review of G1 sample and trainer top 10).

SELECT COUNT(*) AS races, SUM(tier_code IS NULL) AS no_tier, SUM(race_mcr IS NULL) AS no_mcr
FROM mordin_race_class;

SELECT comp_level, COUNT(*) AS n FROM mordin_race_class GROUP BY comp_level ORDER BY comp_level;

SELECT tier_code, COUNT(*) AS n FROM mordin_race_class GROUP BY tier_code ORDER BY mordin_tier_rank(tier_code);

SELECT r.race_title, r.tier_code, r.comp_level, r.race_mcr, p.finish_pos, p.lengths_to_winner, p.mordin_class_rating
FROM mordin_race_class r JOIN mordin_runner_perf p ON p.race_id = r.race_id
WHERE r.tier_code = 'G1' ORDER BY r.meeting_date DESC, p.finish_pos LIMIT 10;

SELECT * FROM mordin_trainer_group_sr ORDER BY rolling_5yr_group_strike_rate DESC LIMIT 10;
