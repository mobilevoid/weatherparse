##
# WeatherParse - AcuRite Access/smartHUB and IP Camera Data Processing, Display, and Upload.
# @copyright Copyright (C) 2026 WeatherParse contributors
# @link https://github.com/mobilevoid/weatherparse
# @license AGPL-3.0+
#
# Based on Acuparse, Copyright (C) 2015-2023 Maxwell Power. Modified for WeatherParse in 2026.
#
# This code is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as published
# by the Free Software Foundation, either version 3 of the License, or
# any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License
# along with this code. If not, see <http://www.gnu.org/licenses/>.
##

##
# File: Dockerfile
# WeatherParse Dockerfile
##

ARG OS_VERSION=trixie
FROM php:8.4-apache-${OS_VERSION}

ARG BUILD_DATE
ARG VCS_REF
ARG BUILD_VERSION
ARG BUILD_BRANCH
ARG WEATHERPARSE_DIR='/opt/weatherparse'

LABEL org.label-schema.schema-version="1.0" \
org.label-schema.build-date=${BUILD_DATE} \
org.label-schema.name="mobilevoid/weatherparse" \
org.label-schema.description="WeatherParse Weather Data Processing" \
org.label-schema.url="https://github.com/mobilevoid/weatherparse" \
org.label-schema.vcs-url="https://github.com/mobilevoid/weatherparse" \
org.label-schema.vcs-ref=${VCS_REF} \
org.label-schema.version=${BUILD_VERSION} \
org.label-schema.usage="https://github.com/mobilevoid/weatherparse/blob/main/docs/DOCKER.txt" \
org.label-schema.vendor="WeatherParse"

ENV DEBIAN_FRONTEND=noninteractive

RUN echo "Install and Configure PACKAGES" \
&& apt-get update -qq && apt-get install -yqq --no-install-recommends \
mariadb-client \
rsyslog \
python3-certbot-apache \
cron \
ffmpeg \
imagemagick \
fonts-dejavu-core \
wget \
ca-certificates \
procps \
&& docker-php-ext-install mysqli \
&& docker-php-ext-enable mysqli \
&& apt-get autoremove --purge -yqq \
&& apt-get clean \
&& rm -rf /var/lib/apt/lists/*

COPY . "${WEATHERPARSE_DIR}"
WORKDIR "${WEATHERPARSE_DIR}"

RUN echo "Copy and run WeatherParse BUILD" \
&& mv .docker/* /usr/local/bin/ \
&& rm -rf .docker \
&& chmod +x /usr/local/bin/docker-build \
&& docker-build \
&& rm -rf /usr/local/bin/docker-build

ENTRYPOINT ["docker-entrypoint"]
CMD ["weatherparse"]

EXPOSE 80 443
