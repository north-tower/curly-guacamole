DELIMITER $$
CREATE PROCEDURE `points_published_picks_daily_UPDATE`()
BEGIN
    DECLARE v_meeting_date DATE DEFAULT CURDATE();

    CREATE TABLE IF NOT EXISTS `points_engine_published_picks` (
        `race_id` bigint(20) unsigned NOT NULL,
        `meeting_date` date NOT NULL,
        `win_horse` varchar(255) NOT NULL DEFAULT '',
        `place_horses` text,
        `ew_simple_horse` varchar(255) NOT NULL DEFAULT '',
        `ew_edge_horse` varchar(255) NOT NULL DEFAULT '',
        `saved_at` datetime NOT NULL,
        `source` varchar(32) NOT NULL DEFAULT 'cron_daily',
        PRIMARY KEY (`race_id`, `meeting_date`),
        KEY `meeting_date` (`meeting_date`)
    );

    DROP TEMPORARY TABLE IF EXISTS `tmp_points_trainer_course`;
    CREATE TEMPORARY TABLE `tmp_points_trainer_course` AS
    SELECT
        hrunb.trainer_name,
        hracb.course,
        ROUND(
            100 * SUM(CASE WHEN CAST(hrunb.finish_position AS UNSIGNED) = 1 THEN 1 ELSE 0 END) / COUNT(*),
            1
        ) AS win_pct
    FROM historic_runners_beta hrunb
    INNER JOIN historic_races_beta hracb ON hracb.race_id = hrunb.race_id
    WHERE hracb.meeting_date >= DATE_SUB(v_meeting_date, INTERVAL 5 YEAR)
      AND hracb.meeting_date < v_meeting_date
      AND hrunb.finish_position REGEXP '^[0-9]+$'
    GROUP BY hrunb.trainer_name, hracb.course;

    DROP TEMPORARY TABLE IF EXISTS `tmp_points_scored`;
    CREATE TEMPORARY TABLE `tmp_points_scored` AS
    SELECT
        base.*
    FROM (
        SELECT
            sp.race_id,
            v_meeting_date AS meeting_date,
            sp.runner_id,
            sp.name AS horse_name,
            dracb.race_type,
            dracb.age_range,
            CASE
                WHEN LOWER(IFNULL(dracb.race_type, '')) LIKE '%hurdle%'
                  OR LOWER(IFNULL(dracb.race_type, '')) LIKE '%chase%'
                  OR LOWER(IFNULL(dracb.race_type, '')) LIKE '%n_h_flat%'
                  OR LOWER(IFNULL(dracb.race_type, '')) LIKE '%nh_flat%'
                  OR LOWER(IFNULL(dracb.race_type, '')) LIKE '%national hunt%'
                THEN 0 ELSE 1
            END AS is_flat,
            CASE
                WHEN sp.age = 2
                  AND (
                    LOWER(IFNULL(dracb.race_type, '')) NOT LIKE '%hurdle%'
                    AND LOWER(IFNULL(dracb.race_type, '')) NOT LIKE '%chase%'
                    AND LOWER(IFNULL(dracb.race_type, '')) NOT LIKE '%n_h_flat%'
                    AND LOWER(IFNULL(dracb.race_type, '')) NOT LIKE '%nh_flat%'
                    AND LOWER(IFNULL(dracb.race_type, '')) NOT LIKE '%national hunt%'
                  )
                  AND (
                    LOWER(IFNULL(dracb.age_range, '')) LIKE '%2%'
                    OR LOWER(IFNULL(dracb.age_range, '')) = '2'
                  )
                THEN 1 ELSE 0
            END AS is_2yo_flat,
            CASE
                WHEN sp.foaling_date IS NULL OR sp.foaling_date IN ('', '0000-00-00') THEN 0
                ELSE
                    LEAST(5, GREATEST(-8,
                        CASE MONTH(sp.foaling_date)
                            WHEN 1 THEN 2 WHEN 2 THEN 4 WHEN 3 THEN 4 WHEN 4 THEN 2
                            WHEN 5 THEN 0 WHEN 6 THEN -2 WHEN 7 THEN -3 WHEN 8 THEN -4
                            WHEN 9 THEN -5 WHEN 10 THEN -6 WHEN 11 THEN -6 WHEN 12 THEN -6
                            ELSE 0
                        END
                        + CASE
                            WHEN MONTH(v_meeting_date) BETWEEN 3 AND 6 AND MONTH(sp.foaling_date) IN (2, 3) THEN 1
                            WHEN MONTH(v_meeting_date) BETWEEN 3 AND 6 AND MONTH(sp.foaling_date) >= 5 THEN -1
                            WHEN MONTH(v_meeting_date) BETWEEN 7 AND 10 AND MONTH(sp.foaling_date) = 1 THEN -1
                            WHEN MONTH(v_meeting_date) BETWEEN 7 AND 10 AND MONTH(sp.foaling_date) = 4 THEN -1
                            WHEN MONTH(v_meeting_date) BETWEEN 7 AND 10 AND MONTH(sp.foaling_date) >= 5 THEN -2
                            ELSE 0
                          END
                    ))
            END AS maturity_edge_score,
            CASE
                WHEN nr.runner_id IS NOT NULL THEN 1
                ELSE 0
            END AS is_non_runner,
            CASE
                WHEN sp.forecast_price_decimal IS NOT NULL
                  AND sp.forecast_price_decimal > 1
                THEN sp.forecast_price_decimal
                ELSE NULL
            END AS odds_decimal,
            LEAST(
                CASE
                    WHEN NOT (
                        (sp.form_figures IS NOT NULL AND TRIM(sp.form_figures) != '' AND TRIM(sp.form_figures) NOT IN ('-', '—', '0'))
                        OR sp.SR_LTO IS NOT NULL
                        OR sp.SR_2 IS NOT NULL
                        OR sp.SR_3 IS NOT NULL
                        OR (sp.days_since_ran IS NOT NULL AND sp.days_since_ran > 0 AND sp.days_since_ran < 800)
                    )
                    AND NOT (
                        IFNULL(sp.fhorsite_rating, 0) >= 76
                        AND IFNULL(sp.fhorsite_rating_reliability, 0) >= 55
                    )
                    THEN 52.0
                    ELSE 100.0
                END,
                GREATEST(0, ROUND(
                50.0
                + IF(sp.fhorsite_rating IS NOT NULL, (sp.fhorsite_rating - 70.0) * 0.35, 0)
                + IF(sp.fhorsite_rating_reliability IS NOT NULL, (sp.fhorsite_rating_reliability - 50.0) * 0.08, 0)
                + CASE
                    WHEN sp.SR_LTO IS NOT NULL OR sp.SR_2 IS NOT NULL OR sp.SR_3 IS NOT NULL THEN
                        (
                            (
                                IFNULL(sp.SR_LTO, 0) + IFNULL(sp.SR_2, 0) + IFNULL(sp.SR_3, 0)
                            ) / NULLIF(
                                IF(sp.SR_LTO IS NOT NULL, 1, 0)
                                + IF(sp.SR_2 IS NOT NULL, 1, 0)
                                + IF(sp.SR_3 IS NOT NULL, 1, 0),
                                0
                            ) - 70.0
                        ) * 0.12
                    ELSE 0
                  END
                + CASE
                    WHEN (
                        LOWER(IFNULL(dracb.race_type, '')) NOT LIKE '%hurdle%'
                        AND LOWER(IFNULL(dracb.race_type, '')) NOT LIKE '%chase%'
                        AND LOWER(IFNULL(dracb.race_type, '')) NOT LIKE '%n_h_flat%'
                        AND LOWER(IFNULL(dracb.race_type, '')) NOT LIKE '%nh_flat%'
                        AND LOWER(IFNULL(dracb.race_type, '')) NOT LIKE '%national hunt%'
                    ) AND sp.draw_bias_pct IS NOT NULL
                    THEN (sp.draw_bias_pct - 10.0) * 0.10
                    ELSE 0
                  END
                + IF(sp.TnrWinPct14d IS NOT NULL, (sp.TnrWinPct14d - 12.0) * 0.18, 0)
                + IF(tc.win_pct IS NOT NULL, (tc.win_pct - 10.0) * 0.20, 0)
                + IF(sp.TnrJkyPlacePct IS NOT NULL, (sp.TnrJkyPlacePct - 50.0) * 0.10, 0)
                - GREATEST(0, IFNULL(sp.days_since_ran, 0) - 35.0) * 0.08
                - GREATEST(0, IFNULL(sp.class_diff, 0)) * 0.30
                + GREATEST(0, -IFNULL(sp.class_diff, 0)) * 0.18
                + IFNULL(sp.official_rating_diff, 0) * 0.15
                + LEAST(
                    3.0,
                    (
                        GREATEST(0, IFNULL(sp.candd_winner, 0))
                        + GREATEST(0, IFNULL(sp.course_winner, 0))
                        + GREATEST(0, IFNULL(sp.distance_winner, 0))
                        + GREATEST(0, IFNULL(sp.going_prev_wins, 0))
                    ) * 1.2
                  )
                + IF(IFNULL(sp.beaten_favourite, 0) > 0, 0.8, 0)
                + (
                    CASE
                        WHEN sp.age = 2
                          AND (
                            LOWER(IFNULL(dracb.race_type, '')) NOT LIKE '%hurdle%'
                            AND LOWER(IFNULL(dracb.race_type, '')) NOT LIKE '%chase%'
                            AND LOWER(IFNULL(dracb.race_type, '')) NOT LIKE '%n_h_flat%'
                            AND LOWER(IFNULL(dracb.race_type, '')) NOT LIKE '%nh_flat%'
                            AND LOWER(IFNULL(dracb.race_type, '')) NOT LIKE '%national hunt%'
                          )
                          AND (
                            LOWER(IFNULL(dracb.age_range, '')) LIKE '%2%'
                            OR LOWER(IFNULL(dracb.age_range, '')) = '2'
                          )
                          AND sp.foaling_date IS NOT NULL
                          AND sp.foaling_date NOT IN ('', '0000-00-00')
                        THEN
                            LEAST(5, GREATEST(-8,
                                CASE MONTH(sp.foaling_date)
                                    WHEN 1 THEN 2 WHEN 2 THEN 4 WHEN 3 THEN 4 WHEN 4 THEN 2
                                    WHEN 5 THEN 0 WHEN 6 THEN -2 WHEN 7 THEN -3 WHEN 8 THEN -4
                                    WHEN 9 THEN -5 WHEN 10 THEN -6 WHEN 11 THEN -6 WHEN 12 THEN -6
                                    ELSE 0
                                END
                                + CASE
                                    WHEN MONTH(v_meeting_date) BETWEEN 3 AND 6 AND MONTH(sp.foaling_date) IN (2, 3) THEN 1
                                    WHEN MONTH(v_meeting_date) BETWEEN 3 AND 6 AND MONTH(sp.foaling_date) >= 5 THEN -1
                                    WHEN MONTH(v_meeting_date) BETWEEN 7 AND 10 AND MONTH(sp.foaling_date) = 1 THEN -1
                                    WHEN MONTH(v_meeting_date) BETWEEN 7 AND 10 AND MONTH(sp.foaling_date) = 4 THEN -1
                                    WHEN MONTH(v_meeting_date) BETWEEN 7 AND 10 AND MONTH(sp.foaling_date) >= 5 THEN -2
                                    ELSE 0
                                  END
                            )) * 0.70
                        ELSE 0
                    END
                  )
                - CASE
                    WHEN NOT (
                        (sp.form_figures IS NOT NULL AND TRIM(sp.form_figures) != '' AND TRIM(sp.form_figures) NOT IN ('-', '—', '0'))
                        OR sp.SR_LTO IS NOT NULL
                        OR sp.SR_2 IS NOT NULL
                        OR sp.SR_3 IS NOT NULL
                        OR (sp.days_since_ran IS NOT NULL AND sp.days_since_ran > 0 AND sp.days_since_ran < 800)
                    ) THEN 12.0
                    ELSE 0.0
                  END
            , 1)))
            AS model_score
        FROM `speed&performance_table` sp
        INNER JOIN daily_races_beta dracb ON dracb.race_id = sp.race_id
        LEFT JOIN non_runners nr
            ON nr.race_id = sp.race_id AND nr.runner_id = sp.runner_id
        LEFT JOIN tmp_points_trainer_course tc
            ON tc.trainer_name = sp.trainer_name AND tc.course = sp.course
        WHERE dracb.meeting_date = v_meeting_date
    ) base;

    DROP TEMPORARY TABLE IF EXISTS `tmp_points_ranked`;
    CREATE TEMPORARY TABLE `tmp_points_ranked` AS
    SELECT
        s.*,
        CASE
            WHEN s.is_non_runner = 1 THEN NULL
            ELSE ROW_NUMBER() OVER (
                PARTITION BY s.race_id, CASE WHEN s.is_non_runner = 1 THEN 1 ELSE 0 END
                ORDER BY s.model_score DESC, s.horse_name ASC
            )
        END AS model_rank,
        CASE
            WHEN s.is_non_runner = 1 OR s.odds_decimal IS NULL THEN NULL
            ELSE ROW_NUMBER() OVER (
                PARTITION BY s.race_id, CASE WHEN s.is_non_runner = 1 OR s.odds_decimal IS NULL THEN 1 ELSE 0 END
                ORDER BY s.odds_decimal ASC, s.horse_name ASC
            )
        END AS market_rank
    FROM tmp_points_scored s;

    UPDATE tmp_points_ranked
    SET model_rank = NULL
    WHERE is_non_runner = 1;

    UPDATE tmp_points_ranked
    SET market_rank = NULL
    WHERE is_non_runner = 1 OR odds_decimal IS NULL;

    ALTER TABLE tmp_points_ranked ADD COLUMN edge_score DECIMAL(8,2) DEFAULT 0;
    UPDATE tmp_points_ranked
    SET edge_score = ROUND(
        (CASE
            WHEN model_rank > 0 AND market_rank > 0 THEN (market_rank - model_rank)
            ELSE 0
         END) * 4.0
        + GREATEST(0.0, (model_score - 55.0) * 0.20),
        2
    );

    DELETE FROM points_engine_published_picks
    WHERE meeting_date = v_meeting_date
      AND source IN ('cron_daily', 'admin_bulk_live', 'admin_bulk_replay');

    INSERT INTO points_engine_published_picks (
        race_id,
        meeting_date,
        win_horse,
        place_horses,
        ew_simple_horse,
        ew_edge_horse,
        saved_at,
        source
    )
    SELECT
        r.race_id,
        v_meeting_date,
        IFNULL(w.win_horse, ''),
        IFNULL(p.place_horses, '[]'),
        IFNULL(es.ew_simple_horse, ''),
        IFNULL(ee.ew_edge_horse, ''),
        NOW(),
        'cron_daily'
    FROM (
        SELECT DISTINCT race_id
        FROM tmp_points_ranked
        WHERE is_non_runner = 0
    ) r
    LEFT JOIN (
        SELECT race_id, horse_name AS win_horse
        FROM tmp_points_ranked
        WHERE model_rank = 1
    ) w ON w.race_id = r.race_id
    LEFT JOIN (
        SELECT
            race_id,
            CONCAT(
                '[',
                GROUP_CONCAT(
                    JSON_QUOTE(horse_name)
                    ORDER BY model_rank
                    SEPARATOR ','
                ),
                ']'
            ) AS place_horses
        FROM tmp_points_ranked
        WHERE model_rank BETWEEN 1 AND 3
        GROUP BY race_id
    ) p ON p.race_id = r.race_id
    LEFT JOIN (
        SELECT race_id, horse_name AS ew_simple_horse
        FROM (
            SELECT
                race_id,
                horse_name,
                ROW_NUMBER() OVER (
                    PARTITION BY race_id
                    ORDER BY model_score DESC, horse_name ASC
                ) AS ew_simple_rn
            FROM tmp_points_ranked
            WHERE is_non_runner = 0
              AND odds_decimal IS NOT NULL
              AND odds_decimal >= 6.0
        ) x
        WHERE ew_simple_rn = 1
    ) es ON es.race_id = r.race_id
    LEFT JOIN (
        SELECT race_id, horse_name AS ew_edge_horse
        FROM (
            SELECT
                race_id,
                horse_name,
                ROW_NUMBER() OVER (
                    PARTITION BY race_id
                    ORDER BY edge_score DESC, model_score DESC, horse_name ASC
                ) AS ew_edge_rn
            FROM tmp_points_ranked
            WHERE is_non_runner = 0
              AND odds_decimal IS NOT NULL
              AND odds_decimal >= 6.0
              AND edge_score >= 6.0
              AND model_rank BETWEEN 1 AND 5
              AND market_rank > 0
              AND (market_rank - model_rank) >= 2
        ) y
        WHERE ew_edge_rn = 1
    ) ee ON ee.race_id = r.race_id
    WHERE w.win_horse IS NOT NULL AND w.win_horse != ''
    ON DUPLICATE KEY UPDATE
        win_horse = IF(points_engine_published_picks.source = 'race_card', points_engine_published_picks.win_horse, VALUES(win_horse)),
        place_horses = IF(points_engine_published_picks.source = 'race_card', points_engine_published_picks.place_horses, VALUES(place_horses)),
        ew_simple_horse = IF(points_engine_published_picks.source = 'race_card', points_engine_published_picks.ew_simple_horse, VALUES(ew_simple_horse)),
        ew_edge_horse = IF(points_engine_published_picks.source = 'race_card', points_engine_published_picks.ew_edge_horse, VALUES(ew_edge_horse)),
        saved_at = IF(points_engine_published_picks.source = 'race_card', points_engine_published_picks.saved_at, VALUES(saved_at)),
        source = IF(points_engine_published_picks.source = 'race_card', points_engine_published_picks.source, VALUES(source));

    DROP TEMPORARY TABLE IF EXISTS `tmp_points_trainer_course`;
    DROP TEMPORARY TABLE IF EXISTS `tmp_points_scored`;
    DROP TEMPORARY TABLE IF EXISTS `tmp_points_ranked`;

    SELECT CONCAT(
        'points_published_picks_daily_UPDATE completed for ',
        v_meeting_date,
        ' — rows: ',
        (SELECT COUNT(*) FROM points_engine_published_picks WHERE meeting_date = v_meeting_date),
        ' at ',
        NOW()
    ) AS status;
END $$
DELIMITER ;
