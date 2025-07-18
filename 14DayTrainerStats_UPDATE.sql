CREATE DEFINER=`root`@`localhost` PROCEDURE `14DayTrainerStats_UPDATE`()
BEGIN
	DROP TABLE IF EXISTS my_14d_trainer_stats;

	CREATE TABLE my_14d_trainer_stats as
	SELECT trainer_id, trainer_name, 
		-- sum the run column from the sub query
		SUM(run) AS TnrRuns14d,
		-- sum the win column from the sub query
		SUM(win) AS TnrWins14d,
		-- sum the placed column from the sub query
		SUM(placed) AS TnrPlaced14d,
		-- calculate the win percentage to 2 decimal places
		ROUND((SUM(win)/sum(run)) *100,2) AS TnrWinPct14d,
		-- calculate the place percentage to 2 decimal places
		ROUND((SUM(placed)/sum(run)) *100,2) AS TnrRTPPct14d,
		-- sum the profit column from the sub query
		SUM(profit) AS TnrWinProfit14d
	FROM 
		-- using a sub query to select data from the join historic_races and historic runners for the last 14 days
		(SELECT trainer_id, trainer_name, placed, 
		-- create a default column with a value of 1 for each row selected
		1 as run,
		-- create a column where the value is 1 on rows where the horse has won otherwise 0 
		CASE WHEN historic_runners_beta.finish_position = 1 THEN 1 ELSE 0 END win,
		-- create a column converting the decimal odds to win odds by subtracting 1
		starting_price_decimal - 1 winodds,
		-- create a column with the win odds if the horse has won or -1 if it lost
		CASE WHEN historic_runners_beta.finish_position = 1 THEN starting_price_decimal -1 ELSE -1 END profit
		FROM historic_races_beta
			INNER JOIN historic_runners_beta USING(race_id)
			INNER JOIN historic_runners_insights ON (historic_runners_beta.race_id = historic_runners_insights.race_id AND historic_runners_beta.runner_id = historic_runners_insights.runner_id)
		WHERE historic_races_beta.meeting_date >= adddate(curdate(), INTERVAL -14 DAY)
		AND in_race_comment <> 'Withdrawn'
		AND starting_price_decimal IS NOT NULL) sq
	GROUP BY trainer_id
	ORDER BY TnrPlaced14d desc, TnrRuns14d desc;
END