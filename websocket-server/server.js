/**
 * La Casa Gaudencia — WebSocket Server
 *
 * Port 9090 handles both:
 *  • WebSocket connections from the mobile app  (ws://HOST:9090?token=JWT)
 *  • HTTP POST from Symfony to push events      (POST http://HOST:9090/broadcast)
 *  • HTTP GET  for health check                 (GET  http://HOST:9090/status)
 *
 * Run:  node server.js
 */

const WebSocket = require('ws');
const http      = require('http');

const WS_PORT = 9090;

// userId (string) → Set<WebSocket>  (one user may have several open sockets)
const userSockets = new Map();

// ── JWT userId extraction (no signature verification needed for school demo) ──

function decodeUserId(token) {
  try {
    const payloadB64 = token.split('.')[1];
    const json       = Buffer.from(payloadB64, 'base64url').toString('utf8');
    const payload    = JSON.parse(json);
    // Symfony LexikJWTAuthenticationBundle stores the username in 'username'
    // and may or may not include 'id'; fall back to a timestamp so we always
    // get a non-null key.
    return String(payload.id ?? payload.sub ?? payload.username ?? Date.now());
  } catch {
    return null;
  }
}

// ── Broadcast helpers ─────────────────────────────────────────────────────────

function sendToUser(userId, msg) {
  const sockets = userSockets.get(String(userId));
  let sent = 0;
  if (sockets) {
    sockets.forEach(ws => {
      if (ws.readyState === WebSocket.OPEN) {
        ws.send(msg);
        sent++;
      }
    });
  }
  return sent;
}

function broadcastAll(msg) {
  let sent = 0;
  userSockets.forEach(sockets =>
    sockets.forEach(ws => {
      if (ws.readyState === WebSocket.OPEN) { ws.send(msg); sent++; }
    }),
  );
  return sent;
}

// ── HTTP server (used by Symfony AND as the upgrade target for WebSocket) ─────

const server = http.createServer((req, res) => {
  const corsHeaders = {
    'Access-Control-Allow-Origin':  '*',
    'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type',
    'Content-Type': 'application/json',
  };

  if (req.method === 'OPTIONS') {
    res.writeHead(204, corsHeaders);
    res.end();
    return;
  }

  // ── POST /broadcast ── Symfony calls this to push a notification ────────────
  if (req.method === 'POST' && req.url === '/broadcast') {
    let body = '';
    req.on('data', chunk => (body += chunk));
    req.on('end', () => {
      try {
        const payload = JSON.parse(body);
        const packet  = JSON.stringify({
          id:        payload.id        ?? `ws-${Date.now()}`,
          title:     payload.title     ?? 'Notification',
          body:      payload.body      ?? '',
          type:      payload.type      ?? 'general',
          createdAt: payload.createdAt ?? new Date().toISOString(),
          data:      payload.data      ?? {},
        });

        const targetId   = payload.userId ? String(payload.userId) : null;
        const recipients = targetId ? sendToUser(targetId, packet) : broadcastAll(packet);

        console.log(
          `[WS] Broadcast → ${targetId ? `user ${targetId}` : 'ALL'} ` +
          `| ${recipients} socket(s) | ${payload.title}`,
        );

        res.writeHead(200, corsHeaders);
        res.end(JSON.stringify({ ok: true, recipients }));
      } catch (e) {
        res.writeHead(400, corsHeaders);
        res.end(JSON.stringify({ error: 'Invalid JSON body', detail: e.message }));
      }
    });
    return;
  }

  // ── GET /status ── health-check / dashboard ─────────────────────────────────
  if (req.method === 'GET' && req.url === '/status') {
    const totalSockets = [...userSockets.values()].reduce((n, s) => n + s.size, 0);
    res.writeHead(200, corsHeaders);
    res.end(JSON.stringify({
      ok:             true,
      connectedUsers: userSockets.size,
      totalSockets,
      uptime:         Math.floor(process.uptime()) + 's',
    }));
    return;
  }

  res.writeHead(404, corsHeaders);
  res.end(JSON.stringify({ error: 'Not found' }));
});

// ── WebSocket server (attached to the same HTTP server) ───────────────────────

const wss = new WebSocket.Server({ server });

wss.on('connection', (ws, req) => {
  // Extract token from query string: ws://host:9090?token=JWT
  const urlObj = new URL(req.url, `http://localhost:${WS_PORT}`);
  const token  = urlObj.searchParams.get('token') ?? '';
  const userId = decodeUserId(token);

  if (!userId) {
    ws.close(4001, 'Unauthorized — missing or invalid token');
    return;
  }

  // Register
  if (!userSockets.has(userId)) userSockets.set(userId, new Set());
  userSockets.get(userId).add(ws);

  console.log(`[WS] Connected    user=${userId}  users=${userSockets.size}`);

  // Welcome handshake — mobile client uses this to confirm the link is live
  ws.send(JSON.stringify({
    id:        `sys-welcome-${Date.now()}`,
    type:      'system',
    title:     'Connected',
    body:      'Real-time notifications are active.',
    createdAt: new Date().toISOString(),
  }));

  // Heartbeat — mark alive on pong
  ws.isAlive = true;
  ws.on('pong', () => { ws.isAlive = true; });

  // Handle messages from the client (ping-echo, future client→server events)
  ws.on('message', raw => {
    try {
      const msg = JSON.parse(raw.toString());
      if (msg.type === 'ping') {
        ws.send(JSON.stringify({ type: 'pong', ts: Date.now() }));
        return;
      }
      console.log(`[WS] Message from user=${userId}:`, msg);
    } catch { /* ignore malformed */ }
  });

  ws.on('close', code => {
    const sockets = userSockets.get(userId);
    if (sockets) {
      sockets.delete(ws);
      if (sockets.size === 0) userSockets.delete(userId);
    }
    console.log(`[WS] Disconnected user=${userId}  code=${code}  users=${userSockets.size}`);
  });

  ws.on('error', err => {
    console.error(`[WS] Error user=${userId}:`, err.message);
  });
});

// Ping all clients every 25 s — drop stale connections
const heartbeatInterval = setInterval(() => {
  wss.clients.forEach(ws => {
    if (!ws.isAlive) { ws.terminate(); return; }
    ws.isAlive = false;
    ws.ping();
  });
}, 25_000);

wss.on('close', () => clearInterval(heartbeatInterval));

// ── Start ─────────────────────────────────────────────────────────────────────

server.listen(WS_PORT, '0.0.0.0', () => {
  console.log('');
  console.log('╔══════════════════════════════════════════════════╗');
  console.log(`║  La Casa Gaudencia WebSocket Server — port ${WS_PORT}  ║`);
  console.log('╠══════════════════════════════════════════════════╣');
  console.log(`║  WS  → ws://0.0.0.0:${WS_PORT}?token=<JWT>           ║`);
  console.log(`║  POST → http://0.0.0.0:${WS_PORT}/broadcast           ║`);
  console.log(`║  GET  → http://0.0.0.0:${WS_PORT}/status              ║`);
  console.log('╚══════════════════════════════════════════════════╝');
  console.log('');
});

process.on('SIGINT',  () => { wss.close(); server.close(); process.exit(0); });
process.on('SIGTERM', () => { wss.close(); server.close(); process.exit(0); });
