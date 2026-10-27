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
 * File: src/inc/loader.php
 * Loads the config, sets up database connection and session
 */

ini_set('display_errors', 0);

if (!defined('APP_BASE_PATH')) {
    define('APP_BASE_PATH', dirname(__DIR__));
}

// Load config
if (!isset($config)) {
    if (file_exists(APP_BASE_PATH . '/usr/config.php')) {
        $config = require(APP_BASE_PATH . '/usr/config.php');
        $installed = true;
        if ($config->debug->logging === true) {
            error_reporting(E_ALL);
        } else {
            error_reporting(0);
        }
    } else {
        $config = require(APP_BASE_PATH . '/usr/config.new.php');
        $installed = false;
    }
}

if (!isset($appInfo)) {
    $appInfo = json_decode(file_get_contents(dirname(__DIR__, 2) . '/.version'), true);
    $appInfo = array_change_key_case($appInfo);
    $appInfo = (object)$appInfo;
}

if (!function_exists('mysqlClientCommand')) {
    /**
     * Build a mysql command-line client call. Host, user and database are shell-quoted and the password goes in the
     * environment (MYSQL_PWD), so it never appears on the command line or in the process list.
     * @param object $mysql The mysql section of the config
     * @param string $args Extra arguments, already shell-quoted by the caller
     */
    function mysqlClientCommand(object $mysql, string $args): string
    {
        putenv('MYSQL_PWD=' . $mysql->password);
        return 'mysql -h' . escapeshellarg($mysql->host) . ' -u' . escapeshellarg($mysql->username) . ' ' . $args;
    }
}

if (!function_exists('settingsSecretFields')) {
    /**
     * Settings that are passwords, keys or secrets. The settings form shows them escaped and saves them exactly as
     * typed (no tag stripping or quote encoding), so a password with " < > & ' survives a save unchanged.
     * @return array[] Paths into the submitted settings form / config object
     */
    function settingsSecretFields(): array
    {
        return [
            ['mysql', 'password'],
            ['google', 'recaptcha', 'secret'],
            ['mailgun', 'secret'],
            ['upload', 'wu', 'password'],
            ['upload', 'pws', 'key'],
            ['upload', 'wc', 'key'],
            ['upload', 'windy', 'key'],
            ['upload', 'windguru', 'password'],
            ['upload', 'openweather', 'key'],
            ['upload', 'generic', 'password'],
            ['upload', 'mqtt', 'username'],
            ['upload', 'mqtt', 'password'],
        ];
    }

    /**
     * Puts the raw submitted value of every secret setting back into a sanitized form array.
     * @param array $sanitized The form after legacySanitizeArray()
     * @param array $raw The form as submitted
     */
    function settingsRestoreSecrets(array $sanitized, array $raw): array
    {
        foreach (settingsSecretFields() as $path) {
            $value = $raw;
            foreach ($path as $key) {
                if (!is_array($value) || !array_key_exists($key, $value)) {
                    continue 2;
                }
                $value = $value[$key];
            }
            if (!is_string($value)) {
                continue;
            }
            $target = &$sanitized;
            foreach ($path as $key) {
                if (!isset($target[$key]) || !is_array($target[$key])) {
                    $target[$key] = [];
                }
                $target = &$target[$key];
            }
            $target = $value;
            unset($target);
        }
        return $sanitized;
    }

    /**
     * A config value for an HTML value="" attribute. Ordinary settings were stored with quotes as entities (&#39;),
     * which are left as they are so they show as the character and save back the same. Secrets are stored raw, so
     * every character is escaped.
     * @param mixed $value The setting
     * @param bool $secret True for a password/key (see settingsSecretFields)
     */
    function settingsFormValue($value, bool $secret = false): string
    {
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $secret);
    }
}

if (!function_exists('legacySanitizeString')) {
    /**
     * What FILTER_SANITIZE_STRING did (deprecated in PHP 8.1): strip tags and encode quotes.
     * Used with FILTER_CALLBACK so input handling stays exactly as before.
     */
    function legacySanitizeString($value)
    {
        if (!is_string($value)) {
            return $value;
        }
        return str_replace(["'", '"'], ['&#39;', '&#34;'], strip_tags($value));
    }

    /**
     * legacySanitizeString() applied to every value of a (nested) array, e.g. a whole submitted form.
     */
    function legacySanitizeArray(array $values): array
    {
        array_walk_recursive($values, function (&$value) {
            $value = legacySanitizeString($value);
        });
        return $values;
    }
}

// Set timezone
if (date_default_timezone_get() != $config->site->timezone) {
    date_default_timezone_set($config->site->timezone);
}

// Open Database Connection
if (!isset($conn)) {
    /**
     * @var boolean $installed
     */
    if ($installed === true) {
        ini_set('mysqlnd_qc.enable_qc', 1);
        ini_set('mysqlnd_qc.cache_by_default', 1);

        // PHP 8.1+ throws on every SQL error; WeatherParse checks results itself, as on PHP 7.
        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = mysqli_connect($config->mysql->host, $config->mysql->username, $config->mysql->password,
            $config->mysql->database);
        if (!$conn) {
            header($_SERVER["SERVER_PROTOCOL"] . " 503 Service Unavailable");
            require_once('templates/dbConnectFailed.php');
            syslog(LOG_ERR, "(SYSTEM){LOADER}[ERROR]: MySQL Connection failed: " . mysqli_connect_error());
            exit();
        }
    }
}

// Start Logging
if (!isset($openlog)) {
    $facility = (php_uname("s") === 'Linux') ? LOG_LOCAL0 : LOG_USER;
    $openLog = openlog("$appInfo->name($appInfo->version)", LOG_ODELAY, $facility);
}

// Start the session
if (!isset($_SESSION)) {
    include(APP_BASE_PATH . '/inc/session.php');
}

/*
 * Cross-site request protection. A browser sends Origin (form posts) and Referer (links) naming the page a request came
 * from. Refuse form posts from another site, and admin links that change something (delete, token, do, arrange) unless
 * they come from a page of this site. The Access/smartHUB uploads and API clients send neither header and are not
 * affected. Runs once per request (the loader is included by many files).
 */
if (PHP_SAPI !== 'cli' && !defined('WEATHERPARSE_ORIGIN_CHECKED')) {
    define('WEATHERPARSE_ORIGIN_CHECKED', true);
    $ownHosts = array_filter([
        strtolower((string)($_SERVER['HTTP_HOST'] ?? '')),
        strtolower((string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? '')),
    ]);
    $fromHost = function (string $url): string {
        $p = parse_url($url);
        return isset($p['host']) ? strtolower($p['host'] . (isset($p['port']) ? ':' . $p['port'] : '')) : '';
    };
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
    $path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $isUpload = str_starts_with($path, '/weatherstation');
    $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    $isAdminChange = str_starts_with($path, '/admin') && array_intersect(array_keys($_GET), ['delete', 'token', 'do', 'arrange']);

    $refused = false;
    if (!$isUpload && $isPost && ($origin !== '' || $referer !== '')) {
        $from = $origin !== '' && $origin !== 'null' ? $fromHost($origin) : $fromHost($referer);
        $refused = !in_array($from, $ownHosts, true);
    } elseif (!$isUpload && !$isPost && $isAdminChange) {
        $refused = $referer === '' || !in_array($fromHost($referer), $ownHosts, true);
    }
    if ($refused) {
        syslog(LOG_WARNING, "(SYSTEM)[SECURITY]: Refused cross-site request to $path");
        header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
        exit('Request refused: it did not come from this site.');
    }
}

// Output buffering
if (!isset($obStart)) {
    $obStart = ob_start();
    header('Cache-Control: no-cache');
    header('X-Content-Type-Options: nosniff');
    header_remove('Pragma');
    header_remove('Expires');
}
