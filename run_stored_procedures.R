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
  dbExecute(smartformDB, "CALL `coolwed1_WP9PN`.`SR_sample_races_UPDATE`()")
  print("✓ SR_sample_races_UPDATE completed")
}, error = function(e) {
  print(paste("Error running SR_sample_races_UPDATE:", e$message))
})

tryCatch({
  dbExecute(smartformDB, "CALL `coolwed1_WP9PN`.`SR_data_UPDATE`()")
  print("✓ SR_data_UPDATE completed")
}, error = function(e) {
  print(paste("Error running SR_data_UPDATE:", e$message))
})

tryCatch({
  dbExecute(smartformDB, "CALL `coolwed1_WP9PN`.`my_daily_races_UPDATE`()")
  print("✓ my_daily_races_UPDATE completed")
}, error = function(e) {
  print(paste("Error running my_daily_races_UPDATE:", e$message))
})

tryCatch({
  dbExecute(smartformDB, "CALL `coolwed1_WP9PN`.`my_daily_details_tb_UPDATE`()")
  print("✓ my_daily_details_tb_UPDATE completed")
}, error = function(e) {
  print(paste("Error running my_daily_details_tb_UPDATE:", e$message))
})

tryCatch({
  dbExecute(smartformDB, "CALL `coolwed1_WP9PN`.`adv_my_daily_races_UPDATE`()")
  print("✓ adv_my_daily_races_UPDATE completed")
}, error = function(e) {
  print(paste("Error running adv_my_daily_races_UPDATE:", e$message))
})

tryCatch({
  dbExecute(smartformDB, "CALL `coolwed1_WP9PN`.`adv_my_daily_details_tb_UPDATE`()")
  print("✓ adv_my_daily_details_tb_UPDATE completed")
}, error = function(e) {
  print(paste("Error running adv_my_daily_details_tb_UPDATE:", e$message))
})

# Check what tables exist now
tables_check <- dbGetQuery(smartformDB, "SHOW TABLES FROM coolwed1_WP9PN LIKE 'sr_%'")
print("Available tables after running procedures:")
print(tables_check)

dbDisconnect(smartformDB)
print("Stored procedures execution completed!") 