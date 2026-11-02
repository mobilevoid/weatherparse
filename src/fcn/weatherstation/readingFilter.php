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
 * A failing or wet temperature/humidity element can report values like 158 F (the sensor's error value), sit at
 * 120 F or more with the humidity pinned near 100%, or jump 40 F between two five-minute reports. Such a reading is
 * not stored and not uploaded; the other sensors in the same report are.
 *
 * Rules (station.spike_filter = true):
 *  - outside -60 F .. 140 F: rejected
 *  - impossible air: when the dew point worked out from the temperature and humidity is above
 *    station.spike_max_dewpoint F (default 95; the highest dew point ever measured on Earth is about 95 F), the
 *    temperature AND the humidity of that report are rejected, because both come from the same element
 *  - compared with the median of the last SPIKE_FILTER_REFERENCE_READINGS temperatures stored in the
 *    SPIKE_FILTER_WINDOW_MINUTES before it: the change may not exceed station.spike_max_change F (default 10) per
 *    5 minutes since the newest of them, never less than spike_max_change and never more than
 *    SPIKE_FILTER_MAX_GROWTH times it. Only accepted readings are stored and the reference uses up to six of them,
 *    so one bad reading that slipped through can no longer become the reference and cause good readings to be
 *    thrown away, and a long run of bad readings can no longer widen the limit until a bad value gets in.
 *  - nothing stored in the last SPIKE_FILTER_WINDOW_MINUTES: any possible value is accepted, so a real change after
 *    a long outage can never lock the station out for longer than that
 */

const SPIKE_FILTER_MIN_F = -60.0;
const SPIKE_FILTER_MAX_F = 140.0;
const SPIKE_FILTER_WINDOW_MINUTES = 180;
const SPIKE_FILTER_REFERENCE_READINGS = 6;
const SPIKE_FILTER_MAX_GROWTH = 3.0;
const SPIKE_FILTER_MAX_DEWPOINT_F = 95.0;

/**
 * Dew point (F) from temperature (F) and relative humidity (%), Magnus formula.
 *
 * @param float $tempF Temperature
 * @param int|null $humidity Relative humidity, 1 to 100
 * @return float|null null when the humidity is not a usable value
 */
function spikeFilterDewPointF(float $tempF, ?int $humidity): ?float
{
    if ($humidity === null || $humidity < 1 || $humidity > 100) {
        return null;
    }
    $tempC = ($tempF - 32) * 5 / 9;
    $gamma = log($humidity / 100) + (17.62 * $tempC) / (243.12 + $tempC);
    $dewC = 243.12 * $gamma / (17.62 - $gamma);
    return $dewC * 9 / 5 + 32;
}

/**
 * The decision itself, without the database, so it can be tested against logged readings.
 *
 * @param float $tempF The new reading
 * @param int|null $humidity Humidity of the same report, null when unknown
 * @param int $readingTime Unix time of the new reading
 * @param array $recent Accepted readings before it, newest first, each [float tempF, int unixTime]
 * @param float $maxChangePer5 station.spike_max_change (0 or less turns the change rule off)
 * @param float $maxDewpointF station.spike_max_dewpoint (0 or less turns the dew point rule off)
 * @return array ['accepted' => bool, 'reason' => string (empty when accepted), 'impossible_air' => bool]
 */
function spikeFilterDecision(float $tempF, ?int $humidity, int $readingTime, array $recent, float $maxChangePer5, float $maxDewpointF = SPIKE_FILTER_MAX_DEWPOINT_F): array
{
    if ($tempF < SPIKE_FILTER_MIN_F || $tempF > SPIKE_FILTER_MAX_F) {
        return ['accepted' => false, 'impossible_air' => false,
            'reason' => sprintf('outside %.0f to %.0f F', SPIKE_FILTER_MIN_F, SPIKE_FILTER_MAX_F)];
    }

    $dewF = spikeFilterDewPointF($tempF, $humidity);
    if ($dewF !== null && $maxDewpointF > 0 && $dewF > $maxDewpointF) {
        return ['accepted' => false, 'impossible_air' => true,
            'reason' => sprintf('dew point %.1f F at %d%% humidity is impossible; limit %.1f F', $dewF, $humidity, $maxDewpointF)];
    }

    $windowStart = $readingTime - SPIKE_FILTER_WINDOW_MINUTES * 60;
    $reference = [];
    $newest = null;
    foreach ($recent as [$recentTemp, $recentTime]) {
        if ($recentTime >= $readingTime || $recentTime < $windowStart) {
            continue;
        }
        $reference[] = (float)$recentTemp;
        $newest = ($newest === null) ? $recentTime : max($newest, $recentTime);
        if (count($reference) >= SPIKE_FILTER_REFERENCE_READINGS) {
            break;
        }
    }
    if (!$reference || $maxChangePer5 <= 0) {
        return ['accepted' => true, 'impossible_air' => false, 'reason' => ''];
    }

    sort($reference);
    $mid = intdiv(count($reference), 2);
    $median = count($reference) % 2 ? $reference[$mid] : ($reference[$mid - 1] + $reference[$mid]) / 2;
    $minutes = max(5, ($readingTime - $newest) / 60);
    $allowed = min(max($maxChangePer5, $maxChangePer5 * $minutes / 5), $maxChangePer5 * SPIKE_FILTER_MAX_GROWTH);
    $change = abs($tempF - $median);
    if ($change > $allowed) {
        return ['accepted' => false, 'impossible_air' => false,
            'reason' => sprintf('%.1f F from the recent median %.1f F of %d readings; limit %.1f F', $change, $median, count($reference), $allowed)];
    }
    return ['accepted' => true, 'impossible_air' => false, 'reason' => ''];
}

/**
 * @param mysqli $conn Database connection
 * @param object $config Global config
 * @param float $tempF The new reading
 * @param string $timestamp Reading time, Y-m-d H:i:s
 * @param string $label Sensor label for the log, e.g. ATLAS
 * @param int|null $humidity Humidity of the same report (for the dew point rule), null when unknown
 * @return bool true when the reading may be stored
 */
function temperatureReadingAccepted(mysqli $conn, object $config, float $tempF, string $timestamp, string $label, ?int $humidity = null): bool
{
    if (($config->station->spike_filter ?? true) !== true) {
        return true;
    }

    $readingTime = strtotime($timestamp);
    $sinceSql = mysqli_real_escape_string($conn, date('Y-m-d H:i:s', $readingTime - SPIKE_FILTER_WINDOW_MINUTES * 60));
    $untilSql = mysqli_real_escape_string($conn, $timestamp);
    $limit = SPIKE_FILTER_REFERENCE_READINGS * 2;
    $result = mysqli_query($conn, "SELECT `tempF`, `timestamp` FROM `temperature` WHERE `timestamp` >= '$sinceSql' AND `timestamp` < '$untilSql' AND `tempF` IS NOT NULL ORDER BY `timestamp` DESC LIMIT $limit");
    $recent = [];
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $recent[] = [(float)$row['tempF'], strtotime($row['timestamp'])];
    }

    $decision = spikeFilterDecision($tempF, $humidity, $readingTime, $recent,
        (float)($config->station->spike_max_change ?? 10),
        (float)($config->station->spike_max_dewpoint ?? SPIKE_FILTER_MAX_DEWPOINT_F));
    if (!$decision['accepted']) {
        syslog(LOG_WARNING, sprintf("(ACCESS){%s}[FILTER]: Dropped temperature %.1f F (%s)", $label, $tempF, $decision['reason']));
    }
    return $decision['accepted'];
}

/**
 * Humidity is stored unless it and the temperature of the same report describe impossible air
 * (the same element measures both, so neither value can be trusted then).
 *
 * @param object $config Global config
 * @param float $tempF Temperature of the same report
 * @param int $humidity The new humidity reading
 * @param string $label Sensor label for the log, e.g. ATLAS
 * @return bool true when the humidity may be stored
 */
function humidityReadingAccepted(object $config, float $tempF, int $humidity, string $label): bool
{
    if (($config->station->spike_filter ?? true) !== true) {
        return true;
    }
    $limit = (float)($config->station->spike_max_dewpoint ?? SPIKE_FILTER_MAX_DEWPOINT_F);
    $dewF = spikeFilterDewPointF($tempF, $humidity);
    if ($dewF !== null && $limit > 0 && $dewF > $limit) {
        syslog(LOG_WARNING, sprintf("(ACCESS){%s}[FILTER]: Dropped humidity %d%% (reported with %.1f F, dew point %.1f F is impossible)", $label, $humidity, $tempF, $dewF));
        return false;
    }
    return true;
}
