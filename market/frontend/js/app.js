import { api } from './api.js';
import { createMarketSocket } from './ws.js';

const state = {
  sessionId: localStorage.getItem('market.sessionId') || '',
  nickname: localStorage.getItem('market.nickname') || '',
  selectedAssetId: 'asset-1',
  assets: [],
  prices: {},
};

const els = {
  lobby: document.getElementById('lobby'),
  game: document.getElementById('game'),
  nickname: document.getElementById('nickname'),
  enterBtn: document.getElementById('enter-btn'),
  lobbyError: document.getElementById('lobby-error'),
  playerName: document.getElementById('player-name'),
  sessionValue: document.getElementById('session-value'),
  endBtn: document.getElementById('end-btn'),
  assetList: document.getElementById('asset-list'),
  portfolio: document.getElementById('portfolio'),
  orderbook: document.getElementById('orderbook'),
  selectedAsset: document.getElementById('selected-asset'),
  quantity: document.getElementById('quantity'),
  buyBtn: document.getElementById('buy-btn'),
  sellBtn: document.getElementById('sell-btn'),
  tradeMsg: document.getElementById('trade-msg'),
  openOrders: document.getElementById('open-orders'),
  leaderboard: document.getElementById('leaderboard'),
  toast: document.getElementById('toast'),
};

function showToast(message) {
  els.toast.textContent = message;
  els.toast.classList.remove('hidden');
  setTimeout(() => els.toast.classList.add('hidden'), 2500);
}

function showLobbyError(message) {
  els.lobbyError.textContent = message;
  els.lobbyError.classList.toggle('hidden', !message);
}

function renderAssets() {
  els.assetList.innerHTML = state.assets.map((asset) => {
    const selected = asset.id === state.selectedAssetId ? 'selected' : '';
    return `<li class="${selected}" data-id="${asset.id}">${asset.name} — ${Number(asset.lastPrice).toFixed(2)}</li>`;
  }).join('');
}

function renderPortfolio(summary) {
  const holdings = (summary.holdings || [])
    .map((h) => `<div>${h.assetId}: ${h.quantity} @ ${Number(h.currentPrice).toFixed(2)}</div>`)
    .join('');

  els.portfolio.innerHTML = `
    <div>Cash: ${Number(summary.cash).toFixed(2)}</div>
    <div>Total: ${Number(summary.totalValue).toFixed(2)}</div>
    ${holdings || '<div>No holdings</div>'}
  `;
  els.sessionValue.textContent = `Portfolio: ${Number(summary.totalValue).toFixed(2)}`;
}

async function renderOrderBook() {
  if (!state.selectedAssetId) return;
  const payload = await api.getOrderBook(state.selectedAssetId);
  const book = payload.orderbook;
  const queue = (book.queue || [])
    .map((row) => `<div>${row.side.toUpperCase()}: ${row.quantity}</div>`)
    .join('');

  els.orderbook.innerHTML = `
    <div>Last: ${Number(book.lastPrice).toFixed(2)}</div>
    <div>Depth buy ${book.depth.buy} / sell ${book.depth.sell}</div>
    ${queue || '<div>No queued orders</div>'}
  `;
}

function renderOpenOrders(items) {
  els.openOrders.innerHTML = (items || [])
    .map((order) => `<li>${order.side} ${order.remainingQty}x ${order.assetId} (${order.status})</li>`)
    .join('') || '<li>No open orders</li>';
}

function renderLeaderboard(items) {
  els.leaderboard.innerHTML = (items || [])
    .map((entry) => `<li>${entry.displayName} — ${Number(entry.score).toFixed(2)}</li>`)
    .join('') || '<li>No scores yet</li>';
}

async function refreshAll() {
  if (!state.sessionId) return;

  const [assetsPayload, portfolioPayload, ordersPayload, leaderboardPayload] = await Promise.all([
    api.getAssets(),
    api.getPortfolio(state.sessionId),
    api.getOpenOrders(state.sessionId),
    api.getLeaderboard(),
  ]);

  state.assets = assetsPayload.items || [];
  renderAssets();
  renderPortfolio(portfolioPayload);
  renderOpenOrders(ordersPayload.items);
  renderLeaderboard(leaderboardPayload.items);
  await renderOrderBook();
}

async function enterGame() {
  const nickname = els.nickname.value.trim();
  showLobbyError('');

  try {
    const payload = await api.startSession(nickname);
    const session = payload.session;
    state.sessionId = session.sessionId;
    state.nickname = session.nickname;
    localStorage.setItem('market.sessionId', state.sessionId);
    localStorage.setItem('market.nickname', state.nickname);

    els.lobby.classList.add('hidden');
    els.game.classList.remove('hidden');
    els.playerName.textContent = state.nickname;
    els.selectedAsset.textContent = state.selectedAssetId;

    await refreshAll();
    showToast('Welcome to the market');
  } catch (error) {
    showLobbyError(error.message);
  }
}

async function placeTrade(side) {
  const quantity = Number(els.quantity.value);
  if (!state.sessionId || quantity <= 0) return;

  try {
    const payload = await api.placeOrder(state.sessionId, state.selectedAssetId, side, quantity);
    const result = payload.result;
    const trades = result.trades || [];

    if (trades.length > 0) {
      showToast(`${side.toUpperCase()} matched (${trades.length} trade(s))`);
    } else {
      showToast(`${side.toUpperCase()} order queued`);
    }

    renderPortfolio(result.portfolio);
    renderOpenOrders(await api.getOpenOrders(state.sessionId).then((r) => r.items));
    await renderOrderBook();
  } catch (error) {
    els.tradeMsg.textContent = error.message;
  }
}

async function endSession() {
  if (!state.sessionId) return;

  try {
    const payload = await api.endSession(state.sessionId);
    showToast(`Session ended. Score: ${Number(payload.leaderboardEntry.score).toFixed(2)}`);
  } catch (error) {
    showToast(error.message);
  }

  localStorage.removeItem('market.sessionId');
  localStorage.removeItem('market.nickname');
  state.sessionId = '';
  els.game.classList.add('hidden');
  els.lobby.classList.remove('hidden');
}

els.enterBtn.addEventListener('click', enterGame);
els.endBtn.addEventListener('click', endSession);
els.buyBtn.addEventListener('click', () => placeTrade('buy'));
els.sellBtn.addEventListener('click', () => placeTrade('sell'));

els.assetList.addEventListener('click', async (event) => {
  const item = event.target.closest('[data-id]');
  if (!item) return;
  state.selectedAssetId = item.dataset.id;
  els.selectedAsset.textContent = state.selectedAssetId;
  renderAssets();
  await renderOrderBook();
});

createMarketSocket({
  onMessage(payload) {
    if (payload.type === 'price_tick') {
      state.assets = payload.items || [];
      renderAssets();
    }

    if (payload.type === 'leaderboard_update') {
      renderLeaderboard(payload.items);
    }

    if (payload.type === 'trade' && state.sessionId) {
      refreshAll().catch(() => {});
    }
  },
});

if (state.sessionId) {
  els.nickname.value = state.nickname;
  els.lobby.classList.add('hidden');
  els.game.classList.remove('hidden');
  els.playerName.textContent = state.nickname;
  refreshAll().catch(() => {
    localStorage.removeItem('market.sessionId');
    els.game.classList.add('hidden');
    els.lobby.classList.remove('hidden');
  });
}
