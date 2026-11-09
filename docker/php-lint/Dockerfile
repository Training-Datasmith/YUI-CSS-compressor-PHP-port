FROM php@sha256:5249d94b4b00db63a0ed53e6528114926110ab5dcb0d0f07cc30d1ea7dbe747e

ENV DEBIAN_FRONTEND=noninteractive
ENV PHP_VERSION=5.3.29
ENV PHP_PREFIX=/opt/php-5.3.29
ENV PHP_TARBALL_SHA256=c4e1cf6972b2a9c7f2777a18497d83bf713cdbecabb65d3ff62ba441aebb0091

RUN set -eux; \
    printf '%s\n' \
        'deb https://archive.debian.org/debian buster main' \
        > /etc/apt/sources.list; \
    printf '%s\n' 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99no-check-valid-until; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        bzip2 \
        build-essential \
        autoconf \
        libxml2-dev \
    ; \
    curl -fsSL -o /tmp/php.tar.bz2 "https://www.php.net/distributions/php-${PHP_VERSION}.tar.bz2"; \
    echo "${PHP_TARBALL_SHA256}  /tmp/php.tar.bz2" | sha256sum -c -; \
    mkdir -p /usr/src; \
    tar -xjf /tmp/php.tar.bz2 -C /usr/src; \
    cd "/usr/src/php-${PHP_VERSION}"; \
    ./configure --prefix="${PHP_PREFIX}" --disable-all --enable-cli; \
    make -j"$(nproc)"; \
    make install; \
    "${PHP_PREFIX}/bin/php" -v | grep '^PHP 5.3.29'; \
    rm -rf "/usr/src/php-${PHP_VERSION}" /tmp/php.tar.bz2; \
    apt-get purge -y --auto-remove build-essential autoconf bzip2; \
    rm -rf /var/lib/apt/lists/*

ENV PATH="${PHP_PREFIX}/bin:${PATH}"

WORKDIR /app
