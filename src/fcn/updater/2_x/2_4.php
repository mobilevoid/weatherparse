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
 * File: src/fcn/updater/2_x/2_4.php
 * 2.4 Site Update Tasks
 */

/**
 * @var mysqli $conn Global MYSQL Connection
 * @var object $config Global Config
 * @var string $notes
 */

switch ($config->version->app) {

    // Update from 2.3.2-beta
    case '2.3.2-beta':
    case '2.3.2':
    case '2.4.0-beta':
        $config->version->app = '2.4.0-release';
        @$config->station->baro_source = 0;
        $notes .= '<li><strong>' . $config->version->app . '</strong> - ' . 'Added ability to select which barometer values are retained.</li>';
}
