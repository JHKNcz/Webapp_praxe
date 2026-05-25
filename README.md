# Market.io

Multiplayer .io-style market simulation for short sessions. Each player joins with a nickname, receives starting cash, trades simulated assets, and competes on a live leaderboard until the session ends.

**Design goals**

- Session-only play (no permanent accounts); state in Redis for the open window
- Solo-friendly **market** orders (instant fill vs simulated exchange) plus optional **P2P** order book
- Simple browser UI with live prices, portfolio PnL, order book, and transaction history
- Self-hosted stack with a single Docker ingress (`frontend`) and internal-only API/WS services

The runnable app lives in the [`market/`](market/) directory.

## Repository layout

```
market/
  backend/          PHP API (Application / Domain / Infrastructure)
  frontend/         Static UI (vanilla JS)
  ws-gateway/       Redis pub/sub → WebSocket fan-out
  dev/              Local dev proxy (port 3000)
  deploy/           Example nginx config
  docker-compose.yml
```

Backend details: [`market/backend/ARCHITECTURE.md`](market/backend/ARCHITECTURE.md).

## Stack

| Service | Role | Host exposure (default) |
|---------|------|-------------------------|
| `frontend` | Static UI + reverse proxy for `/api` and `/ws` | `127.0.0.1:9082` |
| `api` | PHP REST API | internal Docker network only |
| `ws-gateway` | WebSocket fan-out (Redis pub/sub) | internal Docker network only |
| `redis` | Orders, portfolios, leaderboard, pub/sub | internal Docker network only |

Frontend nginx config lives in [`market/frontend/nginx.conf`](market/frontend/nginx.conf).

## Quick start

```bash
cd market
cp .env.example .env
docker compose up -d --build
```

Open **http://127.0.0.1:9082**.

If you prefer the Node dev proxy for local iteration:

```bash
npm run dev
```

Then use **http://localhost:3000**.

## Test on your PC (before server deploy)

### Prerequisites

1. [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Windows/macOS/Linux)
2. [Node.js 20+](https://nodejs.org/) (for `npm run dev` only)

### Steps

```powershell
cd path\to\Webapp_praxe\market
copy .env.example .env
docker compose up -d --build
npm run dev
```

### Verify backend

```powershell
curl http://127.0.0.1:9082/api/assets
curl http://127.0.0.1:9082/api/leaderboard
docker compose exec -T api php tests/run.php
docker compose exec -T api php tests/smoke.php
docker compose exec -T api php tests/price_persistence.php
```

Expected: **27/27** tests in `run.php`, smoke OK, price persistence shows changing `lastPrice` in Redis.

### Stop

```powershell
docker compose down
```

### Without Docker (advanced)

PHP 8.1+, Composer. Redis optional — without it the API uses in-memory storage (prices reset per request; not suitable for real play).

```powershell
cd market/backend
composer install
php -S 127.0.0.1:9080 -t public
```

Run `ws-gateway` only when Redis is available.

---

## Production ingress options

### Option A: Existing nginx/NPM in front of Docker

Proxy all traffic to `frontend` only (`http://127.0.0.1:9082`).  
Do not proxy `/api` and `/ws` separately: frontend nginx already routes those paths internally.

### Option B: Isolated Docker network + `cloudflared` tunnel (recommended)

Use a private app network for `frontend`, `api`, `ws-gateway`, `redis`, and attach only `cloudflared` as edge ingress.

- No host ports are required for API, WS, or Redis.
- Tunnel ingress targets `http://frontend:80`.
- DNS points to Cloudflare tunnel hostname; host nginx stays untouched for other confidential apps.

Minimal `cloudflared` ingress shape:

```yaml
ingress:
  - hostname: marketio.example.com
    service: http://frontend:80
  - service: http_status:404
```

## Environment

Copy [`market/.env.example`](market/.env.example) to `market/.env`. Compose passes variables into the `api` service.

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_DEBUG` | Verbose price logs in API | `false` |
| `FRONTEND_PORT` | Host port for the single exposed frontend ingress | `9082` |
| `REDIS_URL` | Redis connection | `redis://redis:6379` |
| `REDIS_PREFIX` | Key prefix (isolate from other apps) | `market:` |
| `INITIAL_CASH` | Starting cash per session (read by `config/app.php`) | `10000` |

Legacy variables `API_PORT` and `WS_HOST_PORT` can remain in `.env`, but the current compose file does not publish those services to the host.

After changing `INITIAL_CASH`, recreate containers: `docker compose up -d --build`.

## API (prefix with `/api` in production)

### Session

| Method | Path | Body | Notes |
|--------|------|------|--------|
| `POST` | `/session/start` | `{ "nickname": "Trader42" }` | Creates session + portfolio |
| `POST` | `/session/resume` | `{ "sessionId": "..." }` | After browser reload (localStorage) |
| `POST` | `/session/end` | `{ "sessionId": "..." }` | Cancels open orders, hall-of-fame score |

### Assets and portfolio

| Method | Path | Notes |
|--------|------|--------|
| `GET` | `/assets` | All assets + `lastPrice` |
| `GET` | `/assets/tick` | Force price tick + live leaderboard sync |
| `GET` | `/assets/{id}?limit=40` | Price history |
| `GET` | `/portfolio?sessionId=...` | Cash, holdings, PnL |

### Orders and book

| Method | Path | Body | Notes |
|--------|------|------|--------|
| `POST` | `/orders` | `{ sessionId, assetId, side, quantity, mode }` | `mode`: `"market"` or `"limit"` |
| `POST` | `/orders/{id}/take` | `{ sessionId, quantity }` | Fill against resting order |
| `GET` | `/orders?sessionId=...` | Open orders for session |
| `GET` | `/orderbook/{assetId}` | Bids/asks with nicknames |

### Other

| Method | Path | Notes |
|--------|------|--------|
| `GET` | `/transactions?sessionId=&limit=50` | Market + P2P history |
| `GET` | `/leaderboard` | Live top sessions by portfolio value |

## How trading works

1. **Simulated prices** — `PriceGeneratorService` updates `lastPrice` on tick (`GET /assets/tick`, WebSocket `price_tick`). Players do not set the market.
2. **Market** (`mode: market`) — instant fill at `lastPrice` via `MarketTradeService` (solo vs exchange).
3. **Post** (`mode: limit`) — locks cash (buy) or shares (sell), enqueues at **current** `lastPrice` (not a custom limit price in the UI).
4. **FIFO matching** — different sessions only; match when `buyPrice >= sellPrice`, fill at **resting sell price**. Same-session buy+sell at the head of the queue are skipped without blocking other pairs.
5. **Take** — hit a specific resting order at the maker’s price.
6. **Collateral** — locked funds/shares return when an order is fully filled, cancelled on session end, or released after partial fills on the remaining quantity.
7. **Session end** — `OrderCancellationService` removes ghost orders from the book and unlocks collateral before the portfolio is deleted.

## WebSocket events

Connect via `ws-gateway` at `/ws` (proxied as `ws://host/ws` in dev). Event types:

- `price_tick` — `{ items: [{ id, name, lastPrice }, ...] }`
- `trade` — executed trade payload
- `leaderboard_update` — `{ items: [...] }`
- `orderbook_update` — `{ assetId, orderbook: { bids, asks } }`

## Development

From `market/`:

```bash
npm run dev
```

Runs [`market/dev/server.js`](market/dev/server.js): static files on port **3000**, proxies `/api` → API and `/ws` → gateway.

Port already in use:

```powershell
$env:DEV_PORT=3001; npm run dev
```

## Tests

| Command | Purpose |
|---------|---------|
| `docker compose exec -T api php tests/run.php` | 27 unit/integration tests (portfolio, matching, session, API) |
| `docker compose exec -T api php tests/smoke.php` | HTTP smoke |
| `docker compose exec -T api php tests/price_persistence.php` | Redis prices change between ticks |

Local (no Docker): `cd market/backend && composer install && php tests/run.php` (in-memory only).
