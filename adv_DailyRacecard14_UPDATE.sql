DELIMITER $$
CREATE PROCEDURE `adv_DailyRacecard14_UPDATE`()
BEGIN

	DROP TABLE IF EXISTS adv_DailyRacecard14;
	CREATE TABLE adv_DailyRacecard14 as
	SELECT
			concat(race_id, runner_id) as PK,
            race_id, runner_id,
			DATE_FORMAT(amdd.meeting_date, "%d-%m-%Y") As Date,
            TIME_FORMAT(amdd.scheduled_time, "%H:%i") AS Time,
			amdd.course, 
			-- calculate the number of furlongs to one decimal place from the distance_yards
			ROUND(amdd.distance_yards/220,1) AS Furlongs,
            amdd.Distance,
			amdd.cloth_number, amdd.stall_number, amdd.name, amdd.foaling_date, amdd.trainer_name, amdd.jockey_name , amdd.forecast_price,  amdd.forecast_price_decimal,
			mts.TnrRuns14d, mts.TnrWins14d, mts.TnrPlaced14d, mts.TnrWinPct14d, mts.TnrRTPPct14d, mts.TnrWinProfit14d, 
            mjs.JkyRuns14d, mjs.JkyWins14d, mjs.JkyPlaced14d, mjs.JkyWinPct14d, mjs.JkyPlcPct14d, mjs.JkyWinProfit14d,
            (mjs.JkyPlcPct14d + mts.TnrRTPPct14d) as TnrJkyPlacePct
	FROM  adv_my_daily_details_tb amdd
		LEFT OUTER JOIN my_14d_trainer_stats mts ON (amdd.trainer_id = mts.trainer_id)
        LEFT OUTER JOIN my_14d_jockey_stats mjs ON (amdd.jockey_id = mjs.jockey_id);

END $$
DELIMITER ;