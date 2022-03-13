FROM php:8.1.1-fpm-alpine3.15

COPY --from=composer:2.2.4 /usr/bin/composer /usr/bin/composer

RUN apk --no-cache --virtual .opcache add --update \
&& NPROC=$(grep -c ^processor /proc/cpuinfo 2>/dev/null || 1) \
&& docker-php-ext-install -j${NPROC} opcache \
&& apk del .opcache

RUN apk --no-cache --virtual .xdebug add --update autoconf build-base \
&& pecl install xdebug-3.1.2 && docker-php-ext-enable xdebug \
&& apk del .xdebug

RUN apk --no-cache --virtual .dbgpProxy add --update wget \
&& apk --no-cache add libc6-compat \
&& wget -O /usr/bin/dbgpProxy https://xdebug.org/files/binaries/dbgpProxy \
&& chmod 755 /usr/bin/dbgpProxy \
&& apk del .dbgpProxy

RUN apk --no-cache --virtual .kafka add --update autoconf build-base make git bash libpthread-stubs linux-headers python3 zstd-dev zstd-static librdkafka-dev zstd-libs \
&& apk --no-cache add libsasl \
&& git clone --branch v1.6.2 --depth 1 https://github.com/edenhill/librdkafka.git /tmp/librdkafka \
&& cd /tmp/librdkafka \
&& ./configure --install-deps --source-deps-only \
&& make \
&& make install \
&& rm -rf /tmp/librdkafka \
&& pecl install rdkafka-6.0.0 && docker-php-ext-enable rdkafka \
&& apk del .kafka

RUN apk --no-cache --virtual .openswoole add --update autoconf build-base libressl-dev \
&& apk --no-cache add libstdc++ \
&& pecl install openswoole-4.10.0 && docker-php-ext-enable openswoole \
&& apk del .openswoole

RUN apk --no-cache --virtual .pcntl add --update \
&& NPROC=$(grep -c ^processor /proc/cpuinfo 2>/dev/null || 1) \
&& docker-php-ext-install -j${NPROC} pcntl \
&& apk del .pcntl

RUN apk --no-cache --virtual .supervisord add --update \
&& apk --no-cache add supervisor \
&& touch /etc/supervisord.conf \
&& echo "[supervisord]" >> /etc/supervisord.conf \
&& echo "nodaemon=true" >> /etc/supervisord.conf \
&& apk del .supervisord

RUN apk --no-cache --virtual .redis add --update autoconf build-base \
&& pecl install redis && docker-php-ext-enable redis \
&& apk del .redis

ARG UNAME=udocker
ARG UID=1000
ARG GNAME=$UNAME
ARG GID=1000
ARG GROUPS=$GNAME

RUN addgroup -S $GNAME --gid $GID \
&& adduser -S $UNAME -G $GNAME --uid $UID
USER $UNAME
WORKDIR /var/www/html

# php-fpm port
EXPOSE 9000

# dbgpProxy port
EXPOSE 9001

# xdebug 3 port
EXPOSE 9003

ENTRYPOINT ["/bin/sh", "-c", "php-fpm & supervisord --configuration /etc/supervisord.conf"]
