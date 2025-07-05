DELIMITER $$

CREATE DEFINER=`smartform_user`@`localhost` PROCEDURE `RUNME_UpdateCall`()
BEGIN
CALL `coolwed1_WP9PN`.`SR_sample_races_UPDATE`();
CALL `coolwed1_WP9PN`.`SR_data_UPDATE`();
CALL `coolwed1_WP9PN`.`my_daily_races_UPDATE`();
CALL `coolwed1_WP9PN`.`adv_my_daily_races_UPDATE`();

CALL `coolwed1_WP9PN`.`adv_my_daily_details_tb_UPDATE`();
CALL `coolwed1_WP9PN`.`my_daily_details_tb_UPDATE`();

END$$

DELIMITER ;