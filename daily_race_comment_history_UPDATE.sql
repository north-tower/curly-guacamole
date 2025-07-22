DELIMITER $$
CREATE PROCEDURE `daily_race_comment_history_UPDATE`()
BEGIN
DROP TABLE IF EXISTS daily_race_comment_history;

CREATE TABLE daily_race_comment_history AS SELECT DISTINCT `hrunb`.`runner_id` AS `runner_id`,
    `dch`.`race_id` AS `race_id`,
    `hracb`.`meeting_date` AS `meeting_date`,
    `hracb`.`race_type` AS `race_type`,
    `hracb`.`going` AS `going`,
    `hracb`.`class` AS `class`,
    `hrunb`.`name` AS `name`,
    `hrunb`.`form_figures` AS `form_figures`,
    `hrunb`.`finish_position` AS `finish_position`,
    `hrunb`.`distance_beaten` AS `distance_beaten`,
    `hrunb`.`in_race_comment` AS `in_race_comment`,
    `hrunb`.`official_rating` AS `official_rating`,
    `sr`.`speed_rating`,
    ROUND(`sr`.`wt_speed_rating`, 2) AS `wt_speed_rating`,
    `hrunb`.`legacy_speed_rating` AS `legacy_speed_rating` FROM
    daily_comment_history dch
        LEFT JOIN
    historic_runners_beta hrunb ON (dch.race_id = hrunb.race_id)
        LEFT JOIN
    historic_races_beta hracb ON (dch.race_id = hracb.race_id)
        LEFT JOIN
    `sr_results` `sr` ON ((`dch`.`race_id` = `sr`.`race_id`)
        AND (`hrunb`.`runner_id` = `sr`.`runner_id`))
ORDER BY finish_position ASC;
END $$
DELIMITER ;