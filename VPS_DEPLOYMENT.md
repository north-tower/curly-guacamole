# VPS Deployment Guide for Web_SR_Analysis

## Quick Start

### 1. Initial Setup
```bash
# Clone or upload your project to VPS
cd /home/your_username/
git clone <your-repo> Web_SR_Analysis
cd Web_SR_Analysis

# Run initial setup
sudo bash setup_vps.sh
```

### 2. Configure Database
```bash
# Edit database configuration
nano Database_Access.cnf
# Update with your actual database credentials
```

### 3. Install R Packages
```bash
# Start R and install packages
R
source("install_r_packages.R")
```

### 4. Test Run
```bash
# Make scripts executable
chmod +x *.sh

# Test the analysis
bash run_analysis.sh
```

### 5. Set Up Automation
```bash
# Set up cron jobs
bash setup_cron.sh

# Or set up as systemd service
sudo cp r-analysis.service /etc/systemd/system/
sudo systemctl enable r-analysis.service
sudo systemctl start r-analysis.service
```

## Configuration

### Database Setup
1. Create MariaDB database and user
2. Import your data tables
3. Update `Database_Access.cnf` with credentials

### Memory Optimization
- For large datasets, consider increasing R memory limit:
```bash
export R_MAX_MEM_SIZE=4G
```

### Logging
- Logs are stored in `logs/` directory
- Each run creates timestamped log file
- Monitor with: `tail -f logs/analysis_YYYYMMDD_HHMMSS.log`

## Monitoring

### System Resources
```bash
# Check system status
bash monitor.sh

# Monitor in real-time
htop
```

### Analysis Status
```bash
# Check recent runs
ls -la logs/

# View latest log
tail -f logs/$(ls -t logs/ | head -1)
```

## Troubleshooting

### Common Issues
1. **Database Connection Failed**
   - Check credentials in `Database_Access.cnf`
   - Verify MariaDB is running: `sudo systemctl status mariadb`

2. **R Package Errors**
   - Re-run: `R -e "source('install_r_packages.R')"`

3. **Memory Issues**
   - Increase swap space
   - Reduce dataset size or optimize queries

### Performance Tips
- Use SSD storage for better I/O performance
- Consider running analysis during off-peak hours
- Monitor and adjust cron schedule based on data update frequency

## Security
- Use strong database passwords
- Configure firewall rules
- Keep system updated
- Use SSH keys instead of passwords

## Backup Strategy
```bash
# Backup database
mysqldump -u username -p database_name > backup.sql

# Backup project files
tar -czf project_backup.tar.gz Web_SR_Analysis/
``` 