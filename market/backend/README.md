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
docker compose down -v
docker compose up -d --build
```

Services:

- Frontend: `http://127.0.0.1:9082`
- API (via frontend proxy): `http://127.0.0.1:9082/api`
- WebSocket (via frontend proxy): `ws://127.0.0.1:9082/ws`

Notes for the redesigned branch:

- Starting cash default is `1000` (`INITIAL_CASH` env).
- Seed assets are:
  - `MoonRocket AI Ltd`
  - `GameStopp Corp`
  - `Lehmann & Bros Inc`
- Price update interval is 1s in backend config, while ws-gateway triggers market ticks at 500ms cadence.
- If you do not remove volumes, Redis may keep old seeded market data from previous runs.
