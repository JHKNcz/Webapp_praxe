// market/frontend/js/ui-state.js

// Always produces 2 decimal places, preserving trailing zero: 9902.20
export function formatValue(n) {
  return Number(n).toFixed(2);
}

export function formatPnl(value) {
  const v = Number(value);
  const sign = v > 0 ? '+' : '';
  return `${sign}${v.toFixed(2)}`;
}

export function formatPercent(value) {
  const v = Number(value);
  const sign = v > 0 ? '+' : '';
  return `${sign}${v.toFixed(2)}%`;
}

export function pnlClass(value) {
  return Number(value) >= 0 ? 'price-up' : 'price-down';
}

export function formatTime(ts) {
  if (!ts) return '';
  return new Date(ts * 1000).toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
}

// Starting capital from backend config (app.php initial_cash default)
export const INITIAL_CASH = 1000;

// Computes return % vs starting capital for leaderboard entries
export function leaderboardReturn(score) {
  return ((Number(score) - INITIAL_CASH) / INITIAL_CASH) * 100;
}

// Returns HTML badge for leaderboard rank (0-indexed)
export function rankBadge(index) {
  if (index === 0) return '<span class="lb-crown" aria-label="1st place">👑</span>';
  if (index === 1)
    return '<span class="lb-medal lb-silver" aria-label="2nd place">2</span>';
  if (index === 2)
    return '<span class="lb-medal lb-bronze" aria-label="3rd place">3</span>';
  return `<span class="lb-rank-num">${index + 1}</span>`;
}
