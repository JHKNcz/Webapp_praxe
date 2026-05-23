# Backend architektura (PHP)

## Cíle

- Jasné vrstvy: HTTP → Application → Domain → Infrastructure
- Simulované ceny na serveru (hráči cenu neovlivňují)
- FIFO P2P matching mezi hráči za aktuální `lastPrice`
- Session-only identita s nickname při vstupu
- Redis pro leaderboard, order queues a pub/sub (WebSocket gateway)

## Struktura

```
backend/
  public/index.php          # Front controller
  bootstrap.php             # DI wiring
  config/routes.php
  src/
    Controller/
    Application/
      AssetService.php
      OrderService.php
      MatchingEngine.php
      EventPublisher.php
    Domain/Entity/
      Order.php, Trade.php, Portfolio.php, Session.php
    Infrastructure/Storage/
      InMemory/               # lokální dev / testy
      Redis/                  # produkce (Docker)
  tests/run.php
```

## Order book + FIFO

- Hráč posílá `{ side, quantity }` — market order za simulovanou cenu
- Buy: rezervace hotovosti (`Portfolio::lockCash`)
- Sell: rezervace akcií (`Portfolio::lockShares`)
- Fronty v Redis LIST (`LPUSH` / `RPOP`) nebo InMemory pro testy
- `MatchingEngine` páruje nejstarší buy se nejstarším sell, partial fill podporován

## Redis

| Klíč / kanál | Účel |
|--------------|------|
| `{prefix}leaderboard:global` | Sorted set — top scores |
| `{prefix}orders:{assetId}:{side}` | FIFO fronta |
| `{prefix}order:{id}` | Hash — detail orderu |
| `market:prices` | Pub/sub — price ticks |
| `market:trades` | Pub/sub — executed trades |
| `market:leaderboard` | Pub/sub — leaderboard updates |

## WebSocket

Node.js `ws-gateway` subscribe na Redis kanály a broadcastuje klientům. PHP neobsahuje WS server.

## Deploy

Docker Compose stack (`market-io` project name) binduje API a WS na localhost. Externí nginx servíruje `frontend/` a proxyuje `/api` a `/ws` — viz `deploy/external-nginx.example.conf`.

## Testy

```bash
cd backend && composer install && php tests/run.php
```
