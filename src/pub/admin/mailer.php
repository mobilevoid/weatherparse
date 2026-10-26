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
 * File: src/pub/admin/mailer.php
 * Send a test email
 */

// Get the loader
require(dirname(__DIR__, 2) . '/inc/loader.php');
require(APP_BASE_PATH . '/fcn/mailer.php');

/**
 * @var mysqli $conn Global MYSQL Connection
 * @var object $config Global Config
 */

if (isset($_SESSION['authenticated']) && $_SESSION['admin'] === true) {

    $uid = $_SESSION['uid'];
    $email = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT `email` FROM `users` WHERE `uid` = '$uid'"));
    $email = $email['email'];
    $subject = 'WeatherParse Test Email';
    $message = '<h2>WeatherParse Test Email</h2><p>This is a test email from your WeatherParse System.</p>';

    // Mail it
    $testEmail = mailer($email, $subject, $message);
    $mgStatus = ($config->mailgun->enabled === true) ? 'Mailgun: ' . ($testEmail['message'] ?? 'no reply') : false;
    // Mailgun always returns its reply (also on errors); it only accepted the mail when there is a message id
    $testSent = ($config->mailgun->enabled === true) ? !empty($testEmail['id']) : (bool)$testEmail;

    if (!$testSent) {
        // Log it
        syslog(LOG_INFO, "(SYSTEM)[ERROR]: Error sending test email to $email");
        // Display message
        $_SESSION['messages'] = '<div class="alert alert-warning alert-dismissible"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>Sending Test Email Failed! ' . $mgStatus . '</div>';
    } else {
        // Log it
        syslog(LOG_INFO, "(SYSTEM): Sent test email to $email");
        // Display message
        $_SESSION['messages'] = '<div class="alert alert-success alert-dismissible"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>Test Email Sent Successfully! ' . $mgStatus . '</div>';
    }

    header("Location: /admin");
}
