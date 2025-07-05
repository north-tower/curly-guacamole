# R Package Installation Script
# Run this in R to install all required packages

# List of required packages
required_packages <- c(
  "RMariaDB",
  "plyr", 
  "mgcv",
  "tidyverse",
  "broom",
  "dplyr",
  "ggplot2",
  "readr",
  "stringr",
  "lubridate"
)

# Function to install packages if not already installed
install_if_missing <- function(packages) {
  for (package in packages) {
    if (!require(package, character.only = TRUE)) {
      install.packages(package, dependencies = TRUE)
      library(package, character.only = TRUE)
    } else {
      cat(paste("Package", package, "is already installed.\n"))
    }
  }
}

# Install packages
cat("Installing required R packages...\n")
install_if_missing(required_packages)

cat("All packages installed successfully!\n") 