CREATE DEFINER=`smartform`@`localhost` PROCEDURE `separated_comment_count_UPDATE`()
BEGIN
	DROP TABLE IF EXISTS separated_comment_count;

	CREATE TABLE separated_comment_count as
		SELECT
			sc.digit,
			sc.`comment`,
			count(sc.`comment`) as count
		FROM smartform.separated_comments sc
		GROUP BY
			sc.digit,
			sc.`comment`
;
END