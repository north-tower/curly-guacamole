DELIMITER $$
CREATE PROCEDURE `todays_runners_insights_UPDATE`()
BEGIN
	DROP TABLE IF EXISTS todays_runners_insights;

	CREATE TABLE todays_runners_insights as
    SELECT 
		dri.*,
        `drunb`.`foaling_date` AS `foaling_date`,
        `drunb`.`colour` AS `colour`,
        `drunb`.`form_figures` AS `form_figures`,
        `drunb`.`form_type` AS `form_type`,
        `drunb`.`cloth_number` AS `cloth_number`,
        `drunb`.`stall_number` AS `stall_number`,
        `drunb`.`long_handicap` AS `long_handicap`,
        `drunb`.`adjusted_rating` AS `adjusted_rating`,
        `drunb`.`trainer_name` AS `trainer_name`,
        `drunb`.`trainer_id` AS `trainer_id`,
        `drunb`.`owner_name` AS `owner_name`,
        `drunb`.`jockey_name` AS `jockey_name`,
        `drunb`.`jockey_id` AS `jockey_id`,
        `drunb`.`jockey_colours` AS `jockey_colours`,
        `drunb`.`dam_name` AS `dam_name`,
        `drunb`.`dam_id` AS `dam_id`,
        `drunb`.`dam_cname` AS `dam_cname`,
        `drunb`.`dam_year_born` AS `dam_year_born`,
        `drunb`.`sire_name` AS `sire_name`,
        `drunb`.`sire_id` AS `sire_id`,
        `drunb`.`sire_cname` AS `sire_cname`,
        `drunb`.`sire_year_born` AS `sire_year_born`,
        `drunb`.`dam_sire_name` AS `dam_sire_name`,
        `drunb`.`dam_sire_id` AS `dam_sire_id`,
        `drunb`.`dam_sire_cname` AS `dam_sire_cname`,
        `drunb`.`dam_sire_year_born` AS `dam_sire_year_born`,
        `drunb`.`forecast_price` AS `forecast_price`,
        `drunb`.`forecast_price_decimal` AS `forecast_price_decimal`,
        `drunb`.`days_since_ran` AS `days_since_ran`,
        `drunb`.`days_since_ran_flat` AS `days_since_ran_flat`,
        `drunb`.`days_since_ran_jumps` AS `days_since_ran_jumps`,
        `drunb`.`weight_penalty` AS `weight_penalty`,
        `drunb`.`tack_hood` AS `tack_hood`,
        `drunb`.`tack_visor` AS `tack_visor`,
        `drunb`.`tack_blinkers` AS `tack_blinkers`,
        `drunb`.`tack_eye_shield` AS `tack_eye_shield`,
        `drunb`.`tack_eye_cover` AS `tack_eye_cover`,
        `drunb`.`tack_cheek_piece` AS `tack_cheek_piece`,
        `drunb`.`tack_pacifiers` AS `tack_pacifiers`,
        `drunb`.`tack_tongue_strap` AS `tack_tongue_strap`,
        `drunb`.`wind_surgery_declared` AS `wind_surgery_declared`,
        `drunb`.`course_winner` AS `course_winner`,
        `drunb`.`distance_winner` AS `distance_winner`,
        `drunb`.`candd_winner` AS `candd_winner`,
        `drunb`.`beaten_favourite` AS `beaten_favourite`
    FROM
		`daily_runners_insights` dri
        LEFT JOIN daily_runners_beta drunb ON (drunb.race_id = dri.race_id) AND (drunb.runner_id = dri.runner_id)
    WHERE
        (dri.`meeting_date` = CURDATE());

END $$
DELIMITER ;