## Connect to DB
#######################################

# Select the config file
rmariadb.settingsfile <- "Database_Access.cnf"
rmariadb.db <- "Database_Access"

# Use the config file to connect
smartformDB <- dbConnect(RMariaDB::MariaDB(),
                         default.file=rmariadb.settingsfile,
                         group=rmariadb.db
                         )

## Query to load relevant data
#######################################

# First, let's check if the tables exist
tables_check <- dbGetQuery(smartformDB, "SHOW TABLES FROM coolwed1_WP9PN LIKE 'sr_%'")
print("Available tables:")
print(tables_check)

racesQ <- paste ("
SELECT * FROM coolwed1_WP9PN.sr_sample_races
                    ", sep = "")
races <- dbGetQuery(smartformDB, racesQ)

# Debug: Check what columns are in races
print("Columns in races data:")
print(colnames(races))
print("Number of rows in races:")
print(nrow(races))

runnersQ <- paste ("
SELECT * FROM sr_data
                    ", sep = "")
runners <- dbGetQuery(smartformDB, runnersQ)

daily_dataQ <- paste ("
SELECT * FROM coolwed1_WP9PN.sr_daily_data WHERE CURDATE() = date(scheduled_time)
                    ", sep = "")
daily_data <- dbGetQuery(smartformDB, daily_dataQ)

rm(racesQ, runnersQ, daily_dataQ)

## Disconnect from DB
#######################################

dbDisconnect(smartformDB)
