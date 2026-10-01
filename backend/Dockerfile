FROM php:8.2-cli-alpine AS builder
RUN apk add --no-cache git unzip curl
# 避免额外拉取 composer 镜像：直接安装 Composer
RUN curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer config -g repos.packagist composer https://mirrors.aliyun.com/composer/
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader 2>/dev/null || composer install --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --optimize

FROM php:8.2-fpm-alpine
RUN apk add --no-cache nginx \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql gd
COPY --from=builder /app /app
WORKDIR /app
RUN mkdir -p runtime/log runtime/cache public/uploads && chmod -R 777 runtime public/uploads
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/www.conf /usr/local/etc/php-fpm.d/zz-docker.conf
EXPOSE 80
CMD sh -c 'php-fpm & nginx -g "daemon off;"'
