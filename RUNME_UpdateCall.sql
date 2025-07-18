DELIMITER $$
CREATE PROCEDURE `RUNME_UpdateCall` ()
BEGIN
CALL `coolwed1_WP9PN`.`SR_sample_races_UPDATE`();
CALL `coolwed1_WP9PN`.`SR_data_UPDATE`();
CALL `coolwed1_WP9PN`.`separated_comments_UPDATE`();
-- CALL `coolwed1_WP9PN`.`todays_runners_insights_UPDATE`();
CALL `coolwed1_WP9PN`.`ancestry_records_UPDATE`();

CALL `coolwed1_WP9PN`.`separated_comment_count_UPDATE`();
CALL `coolwed1_WP9PN`.`my_daily_races_UPDATE`();
CALL `coolwed1_WP9PN`.`adv_my_daily_races_UPDATE`();

CALL `coolwed1_WP9PN`.`14DayTrainerStats_UPDATE`();
CALL `coolwed1_WP9PN`.`14DayJockeyStats_UPDATE`();
CALL `coolwed1_WP9PN`.`separated_comments_win_value_UPDATE`();
CALL `coolwed1_WP9PN`.`adv_my_daily_details_tb_UPDATE`();
CALL `coolwed1_WP9PN`.`my_daily_details_tb_UPDATE`();

CALL `coolwed1_WP9PN`.`DailyRacecard14_UPDATE`();

CALL `coolwed1_WP9PN`.`daily_comment_ratings_UPDATE`();
CALL `coolwed1_WP9PN`.`SR_daily_data_UPDATE`();

CALL `coolwed1_WP9PN`.`daily_race_comment_history_UPDATE`();
END$$
DELIMITER ;