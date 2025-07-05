CREATE DEFINER=`smartform`@`localhost` PROCEDURE `SR_data_UPDATE`()
BEGIN
	DROP TABLE IF EXISTS SR_data;
	CREATE TABLE SR_data AS
		SELECT 
			hracb.race_id,
			hracb.meeting_date AS meeting_date,
            time(hracb.scheduled_time) AS scheduled_time,
			hracb.course AS course,
			hracb.class AS class,
			dracb.race_type AS race_type,
            dracb.track_type AS track_type,
			hracb.going AS going,
            dracb.advanced_going AS adv_going,
            hracb.handicap AS handicap,
			hracb.num_fences AS num_fences,
			hracb.distance_yards AS distance_yards,
			hracb.winning_time_secs AS winning_time_secs,
            hrunb.weight_pounds AS weight_pounds,
            hrunb.jockey_claim AS jockey_claim,
            hrunb.stall_number AS stall_number,
			runner_id,
			name,
			sire_name,
			finish_position,
			distance_behind_winner,
			starting_price_decimal
		FROM
			historic_races_beta hracb
			JOIN daily_races_beta dracb ON (dracb.race_id = hracb.race_id)
				INNER JOIN
			historic_runners_beta hrunb ON hracb.race_id = hrunb.race_id
		WHERE hracb.race_id > 0
        AND hrunb.unfinished is null
        AND YEAR(hracb.meeting_date) between YEAR(CURDATE())-15 and YEAR(CURDATE())
        AND hracb.winning_time_secs > 1
	;
END