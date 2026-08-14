# Mordin Weight Rematch — Study Notes
#
# Source: Nick Mordin, Betting for a Living, Chapter 11 "Why you should ignore weight"
# Script: mordin_weight_rematch.R
#
# ---------------------------------------------------------------------------
# How it fits the existing R pipeline
# ---------------------------------------------------------------------------
# Daily speed-rating chain (unchanged):
#   do.R → load.R → clean.R → stdtimes.R → speedratings.R → func.R → recency.R → write.R
#
# Run the study alone (recommended):
#   Rscript do_mordin.R
#
# Or append it to a full daily run:
#   R -e "options(fhor.run_mordin=TRUE); source('do.R')"
#
# Before running on the server, confirm this build string prints:
#   Mordin rematch: script build = geometry_v3_case_when
# If you still see surface_v2 / if (nzchar) errors, the file was not synced.
#
# Results → MySQL (coolwed1_wp364):
#   mordin_rematch_overall
#   mordin_rematch_by_geometry   ← PRIMARY DL-task table
#   mordin_rematch_by_surface
#   mordin_rematch_by_config
#   mordin_rematch_by_surface_config
#   mordin_rematch_style_buckets
# Plus CSV backup in mordin_weight_rematch_out/
#
# ---------------------------------------------------------------------------
# Brief → implementation
# ---------------------------------------------------------------------------
# Scope: flat handicaps 2021→today (Turf + AW separated)
# Match: same race, within 2 lengths OR adjacent positions
# Rematch: both horses' immediate next race (lag race_id)
# Weight: loser gets >=2 lb relative pull (weight_pounds − jockey_claim)
# Primary output: track_geometry
#   Turning Synthetic | Turning Turf | Straight Galloping Turf | …
# Metrics: total_rematches | loser_won_rematch | reversal_rate_pct
#
# ---------------------------------------------------------------------------
# Schema mapping
# ---------------------------------------------------------------------------
# horse_id → runner_id | weight → weight_pounds − jockey_claim
# lengths_behind → distance_beaten | historic_*_beta + daily_races_beta.track_type
# course_features → profile / geometry text (join like my_daily_races_UPDATE)
