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
 * File: src/fcn/weather/dashboard/towerLightning.php
 * Get the tower lightning HTML
 */

/**
 * @var object $towerLightning Tower Lightning Values
 * @var object $config Global Config
 * @var object $lightning Lightning Values
 */

?>
<section id="live-tower-lightning-data" class="row live-weather-data">
    <div class="col-12">
        <h1><i class="fas fa-bolt" aria-hidden="true"></i> Lightning Tower</h1>
        <ul class="list-unstyled">
            <?php
            if ($towerLightning->dailystrikes != 0) {
                ?>
                <li><h3>Latest Strikes:</h3> <?= $towerLightning->currentstrikes; ?> | <h3>
                        Total Today:</h3> <?= $towerLightning->dailystrikes; ?></li>
                <li><h3>Distance:</h3> <?php
                    if ($config->site->hide_alternate === 'false' || $config->site->hide_alternate === 'archive') {
                        $last_strike_distance = ($config->site->imperial === true) ? "$towerLightning->last_strike_distance_M Miles ($towerLightning->last_strike_distance_KM KM)" : "$towerLightning->last_strike_distance_KM KM ($towerLightning->last_strike_distance_M Miles)";
                    } else {
                        $last_strike_distance = ($config->site->imperial === true) ? "$towerLightning->last_strike_distance_M Miles" : "$towerLightning->last_strike_distance_KM KM";
                    }
                    echo $last_strike_distance; ?></li>
                <li><h3>Interference:</h3> <?= $towerLightning->interference; ?> |
                    <h3>Last:</h3> <?= $towerLightning->last_update; ?></li>
                <?php
            } else {
                ?>
                <li><h3>No Lightning Detected</h3></li>
                <li><h3>Interference:</h3> <?= $towerLightning->interference; ?></li>
                <?php
            }
            ?>
        </ul>
    </div>
</section>
