DELIMITER $$
CREATE PROCEDURE `daily_comment_history_UPDATE`()
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    -- Drop existing table
    DROP TABLE IF EXISTS daily_comment_history;

    -- Create optimized table structure with better indexes
    CREATE TABLE daily_comment_history (
        runner_id INT NOT NULL,
        race_id INT NOT NULL,
        meeting_date DATE NOT NULL,
        course VARCHAR(100),
        profile TEXT,
        general_features TEXT,
        specific_features TEXT,
        direction CHAR(1),
        Distance VARCHAR(20),
        race_type VARCHAR(50),
        finish_position INT,
        days_since_ran INT,
        distance_beaten DECIMAL(8,2),
        race_abbrev_name VARCHAR(100),
        starting_price VARCHAR(32),
        official_rating INT,
        value DECIMAL(10,2),
        Runner_Count INT,
        weight_pounds INT,
        jockey_claim INT,
        jockey_name VARCHAR(100),
        speed_rating INT,
        going VARCHAR(50),
        in_race_comment TEXT,
        race_title VARCHAR(200),
        country VARCHAR(50),
        prize_pos_1 DECIMAL(10,2),
        prize_pos_2 DECIMAL(10,2),
        prize_pos_3 DECIMAL(10,2),
        prize_pos_4 DECIMAL(10,2),
        last_winner_no_race VARCHAR(100),
        last_winner_name VARCHAR(100),
        last_winner_sp VARCHAR(32),
        last_winner_trainer VARCHAR(100),
        scheduled_time TIME,
        age_range VARCHAR(20),
        class VARCHAR(20),
        HCap VARCHAR(20),
        name VARCHAR(100),
        foaling_date DATE,
        form_figures VARCHAR(50),
        wt_speed_rating DECIMAL(8,2),
        legacy_speed_rating INT,
        -- Optimized indexes
        PRIMARY KEY (runner_id, race_id),
        INDEX idx_meeting_date (meeting_date),
        INDEX idx_course (course),
        INDEX idx_race_type (race_type),
        INDEX idx_finish_position (finish_position)
    ) ENGINE=InnoDB;

    -- Create temporary table for distance calculations to avoid repeated computation
    CREATE TEMPORARY TABLE temp_distance_calc AS
    SELECT DISTINCT 
        distance_yards,
        CONCAT(
            IF(distance_yards >= 1760, CONCAT(FLOOR(distance_yards / 1760), 'm'), ''), 
            IF(MOD(distance_yards, 1760) >= 220, CONCAT(FLOOR(MOD(distance_yards, 1760) / 220), 'f'), ''), 
            IF(MOD(distance_yards, 220) > 0, CONCAT(MOD(distance_yards, 220), 'y'), '')
        ) AS formatted_distance
    FROM historic_races_beta;
    
    ALTER TABLE temp_distance_calc ADD PRIMARY KEY (distance_yards);

    -- Single optimized INSERT using UNION ALL instead of two separate INSERTs
    INSERT INTO daily_comment_history
    SELECT DISTINCT
        runner_id,
        race_id,
        meeting_date,
        course,
        profile,
        general_features,
        specific_features,
        direction,
        Distance,
        race_type,
        finish_position,
        days_since_ran,
        distance_beaten,
        race_abbrev_name,
        starting_price,
        official_rating,
        value,
        Runner_Count,
        weight_pounds,
        jockey_claim,
        jockey_name,
        speed_rating,
        going,
        in_race_comment,
        race_title,
        country,
        prize_pos_1,
        prize_pos_2,
        prize_pos_3,
        prize_pos_4,
        last_winner_no_race,
        last_winner_name,
        last_winner_sp,
        last_winner_trainer,
        scheduled_time,
        age_range,
        class,
        HCap,
        name,
        foaling_date,
        form_figures,
        wt_speed_rating,
        legacy_speed_rating
    FROM (
        -- First dataset: dailyracecard14
        SELECT 
            hrunb.runner_id,
            hrunb.race_id,
            hracb.meeting_date,
            hracb.course,
            COALESCE(cf.profile, '') as profile,
            COALESCE(cf.general_features, '') as general_features,
            COALESCE(cf.specific_features, '') as specific_features,
            LEFT(hracb.direction, 1) AS direction,
            COALESCE(tdc.formatted_distance, '') AS Distance,
            hracb.race_type,
            hrunb.finish_position,
            hrunb.days_since_ran,
            hrunb.distance_beaten,
            hracb.race_abbrev_name,
            hrunb.starting_price,
            hrunb.official_rating,
            COALESCE(dracb.prize_pos_1, 0) AS value,
            hracb.num_runners AS Runner_Count,
            hrunb.weight_pounds,
            hrunb.jockey_claim,
            hrunb.jockey_name,
            COALESCE(sr.speed_rating, 0) as speed_rating,
            hracb.going,
            hrunb.in_race_comment,
            COALESCE(dracb.race_title, '') as race_title,
            COALESCE(dracb.country, '') as country,
            COALESCE(dracb.prize_pos_1, 0) as prize_pos_1,
            COALESCE(dracb.prize_pos_2, 0) as prize_pos_2,
            COALESCE(dracb.prize_pos_3, 0) as prize_pos_3,
            COALESCE(dracb.prize_pos_4, 0) as prize_pos_4,
            COALESCE(dracb.last_winner_no_race, '') as last_winner_no_race,
            COALESCE(dracb.last_winner_name, '') as last_winner_name,
            COALESCE(dracb.last_winner_sp, '') as last_winner_sp,
            COALESCE(dracb.last_winner_trainer, '') as last_winner_trainer,
            hracb.scheduled_time,
            CASE 
                WHEN hracb.max_age IS NOT NULL THEN
                    CASE 
                        WHEN hracb.max_age = hracb.min_age THEN CONCAT(hracb.min_age, 'YO ONLY')
                        ELSE CONCAT(hracb.min_age, 'YO to ', hracb.max_age, 'YO')
                    END
                ELSE CONCAT(hracb.min_age, 'YO+')
            END AS age_range,
            hracb.class,
            IF(hracb.handicap = 1, 'Handicap', 'Non-Handicap') AS HCap,
            hrunb.name,
            hrunb.foaling_date,
            hrunb.form_figures,
            ROUND(COALESCE(sr.wt_speed_rating, 0), 2) AS wt_speed_rating,
            COALESCE(hrunb.legacy_speed_rating, 0) as legacy_speed_rating
        FROM dailyracecard14 drc
        INNER JOIN historic_runners_beta hrunb ON drc.runner_id = hrunb.runner_id
        INNER JOIN historic_races_beta hracb ON hracb.race_id = hrunb.race_id
        LEFT JOIN daily_races_beta dracb ON dracb.race_id = hrunb.race_id
        LEFT JOIN sr_results sr ON hrunb.race_id = sr.race_id AND drc.runner_id = sr.runner_id
        LEFT JOIN course_features cf ON hracb.course = cf.course AND hracb.race_type = cf.race_type
        LEFT JOIN temp_distance_calc tdc ON hracb.distance_yards = tdc.distance_yards

        UNION ALL

        -- Second dataset: adv_dailyracecard14
        SELECT 
            ahrunb.runner_id,
            ahrunb.race_id,
            ahracb.meeting_date,
            ahracb.course,
            COALESCE(cf.profile, '') as profile,
            COALESCE(cf.general_features, '') as general_features,
            COALESCE(cf.specific_features, '') as specific_features,
            LEFT(ahracb.direction, 1) AS direction,
            COALESCE(tdc.formatted_distance, '') AS Distance,
            ahracb.race_type,
            ahrunb.finish_position,
            ahrunb.days_since_ran,
            ahrunb.distance_beaten,
            ahracb.race_abbrev_name,
            ahrunb.starting_price,
            ahrunb.official_rating,
            COALESCE(adracb.prize_pos_1, 0) AS value,
            ahracb.num_runners AS Runner_Count,
            ahrunb.weight_pounds,
            ahrunb.jockey_claim,
            ahrunb.jockey_name,
            COALESCE(asr.speed_rating, 0) as speed_rating,
            ahracb.going,
            ahrunb.in_race_comment,
            COALESCE(adracb.race_title, '') as race_title,
            COALESCE(adracb.country, '') as country,
            COALESCE(adracb.prize_pos_1, 0) as prize_pos_1,
            COALESCE(adracb.prize_pos_2, 0) as prize_pos_2,
            COALESCE(adracb.prize_pos_3, 0) as prize_pos_3,
            COALESCE(adracb.prize_pos_4, 0) as prize_pos_4,
            COALESCE(adracb.last_winner_no_race, '') as last_winner_no_race,
            COALESCE(adracb.last_winner_name, '') as last_winner_name,
            COALESCE(adracb.last_winner_sp, '') as last_winner_sp,
            COALESCE(adracb.last_winner_trainer, '') as last_winner_trainer,
            ahracb.scheduled_time,
            CASE 
                WHEN ahracb.max_age IS NOT NULL THEN
                    CASE 
                        WHEN ahracb.max_age = ahracb.min_age THEN CONCAT(ahracb.min_age, 'YO ONLY')
                        ELSE CONCAT(ahracb.min_age, 'YO to ', ahracb.max_age, 'YO')
                    END
                ELSE CONCAT(ahracb.min_age, 'YO+')
            END AS age_range,
            ahracb.class,
            IF(ahracb.handicap = 1, 'Handicap', 'Non-Handicap') AS HCap,
            ahrunb.name,
            ahrunb.foaling_date,
            ahrunb.form_figures,
            ROUND(COALESCE(asr.wt_speed_rating, 0), 2) AS wt_speed_rating,
            COALESCE(ahrunb.legacy_speed_rating, 0) as legacy_speed_rating
        FROM adv_dailyracecard14 adrc
        INNER JOIN historic_runners_beta ahrunb ON adrc.runner_id = ahrunb.runner_id
        INNER JOIN historic_races_beta ahracb ON ahracb.race_id = ahrunb.race_id
        LEFT JOIN daily_races_beta adracb ON adracb.race_id = ahrunb.race_id
        LEFT JOIN sr_results asr ON ahrunb.race_id = asr.race_id AND adrc.runner_id = asr.runner_id
        LEFT JOIN course_features cf ON ahracb.course = cf.course AND ahracb.race_type = cf.race_type
        LEFT JOIN temp_distance_calc tdc ON ahracb.distance_yards = tdc.distance_yards
    ) combined_data;

    -- Clean up temporary table
    DROP TEMPORARY TABLE temp_distance_calc;

    -- Add analyze table for better query planning
    ANALYZE TABLE daily_comment_history;

END $$
DELIMITER ;