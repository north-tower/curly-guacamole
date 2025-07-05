#!/bin/bash

# System Monitoring Script
# Run as: bash monitor.sh

echo "=== System Resource Monitor ==="
echo "Date: $(date)"
echo ""

# CPU and Memory usage
echo "CPU Usage:"
top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1
echo ""

echo "Memory Usage:"
free -h
echo ""

# Disk usage
echo "Disk Usage:"
df -h
echo ""

# Check if R processes are running
echo "R Processes:"
ps aux | grep -i r | grep -v grep
echo ""

# Check recent log files
echo "Recent Analysis Logs:"
ls -la logs/ | tail -5
echo ""

# Database connection test
echo "Database Connection Test:"
mysql -u your_username -p -e "SELECT 1;" 2>/dev/null && echo "Database: OK" || echo "Database: FAILED"
echo ""

# Check cron jobs
echo "Active Cron Jobs:"
crontab -l 2>/dev/null | grep -v "^#" | grep -v "^$" 