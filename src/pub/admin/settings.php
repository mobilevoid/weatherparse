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
 * File: src/pub/admin/settings.php
 * Modify Config Settings
 */

// Get the loader
require(dirname(__DIR__, 2) . '/inc/loader.php');

// Battery alert settings helpers
require_once(APP_BASE_PATH . '/fcn/batteryAlert.php');

/**
 * @var object $config Global Config
 */

if (isset($_SESSION['authenticated']) && $_SESSION['admin'] === true) {

    // Process the changes
    if (isset($_GET['do'])) {

        function checkMAC($MAC)
        {
            $patterns = array();
            $patterns[0] = '/:/';
            $patterns[1] = '/-/';
            $patterns[2] = '/ /';
            $replacement = '';
            return preg_replace($patterns, $replacement, $MAC);
        }

        // Do some quick input filtering
        $_POST = legacySanitizeArray(filter_input_array(INPUT_POST, FILTER_UNSAFE_RAW) ?: []);

        //Database
        $config->mysql->host = isset($_POST['mysql']['host']) ? $_POST['mysql']['host'] : ($config->mysql->host ?? null);
        $config->mysql->database = isset($_POST['mysql']['database']) ? $_POST['mysql']['database'] : ($config->mysql->database ?? null);
        $config->mysql->username = isset($_POST['mysql']['username']) ? $_POST['mysql']['username'] : ($config->mysql->username ?? null);
        $config->mysql->password = isset($_POST['mysql']['password']) ? $_POST['mysql']['password'] : ($config->mysql->password ?? null);
        // Check and adjust database trim level
        if ($config->mysql->trim != $_POST['mysql']['trim']) {
            if ($_POST['mysql']['trim'] === '1') {
                $schema = dirname(__DIR__, 3) . '/sql/trim/enable.sql';
            } elseif ($_POST['mysql']['trim'] === '2') {
                $schema = dirname(__DIR__, 3) . '/sql/trim/enable_xtower.sql';
            } else {
                $schema = dirname(__DIR__, 3) . '/sql/trim/disable.sql';
            }
            $schema = mysqlClientCommand($config->mysql, escapeshellarg($config->mysql->database) . ' < ' . escapeshellarg($schema));
            $schema = exec($schema, $schemaOutput, $schemaReturn);
            syslog(LOG_INFO, "(SYSTEM){CONFIG}: Database Trimming Updated");
            $config->mysql->trim = isset($_POST['mysql']['trim']) ? (int)$_POST['mysql']['trim'] : ($config->mysql->trim ?? 0);
        }

        // Station
        $config->station->device = isset($_POST['station']['device']) ? (int)$_POST['station']['device'] : ($config->station->device ?? 0);
        $config->station->hub_mac = (isset($_POST['station']['hub_mac'])) ? checkMAC($_POST['station']['hub_mac']) : ($config->station->hub_mac ?? null);
        $config->station->access_mac = (isset($_POST['station']['access_mac'])) ? checkMAC($_POST['station']['access_mac']) : ($config->station->access_mac ?? null);
        $config->station->primary_sensor = isset($_POST['station']['primary_sensor']) ? (int)$_POST['station']['primary_sensor'] : ($config->station->primary_sensor ?? 0);
        $config->station->sensor_iris = (isset($_POST['station']['sensor_iris'])) ? sprintf('%08d',
            $_POST['station']['sensor_iris']) : null;
        $config->station->sensor_atlas = (isset($_POST['station']['sensor_atlas'])) ? sprintf('%08d',
            $_POST['station']['sensor_atlas']) : null;
        $config->station->baro_offset = (isset($_POST['station']['baro_offset'])) ? (float)$_POST['station']['baro_offset'] : ($config->station->baro_offset ?? 0);
        $config->station->towers = isset($_POST['station']['towers']) ? (bool)$_POST['station']['towers'] : ($config->station->towers ?? false);
        $config->station->towers_additional = isset($_POST['station']['towers_additional']) ? (bool)$_POST['station']['towers_additional'] : ($config->station->towers_additional ?? false);
        $config->station->lightning_source = isset($_POST['station']['lightning_source']) ? (int)$_POST['station']['lightning_source'] : ($config->station->lightning_source ?? 0);
        $config->station->filter_access = isset($_POST['station']['filter_access']) ? (bool)$_POST['station']['filter_access'] : ($config->station->filter_access ?? false);
        $config->station->spike_filter = isset($_POST['station']['spike_filter']) ? (bool)$_POST['station']['spike_filter'] : ($config->station->spike_filter ?? true);
        $config->station->spike_max_change = isset($_POST['station']['spike_max_change']) ? max(1.0, min(50.0, (float)$_POST['station']['spike_max_change'])) : ($config->station->spike_max_change ?? 10.0);
        $config->station->maintenance = isset($_POST['station']['maintenance']) ? (bool)$_POST['station']['maintenance'] : ($config->station->maintenance ?? false);
        $config->station->reverse_wind = isset($_POST['station']['reverse_wind']) ? (bool)$_POST['station']['reverse_wind'] : ($config->station->reverse_wind ?? false);
        $config->station->realtime = isset($_POST['station']['realtime']) ? (bool)$_POST['station']['realtime'] : ($config->station->realtime ?? false);
        $config->station->realtimeUTC = isset($_POST['station']['realtimeUTC']) ? (bool)$_POST['station']['realtimeUTC'] : ($config->station->realtimeUTC ?? false);

        // Site
        $config->site->name = isset($_POST['site']['name']) ? $_POST['site']['name'] : ($config->site->name ?? null);
        $config->site->desc = isset($_POST['site']['desc']) ? $_POST['site']['desc'] : ($config->site->desc ?? null);
        $config->site->location = isset($_POST['site']['location']) ? $_POST['site']['location'] : ($config->site->location ?? null);
        $config->site->hostname = isset($_POST['site']['hostname']) ? $_POST['site']['hostname'] : ($config->site->hostname ?? null);
        $config->site->email = isset($_POST['site']['email']) ? $_POST['site']['email'] : ($config->site->email ?? null);
        $config->site->timezone = isset($_POST['site']['timezone']) ? $_POST['site']['timezone'] : ($config->site->timezone ?? null);
        $config->site->display_date = isset($_POST['site']['display_date']) ? $_POST['site']['display_date'] : ($config->site->display_date ?? null);
        $config->site->dashboard_display_time = isset($_POST['site']['dashboard_display_time']) ? $_POST['site']['dashboard_display_time'] : ($config->site->dashboard_display_time ?? null);
        $config->site->dashboard_display_date = isset($_POST['site']['dashboard_display_date']) ? $_POST['site']['dashboard_display_date'] : ($config->site->dashboard_display_date ?? null);
        $config->site->dashboard_display_date_full = isset($_POST['site']['dashboard_display_date_full']) ? $_POST['site']['dashboard_display_date_full'] : ($config->site->dashboard_display_date_full ?? null);
        $config->site->date_api_json = isset($_POST['site']['date_api_json']) ? $_POST['site']['date_api_json'] : ($config->site->date_api_json ?? null);
        $config->site->lat = isset($_POST['site']['lat']) ? (float)$_POST['site']['lat'] : ($config->site->lat ?? 0.0);
        $config->site->long = isset($_POST['site']['long']) ? (float)$_POST['site']['long'] : ($config->site->long ?? 0.0);
        $config->site->imperial = isset($_POST['site']['imperial']) ? (bool)$_POST['site']['imperial'] : ($config->site->imperial ?? false);
        $config->site->hide_alternate = isset($_POST['site']['hide_alternate']) ? $_POST['site']['hide_alternate'] : ($config->site->hide_alternate ?? null);
        $config->site->theme = isset($_POST['site']['theme']) ? $_POST['site']['theme'] : ($config->site->theme ?? null);
        $config->site->updates = isset($_POST['site']['updates']) ? (bool)$_POST['site']['updates'] : ($config->site->updates ?? false);

        // Webcam
        $config->camera->enabled = isset($_POST['camera']['enabled']) ? (bool)$_POST['camera']['enabled'] : ($config->camera->enabled ?? false);
        $config->camera->text = (isset($_POST['camera']['text'])) ? $_POST['camera']['text'] : ($config->camera->text ?? 'Image updated every X minutes.');
        $config->camera->name = trim((string)($_POST['camera']['name'] ?? '')) ?: 'Camera 1';
        $config->camera->name2 = trim((string)($_POST['camera']['name2'] ?? '')) ?: 'Camera 2';
        $config->camera->sort->today = (isset($_POST['camera']['sort']['today'])) ? $_POST['camera']['sort']['today'] : ($config->camera->sort->today ?? 'ascending');
        $config->camera->sort->archive = (isset($_POST['camera']['sort']['archive'])) ? $_POST['camera']['sort']['archive'] : ($config->camera->sort->archive ?? 'ascending');

        // Archive
        $config->archive->enabled = isset($_POST['archive']['enabled']) ? (bool)$_POST['archive']['enabled'] : ($config->archive->enabled ?? false);

        // Contact
        $config->contact->enabled = isset($_POST['contact']['enabled']) ? (bool)$_POST['contact']['enabled'] : ($config->contact->enabled ?? false);

        // Email Outage Alerts
        $config->outage_alert->enabled = isset($_POST['outage_alert']['enabled']) ? (bool)$_POST['outage_alert']['enabled'] : ($config->outage_alert->enabled ?? false);
        $config->outage_alert->offline_for = (isset($_POST['outage_alert']['offline_for'])) ? $_POST['outage_alert']['offline_for'] : ($config->outage_alert->offline_for ?? '5 minutes');
        $config->outage_alert->interval = (isset($_POST['outage_alert']['interval'])) ? $_POST['outage_alert']['interval'] : ($config->outage_alert->interval ?? '1 hour');

        // Email Battery Alerts (fields of a disabled alert are not sent and keep their saved values)
        $batteryAlert = batteryAlertSettings($config);
        if (!isset($config->battery_alert)) {
            $config->battery_alert = (object)array();
        }
        $config->battery_alert->enabled = isset($_POST['battery_alert']['enabled']) ? (bool)$_POST['battery_alert']['enabled'] : $batteryAlert->enabled;
        $config->battery_alert->recipients = isset($_POST['battery_alert']['recipients']) ? batteryAlertCleanRecipients((string)$_POST['battery_alert']['recipients']) : $batteryAlert->recipients;
        $config->battery_alert->reminder_hours = isset($_POST['battery_alert']['reminder_hours']) ? max(0, min(BATTERY_ALERT_MAX_REMINDER_HOURS, (int)$_POST['battery_alert']['reminder_hours'])) : $batteryAlert->reminder_hours;
        $config->battery_alert->send_recovery = isset($_POST['battery_alert']['send_recovery']) ? (bool)$_POST['battery_alert']['send_recovery'] : $batteryAlert->send_recovery;

        // Google

        // reCAPTCHA
        $config->google->recaptcha->enabled = isset($_POST['google']['recaptcha']['enabled']) ? (bool)$_POST['google']['recaptcha']['enabled'] : ($config->google->recaptcha->enabled ?? false);
        $config->google->recaptcha->secret = (isset($_POST['google']['recaptcha']['secret'])) ? $_POST['google']['recaptcha']['secret'] : ($config->google->recaptcha->secret ?? null);
        $config->google->recaptcha->sitekey = (isset($_POST['google']['recaptcha']['sitekey'])) ? $_POST['google']['recaptcha']['sitekey'] : ($config->google->recaptcha->sitekey ?? null);

        // Analytics and Matomo were removed in 4.1.1 (no tracking); drop any keys left in an older config
        unset($config->google->analytics, $config->matomo);

        // Mailgun
        $config->mailgun->enabled = isset($_POST['mailgun']['enabled']) ? (bool)$_POST['mailgun']['enabled'] : ($config->mailgun->enabled ?? false);
        $config->mailgun->secret = (isset($_POST['mailgun']['secret'])) ? $_POST['mailgun']['secret'] : ($config->mailgun->secret ?? null);
        $config->mailgun->domain = (isset($_POST['mailgun']['domain'])) ? $_POST['mailgun']['domain'] : ($config->mailgun->domain ?? null);

        // Uploader

        // Master Sensor
        $config->upload->sensor->external = isset($_POST['upload']['sensor']['external']) ? $_POST['upload']['sensor']['external'] : ($config->upload->sensor->external ?? null);
        $config->upload->sensor->id = (isset($_POST['upload']['sensor']['id'])) ? $_POST['upload']['sensor']['id'] : ($config->upload->sensor->id ?? null);
        $config->upload->sensor->archive = isset($_POST['upload']['sensor']['archive']) ? (bool)$_POST['upload']['sensor']['archive'] : ($config->upload->sensor->archive ?? false);

        // WU
        $config->upload->wu->enabled = isset($_POST['upload']['wu']['enabled']) ? (bool)$_POST['upload']['wu']['enabled'] : ($config->upload->wu->enabled ?? false);
        $config->upload->wu->id = (isset($_POST['upload']['wu']['id'])) ? $_POST['upload']['wu']['id'] : ($config->upload->wu->id ?? null);
        $config->upload->wu->password = (isset($_POST['upload']['wu']['password'])) ? $_POST['upload']['wu']['password'] : ($config->upload->wu->password ?? null);
        $config->upload->wu->url = (isset($_POST['upload']['wu']['url'])) ? $_POST['upload']['wu']['url'] : ($config->upload->wu->url ?? 'https://weatherstation.wunderground.com/weatherstation/updateweatherstation.php');

        // PWS
        $config->upload->pws->enabled = isset($_POST['upload']['pws']['enabled']) ? (bool)$_POST['upload']['pws']['enabled'] : ($config->upload->pws->enabled ?? false);
        $config->upload->pws->id = (isset($_POST['upload']['pws']['id'])) ? $_POST['upload']['pws']['id'] : ($config->upload->pws->id ?? null);
        $config->upload->pws->key = (isset($_POST['upload']['pws']['key'])) ? $_POST['upload']['pws']['key'] : ($config->upload->pws->key ?? null);
        $config->upload->pws->url = (isset($_POST['upload']['pws']['url'])) ? $_POST['upload']['pws']['url'] : ($config->upload->pws->url ?? 'https://pwsupdate.pwsweather.com/api/v1/submitwx');

        // CWOP
        $config->upload->cwop->enabled = isset($_POST['upload']['cwop']['enabled']) ? (bool)$_POST['upload']['cwop']['enabled'] : ($config->upload->cwop->enabled ?? false);
        $config->upload->cwop->id = (isset($_POST['upload']['cwop']['id'])) ? $_POST['upload']['cwop']['id'] : ($config->upload->cwop->id ?? null);
        $config->upload->cwop->location = (isset($_POST['upload']['cwop']['location'])) ? $_POST['upload']['cwop']['location'] : ($config->upload->cwop->location ?? null);
        $config->upload->cwop->interval = (isset($_POST['upload']['cwop']['interval'])) ? $_POST['upload']['cwop']['interval'] : ($config->upload->cwop->interval ?? '10 minutes');
        $config->upload->cwop->url = (isset($_POST['upload']['cwop']['url'])) ? $_POST['upload']['cwop']['url'] : ($config->upload->cwop->url ?? 'cwop.aprs.net');

        // WC
        $config->upload->wc->enabled = isset($_POST['upload']['wc']['enabled']) ? (bool)$_POST['upload']['wc']['enabled'] : ($config->upload->wc->enabled ?? false);
        $config->upload->wc->id = (isset($_POST['upload']['wc']['id'])) ? $_POST['upload']['wc']['id'] : ($config->upload->wc->id ?? null);
        $config->upload->wc->key = (isset($_POST['upload']['wc']['key'])) ? $_POST['upload']['wc']['key'] : ($config->upload->wc->key ?? null);
        $config->upload->wc->device = (isset($_POST['upload']['wc']['device'])) ? $_POST['upload']['wc']['device'] : ($config->upload->wc->device ?? null);
        $config->upload->wc->url = (isset($_POST['upload']['wc']['url'])) ? $_POST['upload']['wc']['url'] : ($config->upload->wc->url ?? 'https://api.weathercloud.net/v01/set');
        $config->upload->wc->softwareid = (isset($_POST['upload']['wc']['softwareid'])) ? trim((string)$_POST['upload']['wc']['softwareid']) : ($config->upload->wc->softwareid ?? '');

        // Windy
        $config->upload->windy->enabled = isset($_POST['upload']['windy']['enabled']) ? (bool)$_POST['upload']['windy']['enabled'] : ($config->upload->windy->enabled ?? false);
        $config->upload->windy->id = (isset($_POST['upload']['windy']['id'])) ? $_POST['upload']['windy']['id'] : ($config->upload->windy->id ?? null);
        $config->upload->windy->key = (isset($_POST['upload']['windy']['key'])) ? $_POST['upload']['windy']['key'] : ($config->upload->windy->key ?? null);
        $config->upload->windy->station = (isset($_POST['upload']['windy']['station'])) ? $_POST['upload']['windy']['station'] : ($config->upload->windy->station ?? '0');
        $config->upload->windy->url = 'https://stations.windy.com/api/v2/observation/update';

        // Windguru
        $config->upload->windguru->enabled = isset($_POST['upload']['windguru']['enabled']) ? (bool)$_POST['upload']['windguru']['enabled'] : ($config->upload->windguru->enabled ?? false);
        $config->upload->windguru->uid = (isset($_POST['upload']['windguru']['uid'])) ? $_POST['upload']['windguru']['uid'] : ($config->upload->windguru->uid ?? null);
        $config->upload->windguru->id = (isset($_POST['upload']['windguru']['id'])) ? $_POST['upload']['windguru']['id'] : ($config->upload->windguru->id ?? null);
        $config->upload->windguru->password = (isset($_POST['upload']['windguru']['password'])) ? $_POST['upload']['windguru']['password'] : ($config->upload->windguru->password ?? null);
        $config->upload->windguru->url = (isset($_POST['upload']['windguru']['url'])) ? $_POST['upload']['windguru']['url'] : ($config->upload->windguru->url ?? 'https://www.windguru.cz/upload/api.php');

        // OpenWeather
        $config->upload->openweather->enabled = isset($_POST['upload']['openweather']['enabled']) ? (bool)$_POST['upload']['openweather']['enabled'] : ($config->upload->openweather->enabled ?? false);
        $config->upload->openweather->id = (isset($_POST['upload']['openweather']['id'])) ? $_POST['upload']['openweather']['id'] : ($config->upload->openweather->id ?? null);
        $config->upload->openweather->key = (isset($_POST['upload']['openweather']['key'])) ? $_POST['upload']['openweather']['key'] : ($config->upload->openweather->key ?? null);
        $config->upload->openweather->url = (isset($_POST['upload']['openweather']['url'])) ? $_POST['upload']['openweather']['url'] : ($config->upload->openweather->url ?? 'https://api.openweathermap.org/data/3.0/measurements');

        // Generic
        $config->upload->generic->enabled = isset($_POST['upload']['generic']['enabled']) ? (bool)$_POST['upload']['generic']['enabled'] : ($config->upload->generic->enabled ?? false);
        $config->upload->generic->id = (isset($_POST['upload']['generic']['id'])) ? $_POST['upload']['generic']['id'] : ($config->upload->generic->id ?? null);
        $config->upload->generic->password = (isset($_POST['upload']['generic']['password'])) ? $_POST['upload']['generic']['password'] : ($config->upload->generic->password ?? null);
        $config->upload->generic->url = (isset($_POST['upload']['generic']['url'])) ? $_POST['upload']['generic']['url'] : ($config->upload->generic->url ?? null);

        // MyAcuRite
        $config->upload->myacurite->access_enabled = isset($_POST['upload']['myacurite']['access_enabled']) ? (bool)$_POST['upload']['myacurite']['access_enabled'] : ($config->upload->myacurite->access_enabled ?? false);
        $config->upload->myacurite->access_url = isset($_POST['upload']['myacurite']['access_url']) ? $_POST['upload']['myacurite']['access_url'] : ($config->upload->myacurite->access_url ?? null);
        $config->upload->myacurite->pass_unknown = isset($_POST['upload']['myacurite']['pass_unknown']) ? (bool)$_POST['upload']['myacurite']['pass_unknown'] : ($config->upload->myacurite->pass_unknown ?? false);

        // MQTT
        $config->upload->mqtt->enabled = isset($_POST['upload']['mqtt']['enabled']) ? (bool)$_POST['upload']['mqtt']['enabled'] : ($config->upload->mqtt->enabled ?? false);
        $config->upload->mqtt->client = (isset($_POST['upload']['mqtt']['client'])) ? $_POST['upload']['mqtt']['client'] : ($config->upload->mqtt->client ?? "weatherparse");
        $config->upload->mqtt->server = (isset($_POST['upload']['mqtt']['server'])) ? $_POST['upload']['mqtt']['server'] : ($config->upload->mqtt->server ?? null);
        $config->upload->mqtt->port = (isset($_POST['upload']['mqtt']['port'])) ? $_POST['upload']['mqtt']['port'] : ($config->upload->mqtt->port ?? 1883);
        $config->upload->mqtt->topic = (isset($_POST['upload']['mqtt']['topic'])) ? $_POST['upload']['mqtt']['topic'] : ($config->upload->mqtt->topic ?? "weatherparse");
        $config->upload->mqtt->username = (isset($_POST['upload']['mqtt']['username'])) ? $_POST['upload']['mqtt']['username'] : ($config->upload->mqtt->username ?? null);
        $config->upload->mqtt->password = (isset($_POST['upload']['mqtt']['password'])) ? $_POST['upload']['mqtt']['password'] : ($config->upload->mqtt->password ?? null);

        // Debug
        $config->debug->logging = isset($_POST['debug']['logging']) ? (bool)$_POST['debug']['logging'] : ($config->debug->logging ?? false);
        $config->debug->server->enabled = (isset($_POST['debug']['server']['enabled'])) ? (bool)$_POST['debug']['server']['enabled'] : ($config->debug->server->enabled ?? 0);
        $config->debug->server->url = (isset($_POST['debug']['server']['url'])) ? $_POST['debug']['server']['url'] : ($config->debug->server->url ?? null);

        // Save the config file
        $export = var_export($config, true);
        $export = str_ireplace('stdClass::__set_state', '(object)', $export);
        $save = file_put_contents(APP_BASE_PATH . '/usr/config.php', '<?php return ' . $export . ';');
        if ($save !== false) {
            // Log it
            syslog(LOG_INFO, "(SYSTEM){CONFIG}: Site configuration saved successfully");
            $_SESSION['messages'] = '<div class="alert alert-success alert-dismissible"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>Configuration saved successfully!</div>';
        } else {
            // Log it
            syslog(LOG_ERR, "(SYSTEM){CONFIG}[ERROR]: Saving configuration failed");
            $_SESSION['messages'] = '<div class="alert alert-danger alert-dismissible"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>Saving configuration failed!</div>';
        }
        header("Location: /admin");
        exit();
    } // Show the change form
    else {
        $pageTitle = 'Global System Configuration';
        include(APP_BASE_PATH . '/inc/header.php');
        ?>

        <div class="row">
            <div class="col">
                <h2 class="page-header">Global System Configuration</h2>
            </div>
        </div>

        <hr>

        <section id="modify-settings" class="row modify-settings">
            <div class="col">
                <nav>
                    <div class="nav nav-tabs justify-content-center" id="nav-tab" role="tablist">
                        <a class="nav-item nav-link active" id="nav-site-tab" data-bs-toggle="tab" href="#nav-site"
                           role="tab" aria-controls="nav-site" aria-selected="true">Site
                        </a>
                        <a class="nav-item nav-link" id="nav-sensor-tab" data-bs-toggle="tab" href="#nav-sensor"
                           role="tab" aria-controls="nav-sensor" aria-selected="false">Sensor
                        </a>
                        <a class="nav-item nav-link" id="nav-features-tab" data-bs-toggle="tab"
                           href="#nav-features" role="tab" aria-controls="nav-features" aria-selected="false">
                            Features
                        </a>
                        <a class="nav-item nav-link" id="nav-upload-tab" data-bs-toggle="tab" href="#nav-upload"
                           role="tab" aria-controls="nav-upload" aria-selected="false">Upload
                        </a>
                        <a class="nav-item nav-link" id="nav-database-tab" data-bs-toggle="tab"
                           href="#nav-database"
                           role="tab" aria-controls="nav-database" aria-selected="false">Database
                        </a>
                        <?php if ($config->debug->server->show === true) { ?>
                            <a class="nav-item nav-link" id="nav-debug-tab" data-bs-toggle="tab" href="#nav-debug"
                               role="tab" aria-controls="nav-debug" aria-selected="false">Debug
                            </a>
                        <?php } ?>
                    </div>
                </nav>

                <form action="/admin/settings?do" method="POST">
                    <!-- Content Tabs-->
                    <div class="tab-content mt-3" id="nav-tabContent">

                        <!-- Site Settings -->
                        <?php require(APP_BASE_PATH . '/fcn/settings/site.php'); ?>
                        <!-- END: Site Settings -->

                        <!-- Sensor Settings -->
                        <?php require(APP_BASE_PATH . '/fcn/settings/sensor.php'); ?>
                        <!-- END: Sensor Settings -->

                        <!-- Feature Settings -->
                        <?php require(APP_BASE_PATH . '/fcn/settings/features.php'); ?>
                        <!-- END: Feature Settings -->

                        <!-- Upload Settings -->
                        <?php require(APP_BASE_PATH . '/fcn/settings/upload.php'); ?>
                        <!-- END: Upload Settings -->

                        <!-- Database Settings -->
                        <?php require(APP_BASE_PATH . '/fcn/settings/database.php'); ?>
                        <!-- END: Database Settings -->

                        <?php if ($config->debug->server->show === true) { ?>
                            <!-- Debug Server Settings -->
                            <?php require(APP_BASE_PATH . '/fcn/settings/debug.php'); ?>
                            <!-- END: Debug Settings -->
                        <?php } ?>
                    </div>

                    <hr class="hr-dotted">

                    <div class="row">
                        <div class="col">
                            <button type="submit" id="submit" value="submit" class="btn btn-success"><i
                                        class="fas fa-save" aria-hidden="true"></i> Save Settings
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
        <?php
        include(APP_BASE_PATH . '/inc/footer.php');
    }
} // Not logged in or user is not an admin
else {
    header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
    header("Location: /admin/account");
}
