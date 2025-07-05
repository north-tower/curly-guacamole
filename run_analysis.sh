#!/bin/bash

# Automated R Analysis Runner
# Run as: bash run_analysis.sh

# Set working directory
PROJECT_DIR="/home/$(whoami)/Web_SR_Analysis"
cd "$PROJECT_DIR"

# Create logs directory
mkdir -p logs

# Get current timestamp
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
LOG_FILE="logs/analysis_${TIMESTAMP}.log"

echo "Starting R analysis at $(date)" | tee -a "$LOG_FILE"

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