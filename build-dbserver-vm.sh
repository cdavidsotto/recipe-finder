#!/bin/bash
set -e

apt-get update
apt-get install -y mysql-server
systemctl enable --now mysql

mysql <<'SQL'
CREATE DATABASE IF NOT EXISTS recipefinder;

CREATE USER IF NOT EXISTS 'recipeapp'@'192.168.56.12'
  IDENTIFIED BY 'recipe_demo_pw';

GRANT SELECT ON recipefinder.*
  TO 'recipeapp'@'192.168.56.12';
SQL

mysql recipefinder < /vagrant/setup-database.sql

sed -i 's/^[[:space:]]*bind-address[[:space:]]*=.*/bind-address = 192.168.56.13/' \
  /etc/mysql/mysql.conf.d/mysqld.cnf

systemctl restart mysql
