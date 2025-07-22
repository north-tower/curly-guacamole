DELIMITER $$
CREATE PROCEDURE `RUNME_UpdateCall` ()
BEGIN
CALL `SR_sample_races_UPDATE`();
CALL `SR_data_UPDATE`();
CALL `separated_comments_UPDATE`();
CALL `todays_runners_insights_UPDATE`();
CALL `ancestry_records_UPDATE`();

CALL `separated_comment_count_UPDATE`();
CALL `my_daily_races_UPDATE`();
CALL `adv_my_daily_races_UPDATE`();

CALL `14DayTrainerStats_UPDATE`();
CALL `14DayJockeyStats_UPDATE`();
CALL `separated_comments_win_value_UPDATE`();
CALL `adv_my_daily_details_tb_UPDATE`();
CALL `my_daily_details_tb_UPDATE`();

CALL `DailyRacecard14_UPDATE`();

CALL `daily_comment_ratings_UPDATE`();
CALL `SR_daily_data_UPDATE`();

CALL `daily_race_comment_history_UPDATE`();
END$$
DELIMITER ;