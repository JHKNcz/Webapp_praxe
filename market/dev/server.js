/**
 * Local dev server for testing on your PC (no external nginx needed).
 *
 * Serves frontend/ and proxies:
 *   /api/* -> http://127.0.0.1:9080/*
 *   /ws    -> ws://127.0.0.1:9081/ws
 *
 * Usage (after `docker compose up`):
 *   node dev/server.js
 *   open http://localhost:3000
 */

import { createServer } from 'node:http';
import { readFile, stat } from 'node:fs/promises';
import { join, extname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { WebSocketServer, WebSocket } from 'ws';

const __dirname = fileURLToPath(new URL('.', import.meta.url));
const FRONTEND = join(__dirname, '..', 'frontend');
const API_TARGET = process.env.API_TARGET || 'http://127.0.0.1:9080';
const WS_TARGET = process.env.WS_TARGET || 'ws://127.0.0.1:9081/ws';
const PORT = Number(process.env.DEV_PORT || 3000);

const MIME = {
  '.html': 'text/html',
  '.css': 'text/css',
  '.js': 'application/javascript',
  '.json': 'application/json',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.ico': 'image/x-icon',
};

async function serveStatic(pathname) {
  const filePath = join(FRONTEND, pathname === '/' ? 'index.html' : pathname);

  try {
    const info = await stat(filePath);
    if (!info.isFile()) return null;
    const body = await readFile(filePath);
    return { body, type: MIME[extname(filePath)] || 'application/octet-stream' };
  } catch {
    return null;
  }
}

async function proxyApi(req, res) {
  const url = `${API_TARGET}${req.url.replace(/^\/api/, '') || '/'}`;

  const headers = { ...req.headers, host: new URL(API_TARGET).host };
  delete headers.connection;

  const init = { method: req.method, headers };

  if (req.method !== 'GET' && req.method !== 'HEAD') {
    const chunks = [];
    for await (const chunk of req) chunks.push(chunk);
    init.body = Buffer.concat(chunks);
  }

  const upstream = await fetch(url, init);
  res.writeHead(upstream.status, Object.fromEntries(upstream.headers.entries()));
  const body = Buffer.from(await upstream.arrayBuffer());
  res.end(body);
}

const server = createServer(async (req, res) => {
  if (!req.url) {
    res.writeHead(400);
    res.end('Bad request');
    return;
  }

  if (req.url.startsWith('/api')) {
    try {
      await proxyApi(req, res);
    } catch (error) {
      res.writeHead(502, { 'Content-Type': 'text/plain' });
      res.end(`API unreachable (${API_TARGET}). Is docker compose running?\n${error.message}`);
    }
    return;
  }

  const file = await serveStatic(req.url.split('?')[0]);
  if (file) {
    res.writeHead(200, { 'Content-Type': file.type });
    res.end(file.body);
    return;
  }

  const fallback = await serveStatic('/index.html');
  if (fallback) {
    res.writeHead(200, { 'Content-Type': fallback.type });
    res.end(fallback.body);
    return;
  }

  res.writeHead(404);
  res.end('Not found');
});

const wss = new WebSocketServer({ server, path: '/ws' });

wss.on('connection', (client) => {
  const upstream = new WebSocket(WS_TARGET);

  upstream.on('open', () => {
    client.on('message', (data) => {
      if (upstream.readyState === WebSocket.OPEN) upstream.send(data);
    });

    upstream.on('message', (data) => {
      if (client.readyState === WebSocket.OPEN) client.send(data);
    });
  });

  client.on('close', () => upstream.close());
  upstream.on('close', () => client.close());
  upstream.on('error', () => client.close());
  client.on('error', () => upstream.close());
});

server.listen(PORT, () => {
  console.log(`Market.io dev server: http://localhost:${PORT}`);
  console.log(`  API proxy -> ${API_TARGET}`);
  console.log(`  WS  proxy -> ${WS_TARGET}`);
});
