# Two-client OddSockets round-trip demo for the PHP SDK.
#
# Build from the repo ROOT so the SDK source is in context:
#   docker build -f demo/Dockerfile -t oddsockets-php-demo .
#   docker run --rm -e ODDSOCKETS_API_KEY="ak_..." oddsockets-php-demo
#
# A successful run prints "OK - cross-client round-trip verified" and exits 0.
FROM php:8.2-cli

# Composer + the git/unzip it needs to resolve dependencies.
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Bring in the SDK source and the demo. The demo's composer.json resolves the
# SDK through a local path repository (../), so we need both trees present.
COPY . /app

WORKDIR /app/demo
RUN composer install --no-interaction --no-progress

ENTRYPOINT ["php", "roundtrip.php"]
