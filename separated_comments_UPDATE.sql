DELIMITER $$
CREATE PROCEDURE `separated_comments_UPDATE`()
BEGIN
	DROP TABLE IF EXISTS separated_comments;

	CREATE TABLE separated_comments AS 
    SELECT 
		runner_id,
		race_id,
		finish_position,
		digit + 1 AS digit,
		TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(in_race_comment, ',', n.digit + 1),',', -1)) 'comment'
    FROM
		fhorsitedb.historic_runners_beta
	INNER JOIN
		(SELECT 0 digit UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15) n
		ON LENGTH(REPLACE(in_race_comment, ',', '')) <= LENGTH(in_race_comment) - n.digit
	WHERE amended_position IS NULL
		AND in_race_comment NOT LIKE 'non%runner'
	ORDER BY runner_id , race_id , n.digit
;
END $$
DELIMITER ;