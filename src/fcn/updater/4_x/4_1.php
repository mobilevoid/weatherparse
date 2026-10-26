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
 * File: src/fcn/updater/4_x/4_1.php
 * WeatherParse 4.0.0 -> 4.1.0
 */

/**
 * @var mysqli $conn Global MYSQL Connection
 * @var object $config Global Config
 * @var string $notes
 */

switch ($config->version->app) {

    // WeatherParse 4.0.0 to 4.1.0
    case '4.0.0':
        $config->version->app = '4.1.0';
        require_once(APP_BASE_PATH . '/fcn/batteryAlert.php');
        // Low battery email alerts. Settings already in the config (new installs) are kept.
        if (!isset($config->battery_alert)) {
            $config->battery_alert = (object)array();
        }
        if (!isset($config->battery_alert->enabled)) {
            // On when mail is set up (Mailgun or a local mail program)
            $config->battery_alert->enabled = batteryAlertMailConfigured($config);
        }
        if (!isset($config->battery_alert->recipients)) {
            $config->battery_alert->recipients = '';
        }
        if (!isset($config->battery_alert->reminder_hours)) {
            $config->battery_alert->reminder_hours = 24;
        }
        if (!isset($config->battery_alert->send_recovery)) {
            $config->battery_alert->send_recovery = true;
        }
        if (batteryAlertEnsureTable($conn)) {
            syslog(LOG_INFO, "(SYSTEM){UPDATER}: battery_alert table ready");
        } else {
            syslog(LOG_ERR, "(SYSTEM){UPDATER}[ERROR]: Creating the battery_alert table failed; cron will retry");
            $notes .= '<li><strong>Warning:</strong> the battery_alert table could not be created. The cron job retries each run; check the database user can CREATE tables.</li>';
        }
        $notes .= '<li><strong>' . $config->version->app . '</strong> - ' . 'Low battery email alerts (Access hub, Atlas, Iris, towers). Battery alerts are ' . (($config->battery_alert->enabled === true) ? 'on' : 'off') . '; see Settings, Features. See CHANGELOG.txt</li>';

}
