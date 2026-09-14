#!/bin/bash
set -e

apt-get update
apt-get install -y apache2

a2enmod proxy proxy_http

cp /vagrant/recipe-proxy.conf /etc/apache2/sites-available/
a2ensite recipe-proxy
a2dissite 000-default

systemctl enable apache2
service apache2 restart
