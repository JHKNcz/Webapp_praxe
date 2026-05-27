const API_BASE = '/api';

async function request(path, options = {}) {
  const response = await fetch(`${API_BASE}${path}`, {
    headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
    ...options,
  });

  const payload = await response.json();

  if (!response.ok || payload.ok === false) {
    throw new Error(payload.error?.message || payload.message || 'Request failed');
  }

  return payload;
}

export const api = {
  startSession(nickname) {
    return request('/session/start', {
      method: 'POST',
      body: JSON.stringify({ nickname }),
    });
  },

  endSession(sessionId) {
    return request('/session/end', {
      method: 'POST',
      body: JSON.stringify({ sessionId }),
    });
  },

  resumeSession(sessionId) {
    return request('/session/resume', {
      method: 'POST',
      body: JSON.stringify({ sessionId }),
    });
  },

  getAssets() {
    return request('/assets');
  },

  tickAssets() {
    return request('/assets/tick');
  },

  async getAssetDetail(assetId, limit = 40) {
    return request(`/assets/${encodeURIComponent(assetId)}?limit=${limit}`);
  },

  async getPortfolio(sessionId) {
    const payload = await request(`/portfolio?sessionId=${encodeURIComponent(sessionId)}`);
    return payload.portfolio;
  },

  async placeOrder(sessionId, assetId, side, quantity, mode = 'market', limitPrice = null) {
    const body = { sessionId, assetId, side, quantity, mode };
    if (limitPrice != null) {
      body.limitPrice = limitPrice;
    }
    const payload = await request('/orders', {
      method: 'POST',
      body: JSON.stringify(body),
    });
    return payload;
  },

  async takeOrder(sessionId, orderId, quantity) {
    const payload = await request(`/orders/${encodeURIComponent(orderId)}/take`, {
      method: 'POST',
      body: JSON.stringify({ sessionId, quantity }),
    });
    return payload;
  },

  async getOpenOrders(sessionId) {
    const payload = await request(`/orders?sessionId=${encodeURIComponent(sessionId)}`);
    return payload;
  },

  async cancelOrder(sessionId, orderId) {
    const payload = await request(`/orders/${encodeURIComponent(orderId)}`, {
      method: 'DELETE',
      body: JSON.stringify({ sessionId }),
    });
    return payload;
  },

  async getOrderBook(assetId) {
    const payload = await request(`/orderbook/${encodeURIComponent(assetId)}`);
    return payload;
  },

  async getTransactions(sessionId, limit = 50) {
    const payload = await request(
      `/transactions?sessionId=${encodeURIComponent(sessionId)}&limit=${limit}`
    );
    return payload;
  },

  getLeaderboard() {
    return request('/leaderboard');
  },
};
