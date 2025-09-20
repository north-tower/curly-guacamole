DELIMITER $$
CREATE PROCEDURE `separated_comments_win_value_UPDATE`()
BEGIN
	DROP TABLE IF EXISTS separated_comments_win_value;
    
	CREATE TABLE separated_comments_win_value as
	SELECT
		scc.`comment`,
		scc.digit,
		(SELECT COUNT(*) 
		 FROM separated_comments sc2 
		 WHERE sc2.comment = scc.comment 
		 AND sc2.digit = scc.digit 
		 AND sc2.finish_position = 1) as win_count,
		scc.count,
		(SELECT 100 * COUNT(*) / scc.count
		 FROM separated_comments sc3 
		 WHERE sc3.comment = scc.comment 
		 AND sc3.digit = scc.digit 
		 AND sc3.finish_position = 1) as win_pct
	FROM separated_comment_count scc
	WHERE scc.count >= 50;
END $$
DELIMITER ;