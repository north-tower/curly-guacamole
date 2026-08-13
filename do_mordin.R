# Mordin Weight Rematch — standalone runner
# Uses the same Database_Access.cnf + coolwed1_wp364 schema as load.R / write.R
#
# Run:
#   Rscript do_mordin.R
# or in R:
#   source("do_mordin.R")

library(RMariaDB)
library(tidyverse)
library(DBI)

source("mordin_weight_rematch.R")
