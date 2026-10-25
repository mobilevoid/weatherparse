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
 * File: src/api/system/index.php
 * System API Index
 */

header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
header('Content-Type: application/json; charset=UTF-8'); // Set the header for JSON output
echo '{"error":"Bad Request - Missing Endpoint"}';
