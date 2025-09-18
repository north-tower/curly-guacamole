DELIMITER $$
CREATE PROCEDURE `daily_comment_history_UPDATE`()
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    DECLARE EXIT HANDLER FOR SQLWARNING
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Drop and recreate table
    DROP TABLE IF EXISTS daily_comment_history;

	CREATE TABLE daily_comment_history AS SELECT `hrunb`.`runner_id` AS `runner_id`,
    `hrunb`.`race_id` AS `race_id`,
    `hracb`.`meeting_date` AS `meeting_date`,
    `hracb`.`course` AS `course`,
    `cf`.`profile` AS `profile`,
    `cf`.`general_features` AS `general_features`,
    `cf`.`specific_features` AS `specific_features`,    
    SUBSTRING(hracb.direction, 1, 1) AS direction,
    CONCAT(IF(hracb.distance_yards >= 1760, CONCAT(FLOOR(hracb.distance_yards / 1760), 'm'), ''), IF(MOD(hracb.distance_yards, 1760) >= 220, CONCAT(FLOOR(MOD(hracb.distance_yards, 1760) / 220),
		'f'), ''), IF(MOD(hracb.distance_yards, 220) > 0, CONCAT(MOD(hracb.distance_yards, 220), 'y'), ''))
		AS Distance,
    `hracb`.`race_type` AS `race_type`,
    `hrunb`.`finish_position` AS `finish_position`,
    `hrunb`.`days_since_ran` AS `days_since_ran`,
    `hrunb`.`distance_beaten` AS `distance_beaten`,
    `hracb`.`race_abbrev_name` AS `race_abbrev_name`,
    `hrunb`.`starting_price` AS `starting_price`,
    `hrunb`.`official_rating` AS `official_rating`,
    dracb.prize_pos_1 AS value, 
    num_runners AS Runner_Count,
    `hrunb`.`weight_pounds` AS `weight_pounds`,
    hrunb.jockey_claim AS jockey_claim,
    `hrunb`.`jockey_name` AS `jockey_name`,
    `sr`.`speed_rating`,
    `hracb`.`going` AS `going`,
    `hrunb`.`in_race_comment` AS `in_race_comment`,
    
    `dracb`.`race_title` AS `race_title`,
    `dracb`.`country` AS `country`,
    `dracb`.`prize_pos_1` AS `prize_pos_1`,
    `dracb`.`prize_pos_2` AS `prize_pos_2`,
    `dracb`.`prize_pos_3` AS `prize_pos_3`,
    `dracb`.`prize_pos_4` AS `prize_pos_4`,
    `dracb`.`last_winner_no_race` AS `last_winner_no_race`,
    `dracb`.`last_winner_name` AS `last_winner_name`,
    `dracb`.`last_winner_sp` AS `last_winner_sp`,
    `dracb`.`last_winner_trainer` AS `last_winner_trainer`,
    
    hracb.scheduled_time,
    IF(max_age IS NOT NULL, IF(max_age = min_age, CONCAT(min_age, 'YO ONLY'), CONCAT(min_age, 'YO to ', max_age, 'YO')), CONCAT(min_age, 'YO+')) 
		AS age_range,
    `hracb`.`class` AS `class`,
    IF(hracb.handicap = 1, 'Handicap', 'Non-Handicap') AS HCap,
    `hrunb`.`name` AS `name`,
    `hrunb`.`foaling_date` AS `foaling_date`,
    `hrunb`.`form_figures` AS `form_figures`,    
    ROUND(`sr`.`wt_speed_rating`, 2) AS `wt_speed_rating`,
    `hrunb`.`legacy_speed_rating` AS `legacy_speed_rating` 
    FROM
    ((`dailyracecard14` `drc`
    JOIN `historic_runners_beta` `hrunb` ON ((`drc`.`runner_id` = `hrunb`.`runner_id`)))
    LEFT JOIN `historic_races_beta` `hracb` ON ((`hracb`.`race_id` = `hrunb`.`race_id`)))
    LEFT JOIN `daily_races_beta` `dracb` ON (`dracb`.`race_id` = `hrunb`.`race_id`)
        LEFT JOIN
    `sr_results` `sr` ON ((`hrunb`.`race_id` = `sr`.`race_id`)
        AND (`drc`.`runner_id` = `sr`.`runner_id`))	
    LEFT JOIN `course_features` `cf` ON (`hracb`.`course` = `cf`.`course`) AND (`hracb`.`race_type` = `cf`.`race_type`)
     
     
        
	UNION SELECT `ahrunb`.`runner_id` AS `runner_id`,
    `ahrunb`.`race_id` AS `race_id`,
    `ahracb`.`meeting_date` AS `meeting_date`,
    `ahracb`.`course` AS `course`,
    `cf`.`profile` AS `profile`,
    `cf`.`general_features` AS `general_features`,
    `cf`.`specific_features` AS `specific_features`,  
    SUBSTRING(ahracb.direction, 1, 1) AS direction,
    CONCAT(IF(ahracb.distance_yards >= 1760, CONCAT(FLOOR(ahracb.distance_yards / 1760), 'm'), ''), IF(MOD(ahracb.distance_yards, 1760) >= 220, CONCAT(FLOOR(MOD(ahracb.distance_yards, 1760) / 220),
		'f'), ''), IF(MOD(ahracb.distance_yards, 220) > 0, CONCAT(MOD(ahracb.distance_yards, 220), 'y'), ''))
		AS Distance,
    `ahracb`.`race_type` AS `race_type`,
    `ahrunb`.`finish_position` AS `finish_position`,
    `ahrunb`.`days_since_ran` AS `days_since_ran`,
    `ahrunb`.`distance_beaten` AS `distance_beaten`,
    `ahracb`.`race_abbrev_name` AS `race_abbrev_name`,
    `ahrunb`.`starting_price` AS `starting_price`,
    `ahrunb`.`official_rating` AS `official_rating`,
    adracb.prize_pos_1 AS value, 
    num_runners AS Runner_Count,
    `ahrunb`.`weight_pounds` AS `weight_pounds`,
    ahrunb.jockey_claim AS jockey_claim,
    `ahrunb`.`jockey_name` AS `jockey_name`,
    `asr`.`speed_rating`,
    `ahracb`.`going` AS `going`,
    `ahrunb`.`in_race_comment` AS `in_race_comment`,
    `adracb`.`race_title` AS `race_title`,
    `adracb`.`country` AS `country`,
    `adracb`.`prize_pos_1` AS `prize_pos_1`,
    `adracb`.`prize_pos_2` AS `prize_pos_2`,
    `adracb`.`prize_pos_3` AS `prize_pos_3`,
    `adracb`.`prize_pos_4` AS `prize_pos_4`,
    `adracb`.`last_winner_no_race` AS `last_winner_no_race`,
    `adracb`.`last_winner_name` AS `last_winner_name`,
    `adracb`.`last_winner_sp` AS `last_winner_sp`,
    `adracb`.`last_winner_trainer` AS `last_winner_trainer`,
    
    ahracb.scheduled_time,
    IF(max_age IS NOT NULL, IF(max_age = min_age, CONCAT(min_age, 'YO ONLY'), CONCAT(min_age, 'YO to ', max_age, 'YO')), CONCAT(min_age, 'YO+')) 
		AS age_range,
    `ahracb`.`class` AS `class`,
    IF(ahracb.handicap = 1, 'Handicap', 'Non-Handicap') AS HCap,
    `ahrunb`.`name` AS `name`,
    `ahrunb`.`foaling_date` AS `foaling_date`,
    `ahrunb`.`form_figures` AS `form_figures`,    
    ROUND(`asr`.`wt_speed_rating`, 2) AS `wt_speed_rating`,
    `ahrunb`.`legacy_speed_rating` AS `legacy_speed_rating` 
    FROM `adv_dailyracecard14` `adrc`
    JOIN `historic_runners_beta` `ahrunb` ON (`adrc`.`runner_id` = `ahrunb`.`runner_id`)
    LEFT JOIN `historic_races_beta` `ahracb` ON (`ahracb`.`race_id` = `ahrunb`.`race_id`)
    LEFT JOIN `daily_races_beta` `adracb` ON (`adracb`.`race_id` = `ahrunb`.`race_id`)
	LEFT JOIN `sr_results` `asr` ON ((`ahrunb`.`race_id` = `asr`.`race_id`) AND (`adrc`.`runner_id` = `asr`.`runner_id`))
    LEFT JOIN `course_features` `cf` ON (`ahracb`.`course` = `cf`.`course`) AND (`ahracb`.`race_type` = `cf`.`race_type`)
GROUP BY `runner_id`, `race_id`
ORDER BY `meeting_date` DESC;
    
    COMMIT;
    SELECT CONCAT('daily_comment_history_UPDATE completed successfully at ', NOW()) AS status;
    
END $$
DELIMITER ;