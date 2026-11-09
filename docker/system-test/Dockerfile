FROM php@sha256:5249d94b4b00db63a0ed53e6528114926110ab5dcb0d0f07cc30d1ea7dbe747e

ENV DEBIAN_FRONTEND=noninteractive

RUN set -eux; \
    printf '%s\n' \
        'deb https://archive.debian.org/debian buster main' \
        > /etc/apt/sources.list; \
    printf '%s\n' 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99no-check-valid-until; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        apt-transport-https \
        curl \
        git \
        unzip \
    ; \
    rm -rf /var/lib/apt/lists/*

WORKDIR /app
