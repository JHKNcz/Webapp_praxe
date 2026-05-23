export function createMarketSocket(handlers = {}) {
  const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
  const url = `${protocol}//${window.location.host}/ws`;
  let socket = null;
  let retries = 0;
  let closedByUser = false;

  function connect() {
    socket = new WebSocket(url);

    socket.addEventListener('open', () => {
      retries = 0;
      handlers.onOpen?.();
    });

    socket.addEventListener('message', (event) => {
      try {
        const payload = JSON.parse(event.data);
        handlers.onMessage?.(payload);
      } catch {
        // ignore malformed payloads
      }
    });

    socket.addEventListener('close', () => {
      handlers.onClose?.();
      if (!closedByUser) {
        const delay = Math.min(1000 * 2 ** retries, 15000);
        retries += 1;
        setTimeout(connect, delay);
      }
    });
  }

  connect();

  return {
    close() {
      closedByUser = true;
      socket?.close();
    },
  };
}
