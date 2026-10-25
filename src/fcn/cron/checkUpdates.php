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
 * File: src/fcn/cron/checkUpdates.php
 * System Update Checker
 */

require(dirname(__DIR__, 2) . '/inc/loader.php');

/**
 * @var object $config Global Config
 * @var mysqli $conn Global MYSQL Connection
 * @var object $appInfo Global Application Info
 */

/*
 * Update check: reads the latest release of this project from the public GitHub API.
 * Nothing about this installation is sent; the request carries only a generic User-Agent.
 */
$result = mysqli_fetch_assoc(mysqli_query($conn, "SELECT `value` FROM `system` WHERE `name` = 'lastUpdateCheck'"));
if ($result) {
    syslog(LOG_NOTICE, "(SYSTEM){UPDATER}: Checking for updates ...");
    // Make sure update interval has passed since last update
    if ((strtotime($result['value']) < strtotime("-" . '23 hours 20 min')) || isset($updateComplete)) {
        $ch = curl_init($appInfo->releases_api);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json', 'User-Agent: WeatherParse'],
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $lastCheckedOn = date("Y-m-d H:i:s");
        mysqli_query($conn, "UPDATE `system` SET `value` = '$lastCheckedOn' WHERE `name` = 'lastUpdateCheck'");

        if ($status === 200 && ($release = json_decode($response)) && !empty($release->tag_name)) {
            $latestRelease = ltrim((string)$release->tag_name, 'vV');
            $latestReleaseSql = mysqli_real_escape_string($conn, $latestRelease);
            $result = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT `value` FROM `system` WHERE `name`='latestRelease'"));
            if (($result['value'] ?? null) !== $latestRelease) {
                mysqli_query($conn, "UPDATE `system` SET `value` = '$latestReleaseSql' WHERE `name` = 'latestRelease'");
            }
            if (version_compare($config->version->app, $latestRelease, '<')) {
                syslog(LOG_INFO, "(SYSTEM){UPDATER}: Version $latestRelease available");
            } else {
                syslog(LOG_NOTICE, "(SYSTEM){UPDATER}: No new version available");
            }
        } elseif ($status === 404) {
            syslog(LOG_NOTICE, "(SYSTEM){UPDATER}: No releases published yet");
        } else {
            syslog(LOG_ERR, "(SYSTEM){UPDATER}: Error checking for update (HTTP $status)");
        }
    } else {
        syslog(LOG_NOTICE, "(SYSTEM){UPDATER}: Too soon to check for updates");
    }
}
