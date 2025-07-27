DELIMITER $$
CREATE PROCEDURE `adv_speed_analysis_UPDATE`()
BEGIN
	DROP TABLE IF EXISTS adv_speed_analysis;
	CREATE TABLE adv_speed_analysis as
		SELECT
			name,
			runner_id,
			meeting_date AS meeting_date_LTO,
			wt_speed_rating AS wt_speed_rating_LTO,
			MAX(CASE WHEN RowNumber = 1 THEN speed_rating END) as speed_rating_LTO,
			MAX(CASE WHEN RowNumber = 2 THEN speed_rating END) as SR_2,
			MAX(CASE WHEN RowNumber = 3 THEN speed_rating END) as SR_3,
			MAX(CASE WHEN RowNumber = 4 THEN speed_rating END) as SR_4,
            MAX(CASE WHEN RowNumber = 5 THEN speed_rating END) as SR_5,
            MAX(CASE WHEN RowNumber = 6 THEN speed_rating END) as SR_6,
			MAX(CASE WHEN RowNumber = 1 THEN class END) as class_LTO,
			MAX(CASE WHEN RowNumber = 2 THEN class END) as CL_2,
			MAX(CASE WHEN RowNumber = 3 THEN class END) as CL_3,
			MAX(CASE WHEN RowNumber = 4 THEN class END) as CL_4,
            MAX(CASE WHEN RowNumber = 5 THEN class END) as CL_5,
            MAX(CASE WHEN RowNumber = 6 THEN class END) as CL_6,
			MAX(CASE WHEN RowNumber = 1 THEN distance_furlongs END) as distance_furlongs_LTO,
			MAX(CASE WHEN RowNumber = 2 THEN distance_furlongs END) as DF_2,
			MAX(CASE WHEN RowNumber = 3 THEN distance_furlongs END) as DF_3,
			MAX(CASE WHEN RowNumber = 4 THEN distance_furlongs END) as DF_4,
            MAX(CASE WHEN RowNumber = 5 THEN distance_furlongs END) as DF_5,
            MAX(CASE WHEN RowNumber = 6 THEN distance_furlongs END) as DF_6
		FROM
			(SELECT
				hrunb.runner_id,
				hrunb.name,
				hracb.meeting_date,
				ROUND((CAST(hracb.distance_yards/220 AS CHAR) + 0), 1) as distance_furlongs,
				hracb.class,
				sr.speed_rating,
				ROUND(sr.wt_speed_rating, 3) AS wt_speed_rating,
				ROW_NUMBER() OVER(PARTITION BY hrunb.runner_id ORDER BY hracb.meeting_date DESC) AS RowNumber
			FROM fhorsitedb.adv_my_daily_details_tb amddt 
            JOIN historic_runners_beta hrunb ON (hrunb.runner_id = amddt.runner_id)
            JOIN historic_races_beta hracb ON (hrunb.race_id = hracb.race_id)
				LEFT JOIN fhorsitedb.sr_results sr ON (sr.runner_id = amddt.runner_id AND sr.race_id = hrunb.race_id)
			WHERE hrunb.runner_id IS NOT NULL
            AND (hrunb.unfinished != 'Non-Runner' OR hrunb.unfinished IS NULL)
			ORDER BY hracb.meeting_date DESC) a
		WHERE RowNumber <= 6
		GROUP BY runner_id;
END $$
DELIMITER ;