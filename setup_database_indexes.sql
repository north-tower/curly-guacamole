DELIMITER $$
CREATE PROCEDURE `setup_database_indexes`()
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '42000' BEGIN END;
    DECLARE CONTINUE HANDLER FOR 1061 BEGIN END; -- Duplicate key name error
    
    -- Set session variables for better performance during index creation
    SET SESSION sql_log_bin = 0;
    SET SESSION foreign_key_checks = 0;
    SET SESSION unique_checks = 0;
    SET SESSION autocommit = 0;
    
    START TRANSACTION;
    
    SELECT 'Creating indexes for historic_runners_beta...' as status;
    
    -- Check and create indexes for historic_runners_beta
    SELECT COUNT(*) INTO @idx_count 
    FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
    AND table_name = 'historic_runners_beta' 
    AND index_name = 'idx_runner_id';
    
    IF @idx_count = 0 THEN
        ALTER TABLE historic_runners_beta ADD INDEX idx_runner_id (runner_id);
        SELECT 'Added idx_runner_id to historic_runners_beta' as message;
    ELSE
        SELECT 'Index idx_runner_id already exists on historic_runners_beta' as message;
    END IF;
    
    SELECT COUNT(*) INTO @idx_count 
    FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
    AND table_name = 'historic_runners_beta' 
    AND index_name = 'idx_race_id';
    
    IF @idx_count = 0 THEN
        ALTER TABLE historic_runners_beta ADD INDEX idx_race_id (race_id);
        SELECT 'Added idx_race_id to historic_runners_beta' as message;
    ELSE
        SELECT 'Index idx_race_id already exists on historic_runners_beta' as message;
    END IF;
    
    SELECT 'Creating indexes for historic_races_beta...' as status;
    
    -- Check and create indexes for historic_races_beta
    SELECT COUNT(*) INTO @idx_count 
    FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
    AND table_name = 'historic_races_beta' 
    AND index_name = 'idx_race_id';
    
    IF @idx_count = 0 THEN
        ALTER TABLE historic_races_beta ADD INDEX idx_race_id (race_id);
        SELECT 'Added idx_race_id to historic_races_beta' as message;
    ELSE
        SELECT 'Index idx_race_id already exists on historic_races_beta' as message;
    END IF;
    
    SELECT 'Creating indexes for daily_races_beta...' as status;
    
    -- Check and create indexes for daily_races_beta
    SELECT COUNT(*) INTO @idx_count 
    FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
    AND table_name = 'daily_races_beta' 
    AND index_name = 'idx_race_id';
    
    IF @idx_count = 0 THEN
        ALTER TABLE daily_races_beta ADD INDEX idx_race_id (race_id);
        SELECT 'Added idx_race_id to daily_races_beta' as message;
    ELSE
        SELECT 'Index idx_race_id already exists on daily_races_beta' as message;
    END IF;
    
    SELECT 'Creating indexes for sr_results...' as status;
    
    -- Check and create composite index for sr_results
    SELECT COUNT(*) INTO @idx_count 
    FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
    AND table_name = 'sr_results' 
    AND index_name = 'idx_race_runner';
    
    IF @idx_count = 0 THEN
        ALTER TABLE sr_results ADD INDEX idx_race_runner (race_id, runner_id);
        SELECT 'Added idx_race_runner to sr_results' as message;
    ELSE
        SELECT 'Index idx_race_runner already exists on sr_results' as message;
    END IF;
    
    SELECT 'Creating indexes for course_features...' as status;
    
    -- Check and create composite index for course_features
    SELECT COUNT(*) INTO @idx_count 
    FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
    AND table_name = 'course_features' 
    AND index_name = 'idx_course_race_type';
    
    IF @idx_count = 0 THEN
        ALTER TABLE course_features ADD INDEX idx_course_race_type (course, race_type);
        SELECT 'Added idx_course_race_type to course_features' as message;
    ELSE
        SELECT 'Index idx_course_race_type already exists on course_features' as message;
    END IF;
    
    -- Additional useful indexes based on the main procedure queries
    SELECT 'Creating additional performance indexes...' as status;
    
    -- Index for dailyracecard14 if it doesn't exist
    SELECT COUNT(*) INTO @idx_count 
    FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
    AND table_name = 'dailyracecard14' 
    AND index_name = 'idx_runner_id';
    
    IF @idx_count = 0 THEN
        ALTER TABLE dailyracecard14 ADD INDEX idx_runner_id (runner_id);
        SELECT 'Added idx_runner_id to dailyracecard14' as message;
    END IF;
    
    -- Index for adv_dailyracecard14 if it doesn't exist
    SELECT COUNT(*) INTO @idx_count 
    FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
    AND table_name = 'adv_dailyracecard14' 
    AND index_name = 'idx_runner_id';
    
    IF @idx_count = 0 THEN
        ALTER TABLE adv_dailyracecard14 ADD INDEX idx_runner_id (runner_id);
        SELECT 'Added idx_runner_id to adv_dailyracecard14' as message;
    END IF;
    
    -- Index on distance_yards for temp table optimization
    SELECT COUNT(*) INTO @idx_count 
    FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
    AND table_name = 'historic_races_beta' 
    AND index_name = 'idx_distance_yards';
    
    IF @idx_count = 0 THEN
        ALTER TABLE historic_races_beta ADD INDEX idx_distance_yards (distance_yards);
        SELECT 'Added idx_distance_yards to historic_races_beta' as message;
    END IF;
    
    COMMIT;
    
    -- Reset session variables
    SET SESSION sql_log_bin = 1;
    SET SESSION foreign_key_checks = 1;
    SET SESSION unique_checks = 1;
    SET SESSION autocommit = 1;
    
    SELECT 'Database indexing setup completed successfully!' as final_status;
    
END $$
DELIMITER ;