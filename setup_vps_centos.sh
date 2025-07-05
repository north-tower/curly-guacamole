#!/bin/bash

# VPS Setup Script for Web_SR_Analysis R Project (CentOS/RHEL)
# Run as: sudo bash setup_vps_centos.sh

echo "Setting up CentOS/RHEL VPS for R project..."

# Update system
yum update -y

# Install EPEL repository (required for R)
yum install -y epel-release

# Install R and development tools
yum install -y R R-devel

# Install MariaDB/MySQL
yum install -y mariadb-server mariadb

# Start and enable MariaDB
systemctl start mariadb
systemctl enable mariadb

# Install required system dependencies for R packages
yum install -y mariadb-devel openssl-devel libcurl-devel libxml2-devel

# Install additional useful tools
yum install -y htop git screen tmux wget

# Install RStudio Server (CentOS version)
wget https://download2.rstudio.org/server/centos7/x86_64/rstudio-server-rhel-2023.12.0-369-x86_64.rpm
yum install -y rstudio-server-rhel-2023.12.0-369-x86_64.rpm

# Start RStudio Server
systemctl start rstudio-server
systemctl enable rstudio-server

# Secure MariaDB installation
mysql_secure_installation

echo "CentOS/RHEL system setup complete!"
echo "RStudio Server should be available on port 8787" 