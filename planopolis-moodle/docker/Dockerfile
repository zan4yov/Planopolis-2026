# Planopolis PWK 2026 – Moodle quiz platform
FROM php:8.3-apache

ARG MOODLE_BRANCH=MOODLE_502_STABLE

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libicu-dev libzip-dev libpq-dev libxml2-dev \
        postgresql-client curl ca-certificates unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd intl zip pgsql pdo_pgsql soap opcache exif \
    && rm -rf /var/lib/apt/lists/*

# Moodle code (5.2 stable branch). Moodle 5.1+ serves only the public/ folder.
RUN curl -fsSL "https://github.com/moodle/moodle/archive/refs/heads/${MOODLE_BRANCH}.tar.gz" -o /tmp/moodle.tgz \
    && mkdir -p /var/www/moodle && tar -xzf /tmp/moodle.tgz -C /var/www/moodle --strip-components=1 \
    && rm /tmp/moodle.tgz

# Planopolis plugin.
COPY plugin/local_planopolis /var/www/moodle/public/local/planopolis

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-moodle.ini
COPY docker/apache-moodle.conf /etc/apache2/sites-available/000-default.conf
COPY docker/apache-mpm.conf /etc/apache2/conf-enabled/zz-mpm.conf
COPY docker/entrypoint.sh /usr/local/bin/moodle-entrypoint
RUN chmod +x /usr/local/bin/moodle-entrypoint \
    && a2enmod rewrite headers \
    && mkdir -p /var/moodledata && chown -R www-data:www-data /var/moodledata /var/www/moodle

VOLUME ["/var/moodledata"]
EXPOSE 80
ENTRYPOINT ["moodle-entrypoint"]
CMD ["web"]
