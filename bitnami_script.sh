#!/bin/bash

# Exit on any error
set -e

# Variables
REPO_URL="https://github.com/zeemsabri/ozee-crm.git"
BRANCH="dev"
APP_NAME="ozee-crm"
APP_PATH="/opt/bitnami/projects/$APP_NAME"
DOMAIN="crm.ozeweeb.com.au"
LARAVEL_ENV="laravel_env"
PHP_PATH="/opt/bitnami/php/bin/php"
COMPOSER_PATH="/opt/bitnami/php/bin/composer"
USER=$(whoami)

# Step 1: Update system and install dependencies
echo "Updating system and installing dependencies..."
sudo apt-get update -y
sudo apt-get install -y git unzip curl lsb-release

# Check PHP version
echo "Checking PHP version..."
$PHP_PATH -v
# Install PHP extensions if not already present
echo "Installing PHP extensions..."
sudo apt-get install -y php-mysql php-xml php-mbstring php-bcmath php-zip

# Install Composer if not already installed
if [ ! -f "$COMPOSER_PATH" ]; then
    echo "Installing Composer..."
    curl -sS https://getcomposer.org/installer | sudo $PHP_PATH -- --install-dir=/opt/bitnami/php/bin --filename=composer
else
    echo "Composer already installed at $COMPOSER_PATH."
fi

# Step 2: Create project directory and clone or update repository
echo "Cloning or updating repository..."
sudo mkdir -p /opt/bitnami/projects
sudo chown $USER:$USER /opt/bitnami/projects
cd /opt/bitnami/projects
if [ -d "$APP_NAME" ]; then
    echo "Directory $APP_NAME exists. Pulling latest changes from $BRANCH branch..."
    cd $APP_NAME
    git checkout $BRANCH
    git pull origin $BRANCH
else
    git clone -b $BRANCH $REPO_URL $APP_NAME
    cd $APP_NAME
fi

# Step 3: Set up Laravel environment and sensitive files
echo "Setting up Laravel environment and sensitive files..."
# Check if .env file exists, if not, create it from the template
if [ ! -f ".env" ]; then
    cp $LARAVEL_ENV .env
    sudo chown $USER:$USER .env
    chmod 600 .env
fi

# Function to read a variable and write to .env if it's not already set with a value
read_if_missing() {
    local var_name=$1
    local prompt_text=$2
    # Check if the value is empty, or the line doesn't exist
    if ! grep -q "^$var_name=[^ ]" .env; then
        read -p "$prompt_text" var_value
        sed -i "s|^$var_name=.*|$var_name=\"$var_value\"|" .env
    else
        echo "$var_name is already set."
    fi
}

echo "Checking for sensitive keys in .env file. You will only be prompted for missing keys."
read_if_missing "GOOGLE_CLIENT_ID" "Enter GOOGLE_CLIENT_ID: "
read_if_missing "GOOGLE_CLIENT_SECRET" "Enter GOOGLE_CLIENT_SECRET: "
read_if_missing "EXCHANGE_RATES_API_KEY" "Enter EXCHANGE_RATES_API_KEY: "
read_if_missing "REVERB_APP_ID" "Enter REVERB_APP_ID: "
read_if_missing "REVERB_APP_KEY" "Enter REVERB_APP_KEY: "
read_if_missing "REVERB_APP_SECRET" "Enter REVERB_APP_SECRET: "

# Handle REVERB_HOST separately as it has a default value
if ! grep -q "^REVERB_HOST=[^ ]" .env; then
    read -p "Enter REVERB_HOST (press Enter to use $DOMAIN): " REVERB_HOST_NEW
    REVERB_HOST=${REVERB_HOST_NEW:-$DOMAIN}
    sed -i "s|^REVERB_HOST=.*|REVERB_HOST=\"$REVERB_HOST\"|" .env
else
    echo "REVERB_HOST is already set."
fi

# Update .env with database, app settings, and sensitive keys (non-user-input)
echo "Configuring default .env file settings..."
sed -i "s|APP_URL=.*|APP_URL=https://$DOMAIN|" .env
sed -i "s|DB_HOST=.*|DB_HOST=localhost|" .env
sed -i "s|DB_PORT=.*|DB_PORT=3306|" .env
sed -i "s|DB_DATABASE=.*|DB_DATABASE=ozee_crm_db|" .env
sed -i "s|DB_USERNAME=.*|DB_USERNAME=ozee_user|" .env
sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=secure_password_123|" .env
sed -i "s|^REVERB_PORT=.*|REVERB_PORT=443|" .env
sed -i "s|^REVERB_SCHEME=.*|REVERB_SCHEME=https|" .env

# Prompt for the email address for Certbot and Google tokens
echo ""
echo "An email address is required for SSL certificate registration and Google tokens."
read -p "Enter your email address: " EMAIL

# --- START OF GOOGLE TOKENS CREATION (BEFORE COMPOSER) ---
# Create the private storage directory if it's not present
sudo mkdir -p "$APP_PATH/storage/app/private"

# Check if the google_tokens.json file exists. Only prompt for tokens if it doesn't.
if [ ! -f "$APP_PATH/storage/app/private/google_tokens.json" ]; then
    echo ""
    echo "Google tokens not found. Please provide them to create the token file."
    read -p "Enter Google Access Token: " GOOGLE_ACCESS_TOKEN
    read -p "Enter Google Refresh Token: " GOOGLE_REFRESH_TOKEN

    # Create the JSON file with the provided tokens
    sudo bash -c "cat > $APP_PATH/storage/app/private/google_tokens.json" <<TOKEN_EOF
{
    "access_token": "$GOOGLE_ACCESS_TOKEN",
    "refresh_token": "$GOOGLE_REFRESH_TOKEN",
    "expires_in": 3599,
    "created_at": $(date +%s),
    "email": "$EMAIL"
}
TOKEN_EOF
    echo "Google token file created successfully."
else
    echo "Google token file already exists at $APP_PATH/storage/app/private/google_tokens.json. Skipping creation."
fi
# --- END OF GOOGLE TOKENS CREATION ---

# Step 4: Install Composer dependencies
echo "Installing Composer dependencies..."
$COMPOSER_PATH install --optimize-autoloader --no-dev


# Step 5: Set permissions
echo "Setting permissions..."
sudo chown -R daemon:daemon storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# --- START OF MARIADB RESTART AND WAIT LOOP ---
echo "Restarting MariaDB and waiting for it to be ready..."
sudo /opt/bitnami/ctlscript.sh restart mariadb

MYSQL_PASSWORD=$(cat /home/bitnami/bitnami_application_password)
MAX_RETRIES=10
RETRY_COUNT=0
until sudo /opt/bitnami/mariadb/bin/mariadb -u root -p"$MYSQL_PASSWORD" -e "SHOW DATABASES;" >/dev/null 2>&1 || [ $RETRY_COUNT -eq $MAX_RETRIES ]; do
    echo "Waiting for MariaDB to be ready... (Attempt $((RETRY_COUNT+1))/$MAX_RETRIES)"
    sleep 5
    RETRY_COUNT=$((RETRY_COUNT+1))
done

if [ $RETRY_COUNT -eq $MAX_RETRIES ]; then
    echo "Error: MariaDB did not become ready after multiple retries. Aborting."
    exit 1
fi
echo "MariaDB is ready."
# --- END OF MARIADB RESTART AND WAIT LOOP ---

# Step 6: Configure MySQL database
echo "Configuring MySQL database..."
# Create the database and user with the provided password
sudo /opt/bitnami/mariadb/bin/mariadb -u root -p"$MYSQL_PASSWORD" <<EOF
CREATE DATABASE IF NOT EXISTS ozee_crm_db;
CREATE USER IF NOT EXISTS 'ozee_user'@'localhost' IDENTIFIED BY 'secure_password_123';
GRANT ALL PRIVILEGES ON ozee_crm_db.* TO 'ozee_user'@'localhost';
FLUSH PRIVILEGES;
EOF

# Step 7: Run migrations
echo "Running Laravel migrations..."
$PHP_PATH artisan migrate --force
# Now that dependencies are installed and the tokens are present, create the storage link
echo "Creating Laravel storage symbolic link..."
sudo $PHP_PATH artisan storage:link


# Step 8: Disable default Bitnami virtual host
echo "Disabling default Bitnami virtual host..."
sudo mv /opt/bitnami/apache/conf/vhosts/00_status-vhost.conf /opt/bitnami/apache/conf/vhosts/00_status-vhost.conf.bak 2>/dev/null || true
sudo mv /opt/bitnami/apache/conf/vhosts/sample-vhost.conf /opt/bitnami/apache/conf/vhosts/sample-vhost.conf.bak 2>/dev/null || true

# Configure Apache for the app
echo "Configuring Apache..."
sudo bash -c "cat > /opt/bitnami/apache/conf/vhosts/$APP_NAME-vhost.conf" <<EOF
<VirtualHost *:80>
    ServerName $DOMAIN
    ServerAlias www.$DOMAIN
    DocumentRoot $APP_PATH/public
    <Directory "$APP_PATH/public">
        Options -Indexes +FollowSymLinks -MultiViews
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOF

sudo bash -c "cat > /opt/bitnami/apache/conf/vhosts/$APP_NAME-https-vhost.conf" <<EOF
<VirtualHost *:443>
    ServerName $DOMAIN
    ServerAlias www.$DOMAIN
    DocumentRoot $APP_PATH/public
    SSLEngine on
    SSLCertificateFile "/opt/bitnami/apache/conf/bitnami/certs/server.crt"
    SSLCertificateKeyFile "/opt/bitnami/apache/conf/bitnami/certs/server.key"
    <Directory "$APP_PATH/public">
        Options -Indexes +FollowSymLinks -MultiViews
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOF

# Include the virtual host configurations
echo "Include /opt/bitnami/apache/conf/vhosts/$APP_NAME-vhost.conf" | sudo tee -a /opt/bitnami/apache/conf/bitnami/bitnami.conf
echo "Include /opt/bitnami/apache/conf/vhosts/$APP_NAME-https-vhost.conf" | sudo tee -a /opt/bitnami/apache/conf/bitnami/bitnami.conf

# Step 9: Check DNS propagation
echo "Checking DNS propagation for $DOMAIN..."
if ! dig +short $DOMAIN | grep -q "$(curl -s http://169.254.169.254/latest/meta-data/public-ipv4)"; then
    echo "Warning: DNS for $DOMAIN may not have propagated. SSL setup may fail."
    echo "Please ensure the A record for $DOMAIN points to $(curl -s http://169.254.169.254/latest/meta-data/public-ipv4)."
    echo "You can retry SSL setup later with:"
    echo "sudo /opt/bitnami/ctlscript.sh stop"
    echo "sudo certbot certonly --standalone -d $DOMAIN --non-interactive --agree-tos --email \"$EMAIL\""
fi

# Step 10: Install and configure SSL with Certbot
echo "Installing Certbot and configuring SSL..."
# sudo apt-get install -y certbot # Certbot should already be installed
# sudo /opt/bitnami/ctlscript.sh stop # No need to stop services again

# --- COMMENTED OUT: CERTBOT RUN ---
# if [ -d "/etc/letsencrypt/live/$DOMAIN" ]; then
#     echo "A certificate for $DOMAIN already exists. Skipping Certbot run."
# else
#     if ! sudo certbot certonly --standalone -d $DOMAIN --non-interactive --agree-tos --email "$EMAIL"; then
#         echo "Certbot failed. Please check DNS propagation or run manually:"
#         echo "sudo /opt/bitnami/ctlscript.sh stop"
#         echo "sudo certbot certonly --standalone -d $DOMAIN --non-interactive --agree-tos --email \"$EMAIL\""
#         exit 1
#     fi
# fi
# --- END COMMENTED OUT ---

# Re-link the SSL certificates to point to the new ones created by Certbot
# The old ones were moved in the Certbot section which is now commented out.
# So let's make sure the links are created correctly now.
echo "Linking SSL certificates to the newly created ones..."
sudo mv /opt/bitnami/apache/conf/bitnami/certs/server.crt /opt/bitnami/apache/conf/bitnami/certs/server.crt.old 2>/dev/null || true
sudo mv /opt/bitnami/apache/conf/bitnami/certs/server.key /opt/bitnami/apache/conf/bitnami/certs/server.key.old 2>/dev/null || true
sudo ln -s /etc/letsencrypt/live/$DOMAIN/fullchain.pem /opt/bitnami/apache/conf/bitnami/certs/server.crt
sudo ln -s /etc/letsencrypt/live/$DOMAIN/privkey.pem /opt/bitnami/apache/conf/bitnami/certs/server.key

# Step 11: Configure HTTP to HTTPS redirection
echo "Configuring HTTP to HTTPS redirection..."
sudo sed -i '/<VirtualHost \*:80>/a \    RewriteEngine On\n    RewriteCond %{HTTPS} off\\n    RewriteRule ^ https://%{SERVER_NAME}%{REQUEST_URI} [R=301,L]' /opt/bitnami/apache/conf/vhosts/$APP_NAME-vhost.conf

# Step 12: Start Reverb
echo "Starting Laravel Reverb..."
$PHP_PATH artisan reverb:start &

# Step 13: Set up Laravel scheduler
echo "Setting up Laravel scheduler..."
(crontab -l 2>/dev/null; echo "* * * * * cd $APP_PATH && $PHP_PATH artisan schedule:run >> /dev/null 2>&1") | crontab -

# Step 14: Restart Apache
echo "Restarting Apache..."
sudo /opt/bitnami/ctlscript.sh restart

# Step 15: Verify Apache configuration
echo "Verifying Apache configuration..."
sudo /opt/bitnami/apache/bin/httpd -t
echo "Checking virtual host files..."
ls -l /opt/bitnami/apache/conf/vhosts/$APP_NAME*.conf
echo "Checking bitnami.conf inclusions..."
grep $APP_NAME /opt/bitnami/apache/conf/bitnami/bitnami.conf

echo "Deployment complete! Your Laravel/Vue app should be live at https://$DOMAIN"
echo "If you see the Bitnami page, check Apache logs: sudo tail -f /opt/bitnami/apache/logs/error_log"
echo "If SSL fails, verify DNS and retry the Certbot command provided above."
#!/bin/bash

# Exit on any error
set -e

# Variables
REPO_URL="https://github.com/zeemsabri/ozee-crm.git"
BRANCH="dev"
APP_NAME="ozee-crm"
APP_PATH="/opt/bitnami/projects/$APP_NAME"
DOMAIN="crm.ozeweeb.com.au"
LARAVEL_ENV="laravel_env"
PHP_PATH="/opt/bitnami/php/bin/php"
COMPOSER_PATH="/opt/bitnami/php/bin/composer"
USER=$(whoami)

# Step 1: Update system and install dependencies
echo "Updating system and installing dependencies..."
sudo apt-get update -y
sudo apt-get install -y git unzip curl lsb-release

# Check PHP version
echo "Checking PHP version..."
$PHP_PATH -v
# Install PHP extensions if not already present
echo "Installing PHP extensions..."
sudo apt-get install -y php-mysql php-xml php-mbstring php-bcmath php-zip

# Install Composer if not already installed
if [ ! -f "$COMPOSER_PATH" ]; then
    echo "Installing Composer..."
    curl -sS https://getcomposer.org/installer | sudo $PHP_PATH -- --install-dir=/opt/bitnami/php/bin --filename=composer
else
    echo "Composer already installed at $COMPOSER_PATH."
fi

# Step 2: Create project directory and clone or update repository
echo "Cloning or updating repository..."
sudo mkdir -p /opt/bitnami/projects
sudo chown $USER:$USER /opt/bitnami/projects
cd /opt/bitnami/projects
if [ -d "$APP_NAME" ]; then
    echo "Directory $APP_NAME exists. Pulling latest changes from $BRANCH branch..."
    cd $APP_NAME
    git checkout $BRANCH
    git pull origin $BRANCH
else
    git clone -b $BRANCH $REPO_URL $APP_NAME
    cd $APP_NAME
fi

# Step 3: Set up Laravel environment and sensitive files
echo "Setting up Laravel environment and sensitive files..."
# Check if .env file exists, if not, create it from the template
if [ ! -f ".env" ]; then
    cp $LARAVEL_ENV .env
    sudo chown $USER:$USER .env
    chmod 600 .env
fi

# Function to read a variable and write to .env if it's not already set with a value
read_if_missing() {
    local var_name=$1
    local prompt_text=$2
    # Check if the value is empty, or the line doesn't exist
    if ! grep -q "^$var_name=[^ ]" .env; then
        read -p "$prompt_text" var_value
        sed -i "s|^$var_name=.*|$var_name=\"$var_value\"|" .env
    else
        echo "$var_name is already set."
    fi
}

echo "Checking for sensitive keys in .env file. You will only be prompted for missing keys."
read_if_missing "GOOGLE_CLIENT_ID" "Enter GOOGLE_CLIENT_ID: "
read_if_missing "GOOGLE_CLIENT_SECRET" "Enter GOOGLE_CLIENT_SECRET: "
read_if_missing "EXCHANGE_RATES_API_KEY" "Enter EXCHANGE_RATES_API_KEY: "
read_if_missing "REVERB_APP_ID" "Enter REVERB_APP_ID: "
read_if_missing "REVERB_APP_KEY" "Enter REVERB_APP_KEY: "
read_if_missing "REVERB_APP_SECRET" "Enter REVERB_APP_SECRET: "

# Handle REVERB_HOST separately as it has a default value
if ! grep -q "^REVERB_HOST=[^ ]" .env; then
    read -p "Enter REVERB_HOST (press Enter to use $DOMAIN): " REVERB_HOST_NEW
    REVERB_HOST=${REVERB_HOST_NEW:-$DOMAIN}
    sed -i "s|^REVERB_HOST=.*|REVERB_HOST=\"$REVERB_HOST\"|" .env
else
    echo "REVERB_HOST is already set."
fi

# Update .env with database, app settings, and sensitive keys (non-user-input)
echo "Configuring default .env file settings..."
sed -i "s|APP_URL=.*|APP_URL=https://$DOMAIN|" .env
sed -i "s|DB_HOST=.*|DB_HOST=localhost|" .env
sed -i "s|DB_PORT=.*|DB_PORT=3306|" .env
sed -i "s|DB_DATABASE=.*|DB_DATABASE=ozee_crm_db|" .env
sed -i "s|DB_USERNAME=.*|DB_USERNAME=ozee_user|" .env
sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=secure_password_123|" .env
sed -i "s|^REVERB_PORT=.*|REVERB_PORT=443|" .env
sed -i "s|^REVERB_SCHEME=.*|REVERB_SCHEME=https|" .env

# Prompt for the email address for Certbot and Google tokens
echo ""
echo "An email address is required for SSL certificate registration and Google tokens."
read -p "Enter your email address: " EMAIL

# --- START OF GOOGLE TOKENS CREATION (BEFORE COMPOSER) ---
# Create the private storage directory if it's not present
sudo mkdir -p "$APP_PATH/storage/app/private"

# Check if the google_tokens.json file exists. Only prompt for tokens if it doesn't.
if [ ! -f "$APP_PATH/storage/app/private/google_tokens.json" ]; then
    echo ""
    echo "Google tokens not found. Please provide them to create the token file."
    read -p "Enter Google Access Token: " GOOGLE_ACCESS_TOKEN
    read -p "Enter Google Refresh Token: " GOOGLE_REFRESH_TOKEN

    # Create the JSON file with the provided tokens
    sudo bash -c "cat > $APP_PATH/storage/app/private/google_tokens.json" <<TOKEN_EOF
{
    "access_token": "$GOOGLE_ACCESS_TOKEN",
    "refresh_token": "$GOOGLE_REFRESH_TOKEN",
    "expires_in": 3599,
    "created_at": $(date +%s),
    "email": "$EMAIL"
}
TOKEN_EOF
    echo "Google token file created successfully."
else
    echo "Google token file already exists at $APP_PATH/storage/app/private/google_tokens.json. Skipping creation."
fi
# --- END OF GOOGLE TOKENS CREATION ---

# Step 4: Install Composer dependencies
echo "Installing Composer dependencies..."
$COMPOSER_PATH install --optimize-autoloader --no-dev


# Step 5: Set permissions
echo "Setting permissions..."
sudo chown -R daemon:daemon storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# --- START OF MARIADB RESTART AND WAIT LOOP ---
echo "Restarting MariaDB and waiting for it to be ready..."
sudo /opt/bitnami/ctlscript.sh restart mariadb

MYSQL_PASSWORD=$(cat /home/bitnami/bitnami_application_password)
MAX_RETRIES=10
RETRY_COUNT=0
until sudo /opt/bitnami/mariadb/bin/mariadb -u root -p"$MYSQL_PASSWORD" -e "SHOW DATABASES;" >/dev/null 2>&1 || [ $RETRY_COUNT -eq $MAX_RETRIES ]; do
    echo "Waiting for MariaDB to be ready... (Attempt $((RETRY_COUNT+1))/$MAX_RETRIES)"
    sleep 5
    RETRY_COUNT=$((RETRY_COUNT+1))
done

if [ $RETRY_COUNT -eq $MAX_RETRIES ]; then
    echo "Error: MariaDB did not become ready after multiple retries. Aborting."
    exit 1
fi
echo "MariaDB is ready."
# --- END OF MARIADB RESTART AND WAIT LOOP ---

# Step 6: Configure MySQL database
echo "Configuring MySQL database..."
# Create the database and user with the provided password
sudo /opt/bitnami/mariadb/bin/mariadb -u root -p"$MYSQL_PASSWORD" <<EOF
CREATE DATABASE IF NOT EXISTS ozee_crm_db;
CREATE USER IF NOT EXISTS 'ozee_user'@'localhost' IDENTIFIED BY 'secure_password_123';
GRANT ALL PRIVILEGES ON ozee_crm_db.* TO 'ozee_user'@'localhost';
FLUSH PRIVILEGES;
EOF

# Step 7: Run migrations
echo "Running Laravel migrations..."
$PHP_PATH artisan migrate --force
# Now that dependencies are installed and the tokens are present, create the storage link
echo "Creating Laravel storage symbolic link..."
sudo $PHP_PATH artisan storage:link


# Step 8: Disable default Bitnami virtual host
echo "Disabling default Bitnami virtual host..."
sudo mv /opt/bitnami/apache/conf/vhosts/00_status-vhost.conf /opt/bitnami/apache/conf/vhosts/00_status-vhost.conf.bak 2>/dev/null || true
sudo mv /opt/bitnami/apache/conf/vhosts/sample-vhost.conf /opt/bitnami/apache/conf/vhosts/sample-vhost.conf.bak 2>/dev/null || true

# Configure Apache for the app
echo "Configuring Apache..."
sudo bash -c "cat > /opt/bitnami/apache/conf/vhosts/$APP_NAME-vhost.conf" <<EOF
<VirtualHost *:80>
    ServerName $DOMAIN
    ServerAlias www.$DOMAIN
    DocumentRoot $APP_PATH/public
    <Directory "$APP_PATH/public">
        Options -Indexes +FollowSymLinks -MultiViews
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOF

sudo bash -c "cat > /opt/bitnami/apache/conf/vhosts/$APP_NAME-https-vhost.conf" <<EOF
<VirtualHost *:443>
    ServerName $DOMAIN
    ServerAlias www.$DOMAIN
    DocumentRoot $APP_PATH/public
    SSLEngine on
    SSLCertificateFile "/opt/bitnami/apache/conf/bitnami/certs/server.crt"
    SSLCertificateKeyFile "/opt/bitnami/apache/conf/bitnami/certs/server.key"
    <Directory "$APP_PATH/public">
        Options -Indexes +FollowSymLinks -MultiViews
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOF

# Include the virtual host configurations
echo "Include /opt/bitnami/apache/conf/vhosts/$APP_NAME-vhost.conf" | sudo tee -a /opt/bitnami/apache/conf/bitnami/bitnami.conf
echo "Include /opt/bitnami/apache/conf/vhosts/$APP_NAME-https-vhost.conf" | sudo tee -a /opt/bitnami/apache/conf/bitnami/bitnami.conf

# Step 9: Check DNS propagation
echo "Checking DNS propagation for $DOMAIN..."
if ! dig +short $DOMAIN | grep -q "$(curl -s http://169.254.169.254/latest/meta-data/public-ipv4)"; then
    echo "Warning: DNS for $DOMAIN may not have propagated. SSL setup may fail."
    echo "Please ensure the A record for $DOMAIN points to $(curl -s http://169.254.169.254/latest/meta-data/public-ipv4)."
    echo "You can retry SSL setup later with:"
    echo "sudo /opt/bitnami/ctlscript.sh stop"
    echo "sudo certbot certonly --standalone -d $DOMAIN --non-interactive --agree-tos --email \"$EMAIL\""
fi

# Step 10: Install and configure SSL with Certbot
echo "Installing Certbot and configuring SSL..."
# sudo apt-get install -y certbot # Certbot should already be installed
# sudo /opt/bitnami/ctlscript.sh stop # No need to stop services again

# --- COMMENTED OUT: CERTBOT RUN ---
# if [ -d "/etc/letsencrypt/live/$DOMAIN" ]; then
#     echo "A certificate for $DOMAIN already exists. Skipping Certbot run."
# else
#     if ! sudo certbot certonly --standalone -d $DOMAIN --non-interactive --agree-tos --email "$EMAIL"; then
#         echo "Certbot failed. Please check DNS propagation or run manually:"
#         echo "sudo /opt/bitnami/ctlscript.sh stop"
#         echo "sudo certbot certonly --standalone -d $DOMAIN --non-interactive --agree-tos --email \"$EMAIL\""
#         exit 1
#     fi
# fi
# --- END COMMENTED OUT ---

# Re-link the SSL certificates to point to the new ones created by Certbot
# The old ones were moved in the Certbot section which is now commented out.
# So let's make sure the links are created correctly now.
echo "Linking SSL certificates to the newly created ones..."
sudo mv /opt/bitnami/apache/conf/bitnami/certs/server.crt /opt/bitnami/apache/conf/bitnami/certs/server.crt.old 2>/dev/null || true
sudo mv /opt/bitnami/apache/conf/bitnami/certs/server.key /opt/bitnami/apache/conf/bitnami/certs/server.key.old 2>/dev/null || true
sudo ln -s /etc/letsencrypt/live/$DOMAIN/fullchain.pem /opt/bitnami/apache/conf/bitnami/certs/server.crt
sudo ln -s /etc/letsencrypt/live/$DOMAIN/privkey.pem /opt/bitnami/apache/conf/bitnami/certs/server.key

# Step 11: Configure HTTP to HTTPS redirection
echo "Configuring HTTP to HTTPS redirection..."
sudo sed -i '/<VirtualHost \*:80>/a \    RewriteEngine On\n    RewriteCond %{HTTPS} off\\n    RewriteRule ^ https://%{SERVER_NAME}%{REQUEST_URI} [R=301,L]' /opt/bitnami/apache/conf/vhosts/$APP_NAME-vhost.conf

# Step 12: Start Reverb
echo "Starting Laravel Reverb..."
$PHP_PATH artisan reverb:start &

# Step 13: Set up Laravel scheduler
echo "Setting up Laravel scheduler..."
(crontab -l 2>/dev/null; echo "* * * * * cd $APP_PATH && $PHP_PATH artisan schedule:run >> /dev/null 2>&1") | crontab -

# Step 14: Restart Apache
echo "Restarting Apache..."
sudo /opt/bitnami/ctlscript.sh restart

# Step 15: Verify Apache configuration
echo "Verifying Apache configuration..."
sudo /opt/bitnami/apache/bin/httpd -t
echo "Checking virtual host files..."
ls -l /opt/bitnami/apache/conf/vhosts/$APP_NAME*.conf
echo "Checking bitnami.conf inclusions..."
grep $APP_NAME /opt/bitnami/apache/conf/bitnami/bitnami.conf

echo "Deployment complete! Your Laravel/Vue app should be live at https://$DOMAIN"
echo "If you see the Bitnami page, check Apache logs: sudo tail -f /opt/bitnami/apache/logs/error_log"
echo "If SSL fails, verify DNS and retry the Certbot command provided above."
