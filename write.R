## Connect to DB
#######################################

# Select the config file
rmariadb.settingsfile <- "./Database_Access.cnf"
rmariadb.db <- "Database_Access"

# Use the config file to connect
smartformDB <- dbConnect(RMariaDB::MariaDB(),
                         default.file=rmariadb.settingsfile,
                         group=rmariadb.db
                         )

## Query for Loading and performing any further data/table formatting
#######################################

print(paste("Writing", nrow(SR_data), "rows to sr_results"))
dbExecute(smartformDB, "DROP TABLE IF EXISTS sr_results")
dbWriteTable(smartformDB, "sr_results", SR_data)
dbExecute(smartformDB, "CREATE INDEX idx_race_runner_id ON sr_results(race_id, runner_id)")



print(paste("Writing", nrow(draw_bias), "rows to draw_bias"))
dbExecute(smartformDB, "DROP TABLE IF EXISTS draw_bias")
dbWriteTable(smartformDB, "draw_bias", draw_bias)

print("Calling adv_speed_analysis_UPDATE and speed_analysis_UPDATE procedures")
dbExecute(smartformDB, "CALL `coolwed1_wp364`.`adv_speed_analysis_UPDATE`()")
dbExecute(smartformDB, "CALL `coolwed1_wp364`.`speed_analysis_UPDATE`()")

print("Calling speed&performance_table_UPDATE procedure")
dbExecute(smartformDB, "CALL `coolwed1_wp364`.`speed&performance_table_UPDATE`()")

print("Calling adv_speed&performance_table_UPDATE procedure")
dbExecute(smartformDB, "CALL `coolwed1_wp364`.`adv_speed&performance_table_UPDATE`()")
## Disconnect from DB
#######################################

dbDisconnect(smartformDB)
