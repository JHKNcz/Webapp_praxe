// market/frontend/js/app.js
import { api } from './api.js';
import { createMarketSocket } from './ws.js';
import { COPY } from './ui-copy.js';
import {
  renderHero,
  updateHeroRank,
  renderAssets,
  renderLivePrice,
  renderNewsFeed,
  renderPriceChart,
  renderPortfolio,
  renderOrderBook,
  renderOpenOrders,
  renderLeaderboard,
  renderTransactions,
} from './ui-render.js';
import { formatClockNow } from './ui-state.js';

const state = {
  sessionId:       localStorage.getItem('market.sessionId') || '',
  nickname:        localStorage.getItem('market.nickname')  || '',
  selectedAssetId: 'asset-1',
  assets:          [],
  assetPhases:     {},
  lastPrices:      {},
  lastPriceTickAt: 0,
  chartHistory:    {},
  chartNewsMarkers: {},
  newsItems:       [],
};

const CHART_LIMIT = 200;
const MAX_NEWS_DISPLAY = 15;
const TOAST_VARIANTS = ['toast--p2p'];
let toastTimer = null;
let newsAlertTimer = null;
let clockTimer = null;
let chartLoadToken = 0;

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
  marketClock:    document.getElementById('market-clock'),
  newsFeed:       document.getElementById('news-feed'),
  newsEmpty:      document.getElementById('news-empty'),
  newsAlert:      document.getElementById('news-alert'),
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
  limitPrice:     document.getElementById('limit-price'),
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

function pushNews(event) {
  if (!event?.headline) return;

  const item = {
    ts: event.ts || Math.floor(Date.now() / 1000),
    headline: String(event.headline).trim(),
    assetId: event.assetId || '',
    assetName: event.assetName || '',
    phase: event.phase || 'normal',
  };

  if (!item.headline) return;
  if (state.newsItems[0]?.headline === item.headline && state.newsItems[0]?.ts === item.ts) {
    return;
  }

  state.newsItems.unshift(item);
  if (state.newsItems.length > MAX_NEWS_DISPLAY) {
    state.newsItems.length = MAX_NEWS_DISPLAY;
  }

  if (!state.chartNewsMarkers[item.assetId]) {
    state.chartNewsMarkers[item.assetId] = [];
  }
  state.chartNewsMarkers[item.assetId].push({ ts: item.ts, phase: item.phase });
  const markers = state.chartNewsMarkers[item.assetId];
  if (markers.length > 24) {
    state.chartNewsMarkers[item.assetId] = markers.slice(-24);
  }

  renderNewsFeed(els.newsFeed, els.newsEmpty, state.newsItems);
  showNewsAlert(item);

  if (item.assetId === state.selectedAssetId) {
    els.livePrice.classList.remove('flash-news');
    void els.livePrice.offsetWidth;
    els.livePrice.classList.add('flash-news');
    renderSelectedChart();
  }
}

function showNewsAlert(item) {
  if (!els.newsAlert) return;
  if (newsAlertTimer) {
    clearTimeout(newsAlertTimer);
    newsAlertTimer = null;
  }
  els.newsAlert.className = 'news-alert';
  els.newsAlert.innerHTML = `<span class="news-alert-time">${formatClockNow()}</span> ${item.headline}`;
  els.newsAlert.classList.remove('hidden');
  newsAlertTimer = setTimeout(() => {
    els.newsAlert.classList.add('hidden');
  }, 3200);
}

function startMarketClock() {
  const tick = () => {
    if (!els.marketClock) return;
    const now = new Date();
    els.marketClock.textContent = formatClockNow(now);
    els.marketClock.dateTime = now.toISOString();
  };
  tick();
  if (clockTimer) clearInterval(clockTimer);
  clockTimer = setInterval(tick, 1000);
}

function mergeChartPoint(assetId, price, ts) {
  if (!state.chartHistory[assetId]) {
    state.chartHistory[assetId] = [];
  }
  const hist = state.chartHistory[assetId];
  const last = hist[hist.length - 1];
  if (last && last.ts === ts) {
    last.price = price;
    return;
  }
  hist.push({ price, ts });
  if (hist.length > CHART_LIMIT) {
    hist.shift();
  }
}

function renderSelectedChart() {
  const id = state.selectedAssetId;
  renderPriceChart(
    els.priceChart,
    state.chartHistory[id] || [],
    state.assetPhases[id] || 'normal',
    state.chartNewsMarkers[id] || []
  );
}

function showToast(message, extraClass = '') {
  if (toastTimer) {
    clearTimeout(toastTimer);
    toastTimer = null;
  }
  els.toast.textContent = message;
  TOAST_VARIANTS.forEach((cssClass) => els.toast.classList.remove(cssClass));
  if (extraClass) {
    els.toast.classList.add(extraClass);
  }
  els.toast.classList.remove('hidden');
  toastTimer = setTimeout(() => {
    els.toast.classList.add('hidden');
  }, 2500);
}

function pulseTradeBtn(button) {
  if (!button) return;
  button.classList.remove('btn-pulse');
  void button.offsetWidth;
  button.classList.add('btn-pulse');
}

function fireConfetti() {
  const confetti = window.confetti;
  if (typeof confetti !== 'function') return;
  confetti({
    particleCount: 100,
    spread: 68,
    startVelocity: 40,
    scalar: 0.95,
    origin: { y: 0.72 },
  });
}

function showLobbyError(message) {
  els.lobbyError.textContent = message;
  els.lobbyError.classList.toggle('hidden', !message);
}

function clearSession(message = '') {
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

function syncDefaultLimitPrice() {
  const asset = getSelectedAsset();
  if (!asset || els.limitPrice._userEdited) return;
  els.limitPrice.value = Number(asset.lastPrice).toFixed(2);
}

function applyAssetPrices(items, tickTs) {
  const prev = { ...state.lastPrices };
  const ts = tickTs || Math.floor(Date.now() / 1000);
  state.assets = items || [];
  state.assetPhases = {};
  state.assets.forEach((a) => { prev[a.id] = state.lastPrices[a.id]; });
  state.assets.forEach((a) => {
    state.assetPhases[a.id] = a.phase || 'normal';
    mergeChartPoint(a.id, Number(a.lastPrice), ts);
  });
  state.lastPriceTickAt = ts;
  renderAssets(els, state.assets, state.selectedAssetId, state.lastPrices, state.assetPhases);
  state.assets.forEach((a) => { state.lastPrices[a.id] = Number(a.lastPrice); });
  renderLivePrice(els, getSelectedAsset(), prev, state.lastPriceTickAt);
  syncDefaultLimitPrice();
  renderSelectedChart();
}

async function refreshPortfolioOnly() {
  if (!state.sessionId) return;
  const summary = await apiCall(() => api.getPortfolio(state.sessionId));
  renderPortfolio(els, summary, state.assets);
  renderHero(els, summary, state.nickname);
}

async function loadPriceChart() {
  if (!state.selectedAssetId) return;
  const assetId = state.selectedAssetId;
  const token = ++chartLoadToken;
  try {
    const payload = await api.getAssetDetail(assetId, CHART_LIMIT);
    if (token !== chartLoadToken || assetId !== state.selectedAssetId) return;
    state.chartHistory[assetId] = (payload.history || []).map((p) => ({
      price: Number(p.price),
      ts: Number(p.ts || 0),
    }));
    renderSelectedChart();
  } catch {
    if (token !== chartLoadToken) return;
    renderSelectedChart();
  }
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
  state.assetPhases = {};
  state.assets.forEach((a) => { state.assetPhases[a.id] = a.phase || 'normal'; });
  state.assets.forEach((a) => { state.lastPrices[a.id] = Number(a.lastPrice); });

  renderAssets(els, state.assets, state.selectedAssetId, state.lastPrices, state.assetPhases);
  renderLivePrice(els, getSelectedAsset(), state.lastPrices, state.lastPriceTickAt);
  syncDefaultLimitPrice();
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

function tradeToast(result, context = {}) {
  const fillType = result.fillType || 'market';
  const trades   = result.trades  || [];
  const side = context.side === 'sell' ? 'sell' : 'buy';
  if (fillType === 'market') {
    showToast(side === 'sell' ? 'Market sell filled' : 'Market buy filled');
    pulseTradeBtn(side === 'sell' ? els.sellBtn : els.buyBtn);
  } else if (fillType === 'limit' && trades.length > 0) {
    showToast(trades.length > 1 ? `Limit matched (${trades.length} fills)` : 'Limit matched');
    pulseTradeBtn(side === 'sell' ? els.postSellBtn : els.postBuyBtn);
  } else if (fillType === 'limit') {
    showToast('Limit posted');
    pulseTradeBtn(side === 'sell' ? els.postSellBtn : els.postBuyBtn);
  } else if (fillType === 'p2p') {
    showToast('P2P deal closed', 'toast--p2p');
    pulseTradeBtn(els.buyBtn);
    pulseTradeBtn(els.sellBtn);
  }

  const pnlPercent = Number(result?.portfolio?.pnlPercent ?? 0);
  if (pnlPercent >= 25) {
    fireConfetti();
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
    startMarketClock();
    await refreshAll();
    showToast(COPY.toastWelcome);
  } catch (error) {
    showLobbyError(error.message);
  }
}

async function endSession() {
  if (!state.sessionId) return;
  if (!confirm('End your session and submit your final score?')) return;
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
  if (!state.sessionId) return;
  if (quantity <= 0) {
    els.tradeMsg.textContent = COPY.quantityError;
    return;
  }
  els.tradeMsg.textContent = '';
  setButtonsDisabled(true);
  try {
    const limitPrice = mode === 'limit' ? Number(els.limitPrice.value) : null;
    const payload = await apiCall(() =>
      api.placeOrder(state.sessionId, state.selectedAssetId, side, quantity, mode, limitPrice)
    );
    const result = payload.result;
    tradeToast(result, { side });
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
    tradeToast(payload.result, { side: 'buy' });
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

async function cancelOrder(orderId) {
  if (!state.sessionId || !orderId) return;
  els.tradeMsg.textContent = '';
  setButtonsDisabled(true);
  try {
    await apiCall(() => api.cancelOrder(state.sessionId, orderId));
    showToast(COPY.actionCancelOrder);
    const [orders, portfolio] = await Promise.all([
      api.getOpenOrders(state.sessionId),
      api.getPortfolio(state.sessionId),
    ]);
    renderOpenOrders(els, orders.items, state.assets);
    renderPortfolio(els, portfolio, state.assets);
    renderHero(els, portfolio, state.nickname);
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

els.limitPrice._userEdited = false;
els.limitPrice.addEventListener('input', () => { els.limitPrice._userEdited = true; });

els.assetList.addEventListener('click', async (e) => {
  const item = e.target.closest('[data-id]');
  if (!item) return;
  state.selectedAssetId = item.dataset.id;
  els.limitPrice._userEdited = false;
  renderAssets(els, state.assets, state.selectedAssetId, state.lastPrices, state.assetPhases);
  renderSelectedChart();
  syncDefaultLimitPrice();
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

els.openOrders.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-cancel-order-id]');
  if (!btn || btn.disabled) return;
  cancelOrder(btn.dataset.cancelOrderId);
});

createMarketSocket({
  onMessage(payload) {
    if (payload.type === 'price_tick') {
      applyAssetPrices(payload.items || [], payload.ts);
      if (payload.event?.headline) {
        pushNews(payload.event);
      }
      if (state.sessionId) {
        refreshPortfolioOnly().catch(() => {});
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

startMarketClock();

if (state.sessionId) {
  els.nickname.value = state.nickname;
  els.lobby.classList.add('hidden');
  els.game.classList.remove('hidden');
  apiCall(() => api.resumeSession(state.sessionId))
    .then(() => refreshAll())
    .catch((error) => {
      if (!isSessionError(error)) {
        clearSession(COPY.sessionResumeFailed);
      }
    });
}
