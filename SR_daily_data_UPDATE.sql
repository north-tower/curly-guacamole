CREATE DEFINER=`smartform`@`localhost` PROCEDURE `SR_daily_data_UPDATE`()
BEGIN
	DROP TABLE IF EXISTS SR_daily_data;
	CREATE TABLE SR_daily_data AS
		SELECT
				dracb.race_id AS race_id,
				dracb.course AS course,
				dracb.class AS class,
				dracb.race_type AS race_type,
                dracb.track_type AS track_type,
				dracb.advanced_going AS going,
				dracb.distance_yards AS distance_yards,
                dracb.handicap AS handicap,
                dracb.scheduled_time AS scheduled_time,
                drunb.jockey_claim AS jockey_claim,
                drunb.stall_number AS stall_number,
				drunb.weight_pounds AS weight_pounds,
                drunb.runner_id AS runner_id,
                drunb.name AS name,
                drunb.sire_name AS sire_name,
                hrunb.finish_position
				FROM
					daily_races_beta dracb
                    JOIN daily_runners_beta drunb ON (drunb.race_id = dracb.race_id)
                    LEFT JOIN historic_runners_beta hrunb ON (hrunb.race_id = dracb.race_id AND hrunb.runner_id = drunb.runner_id)
                ;

END