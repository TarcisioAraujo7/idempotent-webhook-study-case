#!/bin/sh
set -e

if [ ! -f .env ]; then
    cp .env.example .env
fi

set_env() {
    name="$1"
    eval "is_set=\${$name+x}"

    if [ "$is_set" = "x" ]; then
        eval "value=\${$name}"
        escaped_value=$(printf '%s' "$value" | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g')
        line="$name=\"$escaped_value\""
        escaped_line=$(printf '%s' "$line" | sed -e 's/[&|]/\\&/g')

        if grep -q "^$name=" .env; then
            sed -i "s|^$name=.*|$escaped_line|" .env
        else
            printf '%s\n' "$line" >> .env
        fi
    fi
}

for variable in \
    APP_NAME \
    APP_ENV \
    APP_KEY \
    APP_DEBUG \
    APP_URL \
    LOG_CHANNEL \
    LOG_LEVEL \
    DB_CONNECTION \
    DB_HOST \
    DB_PORT \
    DB_DATABASE \
    DB_USERNAME \
    DB_PASSWORD \
    REDIS_CLIENT \
    REDIS_HOST \
    REDIS_PORT \
    CACHE_STORE \
    QUEUE_CONNECTION \
    SESSION_DRIVER \
    BROADCAST_CONNECTION \
    FILESYSTEM_DISK \
    MAIL_MAILER \
    PAYMENT_WEBHOOK_LOCK_SECONDS
do
    set_env "$variable"
done

app_key=$(sed -n 's/^APP_KEY=//p' .env | tail -n 1 | tr -d '"')

if [ -z "$app_key" ]; then
    if [ -n "${APP_KEY:-}" ]; then
        set_env APP_KEY
    else
        php artisan key:generate --force --no-interaction
    fi
fi

exec "$@"
