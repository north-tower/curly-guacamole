DELIMITER $$
CREATE PROCEDURE `RUNME_UpdateCall` ()
BEGIN
SELECT CONCAT('Starting at ', NOW()) AS status;
CALL `SR_sample_races_UPDATE`();
SELECT CONCAT('SR_sample_races_UPDATE completed at ', NOW()) AS status;

CALL `SR_data_UPDATE`();
SELECT CONCAT('SR_data_UPDATE completed at ', NOW()) AS status;

CALL `separated_comments_UPDATE`();
SELECT CONCAT('separated_comments_UPDATE completed at ', NOW()) AS status;

CALL `todays_runners_insights_UPDATE`();
SELECT CONCAT('todays_runners_insights_UPDATE completed at ', NOW()) AS status;

CALL `ancestry_records_UPDATE`();
SELECT CONCAT('ancestry_records_UPDATE completed at ', NOW()) AS status;

CALL `separated_comment_count_UPDATE`();
SELECT CONCAT('separated_comment_count_UPDATE completed at ', NOW()) AS status;

CALL `my_daily_races_UPDATE`();
SELECT CONCAT('my_daily_races_UPDATE completed at ', NOW()) AS status;

CALL `adv_my_daily_races_UPDATE`();
SELECT CONCAT('adv_my_daily_races_UPDATE completed at ', NOW()) AS status;

CALL `14DayTrainerStats_UPDATE`();
SELECT CONCAT('14DayTrainerStats_UPDATE completed at ', NOW()) AS status;

CALL `14DayJockeyStats_UPDATE`();
SELECT CONCAT('14DayJockeyStats_UPDATE completed at ', NOW()) AS status;

CALL `separated_comments_win_value_UPDATE`();
SELECT CONCAT('separated_comments_win_value_UPDATE completed at ', NOW()) AS status;

CALL `adv_my_daily_details_tb_UPDATE`();
SELECT CONCAT('adv_my_daily_details_tb_UPDATE completed at ', NOW()) AS status;

CALL `my_daily_details_tb_UPDATE`();
SELECT CONCAT('my_daily_details_tb_UPDATE completed at ', NOW()) AS status;

CALL `DailyRacecard14_UPDATE`();
SELECT CONCAT('DailyRacecard14_UPDATE completed at ', NOW()) AS status;

CALL `adv_DailyRacecard14_UPDATE`();
SELECT CONCAT('adv_DailyRacecard14_UPDATE completed at ', NOW()) AS status;

CALL `daily_comment_history_UPDATE`();
SELECT CONCAT('daily_comment_history_UPDATE completed at ', NOW()) AS status;

CALL `daily_comment_ratings_UPDATE`();
SELECT CONCAT('daily_comment_ratings_UPDATE completed at ', NOW()) AS status;

CALL `adv_daily_comment_ratings_UPDATE`();
SELECT CONCAT('adv_daily_comment_ratings_UPDATE completed at ', NOW()) AS status;

CALL `adv_speed&performance_table_UPDATE`();
SELECT CONCAT('adv_speed&performance_table_UPDATE completed at ', NOW()) AS status;

CALL `SR_daily_data_UPDATE`();
SELECT CONCAT('SR_daily_data_UPDATE completed at ', NOW()) AS status;

CALL `daily_race_comment_history_UPDATE`();
SELECT CONCAT('daily_race_comment_history_UPDATE completed at ', NOW()) AS status;

SELECT CONCAT('RUNME_UpdateCall completed at ', NOW()) AS status;
END$$
DELIMITER ;