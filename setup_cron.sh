#!/bin/bash

# Cron Job Setup Script
# Run as: bash setup_cron.sh

PROJECT_DIR="/home/$(whoami)/Web_SR_Analysis"
SCRIPT_PATH="$PROJECT_DIR/run_analysis.sh"

# Make scripts executable
chmod +x "$SCRIPT_PATH"
chmod +x "$PROJECT_DIR/setup_vps.sh"

# Add cron job to run analysis daily at 6 AM
(crontab -l 2>/dev/null; echo "0 6 * * * $SCRIPT_PATH") | crontab -

# Add cron job to run analysis every 4 hours during race days (example)
# (crontab -l 2>/dev/null; echo "0 */4 * * 1-5 $SCRIPT_PATH") | crontab -

echo "Cron jobs set up successfully!"
echo "Analysis will run daily at 6:00 AM"
echo "To view cron jobs: crontab -l"
echo "To edit cron jobs: crontab -e" 