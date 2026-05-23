# Market.io

Multiplayer .io style market simulation. Players join with a nickname, trade assets at simulated prices via hybrid market/P2P order matching, and compete on a Redis-backed leaderboard.

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
npm run dev
```

Open **http://localhost:3000** in your browser.

### Verify backend

```powershell
curl http://127.0.0.1:9080/assets
curl http://127.0.0.1:9081/health
docker compose exec -T api php tests/run.php
docker compose exec -T api php tests/smoke.php
docker compose exec -T api php tests/price_persistence.php
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

### Session

- `POST /session/start` — `{ nickname }` — create session, register for live leaderboard
- `POST /session/resume` — `{ sessionId }` — re-register after browser reload (localStorage restore)
- `POST /session/end` — `{ sessionId }` — cancel open orders, record score, delete session data

### Assets & portfolio

- `GET /assets` — list assets with `lastPrice`
- `GET /assets/tick` — advance simulated prices (also drives live leaderboard sync)
- `GET /assets/{id}?limit=40` — price history
- `GET /portfolio?sessionId=...` — cash, holdings, PnL summary

### Orders & book

- `POST /orders` — `{ sessionId, assetId, side, quantity, mode }`
  - `mode: "market"` — instant fill vs exchange at current `lastPrice`
  - `mode: "limit"` — post to FIFO order book at current `lastPrice` (not a custom limit price)
- `POST /orders/{id}/take` — `{ sessionId, quantity }` — take a resting player order
- `GET /orders?sessionId=...` — open orders for session
- `GET /orderbook/{assetId}` — bids/asks snapshot

### Other

- `GET /transactions?sessionId=&limit=50` — market and P2P trade history
- `GET /leaderboard` — live top players

## How trading works

1. **Simulated prices** — the server moves `lastPrice` on `GET /assets/tick` and broadcasts `price_tick` over WebSocket. Players do not set the market.
2. **Market order** (`mode: market`) — immediate fill against the exchange at `lastPrice` via `MarketTradeService`.
3. **Post** (`mode: limit`) — locks cash (buy) or shares (sell) and enqueues at the **current** `lastPrice`. This is a resting offer in the book, not an arbitrary limit price in the UI.
4. **FIFO matching** — when a buy and sell from different sessions cross (`buyPrice >= sellPrice`), `MatchingEngine` fills at the **resting sell price** (maker on the sell side).
5. **Take** — aggressor hits a specific resting order at the maker’s posted price.
6. **Session end** — `OrderCancellationService` cancels open orders, unlocks collateral, then deletes the portfolio. Other players no longer see ghost book rows.

## WebSocket events

Subscribe via `ws-gateway` on `/ws`. Channels: `price_tick`, `trade`, `leaderboard_update`, `orderbook_update`.

## Development

From `market/`:

```bash
npm run dev
```

Runs [`dev/server.js`](dev/server.js) — static frontend on port 3000, proxies `/api` and `/ws`.
