DELIMITER $$
CREATE PROCEDURE `separated_comments_win_value_UPDATE_SIMPLE`()
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SELECT CONCAT('Error in separated_comments_win_value_UPDATE_SIMPLE: ', SQLSTATE, ' - ', SQL_MESSAGE_TEXT) AS error_message;
        RESIGNAL;
    END;
    
    SELECT 'Starting simplified separated_comments_win_value_UPDATE...' AS status;
    
    -- Drop existing table
    DROP TABLE IF EXISTS separated_comments_win_value;
    
    -- Create table with a simpler approach - use EXISTS instead of JOIN
    CREATE TABLE separated_comments_win_value as
    SELECT
        scc.`comment`,
        scc.digit,
        (SELECT COUNT(*) 
         FROM coolwed1_wp364.separated_comments sc2 
         WHERE sc2.comment = scc.comment 
         AND sc2.digit = scc.digit 
         AND sc2.finish_position = 1) as win_count,
        scc.count,
        (SELECT 100 * COUNT(*) / scc.count
         FROM coolwed1_wp364.separated_comments sc3 
         WHERE sc3.comment = scc.comment 
         AND sc3.digit = scc.digit 
         AND sc3.finish_position = 1) as win_pct
    FROM separated_comment_count scc
    WHERE scc.count >= 50;
    
    SELECT 'separated_comments_win_value_UPDATE_SIMPLE completed successfully' AS status;
END $$
DELIMITER ;
