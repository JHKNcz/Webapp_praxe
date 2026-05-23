# PHP backend for Market.io

## Local (without Docker)

```bash
composer install
php -S localhost:8000 -t public
```

Run tests:

```bash
php tests/run.php
```

## Docker

From `market/` directory:

```bash
docker compose up -d --build
```

API listens on `127.0.0.1:9080`. Wire your external nginx using `deploy/external-nginx.example.conf`.
