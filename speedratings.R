

runners_data <- runners

SR_data <- merge(runners_data, STD_data, by = c("course", "distance_yards", "race_type", "track_type"))
SR_data <- SR_data %>% 
  merge(going_conversion) %>% 
  merge(track_conversion)
SR_data <- SR_data %>% 
  mutate(WT_per_mile = (winning_time_secs * 1760)/distance_yards) %>% 
  mutate(
    going_value_adj = going_value_coef * going_value,
    track_value_adj = track_value_coef * track_value,
    distance_yards_adj = distance_yards_coef * distance_yards) %>%
  mutate(adjusted_WT = WT_per_mile - (going_value_adj + distance_yards_adj + track_value_adj)) %>% 
  mutate(distance_behind_winner = if_else(is.na(distance_behind_winner), 0, distance_behind_winner))

SR_data <- SR_data %>% 
  select(race_id, distance_yards, course, race_type, track_type, meeting_date, scheduled_time, winning_time_secs) %>% 
  distinct(.keep_all = TRUE) %>% 
  group_by(race_type, track_type, course, distance_yards) %>% 
  mutate(sd_3.92 = 3.92 * sd(winning_time_secs), mean_WT_secs = mean(winning_time_secs)) %>% 
  filter(abs(winning_time_secs - mean_WT_secs) < sd_3.92) %>% 
  select(-sd_3.92, -mean_WT_secs) %>% 
  merge(SR_data)

SR_data <- SR_data %>% 
  group_by(race_id) %>% 
  mutate(sd_1 = sd(distance_behind_winner), mean_lag = mean(distance_behind_winner)) %>% 
  mutate(Lagged = if_else(distance_behind_winner > (mean_lag + sd_1), 1, 0)) %>% 
  select(-sd_1, -mean_lag) %>% 
  group_by(runner_id) %>% 
  mutate(Lag_pct = round(100 * sum(Lagged) / n(), 1), No_official_races = n()) %>% 
  ungroup()

########################

SR_data <- SR_data %>% 
  mutate(LPS = case_when(
    race_type == "Flat" & track_type != "Turf" & course == "Southwell" ~ 5,
    race_type == "Flat" & track_type != "Turf" & course != "Southwell" ~ 6,
    race_type == "N_H_Flat" & track_type != "Turf" & course == "Southwell" ~ 4,
    race_type == "N_H_Flat" & track_type != "Turf" & course != "Southwell" ~ 5,
    (race_type == "Chase" | race_type == "Hurdle") & going_index <= 7 ~ 5,
    (race_type == "Chase" | race_type == "Hurdle") & going_index <= 12 ~ 4.5,
    (race_type == "Chase" | race_type == "Hurdle") & going_index <= 15 ~ 4,
    (race_type == "Flat" | race_type == "N_H_Flat") & track_type == "Turf" & going_index <= 7 ~ 6,
    (race_type == "Flat" | race_type == "N_H_Flat") & track_type == "Turf" & going_index <= 12 ~ 5.5,
    (race_type == "Flat" | race_type == "N_H_Flat") & track_type == "Turf" & going_index <= 15 ~ 5
  ))

########################

draw_bias <- SR_data %>% 
  filter(!is.na(stall_number)) %>% 
  mutate(half_furlongs = as.numeric(as.character(cut(.$distance_yards, breaks = seq(55, 8855, by = 110),
                  labels = FALSE)))) %>% 
  mutate(distance_yards_min = half_furlongs * 110 - 54, distance_yards_max = half_furlongs * 110 + 55) %>% 
  group_by(race_type, track_type, course, stall_number, distance_yards_min, distance_yards_max) %>% 
  summarise(win_count = length(which(finish_position == 1)),
            n = n(),
            win_percent_by_stall = 100*length(which(finish_position == 1))/n()
            ) %>% 
  filter(n>10)
  
#########################

SR_data <- SR_data %>% 
  mutate(Δ_WT = adjusted_WT - std_adj_WT) %>% 
  mutate(winners_SR = 80 - (LPS * Δ_WT)) %>% 
  mutate(winners_SR = ifelse(winners_SR < 0, 0, winners_SR))

#############################



SR_data <- SR_data %>% 
  mutate(speed_rating = if_else(Lagged == 0, round(winners_SR - (distance_behind_winner * 1760 / (distance_yards)), 1), 0))


SR_data <- SR_data %>% 
  select(-winning_time_secs, -going_value, -going_index, -adjusted_WT, -std_adj_WT, -LPS, -Δ_WT, -winners_SR,
         -going_value_coef, -distance_yards_coef, -going_value_adj, -distance_yards_adj, -WT_per_mile, -num_fences,
         -Lagged)

#############
