DELIMITER $$
CREATE PROCEDURE `separated_comments_win_value_UPDATE`()
BEGIN
	DROP TABLE IF EXISTS separated_comments_win_value;
    
	-- Create table with proper structure
	CREATE TABLE separated_comments_win_value (
		comment VARCHAR(255),
		digit INT,
		win_count INT DEFAULT 0,
		count INT,
		win_pct DECIMAL(5,2) DEFAULT 0.00,
		INDEX idx_comment_digit (comment, digit)
	);
    
    -- Step 1: Insert base data from separated_comment_count
    INSERT INTO separated_comments_win_value (comment, digit, count)
    SELECT comment, digit, count 
    FROM separated_comment_count 
    WHERE count >= 50;
    
    -- Step 2: Create temporary table for win counts (more efficient)
    CREATE TEMPORARY TABLE temp_win_counts AS
    SELECT 
        comment,
        digit,
        COUNT(*) as win_count
    FROM separated_comments sc
    WHERE sc.finish_position = 1
    GROUP BY comment, digit;
    
    -- Step 3: Update win counts using the temporary table
    UPDATE separated_comments_win_value scwv
    INNER JOIN temp_win_counts twc 
        ON scwv.comment = twc.comment 
        AND scwv.digit = twc.digit
    SET scwv.win_count = twc.win_count,
        scwv.win_pct = ROUND(100 * twc.win_count / scwv.count, 2);
    
    -- Clean up temporary table
    DROP TEMPORARY TABLE temp_win_counts;
END $$
DELIMITER ;