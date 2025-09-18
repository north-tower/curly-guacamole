DELIMITER $$
CREATE PROCEDURE `RUNME_UpdateCall` ()
BEGIN
    -- Log start time
    SELECT CONCAT('Starting RUNME_UpdateCall at ', NOW()) AS status;
    
    -- Group 1: Core data updates
    SELECT 'Starting SR_sample_races_UPDATE...' AS status;
    CALL `SR_sample_races_UPDATE`();
    SELECT 'SR_sample_races_UPDATE completed' AS status;
    
    SELECT 'Starting SR_data_UPDATE...' AS status;
    CALL `SR_data_UPDATE`();
    SELECT 'SR_data_UPDATE completed' AS status;
    
    SELECT 'Starting separated_comments_UPDATE...' AS status;
    CALL `separated_comments_UPDATE`();
    SELECT 'separated_comments_UPDATE completed' AS status;
    
    SELECT 'Starting todays_runners_insights_UPDATE...' AS status;
    CALL `todays_runners_insights_UPDATE`();
    SELECT 'todays_runners_insights_UPDATE completed' AS status;
    
    SELECT 'Starting ancestry_records_UPDATE...' AS status;
    CALL `ancestry_records_UPDATE`();
    SELECT 'ancestry_records_UPDATE completed' AS status;

    -- Group 2: Comment and race updates
    SELECT 'Starting separated_comment_count_UPDATE...' AS status;
    CALL `separated_comment_count_UPDATE`();
    SELECT 'separated_comment_count_UPDATE completed' AS status;
    
    SELECT 'Starting my_daily_races_UPDATE...' AS status;
    CALL `my_daily_races_UPDATE`();
    SELECT 'my_daily_races_UPDATE completed' AS status;
    
    SELECT 'Starting adv_my_daily_races_UPDATE...' AS status;
    CALL `adv_my_daily_races_UPDATE`();
    SELECT 'adv_my_daily_races_UPDATE completed' AS status;

    -- Group 3: Stats and details
    SELECT 'Starting 14DayTrainerStats_UPDATE...' AS status;
    CALL `14DayTrainerStats_UPDATE`();
    SELECT '14DayTrainerStats_UPDATE completed' AS status;
    
    SELECT 'Starting 14DayJockeyStats_UPDATE...' AS status;
    CALL `14DayJockeyStats_UPDATE`();
    SELECT '14DayJockeyStats_UPDATE completed' AS status;
    
    SELECT 'Starting separated_comments_win_value_UPDATE...' AS status;
    CALL `separated_comments_win_value_UPDATE_SIMPLE`();
    SELECT 'separated_comments_win_value_UPDATE completed' AS status;
    
    SELECT 'Starting adv_my_daily_details_tb_UPDATE...' AS status;
    CALL `adv_my_daily_details_tb_UPDATE`();
    SELECT 'adv_my_daily_details_tb_UPDATE completed' AS status;
    
    SELECT 'Starting my_daily_details_tb_UPDATE...' AS status;
    CALL `my_daily_details_tb_UPDATE`();
    SELECT 'my_daily_details_tb_UPDATE completed' AS status;

    -- Group 4: Racecard updates
    SELECT 'Starting DailyRacecard14_UPDATE...' AS status;
    CALL `DailyRacecard14_UPDATE`();
    SELECT 'DailyRacecard14_UPDATE completed' AS status;
    
    SELECT 'Starting adv_DailyRacecard14_UPDATE...' AS status;
    CALL `adv_DailyRacecard14_UPDATE`();
    SELECT 'adv_DailyRacecard14_UPDATE completed' AS status;

    -- Group 5: Comment history and ratings
    SELECT 'Starting daily_comment_history_UPDATE...' AS status;
    CALL `daily_comment_history_UPDATE`();
    SELECT 'daily_comment_history_UPDATE completed' AS status;
    
    SELECT 'Starting daily_comment_ratings_UPDATE...' AS status;
    CALL `daily_comment_ratings_UPDATE`();
    SELECT 'daily_comment_ratings_UPDATE completed' AS status;
    
    SELECT 'Starting adv_daily_comment_ratings_UPDATE...' AS status;
    CALL `adv_daily_comment_ratings_UPDATE`();
    SELECT 'adv_daily_comment_ratings_UPDATE completed' AS status;
    
    SELECT 'Starting SR_daily_data_UPDATE...' AS status;
    CALL `SR_daily_data_UPDATE`();
    SELECT 'SR_daily_data_UPDATE completed' AS status;

    SELECT 'Starting daily_race_comment_history_UPDATE...' AS status;
    CALL `daily_race_comment_history_UPDATE`();
    SELECT 'daily_race_comment_history_UPDATE completed' AS status;
    
    SELECT CONCAT('RUNME_UpdateCall completed successfully at ', NOW()) AS status;
    
END$$
DELIMITER ;