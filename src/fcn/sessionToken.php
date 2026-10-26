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
 * File: src/fcn/sessionToken.php
 * Create a session token
 */

/**
 * @var mysqli $conn Global MYSQL Connection
 * @var string $uid User ID
 */

// Generate the device key and token for this session
// Cryptographically random; the browser keeps the token, the database only its hash
$deviceKey = bin2hex(random_bytes(20));
$token = bin2hex(random_bytes(20));
$tokenHash = sha1($token);
$userAgent = mysqli_real_escape_string($conn, substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255));

// Save the session to the database
$result = mysqli_query($conn,
    "INSERT INTO `sessions` (`uid`, `device_key`, `token`, `user_agent`) VALUES ('$uid', '$deviceKey', '$tokenHash', '$userAgent')");
if (!$result) {
    // Log it
    syslog(LOG_ERR, "(SYSTEM){USER}[ERROR]: Saving session failed! Raw = " . mysqli_error($conn));
}

// Send the session cookie
$cookieOptions = [
    'expires' => time() + 60 * 60 * 24 * 30,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
];
setcookie('device', $deviceKey, $cookieOptions);
setcookie('token', $token, $cookieOptions);
