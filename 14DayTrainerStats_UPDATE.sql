DELIMITER $$

CREATE DEFINER=`smartform_user`@`localhost` PROCEDURE `14DayTrainerStats_UPDATE`()
BEGIN
    DROP TABLE IF EXISTS my_14d_trainer_stats;

    CREATE TABLE my_14d_trainer_stats as
    SELECT trainer_id, trainer_name, 
        SUM(run) AS TnrRuns14d,
        SUM(win) AS TnrWins14d,
        SUM(placed) AS TnrPlaced14d,
        ROUND((SUM(win)/sum(run)) *100,2) AS TnrWinPct14d,
        ROUND((SUM(placed)/sum(run)) *100,2) AS TnrRTPPct14d,
        SUM(profit) AS TnrWinProfit14d
    FROM 
        (SELECT trainer_id, trainer_name, placed, 
        1 as run,
        CASE WHEN historic_runners_beta.finish_position = 1 THEN 1 ELSE 0 END win,
        starting_price_decimal - 1 winodds,
        CASE WHEN historic_runners_beta.finish_position = 1 THEN starting_price_decimal -1 ELSE -1 END profit
        FROM historic_races_beta
            INNER JOIN historic_runners_beta USING(race_id)
            INNER JOIN historic_runners_insights ON (historic_runners_beta.race_id = historic_runners_insights.race_id AND historic_runners_beta.runner_id = historic_runners_insights.runner_id)
        WHERE historic_races_beta.meeting_date >= adddate(curdate(), INTERVAL -14 DAY)
        AND in_race_comment <> 'Withdrawn'
        AND starting_price_decimal IS NOT NULL) sq
    GROUP BY trainer_id
    ORDER BY TnrPlaced14d desc, TnrRuns14d desc;
END$$

DELIMITER ;