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
 * File: src/fcn/cron/uploaders/windy.php
 * Windy Updater
 */

/**
 * @var mysqli $conn Global MYSQL Connection
 * @var object $config Global Config
 * @var object $data Weather Data
 * @var object $atlas Atlas Data
 */

syslog(LOG_NOTICE, "(EXTERNAL){Windy}: Starting Update ...");

/*
 * Windy Stations API v2 (the v1 /pws/update path is retired).
 * GET <url>?id=<station id>&ts=<unix time>&temp=..., station password as a Bearer token (never in the URL).
 * Units: temp and dewpoint in C, wind and gust in m/s, winddir in degrees, pressure in Pa, rh in %.
 * Windy accepts one observation per station every 5 minutes.
 */
$sql = "SELECT `timestamp` FROM `windy_updates` ORDER BY `timestamp` DESC LIMIT 1";
$result = mysqli_fetch_assoc(mysqli_query($conn, $sql));
$count = mysqli_num_rows(mysqli_query($conn, $sql));

if (empty($config->upload->windy->id) || empty($config->upload->windy->key)) {
    syslog(LOG_ERR, "(EXTERNAL){Windy}[ERROR]: Station ID and station password are required (Settings > Upload > Windy)");
} // Make sure update interval has passed since last update
elseif (($count == 0) || (strtotime($result['timestamp']) < strtotime('-5 minutes'))) {
    $windyFields = [
        'id' => $config->upload->windy->id,
        'ts' => time(),
        'temp' => round((float)$data->tempC, 1),
        'dewpoint' => round((float)$data->dewptC, 1),
        'rh' => (int)$data->relH,
        'wind' => round((float)$data->windSpeedMPH * 0.44704, 1),
        'gust' => round((float)$data->windGustMPH * 0.44704, 1),
        'winddir' => ((int)$data->windDEG % 360 + 360) % 360,
        'pressure' => (int)round((float)$data->pressure_inHg * 3386.389),
        'softwaretype' => 'WeatherParse',
    ];
    $windyQuery = '?' . http_build_query($windyFields);
    $ch = curl_init(rtrim($config->upload->windy->url, '/') . $windyQuery);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $config->upload->windy->key],
    ]);
    $windyResponse = curl_exec($ch);
    $windyStatus = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $windyQueryResult = "HTTP $windyStatus " . substr((string)$windyResponse, 0, 200);

    // Save to DB (the query holds no password)
    $windyQuerySql = mysqli_real_escape_string($conn, $windyQuery);
    $windyResultSql = mysqli_real_escape_string($conn, $windyQueryResult);
    mysqli_query($conn, "INSERT INTO `windy_updates` (`query`,`result`) VALUES ('$windyQuerySql', '$windyResultSql')");

    if ($windyStatus === 200) {
        syslog(LOG_NOTICE, "(EXTERNAL){Windy}: Query = $windyQuery | Response = $windyQueryResult");
    } else {
        syslog(LOG_WARNING, "(EXTERNAL){Windy}[WARN]: Query = $windyQuery | Response = $windyQueryResult");
    }
} // No new update to send
else {
    // Log it
    syslog(LOG_NOTICE, "(EXTERNAL){Windy}: Update not sent. Not enough time has passed");
}
