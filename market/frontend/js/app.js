import { api } from './api.js';
import { createMarketSocket } from './ws.js';

const PRICE_POLL_MS = 5000;

const state = {
  sessionId: localStorage.getItem('market.sessionId') || '',
  nickname: localStorage.getItem('market.nickname') || '',
  selectedAssetId: 'asset-1',
  assets: [],
  lastPrices: {},
  priceFlash: '',
  lastPriceTickAt: 0,
  pricePollTimer: null,
};

const els = {
  lobby: document.getElementById('lobby'),
  game: document.getElementById('game'),
  nickname: document.getElementById('nickname'),
  enterBtn: document.getElementById('enter-btn'),
  lobbyError: document.getElementById('lobby-error'),
  playerName: document.getElementById('player-name'),
  topbarPrice: document.getElementById('topbar-price'),
  sessionValue: document.getElementById('session-value'),
  endBtn: document.getElementById('end-btn'),
  assetList: document.getElementById('asset-list'),
  portfolio: document.getElementById('portfolio'),
  orderbook: document.getElementById('orderbook'),
  livePrice: document.getElementById('live-price'),
  livePriceName: document.getElementById('live-price-name'),
  livePriceValue: document.getElementById('live-price-value'),
  livePriceTime: document.getElementById('live-price-time'),
  selectedAsset: document.getElementById('selected-asset'),
  quantity: document.getElementById('quantity'),
  buyBtn: document.getElementById('buy-btn'),
  sellBtn: document.getElementById('sell-btn'),
  postBuyBtn: document.getElementById('post-buy-btn'),
  postSellBtn: document.getElementById('post-sell-btn'),
  tradeMsg: document.getElementById('trade-msg'),
  openOrders: document.getElementById('open-orders'),
  leaderboard: document.getElementById('leaderboard'),
  transactions: document.getElementById('transactions'),
  priceChart: document.getElementById('price-chart'),
  toast: document.getElementById('toast'),
};

function assetName(assetId) {
  return state.assets.find((a) => a.id === assetId)?.name || assetId;
}

function showToast(message) {
  els.toast.textContent = message;
  els.toast.classList.remove('hidden');
  setTimeout(() => els.toast.classList.add('hidden'), 2500);
}

function showLobbyError(message) {
  els.lobbyError.textContent = message;
  els.lobbyError.classList.toggle('hidden', !message);
}

function stopPricePoll() {
  if (state.pricePollTimer !== null) {
    clearInterval(state.pricePollTimer);
    state.pricePollTimer = null;
  }
}

function startPricePoll() {
  stopPricePoll();
  state.pricePollTimer = setInterval(() => {
    pollMarketPrices().catch(() => {});
  }, PRICE_POLL_MS);
}

function clearSession(message = '') {
  stopPricePoll();
  localStorage.removeItem('market.sessionId');
  localStorage.removeItem('market.nickname');
  state.sessionId = '';
  state.nickname = '';
  els.tradeMsg.textContent = '';
  showLobbyError('');
  els.game.classList.add('hidden');
  els.lobby.classList.remove('hidden');
  if (message) {
    showLobbyError(message);
    showToast(message);
  }
}

function isSessionError(error) {
  const message = error?.message || '';
  return message.includes('Session not found') || message.includes('Session is already closed');
}

async function apiCall(fn) {
  try {
    return await fn();
  } catch (error) {
    if (isSessionError(error)) {
      clearSession('Session vypršela (restart serveru). Vstupte znovu na burzu.');
    }
    throw error;
  }
}

function getSelectedAsset() {
  return state.assets.find((asset) => asset.id === state.selectedAssetId) || null;
}

function formatTime(ts) {
  if (!ts) return '';
  return new Date(ts * 1000).toLocaleTimeString();
}

function formatPnl(value) {
  const sign = value > 0 ? '+' : '';
  return `${sign}${Number(value).toFixed(2)}`;
}

function applyAssetPrices(items) {
  state.assets = items || [];
  state.lastPriceTickAt = Math.floor(Date.now() / 1000);
  renderAssets();
}

async function refreshPortfolioOnly() {
  if (!state.sessionId) return;
  const summary = await apiCall(() => api.getPortfolio(state.sessionId));
  renderPortfolio(summary);
}

async function pollMarketPrices() {
  if (!state.sessionId) return;

  const payload = await apiCall(() => api.tickAssets());
  applyAssetPrices(payload.items || []);
  await refreshPortfolioOnly();
  await loadPriceChart();
}

function renderPriceChart(history) {
  if (!els.priceChart) return;

  const points = (history || []).filter((p) => typeof p.price === 'number');

  if (points.length < 2) {
    els.priceChart.innerHTML = '';
    return;
  }

  const width = 320;
  const height = 120;
  const pad = 8;
  const prices = points.map((p) => Number(p.price));
  const min = Math.min(...prices);
  const max = Math.max(...prices);
  const range = max - min || 1;

  const coords = points.map((p, i) => {
    const x = pad + (i / (points.length - 1)) * (width - pad * 2);
    const y = height - pad - ((Number(p.price) - min) / range) * (height - pad * 2);
    return `${x.toFixed(1)},${y.toFixed(1)}`;
  });

  const line = coords.join(' ');
  const area = `${pad},${height - pad} ${line} ${width - pad},${height - pad}`;

  els.priceChart.innerHTML = `
    <polygon class="price-chart-area" points="${area}" />
    <polyline class="price-chart-line" points="${line}" />
  `;
}

async function loadPriceChart() {
  if (!state.selectedAssetId) return;

  try {
    const payload = await api.getAssetDetail(state.selectedAssetId);
    renderPriceChart(payload.history || []);
  } catch {
    renderPriceChart([]);
  }
}

function renderLivePrice() {
  const asset = getSelectedAsset();

  if (!asset) {
    els.livePriceName.textContent = '—';
    els.livePriceValue.textContent = '—';
    els.livePriceTime.textContent = '';
    els.topbarPrice.textContent = '';
    els.selectedAsset.textContent = state.selectedAssetId;
    return;
  }

  const price = Number(asset.lastPrice);
  const prev = state.lastPrices[asset.id];

  if (prev !== undefined && prev !== price) {
    state.priceFlash = price > prev ? 'up' : 'down';
    els.livePrice.classList.remove('flash-up', 'flash-down');
    void els.livePrice.offsetWidth;
    els.livePrice.classList.add(state.priceFlash === 'up' ? 'flash-up' : 'flash-down');
  }

  state.lastPrices[asset.id] = price;

  els.livePriceName.textContent = asset.name;
  els.livePriceValue.textContent = price.toFixed(2);
  els.livePriceValue.className = `live-price-value ${state.priceFlash === 'up' ? 'price-up' : state.priceFlash === 'down' ? 'price-down' : ''}`;
  els.livePriceTime.textContent = state.lastPriceTickAt
    ? `Aktualizováno ${formatTime(state.lastPriceTickAt)}`
    : '';
  els.topbarPrice.textContent = `${asset.name}: ${price.toFixed(2)}`;
  els.selectedAsset.textContent = `${asset.name} @ ${price.toFixed(2)}`;
}

function renderAssets() {
  els.assetList.innerHTML = state.assets
    .map((asset) => {
      const selected = asset.id === state.selectedAssetId ? 'selected' : '';
      return `<li class="${selected}" data-id="${asset.id}">${asset.name} — ${Number(asset.lastPrice).toFixed(2)}</li>`;
    })
    .join('');
  renderLivePrice();
}

function renderPortfolio(summary) {
  const pnl = Number(summary.pnl ?? 0);
  const pnlPercent = Number(summary.pnlPercent ?? 0);
  const pnlClass = pnl >= 0 ? 'price-up' : 'price-down';

  const holdings = (summary.holdings || [])
    .map((h) => {
      const name = assetName(h.assetId);
      const holdingPnl = Number(h.unrealizedPnl ?? 0);
      const holdingClass = holdingPnl >= 0 ? 'price-up' : 'price-down';
      return `<div class="holding-row">
        <span>${name}: ${h.quantity} ks</span>
        <span>@ ${Number(h.currentPrice).toFixed(2)}</span>
        <span class="${holdingClass}">(${formatPnl(holdingPnl)})</span>
      </div>`;
    })
    .join('');

  els.portfolio.innerHTML = `
    <div>Cash: ${Number(summary.cash).toFixed(2)}</div>
    <div>Celkem: <strong>${Number(summary.totalValue).toFixed(2)}</strong></div>
    <div class="pnl-row ${pnlClass}">Zisk/ztráta: <strong>${formatPnl(pnl)}</strong> (${formatPnl(pnlPercent)} %)</div>
    ${holdings || '<div>Žádné pozice</div>'}
  `;
  els.sessionValue.textContent = `Portfolio: ${Number(summary.totalValue).toFixed(2)} (${formatPnl(pnl)})`;
}

function renderOrderBookRows(rows) {
  return (rows || [])
    .map((row) => {
      const isOwn = row.sessionId === state.sessionId;
      const action = row.side === 'sell' ? 'Buy' : 'Sell';
      return `
        <div class="ob-row ${row.side}">
          <span class="ob-nick">${row.nickname}</span>
          <span class="ob-price">${Number(row.price).toFixed(2)}</span>
          <span class="ob-qty">${row.remainingQty}</span>
          <button type="button" class="ob-take secondary" data-order-id="${row.id}" ${isOwn ? 'disabled' : ''}>${action}</button>
        </div>
      `;
    })
    .join('');
}

function renderOrderBookFromData(book) {
  const asks = renderOrderBookRows(book.asks);
  const bids = renderOrderBookRows(book.bids);
  const empty = !asks && !bids;

  els.orderbook.innerHTML = `
    <div class="ob-last">Last: <strong>${Number(book.lastPrice).toFixed(2)}</strong></div>
    <div class="ob-section">
      <div class="ob-head">Prodeje (asks)</div>
      ${asks || '<div class="ob-empty">Žádné prodejní objednávky</div>'}
    </div>
    <div class="ob-section">
      <div class="ob-head">Nákupy (bids)</div>
      ${bids || '<div class="ob-empty">Žádné nákupní objednávky</div>'}
    </div>
    ${empty ? '<p class="hint">Prázdný book — obchodujte přes Market nebo zadejte Post objednávku.</p>' : ''}
  `;
}

async function renderOrderBook() {
  if (!state.selectedAssetId) return;
  const payload = await api.getOrderBook(state.selectedAssetId);
  renderOrderBookFromData(payload.orderbook);
}

function renderOpenOrders(items) {
  els.openOrders.innerHTML = (items || [])
    .map(
      (order) =>
        `<li>${order.side} ${order.remainingQty}× ${assetName(order.assetId)} @ ${Number(order.price).toFixed(2)} (${order.status})</li>`
    )
    .join('') || '<li>Žádné otevřené objednávky</li>';
}

function renderLeaderboard(items) {
  els.leaderboard.innerHTML = (items || [])
    .map((entry) => `<li>${entry.displayName} — ${Number(entry.score).toFixed(2)}</li>`)
    .join('') || '<li>Zatím žádné skóre</li>';
}

function renderTransactions(items) {
  if (!items || items.length === 0) {
    els.transactions.innerHTML = '<div class="ob-empty">Zatím žádné obchody</div>';
    return;
  }

  els.transactions.innerHTML = `
    <table class="tx-table">
      <thead>
        <tr><th>Čas</th><th>Strana</th><th>Asset</th><th>Ks</th><th>Cena</th><th>Celkem</th><th>Přes</th></tr>
      </thead>
      <tbody>
        ${items
          .map(
            (tx) => `
          <tr>
            <td>${formatTime(tx.timestamp)}</td>
            <td class="${tx.side === 'buy' ? 'price-up' : 'price-down'}">${tx.side === 'buy' ? 'NÁKUP' : 'PRODEJ'}</td>
            <td>${assetName(tx.assetId)}</td>
            <td>${tx.quantity}</td>
            <td>${Number(tx.price).toFixed(2)}</td>
            <td>${Number(tx.total).toFixed(2)}</td>
            <td>${tx.type === 'market' ? 'burza' : tx.counterparty}</td>
          </tr>
        `
          )
          .join('')}
      </tbody>
    </table>
  `;
}

async function loadTransactions() {
  if (!state.sessionId) return;
  const payload = await api.getTransactions(state.sessionId);
  renderTransactions(payload.items);
}

async function refreshAll() {
  if (!state.sessionId) return;

  const [assetsPayload, portfolioPayload, ordersPayload, leaderboardPayload] = await apiCall(() =>
    Promise.all([
      api.getAssets(),
      api.getPortfolio(state.sessionId),
      api.getOpenOrders(state.sessionId),
      api.getLeaderboard(),
    ])
  );

  state.assets = assetsPayload.items || [];
  if (!state.assets.find((a) => a.id === state.selectedAssetId) && state.assets[0]) {
    state.selectedAssetId = state.assets[0].id;
  }
  state.lastPriceTickAt = Math.floor(Date.now() / 1000);
  renderAssets();
  renderPortfolio(portfolioPayload);
  renderOpenOrders(ordersPayload.items);
  renderLeaderboard(leaderboardPayload.items);
  await Promise.all([renderOrderBook(), loadTransactions(), loadPriceChart()]);
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

    await refreshAll();
    startPricePoll();
    showToast('Vítejte na burze');
  } catch (error) {
    showLobbyError(error.message);
  }
}

function tradeToast(result) {
  const fillType = result.fillType || 'market';
  const trades = result.trades || [];

  if (fillType === 'market') {
    showToast('Vyplněno za tržní cenu');
  } else if (fillType === 'limit' && trades.length > 0) {
    showToast(`Objednávka v booku spárována (${trades.length} obchodů)`);
  } else if (fillType === 'limit') {
    showToast('Objednávka v booku (tržní cena)');
  } else if (fillType === 'p2p') {
    showToast('Obchod s hráčem proveden');
  }
}

async function placeTrade(side, mode = 'market') {
  const quantity = Number(els.quantity.value);
  if (!state.sessionId || quantity <= 0) return;

  els.tradeMsg.textContent = '';
  setButtonsDisabled(true);

  try {
    const payload = await apiCall(() =>
      api.placeOrder(state.sessionId, state.selectedAssetId, side, quantity, mode)
    );
    const result = payload.result;
    tradeToast(result);

    renderPortfolio(result.portfolio);
    applyAssetPrices(state.assets);
    renderOpenOrders((await api.getOpenOrders(state.sessionId)).items);
    await renderOrderBook();
    await loadTransactions();
    const lb = await api.getLeaderboard();
    renderLeaderboard(lb.items);
  } catch (error) {
    if (!isSessionError(error)) {
      els.tradeMsg.textContent = error.message;
    }
  } finally {
    setButtonsDisabled(false);
  }
}

async function takeOrder(orderId) {
  const quantity = Number(els.quantity.value);
  if (!state.sessionId || !orderId || quantity <= 0) return;

  els.tradeMsg.textContent = '';
  setButtonsDisabled(true);

  try {
    const payload = await apiCall(() => api.takeOrder(state.sessionId, orderId, quantity));
    tradeToast(payload.result);
    renderPortfolio(payload.result.portfolio);
    renderOpenOrders((await api.getOpenOrders(state.sessionId)).items);
    await renderOrderBook();
    await loadTransactions();
    const lb = await api.getLeaderboard();
    renderLeaderboard(lb.items);
  } catch (error) {
    if (!isSessionError(error)) {
      els.tradeMsg.textContent = error.message;
    }
  } finally {
    setButtonsDisabled(false);
  }
}

function setButtonsDisabled(disabled) {
  [els.buyBtn, els.sellBtn, els.postBuyBtn, els.postSellBtn].forEach((btn) => {
    btn.disabled = disabled;
  });
}

async function endSession() {
  if (!state.sessionId) return;

  stopPricePoll();

  try {
    const payload = await api.endSession(state.sessionId);
    showToast(`Session ukončena. Skóre: ${Number(payload.leaderboardEntry.score).toFixed(2)}`);
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
els.nickname.addEventListener('keydown', (event) => {
  if (event.key === 'Enter') enterGame();
});
els.endBtn.addEventListener('click', endSession);
els.buyBtn.addEventListener('click', () => placeTrade('buy', 'market'));
els.sellBtn.addEventListener('click', () => placeTrade('sell', 'market'));
els.postBuyBtn.addEventListener('click', () => placeTrade('buy', 'limit'));
els.postSellBtn.addEventListener('click', () => placeTrade('sell', 'limit'));

els.assetList.addEventListener('click', async (event) => {
  const item = event.target.closest('[data-id]');
  if (!item) return;
  state.selectedAssetId = item.dataset.id;
  renderAssets();
  await Promise.all([renderOrderBook(), loadPriceChart()]);
});

els.orderbook.addEventListener('click', (event) => {
  const btn = event.target.closest('[data-order-id]');
  if (!btn || btn.disabled) return;
  takeOrder(btn.dataset.orderId);
});

createMarketSocket({
  onMessage(payload) {
    if (payload.type === 'price_tick') {
      applyAssetPrices(payload.items || []);
      if (state.sessionId) {
        refreshPortfolioOnly().catch(() => {});
        loadPriceChart().catch(() => {});
      }
    }

    if (payload.type === 'leaderboard_update') {
      renderLeaderboard(payload.items);
    }

    if (payload.type === 'orderbook_update' && payload.assetId === state.selectedAssetId) {
      renderOrderBookFromData(payload.orderbook);
    }

    if (payload.type === 'trade' && state.sessionId) {
      const trade = payload.trade || {};
      const involved =
        trade.sessionId === state.sessionId ||
        trade.buySessionId === state.sessionId ||
        trade.sellSessionId === state.sessionId;

      if (involved) {
        refreshAll().catch(() => {});
      } else if (payload.orderbook) {
        renderOrderBookFromData(payload.orderbook);
      } else if (payload.assetId === state.selectedAssetId) {
        renderOrderBook().catch(() => {});
      }
    }
  },
});

if (state.sessionId) {
  els.nickname.value = state.nickname;
  els.lobby.classList.add('hidden');
  els.game.classList.remove('hidden');
  els.playerName.textContent = state.nickname;
  apiCall(() => api.resumeSession(state.sessionId))
    .then(() => refreshAll())
    .then(() => startPricePoll())
    .catch((error) => {
      if (!isSessionError(error)) {
        clearSession('Nepodařilo se obnovit session. Vstupte znovu na burzu.');
      }
    });
}
