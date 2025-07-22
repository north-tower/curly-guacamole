DELIMITER $$
CREATE PROCEDURE `my_daily_races_UPDATE`()
BEGIN
DROP TABLE IF EXISTS my_daily_races;

CREATE TABLE my_daily_races AS
SELECT
	#daily_races data
	dracb.race_id,
    meeting_id,
    meeting_date,
    weather,
    meeting_status,
    meeting_abandoned_reason,
    draw_advantage,
    dracb.course,
    dracb.country,
    race_title,
    dracb.race_type,
    track_type,
    cf.direction,    
    cf.profile,
    cf.general_features,
	cf.specific_features,
    advanced_going,
    class,
    handicap,
    IF(handicap = 1, 'Handicap', 'Non-Handicap') AS HCap,
    trifecta,
    showcase,
    age_range,
	CONCAT(	IF(distance_yards>=1760,CONCAT(FLOOR(distance_yards/1760),'m'),''),
		IF(MOD(distance_yards,1760)>=220,CONCAT(FlOOR(MOD(distance_yards,1760)/220),'f'),''),
		IF(MOD(distance_yards,220)>0,CONCAT(MOD(distance_yards,220),'y'),'')) AS Distance,
    distance_yards,
    CAST(ROUND(distance_yards/220,1) AS CHAR)+0 AS distance_furlongs,
    added_money,
    penalty_value,
    scheduled_time,
    prize_pos_1,
    prize_pos_2,
    prize_pos_3,
    prize_pos_4,
    prize_pos_5,
    prize_pos_6,
    prize_pos_7,
    prize_pos_8,
    prize_pos_9,
    last_winner_no_race,
    last_winner_year,
    last_winner_runners,
    last_winner_runner_id,
    last_winner_name,
    last_winner_age,
    last_winner_bred,
    last_winner_weight,
    last_winner_trainer,
    last_winner_trainer_id,
    last_winner_jockey,
    last_winner_jockey_id,
    last_winner_sp,
    last_winner_sp_decimal,
    last_winner_betting_ranking,
    last_winner_course_winner,
    last_winner_distance_winner,
    last_winner_candd_winner,
    last_winner_beaten_favourite
FROM daily_races_beta dracb
LEFT JOIN (SELECT course, country, race_type_id, race_code, profile, general_features, specific_features, direction, straight_track_up_to,
IF(race_type = 'National_Hunt_Flat', 'N_H_Flat', race_type) AS race_type
FROM course_features) cf ON(cf.course = dracb.course AND cf.country = dracb.country AND cf.race_type = IF(dracb.race_type = 'Flat' AND dracb.track_type != 'Turf', 'All Weather Flat', dracb.race_type))
WHERE
    meeting_date = CURDATE()
ORDER BY course, scheduled_time;

END $$
DELIMITER ;