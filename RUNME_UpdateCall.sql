DELIMITER $$

CREATE DEFINER=`root`@`localhost` PROCEDURE `RUNME_UpdateCall`()
BEGIN
CALL `smartform`.`SR_sample_races_UPDATE`();
CALL `smartform`.`SR_data_UPDATE`();
CALL `smartform`.`SR_daily_data_UPDATE`();
CALL `smartform`.`my_daily_races_UPDATE`();
CALL `smartform`.`adv_my_daily_races_UPDATE`();

CALL `smartform`.`adv_my_daily_details_tb_UPDATE`();
CALL `smartform`.`my_daily_details_tb_UPDATE`();

END$$

DELIMITER ;