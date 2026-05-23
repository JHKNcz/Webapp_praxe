# Backend architektura (PHP)

## Cíle

- Jasné vrstvy: HTTP → Application → Domain → Infrastructure
- Simulované ceny (`PriceGeneratorService`) — mean reversion + šum, tick každých N sekund
- **Stav trhu v Redis** (`RedisAssetRepository`, `RedisPriceHistoryRepository`) — přežije mezi HTTP requesty (PHP `-S` resetoval InMemory)
- **Hybrid trading:** market maker (solo) + P2P order book (multiplayer)
- Live leaderboard aktivních hráčů
- Per-session transaction history
- Session-only identita s nickname při vstupu
- Redis pro fronty, leaderboard, historie obchodů a pub/sub

## Struktura

```
backend/
  public/index.php
  bootstrap.php
  config/routes.php
  src/
    Application/
      OrderService.php
      OrderCancellationService.php
      MarketTradeService.php
      MatchingEngine.php
      TradeHistoryService.php
      LeaderboardService.php
      ActiveSessionRegistry.php
      EventPublisher.php
    Domain/Entity/
      Order, Trade, TransactionEntry, Portfolio, Session
    Infrastructure/Storage/
      InMemory/ / Redis/
  tests/run.php
```

## Session lifecycle

### Start (`POST /session/start`)

- Vytvoří `Session` + `Portfolio` s `initial_cash` z `config/app.php` (env `INITIAL_CASH`)
- Přidá `sessionId` do `ActiveSessionRegistry` (Redis SET `sessions:active`)
- `LeaderboardService::syncLive()`

### Resume (`POST /session/resume`)

- Po reloadu frontendu (localStorage) — session data v Redis zůstává, ale registry se ztratí při restartu gateway/API procesu nebo nikdy nebyla zapsána
- Znovu `ActiveSessionRegistry::add()` + `syncLive()` bez nového portfolia

### End (`POST /session/end`)

1. `OrderCancellationService::cancelAllForSession()` — u každé open order: unlock collateral (`creditCash` / `creditShares`), `Order::cancel()`, `removeFromQueue`, pub/sub orderbook
2. `LeaderboardService::record()` → hall of fame
3. `removeLive()` + `ActiveSessionRegistry::remove()`
4. `SessionService::endSession()` — smaže portfolio a session

## Obchodování

### Market (`mode: market`)

- Okamžitý fill za `lastPrice` přes `MarketTradeService`
- Záznam v `TradeHistoryService` s `counterparty: exchange`

### Post / order book (`mode: limit`)

- Cena objednávky = aktuální `lastPrice` v momentě postu (ne vlastní limit z UI)
- `lockCash` / `lockShares`, enqueue do FIFO fronty (`orders:{assetId}:{side}`)
- `MatchingEngine::match()` páruje buy/sell mezi **různými** session

### FIFO pravidlo (`MatchingEngine::match`)

- Párování jen pokud `buyOrder.price >= sellOrder.price`
- Fill price = `sellOrder.price` (resting sell / maker na sell straně)
- Úprava locked cash u buyera: `extraCost = fillPrice * qty - buyLocked`
- Stejná session na obou stranách → requeue a stop (no self-trade)

### Take (`POST /orders/{id}/take`)

- Agresor bere konkrétní resting order za cenu makeru
- `MatchingEngine::takeOrder()` — přímý převod mezi portfolii

## Transaction history

- `GET /transactions?sessionId=&limit=50`
- Redis: `trades:session:{id}` (LIST, max 100)
- Záznamy pro market i P2P (oba hráči u P2P)

## Live leaderboard

- `sessions:active`, `leaderboard:live`, aktualizace při obchodu a price ticku (`AssetService` iteruje aktivní session)
- Po end session → `leaderboard:global`
- Frontend bez WS také polluje leaderboard po market/take obchodu

## Redis

| Klíč / kanál | Účel |
|--------------|------|
| `leaderboard:global` | Hall of fame |
| `leaderboard:live` | Aktivní hráči |
| `sessions:active` | SET aktivních session |
| `orders:{assetId}:{side}` | FIFO fronta posted objednávek |
| `order:{id}` | Hash detail orderu |
| `trades:session:{id}` | Historie obchodů hráče |
| `market:prices` | Pub/sub price ticks |
| `market:trades` | Pub/sub executed trades |
| `market:leaderboard` | Pub/sub leaderboard |
| `market:orderbook` | Pub/sub order book snapshot |

## WebSocket

`ws-gateway` subscribe na všechny `market:*` kanály a broadcastuje klientům.

## Konfigurace

`config/app.php` — `INITIAL_CASH` z env (default 10000), `price_update_interval_seconds`, seed assets.

## Testy

```bash
docker compose exec -T api php tests/run.php
docker compose exec -T api php tests/smoke.php
docker compose exec -T api php tests/price_persistence.php
```

Regresní testy v `tests/run.php` pokrývají: env initial cash, cancel on session end, resume registry, FIFO fill at resting sell price.
