#!/bin/bash
apt-get update
apt-get install -y apache2 php libapache2-mod-php php-mysql mysql-client git

  # AWS CLI v2 from AWS's installer, used by www/lambda.php to call Lambda.
  # (The snap version refuses to run as www-data, whose home is /var/www.)
  curl -s "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o /tmp/awscliv2.zip
  unzip -q -o /tmp/awscliv2.zip -d /tmp
  /tmp/aws/install

# Fetch the application code.
git clone ${repo_url} /opt/recipe-finder

# Database settings for www/db.php, readable by Apache but not by other users.
mkdir -p /etc/recipe-finder
cat > /etc/recipe-finder/config.ini <<'INI'
[database]
host = "${db_host}"
name = "recipefinder"
user = "admin"
password = "${db_password}"

[lambda]
function = "recipe-shopping-list"
region = "us-east-1"
INI
chown root:www-data /etc/recipe-finder/config.ini
chmod 640 /etc/recipe-finder/config.ini

# Create the tables and seed recipes in RDS.
MYSQL_PWD='${db_password}' mysql -h ${db_host} -u admin recipefinder < /opt/recipe-finder/setup-database.sql

# Serve www/ like Assignment 1 served /vagrant/www.
cat > /etc/apache2/sites-available/recipe-app.conf <<'CONF'
<VirtualHost *:80>
    DocumentRoot /opt/recipe-finder/www
    DirectoryIndex index.php

    <Directory /opt/recipe-finder/www>
        Require all granted
    </Directory>

    ErrorLog $${APACHE_LOG_DIR}/recipe-error.log
    CustomLog $${APACHE_LOG_DIR}/recipe-access.log combined
</VirtualHost>
CONF
a2ensite recipe-app
a2dissite 000-default
systemctl enable apache2
systemctl restart apache2
