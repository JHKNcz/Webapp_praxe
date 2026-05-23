# Market.io

Multiplayer .io-style market simulation for short sessions. Each player joins with a nickname, receives starting cash, trades simulated assets, and competes on a live leaderboard until the session ends.

**Design goals**

- Session-only play (no permanent accounts); state in Redis for the open window
- Solo-friendly **market** orders (instant fill vs simulated exchange) plus optional **P2P** order book
- Simple browser UI with live prices, portfolio PnL, order book, and transaction history
- Self-hosted stack (Docker Compose) that can sit behind your existing nginx

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

| Service | Role | Default host port |
|---------|------|-------------------|
| `api` | PHP REST API | `127.0.0.1:9080` |
| `ws-gateway` | WebSocket fan-out (Redis pub/sub) | `127.0.0.1:9081` |
| `redis` | Orders, portfolios, leaderboard, pub/sub | internal only |

Static frontend: [`market/frontend/`](market/frontend/) — in production, serve as site root and proxy `/api` and `/ws`.

## Quick start

```bash
cd market
cp .env.example .env
docker compose up -d --build
npm run dev
```

Open **http://localhost:3000** (dev proxy). API alone: **http://127.0.0.1:9080**.

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
curl http://127.0.0.1:9080/assets
curl http://127.0.0.1:9081/health
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

## External nginx (production)

1. Mount or copy `market/frontend/` as the site root.
2. Proxy `/api/` → `http://127.0.0.1:9080` (strip `/api` prefix in the PHP app).
3. Proxy `/ws` → `http://127.0.0.1:9081` with WebSocket upgrade headers.

Template: [`market/deploy/external-nginx.example.conf`](market/deploy/external-nginx.example.conf).

## Environment

Copy [`market/.env.example`](market/.env.example) to `market/.env`. Compose passes variables into the `api` service.

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_DEBUG` | Verbose price logs in API | `false` |
| `API_PORT` | Host port for PHP API | `9080` |
| `WS_HOST_PORT` | Host port for WebSocket gateway | `9081` |
| `REDIS_URL` | Redis connection | `redis://redis:6379` |
| `REDIS_PREFIX` | Key prefix (isolate from other apps) | `market:` |
| `INITIAL_CASH` | Starting cash per session (read by `config/app.php`) | `10000` |

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
