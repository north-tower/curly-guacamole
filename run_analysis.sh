#!/bin/bash

# Automated R Analysis Runner
# Run as: bash run_analysis.sh

# Set working directory to current directory
PROJECT_DIR="$(pwd)"
cd "$PROJECT_DIR"

# Create logs directory
mkdir -p logs

# Get current timestamp
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
LOG_FILE="logs/analysis_${TIMESTAMP}.log"

echo "Starting R analysis at $(date)" | tee -a "$LOG_FILE"
echo "Working directory: $PROJECT_DIR" | tee -a "$LOG_FILE"

# Check if R is installed
if ! command -v Rscript &> /dev/null; then
    echo "ERROR: Rscript not found. Please install R first." | tee -a "$LOG_FILE"
    echo "Run: sudo bash setup_vps_centos.sh" | tee -a "$LOG_FILE"
    exit 1
fi

# Run R script with error handling
Rscript do.R 2>&1 | tee -a "$LOG_FILE"

# Check exit status
if [ $? -eq 0 ]; then
    echo "Analysis completed successfully at $(date)" | tee -a "$LOG_FILE"
    # Optional: Send notification
    # echo "Analysis complete" | mail -s "R Analysis Success" your@email.com
else
    echo "Analysis failed at $(date)" | tee -a "$LOG_FILE"
    # Optional: Send error notification
    # echo "Analysis failed" | mail -s "R Analysis Error" your@email.com
    exit 1
fi 