DELIMITER $$

DROP FUNCTION IF EXISTS `mordin_class_num`$$
CREATE FUNCTION `mordin_class_num`(p_class VARCHAR(50)) RETURNS TINYINT
DETERMINISTIC NO SQL
BEGIN
    DECLARE v VARCHAR(5);
    SET v = REGEXP_SUBSTR(IFNULL(p_class, ''), '[1-7]');
    RETURN IF(v IS NULL OR v = '', NULL, CAST(v AS UNSIGNED));
END$$

DROP FUNCTION IF EXISTS `mordin_band_top`$$
CREATE FUNCTION `mordin_band_top`(p_title VARCHAR(255)) RETURNS SMALLINT
DETERMINISTIC NO SQL
BEGIN
    DECLARE v VARCHAR(10);
    SET v = REGEXP_SUBSTR(IFNULL(p_title, ''), '[0-9]{1,3}-[0-9]{2,3}');
    IF v IS NULL OR v = '' THEN
        RETURN NULL;
    END IF;
    RETURN CAST(SUBSTRING_INDEX(v, '-', -1) AS UNSIGNED);
END$$

DROP FUNCTION IF EXISTS `mordin_tier_code`$$
CREATE FUNCTION `mordin_tier_code`(p_title VARCHAR(255), p_class VARCHAR(50), p_handicap TINYINT) RETURNS VARCHAR(12)
DETERMINISTIC NO SQL
BEGIN
    DECLARE t VARCHAR(255) DEFAULT LOWER(IFNULL(p_title, ''));
    DECLARE c TINYINT DEFAULT mordin_class_num(p_class);
    DECLARE b SMALLINT DEFAULT mordin_band_top(p_title);

    IF t REGEXP 'group[[:space:]]*1([^0-9]|$)|grade[[:space:]]*1([^0-9]|$)|\\(g1\\)' THEN RETURN 'G1'; END IF;
    IF t REGEXP 'group[[:space:]]*2([^0-9]|$)|grade[[:space:]]*2([^0-9]|$)|\\(g2\\)' THEN RETURN 'G2'; END IF;
    IF t REGEXP 'group[[:space:]]*3([^0-9]|$)|grade[[:space:]]*3([^0-9]|$)|\\(g3\\)' THEN RETURN 'G3'; END IF;
    IF t REGEXP 'listed|\\(l\\)' THEN RETURN 'LISTED'; END IF;
    IF t REGEXP 'sell' THEN RETURN 'C67_BOTTOM'; END IF;
    IF c = 1 OR (c = 2 AND IFNULL(p_handicap, 0) = 1 AND (t REGEXP 'heritage' OR IFNULL(b, 0) >= 111)) THEN
        RETURN 'C2_OPEN';
    END IF;
    IF c = 2 THEN RETURN IF(IFNULL(b, 0) >= 106, 'C2_HIGH', 'C2_MID'); END IF;
    IF c = 3 THEN RETURN IF(b IS NOT NULL AND b <= 90, 'C34_MID', 'C3_MIDHIGH'); END IF;
    IF c = 4 THEN RETURN IF(IFNULL(b, 0) >= 86, 'C34_MID', 'C4_LOWMID'); END IF;
    IF c = 5 THEN RETURN 'C5_LOW'; END IF;
    IF c IN (6, 7) THEN RETURN 'C67_BOTTOM'; END IF;
    RETURN NULL;
END$$

DROP FUNCTION IF EXISTS `mordin_tier_rank`$$
CREATE FUNCTION `mordin_tier_rank`(p_code VARCHAR(12)) RETURNS TINYINT
DETERMINISTIC NO SQL
RETURN CASE p_code
    WHEN 'G1' THEN 1 WHEN 'G2' THEN 2 WHEN 'G3' THEN 3 WHEN 'LISTED' THEN 4
    WHEN 'C2_OPEN' THEN 5 WHEN 'C2_HIGH' THEN 6 WHEN 'C2_MID' THEN 7
    WHEN 'C3_MIDHIGH' THEN 8 WHEN 'C34_MID' THEN 9 WHEN 'C4_LOWMID' THEN 10
    WHEN 'C5_LOW' THEN 11 WHEN 'C67_BOTTOM' THEN 12
    ELSE NULL
END$$

DROP FUNCTION IF EXISTS `mordin_country_group`$$
CREATE FUNCTION `mordin_country_group`(p_country VARCHAR(50)) RETURNS VARCHAR(8)
DETERMINISTIC NO SQL
BEGIN
    DECLARE c VARCHAR(50) DEFAULT LOWER(TRIM(IFNULL(p_country, '')));
    IF c = '' OR c REGEXP '^(gb|gbr|uk|eng|england|sco|scotland|wal|wales|great britain|united kingdom|northern ireland|ni)$' THEN RETURN 'GB'; END IF;
    IF c REGEXP '^(ire|irl|ireland|eire)$' THEN RETURN 'IRE'; END IF;
    IF c REGEXP '^(fr|fra|france)$' THEN RETURN 'FR'; END IF;
    IF c REGEXP '^(ger|deu|germany|ity|ita|it|italy)$' THEN RETURN 'GER_ITY'; END IF;
    IF c REGEXP '^(usa|us|united states)$' THEN RETURN 'USA'; END IF;
    RETURN 'OTHER';
END$$

DROP FUNCTION IF EXISTS `mordin_intl_tier`$$
CREATE FUNCTION `mordin_intl_tier`(p_code VARCHAR(12), p_country_group VARCHAR(8)) RETURNS VARCHAR(12)
DETERMINISTIC NO SQL
BEGIN
    IF p_country_group = 'OTHER' THEN RETURN 'UNQ'; END IF;
    IF p_country_group = 'USA' THEN RETURN IF(p_code = 'G1', 'G1', 'UNQ'); END IF;
    IF p_country_group = 'GER_ITY' THEN
        RETURN CASE p_code
            WHEN 'G1' THEN 'G3'
            WHEN 'G2' THEN 'C2_HIGH'
            WHEN 'G3' THEN 'C2_HIGH'
            WHEN 'LISTED' THEN 'C2_HIGH'
            ELSE p_code
        END;
    END IF;
    RETURN p_code;
END$$

DROP FUNCTION IF EXISTS `mordin_comp_level`$$
CREATE FUNCTION `mordin_comp_level`(p_margin DECIMAL(6,2), p_within2 INT, p_comment TEXT) RETURNS TINYINT
DETERMINISTIC NO SQL
BEGIN
    DECLARE c TEXT DEFAULT LOWER(IFNULL(p_comment, ''));
    DECLARE lvl TINYINT;
    IF p_margin IS NULL THEN
        RETURN 3;
    END IF;
    SET lvl = CASE
        WHEN p_margin >= 5 THEN 1
        WHEN p_margin >= 3 THEN 2
        WHEN p_margin >= 1 THEN 3
        WHEN IFNULL(p_within2, 0) >= 4 THEN 5
        ELSE 4
    END;
    IF p_margin >= 2 AND c REGEXP 'won easily|eased down|eased (near|close to|towards) finish|unextended|comfortabl|readily|cosily|made all|shaken up to lead' THEN
        SET lvl = 1;
    END IF;
    IF p_margin < 2 AND c REGEXP 'driven out|gamely|all out|hard pressed|sustained (duel|challenge)|hard ridden|just held on|on the nod' THEN
        SET lvl = GREATEST(lvl, 4);
    END IF;
    RETURN lvl;
END$$

DROP FUNCTION IF EXISTS `mordin_easy_bonus`$$
CREATE FUNCTION `mordin_easy_bonus`(p_level TINYINT, p_margin DECIMAL(6,2), p_comment TEXT) RETURNS TINYINT
DETERMINISTIC NO SQL
BEGIN
    IF IFNULL(p_level, 0) <> 1 OR p_margin IS NULL
       OR LOWER(IFNULL(p_comment, '')) NOT REGEXP 'won easily|eased down|eased (near|close to|towards) finish' THEN
        RETURN 0;
    END IF;
    RETURN CASE WHEN p_margin >= 5 THEN 3 WHEN p_margin >= 3 THEN 2 ELSE 1 END;
END$$

DROP FUNCTION IF EXISTS `mordin_perf_mcr`$$
CREATE FUNCTION `mordin_perf_mcr`(p_race_mcr DECIMAL(5,1), p_bonus TINYINT, p_finish_pos INT, p_lengths DECIMAL(6,2), p_distance_yards INT) RETURNS DECIMAL(5,1)
DETERMINISTIC NO SQL
BEGIN
    IF p_race_mcr IS NULL OR p_finish_pos IS NULL OR IFNULL(p_distance_yards, 0) <= 0 THEN
        RETURN NULL;
    END IF;
    IF p_finish_pos = 1 THEN
        RETURN p_race_mcr + IFNULL(p_bonus, 0);
    END IF;
    RETURN ROUND(p_race_mcr - LEAST(IFNULL(p_lengths, 20), 20) * 1760 / p_distance_yards, 1);
END$$

DROP FUNCTION IF EXISTS `mordin_maiden_tier`$$
CREATE FUNCTION `mordin_maiden_tier`(p_title VARCHAR(255), p_prize DECIMAL(12,2)) RETURNS TINYINT
DETERMINISTIC NO SQL
BEGIN
    DECLARE t VARCHAR(255) DEFAULT LOWER(IFNULL(p_title, ''));
    IF t NOT REGEXP 'maiden|novice' THEN RETURN NULL; END IF;
    IF t REGEXP 'sell' THEN RETURN 4; END IF;
    IF t REGEXP 'claim' THEN RETURN 3; END IF;
    IF t REGEXP 'auction|median' THEN RETURN 2; END IF;
    RETURN IF(IFNULL(p_prize, 0) >= 10000, 1, 2);
END$$

DROP FUNCTION IF EXISTS `mordin_is_2yo_only`$$
CREATE FUNCTION `mordin_is_2yo_only`(p_age_range VARCHAR(20)) RETURNS TINYINT
DETERMINISTIC NO SQL
RETURN IF(LOWER(TRIM(IFNULL(p_age_range, ''))) REGEXP '^2[^0-9+-]*$', 1, 0)$$

DROP FUNCTION IF EXISTS `mordin_is_low_tier_juv`$$
CREATE FUNCTION `mordin_is_low_tier_juv`(p_title VARCHAR(255), p_handicap TINYINT, p_age_range VARCHAR(20)) RETURNS TINYINT
DETERMINISTIC NO SQL
RETURN IF(
    LOWER(IFNULL(p_title, '')) REGEXP 'nursery|claim|sell'
    OR (IFNULL(p_handicap, 0) = 1 AND mordin_is_2yo_only(p_age_range) = 1),
    1, 0
)$$

DELIMITER ;
