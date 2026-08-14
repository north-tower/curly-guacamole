# =============================================================================
# Mordin Weight Rematch — Data Verification Study
# Based on Nick Mordin, Betting for a Living, Ch. 11 "Why you should ignore weight"
#
# Runs with the same DB credentials / schema as load.R + write.R
#   source("do.R")                 # daily pipeline (Mordin off by default)
#   source("do_mordin.R")          # run this study alone
#   options(fhor.run_mordin=TRUE); source("do.R")  # include after write.R
# =============================================================================

suppressPackageStartupMessages({
  library(tidyverse)
  library(DBI)
  library(RMariaDB)
})

# -----------------------------------------------------------------------------
# 0. Config (aligned with load.R / write.R)
# -----------------------------------------------------------------------------
SEASON_FROM <- "2021-01-01"
SEASON_TO   <- as.character(Sys.Date())
CLOSE_LENGTHS <- 2
MIN_WEIGHT_PULL_LB <- 2

# Same cnf / group as the rest of the pipeline
rmariadb.settingsfile <- if (file.exists("./Database_Access.cnf")) {
  "./Database_Access.cnf"
} else if (file.exists("Database_Access.cnf")) {
  "Database_Access.cnf"
} else {
  stop("Missing Database_Access.cnf (same file used by load.R / write.R).")
}
rmariadb.db <- "Database_Access"

# Same schema prefix used in load.R / write.R stored-proc calls
DB_SCHEMA <- "coolwed1_wp364"
tbl <- function(name) paste0("`", DB_SCHEMA, "`.`", name, "`")

TURNING_COURSES <- c(
  "Lingfield", "Chester", "Epsom", "Brighton", "Catterick", "Hamilton",
  "Leicester", "Windsor", "Bath", "Thirsk", "Musselburgh", "Beverley",
  "Chepstow", "Ffos Las", "Wolverhampton", "Kempton", "Southwell",
  "Newcastle", "Dundalk", "Laytown"
)
GALLOPING_COURSES <- c(
  "Newbury", "Ascot", "York", "Newmarket", "Doncaster", "Ayr",
  "Haydock", "Sandown", "Goodwood", "Curragh", "Leopardstown",
  "Punchestown", "Navan", "Tipperary", "Down Royal", "Gowran Park",
  "Salisbury", "Ripon", "Redcar", "Pontefract", "Nottingham",
  "Carlise", "Carlisle", "Wetherby", "Kelso"
)

`%||%` <- function(a, b) {
  if (is.null(a) || length(a) == 0 || (is.character(a) && !nzchar(a[[1]]))) b else a
}

# -----------------------------------------------------------------------------
# 1. Connect (same pattern as load.R)
# -----------------------------------------------------------------------------
smartformDB <- dbConnect(
  RMariaDB::MariaDB(),
  default.file = rmariadb.settingsfile,
  group = rmariadb.db
)
con <- smartformDB

# -----------------------------------------------------------------------------
# 2. Extract flat handicap results (Turf + AW)
# -----------------------------------------------------------------------------
# track_type is on daily_races_beta (see SR_data_UPDATE), not historic_races_beta.
# LEFT JOIN so older races still load; surface falls back to going / course in R.
sql <- paste0("
SELECT
  hrunb.race_id,
  hrunb.runner_id,
  hrunb.name AS horse_name,
  hracb.meeting_date,
  hracb.course,
  hracb.race_type,
  hracb.handicap,
  hracb.distance_yards,
  hracb.direction,
  hracb.class,
  hracb.going,
  CAST(hrunb.finish_position AS UNSIGNED) AS finish_position,
  COALESCE(hrunb.distance_beaten, 0) AS distance_beaten,
  COALESCE(hrunb.weight_pounds, 0) AS weight_pounds,
  COALESCE(hrunb.jockey_claim, 0) AS jockey_claim,
  COALESCE(dracb.track_type, '') AS track_type,
  COALESCE(cf.profile, '') AS course_profile,
  COALESCE(cf.general_features, '') AS general_features,
  COALESCE(cf.straight_track_up_to, '') AS straight_track_up_to
FROM ", tbl("historic_runners_beta"), " hrunb
INNER JOIN ", tbl("historic_races_beta"), " hracb
  ON hracb.race_id = hrunb.race_id
LEFT JOIN ", tbl("daily_races_beta"), " dracb
  ON dracb.race_id = hracb.race_id
LEFT JOIN ", tbl("course_features"), " cf
  ON cf.course = hracb.course
 AND cf.race_type = IF(
       hracb.race_type = 'Flat'
         AND COALESCE(dracb.track_type, '') != ''
         AND COALESCE(dracb.track_type, '') != 'Turf',
       'All Weather Flat',
       hracb.race_type
     )
WHERE hracb.meeting_date BETWEEN '", SEASON_FROM, "' AND '", SEASON_TO, "'
  AND hracb.handicap = 1
  AND LOWER(COALESCE(hracb.race_type, '')) LIKE '%flat%'
  AND LOWER(COALESCE(hracb.race_type, '')) NOT LIKE '%hurdle%'
  AND LOWER(COALESCE(hracb.race_type, '')) NOT LIKE '%chase%'
  AND LOWER(COALESCE(hracb.race_type, '')) NOT LIKE '%national hunt%'
  AND hrunb.finish_position REGEXP '^[0-9]+$'
  AND CAST(hrunb.finish_position AS UNSIGNED) BETWEEN 1 AND 40
  AND COALESCE(hrunb.weight_pounds, 0) > 0
")
print(paste("Mordin rematch: fetching flat handicaps", SEASON_FROM, "->", SEASON_TO, "from", DB_SCHEMA))
raw <- dbGetQuery(con, sql)
print(paste("Mordin rematch: rows loaded =", nrow(raw)))

if (nrow(raw) == 0) {
  dbDisconnect(con)
  stop("No historic flat handicap rows returned. Check schema / date range.")
}

# -----------------------------------------------------------------------------
# 3. Surface + track configuration labels
# -----------------------------------------------------------------------------
AW_COURSES <- c(
  "Wolverhampton", "Kempton", "Lingfield", "Southwell", "Newcastle",
  "Chelmsford City", "Chelmsford", "Dundalk", "Laytown"
)
AW_GOINGS <- c("Slow", "Standard to Slow", "Standard", "Standard to Fast", "Fast")

# Vectorized (dplyr mutate passes whole columns)
is_aw_surface <- function(track_type, course, race_type, going = "") {
  tt <- tolower(trimws(as.character(track_type)))
  c_norm <- gsub("_", " ", as.character(course), fixed = TRUE)
  g <- trimws(as.character(going))
  blob <- tolower(paste(course, race_type, sep = " "))

  has_tt <- !is.na(tt) & nzchar(tt)
  from_tt <- has_tt & tt != "turf"
  from_going <- !has_tt & !is.na(g) & g %in% AW_GOINGS
  from_course <- !has_tt & !from_going & !is.na(c_norm) & c_norm %in% AW_COURSES
  from_blob <- !has_tt & !from_going & !from_course &
    grepl("all\\s*weather|\\baw\\b|polytrack|tapeta|fibresand|synthetic", blob)

  from_tt | from_going | from_course | from_blob
}

classify_track_config <- function(course, profile, general_features, straight_up_to) {
  c_norm <- gsub("_", " ", course %||% "", fixed = TRUE)
  text <- tolower(paste(c_norm, profile %||% "", general_features %||% "", straight_up_to %||% ""))
  if (grepl("gallop|straight|stiff|testing|undulat", text) || c_norm %in% GALLOPING_COURSES) {
    return("Straight / Galloping")
  }
  if (grepl("tight|sharp|turn|bend|switchback", text) || c_norm %in% TURNING_COURSES) {
    return("Turning / Tight")
  }
  if (!is.null(straight_up_to) && nzchar(as.character(straight_up_to)) &&
      !grepl("^\\s*$", as.character(straight_up_to))) {
    return("Straight / Galloping")
  }
  "Unclassified"
}

raw <- raw %>%
  mutate(
    meeting_date = as.Date(meeting_date),
    effective_weight = pmax(0, as.numeric(weight_pounds) - as.numeric(jockey_claim)),
    surface = if_else(
      is_aw_surface(track_type, course, race_type, going),
      "All-Weather",
      "Turf"
    ),
    track_config = pmap_chr(
      list(course, course_profile, general_features, straight_track_up_to),
      ~ classify_track_config(..1, ..2, ..3, ..4)
    ),
    furlongs = as.numeric(distance_yards) / 220
  ) %>%
  distinct(race_id, runner_id, .keep_all = TRUE)

print("Mordin rematch: surface split")
print(count(raw, surface))
print("Mordin rematch: track config split")
print(count(raw, track_config))

# -----------------------------------------------------------------------------
# 4-5. Previous run + rematch pairs
# -----------------------------------------------------------------------------
by_horse <- raw %>%
  arrange(runner_id, meeting_date, race_id) %>%
  group_by(runner_id) %>%
  mutate(
    prev_race_id = lag(race_id),
    prev_position = lag(finish_position),
    prev_distance_beaten = lag(distance_beaten),
    prev_effective_weight = lag(effective_weight)
  ) %>%
  ungroup() %>%
  filter(!is.na(prev_race_id))

pairs <- by_horse %>%
  inner_join(
    by_horse,
    by = c("prev_race_id" = "prev_race_id", "race_id" = "race_id"),
    suffix = c("_a", "_b")
  ) %>%
  filter(runner_id_a < runner_id_b) %>%
  mutate(
    lengths_between_prev = abs(prev_distance_beaten_a - prev_distance_beaten_b),
    adjacent_prev = abs(prev_position_a - prev_position_b) == 1,
    close_prev = adjacent_prev | lengths_between_prev <= CLOSE_LENGTHS
  ) %>%
  filter(close_prev) %>%
  mutate(
    a_beat_b_prev = prev_position_a < prev_position_b,
    winner_pos_rematch = if_else(a_beat_b_prev, finish_position_a, finish_position_b),
    loser_pos_rematch  = if_else(a_beat_b_prev, finish_position_b, finish_position_a),
    winner_wt_prev = if_else(a_beat_b_prev, prev_effective_weight_a, prev_effective_weight_b),
    loser_wt_prev  = if_else(a_beat_b_prev, prev_effective_weight_b, prev_effective_weight_a),
    winner_wt_rematch = if_else(a_beat_b_prev, effective_weight_a, effective_weight_b),
    loser_wt_rematch  = if_else(a_beat_b_prev, effective_weight_b, effective_weight_a),
    loser_weight_pull = (loser_wt_prev - loser_wt_rematch) - (winner_wt_prev - winner_wt_rematch),
    loser_beats_winner = as.integer(loser_pos_rematch < winner_pos_rematch),
    rematch_surface = surface_a,
    rematch_track_config = track_config_a,
    rematch_course = course_a,
    rematch_furlongs = furlongs_a,
    rematch_date = meeting_date_a
  )

print(paste("Mordin rematch: close-finish rematch pairs =", nrow(pairs)))

mordin_core <- pairs %>%
  filter(loser_weight_pull >= MIN_WEIGHT_PULL_LB)

# -----------------------------------------------------------------------------
# 6. Metrics
# -----------------------------------------------------------------------------
run_ts <- format(Sys.time(), "%Y-%m-%d %H:%M:%S")

overall <- mordin_core %>%
  summarise(
    total_rematches = n(),
    loser_won_rematch = sum(loser_beats_winner, na.rm = TRUE),
    reversal_rate_pct = mean(loser_beats_winner, na.rm = TRUE) * 100,
    avg_weight_pull_lb = mean(loser_weight_pull, na.rm = TRUE),
    avg_rematch_furlongs = mean(rematch_furlongs, na.rm = TRUE),
    .groups = "drop"
  ) %>%
  mutate(run_at = run_ts, season_from = SEASON_FROM, season_to = SEASON_TO)

by_surface <- mordin_core %>%
  group_by(rematch_surface) %>%
  summarise(
    total_rematches = n(),
    loser_won_rematch = sum(loser_beats_winner, na.rm = TRUE),
    reversal_rate_pct = mean(loser_beats_winner, na.rm = TRUE) * 100,
    avg_weight_pull_lb = mean(loser_weight_pull, na.rm = TRUE),
    .groups = "drop"
  ) %>%
  mutate(run_at = run_ts)

by_config <- mordin_core %>%
  group_by(rematch_track_config) %>%
  summarise(
    total_rematches = n(),
    loser_won_rematch = sum(loser_beats_winner, na.rm = TRUE),
    reversal_rate_pct = mean(loser_beats_winner, na.rm = TRUE) * 100,
    avg_weight_pull_lb = mean(loser_weight_pull, na.rm = TRUE),
    avg_rematch_furlongs = mean(rematch_furlongs, na.rm = TRUE),
    .groups = "drop"
  ) %>%
  mutate(run_at = run_ts)

by_surface_config <- mordin_core %>%
  group_by(rematch_surface, rematch_track_config) %>%
  summarise(
    total_rematches = n(),
    loser_won_rematch = sum(loser_beats_winner, na.rm = TRUE),
    reversal_rate_pct = mean(loser_beats_winner, na.rm = TRUE) * 100,
    .groups = "drop"
  ) %>%
  arrange(rematch_surface, desc(total_rematches)) %>%
  mutate(run_at = run_ts)

mordin_style_buckets <- pairs %>%
  mutate(
    weight_bucket = case_when(
      loser_weight_pull >= 0.5 ~ "Loser relative weight reduced",
      TRUE ~ "Same relative weights or shift against loser"
    )
  ) %>%
  group_by(weight_bucket) %>%
  summarise(
    n = n(),
    loser_lost_again = sum(loser_beats_winner == 0, na.rm = TRUE),
    loser_lost_again_pct = mean(loser_beats_winner == 0, na.rm = TRUE) * 100,
    avg_pull_when_reduced = mean(loser_weight_pull[loser_weight_pull >= 0.5], na.rm = TRUE),
    .groups = "drop"
  ) %>%
  mutate(run_at = run_ts)

# -----------------------------------------------------------------------------
# 7. Print report
# -----------------------------------------------------------------------------
cat("\n========== MORDIN WEIGHT REMATCH ==========\n")
cat("Seasons:", SEASON_FROM, "->", SEASON_TO, "\n")
cat("Flat handicaps only | close <=", CLOSE_LENGTHS, "lengths OR adjacent positions\n")
cat("Rematch = both horses' immediate next race | pull >=", MIN_WEIGHT_PULL_LB, "lb\n\n")

cat("--- Overall (loser got >=2lb relative pull) ---\n")
print(overall)

cat("\n--- By surface (Turf vs All-Weather) ---\n")
print(by_surface)

cat("\n--- By track configuration (Turning vs Straight/Galloping) ---\n")
print(by_config)

cat("\n--- Surface x track configuration ---\n")
print(by_surface_config)

cat("\n--- Mordin-style buckets (all close rematches, any weight change) ---\n")
cat("Book benchmark: both buckets ~58% 'loser lost again'; avg pull when reduced ~2.45 lb\n")
print(mordin_style_buckets)

# -----------------------------------------------------------------------------
# 8. Write results to MySQL (same style as write.R)
# -----------------------------------------------------------------------------
tryCatch({
  dbExecute(con, paste0("USE `", DB_SCHEMA, "`"))
  for (pair in list(
    list("mordin_rematch_overall", overall),
    list("mordin_rematch_by_surface", by_surface),
    list("mordin_rematch_by_config", by_config),
    list("mordin_rematch_by_surface_config", by_surface_config),
    list("mordin_rematch_style_buckets", mordin_style_buckets)
  )) {
    tname <- pair[[1]]
    df <- as.data.frame(pair[[2]])
    print(paste("Writing", nrow(df), "rows to", tname))
    dbExecute(con, paste0("DROP TABLE IF EXISTS `", tname, "`"))
    dbWriteTable(con, tname, df, overwrite = TRUE, row.names = FALSE)
  }
  print("Mordin rematch summary tables written to database")
}, error = function(e) {
  print(paste("Mordin DB write failed:", e$message))
})

out_dir <- "mordin_weight_rematch_out"
dir.create(out_dir, showWarnings = FALSE)
write_csv(overall, file.path(out_dir, "overall.csv"))
write_csv(by_surface, file.path(out_dir, "by_surface.csv"))
write_csv(by_config, file.path(out_dir, "by_config.csv"))
write_csv(by_surface_config, file.path(out_dir, "by_surface_config.csv"))
write_csv(mordin_style_buckets, file.path(out_dir, "mordin_style_buckets.csv"))
print(paste("CSV backup in", out_dir))

## Disconnect from DB (same as write.R)
#######################################
dbDisconnect(con)
print("Mordin rematch complete - disconnected")
