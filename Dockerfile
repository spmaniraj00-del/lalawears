FROM dunglas/frankenphp:1-php8.3

# SQLite for the app database
RUN install-php-extensions pdo_sqlite

WORKDIR /app

COPY . /app

# Writable dirs for SQLite + uploads
RUN mkdir -p /app/data /app/assets/uploads \
	&& chmod -R 777 /app/data /app/assets/uploads

# Railway injects PORT; Caddyfile binds to it
ENV PORT=8080

EXPOSE 8080

CMD ["frankenphp", "run", "--config", "/app/Caddyfile"]
