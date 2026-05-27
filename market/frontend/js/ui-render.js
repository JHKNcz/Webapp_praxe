// market/frontend/js/ui-render.js
import { COPY } from './ui-copy.js';
import {
  formatValue,
  formatPnl,
  formatPercent,
  pnlClass,
  formatTime,
  formatTimeShort,
  phaseLabel,
  leaderboardReturn,
  rankBadge,
} from './ui-state.js';

// ── Hero block ───────────────────────────────────────────────────────────────

export function renderHero(els, summary, nickname) {
  const pnl = Number(summary.pnl ?? 0);
  const pnlPct = Number(summary.pnlPercent ?? 0);
  els.heroTotal.textContent = formatValue(summary.totalValue);
  els.heroCash.textContent = formatValue(summary.cash);
  els.heroPnl.textContent = formatPnl(pnl);
  els.heroPnl.className = `hero-stat-value ${pnlClass(pnl)}`;
  els.heroPnlPct.textContent = formatPercent(pnlPct);
  els.heroPnlPct.className = `hero-stat-value ${pnlClass(pnlPct)}`;
  if (nickname) els.playerName.textContent = nickname;
}

export function updateHeroRank(els, items, sessionId) {
  const idx = (items || []).findIndex((e) => e.sessionId === sessionId);
  els.heroRank.textContent = idx >= 0 ? `#${idx + 1}` : '';
}

// ── Assets list ──────────────────────────────────────────────────────────────

export function renderAssets(els, assets, selectedAssetId, lastPrices, assetPhases = {}) {
  els.assetList.innerHTML = assets
    .map((asset) => {
      const selected = asset.id === selectedAssetId ? 'selected' : '';
      const prev = lastPrices[asset.id];
      const price = Number(asset.lastPrice);
      let dir = '';
      if (prev !== undefined) {
        dir = price > prev ? 'price-up' : price < prev ? 'price-down' : '';
      }
      return `<li class="${selected}" data-id="${asset.id}">
        <span class="asset-name">${asset.name}</span>
        <span class="asset-price ${dir}">${formatValue(asset.lastPrice)}</span>
      </li>`;
    })
    .join('');
}

// ── Live price + topbar ──────────────────────────────────────────────────────

export function renderLivePrice(els, asset, lastPrices, lastPriceTickAt) {
  if (!asset) {
    els.livePriceName.textContent = '—';
    els.livePriceValue.textContent = '—';
    els.livePriceTime.textContent = '';
    els.topbarPrice.textContent = '';
    els.selectedAsset.textContent = '—';
    return;
  }
  const price = Number(asset.lastPrice);
  const prev = lastPrices[asset.id];
  let flash = '';
  if (prev !== undefined && prev !== price) {
    flash = price > prev ? 'flash-up' : 'flash-down';
    els.livePrice.classList.remove('flash-up', 'flash-down');
    void els.livePrice.offsetWidth;
    els.livePrice.classList.add(flash);
  }
  els.livePriceName.textContent = asset.name;
  els.livePriceValue.textContent = formatValue(price);
  const dir = flash === 'flash-up' ? 'price-up' : flash === 'flash-down' ? 'price-down' : '';
  els.livePriceValue.className = `live-price-value ${dir}`;
  els.livePriceTime.textContent = lastPriceTickAt
    ? COPY.updatedAt(formatTime(lastPriceTickAt))
    : '';
  els.topbarPrice.textContent = `${asset.name}: ${formatValue(price)}`;
  els.selectedAsset.textContent = `${asset.name} @ ${formatValue(price)}`;
}

function escapeHtml(text) {
  return String(text)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;');
}

export function renderNewsFeed(feedEl, emptyEl, items) {
  if (!feedEl) return;
  const list = items || [];
  if (emptyEl) {
    emptyEl.classList.toggle('hidden', list.length > 0);
  }
  if (list.length === 0) {
    feedEl.innerHTML = '';
    return;
  }
  feedEl.innerHTML = list
    .map((item, index) => {
      const phase = item.phase || 'normal';
      const fresh = index === 0 ? ' news-item--fresh' : '';
      return `<li class="news-item news-item--${phase}${fresh}" data-asset-id="${escapeHtml(item.assetId || '')}">
        <span class="news-time">${formatTime(item.ts)}</span>
        <span class="news-phase">${phaseLabel(phase)}</span>
        <span class="news-headline">${escapeHtml(item.headline)}</span>
      </li>`;
    })
    .join('');
}

// ── Price chart ──────────────────────────────────────────────────────────────

export function renderPriceChart(priceChart, history, currentPhase = 'normal', newsMarkers = []) {
  if (!priceChart) return;
  const rawPoints = (history || [])
    .map((p) => ({ price: Number(p.price), ts: Number(p.ts || p.timestamp || 0) }))
    .filter((p) => Number.isFinite(p.price) && p.ts > 0);
  if (rawPoints.length < 2) {
    priceChart.innerHTML = '';
    return;
  }

  const sorted = rawPoints.sort((a, b) => a.ts - b.ts);
  const deduped = [];
  for (const point of sorted) {
    const last = deduped[deduped.length - 1];
    if (last && last.ts === point.ts) {
      last.price = point.price;
    } else {
      deduped.push({ ...point });
    }
  }
  const points = deduped.length > 120 ? deduped.slice(-120) : deduped;
  if (points.length < 2) {
    priceChart.innerHTML = '';
    return;
  }

  const width = 640;
  const height = 220;
  const padL = 42;
  const padR = 12;
  const padT = 22;
  const padB = 28;
  const chartW = width - padL - padR;
  const chartH = height - padT - padB;

  const prices = points.map((p) => p.price);
  const min = Math.min(...prices);
  const max = Math.max(...prices);
  const range = max - min || 1;
  const yPad = range * 0.08;
  const yMin = min - yPad;
  const yMax = max + yPad;
  const yRange = yMax - yMin || 1;
  const tMin = points[0].ts;
  const tMax = points[points.length - 1].ts;
  const tRange = tMax - tMin || 1;

  const toX = (ts) => padL + ((ts - tMin) / tRange) * chartW;
  const toY = (price) => padT + chartH - ((price - yMin) / yRange) * chartH;

  const coords = points.map((p) => `${toX(p.ts).toFixed(1)},${toY(p.price).toFixed(1)}`);
  const line = coords.join(' ');
  const area = `${padL},${padT + chartH} ${line} ${padL + chartW},${padT + chartH}`;

  const markerLines = (newsMarkers || [])
    .filter((m) => m.ts >= tMin && m.ts <= tMax + 2)
    .map((m) => {
      const x = toX(m.ts).toFixed(1);
      const phase = m.phase || 'normal';
      return `<line class="chart-event-line chart-event-line--${phase}" x1="${x}" y1="${padT}" x2="${x}" y2="${padT + chartH}" />`;
    })
    .join('');

  const latestPrice = prices[prices.length - 1];
  const phaseText = phaseLabel(currentPhase || 'normal');

  priceChart.setAttribute('viewBox', `0 0 ${width} ${height}`);
  priceChart.innerHTML = `
    ${markerLines}
    <polygon class="price-chart-area" points="${area}" />
    <polyline class="price-chart-line" points="${line}" />
    <text class="chart-time-label" x="${padL}" y="${height - 6}" text-anchor="start">${formatTimeShort(tMin)}</text>
    <text class="chart-time-label" x="${padL + chartW}" y="${height - 6}" text-anchor="end">${formatTimeShort(tMax)}</text>
    <text class="chart-price-label" x="${width - padR}" y="${padT + 12}" text-anchor="end">${formatValue(latestPrice)}</text>
    <text class="chart-phase-label" x="${padL}" y="${padT + 12}" text-anchor="start">${phaseText}</text>
  `;
}

// ── Portfolio ────────────────────────────────────────────────────────────────

export function renderPortfolio(els, summary, assets) {
  const pnl = Number(summary.pnl ?? 0);
  const pnlPct = Number(summary.pnlPercent ?? 0);

  function assetName(id) {
    return assets.find((a) => a.id === id)?.name || id;
  }

  const holdings = (summary.holdings || [])
    .map((h) => {
      const hPnl = Number(h.unrealizedPnl ?? 0);
      return `<div class="holding-row">
        <span>${assetName(h.assetId)}: ${h.quantity}×</span>
        <span>@ ${formatValue(h.currentPrice)}</span>
        <span class="${pnlClass(hPnl)}">(${formatPnl(hPnl)})</span>
      </div>`;
    })
    .join('');

  els.portfolio.innerHTML = `
    <div class="pf-row"><span class="pf-label">Cash</span><span>${formatValue(summary.cash)}</span></div>
    <div class="pf-row pf-total"><span class="pf-label">Total</span><strong>${formatValue(summary.totalValue)}</strong></div>
    <div class="pf-row ${pnlClass(pnl)}"><span class="pf-label">P&amp;L</span><span>${formatPnl(pnl)} (${formatPercent(pnlPct)})</span></div>
    ${holdings || `<div class="pf-empty">${COPY.portfolioNoPositions}</div>`}
  `;
}

// ── Order book ───────────────────────────────────────────────────────────────

function renderObRows(rows, sessionId) {
  return (rows || [])
    .map((row) => {
      const isOwn = row.sessionId === sessionId;
      const action = row.side === 'sell' ? COPY.actionBuy : COPY.actionSell;
      return `<div class="ob-row ${row.side}">
        <span class="ob-nick">${row.nickname}</span>
        <span class="ob-price">${formatValue(row.price)}</span>
        <span class="ob-qty">${row.remainingQty}</span>
        <button type="button" class="ob-take btn btn-ghost btn-xs" data-order-id="${row.id}" ${isOwn ? 'disabled' : ''}>${action}</button>
      </div>`;
    })
    .join('');
}

export function renderOrderBook(els, book, sessionId) {
  const asks = renderObRows(book.asks, sessionId);
  const bids = renderObRows(book.bids, sessionId);
  const empty = !asks && !bids;
  els.orderbook.innerHTML = `
    <div class="ob-last">Last: <strong>${formatValue(book.lastPrice)}</strong></div>
    <div class="ob-section">
      <div class="ob-head">${COPY.orderbookAsksLabel}</div>
      ${asks || `<div class="ob-empty">${COPY.orderbookNoAsks}</div>`}
    </div>
    <div class="ob-section">
      <div class="ob-head">${COPY.orderbookBidsLabel}</div>
      ${bids || `<div class="ob-empty">${COPY.orderbookNoBids}</div>`}
    </div>
    ${empty ? `<p class="hint">${COPY.orderbookEmptyHint}</p>` : ''}
  `;
}

// ── Open orders ──────────────────────────────────────────────────────────────

export function renderOpenOrders(els, items, assets) {
  function assetName(id) {
    return assets.find((a) => a.id === id)?.name || id;
  }
  els.openOrders.innerHTML =
    (items || [])
      .map(
        (o) =>
          `<li>${o.side.toUpperCase()} ${o.remainingQty}× ${assetName(o.assetId)} @ ${formatValue(o.price)} <span class="order-status">(${o.status})</span> <button type="button" class="order-cancel btn btn-ghost btn-xs" data-cancel-order-id="${o.id}">${COPY.actionCancelOrder}</button></li>`
      )
      .join('') || `<li class="empty-state">${COPY.noOpenOrders}</li>`;
}

// ── Leaderboard (Hall of Fame) ───────────────────────────────────────────────

export function renderLeaderboard(els, items) {
  if (!items || items.length === 0) {
    els.leaderboard.innerHTML = `<li class="empty-state">${COPY.noScores}</li>`;
    return;
  }
  els.leaderboard.innerHTML = items
    .map((entry, i) => {
      const ret = leaderboardReturn(entry.score);
      const retClass = ret >= 0 ? 'price-up' : 'price-down';
      const retStr = formatPercent(ret);
      return `<li class="lb-row ${i < 3 ? `rank-${i + 1}` : 'rank-other'}">
        <span class="lb-badge">${rankBadge(i)}</span>
        <span class="lb-entry">
          <span class="lb-line">${entry.displayName} — ${formatValue(entry.score)}</span>
          <span class="lb-pct ${retClass}">(${retStr})</span>
        </span>
      </li>`;
    })
    .join('');
}

// ── Transactions ─────────────────────────────────────────────────────────────

export function renderTransactions(els, items, assets) {
  function assetName(id) {
    return assets.find((a) => a.id === id)?.name || id;
  }
  if (!items || items.length === 0) {
    els.transactions.innerHTML = `<div class="ob-empty">${COPY.noTrades}</div>`;
    return;
  }
  els.transactions.innerHTML = `
    <table class="tx-table">
      <thead>
        <tr>
          <th>${COPY.txTime}</th>
          <th>${COPY.txSide}</th>
          <th>${COPY.txAsset}</th>
          <th>${COPY.txQty}</th>
          <th>${COPY.txPrice}</th>
          <th>${COPY.txTotal}</th>
          <th>${COPY.txVia}</th>
        </tr>
      </thead>
      <tbody>
        ${items
          .map(
            (tx) => `<tr>
              <td>${formatTime(tx.timestamp)}</td>
              <td class="${tx.side === 'buy' ? 'price-up' : 'price-down'}">${tx.side === 'buy' ? COPY.txBuy : COPY.txSell}</td>
              <td>${assetName(tx.assetId)}</td>
              <td>${tx.quantity}</td>
              <td>${formatValue(tx.price)}</td>
              <td>${formatValue(tx.total)}</td>
              <td>${tx.type === 'market' ? COPY.txMarket : tx.counterparty}</td>
            </tr>`
          )
          .join('')}
      </tbody>
    </table>
  `;
}
