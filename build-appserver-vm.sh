#!/bin/bash
set -e

apt-get update
apt-get install -y apache2 php libapache2-mod-php php-mysql

cp /vagrant/recipe-app.conf /etc/apache2/sites-available/
a2ensite recipe-app
a2dissite 000-default

systemctl enable apache2
systemctl restart apache2
