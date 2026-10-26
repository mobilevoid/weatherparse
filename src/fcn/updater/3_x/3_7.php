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
 * File: src/fcn/updater/3_x/3_7.php
 * 3.7 Site Update Tasks
 */

/**
 * @var mysqli $conn Global MYSQL Connection
 * @var object $config Global Config
 * @var string $notes
 */

switch ($config->version->app) {

    // Update from 3.6.1 to 3.7.0
    case '3.6.1':
        $config->version->app = '3.7.0';
        @$config->station->reverse_wind = false;
        // 3.7.0 also added Matomo settings; WeatherParse 4.1.1 removed analytics, so they are no longer created here
        $notes .= '<li><strong>' . $config->version->app . '</strong> - ' . 'Add Wind Direction reversal. Remove WU webcam upload.';

    // Update from 3.7.0 to 3.7.1
    case '3.7.0':
        $config->version->app = '3.7.1';
        $notes .= '<li><strong>' . $config->version->app . '</strong> - ' . 'Fix Wind Direction bug.';
}
