#!/bin/bash

# Install R Only (MariaDB already exists)
# Run as: sudo bash install_r_only.sh

echo "Installing R on AlmaLinux..."

# Update system
yum update -y

# Install EPEL repository (required for R)
yum install -y epel-release

# Install R and development tools
yum install -y R R-devel

# Install required system dependencies for R packages
yum install -y mariadb-devel openssl-devel libcurl-devel libxml2-devel

# Install additional useful tools
yum install -y htop git screen tmux wget

echo "R installation complete!"
echo "Testing R installation..."

# Test R installation
if command -v Rscript &> /dev/null; then
    echo "✓ Rscript is installed: $(which Rscript)"
    Rscript --version | head -1
else
    echo "✗ Rscript installation failed"
    exit 1
fi

echo ""
echo "Next steps:"
echo "1. Test R: Rscript --version"
echo "2. Install R packages: R -e \"source('install_r_packages.R')\""
echo "3. Test your analysis: bash run_analysis.sh" 