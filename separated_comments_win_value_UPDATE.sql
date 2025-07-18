CREATE DEFINER=`smartform`@`localhost` PROCEDURE `separated_comments_win_value_UPDATE`()
BEGIN
	DROP TABLE IF EXISTS separated_comments_win_value;
    
	CREATE TABLE separated_comments_win_value as
	SELECT
		scc.`comment`,
		scc.digit,
		count(if(finish_position=1,1,null)) as win_count,
		count,
		100*count(if(finish_position=1,1,null))/count as win_pct
	FROM
		separated_comment_count scc
	JOIN separated_comments sc
		ON ((`sc`.`comment` = `scc`.`comment`) AND (`sc`.`digit` = `scc`.`digit`))
		
	WHERE count >= 50
	GROUP BY
		scc.digit,
		scc.`comment`
;
END