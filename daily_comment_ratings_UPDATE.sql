DELIMITER $$

CREATE DEFINER=`smartform_user`@`localhost` PROCEDURE `daily_comment_ratings_UPDATE`()
BEGIN
    DROP TABLE IF EXISTS daily_comment_ratings;
    CREATE TABLE daily_comment_ratings AS
        SELECT
            n.runner_id,
            round(sum_win_pct/count_win_pct,2) comment_rating,
            round(count_win_pct*100*(1-1/(0.5+power(((count_win_pct+13.4)*0.06),3.11)))/count_runner,2) comment_reliability_factor,
            sum_win_pct,
            count_runner,
            count_win_pct
        FROM
            (SELECT
                sc.runner_id,
                sum(win_pct) sum_win_pct,
                count(sc.runner_id) count_runner,
                count(win_pct) count_win_pct
            FROM
                dailyracecard14 dr
            JOIN
                separated_comments sc
                ON (dr.runner_id = sc.runner_id)
            LEFT JOIN
                separated_comments_win_value scwv
                ON ((sc.comment = scwv.comment) AND (sc.digit = scwv.digit))
            GROUP BY
                sc.runner_id) n;
END$$

DELIMITER ;