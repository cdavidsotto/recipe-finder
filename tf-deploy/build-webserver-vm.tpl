#!/bin/bash
apt-get update
apt-get install -y apache2
a2enmod proxy proxy_http

# Forward every request to the app server's private address.
cat > /etc/apache2/sites-available/recipe-proxy.conf <<'CONF'
<VirtualHost *:80>
    ProxyRequests Off
    ProxyPass "/" "http://${app_private_ip}/"
    ProxyPassReverse "/" "http://${app_private_ip}/"
</VirtualHost>
CONF
a2ensite recipe-proxy
a2dissite 000-default
systemctl enable apache2
systemctl restart apache2
