# Backend architektura (PHP)

Tento dokument definuje **jasnou vizi backendu** pro Market (io simulace trhu), aby bylo možné konzistentně vyvíjet a rozšiřovat projekt.

## 1) Cíle a principy
- **Jasné vrstvy**: HTTP → Application → Domain → Infrastructure
- **Server je zdroj cen aktiv**: ceny a validace transakcí jen na serveru
- **Session-only**: bez trvalých účtů, sessionId identifikuje hráče
- **Jednoduchá rozšiřitelnost**: snadné přidání dalších aktiv, leaderboardu a DB

## 2) Struktura složek (návrh)
```
backend/
  public/
    index.php
    assets/
  src/
    Http/
      Router/
      Middleware/
    Controller/
      AssetController.php
      TradeController.php
      SessionController.php
      PortfolioController.php
    Application/
      AssetService.php
      PriceGeneratorService.php
      TradeService.php
      PortfolioService.php
      SessionService.php
    Domain/
      Entity/
        Asset.php
        PricePoint.php
        Portfolio.php
        Holding.php
        Session.php
      ValueObject/
        Money.php
        AssetId.php
    Infrastructure/
      Storage/
        InMemory/
        Sqlite/
      Repository/
        AssetRepository.php
        PortfolioRepository.php
        PriceHistoryRepository.php
  config/
    routes.php
    app.php
  storage/
    logs/
    cache/
  tests/
```

## 3) Vrstvy a odpovědnosti

### 3.1 HTTP vrstva
- **Router**: mapuje HTTP method + URL → Controller
- **Controller**: překládá HTTP požadavek do volání služeb

### 3.2 Application vrstva
- **Services**: aplikační logika (obchod, validace, výpočet portfolia)
- **Use cases**: start session, buy/sell, get asset, get portfolio

### 3.3 Domain vrstva
- **Entity a ValueObjects**: pravidla a modely hry
- **Invarianta**: hotovost nesmí být záporná, quantity > 0

### 3.4 Infrastructure
- **Storage**: ukládání (in‑memory nebo SQLite/MySQL)
- **Repositories**: izolace persistence

## 4) Doménové modely (minimální)
- **Asset**: id, name, lastPrice
- **PricePoint**: assetId, price, timestamp
- **Portfolio**: cash, holdings[]
- **Holding**: assetId, quantity, avgPrice
- **Session**: sessionId, createdAt, portfolioId

## 5) API kontrakty (MVP)

### Session
- `POST /session/start`
  - Response: `{ sessionId, cash }`

### Assets
- `GET /assets`
  - Response: `{ items: [{ id, name, lastPrice }] }`
- `GET /assets/{id}`
  - Response: `{ id, name, lastPrice, history: [ { price, ts } ] }`

### Trade
- `POST /trade/buy`
  - Body: `{ assetId, quantity }`
  - Response: `{ ok: true, cash, holding }`
- `POST /trade/sell`
  - Body: `{ assetId, quantity }`
  - Response: `{ ok: true, cash, holding }`

### Portfolio
- `GET /portfolio`
  - Response: `{ cash, holdings, totalValue }`

## 6) Generování cen (serverový tick)
- **MVP**: generace cen při requestu (lazy) + ukládání posledních N bodů
- **Později**: cron tick každých X sekund a push na klienty

## 7) Storage
- **MVP**: In‑memory repository (rychlé, ale bez persistence)
- **Později**: SQLite → MySQL (stejná API vrstva)

## 8) Testovací strategie (základ)
- **Unit**: doménová pravidla (`Portfolio`, `TradeService`)
- **Integration**: API endpointy (`/trade/buy`, `/portfolio`)

---

Pokud chceš, můžu doplnit konkrétní skeleton souborů a minimální router + controller implementace.
