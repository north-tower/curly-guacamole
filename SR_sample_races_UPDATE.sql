CREATE DEFINER=`smartform_user`@`localhost` PROCEDURE `SR_sample_races_UPDATE`()
BEGIN
	DROP TABLE IF EXISTS SR_sample_races;
	CREATE TABLE SR_sample_races AS
			SELECT
                hracb.race_id AS race_id,
				hracb.course AS course,
                hracb.meeting_date AS meeting_date,
				hracb.class AS class,
				dracb.race_type AS race_type,
                dracb.track_type AS track_type,
				hracb.going AS going,
                dracb.advanced_going AS adv_going,
                hracb.handicap AS handicap,
				hracb.distance_yards AS distance_yards,
				hracb.winning_time_secs AS winning_time_secs,
                #hracb.num_fences AS num_fences,
                #hrunb.weight_pounds AS weight_of_winner,
                #hrunb.age AS age_of_winner,
				hrunb.min_age AS min_age
				FROM
					(SELECT 
						race_id AS race_id,
						meeting_date AS meeting_date,
						course AS course,
						class AS class,
						race_type AS race_type,
						going AS going,
                        handicap AS handicap,
						num_fences AS num_fences,
						distance_yards AS distance_yards,
						winning_time_secs AS winning_time_secs
					FROM
						historic_races_beta hracb
					WHERE class IS NOT NULL
					AND YEAR(meeting_date) between YEAR(CURDATE())-15 and YEAR(CURDATE())
					AND race_id > 0
					AND class < 9
					#AND num_runners > 2
					) hracb
				JOIN daily_races_beta dracb ON (dracb.race_id = hracb.race_id)
				JOIN (SELECT race_id, MIN(age) AS min_age, age, weight_pounds
					FROM historic_runners_beta
					where finish_position is not null
					group by race_id
                    HAVING min_age > 2
					order by finish_position asc
					) hrunb ON hracb.race_id = hrunb.race_id
        ;
END