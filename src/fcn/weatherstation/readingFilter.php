<?php
/**
 * WeatherParse - AcuRite Access/smartHUB and IP Camera Data Processing, Display, and Upload.
 * @copyright Copyright (C) 2026 WeatherParse contributors
 * @link https://github.com/mobilevoid/weatherparse
 * @license AGPL-3.0+
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
 * File: src/fcn/weatherstation/readingFilter.php
 * Temperature spike filter for Iris/Atlas readings.
 *
 * A failing temperature/humidity element can report values like 158 F (the sensor's error value) or jump 40 F between
 * two five-minute reports. Such a reading is not stored and not uploaded; the other sensors in the same report are.
 *
 * Rules (station.spike_filter = true):
 *  - outside -60 F .. 140 F: rejected
 *  - compared with the last stored temperature: if that is at most 30 minutes old, the change may not exceed
 *    station.spike_max_change F (default 10) per 5 minutes elapsed, never less than spike_max_change itself
 *  - the last stored temperature is older than 30 minutes (or there is none): any in-range value is accepted, so a
 *    real change can never lock the station out
 */

const SPIKE_FILTER_MIN_F = -60.0;
const SPIKE_FILTER_MAX_F = 140.0;
const SPIKE_FILTER_WINDOW_MINUTES = 30;

/**
 * @param mysqli $conn Database connection
 * @param object $config Global config
 * @param float $tempF The new reading
 * @param string $timestamp Reading time, Y-m-d H:i:s
 * @param string $label Sensor label for the log, e.g. ATLAS
 * @return bool true when the reading may be stored
 */
function temperatureReadingAccepted(mysqli $conn, object $config, float $tempF, string $timestamp, string $label): bool
{
    if (($config->station->spike_filter ?? true) !== true) {
        return true;
    }

    if ($tempF < SPIKE_FILTER_MIN_F || $tempF > SPIKE_FILTER_MAX_F) {
        syslog(LOG_WARNING, "(ACCESS){{$label}}[FILTER]: Dropped temperature $tempF F (outside " . SPIKE_FILTER_MIN_F . ' to ' . SPIKE_FILTER_MAX_F . ' F)');
        return false;
    }

    $result = mysqli_query($conn, "SELECT `tempF`, `timestamp` FROM `temperature` ORDER BY `timestamp` DESC LIMIT 1");
    $last = $result ? mysqli_fetch_assoc($result) : null;
    if (!$last || $last['tempF'] === null) {
        return true;
    }

    $minutes = (strtotime($timestamp) - strtotime($last['timestamp'])) / 60;
    if ($minutes < 0 || $minutes > SPIKE_FILTER_WINDOW_MINUTES) {
        return true;
    }

    $maxChangePer5 = (float)($config->station->spike_max_change ?? 10);
    if ($maxChangePer5 <= 0) {
        return true;
    }
    $allowed = max($maxChangePer5, $maxChangePer5 * $minutes / 5);
    $change = abs($tempF - (float)$last['tempF']);
    if ($change > $allowed) {
        syslog(LOG_WARNING, sprintf("(ACCESS){%s}[FILTER]: Dropped temperature %.1f F (%.1f F from %.1f F %.0f min earlier; limit %.1f F)",
            $label, $tempF, $change, (float)$last['tempF'], $minutes, $allowed));
        return false;
    }
    return true;
}
