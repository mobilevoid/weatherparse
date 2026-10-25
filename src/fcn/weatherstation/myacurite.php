<?php
/**
 * WeatherParse - AcuRite Access/smartHUB and IP Camera Data Processing, Display, and Upload.
 * @copyright Copyright (C) 2026 WeatherParse contributors
 * @link https://github.com/mobilevoid/weatherparse
 * @license AGPL-3.0+
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
 * File: src/fcn/weatherstation/myacurite.php
 * Forward Access/smartHUB readings to MyAcuRite.
 *
 * The usual setup redirects the AcuRite API hostname to this server (an /etc/hosts entry or a DNS override), which is
 * how the readings arrive here in the first place. Forwarding by name would then loop back to this server. So the
 * forwarder asks DNS directly for the real address (dns_get_record does not read /etc/hosts), refuses any answer that
 * points at this server, and connects to that address while keeping the real hostname for TLS.
 */

/**
 * Addresses that belong to this server: loopback, the address the request arrived on, and the host's own addresses.
 * @return string[]
 */
function myacuriteLocalAddresses(): array
{
    $local = ['127.0.0.1', '::1'];
    if (!empty($_SERVER['SERVER_ADDR'])) {
        $local[] = $_SERVER['SERVER_ADDR'];
    }
    $own = @gethostbynamel(gethostname());
    if (is_array($own)) {
        $local = array_merge($local, $own);
    }
    $all = @shell_exec('hostname -I 2>/dev/null');
    if (is_string($all)) {
        $local = array_merge($local, preg_split('/\s+/', trim($all), -1, PREG_SPLIT_NO_EMPTY));
    }
    return array_unique($local);
}

/**
 * Public IPv4 address of a hostname from DNS, never from /etc/hosts, and never one of this server's own addresses.
 * @return string|null null when DNS has no usable answer
 */
function myacuriteResolve(string $host): ?string
{
    $records = @dns_get_record($host, DNS_A);
    if (!is_array($records)) {
        return null;
    }
    $local = myacuriteLocalAddresses();
    foreach ($records as $record) {
        $ip = $record['ip'] ?? '';
        if ($ip === '' || in_array($ip, $local, true) || str_starts_with($ip, '127.')) {
            continue;
        }
        return $ip;
    }
    return null;
}

/**
 * POST the raw reading to MyAcuRite and return its response body.
 * @param string $baseUrl e.g. https://atlasapi.myacurite.com
 * @param string $pathAndQuery e.g. /weatherstation/updateweatherstation?&id=...
 * @param string $postBody the original request body from the Access
 * @param string $userAgent the original User-Agent from the Access
 * @return string|false false when MyAcuRite cannot be reached; the caller then answers the Access itself
 */
function myacuriteForward(string $baseUrl, string $pathAndQuery, string $postBody, string $userAgent): string|false
{
    $parts = parse_url($baseUrl);
    $host = $parts['host'] ?? '';
    if ($host === '') {
        syslog(LOG_ERR, "(ACCESS){MyAcuRite}[ERROR]: Invalid MyAcuRite URL $baseUrl");
        return false;
    }
    $scheme = $parts['scheme'] ?? 'https';
    $port = $parts['port'] ?? ($scheme === 'http' ? 80 : 443);

    $ip = myacuriteResolve($host);
    if ($ip === null) {
        syslog(LOG_WARNING, "(ACCESS){MyAcuRite}[WARN]: $host has no public DNS answer (or only points at this server); not forwarding");
        return false;
    }

    $ch = curl_init($scheme . '://' . $host . ':' . $port . $pathAndQuery);
    curl_setopt_array($ch, [
        CURLOPT_RESOLVE => ["$host:$port:$ip"],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postBody,
        CURLOPT_USERAGENT => $userAgent,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        syslog(LOG_WARNING, "(ACCESS){MyAcuRite}[WARN]: Forward to $host ($ip) failed: HTTP $status $error");
        return false;
    }
    return $response;
}
