# syntax=docker/dockerfile:1.4

# The different stages of this Dockerfile are meant to be built into separate images
# https://docs.docker.com/develop/develop-images/multistage-build/#stop-at-a-specific-build-stage
# https://docs.docker.com/compose/compose-file/#target

# Prod image
FROM php:8.2-fpm-alpine AS app_php

# Allow to use development versions of Symfony
ARG STABILITY="stable"
ENV STABILITY ${STABILITY}

# Allow to select Symfony version
ARG SYMFONY_VERSION="5.4"
ENV SYMFONY_VERSION ${SYMFONY_VERSION}

ENV APP_ENV=prod

WORKDIR /srv/app

# php extensions installer: https://github.com/mlocati/docker-php-extension-installer
COPY --from=mlocati/php-extension-installer --link /usr/bin/install-php-extensions /usr/local/bin/

# persistent / runtime deps
RUN apk add --no-cache \
		acl \
		fcgi \
		file \
		gettext \
		git \
	;

RUN set -eux; \
    install-php-extensions \
    	intl \
    	zip \
    	opcache \
        gd \
        json \
        mbstring \
        pgsql \
        pdo_pgsql \
        xml \
    ;
#       apcu \

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY --link docker/php/conf.d/app.ini $PHP_INI_DIR/conf.d/
COPY --link docker/php/conf.d/app.prod.ini $PHP_INI_DIR/conf.d/

COPY --link docker/php/php-fpm.d/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf
RUN mkdir -p /var/run/php

COPY --link docker/php/docker-healthcheck.sh /usr/local/bin/docker-healthcheck
RUN chmod +x /usr/local/bin/docker-healthcheck

HEALTHCHECK --interval=10s --timeout=3s --retries=3 CMD ["docker-healthcheck"]

COPY --link docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PATH="${PATH}:/root/.composer/vendor/bin"

COPY --from=composer/composer:2-bin --link /composer /usr/bin/composer

# prevent the reinstallation of vendors at every changes in the source code
COPY composer.* symfony.* ./
RUN set -eux; \
    if [ -f composer.json ]; then \
		composer install --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress; \
		composer clear-cache; \
    fi

# copy sources
COPY --link  . .
RUN rm -Rf docker/

RUN set -eux; \
	mkdir -p var/cache var/log; \
    if [ -f composer.json ]; then \
		composer dump-autoload --classmap-authoritative --no-dev; \
		#composer dump-env prod; \
		composer run-script --no-dev post-install-cmd; \
		chmod +x bin/console; sync; \
    fi

# Dev image
FROM app_php AS app_php_dev

ENV APP_ENV=dev XDEBUG_MODE=off
ARG XDEBUG_VERSION=3.5.3
ARG XDEBUG_SHA256=f073de91bea046106abf4d6071c963ea71e58571df6ce58948ceca89d121cb2d
VOLUME /srv/app/var/

RUN rm $PHP_INI_DIR/conf.d/app.prod.ini; \
	mv "$PHP_INI_DIR/php.ini" "$PHP_INI_DIR/php.ini-production"; \
	mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY --link docker/php/conf.d/app.dev.ini $PHP_INI_DIR/conf.d/

RUN set -eux; \
	apk add --no-cache --virtual .xdebug-build-deps $PHPIZE_DEPS curl linux-headers; \
	curl -fsSL -o /tmp/xdebug.tgz "https://xdebug.org/files/xdebug-${XDEBUG_VERSION}.tgz"; \
	echo "${XDEBUG_SHA256}  /tmp/xdebug.tgz" | sha256sum -c -; \
	mkdir -p /tmp/xdebug; \
	tar -xzf /tmp/xdebug.tgz -C /tmp/xdebug --strip-components=1; \
	cd /tmp/xdebug; \
	phpize; \
	./configure --enable-xdebug; \
	make -j"$(nproc)"; \
	make install; \
	docker-php-ext-enable xdebug; \
	cd /; \
	rm -rf /tmp/xdebug /tmp/xdebug.tgz; \
	apk del .xdebug-build-deps

RUN rm -f .env.local.php

FROM caddy:2.6-builder-alpine AS app_caddy_builder

RUN xcaddy build

# Caddy image
FROM caddy:2.6-alpine AS app_caddy

WORKDIR /srv/app

COPY --from=app_caddy_builder --link /usr/bin/caddy /usr/bin/caddy
COPY --from=app_php --link /srv/app/public public/
COPY --link docker/caddy/Caddyfile /etc/caddy/Caddyfile
