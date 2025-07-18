DELIMITER $$

CREATE DEFINER=`smartform_user`@`localhost` PROCEDURE `ancestry_records_UPDATE`()
BEGIN
    DROP TABLE IF EXISTS ancestry_records;
    CREATE TABLE ancestry_records AS
        SELECT
            run.runner_id AS runner_id,
            run.name AS runner_name,
            run.foaling_date AS runner_DOB,
            run.bred AS runner_origin,
            IF(dam.runner_id IS NOT NULL, dam.runner_id, run.dam_id) AS dam_id,
            IF(dam.name IS NOT NULL, dam.name, run.dam_name) AS dam_name,
            IF(dam.foaling_date IS NOT NULL, dam.foaling_date, DATE(CONCAT(run.dam_year_born, '-01-01'))) AS dam_DOB,
            dam.bred AS dam_origin,
            IF(sire.runner_id IS NOT NULL, sire.runner_id, run.sire_id) AS sire_id,
            IF(sire.name IS NOT NULL, sire.name, run.sire_name) AS sire_name,
            IF(sire.foaling_date IS NOT NULL, sire.foaling_date, DATE(CONCAT(run.sire_year_born, '-01-01'))) AS sire_DOB,
            sire.bred AS sire_origin
        FROM
            (SELECT * FROM (
                SELECT runner_id, name, foaling_date, bred, dam_id, dam_name, dam_year_born, sire_id, sire_name, sire_year_born,
                    ROW_NUMBER() OVER (PARTITION BY runner_id ORDER BY loaded_at DESC) rn
                FROM coolwed1_WP9PN.daily_runners_beta) a
                WHERE rn = 1) run
        LEFT JOIN
            (SELECT * FROM (
                SELECT runner_id, name, foaling_date, bred,
                    ROW_NUMBER() OVER (PARTITION BY runner_id ORDER BY loaded_at DESC) rn
                FROM coolwed1_WP9PN.daily_runners_beta) b
                WHERE rn = 1) dam ON (dam.runner_id = run.dam_id)
        LEFT JOIN 
            (SELECT * FROM (
                SELECT runner_id, name, foaling_date, bred,
                    ROW_NUMBER() OVER (PARTITION BY runner_id ORDER BY loaded_at DESC) rn
                FROM coolwed1_WP9PN.daily_runners_beta) c
                WHERE rn = 1) sire ON (sire.runner_id = run.sire_id);
END$$

DELIMITER ;