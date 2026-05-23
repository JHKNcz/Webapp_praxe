# Market backend (PHP)

## Run

```bash
php -S localhost:8000 -t backend/public
```

## Main endpoints

- `POST /session/start`
- `POST /session/end`
- `GET /assets`
- `GET /assets/tick`
- `GET /assets/{id}`
- `GET /portfolio?sessionId=...`
- `POST /trade/buy`
- `POST /trade/sell`
- `GET /leaderboard`

## Payload examples

### `POST /session/start`

Response:
```json
{
	"ok": true,
	"session": {
		"sessionId": "...",
		"createdAt": 0,
		"endedAt": null,
		"active": true,
		"cash": 10000
	}
}
```

### `GET /assets`

Response:
```json
{
	"ok": true,
	"items": [
		{ "id": "asset-1", "name": "Alpha Token", "lastPrice": 100.12 }
	]
}
```

### `GET /assets/tick`

Response:
```json
{
	"ok": true,
	"items": [
		{ "id": "asset-1", "name": "Alpha Token", "lastPrice": 101.02 }
	]
}
```

### `GET /assets/{id}?limit=20`

Response:
```json
{
	"ok": true,
	"item": { "id": "asset-1", "name": "Alpha Token", "lastPrice": 101.02 },
	"history": [
		{ "assetId": "asset-1", "price": 100.1, "ts": 1710000000 }
	]
}
```

### `POST /trade/buy`

Body:
```json
{ "sessionId": "...", "assetId": "asset-1", "quantity": 2 }
```

Response:
```json
{ "ok": true, "trade": { "type": "buy", "assetId": "asset-1", "quantity": 2, "price": 101.02, "portfolio": { "cash": 9797.96 } } }
```

## CORS

Backend sets permissive CORS headers for development. Adjust in `public/index.php` if needed.

## Example flow

1. Start session.
2. Load assets.
3. Buy/sell using returned `sessionId`.
4. Read portfolio.
5. End session and save leaderboard entry.
