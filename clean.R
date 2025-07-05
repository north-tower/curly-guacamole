# clean.R

# First, let's see what columns we actually have
print("Available columns in races data:")
print(names(races))

# Remove columns only if they exist
columns_to_remove <- c("min_age", "race_id")
existing_columns <- names(races)
columns_to_remove <- columns_to_remove[columns_to_remove %in% existing_columns]

CL_data <- races %>% 
  select(-all_of(columns_to_remove)) %>% 
  filter(winning_time_secs > 10) %>% 
  mutate(going = case_when(
    going == "Good - Firm" ~ "Good to Firm",
    going != "Good - Firm" ~ going
  ))


AW_goings <- data.frame(going = c("Slow", "Standard to Slow", "Standard", "Standard to Fast", "Fast"))
turf_going_index <- data.frame(going = c("Firm", "Good to Firm", "Good", "Good - Yielding", "Yielding", "Good to Soft", "Yielding - Soft", "Soft", "Soft to Heavy", "Heavy"),
                               going_index = c(1, 2, 3, 4, 5, 6, 7, 8, 9, 10))

CL_data <- CL_data %>%
  mutate(WT_per_mile = (winning_time_secs * 1760)/distance_yards) %>% 
  filter(track_type != "Turf" | !going %in% AW_goings$going) %>% 
  filter(track_type == "Turf" | going %in% AW_goings$going)




