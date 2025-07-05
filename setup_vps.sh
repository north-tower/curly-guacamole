#!/bin/bash

# VPS Setup Script for Web_SR_Analysis R Project
# Run as: sudo bash setup_vps.sh

echo "Setting up VPS for R project..."

# Update system
sudo apt update && sudo apt upgrade -y

# Install R and RStudio Server
sudo apt install -y r-base r-base-dev
sudo apt install -y gdebi-core
wget https://download2.rstudio.org/server/bionic/amd64/rstudio-server-2023.12.0-369-amd64.deb
sudo gdebi rstudio-server-2023.12.0-369-amd64.deb

# Install MariaDB/MySQL
sudo apt install -y mariadb-server mariadb-client
sudo mysql_secure_installation

# Install required system dependencies for R packages
sudo apt install -y libmariadb-dev libssl-dev libcurl4-openssl-dev libxml2-dev

# Install additional useful tools
sudo apt install -y htop git screen tmux

echo "System setup complete!" 