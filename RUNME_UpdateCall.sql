DELIMITER $$
CREATE PROCEDURE `RUNME_UpdateCall` ()
BEGIN
CALL `smartform`.`SR_sample_races_UPDATE`();
CALL `smartform`.`SR_data_UPDATE`();
CALL `smartform`.`separated_comments_UPDATE`();
CALL `smartform`.`todays_runners_insights_UPDATE`();
CALL `smartform`.`ancestry_records_UPDATE`();

CALL `smartform`.`separated_comment_count_UPDATE`();
CALL `smartform`.`my_daily_races_UPDATE`();
CALL `smartform`.`adv_my_daily_races_UPDATE`();

CALL `smartform`.`14DayTrainerStats_UPDATE`();
CALL `smartform`.`14DayJockeyStats_UPDATE`();
CALL `smartform`.`separated_comments_win_value_UPDATE`();
CALL `smartform`.`adv_my_daily_details_tb_UPDATE`();
CALL `smartform`.`my_daily_details_tb_UPDATE`();

CALL `smartform`.`DailyRacecard14_UPDATE`();

CALL `smartform`.`daily_comment_ratings_UPDATE`();
CALL `smartform`.`SR_daily_data_UPDATE`();

CALL `smartform`.`daily_race_comment_history_UPDATE`();
END$$
DELIMITER ;