import { createServer } from 'node:http';
import { WebSocketServer } from 'ws';
import Redis from 'ioredis';

const port = Number(process.env.WS_PORT || 3001);
const redisUrl = process.env.REDIS_URL || 'redis://127.0.0.1:6379';

const channels = ['market:prices', 'market:trades', 'market:leaderboard'];
const clients = new Set();

const server = createServer((req, res) => {
  if (req.url === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ ok: true }));
    return;
  }

  res.writeHead(404);
  res.end('Not found');
});

const wss = new WebSocketServer({ server, path: '/ws' });

wss.on('connection', (socket) => {
  clients.add(socket);
  socket.send(JSON.stringify({ type: 'connected', ts: Date.now() }));

  socket.on('close', () => clients.delete(socket));
  socket.on('error', () => clients.delete(socket));
});

const subscriber = new Redis(redisUrl);

subscriber.subscribe(...channels, (err) => {
  if (err) {
    console.error('Redis subscribe failed:', err.message);
    process.exit(1);
  }

  console.log(`Subscribed to ${channels.join(', ')}`);
});

subscriber.on('message', (channel, message) => {
  for (const client of clients) {
    if (client.readyState === client.OPEN) {
      client.send(message);
    }
  }
});

server.listen(port, () => {
  console.log(`WS gateway listening on :${port}`);
});
