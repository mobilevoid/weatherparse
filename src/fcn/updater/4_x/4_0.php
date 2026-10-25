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
 * File: src/fcn/updater/4_x/4_0.php
 * Acuparse 3.9.4 -> WeatherParse 4.0.0
 */

/**
 * @var mysqli $conn Global MYSQL Connection
 * @var object $config Global Config
 * @var string $notes
 */

switch ($config->version->app) {

    // Acuparse 3.9.4 to WeatherParse 4.0.0
    case '3.9.4':
        $config->version->app = '4.0.0';
        // The acuparse.com relay is a third-party service; MyAcuRite is now reached directly (see weatherstation/myacurite.php)
        if (isset($config->upload->myacurite->access_url) && str_contains($config->upload->myacurite->access_url, 'acuparse.com')) {
            $config->upload->myacurite->access_url = 'https://atlasapi.myacurite.com';
        }
        if (isset($config->upload->myacurite->hub_url) && str_contains($config->upload->myacurite->hub_url, 'acuparse.com')) {
            $config->upload->myacurite->hub_url = 'http://hubapi.myacurite.com';
        }
        $notes .= '<li><strong>' . $config->version->app . '</strong> - ' . 'WeatherParse 4.0.0: PHP 8.2 to 8.4, no telemetry (update check reads GitHub releases), MyAcuRite forwarding without a relay. See CHANGELOG.txt</li>';

}
