FROM ubuntu:latest

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        unzip \
        software-properties-common \
        gnupg2 \
    && add-apt-repository ppa:ondrej/php -y \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        php8.5-cli \
        php8.5-common \
        php8.5-mbstring \
        php8.5-xml \
        php8.5-curl \
        php8.5-zip \
        php8.5-bcmath \
        php8.5-intl \
        php8.5-mysql \
        php8.5-sqlite3 \
        php8.5-gd \
        php8.5-redis \
    && curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

EXPOSE 8000

CMD ["bash", "-lc", "if [ ! -f .env ]; then cp .env.example .env; fi && if [ ! -f vendor/autoload.php ]; then composer install --no-interaction --prefer-dist; fi && if [ -z \"${APP_KEY:-}\" ] && ! grep -Eq '^APP_KEY=base64:' .env; then echo 'APP_KEY is missing. Set APP_KEY in Linux env or .env before starting the container.' >&2; exit 1; fi && php artisan migrate --force --no-interaction && php artisan serve --host=0.0.0.0 --port=8000"]
