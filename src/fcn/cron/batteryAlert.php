<?php
/**
 * WeatherParse - AcuRite Access/smartHUB and IP Camera Data Processing, Display, and Upload.
 * @copyright Copyright (C) 2026 WeatherParse contributors
 * @link https://github.com/mobilevoid/weatherparse
 * @license AGPL-3.0+
 *
 * Based on Acuparse, Copyright (C) 2015-2023 Maxwell Power. Modified for WeatherParse in 2026.
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this code. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * File: src/fcn/cron/batteryAlert.php
 * Check device batteries and email when one goes low, at the reminder interval while it stays low, and once it is OK
 */

/**
 * @var mysqli $conn Global MYSQL Connection
 * @var object $config Global Config
 */

require_once(APP_BASE_PATH . '/fcn/batteryAlert.php');

$batterySettings = batteryAlertSettings($config);

if (!batteryAlertEnsureTable($conn)) {
    syslog(LOG_WARNING, "(SYSTEM){BATTERY}[WARNING]: Skipping battery check (no battery_alert table)");
} else {
    $batteryNow = date("Y-m-d H:i:s");

    // Saved low states
    $batteryState = array();
    $result = mysqli_query($conn, "SELECT `device`, `name`, `low_since`, `last_seen`, `last_sent` FROM `battery_alert`");
    if ($result === false) {
        syslog(LOG_ERR, "(SYSTEM){BATTERY}[ERROR]: Reading battery_alert failed (" . mysqli_error($conn) . ")");
    } else {
        while ($row = mysqli_fetch_assoc($result)) {
            $batteryState[$row['device']] = $row;
        }

        $batteryLow = array();
        $batteryRecovered = array();
        $batteryNotify = false;

        foreach (batteryAlertDevices($conn, $config) as $device) {
            // Not reporting: an outage, not a battery problem. Keep any saved state as it is.
            if ($device->fresh === false) {
                if ($config->debug->logging === true) {
                    syslog(LOG_DEBUG, "(SYSTEM){BATTERY}: $device->name has not reported since $device->reported, skipped");
                }
                continue;
            }

            $deviceKey = mysqli_real_escape_string($conn, $device->key);
            $deviceName = mysqli_real_escape_string($conn, $device->name);

            if ($device->battery === 'low') {
                if (!isset($batteryState[$device->key])) {
                    // Newly low
                    $saved = mysqli_query($conn,
                        "INSERT INTO `battery_alert` (`device`, `name`, `low_since`, `last_seen`, `last_sent`) VALUES ('$deviceKey', '$deviceName', '$batteryNow', '$batteryNow', NULL)");
                    if ($saved === false) {
                        syslog(LOG_ERR, "(SYSTEM){BATTERY}[ERROR]: Saving low state of $device->name failed (" . mysqli_error($conn) . ")");
                        continue;
                    }
                    syslog(LOG_WARNING, "(SYSTEM){BATTERY}: *LOW* $device->name battery is low");
                    $batteryState[$device->key] = array('device' => $device->key, 'name' => $device->name,
                        'low_since' => $batteryNow, 'last_seen' => $batteryNow, 'last_sent' => null);
                    $batteryNotify = true;
                } else {
                    // Still low
                    mysqli_query($conn,
                        "UPDATE `battery_alert` SET `last_seen` = '$batteryNow', `name` = '$deviceName' WHERE `device` = '$deviceKey'")
                    or syslog(LOG_ERR, "(SYSTEM){BATTERY}[ERROR]: Updating low state of $device->name failed (" . mysqli_error($conn) . ")");
                    $lastSent = $batteryState[$device->key]['last_sent'];
                    if (empty($lastSent)) {
                        // Never emailed (notifications were off, mail was not set up, or sending failed)
                        $batteryNotify = true;
                    } elseif ($batterySettings->reminder_hours > 0 && strtotime($lastSent) <= strtotime('-' . $batterySettings->reminder_hours . ' hours')) {
                        $batteryNotify = true;
                    }
                }
                $device->low_since = $batteryState[$device->key]['low_since'];
                $batteryLow[$device->key] = $device;
            } elseif ($device->battery === 'normal') {
                if (isset($batteryState[$device->key])) {
                    // Recovered
                    $cleared = mysqli_query($conn, "DELETE FROM `battery_alert` WHERE `device` = '$deviceKey'");
                    if ($cleared === false) {
                        syslog(LOG_ERR, "(SYSTEM){BATTERY}[ERROR]: Clearing low state of $device->name failed (" . mysqli_error($conn) . ")");
                        continue;
                    }
                    syslog(LOG_NOTICE, "(SYSTEM){BATTERY}: *OK* $device->name battery is normal again");
                    $device->low_since = $batteryState[$device->key]['low_since'];
                    $device->was_sent = !empty($batteryState[$device->key]['last_sent']);
                    $batteryRecovered[$device->key] = $device;
                    unset($batteryState[$device->key]);
                }
            } else {
                syslog(LOG_WARNING, "(SYSTEM){BATTERY}[WARNING]: $device->name reports an unknown battery state '$device->battery'");
            }
        }

        // Forget devices that have not reported low for a long time (removed or replaced)
        foreach ($batteryState as $key => $row) {
            if (!isset($batteryLow[$key]) && strtotime($row['last_seen']) < strtotime('-' . BATTERY_ALERT_FORGET_AFTER)) {
                $forgetKey = mysqli_real_escape_string($conn, $key);
                if (mysqli_query($conn, "DELETE FROM `battery_alert` WHERE `device` = '$forgetKey'") === true) {
                    syslog(LOG_NOTICE, "(SYSTEM){BATTERY}: Forgot the low state of " . $row['name'] . " (no report since " . $row['last_seen'] . ")");
                } else {
                    syslog(LOG_ERR, "(SYSTEM){BATTERY}[ERROR]: Forgetting the low state of " . $row['name'] . " failed (" . mysqli_error($conn) . ")");
                }
            }
        }

        $batteryMailReady = batteryAlertMailConfigured($config);

        // Low battery email: on a new low device or when a reminder is due. Lists every low device.
        if (!empty($batteryLow)) {
            $lowNames = implode(', ', array_map(fn($d) => $d->name, $batteryLow));
            if ($batteryNotify === false) {
                syslog(LOG_INFO, "(SYSTEM){BATTERY}: Still low: $lowNames (Notification Skipped)");
            } elseif ($batterySettings->enabled !== true) {
                syslog(LOG_NOTICE, "(SYSTEM){BATTERY}: *LOW* $lowNames (Notifications Disabled)");
            } elseif ($batteryMailReady !== true) {
                syslog(LOG_WARNING, "(SYSTEM){BATTERY}[WARNING]: *LOW* $lowNames (Notification Not Sent: email is not set up)");
            } else {
                list($subject, $message) = batteryAlertLowMessage($config, $batterySettings, $batteryLow);
                if (batteryAlertSend($conn, $batterySettings, $subject, $message)) {
                    $sentKeys = implode("', '", array_map(fn($k) => mysqli_real_escape_string($conn, $k), array_keys($batteryLow)));
                    mysqli_query($conn, "UPDATE `battery_alert` SET `last_sent` = '$batteryNow' WHERE `device` IN ('$sentKeys')")
                    or syslog(LOG_ERR, "(SYSTEM){BATTERY}[ERROR]: Saving the send time failed (" . mysqli_error($conn) . ")");
                    syslog(LOG_ALERT, "(SYSTEM){BATTERY}: *LOW* $lowNames (Notification Sent)");
                } else {
                    syslog(LOG_ERR, "(SYSTEM){BATTERY}[ERROR]: *LOW* $lowNames (Notification Failed, retrying next run)");
                }
            }
        }

        // Battery OK email, only for devices the recipients were told about
        if (!empty($batteryRecovered)) {
            $recoveredNames = implode(', ', array_map(fn($d) => $d->name, $batteryRecovered));
            $batteryRecoveredSent = array_filter($batteryRecovered, fn($d) => $d->was_sent === true);
            if ($batterySettings->enabled !== true) {
                syslog(LOG_NOTICE, "(SYSTEM){BATTERY}: *OK* $recoveredNames (Notifications Disabled)");
            } elseif ($batterySettings->send_recovery !== true) {
                syslog(LOG_NOTICE, "(SYSTEM){BATTERY}: *OK* $recoveredNames (Recovery Notifications Disabled)");
            } elseif (empty($batteryRecoveredSent)) {
                syslog(LOG_NOTICE, "(SYSTEM){BATTERY}: *OK* $recoveredNames (No low alert was sent, Notification Skipped)");
            } elseif ($batteryMailReady !== true) {
                syslog(LOG_WARNING, "(SYSTEM){BATTERY}[WARNING]: *OK* $recoveredNames (Notification Not Sent: email is not set up)");
            } else {
                list($subject, $message) = batteryAlertRecoveryMessage($config, $batteryRecoveredSent);
                if (batteryAlertSend($conn, $batterySettings, $subject, $message)) {
                    syslog(LOG_ALERT, "(SYSTEM){BATTERY}: *OK* $recoveredNames (Notification Sent)");
                } else {
                    syslog(LOG_ERR, "(SYSTEM){BATTERY}[ERROR]: *OK* $recoveredNames (Notification Failed)");
                }
            }
        }

        if (empty($batteryLow) && empty($batteryRecovered) && $config->debug->logging === true) {
            syslog(LOG_DEBUG, "(SYSTEM){BATTERY}: All reporting batteries are normal");
        }
    }
}
