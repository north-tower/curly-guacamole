## Connect to DB
#######################################

# Select the config file
rmariadb.settingsfile <- "~/Database_Access.cnf"
rmariadb.db <- "smartform_search"

# Use the config file to connect
smartformDB <- dbConnect(RMariaDB::MariaDB(),
                         default.file=rmariadb.settingsfile,
                         group=rmariadb.db
                         )

## Query for Loading and performing any further data/table formatting
#######################################

dbExecute(smartformDB, "DROP TABLE IF EXISTS sr_results")
dbWriteTable(smartformDB, "sr_results", SR_data)

dbExecute(smartformDB, "DROP TABLE IF EXISTS draw_bias")
dbWriteTable(smartformDB, "draw_bias", draw_bias)

dbExecute(smartformDB, "CALL `smartform`.`adv_speed_analysis_UPDATE`()")
dbExecute(smartformDB, "CALL `smartform`.`speed_analysis_UPDATE`()")



## Disconnect from DB
#######################################

dbDisconnect(smartformDB)
