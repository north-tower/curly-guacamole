
# If the libraries have not been installed on the system running
# the R code then this will have to be done first!
# Using:
# install.packages("[Package/Library_Name]")

# Import Libraries
library(RMariaDB)
library(plyr)
library(mgcv)
library(dplyr)      # Data manipulation (from tidyverse)
library(readr)      # Data reading (from tidyverse)
library(stringr)    # String manipulation (from tidyverse)
library(lubridate)  # Date handling (from tidyverse)
library(broom)


# Load and run each R script
source("load.R")
source("clean.R")
source("stdtimes.R")
source("speedratings.R")
source("func.R")
source("recency.R")
source("write.R")

