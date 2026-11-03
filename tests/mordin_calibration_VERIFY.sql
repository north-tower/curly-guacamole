-- Run after: source mordin_calibration_REPORT.sql; CALL mordin_calibration_REPORT();
-- Expected: 50–60 rows in mordin_class_calibration; compare avg_winner_sr tier gaps vs spec_mcr (paste full table into mordin_class_NOTES.md § Calibration).

SELECT COUNT(*) AS calibration_rows,
       CASE WHEN COUNT(*) BETWEEN 50 AND 60 THEN 'ok' ELSE 'check_row_count' END AS row_count_check
FROM mordin_class_calibration;

SELECT * FROM mordin_class_calibration ORDER BY mordin_tier_rank(tier_code), comp_level;

-- Level 3 snapshot: tier ladder for gap review (master plan Task 5).
SELECT tier_code, comp_level, races, avg_winner_sr, sd_winner_sr, spec_mcr
FROM mordin_class_calibration
WHERE comp_level = 3
ORDER BY mordin_tier_rank(tier_code);
