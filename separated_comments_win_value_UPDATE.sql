DELIMITER $$
CREATE PROCEDURE `separated_comments_win_value_UPDATE`()
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SELECT CONCAT('Error in separated_comments_win_value_UPDATE: ', SQLSTATE, ' - ', SQL_MESSAGE_TEXT) AS error_message;
        RESIGNAL;
    END;
    
    -- Check if required tables exist
    SELECT 'Checking table existence...' AS status;
    
    -- Check separated_comment_count table
    SELECT COUNT(*) AS separated_comment_count_rows FROM separated_comment_count;
    
    -- Check coolwed1_wp364.separated_comments table
    SELECT COUNT(*) AS separated_comments_rows FROM coolwed1_wp364.separated_comments;
    
    SELECT 'Dropping existing table...' AS status;
	DROP TABLE IF EXISTS separated_comments_win_value;
    
    SELECT 'Creating separated_comments_win_value table...' AS status;
	CREATE TABLE separated_comments_win_value as
	SELECT
		scc.`comment`,
		scc.digit,
		count(if(finish_position=1,1,null)) as win_count,
		count,
		100*count(if(finish_position=1,1,null))/count as win_pct
	FROM
		separated_comment_count scc
	JOIN coolwed1_wp364.separated_comments sc
		ON ((`sc`.`comment` = `scc`.`comment`) AND (`sc`.`digit` = `scc`.`digit`))
		
	WHERE count >= 50
	GROUP BY
		scc.digit,
		scc.`comment`
;
    
    SELECT 'separated_comments_win_value_UPDATE completed successfully' AS status;
END $$
DELIMITER ;