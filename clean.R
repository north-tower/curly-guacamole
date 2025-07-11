# clean.R

# Check if race_id column exists before trying to remove it
if ("race_id" %in% colnames(races)) {
  CL_data <- races %>% 
    select( -race_id) %>% 
    filter(winning_time_secs > 10) %>% 
    mutate(going = case_when(
      going == "Good - Firm" ~ "Good to Firm",
      going != "Good - Firm" ~ going
    ))
} else {
  # If race_id doesn't exist, just filter and mutate without removing it
  CL_data <- races %>% 
    filter(winning_time_secs > 10) %>% 
    mutate(going = case_when(
      going == "Good - Firm" ~ "Good to Firm",
      going != "Good - Firm" ~ going
    ))
}


AW_goings <- data.frame(going = c("Slow", "Standard to Slow", "Standard", "Standard to Fast", "Fast"))
turf_going_index <- data.frame(going = c("Firm", "Good to Firm", "Good", "Good - Yielding", "Yielding", "Good to Soft", "Yielding - Soft", "Soft", "Soft to Heavy", "Heavy"),
                               going_index = c(1, 2, 3, 4, 5, 6, 7, 8, 9, 10))

CL_data <- CL_data %>%
  mutate(WT_per_mile = (winning_time_secs * 1760)/distance_yards) %>% 
  filter(track_type != "Turf" | !going %in% AW_goings$going) %>% 
  filter(track_type == "Turf" | going %in% AW_goings$going)




