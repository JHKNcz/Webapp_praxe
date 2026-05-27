// market/frontend/js/app.js
import { api } from './api.js';
import { createMarketSocket } from './ws.js';
import { COPY } from './ui-copy.js';
import {
  renderHero,
  updateHeroRank,
  renderAssets,
  renderLivePrice,
  renderPriceChart,
  renderPortfolio,
  renderOrderBook,
  renderOpenOrders,
  renderLeaderboard,
  renderTransactions,
} from './ui-render.js';

const PRICE_POLL_MS = 5000;

const state = {
  sessionId:       localStorage.getItem('market.sessionId') || '',
  nickname:        localStorage.getItem('market.nickname')  || '',
  selectedAssetId: 'asset-1',
  assets:          [],
  lastPrices:      {},
  lastPriceTickAt: 0,
  pricePollTimer:  null,
};

const els = {
  lobby:          document.getElementById('lobby'),
  game:           document.getElementById('game'),
  nickname:       document.getElementById('nickname'),
  enterBtn:       document.getElementById('enter-btn'),
  lobbyError:     document.getElementById('lobby-error'),
  playerName:     document.getElementById('player-name'),
  heroTotal:      document.getElementById('hero-total'),
  heroCash:       document.getElementById('hero-cash'),
  heroPnl:        document.getElementById('hero-pnl'),
  heroPnlPct:     document.getElementById('hero-pnl-pct'),
  heroRank:       document.getElementById('hero-rank'),
  topbarPrice:    document.getElementById('topbar-price'),
  endBtn:         document.getElementById('end-btn'),
  assetList:      document.getElementById('asset-list'),
  portfolio:      document.getElementById('portfolio'),
  orderbook:      document.getElementById('orderbook'),
  livePrice:      document.getElementById('live-price'),
  livePriceName:  document.getElementById('live-price-name'),
  livePriceValue: document.getElementById('live-price-value'),
  livePriceTime:  document.getElementById('live-price-time'),
  selectedAsset:  document.getElementById('selected-asset'),
  quantity:       document.getElementById('quantity'),
  buyBtn:         document.getElementById('buy-btn'),
  sellBtn:        document.getElementById('sell-btn'),
  postBuyBtn:     document.getElementById('post-buy-btn'),
  postSellBtn:    document.getElementById('post-sell-btn'),
  tradeMsg:       document.getElementById('trade-msg'),
  openOrders:     document.getElementById('open-orders'),
  leaderboard:    document.getElementById('leaderboard'),
  transactions:   document.getElementById('transactions'),
  priceChart:     document.getElementById('price-chart'),
  toast:          document.getElementById('toast'),
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
  state.nickname  = '';
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
  const msg = error?.message || '';
  return msg.includes('Session not found') || msg.includes('Session is already closed');
}

async function apiCall(fn) {
  try {
    return await fn();
  } catch (error) {
    if (isSessionError(error)) {
      clearSession(COPY.sessionExpired);
    }
    throw error;
  }
}

function getSelectedAsset() {
  return state.assets.find((a) => a.id === state.selectedAssetId) || null;
}

function applyAssetPrices(items) {
  const prev = { ...state.lastPrices };
  state.assets = items || [];
  state.assets.forEach((a) => { prev[a.id] = state.lastPrices[a.id]; });
  state.lastPriceTickAt = Math.floor(Date.now() / 1000);
  renderAssets(els, state.assets, state.selectedAssetId, state.lastPrices);
  state.assets.forEach((a) => { state.lastPrices[a.id] = Number(a.lastPrice); });
  renderLivePrice(els, getSelectedAsset(), prev, state.lastPriceTickAt);
}

async function refreshPortfolioOnly() {
  if (!state.sessionId) return;
  const summary = await apiCall(() => api.getPortfolio(state.sessionId));
  renderPortfolio(els, summary, state.assets);
  renderHero(els, summary, state.nickname);
}

async function loadPriceChart() {
  if (!state.selectedAssetId) return;
  try {
    const payload = await api.getAssetDetail(state.selectedAssetId);
    renderPriceChart(els.priceChart, payload.history || []);
  } catch {
    renderPriceChart(els.priceChart, []);
  }
}

async function pollMarketPrices() {
  if (!state.sessionId) return;
  const payload = await apiCall(() => api.tickAssets());
  applyAssetPrices(payload.items || []);
  await refreshPortfolioOnly();
  await loadPriceChart();
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
  state.assets.forEach((a) => { state.lastPrices[a.id] = Number(a.lastPrice); });

  renderAssets(els, state.assets, state.selectedAssetId, state.lastPrices);
  renderLivePrice(els, getSelectedAsset(), state.lastPrices, state.lastPriceTickAt);
  renderPortfolio(els, portfolioPayload, state.assets);
  renderHero(els, portfolioPayload, state.nickname);
  renderOpenOrders(els, ordersPayload.items, state.assets);
  renderLeaderboard(els, leaderboardPayload.items);
  updateHeroRank(els, leaderboardPayload.items, state.sessionId);
  await Promise.all([
    (async () => {
      const book = await api.getOrderBook(state.selectedAssetId);
      renderOrderBook(els, book.orderbook, state.sessionId);
    })(),
    (async () => {
      const tx = await api.getTransactions(state.sessionId);
      renderTransactions(els, tx.items, state.assets);
    })(),
    loadPriceChart(),
  ]);
}

function tradeToast(result) {
  const fillType = result.fillType || 'market';
  const trades   = result.trades  || [];
  if (fillType === 'market') {
    showToast(COPY.toastFilledMarket);
  } else if (fillType === 'limit' && trades.length > 0) {
    showToast(COPY.toastOrderMatched(trades.length));
  } else if (fillType === 'limit') {
    showToast(COPY.toastOrderPosted);
  } else if (fillType === 'p2p') {
    showToast(COPY.toastP2P);
  }
}

async function enterGame() {
  const nickname = els.nickname.value.trim();
  showLobbyError('');
  try {
    const payload = await api.startSession(nickname);
    const session = payload.session;
    state.sessionId = session.sessionId;
    state.nickname  = session.nickname;
    localStorage.setItem('market.sessionId', state.sessionId);
    localStorage.setItem('market.nickname',  state.nickname);
    els.lobby.classList.add('hidden');
    els.game.classList.remove('hidden');
    await refreshAll();
    startPricePoll();
    showToast(COPY.toastWelcome);
  } catch (error) {
    showLobbyError(error.message);
  }
}

async function endSession() {
  if (!state.sessionId) return;
  stopPricePoll();
  try {
    const payload = await api.endSession(state.sessionId);
    const score   = Number(payload.leaderboardEntry.score).toFixed(2);
    showToast(COPY.toastSessionEnded(score));
  } catch (error) {
    showToast(error.message);
  }
  localStorage.removeItem('market.sessionId');
  localStorage.removeItem('market.nickname');
  state.sessionId = '';
  els.game.classList.add('hidden');
  els.lobby.classList.remove('hidden');
}

function setButtonsDisabled(disabled) {
  [els.buyBtn, els.sellBtn, els.postBuyBtn, els.postSellBtn].forEach(
    (btn) => { btn.disabled = disabled; }
  );
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
    renderPortfolio(els, result.portfolio, state.assets);
    renderHero(els, result.portfolio, state.nickname);
    const [orders, book, lb, tx] = await Promise.all([
      api.getOpenOrders(state.sessionId),
      api.getOrderBook(state.selectedAssetId),
      api.getLeaderboard(),
      api.getTransactions(state.sessionId),
    ]);
    renderOpenOrders(els, orders.items, state.assets);
    renderOrderBook(els, book.orderbook, state.sessionId);
    renderLeaderboard(els, lb.items);
    updateHeroRank(els, lb.items, state.sessionId);
    renderTransactions(els, tx.items, state.assets);
  } catch (error) {
    if (!isSessionError(error)) els.tradeMsg.textContent = error.message;
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
    renderPortfolio(els, payload.result.portfolio, state.assets);
    renderHero(els, payload.result.portfolio, state.nickname);
    const [orders, book, lb, tx] = await Promise.all([
      api.getOpenOrders(state.sessionId),
      api.getOrderBook(state.selectedAssetId),
      api.getLeaderboard(),
      api.getTransactions(state.sessionId),
    ]);
    renderOpenOrders(els, orders.items, state.assets);
    renderOrderBook(els, book.orderbook, state.sessionId);
    renderLeaderboard(els, lb.items);
    updateHeroRank(els, lb.items, state.sessionId);
    renderTransactions(els, tx.items, state.assets);
  } catch (error) {
    if (!isSessionError(error)) els.tradeMsg.textContent = error.message;
  } finally {
    setButtonsDisabled(false);
  }
}

els.enterBtn.addEventListener('click', enterGame);
els.nickname.addEventListener('keydown', (e) => { if (e.key === 'Enter') enterGame(); });
els.endBtn.addEventListener('click', endSession);
els.buyBtn.addEventListener('click',     () => placeTrade('buy',  'market'));
els.sellBtn.addEventListener('click',    () => placeTrade('sell', 'market'));
els.postBuyBtn.addEventListener('click', () => placeTrade('buy',  'limit'));
els.postSellBtn.addEventListener('click',() => placeTrade('sell', 'limit'));

els.assetList.addEventListener('click', async (e) => {
  const item = e.target.closest('[data-id]');
  if (!item) return;
  state.selectedAssetId = item.dataset.id;
  renderAssets(els, state.assets, state.selectedAssetId, state.lastPrices);
  const [book] = await Promise.all([
    api.getOrderBook(state.selectedAssetId),
    loadPriceChart(),
  ]);
  renderOrderBook(els, book.orderbook, state.sessionId);
});

els.orderbook.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-order-id]');
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
      renderLeaderboard(els, payload.items);
      updateHeroRank(els, payload.items, state.sessionId);
    }

    if (payload.type === 'orderbook_update' && payload.assetId === state.selectedAssetId) {
      renderOrderBook(els, payload.orderbook, state.sessionId);
    }

    if (payload.type === 'trade' && state.sessionId) {
      const trade    = payload.trade || {};
      const involved =
        trade.sessionId     === state.sessionId ||
        trade.buySessionId  === state.sessionId ||
        trade.sellSessionId === state.sessionId;
      if (involved) {
        refreshAll().catch(() => {});
      } else if (payload.orderbook) {
        renderOrderBook(els, payload.orderbook, state.sessionId);
      } else if (payload.assetId === state.selectedAssetId) {
        api.getOrderBook(state.selectedAssetId)
          .then((b) => renderOrderBook(els, b.orderbook, state.sessionId))
          .catch(() => {});
      }
    }
  },
});

if (state.sessionId) {
  els.nickname.value = state.nickname;
  els.lobby.classList.add('hidden');
  els.game.classList.remove('hidden');
  apiCall(() => api.resumeSession(state.sessionId))
    .then(() => refreshAll())
    .then(() => startPricePoll())
    .catch((error) => {
      if (!isSessionError(error)) {
        clearSession(COPY.sessionResumeFailed);
      }
    });
}
