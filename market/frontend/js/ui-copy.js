// market/frontend/js/ui-copy.js
export const COPY = {
  // Lobby
  lobbyTagline: 'Simulated stock exchange — maximize your portfolio value.',
  nicknameLabel: 'Nickname',
  nicknamePlaceholder: 'Trader42',
  enterBtn: 'Enter the Market',

  // Top bar
  endSessionBtn: 'End Session',

  // Panels
  instrumentsTitle: 'Instruments',
  portfolioTitle: 'Portfolio',
  orderbookTitle: 'Order Book (Players)',
  tradingTitle: 'Trading',
  leaderboardTitle: '🏆 Hall of Fame',
  tradeHistoryTitle: 'Trade History',
  openOrdersTitle: 'Open Orders',

  // Hero block
  heroPortfolioLabel: 'Portfolio',
  heroCashLabel: 'Cash',
  heroPnlLabel: 'P&L',
  heroReturnLabel: 'Return',

  // Portfolio
  portfolioNoPositions: 'No open positions',

  // Orderbook
  orderbookHint: 'Tap Buy/Sell on a row to trade with that player (uses quantity below).',
  orderbookAsksLabel: 'Asks (Sell orders)',
  orderbookBidsLabel: 'Bids (Buy orders)',
  orderbookNoAsks: 'No sell orders',
  orderbookNoBids: 'No buy orders',
  orderbookEmptyHint: 'Empty book — trade via Market or place a Post order.',

  // Live price
  livePriceLabel: 'Live Price',
  priceHistoryLabel: 'Price History',

  // Trading
  selectedLabel: 'Selected',
  quantityLabel: 'Quantity',
  tradeHint: 'Market = instant fill against the exchange. Post = adds your order to the book at current market price.',
  marketBuyBtn: 'Market Buy',
  marketSellBtn: 'Market Sell',
  postBuyBtn: 'Post Buy',
  postSellBtn: 'Post Sell',
  actionBuy: 'Buy',
  actionSell: 'Sell',
  actionCancelOrder: 'Cancel',
  noOpenOrders: 'No open orders',

  // Leaderboard
  noScores: 'No scores yet',

  // Transaction table
  txTime: 'Time',
  txSide: 'Side',
  txAsset: 'Asset',
  txQty: 'Qty',
  txPrice: 'Price',
  txTotal: 'Total',
  txVia: 'Via',
  txBuy: 'BUY',
  txSell: 'SELL',
  txMarket: 'exchange',
  noTrades: 'No trades yet',

  // Toasts / dynamic messages (factories)
  toastWelcome: 'Welcome to the Market!',
  toastSessionEnded: (score) => `Session ended. Final score: ${score}`,
  toastFilledMarket: 'Filled at market price',
  toastOrderMatched: (n) => `Order matched in book (${n} trades)`,
  toastOrderPosted: 'Order placed in book (market price)',
  toastP2P: 'P2P trade executed',
  sessionExpired: 'Session expired (server restart). Please re-enter the market.',
  sessionResumeFailed: 'Could not resume session. Please re-enter.',

  // Time update label
  updatedAt: (t) => `Updated ${t}`,
};
