-- Mordin seed table checks. Run after deploying mordin_seed_tables.sql on fhorsitedb / coolwed1_wp364.
-- Expected: failures = 0; unmatched_trainers returns no rows.

DROP TEMPORARY TABLE IF EXISTS tmp_mordin_seed_test;
CREATE TEMPORARY TABLE tmp_mordin_seed_test AS
SELECT label, expected, actual, IF(expected <=> actual, 'ok', 'FAIL') AS result FROM (
    SELECT 'matrix_rows' AS label, '60' AS expected, CAST(COUNT(*) AS CHAR) AS actual FROM mordin_class_matrix
    UNION ALL SELECT 'northern_courses', '11', CAST(COUNT(*) AS CHAR) FROM mordin_northern_courses
    UNION ALL SELECT 'trainer_locations', '22', CAST(COUNT(*) AS CHAR) FROM trainer_locations
    UNION ALL SELECT 'trainers_in_historic',
        CAST((SELECT COUNT(*) FROM trainer_locations) AS CHAR),
        CAST((
            SELECT COUNT(*)
            FROM trainer_locations tl
            JOIN (SELECT DISTINCT trainer_name FROM historic_runners_beta) h
              ON h.trainer_name = tl.trainer_name
        ) AS CHAR)
) x;

SELECT label, expected, actual, result FROM tmp_mordin_seed_test WHERE result = 'FAIL';

SELECT
    COUNT(*) AS checks,
    SUM(result = 'FAIL') AS failures
FROM tmp_mordin_seed_test;

SELECT tl.trainer_name AS unmatched_trainer
FROM trainer_locations tl
LEFT JOIN (SELECT DISTINCT trainer_name FROM historic_runners_beta) h
  ON h.trainer_name = tl.trainer_name
WHERE h.trainer_name IS NULL;
