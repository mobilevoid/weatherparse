#!/bin/bash -e
# Copy the WeatherParse files that live outside the chroot into the image.
F="$(dirname "$0")/files"

install -m 644 "${F}/dnsmasq.conf"                   "${ROOTFS_DIR}/etc/dnsmasq.conf"
install -m 600 "${F}/hub-link.nmconnection"          "${ROOTFS_DIR}/etc/NetworkManager/system-connections/hub-link.nmconnection"
install -m 644 "${F}/wifi-powersave-off.conf"        "${ROOTFS_DIR}/etc/NetworkManager/conf.d/wifi-powersave-off.conf"
install -m 644 "${F}/rules.v4"                       "${ROOTFS_DIR}/etc/iptables/rules.v4"
install -m 644 "${F}/90-weatherparse.conf"           "${ROOTFS_DIR}/etc/sysctl.d/90-weatherparse.conf"
install -m 755 "${F}/wifi-watchdog"                  "${ROOTFS_DIR}/usr/local/sbin/wifi-watchdog"
install -m 644 "${F}/wifi-watchdog.service"          "${ROOTFS_DIR}/etc/systemd/system/wifi-watchdog.service"
install -m 644 "${F}/wifi-watchdog.timer"            "${ROOTFS_DIR}/etc/systemd/system/wifi-watchdog.timer"
install -m 755 "${F}/weatherparse-firstboot"         "${ROOTFS_DIR}/usr/local/sbin/weatherparse-firstboot"
install -m 644 "${F}/weatherparse-firstboot.service" "${ROOTFS_DIR}/etc/systemd/system/weatherparse-firstboot.service"
install -m 644 "${F}/weatherparse.cron"              "${ROOTFS_DIR}/etc/cron.d/weatherparse"
install -m 644 "${F}/60-weatherparse.cnf"            "${ROOTFS_DIR}/etc/mysql/mariadb.conf.d/60-weatherparse.cnf"
mkdir -p "${ROOTFS_DIR}/etc/systemd/journald.conf.d"
install -m 644 "${F}/journald-size.conf"             "${ROOTFS_DIR}/etc/systemd/journald.conf.d/size.conf"

# The WeatherParse code itself: the tree pi-gen was started from (copied in by the workflow)
mkdir -p "${ROOTFS_DIR}/opt"
cp -a "${F}/weatherparse" "${ROOTFS_DIR}/opt/weatherparse"
