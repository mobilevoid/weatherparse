#!/bin/bash -e
# Runs inside the image. Everything here is the same for every Pi; per-device secrets are made on first boot.
W=/opt/weatherparse

# Permissions the web server needs
mkdir -p "$W/logs" "$W/cam/tmp" "$W/cam/archive" "$W/src/pub/img/cam"
chown -R www-data:www-data "$W/src/usr" "$W/src/pub/img"

# Apache: WeatherParse sites only
cp "$W/config/weatherparse.conf" "$W/config/weatherparse-ssl.conf" /etc/apache2/sites-available/
a2dissite -q 000-default || true
a2enmod -q rewrite ssl
a2ensite -q weatherparse weatherparse-ssl

# The AcuRite Access uses old TLS: allow TLS 1.1 (Debian 13's openssl.cnf has no TLS section, so add one)
CNF=/etc/ssl/openssl.cnf
if grep -q '^\[system_default_sect\]' "$CNF"; then
	sed -i 's/^MinProtocol = .*/MinProtocol = TLSv1.1/; s/^CipherString = .*/CipherString = DEFAULT@SECLEVEL=1/' "$CNF"
else
	sed -i '/^\[openssl_init\]/a ssl_conf = ssl_sect' "$CNF"
	printf '\n[ssl_sect]\nsystem_default = system_default_sect\n\n[system_default_sect]\nMinProtocol = TLSv1.1\nCipherString = DEFAULT@SECLEVEL=1\n' >>"$CNF"
fi

# dnsmasq serves only the Access port; it must not become the Pi's own resolver
grep -q '^DNSMASQ_EXCEPT' /etc/default/dnsmasq 2>/dev/null || echo 'DNSMASQ_EXCEPT="lo"' >>/etc/default/dnsmasq

# Services
systemctl enable apache2 mariadb dnsmasq netfilter-persistent cron wifi-watchdog.timer weatherparse-firstboot.service

# Keep the image small
apt-get clean
rm -rf /var/lib/apt/lists/*
