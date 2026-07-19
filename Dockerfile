FROM php:8.3-cli-bookworm

# SQLite, PostgreSQL, and MySQL drivers
RUN apt-get update \
	&& apt-get install -y --no-install-recommends libsqlite3-dev libpq-dev \
	&& docker-php-ext-install pdo_sqlite pdo_mysql pdo_pgsql \
	&& rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . /app

RUN mkdir -p /app/data /app/assets/uploads \
	&& chmod -R 777 /app/data /app/assets/uploads

# Railway sets PORT at runtime
ENV PORT=8080
EXPOSE 8080

# Same as local: php -S ... router.php — bind to 0.0.0.0 for Railway
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t /app /app/router.php"]
