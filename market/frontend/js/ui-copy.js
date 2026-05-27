// market/frontend/js/ui-copy.js
export const COPY = {
  // Lobby
  lobbyTagline: 'Trade simulated markets and grow your portfolio.',
  nicknameLabel: 'Display Name',
  nicknamePlaceholder: 'Trader42',
  enterBtn: 'Start Trading',

  // Top bar
  endSessionBtn: 'End Session',

  // Panels
  instrumentsTitle: 'Assets',
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
  portfolioNoPositions: 'No open positions yet.',

  // Orderbook
  orderbookHint: 'Use Buy or Sell on any row to trade with that player at your selected quantity.',
  orderbookAsksLabel: 'Asks (Sell Orders)',
  orderbookBidsLabel: 'Bids (Buy Orders)',
  orderbookNoAsks: 'No sell orders available.',
  orderbookNoBids: 'No buy orders available.',
  orderbookEmptyHint: 'The order book is empty. Use a market order or place a limit order.',

  // Live price
  livePriceLabel: 'Live Price',
  priceHistoryLabel: 'Price History',

  // Trading
  selectedLabel: 'Selected',
  quantityLabel: 'Quantity',
  quantityError: 'Enter a quantity greater than 0.',
  tradeHint: 'Market orders execute immediately at the best available price. Limit orders are posted to the book at your chosen price.',
  marketBuyBtn: 'Market Buy',
  marketSellBtn: 'Market Sell',
  postBuyBtn: 'Limit Buy',
  postSellBtn: 'Limit Sell',
  actionBuy: 'Buy',
  actionSell: 'Sell',
  actionCancelOrder: 'Cancel',
  noOpenOrders: 'No open orders.',

  // Leaderboard
  noScores: 'No scores yet.',

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
  txMarket: 'Exchange',
  noTrades: 'No trades yet.',

  // Toasts / dynamic messages (factories)
  toastWelcome: 'Welcome to Market.io. Good luck!',
  toastSessionEnded: (score) => `Session ended. Final score: ${score}`,
  toastFilledMarket: 'Market order filled.',
  toastOrderMatched: (n) => `Limit order matched (${n} fill${n === 1 ? '' : 's'}).`,
  toastOrderPosted: 'Limit order posted to the book.',
  toastP2P: 'Player-to-player trade completed.',
  sessionExpired: 'Your session expired after a server restart. Please enter the market again.',
  sessionResumeFailed: 'Could not restore your session. Please enter again.',

  // Time update label
  updatedAt: (t) => `Last tick ${t}`,
  marketWireTitle: 'Market Wire',
  newsEmpty: 'No headlines yet — watch for breaking news.',
  chartTimeStart: 'Start',
  chartTimeEnd: 'Now',
};
