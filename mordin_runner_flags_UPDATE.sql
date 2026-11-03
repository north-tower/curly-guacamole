-- Point-in-time runner flags for Mordin rules (historic incremental + today/advance rebuild).
-- Requires: Task 1 functions, Task 2 seeds (mordin_northern_courses, trainer_locations),
--           Task 3 tables (mordin_race_class, mordin_runner_perf, mordin_trainer_group_sr).

DELIMITER $$
DROP PROCEDURE IF EXISTS `mordin_runner_flags_UPDATE`$$
CREATE PROCEDURE `mordin_runner_flags_UPDATE`()
BEGIN
    DECLARE v_logic_version INT DEFAULT 1;
    DECLARE v_hist_from DATE DEFAULT DATE_SUB(CURDATE(), INTERVAL 1825 DAY);

    CREATE TABLE IF NOT EXISTS mordin_runner_flags (
        race_id INT NOT NULL,
        runner_id INT NOT NULL,
        meeting_date DATE NOT NULL,
        logic_version INT NOT NULL,
        age TINYINT,
        race_tier_code VARCHAR(12),
        race_tier_rank TINYINT,
        race_class_num TINYINT,
        distance_yards INT,
        career_runs INT NOT NULL DEFAULT 0,
        season_runs INT NOT NULL DEFAULT 0,
        best_tier_won_rank TINYINT,
        meets_class_requirement TINYINT,
        best_mcr_12m DECIMAL(5,1),
        last_mcr DECIMAL(5,1),
        outclassed_handicapper TINYINT,
        eliminate_juvenile TINYINT NOT NULL DEFAULT 0,
        downgrade_northern_form TINYINT NOT NULL DEFAULT 0,
        foreign_unquantified_runs INT NOT NULL DEFAULT 0,
        us_lower_graded_runs INT NOT NULL DEFAULT 0,
        latest_position INT,
        latest_lengths_2nd_to_3rd DECIMAL(6,2),
        high_value_maiden_placing TINYINT NOT NULL DEFAULT 0,
        is_premier_debut_track TINYINT NOT NULL DEFAULT 0,
        yarmouth_newmarket_pocket TINYINT NOT NULL DEFAULT 0,
        salisbury_lambourn_pocket TINYINT NOT NULL DEFAULT 0,
        debut_penalty TINYINT NOT NULL DEFAULT 0,
        trainer_group_strike_rate DECIMAL(5,1),
        trainer_group_elite TINYINT NOT NULL DEFAULT 0,
        PRIMARY KEY (race_id, runner_id),
        INDEX idx_mrf_date (meeting_date)
    );

    DELETE FROM mordin_runner_flags
    WHERE logic_version <> v_logic_version
       OR meeting_date >= CURDATE()
       OR meeting_date < v_hist_from;

    DROP TEMPORARY TABLE IF EXISTS tmp_mordin_targets;
    CREATE TEMPORARY TABLE tmp_mordin_targets (
        race_id INT NOT NULL,
        runner_id INT NOT NULL,
        meeting_date DATE NOT NULL,
        course VARCHAR(50),
        trainer_name VARCHAR(100),
        age TINYINT,
        tier_code VARCHAR(12),
        tier_rank TINYINT,
        class_num TINYINT,
        handicap TINYINT,
        is_2yo_only TINYINT,
        is_maiden_race TINYINT,
        distance_yards INT,
        PRIMARY KEY (race_id, runner_id)
    );

    INSERT IGNORE INTO tmp_mordin_targets
    SELECT
        mrp.race_id, mrp.runner_id, mrp.meeting_date, mrp.course, mrp.trainer_name,
        CASE
            WHEN hrunb.foaling_date IS NULL OR hrunb.foaling_date IN ('', '0000-00-00') THEN NULL
            ELSE YEAR(mrp.meeting_date) - YEAR(hrunb.foaling_date)
        END,
        mrc.tier_code, mrc.tier_rank, mrc.class_num, mrc.handicap, mrc.is_2yo_only,
        IF(mrc.maiden_tier IS NULL, 0, 1), mrc.distance_yards
    FROM mordin_runner_perf mrp
    JOIN mordin_race_class mrc ON mrc.race_id = mrp.race_id
    JOIN historic_runners_beta hrunb ON hrunb.race_id = mrp.race_id AND hrunb.runner_id = mrp.runner_id
    LEFT JOIN mordin_runner_flags mrf ON mrf.race_id = mrp.race_id AND mrf.runner_id = mrp.runner_id
    WHERE mrp.meeting_date >= v_hist_from
      AND mrp.meeting_date < CURDATE()
      AND mrf.race_id IS NULL;

    INSERT IGNORE INTO tmp_mordin_targets
    SELECT d.race_id, d.runner_id, d.meeting_date, d.course, d.trainer_name, d.age,
           d.tier_code, mordin_tier_rank(d.tier_code), d.class_num, d.handicap, d.is_2yo_only, d.is_maiden_race, d.distance_yards
    FROM (
        SELECT drunb.race_id, drunb.runner_id, dracb.meeting_date, dracb.course, drunb.trainer_name, drunb.age,
               mordin_intl_tier(mordin_tier_code(dracb.race_title, dracb.class, dracb.handicap), mordin_country_group(dracb.country)) AS tier_code,
               mordin_class_num(dracb.class) AS class_num,
               dracb.handicap,
               mordin_is_2yo_only(dracb.age_range) AS is_2yo_only,
               IF(mordin_maiden_tier(dracb.race_title, dracb.prize_pos_1) IS NULL, 0, 1) AS is_maiden_race,
               dracb.distance_yards
        FROM daily_races_beta dracb
        JOIN daily_runners_beta drunb ON drunb.race_id = dracb.race_id
        WHERE dracb.meeting_date >= CURDATE()
          AND dracb.race_type = 'Flat'
    ) d;

    INSERT IGNORE INTO tmp_mordin_targets
    SELECT d.race_id, d.runner_id, d.meeting_date, d.course, d.trainer_name, d.age,
           d.tier_code, mordin_tier_rank(d.tier_code), d.class_num, d.handicap, d.is_2yo_only, d.is_maiden_race, d.distance_yards
    FROM (
        SELECT adrunb.race_id, adrunb.runner_id, adracb.meeting_date, adracb.course, adrunb.trainer_name, adrunb.age,
               mordin_intl_tier(mordin_tier_code(adracb.race_title, adracb.class, adracb.handicap), mordin_country_group(adracb.country)) AS tier_code,
               mordin_class_num(adracb.class) AS class_num,
               adracb.handicap,
               mordin_is_2yo_only(adracb.age_range) AS is_2yo_only,
               IF(mordin_maiden_tier(adracb.race_title, adracb.prize_pos_1) IS NULL, 0, 1) AS is_maiden_race,
               adracb.distance_yards
        FROM advance_daily_races_beta adracb
        JOIN advance_daily_runners_beta adrunb ON adrunb.race_id = adracb.race_id
        WHERE adracb.meeting_date >= CURDATE()
          AND adracb.race_type = 'Flat'
    ) d;

    DROP TEMPORARY TABLE IF EXISTS tmp_mordin_hist;
    CREATE TEMPORARY TABLE tmp_mordin_hist (PRIMARY KEY (race_id, runner_id)) AS
    SELECT
        t.race_id,
        t.runner_id,
        COUNT(h.race_id) AS career_runs,
        SUM(YEAR(h.meeting_date) = YEAR(t.meeting_date)) AS season_runs,
        MAX(
            YEAR(h.meeting_date) = YEAR(t.meeting_date)
            AND h.class_num IS NOT NULL AND t.class_num IS NOT NULL
            AND h.class_num <= t.class_num
            AND (h.finish_pos BETWEEN 1 AND 3 OR h.lengths_to_winner <= 3)
        ) AS season_class_ok,
        MIN(CASE WHEN h.finish_pos = 1 AND h.tier_rank IS NOT NULL THEN
            CASE
                WHEN t.age >= 4 AND t.tier_rank <= 4 AND t.distance_yards <= 1320
                     AND h.distance_yards <= 1320 AND h.tier_rank = 2 THEN 1
                ELSE h.tier_rank
            END
        END) AS best_tier_won_rank,
        MAX(CASE WHEN h.meeting_date >= DATE_SUB(t.meeting_date, INTERVAL 365 DAY) THEN h.mordin_class_rating END) AS best_mcr_12m,
        MAX(h.is_low_tier_juv) AS ever_low_tier_juv,
        MAX(nc.course_key IS NOT NULL AND (h.tier_rank IS NULL OR h.tier_rank > 4)) AS northern_minor,
        SUM(h.foreign_unquantified) AS foreign_unquantified_runs,
        SUM(h.us_lower_graded) AS us_lower_graded_runs
    FROM tmp_mordin_targets t
    LEFT JOIN mordin_runner_perf h ON h.runner_id = t.runner_id AND h.meeting_date < t.meeting_date
    LEFT JOIN mordin_northern_courses nc ON LOWER(h.course) LIKE CONCAT(nc.course_key, '%')
    GROUP BY t.race_id, t.runner_id;

    DROP TEMPORARY TABLE IF EXISTS tmp_mordin_edges;
    CREATE TEMPORARY TABLE tmp_mordin_edges (PRIMARY KEY (race_id, runner_id)) AS
    SELECT
        race_id,
        runner_id,
        MAX(CASE WHEN rn_desc = 1 THEN finish_pos END) AS latest_position,
        MAX(CASE WHEN rn_desc = 1 THEN lengths_2nd_to_3rd END) AS latest_l2to3,
        MAX(CASE WHEN rn_desc = 1 THEN mordin_class_rating END) AS last_mcr,
        MAX(CASE WHEN rn_asc = 1 THEN course END) AS debut_course
    FROM (
        SELECT
            t.race_id, t.runner_id, h.finish_pos, h.lengths_2nd_to_3rd, h.mordin_class_rating, h.course,
            ROW_NUMBER() OVER (PARTITION BY t.race_id, t.runner_id ORDER BY h.meeting_date DESC, h.race_id DESC) AS rn_desc,
            ROW_NUMBER() OVER (PARTITION BY t.race_id, t.runner_id ORDER BY h.meeting_date ASC, h.race_id ASC) AS rn_asc
        FROM tmp_mordin_targets t
        JOIN mordin_runner_perf h ON h.runner_id = t.runner_id AND h.meeting_date < t.meeting_date
    ) x
    WHERE rn_desc = 1 OR rn_asc = 1
    GROUP BY race_id, runner_id;

    INSERT INTO mordin_runner_flags (
        race_id, runner_id, meeting_date, logic_version, age, race_tier_code, race_tier_rank, race_class_num,
        distance_yards, career_runs, season_runs, best_tier_won_rank, meets_class_requirement, best_mcr_12m,
        last_mcr, outclassed_handicapper, eliminate_juvenile, downgrade_northern_form, foreign_unquantified_runs,
        us_lower_graded_runs, latest_position, latest_lengths_2nd_to_3rd, high_value_maiden_placing,
        is_premier_debut_track, yarmouth_newmarket_pocket, salisbury_lambourn_pocket, debut_penalty,
        trainer_group_strike_rate, trainer_group_elite
    )
    SELECT
        t.race_id, t.runner_id, t.meeting_date, v_logic_version, t.age, t.tier_code, t.tier_rank, t.class_num,
        t.distance_yards,
        IFNULL(hs.career_runs, 0),
        IFNULL(hs.season_runs, 0),
        hs.best_tier_won_rank,
        CASE
            WHEN t.tier_rank IS NULL THEN NULL
            WHEN hs.best_tier_won_rank IS NULL THEN 0
            WHEN hs.best_tier_won_rank <= t.tier_rank THEN 1
            ELSE 0
        END,
        hs.best_mcr_12m,
        e.last_mcr,
        CASE
            WHEN IFNULL(t.handicap, 0) <> 1 OR IFNULL(t.age, 0) < 3 THEN 0
            WHEN IFNULL(hs.season_runs, 0) = 0 THEN NULL
            WHEN IFNULL(hs.season_class_ok, 0) = 1 THEN 0
            ELSE 1
        END,
        IF(t.is_2yo_only = 1 AND IFNULL(t.tier_rank, 99) <= 4 AND IFNULL(hs.ever_low_tier_juv, 0) = 1, 1, 0),
        IF(t.is_2yo_only = 1 AND IFNULL(t.tier_code, '') = 'G1' AND IFNULL(hs.northern_minor, 0) = 1, 1, 0),
        IFNULL(hs.foreign_unquantified_runs, 0),
        IFNULL(hs.us_lower_graded_runs, 0),
        e.latest_position,
        e.latest_l2to3,
        IF(t.is_maiden_race = 1 AND IFNULL(e.latest_position, 0) = 2 AND IFNULL(e.latest_l2to3, 0) >= 5, 1, 0),
        IF(LOWER(IFNULL(e.debut_course, t.course)) REGEXP '^(great yarmouth|yarmouth|newmarket|ascot|goodwood|doncaster|salisbury|newbury)', 1, 0),
        IF(IFNULL(t.age, 0) = 2 AND IFNULL(hs.career_runs, 0) = 0
           AND LOWER(IFNULL(t.course, '')) REGEXP 'yarmouth' AND IFNULL(tl.location, '') = 'Newmarket', 1, 0),
        IF(IFNULL(t.age, 0) = 2 AND IFNULL(hs.career_runs, 0) = 0
           AND LOWER(IFNULL(t.course, '')) REGEXP 'salisbury' AND IFNULL(tl.location, '') = 'Lambourn', 1, 0),
        IF(IFNULL(t.age, 0) = 2 AND IFNULL(hs.career_runs, 0) = 0
           AND NOT (
               (LOWER(IFNULL(t.course, '')) REGEXP 'yarmouth' AND IFNULL(tl.location, '') = 'Newmarket')
               OR (LOWER(IFNULL(t.course, '')) REGEXP 'salisbury' AND IFNULL(tl.location, '') = 'Lambourn')
           ), 1, 0),
        tg.rolling_5yr_group_strike_rate,
        IFNULL(tg.is_group_elite, 0)
    FROM tmp_mordin_targets t
    LEFT JOIN tmp_mordin_hist hs ON hs.race_id = t.race_id AND hs.runner_id = t.runner_id
    LEFT JOIN tmp_mordin_edges e ON e.race_id = t.race_id AND e.runner_id = t.runner_id
    LEFT JOIN trainer_locations tl ON tl.trainer_name = t.trainer_name
    LEFT JOIN mordin_trainer_group_sr tg ON tg.trainer_name = t.trainer_name;

    DROP TEMPORARY TABLE IF EXISTS tmp_mordin_targets;
    DROP TEMPORARY TABLE IF EXISTS tmp_mordin_hist;
    DROP TEMPORARY TABLE IF EXISTS tmp_mordin_edges;

    SELECT CONCAT(
        'mordin_runner_flags_UPDATE completed — today/advance rows: ',
        (SELECT COUNT(*) FROM mordin_runner_flags WHERE meeting_date >= CURDATE()),
        ', total rows: ', (SELECT COUNT(*) FROM mordin_runner_flags),
        ' at ', NOW()
    ) AS status;
END $$
DELIMITER ;
