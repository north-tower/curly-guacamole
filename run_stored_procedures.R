# Run stored procedures to create required tables
# This script should be run before the main analysis

library(RMariaDB)

# Select the config file
rmariadb.settingsfile <- "Database_Access.cnf"
rmariadb.db <- "Database_Access"

# Use the config file to connect
smartformDB <- dbConnect(RMariaDB::MariaDB(),
                         default.file=rmariadb.settingsfile,
                         group=rmariadb.db
                         )

# Run the stored procedures to create tables
print("Running stored procedures...")

tryCatch({
  dbExecute(smartformDB, "CALL `fhorsitedb`.`SR_sample_races_UPDATE`()")
  print("✓ SR_sample_races_UPDATE completed")
}, error = function(e) {
  print(paste("Error running SR_sample_races_UPDATE:", e$message))
})

tryCatch({
  dbExecute(smartformDB, "CALL `fhorsitedb`.`SR_data_UPDATE`()")
  print("✓ SR_data_UPDATE completed")
}, error = function(e) {
  print(paste("Error running SR_data_UPDATE:", e$message))
})

tryCatch({
  dbExecute(smartformDB, "CALL `fhorsitedb`.`my_daily_races_UPDATE`()")
  print("✓ my_daily_races_UPDATE completed")
}, error = function(e) {
  print(paste("Error running my_daily_races_UPDATE:", e$message))
})

tryCatch({
  dbExecute(smartformDB, "CALL `fhorsitedb`.`my_daily_details_tb_UPDATE`()")
  print("✓ my_daily_details_tb_UPDATE completed")
}, error = function(e) {
  print(paste("Error running my_daily_details_tb_UPDATE:", e$message))
})

tryCatch({
  dbExecute(smartformDB, "CALL `fhorsitedb`.`adv_my_daily_races_UPDATE`()")
  print("✓ adv_my_daily_races_UPDATE completed")
}, error = function(e) {
  print(paste("Error running adv_my_daily_races_UPDATE:", e$message))
})

tryCatch({
  dbExecute(smartformDB, "CALL `fhorsitedb`.`adv_my_daily_details_tb_UPDATE`()")
  print("✓ adv_my_daily_details_tb_UPDATE completed")
}, error = function(e) {
  print(paste("Error running adv_my_daily_details_tb_UPDATE:", e$message))
})

tryCatch({
  routine_exists <- dbGetQuery(
    smartformDB,
    "SELECT COUNT(*) AS cnt
     FROM information_schema.ROUTINES
     WHERE ROUTINE_SCHEMA = 'fhorsitedb'
       AND ROUTINE_TYPE = 'PROCEDURE'
       AND ROUTINE_NAME = 'daily_sires_insights_UPDATE'"
  )

  if (nrow(routine_exists) > 0 && as.integer(routine_exists$cnt[1]) > 0) {
    dbExecute(smartformDB, "CALL `fhorsitedb`.`daily_sires_insights_UPDATE`()")
    print("✓ daily_sires_insights_UPDATE completed")
  } else {
    print("ℹ daily_sires_insights_UPDATE not found in fhorsitedb, skipped")
  }
}, error = function(e) {
  print(paste("Error running daily_sires_insights_UPDATE:", e$message))
})

# Check what tables exist now
tables_check <- dbGetQuery(smartformDB, "SHOW TABLES FROM fhorsitedb LIKE 'sr_%'")
print("Available tables after running procedures:")
print(tables_check)

dbDisconnect(smartformDB)
print("Stored procedures execution completed!") 