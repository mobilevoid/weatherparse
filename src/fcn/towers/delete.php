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
 * File: src/fcn/towers/delete.php
 * Delete a Tower
 */

/**
 * @var mysqli $conn Global MYSQL Connection
 */

$towerSensorID = mysqli_real_escape_string($conn,
    filter_input(INPUT_GET, 'sensor_id', FILTER_CALLBACK, ['options' => 'legacySanitizeString']));
$result = mysqli_query($conn, "DELETE FROM `towers` WHERE `sensor` = '$towerSensorID'");
// If the insert Query was successful.
if (mysqli_affected_rows($conn) === 1) {
    $_SESSION['messages'] = '<div class="alert alert-success alert-dismissible"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>Tower Deleted Successfully!</div>';
    syslog(LOG_INFO, "(SYSTEM){TOWER}: Tower $towerSensorID deleted successfully");
} else {
    $_SESSION['messages'] = '<div class="alert alert-danger alert-dismissible"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>Oops, something went wrong deleting the tower!</div>';
    syslog(LOG_ERR, "(SYSTEM){TOWER}[ERROR]: Deleting tower $towerSensorID failed!");
}
// Redirect to admin
header("Location: /admin/tower?view");
exit();
