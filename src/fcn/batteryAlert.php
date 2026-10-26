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
 * File: src/fcn/batteryAlert.php
 * Low battery email alerts: settings, state table, device detection and mail helpers.
 * Used by the cron task (fcn/cron/batteryAlert.php), the settings page and the updater.
 */

// A device that has not reported for this long is an outage, not a battery problem
if (!defined('BATTERY_ALERT_STALE_AFTER')) {
    define('BATTERY_ALERT_STALE_AFTER', '1 hour');
}

// Forget the low state of a device that has not reported low for this long (removed tower, replaced sensor)
if (!defined('BATTERY_ALERT_FORGET_AFTER')) {
    define('BATTERY_ALERT_FORGET_AFTER', '30 days');
}

// After a failed send, wait this long before trying again (keeps a broken mail setup from retrying every minute)
if (!defined('BATTERY_ALERT_RETRY_AFTER')) {
    define('BATTERY_ALERT_RETRY_AFTER', '1 hour');
}

// Longest reminder interval the settings page accepts (hours, 30 days). 0 means no reminders.
if (!defined('BATTERY_ALERT_MAX_REMINDER_HOURS')) {
    define('BATTERY_ALERT_MAX_REMINDER_HOURS', 720);
}

/**
 * Is outgoing mail set up? Mailgun with a domain and secret, or a local sendmail program for PHP mail().
 * @param object $config Global Config
 * @return bool
 */
function batteryAlertMailConfigured(object $config): bool
{
    if (($config->mailgun->enabled ?? false) === true) {
        return !empty($config->mailgun->domain) && !empty($config->mailgun->secret);
    }

    // PHP mail() hands the message to the program in sendmail_path (sendmail, postfix, msmtp, ...)
    $sendmailPath = trim((string)ini_get('sendmail_path'));
    if ($sendmailPath === '') {
        return false;
    }
    $program = strtok($sendmailPath, ' ');
    return $program !== false && is_executable($program);
}

/**
 * The battery alert settings with defaults filled in, so installs that have not run the updater yet still work.
 * @param object $config Global Config
 * @return object enabled, recipients, reminder_hours, send_recovery
 */
function batteryAlertSettings(object $config): object
{
    $saved = $config->battery_alert ?? null;
    return (object)array(
        'enabled' => isset($saved->enabled) ? (bool)$saved->enabled : batteryAlertMailConfigured($config),
        'recipients' => isset($saved->recipients) ? (string)$saved->recipients : '',
        'reminder_hours' => isset($saved->reminder_hours) ? max(0, min(BATTERY_ALERT_MAX_REMINDER_HOURS,
            (int)$saved->reminder_hours)) : 24,
        'send_recovery' => isset($saved->send_recovery) ? (bool)$saved->send_recovery : true,
    );
}

/**
 * Split a recipient list (commas, semicolons or spaces) into unique valid addresses.
 * @param string $list Recipient list as typed in the settings
 * @param array $invalid Receives the entries that are not valid email addresses
 * @return array
 */
function batteryAlertParseRecipients(string $list, array &$invalid = array()): array
{
    $valid = array();
    $invalid = array();
    foreach (preg_split('/[\s,;]+/', html_entity_decode($list, ENT_QUOTES), -1, PREG_SPLIT_NO_EMPTY) as $address) {
        if (filter_var($address, FILTER_VALIDATE_EMAIL) !== false) {
            $valid[strtolower($address)] = $address;
        } else {
            $invalid[] = $address;
        }
    }
    return array_values($valid);
}

/**
 * Clean the recipient list from the settings form. Invalid entries are dropped and logged.
 * @param string $list Recipient list as submitted
 * @return string Comma separated valid addresses, empty for "all admins"
 */
function batteryAlertCleanRecipients(string $list): string
{
    $invalid = array();
    $valid = batteryAlertParseRecipients($list, $invalid);
    if (!empty($invalid)) {
        syslog(LOG_WARNING, "(SYSTEM){CONFIG}[WARNING]: Battery alert recipients dropped (not valid email addresses): " . implode(', ', $invalid));
    }
    return implode(', ', $valid);
}

/**
 * Who gets the battery emails: the configured list, otherwise every admin (as the outage alert does).
 * @param mysqli $conn Global MYSQL Connection
 * @param object $settings From batteryAlertSettings()
 * @return array
 */
function batteryAlertRecipients(mysqli $conn, object $settings): array
{
    $recipients = batteryAlertParseRecipients($settings->recipients);
    if (!empty($recipients)) {
        return $recipients;
    }
    $result = mysqli_query($conn, "SELECT `email` FROM `users` WHERE `admin` = '1'");
    if ($result === false) {
        syslog(LOG_ERR, "(SYSTEM){BATTERY}[ERROR]: Reading admin emails failed (" . mysqli_error($conn) . ")");
        return array();
    }
    while ($row = mysqli_fetch_assoc($result)) {
        if (!empty($row['email'])) {
            $recipients[] = $row['email'];
        }
    }
    return array_values(array_unique($recipients));
}

/**
 * The state table. Keep in sync with sql/master.sql.
 * @return string
 */
function batteryAlertTableSQL(): string
{
    return "CREATE TABLE IF NOT EXISTS `battery_alert`
(
    `device`    varchar(32) COLLATE utf8_bin  NOT NULL,
    `name`      varchar(255) COLLATE utf8_bin NOT NULL,
    `low_since` datetime                      NOT NULL,
    `last_seen` datetime                      NOT NULL,
    `last_sent` datetime                               DEFAULT NULL,
    `last_attempt` datetime                            DEFAULT NULL,
    PRIMARY KEY (`device`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  COLLATE = utf8_bin";
}

/**
 * Make sure the state table exists, creating it when missing.
 * @param mysqli $conn Global MYSQL Connection
 * @return bool True when the table is there
 */
function batteryAlertEnsureTable(mysqli $conn): bool
{
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'battery_alert'");
    if ($result !== false && mysqli_num_rows($result) === 1) {
        // Tables made by the first 4.1.0 build lack last_attempt
        $column = mysqli_query($conn, "SHOW COLUMNS FROM `battery_alert` LIKE 'last_attempt'");
        if ($column !== false && mysqli_num_rows($column) === 0) {
            if (mysqli_query($conn, "ALTER TABLE `battery_alert` ADD `last_attempt` datetime DEFAULT NULL AFTER `last_sent`") === true) {
                syslog(LOG_NOTICE, "(SYSTEM){BATTERY}: Added last_attempt to the battery_alert table");
            } else {
                syslog(LOG_ERR, "(SYSTEM){BATTERY}[ERROR]: Adding last_attempt to battery_alert failed (" . mysqli_error($conn) . ")");
                return false;
            }
        }
        return true;
    }
    if (mysqli_query($conn, batteryAlertTableSQL()) === true) {
        syslog(LOG_NOTICE, "(SYSTEM){BATTERY}: Created the battery_alert table");
        return true;
    }
    syslog(LOG_ERR, "(SYSTEM){BATTERY}[ERROR]: battery_alert table is missing and could not be created (" . mysqli_error($conn) . ")");
    return false;
}

/**
 * Is a report time recent enough to trust its battery state?
 * @param string|null $reported Timestamp from the status table
 * @return bool
 */
function batteryAlertIsFresh(?string $reported): bool
{
    $time = empty($reported) ? false : strtotime($reported);
    return $time !== false && $time >= strtotime('-' . BATTERY_ALERT_STALE_AFTER);
}

/**
 * Battery state of every configured device: the Access, the Atlas, the Iris (per receiving device) and each tower.
 * Devices that have not reported recently are included with fresh = false, so their saved state is left alone.
 * @param mysqli $conn Global MYSQL Connection
 * @param object $config Global Config
 * @return array Keyed by device key; each entry has key, name, battery (lower case), reported, fresh, replace
 */
function batteryAlertDevices(mysqli $conn, object $config): array
{
    $devices = array();
    $add = function (string $key, string $name, ?string $battery, ?string $reported, string $replace) use (&$devices) {
        $devices[$key] = (object)array(
            'key' => $key,
            'name' => $name,
            'battery' => strtolower(trim((string)$battery)),
            'reported' => (string)$reported,
            'fresh' => batteryAlertIsFresh($reported),
            'replace' => $replace,
        );
    };

    // Access hub (hubbattery = its backup batteries)
    if (!empty($config->station->access_mac)) {
        $result = mysqli_query($conn,
            "SELECT `battery`, `last_update` FROM `access_status` ORDER BY `last_update` DESC LIMIT 1");
        if ($result === false) {
            syslog(LOG_ERR, "(SYSTEM){BATTERY}[ERROR]: Reading access_status failed (" . mysqli_error($conn) . ")");
        } elseif ($row = mysqli_fetch_assoc($result)) {
            $add('access', 'AcuRite Access hub', $row['battery'], $row['last_update'],
                'the backup batteries in the Access hub (they keep it running during a power cut)');
        }
    }

    // Atlas
    if (!empty($config->station->sensor_atlas) && $config->station->sensor_atlas != 0) {
        $result = mysqli_query($conn,
            "SELECT `battery`, `last_update` FROM `atlas_status` ORDER BY `last_update` DESC LIMIT 1");
        if ($result === false) {
            syslog(LOG_ERR, "(SYSTEM){BATTERY}[ERROR]: Reading atlas_status failed (" . mysqli_error($conn) . ")");
        } elseif ($row = mysqli_fetch_assoc($result)) {
            $add('atlas', 'Atlas sensor ' . ltrim($config->station->sensor_atlas, '0'), $row['battery'],
                $row['last_update'], 'the batteries in the Atlas sensor');
        }
    }

    // Iris, one row per receiving device (access/hub)
    if (!empty($config->station->sensor_iris) && $config->station->sensor_iris != 0) {
        $result = mysqli_query($conn, "SELECT `device`, `battery`, `last_update` FROM `iris_status`");
        if ($result === false) {
            syslog(LOG_ERR, "(SYSTEM){BATTERY}[ERROR]: Reading iris_status failed (" . mysqli_error($conn) . ")");
        } else {
            while ($row = mysqli_fetch_assoc($result)) {
                $via = ($row['device'] === 'hub') ? 'smartHUB' : 'Access';
                $add('iris-' . $row['device'], 'Iris (5-in-1) sensor ' . ltrim($config->station->sensor_iris, '0') . ' (via ' . $via . ')',
                    $row['battery'], $row['last_update'], 'the batteries in the Iris (5-in-1) sensor');
            }
        }
    }

    // Towers: the latest reading of each sensor, named from the towers table when it is listed there
    $result = mysqli_query($conn,
        "SELECT `d`.`sensor`, `d`.`battery`, `d`.`timestamp`, `t`.`name` FROM `tower_data` `d` INNER JOIN (SELECT `sensor`, MAX(`timestamp`) AS `latest` FROM `tower_data` GROUP BY `sensor`) `l` ON `l`.`sensor` = `d`.`sensor` AND `l`.`latest` = `d`.`timestamp` LEFT JOIN `towers` `t` ON `t`.`sensor` = `d`.`sensor`");
    if ($result === false) {
        syslog(LOG_ERR, "(SYSTEM){BATTERY}[ERROR]: Reading tower_data failed (" . mysqli_error($conn) . ")");
    } else {
        while ($row = mysqli_fetch_assoc($result)) {
            $key = 'tower-' . $row['sensor'];
            // Two receivers can store the same reading time; a low report wins
            if (isset($devices[$key]) && $devices[$key]->battery === 'low') {
                continue;
            }
            $name = !empty($row['name']) ? html_entity_decode($row['name'], ENT_QUOTES) : 'Tower';
            $add($key, $name . ' (tower ' . ltrim($row['sensor'], '0') . ')', $row['battery'], $row['timestamp'],
                'the batteries in this tower sensor');
        }
    }

    return $devices;
}

/**
 * Name used in subjects: the hostname, or WeatherParse when it is still localhost (as the outage alert does).
 * @param object $config Global Config
 * @return string
 */
function batteryAlertSiteName(object $config): string
{
    $hostname = (string)($config->site->hostname ?? '');
    return ($hostname === '' || $hostname === 'localhost') ? 'WeatherParse' : $hostname;
}

/**
 * Dashboard address, when the config has a real hostname.
 * @param object $config Global Config
 * @return string|null
 */
function batteryAlertSiteURL(object $config): ?string
{
    $hostname = (string)($config->site->hostname ?? '');
    return ($hostname === '' || $hostname === 'localhost') ? null : 'https://' . $hostname;
}

/**
 * Format a stored timestamp for the email.
 * @param object $config Global Config
 * @param string|null $timestamp
 * @return string
 */
function batteryAlertDate(object $config, ?string $timestamp): string
{
    $time = empty($timestamp) ? false : strtotime($timestamp);
    if ($time === false) {
        return 'unknown';
    }
    return date($config->site->dashboard_display_date_full ?? 'j M Y @ H:i', $time);
}

/**
 * Links to the dashboard and the sensor status page, or nothing when there is no site address.
 * @param object $config Global Config
 * @return string HTML
 */
function batteryAlertLinks(object $config): string
{
    $url = batteryAlertSiteURL($config);
    if ($url === null) {
        return '';
    }
    $url = htmlspecialchars($url);
    return '<p>Station dashboard: <a href="' . $url . '/">' . $url . '/</a><br>Sensor status (admin): <a href="' . $url . '/admin/status">' . $url . '/admin/status</a></p>';
}

/**
 * The low battery email.
 * @param object $config Global Config
 * @param object $settings From batteryAlertSettings()
 * @param array $low Low devices: objects with name, replace, reported, low_since
 * @return array subject, message
 */
function batteryAlertLowMessage(object $config, object $settings, array $low): array
{
    $siteName = batteryAlertSiteName($config);
    $count = count($low);
    $subject = $siteName . ' LOW BATTERY' . (($count === 1) ? ': ' . reset($low)->name : ' (' . $count . ' devices)');

    $message = '<p><strong>' . htmlspecialchars($siteName) . ': ' . (($count === 1) ? 'a device reports' : $count . ' devices report') . ' a low battery.</strong></p><ul>';
    foreach ($low as $device) {
        $message .= '<li><strong>' . htmlspecialchars($device->name) . '</strong><br>'
            . 'Low since: ' . htmlspecialchars(batteryAlertDate($config, $device->low_since)) . ' (first seen low by WeatherParse)<br>'
            . 'Last report: ' . htmlspecialchars(batteryAlertDate($config, $device->reported)) . '<br>'
            . 'Replace: ' . htmlspecialchars($device->replace) . '</li>';
    }
    $message .= '</ul>';
    $message .= ($settings->reminder_hours > 0) ?
        '<p>A reminder is sent every ' . $settings->reminder_hours . ' hours while a battery stays low.</p>'
        :
        '<p>Reminders are off; this is the only email until the battery is replaced.</p>';
    $message .= batteryAlertLinks($config);

    return array($subject, $message);
}

/**
 * The "battery OK again" email.
 * @param object $config Global Config
 * @param array $recovered Recovered devices: objects with name, low_since, reported
 * @return array subject, message
 */
function batteryAlertRecoveryMessage(object $config, array $recovered): array
{
    $siteName = batteryAlertSiteName($config);
    $count = count($recovered);
    $subject = $siteName . ' BATTERY OK' . (($count === 1) ? ': ' . reset($recovered)->name : ' (' . $count . ' devices)');

    $message = '<p><strong>' . htmlspecialchars($siteName) . ': ' . (($count === 1) ? 'a battery is' : $count . ' batteries are') . ' OK again.</strong></p><ul>';
    foreach ($recovered as $device) {
        $message .= '<li><strong>' . htmlspecialchars($device->name) . '</strong><br>'
            . 'Was low since: ' . htmlspecialchars(batteryAlertDate($config, $device->low_since)) . '<br>'
            . 'Normal at: ' . htmlspecialchars(batteryAlertDate($config, $device->reported)) . '</li>';
    }
    $message .= '</ul>' . batteryAlertLinks($config);

    return array($subject, $message);
}

/**
 * Send one email to every recipient.
 * @param mysqli $conn Global MYSQL Connection
 * @param object $settings From batteryAlertSettings()
 * @param string $subject
 * @param string $message HTML body
 * @return bool True when at least one recipient was sent the email
 */
function batteryAlertSend(mysqli $conn, object $settings, string $subject, string $message): bool
{
    require_once(APP_BASE_PATH . '/fcn/mailer.php');

    $recipients = batteryAlertRecipients($conn, $settings);
    if (empty($recipients)) {
        syslog(LOG_ERR, "(SYSTEM){BATTERY}[ERROR]: No recipients (no alert addresses set and no admin email found)");
        return false;
    }

    $sent = 0;
    foreach ($recipients as $to) {
        $result = mailer($to, $subject, $message);
        // mail() returns true/false, Mailgun the decoded API reply (an id when queued)
        if ($result === true || (is_array($result) && !empty($result['id']))) {
            $sent++;
        } else {
            // No address in the log; the MAILER line has the reason (and the address with debug logging on)
            syslog(LOG_ERR, "(SYSTEM){BATTERY}[ERROR]: Sending \"$subject\" to a recipient failed");
        }
    }
    return $sent > 0;
}
