-- Spec matrix vs observed winner speed ratings (5-year window, tiered races only).
-- Requires: Task 1 mordin_tier_rank, Task 2 mordin_class_matrix, Task 3 mordin_race_class.

DELIMITER $$
DROP PROCEDURE IF EXISTS `mordin_calibration_REPORT`$$
CREATE PROCEDURE `mordin_calibration_REPORT`()
BEGIN
    DROP TABLE IF EXISTS mordin_class_calibration;
    CREATE TABLE mordin_class_calibration AS
    SELECT
        mrc.tier_code,
        mrc.comp_level,
        COUNT(*) AS races,
        ROUND(AVG(mrc.winner_speed_rating), 1) AS avg_winner_sr,
        ROUND(STDDEV_SAMP(mrc.winner_speed_rating), 1) AS sd_winner_sr,
        MAX(m.mcr) AS spec_mcr
    FROM mordin_race_class mrc
    LEFT JOIN mordin_class_matrix m ON m.tier_code = mrc.tier_code AND m.comp_level = mrc.comp_level
    WHERE mrc.winner_speed_rating IS NOT NULL
      AND mrc.tier_code IS NOT NULL AND mrc.tier_code <> 'UNQ'
      AND mrc.meeting_date >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)
    GROUP BY mrc.tier_code, mrc.comp_level;

    SELECT * FROM mordin_class_calibration ORDER BY mordin_tier_rank(tier_code), comp_level;
END $$
DELIMITER ;
