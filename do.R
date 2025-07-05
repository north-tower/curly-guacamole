
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


# Set the file location of the R scripts
setwd("~/Website Code files/Web_SR_Analysis")

# Load and run each R script
source("load.R")
source("clean.R")
source("stdtimes.R")
source("speedratings.R")
source("func.R")
source("recency.R")
source("write.R")

