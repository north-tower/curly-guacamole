DELIMITER $$
CREATE PROCEDURE `DailyRacecard14_UPDATE`()
BEGIN

	DROP TABLE IF EXISTS dailyracecard14;
	CREATE TABLE dailyracecard14 as
	SELECT
			concat(race_id, runner_id) as PK,
            race_id, runner_id,
			DATE_FORMAT(mdd.meeting_date, "%d-%m-%Y") As Date,
            TIME_FORMAT(mdd.scheduled_time, "%H:%i") AS Time,
			mdd.course, 
			-- calculate the number of furlongs to one decimal place from the distance_yards
			ROUND(mdd.distance_yards/220,1) AS Furlongs,
            mdd.Distance,
			mdd.cloth_number, mdd.stall_number, mdd.name, mdd.foaling_date, mdd.trainer_name, mdd.jockey_name , mdd.forecast_price,  mdd.forecast_price_decimal,
			mts.TnrRuns14d, mts.TnrWins14d, mts.TnrPlaced14d, mts.TnrWinPct14d, mts.TnrRTPPct14d, mts.TnrWinProfit14d, 
            mjs.JkyRuns14d, mjs.JkyWins14d, mjs.JkyPlaced14d, mjs.JkyWinPct14d, mjs.JkyPlcPct14d, mjs.JkyWinProfit14d,
            (mjs.JkyPlcPct14d + mts.TnrRTPPct14d) as TnrJkyPlacePct
	FROM  my_daily_details mdd
		LEFT OUTER JOIN my_14d_trainer_stats mts ON (mdd.trainer_id = mts.trainer_id)
        LEFT OUTER JOIN my_14d_jockey_stats mjs ON (mdd.jockey_id = mjs.jockey_id);

END $$
DELIMITER ;