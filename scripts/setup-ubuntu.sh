#!/bin/bash

#########################################################
# Webhook Manager - Ubuntu Setup Script
# Automates installation of all prerequisites
#########################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Functions
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_info() {
    echo -e "${YELLOW}→ $1${NC}"
}

check_root() {
    if [[ $EUID -ne 0 ]]; then
        print_error "This script must be run as root or with sudo"
        exit 1
    fi
}

# Main setup
print_info "Starting Webhook Manager prerequisite installation..."
echo ""

# Check if running as root
check_root

# Update system
print_info "Updating system packages..."
apt-get update -y > /dev/null 2>&1
apt-get upgrade -y > /dev/null 2>&1
print_success "System updated"

# Install basic dependencies
print_info "Installing basic dependencies..."
apt-get install -y software-properties-common apt-transport-https ca-certificates \
    curl wget git unzip build-essential gnupg2 lsb-release > /dev/null 2>&1
print_success "Basic dependencies installed"

# Install Nginx
print_info "Installing Nginx..."
apt-get install -y nginx > /dev/null 2>&1
systemctl enable nginx > /dev/null 2>&1
systemctl start nginx > /dev/null 2>&1
print_success "Nginx installed and started"

# Add PHP repository
print_info "Adding PHP repository..."
add-apt-repository -y ppa:ondrej/php > /dev/null 2>&1
apt-get update -y > /dev/null 2>&1
print_success "PHP repository added"

# Install multiple PHP versions
print_info "Installing PHP versions (7.4, 8.0, 8.1, 8.2, 8.3, 8.4)..."
for version in 7.4 8.0 8.1 8.2 8.3 8.4; do
    print_info "Installing PHP $version..."
    apt-get install -y \
        php${version}-fpm \
        php${version}-cli \
        php${version}-common \
        php${version}-mysql \
        php${version}-pgsql \
        php${version}-sqlite3 \
        php${version}-zip \
        php${version}-gd \
        php${version}-mbstring \
        php${version}-curl \
        php${version}-xml \
        php${version}-bcmath \
        php${version}-intl \
        php${version}-redis > /dev/null 2>&1
    systemctl enable php${version}-fpm > /dev/null 2>&1
    systemctl start php${version}-fpm > /dev/null 2>&1
    print_success "PHP $version installed"
done

# Install Composer
print_info "Installing Composer..."
curl -sS https://getcomposer.org/installer | php > /dev/null 2>&1
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer
print_success "Composer installed"

# Install Node.js from NodeSource repository
print_info "Adding NodeSource repository for Node.js 20..."
curl -fsSL https://deb.nodesource.com/setup_20.x | bash - > /dev/null 2>&1
print_success "NodeSource repository added"

print_info "Installing Node.js 20..."
apt-get install -y nodejs > /dev/null 2>&1
NODE_VERSION=$(node -v 2>/dev/null)
NPM_VERSION=$(npm -v 2>/dev/null)
print_success "Node.js $NODE_VERSION and npm $NPM_VERSION installed"

# Install PM2 globally
print_info "Installing PM2..."
npm install -g pm2 > /dev/null 2>&1
pm2 startup systemd > /dev/null 2>&1
print_success "PM2 installed"

# Install Redis
print_info "Installing Redis..."
apt-get install -y redis-server > /dev/null 2>&1
systemctl enable redis-server > /dev/null 2>&1
systemctl start redis-server > /dev/null 2>&1
print_success "Redis installed and started"

# Install MySQL
print_info "Installing MySQL..."
apt-get install -y mysql-server > /dev/null 2>&1
systemctl enable mysql > /dev/null 2>&1
systemctl start mysql > /dev/null 2>&1
print_success "MySQL installed and started"

# Install Certbot for SSL
print_info "Installing Certbot..."
apt-get install -y certbot python3-certbot-nginx > /dev/null 2>&1
print_success "Certbot installed"

# Install Supervisor for process management
print_info "Installing Supervisor..."
apt-get install -y supervisor > /dev/null 2>&1
systemctl enable supervisor > /dev/null 2>&1
systemctl start supervisor > /dev/null 2>&1
print_success "Supervisor installed and started"

# Create web directories
print_info "Creating web directories..."
mkdir -p /var/www
chown -R www-data:www-data /var/www
chmod -R 755 /var/www
print_success "Web directories created"

# Create PM2 config directory
print_info "Creating PM2 config directory..."
mkdir -p /etc/pm2
chmod 755 /etc/pm2
print_success "PM2 config directory created"

# Summary
echo ""
print_success "=========================================="
print_success "Prerequisites installation completed!"
print_success "=========================================="
echo ""
print_info "Installed components:"
echo "  • Nginx"
echo "  • PHP 7.4, 8.0, 8.1, 8.2, 8.3, 8.4 with FPM"
echo "  • Composer"
echo "  • Node.js 20 with npm (system-wide)"
echo "  • PM2"
echo "  • Redis"
echo "  • MySQL"
echo "  • Supervisor"
echo "  • Certbot"
echo ""
print_info "Next steps:"
echo "  1. Run: bash scripts/setup-sudoers.sh"
echo "  2. Configure MySQL database"
echo "  3. Clone and setup your Laravel application"
echo ""
