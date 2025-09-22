DELIMITER $$
CREATE PROCEDURE `daily_comment_history_UPDATE`()
BEGIN
DROP TABLE IF EXISTS daily_comment_history;

-- Create table with proper indexes first
CREATE TABLE daily_comment_history (
    runner_id INT,
    race_id INT,
    meeting_date DATE,
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
    INDEX idx_runner_race (runner_id, race_id),
    INDEX idx_meeting_date (meeting_date)
);

-- Insert data from dailyracecard14 (first part of UNION)
INSERT INTO daily_comment_history
SELECT 
    hrunb.runner_id,
    hrunb.race_id,
    hracb.meeting_date,
    hracb.course,
    cf.profile,
    cf.general_features,
    cf.specific_features,    
    SUBSTRING(hracb.direction, 1, 1) AS direction,
    CONCAT(IF(hracb.distance_yards >= 1760, CONCAT(FLOOR(hracb.distance_yards / 1760), 'm'), ''), 
           IF(MOD(hracb.distance_yards, 1760) >= 220, CONCAT(FLOOR(MOD(hracb.distance_yards, 1760) / 220), 'f'), ''), 
           IF(MOD(hracb.distance_yards, 220) > 0, CONCAT(MOD(hracb.distance_yards, 220), 'y'), '')) AS Distance,
    hracb.race_type,
    hrunb.finish_position,
    hrunb.days_since_ran,
    hrunb.distance_beaten,
    hracb.race_abbrev_name,
    hrunb.starting_price,
    hrunb.official_rating,
    dracb.prize_pos_1 AS value, 
    num_runners AS Runner_Count,
    hrunb.weight_pounds,
    hrunb.jockey_claim,
    hrunb.jockey_name,
    sr.speed_rating,
    hracb.going,
    hrunb.in_race_comment,
    dracb.race_title,
    dracb.country,
    dracb.prize_pos_1,
    dracb.prize_pos_2,
    dracb.prize_pos_3,
    dracb.prize_pos_4,
    dracb.last_winner_no_race,
    dracb.last_winner_name,
    dracb.last_winner_sp,
    dracb.last_winner_trainer,
    hracb.scheduled_time,
    IF(max_age IS NOT NULL, 
       IF(max_age = min_age, CONCAT(min_age, 'YO ONLY'), CONCAT(min_age, 'YO to ', max_age, 'YO')), 
       CONCAT(min_age, 'YO+')) AS age_range,
    hracb.class,
    IF(hracb.handicap = 1, 'Handicap', 'Non-Handicap') AS HCap,
    hrunb.name,
    hrunb.foaling_date,
    hrunb.form_figures,    
    ROUND(sr.wt_speed_rating, 2) AS wt_speed_rating,
    hrunb.legacy_speed_rating 
FROM dailyracecard14 drc
JOIN historic_runners_beta hrunb ON drc.runner_id = hrunb.runner_id
LEFT JOIN historic_races_beta hracb ON hracb.race_id = hrunb.race_id
LEFT JOIN daily_races_beta dracb ON dracb.race_id = hrunb.race_id
LEFT JOIN sr_results sr ON hrunb.race_id = sr.race_id AND drc.runner_id = sr.runner_id
LEFT JOIN course_features cf ON hracb.course = cf.course AND hracb.race_type = cf.race_type;

-- Insert data from adv_dailyracecard14 (second part of UNION)
INSERT INTO daily_comment_history
SELECT 
    ahrunb.runner_id,
    ahrunb.race_id,
    ahracb.meeting_date,
    ahracb.course,
    cf.profile,
    cf.general_features,
    cf.specific_features,  
    SUBSTRING(ahracb.direction, 1, 1) AS direction,
    CONCAT(IF(ahracb.distance_yards >= 1760, CONCAT(FLOOR(ahracb.distance_yards / 1760), 'm'), ''), 
           IF(MOD(ahracb.distance_yards, 1760) >= 220, CONCAT(FLOOR(MOD(ahracb.distance_yards, 1760) / 220), 'f'), ''), 
           IF(MOD(ahracb.distance_yards, 220) > 0, CONCAT(MOD(ahracb.distance_yards, 220), 'y'), '')) AS Distance,
    ahracb.race_type,
    ahrunb.finish_position,
    ahrunb.days_since_ran,
    ahrunb.distance_beaten,
    ahracb.race_abbrev_name,
    ahrunb.starting_price,
    ahrunb.official_rating,
    adracb.prize_pos_1 AS value, 
    num_runners AS Runner_Count,
    ahrunb.weight_pounds,
    ahrunb.jockey_claim,
    ahrunb.jockey_name,
    asr.speed_rating,
    ahracb.going,
    ahrunb.in_race_comment,
    adracb.race_title,
    adracb.country,
    adracb.prize_pos_1,
    adracb.prize_pos_2,
    adracb.prize_pos_3,
    adracb.prize_pos_4,
    adracb.last_winner_no_race,
    adracb.last_winner_name,
    adracb.last_winner_sp,
    adracb.last_winner_trainer,
    ahracb.scheduled_time,
    IF(max_age IS NOT NULL, 
       IF(max_age = min_age, CONCAT(min_age, 'YO ONLY'), CONCAT(min_age, 'YO to ', max_age, 'YO')), 
       CONCAT(min_age, 'YO+')) AS age_range,
    ahracb.class,
    IF(ahracb.handicap = 1, 'Handicap', 'Non-Handicap') AS HCap,
    ahrunb.name,
    ahrunb.foaling_date,
    ahrunb.form_figures,    
    ROUND(asr.wt_speed_rating, 2) AS wt_speed_rating,
    ahrunb.legacy_speed_rating 
FROM adv_dailyracecard14 adrc
JOIN historic_runners_beta ahrunb ON adrc.runner_id = ahrunb.runner_id
LEFT JOIN historic_races_beta ahracb ON ahracb.race_id = ahrunb.race_id
LEFT JOIN daily_races_beta adracb ON adracb.race_id = ahrunb.race_id
LEFT JOIN sr_results asr ON ahrunb.race_id = asr.race_id AND adrc.runner_id = asr.runner_id
LEFT JOIN course_features cf ON ahracb.course = cf.course AND ahracb.race_type = cf.race_type;

-- Remove duplicates and order
DELETE d1 FROM daily_comment_history d1
INNER JOIN daily_comment_history d2 
WHERE d1.runner_id = d2.runner_id 
AND d1.race_id = d2.race_id 
AND d1.meeting_date < d2.meeting_date;

END $$
DELIMITER ;