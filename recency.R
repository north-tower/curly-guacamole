

SR_data <- SR_data[with(SR_data, order(runner_id, meeting_date)), ]

day <- split(SR_data$meeting_date, SR_data$runner_id)
day <- sapply(day, FUN = function(y) as.matrix(y - y[1]))
day <- do.call(rbind, day)
SR_data <- transform(SR_data, day = day)
rm(day)
#
SR_data$distance_behind_winner[SR_data$finish_position == 1] <- 0
stats <- aggregate(cbind(finish_position, distance_behind_winner) ~
                     meeting_date + course + distance_yards, SR_data, FUN = max, na.rm = T)
SR_data <- merge(SR_data, stats,
                 by = c("meeting_date", "course", "distance_yards"),
                 suffixes = c("", ".max"))
SR_data$finish_position[is.na(SR_data$finish_position)] <-
  SR_data$finish_position.max[is.na(SR_data$finish_position)]
SR_data$distance_behind_winner[is.na(SR_data$distance_behind_winner)] <-
  SR_data$distance_behind_winner.max[is.na(SR_data$distance_behind_winner)]
SR_data <- subset(SR_data, T,
                  -c(finish_position.max, distance_behind_winner.max))

stats <- aggregate(speed_rating ~
                     meeting_date + course + distance_yards, SR_data, FUN = min, na.rm = T)
SR_data <- merge(SR_data, stats,
                 by = c("meeting_date", "course", "distance_yards"),
                 suffixes = c("", ".min"))
SR_data$speed_rating[is.na(SR_data$speed_rating)] <-
  SR_data$speed_rating.min[is.na(SR_data$speed_rating)]
SR_data <- subset(SR_data, T,
                  -c(speed_rating.min))

SR_data <- SR_data[with(SR_data, order(runner_id, meeting_date)), ]
stats <- dlply(SR_data,
               .(runner_id),
               .fun = function(DF) rollmean(DF$day, DF[, c("finish_position",
                                                           "distance_behind_winner",
                                                           "speed_rating")
               ]
               )
)
stats <- do.call(rbind, stats)
SR_data <- cbind(SR_data, stats)
rm(stats)
