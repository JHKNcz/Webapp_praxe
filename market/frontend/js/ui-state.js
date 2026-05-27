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

/** Market display timezone (GMT+2 / Europe–Berlin). */
export const MARKET_TIMEZONE = 'Europe/Berlin';

const marketTimeOpts = {
  timeZone: MARKET_TIMEZONE,
  hour: '2-digit',
  minute: '2-digit',
  second: '2-digit',
  hour12: false,
};

const marketTimeShortOpts = {
  timeZone: MARKET_TIMEZONE,
  hour: '2-digit',
  minute: '2-digit',
  hour12: false,
};

export function formatTime(ts) {
  if (!ts) return '';
  return new Date(ts * 1000).toLocaleTimeString('en-GB', marketTimeOpts);
}

export function formatTimeShort(ts) {
  if (!ts) return '';
  return new Date(ts * 1000).toLocaleTimeString('en-GB', marketTimeShortOpts);
}

export function formatClockNow(date = new Date()) {
  return date.toLocaleTimeString('en-GB', {
    ...marketTimeOpts,
    timeZoneName: 'short',
  });
}

export function phaseLabel(phase) {
  const labels = {
    bull_run: 'BULL',
    bear_crash: 'BEAR',
    pump_dump: 'PUMP',
    normal: '',
  };
  return labels[phase] || String(phase || '').toUpperCase();
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
