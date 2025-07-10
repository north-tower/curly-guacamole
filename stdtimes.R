
#########################

# Check if CL_data has any rows before proceeding
if (nrow(CL_data) == 0) {
  print("Warning: CL_data is empty. Creating empty STD_data structure.")
  STD_data <- data.frame()
} else {
  combos <- as.data.frame(table(CL_data[c("course", "distance_yards", "race_type")]))
  combos <- combos %>% 
    filter(Freq >= 20) %>% 
    select(-Freq)
  STD_data <- merge(CL_data, combos, by = c("course", "distance_yards", "race_type"))
  rm(combos)
}

# If STD_data is empty, create empty structures and skip processing
if (nrow(STD_data) == 0) {
  print("Warning: STD_data is empty. Skipping standard times processing.")
  going_conversion <- data.frame()
  track_conversion <- data.frame()
} else {
  ##########################

  Chase_going <- STD_data %>% 
    filter(race_type == "Chase") %>% {
      group_by(., going) %>%
        summarise(going_value = mean(WT_per_mile))
    } %>% 
    mutate(race_type = "Chase") %>% 
    mutate(going_value = going_value - (.$going_value[going == "Good"]))
  #
  Hurdle_going <- STD_data %>% 
    filter(race_type == "Hurdle") %>% {
      group_by(., going) %>%
        summarise(going_value = mean(WT_per_mile))
    } %>% 
    mutate(race_type = "Hurdle") %>% 
    mutate(going_value = going_value - (.$going_value[going == "Good"]))
  #
  Flat_going <- STD_data %>% 
    filter(race_type == "Flat") %>% {
      group_by(., going) %>%
        summarise(going_value = mean(WT_per_mile))
    } %>% 
    mutate(going_value = going_value - (.$going_value[going == "Good"])) %>% {
      rbind(
        mutate(., race_type = "Flat"),
        mutate(., race_type = "N_H_Flat")
      )}
  ##
  going_conversion <- rbind(Chase_going, Hurdle_going, Flat_going) %>% 
    merge(turf_going_index, all.x = TRUE)
  #########

  track_conversion <- STD_data %>% 
    filter(race_type == "Flat") %>% {
      group_by(., track_type) %>%
        summarise(track_value = mean(WT_per_mile))
    } %>% 
    mutate(track_value = track_value - (.$track_value[track_type == "Turf"]))

  #########

  STD_data <- STD_data %>% 
    merge(going_conversion, all.x = TRUE) %>% 
    merge(track_conversion, all.x = TRUE)

  rm(Chase_going, Hurdle_going, Flat_going)

  ###########################

  std_error <- function(x){sd(x)/length(x)}

  STD_data <- STD_data %>%
    group_by(course, distance_yards, race_type) %>%
    filter(n() > 5) %>%
    mutate(sd_3.92 = 3.92 * sd(WT_per_mile), mean_WT = mean(WT_per_mile)) %>%
    filter(abs(WT_per_mile - mean_WT) < sd_3.92) %>%
    select(-sd_3.92, -mean_WT) %>% 
    ungroup()

  #####################################

  going_factor_analysis <- STD_data %>% 
    nest_by(race_type) %>% 
    mutate(going_mod = list(lm(WT_per_mile ~ going_value, data = data))) %>% {
      reframe(., tidy(going_mod)) %>% 
        cbind(test_variable = "going_value_coef", .)
    }

  going_FA <- going_factor_analysis %>% 
    filter(p.value < 0.01 & term != "(Intercept)") %>% 
    select(-term, -std.error, -statistic, -p.value) %>% 
    group_by(race_type) %>% 
    pivot_wider(names_from = c(test_variable), values_from = estimate, values_fill = 0)
  rm(going_factor_analysis)

  STD_data <- STD_data %>% 
    merge(going_FA, all.x = TRUE) %>%
    mutate(going_value_adj = going_value_coef * going_value) %>% 
    mutate(adjusted_WT = WT_per_mile - going_value_adj)
    
  rm(going_FA)
  #######################

  track_factor_analysis <- STD_data %>% 
    nest_by(race_type) %>% 
    mutate(track_mod = list(lm(adjusted_WT ~ track_value, data = data))) %>% {
      reframe(., tidy(track_mod)) %>% 
        cbind(test_variable = "track_value_coef", .)
    }

  track_FA <- track_factor_analysis %>% 
    mutate(estimate = if_else(is.na(estimate), 0, estimate)) %>% 
    filter((p.value < 0.01 | is.na(p.value)) & term != "(Intercept)") %>% 
    select(-term, -std.error, -statistic, -p.value) %>%
    pivot_wider(names_from = c(test_variable), values_from = estimate, values_fill = 0)
  rm(track_factor_analysis)

  STD_data <- STD_data %>% 
    merge(track_FA, all.x = TRUE) %>%
    mutate(track_value_adj = track_value_coef * track_value) %>% 
  mutate(adjusted_WT = adjusted_WT - track_value_adj)

  rm(track_FA)
  #######################

  distance_factor_analysis <- STD_data %>% 
    nest_by(race_type) %>% 
    mutate(distance_mod = list(lm(adjusted_WT ~ distance_yards, data = data))) %>% {
      reframe(., tidy(distance_mod)) %>% 
        cbind(test_variable = "distance_yards_coef", .)
    }

  distance_FA <- distance_factor_analysis %>% 
    filter(p.value < 0.01 & term != "(Intercept)") %>% 
    select(-term, -std.error, -statistic, -p.value) %>% 
    group_by(race_type) %>% 
    pivot_wider(names_from = c(test_variable), values_from = estimate, values_fill = 0)
  rm(distance_factor_analysis)

  STD_data <- STD_data %>% 
    merge(distance_FA, all.x = TRUE) %>%
    mutate(distance_yards_adj = distance_yards_coef * distance_yards) %>% 
    mutate(adjusted_WT = adjusted_WT - distance_yards_adj)

  rm(distance_FA)
  ########################################################

  class_factor_analysis <- STD_data %>% 
    nest_by(race_type) %>% 
    filter(nrow(data) > 0) %>%  # Skip empty groups
    mutate(class_mod = list(lm(adjusted_WT ~ class, data = data))) %>% {
        reframe(., tidy(class_mod)) %>% 
          cbind(test_variable = "class_coef", .)
    }
  ##
  class_FA <- class_factor_analysis %>% 
    filter(term != "(Intercept)") %>% 
    mutate(estimate = if_else(p.value < 0.01, estimate, 0)) %>% 
    select(-term, -std.error, -statistic, -p.value) %>% 
    group_by(race_type) %>% 
    pivot_wider(names_from = c(test_variable), values_from = estimate, values_fill = 0)
  rm(class_factor_analysis)
  ##
  STD_data <- STD_data %>% 
    merge(class_FA, all.x = TRUE, all.y = FALSE)
  rm(class_FA)

  #####################################

  std_error <- function(x){sd(x)/length(x)}

  STD_data <- STD_data %>% 
    group_by(course, distance_yards, race_type) %>% 
    filter(n() > 5) %>% 
    mutate(sd_3.92 = 3.92 * sd(adjusted_WT), mean_WT = mean(adjusted_WT)) %>% 
    filter(abs(adjusted_WT - mean_WT) < sd_3.92) %>% 
    select(-sd_3.92, -mean_WT)
    

  #####################################

  STD_data <- STD_data %>% 
    group_by(course, distance_yards, race_type, track_type, going_value_coef, class_coef, distance_yards_coef, track_value_coef) %>% 
    summarise(std_WT = mean(adjusted_WT),
              mean_class = mean(class)
              ) %>% 
    mutate(std_adj_WT = std_WT - (mean_class - 1) * class_coef) %>% 
    ungroup() %>% 
    select(-class_coef, -std_WT, -mean_class)
}








