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

routine_exists <- dbGetQuery(
  smartformDB,
  "SELECT COUNT(*) AS cnt
   FROM information_schema.ROUTINES
   WHERE ROUTINE_SCHEMA = 'coolwed1_wp364'
     AND ROUTINE_TYPE = 'PROCEDURE'
     AND ROUTINE_NAME = 'points_published_picks_daily_UPDATE'"
)

if (nrow(routine_exists) > 0 && as.integer(routine_exists$cnt[1]) > 0) {
  print("Calling points_published_picks_daily_UPDATE procedure")
  dbExecute(smartformDB, "CALL `coolwed1_wp364`.`points_published_picks_daily_UPDATE`()")
} else {
  print("points_published_picks_daily_UPDATE not found in coolwed1_wp364, skipped")
}

routine_exists <- dbGetQuery(
  smartformDB,
  "SELECT COUNT(*) AS cnt
   FROM information_schema.ROUTINES
   WHERE ROUTINE_SCHEMA = 'coolwed1_wp364'
     AND ROUTINE_TYPE = 'PROCEDURE'
     AND ROUTINE_NAME = 'daily_sires_insights_UPDATE'"
)

if (nrow(routine_exists) > 0 && as.integer(routine_exists$cnt[1]) > 0) {
  print("Calling daily_sires_insights_UPDATE procedure")
  dbExecute(smartformDB, "CALL `coolwed1_wp364`.`daily_sires_insights_UPDATE`()")
} else {
  print("daily_sires_insights_UPDATE not found in coolwed1_wp364, skipped")
}
## Disconnect from DB
#######################################

dbDisconnect(smartformDB)
