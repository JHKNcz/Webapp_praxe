# Market.io

Multiplayer .io style market simulation. Players join with a nickname, trade assets at simulated prices via FIFO P2P order matching, and compete on a Redis-backed leaderboard.

Everything lives under this directory — safe to deploy alongside other self-hosted apps.

## Stack

| Service | Role | Default host port |
|---------|------|-------------------|
| `api` | PHP REST API | `127.0.0.1:9080` |
| `ws-gateway` | WebSocket fan-out (Redis pub/sub) | `127.0.0.1:9081` |
| `redis` | Order queues, leaderboard, pub/sub | internal only |

Static frontend files: [`frontend/`](frontend/) — serve via your existing nginx.

## Quick start

```bash
cd market
cp .env.example .env
docker compose up -d --build
```

Run tests locally:

```bash
cd backend
composer install
php tests/run.php
```

## Test on your PC (before server deploy)

### Prerequisites

1. [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Windows)
2. [Node.js 20+](https://nodejs.org/) (for local dev proxy only)

### Steps

```powershell
cd d:\Commerzbank\Webapp_praxe\market
copy .env.example .env
docker compose up -d --build
```

Wait until containers are healthy, then start the dev proxy (serves frontend + `/api` + `/ws`):

```powershell
node dev/server.js
```

Open **http://localhost:3000** in your browser.

### Verify backend

```powershell
curl http://127.0.0.1:9080/assets
curl http://127.0.0.1:9081/health
docker compose exec api php tests/run.php
```

### Stop

```powershell
docker compose down
```

### Without Docker (advanced)

Install PHP 8.1+, Composer, Redis (optional). Without Redis the app falls back to in-memory storage (fine for solo testing):

```powershell
cd backend
composer install
php -S 127.0.0.1:9080 -t public
```

In another terminal, run `ws-gateway` only if Redis is available. For a quick API smoke test, the PHP server alone is enough.

---

## External nginx (production / your server)

1. Copy or mount `frontend/` as the site root.
2. Proxy `/api/` → `http://127.0.0.1:9080` (strip `/api` prefix).
3. Proxy `/ws` → `http://127.0.0.1:9081` with WebSocket upgrade headers.

See [`deploy/external-nginx.example.conf`](deploy/external-nginx.example.conf) for a full server block template.

## Environment

| Variable | Description | Default |
|----------|-------------|---------|
| `API_PORT` | Host port for PHP API | `9080` |
| `WS_HOST_PORT` | Host port for WebSocket gateway | `9081` |
| `REDIS_URL` | Redis connection | `redis://redis:6379` |
| `REDIS_PREFIX` | Key prefix (isolation from other apps) | `market:` |
| `INITIAL_CASH` | Starting cash per session | `10000` |

## API (prefix with `/api` in production)

- `POST /session/start` — `{ nickname }`
- `POST /session/end` — `{ sessionId }`
- `GET /assets`, `GET /assets/tick`, `GET /assets/{id}`
- `GET /portfolio?sessionId=...`
- `POST /orders` — `{ sessionId, assetId, side, quantity }`
- `GET /orders?sessionId=...`
- `GET /orderbook/{assetId}`
- `GET /leaderboard`

## How trading works

1. Simulated prices update on the server (players do not move the market).
2. Buy/sell orders enter FIFO queues per asset and side.
3. When opposite orders exist, the matching engine pairs them at the current simulated price.
4. Unmatched orders stay pending until a counterparty arrives.
