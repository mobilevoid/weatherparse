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
 * forwarder looks the name up in DNS, refuses any answer that points at this server, and connects to that address
 * while keeping the real hostname for TLS. When the server's own resolver answers with this server (for example a
 * local dnsmasq that reads /etc/hosts), it asks public resolvers (1.1.1.1, 8.8.8.8) directly.
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
 * IPv4 addresses for a hostname from one DNS server, by a plain UDP query (RFC 1035), so no local resolver,
 * /etc/hosts or DNS override is involved.
 * @return string[]
 */
function myacuriteDnsQuery(string $host, string $server, float $timeout = 3.0): array
{
    $id = random_int(0, 0xFFFF);
    $packet = pack('nnnnnn', $id, 0x0100, 1, 0, 0, 0);
    foreach (explode('.', trim($host, '.')) as $label) {
        if ($label === '' || strlen($label) > 63) {
            return [];
        }
        $packet .= chr(strlen($label)) . $label;
    }
    $packet .= chr(0) . pack('nn', 1, 1); // type A, class IN

    $sock = @fsockopen('udp://' . $server, 53, $errno, $errstr, $timeout);
    if (!$sock) {
        return [];
    }
    stream_set_timeout($sock, (int)$timeout, (int)(($timeout - (int)$timeout) * 1e6));
    fwrite($sock, $packet);
    $response = fread($sock, 1500);
    fclose($sock);
    if (!is_string($response) || strlen($response) < 12) {
        return [];
    }
    $header = unpack('nid/nflags/nqd/nan', substr($response, 0, 8));
    if ($header['id'] !== $id || ($header['flags'] & 0x000F) !== 0) {
        return [];
    }
    $pos = 12;
    // Skip the question: name labels (or a pointer), type and class
    for ($q = 0; $q < $header['qd']; $q++) {
        $pos = myacuriteDnsSkipName($response, $pos) + 4;
    }
    $ips = [];
    for ($a = 0; $a < $header['an'] && $pos + 10 <= strlen($response); $a++) {
        $pos = myacuriteDnsSkipName($response, $pos);
        $rr = unpack('ntype/nclass/Nttl/nlen', substr($response, $pos, 10));
        $pos += 10;
        if ($rr['type'] === 1 && $rr['len'] === 4) {
            $ips[] = inet_ntop(substr($response, $pos, 4));
        }
        $pos += $rr['len'];
    }
    return $ips;
}

/** Position after a DNS name (a run of labels ending in 0, or a 2-byte compression pointer). */
function myacuriteDnsSkipName(string $msg, int $pos): int
{
    while ($pos < strlen($msg)) {
        $len = ord($msg[$pos]);
        if ($len === 0) {
            return $pos + 1;
        }
        if (($len & 0xC0) === 0xC0) {
            return $pos + 2;
        }
        $pos += $len + 1;
    }
    return $pos;
}

/**
 * Public IPv4 address of a hostname, never one of this server's own addresses.
 * Tries the system resolver first, then public resolvers directly.
 * @return string|null null when no usable answer
 */
function myacuriteResolve(string $host): ?string
{
    $local = myacuriteLocalAddresses();
    $usable = fn(string $ip) => $ip !== '' && !in_array($ip, $local, true) && !str_starts_with($ip, '127.')
        && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

    $records = @dns_get_record($host, DNS_A);
    foreach (is_array($records) ? $records : [] as $record) {
        if ($usable($record['ip'] ?? '')) {
            return $record['ip'];
        }
    }
    foreach (['1.1.1.1', '8.8.8.8'] as $server) {
        foreach (myacuriteDnsQuery($host, $server) as $ip) {
            if ($usable($ip)) {
                return $ip;
            }
        }
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
        syslog(LOG_WARNING, "(ACCESS){MyAcuRite}[WARN]: $host has no public address in DNS (system resolver and 1.1.1.1/8.8.8.8); not forwarding");
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
