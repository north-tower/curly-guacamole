# R Package Installation Script
# Run this in R to install all required packages

# Set CRAN mirror
options(repos = c(CRAN = "https://cloud.r-project.org"))

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
    if (!require(package, character.only = TRUE, quietly = TRUE)) {
      cat(paste("Installing package:", package, "\n"))
      tryCatch({
        install.packages(package, dependencies = TRUE)
        library(package, character.only = TRUE)
        cat(paste("✓ Successfully installed and loaded:", package, "\n"))
      }, error = function(e) {
        cat(paste("✗ Failed to install:", package, "-", e$message, "\n"))
      })
    } else {
      cat(paste("✓ Package", package, "is already installed and loaded.\n"))
    }
  }
}

# Install packages
cat("Installing required R packages...\n")
install_if_missing(required_packages)

cat("Package installation process completed!\n") 