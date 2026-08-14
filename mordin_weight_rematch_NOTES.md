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
#   (or: bash run_analysis.sh)
#
# Mordin study uses the SAME Database_Access.cnf and coolwed1_wp364 schema.
#
# Run the study alone (recommended):
#   Rscript do_mordin.R
#
# Or append it to a full daily run:
#   R -e "options(fhor.run_mordin=TRUE); source('do.R')"
#
# Results are written to MySQL (like sr_results / draw_bias):
#   coolwed1_wp364.mordin_rematch_overall
#   coolwed1_wp364.mordin_rematch_by_surface
#   coolwed1_wp364.mordin_rematch_by_config
#   coolwed1_wp364.mordin_rematch_by_surface_config
#   coolwed1_wp364.mordin_rematch_style_buckets
# Plus CSV backup in mordin_weight_rematch_out/
#
# Left off the default cron on purpose — 2021→today rematch scan is heavier
# than the daily speed job.
#
# ---------------------------------------------------------------------------
# Book methodology
# ---------------------------------------------------------------------------
# Close finish: within 2 lengths OR adjacent positions.
# Rematch: both horses' immediate next race.
# Book finding: ~58% "loser lost again" whether weight raised/lowered/same;
# when reduced, avg pull ~2.45 lb; races averaged just over 9f.
# Weight matters more on galloping/straight tests than tight turning tracks.
#
# ---------------------------------------------------------------------------
# Schema mapping
# ---------------------------------------------------------------------------
# horse_id → runner_id | weight_lbs → weight_pounds - jockey_claim
# lengths_behind → distance_beaten | tables → historic_*_beta
# track_type → daily_races_beta.track_type (historic_races_beta has no track_type)
# surface fallback → AW going list / known AW courses when daily row missing
# course_features join → same IF(... All Weather Flat ...) as my_daily_races_UPDATE
