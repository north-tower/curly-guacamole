
# If the libraries have not been installed on the system running
# the R code then this will have to be done first!
# Using:
# install.packages("[Package/Library_Name]")

# Import Libraries
library(RMariaDB)
library(plyr)
library(mgcv)
library(tidyverse)
library(broom)


# Load and run each R script
source("load.R")
source("clean.R")
source("stdtimes.R")
source("speedratings.R")
source("func.R")
source("recency.R")
source("write.R")

# Optional research study (heavy historic query — off by default so daily cron stays fast).
# Enable with:  options(fhor.run_mordin = TRUE); source("do.R")
# Or run alone: Rscript do_mordin.R
if (isTRUE(getOption("fhor.run_mordin", FALSE))) {
  print("Running Mordin weight rematch study…")
  source("mordin_weight_rematch.R")
}


# If the libraries have not been installed on the system running
# the R code then this will have to be done first!
# Using:
# install.packages("[Package/Library_Name]")

# Import Libraries




