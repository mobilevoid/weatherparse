WEATHERPARSE
============

https://github.com/mobilevoid/weatherparse

AcuRite Access/smartHUB and IP Camera Data Processing, Display, and Upload.

Notice: This program is open source 3rd party software. It is neither written nor supported by AcuRite.

WeatherParse is a maintained fork of Acuparse (https://github.com/acuparse/acuparse) by Maxwell Power, whose last
release was 3.9.4 (July 2023). It keeps everything Acuparse does and adds:

- Support for current Debian/Raspberry Pi OS releases (Debian 12 and 13) and PHP 8.2 to 8.4.
- No telemetry. The optional update check only reads the public release list of this repository on GitHub and sends
  nothing about your installation.
- Camera image retention (KEEP_DAYS), so the archive cannot fill your SD card.
- Fixes for AcuRite Access firmware that ignores the DNS server handed out by DHCP.

How it Works
============

WeatherParse is a PHP/MySQL program that captures, stores, and displays weather data from an AcuRite
Iris (5-in-1) or Atlas (7-in-1) weather station and tower sensors, via your Access/smartHUB. It uploads weather data to
Weather Underground (https://www.wunderground.com), CWOP (http://www.wxqa.com), Weathercloud (https://weathercloud.net),
PWS Weather (https://www.pwsweather.com), Windy (https://www.windy.com), Windguru (https://www.windguru.cz),
OpenWeather (https://openweathermap.org/), and MQTT (https://mqtt.org/) Brokers.

It also processes and stores images from a local network camera for display and external linking.

Built for weather geeks and designed to be clean, simple, and mobile friendly. It uses a minimal UI with a focus on data,
not flashy graphics.

WeatherParse requires a working AcuRite Access/smartHUB. You redirect weather data from your Access/smartHUB to your
WeatherParse server. It is captured, stored, and then passed along to MyAcuRite untouched.
The response received from MyAcuRite is sent back to your Access/smartHUB. If sending data to MyAcuRite is disabled or
when using a smartHUB, WeatherParse creates the response.

Direct to WiFi Consoles
-----------------------

WeatherParse does not directly support the AcuRite direct to WiFi Consoles. You may have success configuring these as
an Access & Atlas/Iris sensor. Keep in mind, they do not support Tower sensors and they have not been tested.

Features
========

- Your weather data belongs to you, stored on your OWN server.
- Display live camera images, and link from other sites like Weathercloud.
- Uploads weather data from your Iris/Atlas and Tower sensors to external providers.
- Publish weather data to an MQTT Broker Server.
- Customizable barometer readings.
- Southern Hemisphere Wind Direction Support.
- Light and Dark Themes.
- Display data in both &#8457; and &#8451;. Selectable default.
- Multiple tower sensors; public or private.
- Multiple admin or regular user accounts.
    - Regular users can only view private sensor data.
- Archived data display.
- Watch RAW data flow using the systems syslog.
- Stays online even when MyAcuRite is not.
    - Does not require Internet access. Can be deployed independently of MyAcuRite.
- Export API with JSON and formatted HTML output for use in external applications.
- Customizable email outage notifications with Mailgun support.
- Low battery email alerts for the Access hub, Atlas, Iris and tower sensors, with reminders and an all clear.
- Optional Matomo and Google Analytics support as well as Google reCAPTCHA form protection. All are off by default.

What's Missing
==============

- Advanced Data Reporting.
    - Reports can be run against the database. phpMyAdmin is an excellent tool for advanced manual reporting.
- Charts and Graphing.
    - Since this is available at most external weather sites.
- Multiple Access/smartHUB/Iris/Atlas sensors.
    - The current framework is built around a single Access/smartHUB and Iris/Atlas sensor.

Installation
============

Note: If you are not using an Access, you will need to setup a DNS redirect using a DNS server on your local network.

Access Users: The AcuRite Access sends data to MyAcuRite using an SSL connection. By Default Apache will use the
snake oil cert to serve over HTTPS. For most users, this should be sufficient.

See docs/INSTALL.txt for detailed installation instructions.

Installing on a fresh instance of Debian 12 (bookworm) or 13 (trixie), including Raspberry Pi OS based on them, is the
supported install method.

After installing and adding your sensors, you may receive a "No Data Received!" message on your dashboard.
WeatherParse will need to receive readings from your Access/Hub before weather data can be displayed.
See Initial Readings (docs/INSTALL.txt#initial-readings) for more details.

Updating
========

See docs/UPDATING.txt for detailed update instructions.

Release Notes
-------------

See CHANGELOG.txt for detailed release notes.

Additional Outputs
==================

The primary user interface uses AJAX to pull the most recent HTML formatted data every minute.

WeatherParse includes a Display mode for better viewing while in full-screen.

- Display Mode: http(s)://<yourip/domain>/display
    - Force light theme: http(s)://<yourip/domain>/display?light
    - Force dark theme: http(s)://<yourip/domain>/display?dark

Additionally, you can request Bootstrap 5 formatted HTML, JSON array(s), or plain text formatted for watermarking.

- See the API Guide (docs/API.txt) for details.

Troubleshooting
===============

See docs/TROUBLESHOOTING.txt for common troubleshooting steps.

Licencing
=========

WeatherParse is open-source software, released under the AGPL-3.0+ license. It is based on Acuparse,
Copyright (C) 2015-2023 Maxwell Power, also AGPL-3.0+. It also uses several other open source scripts.
Their licences are included where available.

Included 3rd party scripts are located in src/pub/lib.

See LICENSE for more details.

Support
=======

Bugs and feature requests are handled via GitHub Issues (https://github.com/mobilevoid/weatherparse/issues).

Contributing
============

See CONTRIBUTING.txt for more details.
