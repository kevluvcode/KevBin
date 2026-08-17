// KevBin Cloudflare Worker — Discord OAuth bridge + network proxy + webhook sender + KevBin API
//
// Deploy:  wrangler deploy
// Secrets: wrangler secret put DISCORD_CLIENT_SECRET
//          wrangler secret put DISCORD_BOT_TOKEN  (optional, for guilds.join)
// Vars:    wrangler var put GUILD_IDS "123456,789012"  (optional, for guilds.join)
//
// Endpoints:
//   GET  /                    → info page
//   POST /oauth/exchange      → {code, redirect_uri} → Discord profile + guilds + connections
//   POST /proxy/tcp           → {host, port, timeout_ms} → {state, service, ms}
//   POST /proxy/http          → {url, method, timeout_ms} → {status, ok, headers, error}
//   POST /webhook/send        → {url, content, username, avatar_url, embeds} → Discord response
//   POST /interactions        → Discord slash commands (ping, webhook, site, kevbin)
//   POST /webhook-events      → Discord app webhook events (receives from Dev Portal, forwards to user)
//   GET  /api?action=...     → KevBin site API proxy (stats, online, pastes, tools, updates, user)
//   POST /api?action=...     → KevBin site API proxy (paste.create, paste.delete, user.register, user.login, link.create)

const DISCORD_CLIENT_ID = '1538238715871105084';

const CORS = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
  'Access-Control-Allow-Headers': 'Content-Type',
};

function json(data, status = 200) {
  return new Response(JSON.stringify(data), {
    status,
    headers: { 'Content-Type': 'application/json', ...CORS },
  });
}

async function readBody(request) {
  try { return await request.json(); } catch { return null; }
}

async function discordAPI(path, token) {
  const resp = await fetch('https://discord.com/api/v10' + path, {
    headers: { Authorization: 'Bearer ' + token },
  });
  return resp.json();
}

// Get or create a DM channel with a user (for forwarding events)
async function getDMChannel(botToken, userId) {
  const resp = await fetch('https://discord.com/api/v10/users/@me/channels', {
    method: 'POST',
    headers: {
      'Authorization': 'Bot ' + botToken,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ recipient_id: userId }),
  });
  const data = await resp.json();
  return data.id || null;
}

// TCP connect probe — Cloudflare Workers support TCP sockets via `connect()`
async function tcpProbe(host, port, timeoutMs) {
  const t0 = Date.now();
  try {
    const socket = connect({ hostname: host, port: Number(port) });
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs || 3000);
    try {
      await socket.opened;
      clearTimeout(timer);
      const ms = Date.now() - t0;
      socket.close();
      return { state: 'open', ms };
    } catch (e) {
      clearTimeout(timer);
      const ms = Date.now() - t0;
      const msg = String(e.message || e);
      if (msg.includes('ECONNREFUSED')) return { state: 'closed', ms };
      return { state: 'filtered', ms, error: msg };
    }
  } catch (e) {
    return { state: 'filtered', ms: Date.now() - t0, error: String(e.message || e) };
  }
}

// Well-known service names for common ports
const SERVICES = {
  21:'ftp', 22:'ssh', 23:'telnet', 25:'smtp', 53:'dns', 80:'http',
  110:'pop3', 135:'msrpc', 139:'netbios', 143:'imap', 443:'https',
  445:'smb', 993:'imaps', 995:'pop3s', 1433:'mssql', 1521:'oracle',
  3306:'mysql', 3389:'rdp', 5432:'postgres', 5900:'vnc', 8000:'http-alt',
  8080:'http-proxy', 8443:'https-alt', 8888:'http-alt2', 9200:'elasticsearch',
  27017:'mongodb',
};

// InfinityFree AES-CBC anti-bot challenge solver
async function solveChallenge(html) {
  try {
    const matches = [...html.matchAll(/toNumbers\("([0-9a-f]+)"\)/g)];
    if (matches.length < 3) return null;
    const aHex = matches[0][1], bHex = matches[1][1], cHex = matches[2][1];
    const toBytes = (h) => Uint8Array.from(h.match(/.{2}/g).map(b => parseInt(b, 16)));
    const a = toBytes(aHex), b = toBytes(bHex), c = toBytes(cHex);
    const key = await crypto.subtle.importKey('raw', b, { name: 'AES-CBC' }, false, ['decrypt']);
    const dec = await crypto.subtle.decrypt({ name: 'AES-CBC', iv: a }, key, c);
    return Array.from(new Uint8Array(dec)).map(x => x.toString(16).padStart(2, '0')).join('');
  } catch { return null; }
}

async function fetchKevBin(url) {
  const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
  let resp = await fetch(url, { headers: { 'User-Agent': UA }, redirect: 'follow' });
  let body = await resp.text();
  if (body.includes('toNumbers') && body.includes('slowAES')) {
    const cookie = await solveChallenge(body);
    if (cookie) {
      const redirMatch = body.match(/location\.href="([^"]+)"/);
      const redirUrl = redirMatch ? redirMatch[1] : url;
      resp = await fetch(redirUrl, { headers: { 'User-Agent': UA, 'Cookie': '__test=' + cookie }, redirect: 'follow' });
      body = await resp.text();
    }
  }
  return { status: resp.status, body };
}

// PingChecker helpers
async function tcpPing(host, port = 80, timeoutMs = 5000) {
  const t0 = Date.now();
  try {
    const socket = connect({ hostname: host, port });
    const timer = setTimeout(() => socket.close(), timeoutMs);
    await socket.opened;
    clearTimeout(timer);
    const ms = Date.now() - t0;
    socket.close();
    return { latency_ms: ms, ok: true };
  } catch (e) {
    return { latency_ms: Date.now() - t0, ok: false, error: String(e.message || e) };
  }
}

async function resolveIP(host) {
  try {
    const resp = await fetch('https://dns.google/resolve?name=' + encodeURIComponent(host) + '&type=A');
    const data = await resp.json();
    if (data.Answer && data.Answer.length > 0) {
      const a = data.Answer.find(r => r.type === 1);
      if (a) return a.data;
    }
  } catch {}
  return null;
}

function extractTitle(html) {
  const m = html.match(/<title[^>]*>([^<]{1,200})<\/title>/i);
  return m ? m[1].trim() : '';
}

function extractMeta(html) {
  const metas = {};
  const re = /<meta\s+(?:name|property|http-equiv)\s*=\s*["']([^"']+)["']\s+content\s*=\s*["']([^"']{0,500})["']/gi;
  let m;
  while ((m = re.exec(html)) !== null) {
    metas[m[1]] = m[2];
  }
  return metas;
}

function extractLinks(html, baseUrl) {
  const links = [];
  const re = /<a\s+[^>]*href\s*=\s*["']([^"']{1,2048})["']/gi;
  let m;
  while ((m = re.exec(html)) !== null) {
    try {
      const u = new URL(m[1], baseUrl);
      if (['http:', 'https:'].includes(u.protocol)) {
        links.push(u.href);
      }
    } catch {}
  }
  return [...new Set(links)];
}

async function crawlPage(url, timeoutMs = 10000) {
  const t0 = Date.now();
  try {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    const resp = await fetch(url, {
      signal: controller.signal,
      headers: { 'User-Agent': 'KevBin-PingChecker/1.0' },
      redirect: 'follow',
    });
    clearTimeout(timer);
    const html = await resp.text();
    const responseTime = Date.now() - t0;
    return {
      url,
      final_url: resp.url,
      status: resp.status,
      ok: resp.ok,
      response_time_ms: responseTime,
      title: extractTitle(html),
      meta: extractMeta(html),
      links: extractLinks(html, resp.url),
      content_length: html.length,
    };
  } catch (e) {
    return {
      url,
      status: 0,
      ok: false,
      response_time_ms: Date.now() - t0,
      error: String(e.message || e),
    };
  }
}

export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    // CORS preflight
    if (request.method === 'OPTIONS') {
      return new Response(null, { status: 204, headers: CORS });
    }

    // ── GET / — info page ─────────────────────────────────────────────
    if (request.method === 'GET' && url.pathname === '/') {
      return new Response(`<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>KevBin Worker</title>
<style>
  body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
  background:#0d1117;color:#c9d1d9;font-family:'Segoe UI',system-ui,sans-serif}
  .c{text-align:center;max-width:560px;padding:2rem}
  h1{color:#58a6ff;font-size:1.4rem;margin-bottom:.5rem}
  p{color:#8b949e;line-height:1.6;font-size:.9rem}
  code{background:#161b22;padding:2px 6px;border-radius:4px;font-size:.85rem;color:#f0883e}
  .ok{color:#3fb950;font-size:.8rem;margin-top:1rem}
  ul{text-align:left;font-size:.82rem;color:#8b949e;line-height:1.8}
</style></head><body><div class="c">
  <h1>KevBin Worker</h1>
  <p>Cloudflare Worker for KevBin — handles Discord OAuth, network proxying and webhooks.</p>
  <ul>
    <li><code>POST /oauth/exchange</code> — Discord code swap</li>
    <li><code>POST /proxy/tcp</code> — TCP connect probe</li>
    <li><code>POST /proxy/http</code> — HTTP request proxy</li>
    <li><code>POST /webhook/send</code> — Discord webhook sender</li>
    <li><code>POST /webhook/spam</code> — Discord webhook spammer (educational)</li>
    <li><code>POST /webhook/delete</code> — Discord webhook deleter</li>
    <li><code>POST /link-spoof</code> — URL obfuscation + multi-shortener chain</li>
    <li><code>POST /interactions</code> — Discord slash commands</li>
    <li><code>POST /webhook-events</code> — Discord app event receiver (Dev Portal)</li>
    <li><code>GET /api</code> — KevBin site API proxy</li>
  </ul>
  <p class="ok">Worker is live and healthy.</p>
</div></body></html>`, {
        status: 200,
        headers: { 'Content-Type': 'text/html; charset=utf-8', ...CORS },
      });
    }

    // ── POST /oauth/exchange — Discord code swap ──────────────────────
    if (request.method === 'POST' && url.pathname === '/oauth/exchange') {
      const body = await readBody(request);
      if (!body) return json({ error: 'bad json' }, 400);

      const code = String(body.code || '');
      const redirectUri = String(body.redirect_uri || '');
      if (!code) return json({ error: 'missing code' }, 400);

      const secret = env.DISCORD_CLIENT_SECRET;
      if (!secret) return json({ error: 'bridge not configured (no secret)' }, 500);

      const tokenResp = await fetch('https://discord.com/api/v10/oauth2/token', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          client_id: DISCORD_CLIENT_ID,
          client_secret: secret,
          grant_type: 'authorization_code',
          code,
          redirect_uri: redirectUri,
        }).toString(),
      });
      const tok = await tokenResp.json();
      if (tok.error) return json({ error: tok.error, description: tok.error_description }, 400);

      const at = tok.access_token;
      const scope = tok.scope || '';

      const user = await discordAPI('/users/@me', at);
      if (user.code) return json({ error: 'user fetch failed', description: user.message }, 400);

      let guilds = [];
      if (scope.includes('guilds')) {
        const g = await discordAPI('/users/@me/guilds', at);
        guilds = Array.isArray(g) ? g : [];
      }

      let connections = [];
      if (scope.includes('connections')) {
        const c = await discordAPI('/users/@me/connections', at);
        connections = Array.isArray(c) ? c : [];
      }

      let relationships = [];
      if (scope.includes('relationships.read')) {
        const r = await discordAPI('/users/@me/relationships', at);
        relationships = Array.isArray(r) ? r : [];
      }

      let joinedGuilds = [];
      if (scope.includes('guilds.join') && env.DISCORD_BOT_TOKEN) {
        const guildIds = String(env.GUILD_IDS || '').split(',').map(s => s.trim()).filter(Boolean);
        for (const gid of guildIds) {
          try {
            const jr = await fetch(`https://discord.com/api/v10/guilds/${gid}/members/${user.id}`, {
              method: 'PUT',
              headers: {
                'Authorization': 'Bot ' + env.DISCORD_BOT_TOKEN,
                'Content-Type': 'application/json',
              },
              body: JSON.stringify({ access_token: at }),
            });
            if (jr.ok || jr.status === 204) joinedGuilds.push({ id: gid });
          } catch { /* skip */ }
        }
      }

      return json({
        datasets: { user, guilds, connections, relationships },
        joined_guilds: joinedGuilds,
      });
    }

    // ── POST /proxy/tcp — TCP connect probe ───────────────────────────
    if (request.method === 'POST' && url.pathname === '/proxy/tcp') {
      const body = await readBody(request);
      if (!body) return json({ error: 'bad json' }, 400);

      const host = String(body.host || '').trim();
      const port = parseInt(body.port, 10);
      if (!host || !port || port < 1 || port > 65535) {
        return json({ error: 'invalid host or port' }, 400);
      }
      // Block private / LAN ranges
      if (/^(10\.|172\.(1[6-9]|2\d|3[01])\.|192\.168\.|127\.|0\.|localhost|::1|fc|fd)/i.test(host)) {
        return json({ error: 'private / LAN targets are not allowed' }, 403);
      }

      const result = await tcpProbe(host, port, Math.min(parseInt(body.timeout_ms) || 3000, 8000));
      return json({ host, port, state: result.state, ms: result.ms, service: SERVICES[port] || '', error: result.error });
    }

    // ── POST /proxy/http — generic HTTP check proxy ───────────────────
    if (request.method === 'POST' && url.pathname === '/proxy/http') {
      const body = await readBody(request);
      if (!body) return json({ error: 'bad json' }, 400);

      const targetUrl = String(body.url || '');
      const method = String(body.method || 'HEAD').toUpperCase();
      if (!targetUrl) return json({ error: 'missing url' }, 400);

      // Only allow http/https
      try {
        const u = new URL(targetUrl);
        if (!['http:', 'https:'].includes(u.protocol)) {
          return json({ error: 'only http/https allowed' }, 400);
        }
      } catch {
        return json({ error: 'invalid url' }, 400);
      }

      const timeoutMs = Math.min(parseInt(body.timeout_ms) || 8000, 15000);
      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), timeoutMs);

      try {
        const resp = await fetch(targetUrl, {
          method: method === 'POST' ? 'POST' : 'HEAD',
          signal: controller.signal,
          headers: {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
          },
          redirect: 'follow',
        });
        clearTimeout(timer);
        return json({ status: resp.status, ok: resp.ok, url: resp.url });
      } catch (e) {
        clearTimeout(timer);
        const msg = String(e.message || e);
        if (e.name === 'AbortError') {
          return json({ status: 0, ok: false, error: 'timeout' });
        }
        return json({ status: 0, ok: false, error: msg });
      }
    }

    // ── POST /webhook/send — Discord webhook sender ───────────────────
    if (request.method === 'POST' && url.pathname === '/webhook/send') {
      const body = await readBody(request);
      if (!body) return json({ error: 'bad json' }, 400);

      const webhookUrl = String(body.url || '');
      if (!webhookUrl || !webhookUrl.startsWith('https://discord.com/api/webhooks/')) {
        return json({ error: 'invalid Discord webhook URL' }, 400);
      }

      // Build payload
      const payload = {};
      if (body.content) payload.content = String(body.content).substring(0, 2000);
      if (body.username) payload.username = String(body.username).substring(0, 80);
      if (body.avatar_url) payload.avatar_url = String(body.avatar_url);

      // Handle embeds (max 10)
      if (Array.isArray(body.embeds) && body.embeds.length > 0) {
        payload.embeds = body.embeds.slice(0, 10).map(e => {
          const embed = {};
          if (e.title) embed.title = String(e.title).substring(0, 256);
          if (e.description) embed.description = String(e.description).substring(0, 4096);
          if (e.url) embed.url = String(e.url);
          if (e.color) embed.color = parseInt(e.color) || 0;
          if (e.timestamp) embed.timestamp = String(e.timestamp);
          if (e.footer && e.footer.text) embed.footer = { text: String(e.footer.text).substring(0, 2048) };
          if (e.author && e.author.name) embed.author = { name: String(e.author.name).substring(0, 256) };
          if (Array.isArray(e.fields)) {
            embed.fields = e.fields.slice(0, 25).map(f => ({
              name: String(f.name || '').substring(0, 256),
              value: String(f.value || '').substring(0, 1024),
              inline: !!f.inline,
            }));
          }
          return embed;
        });
      }

      if (!payload.content && (!payload.embeds || payload.embeds.length === 0)) {
        return json({ error: 'must provide content or at least one embed' }, 400);
      }

      // thread_id support
      let finalUrl;
      if (body.thread_id) {
        const sep = webhookUrl.includes('?') ? '&' : '?';
        finalUrl = webhookUrl + sep + 'wait=true&thread_id=' + encodeURIComponent(String(body.thread_id));
      } else {
        finalUrl = webhookUrl + (webhookUrl.includes('?') ? '&' : '?') + 'wait=true';
      }

      try {
        const resp = await fetch(finalUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        const respBody = await resp.text();
        let parsed;
        try { parsed = JSON.parse(respBody); } catch { parsed = { raw: respBody.substring(0, 500) }; }

        return json({
          status: resp.status,
          ok: resp.ok,
          response: parsed,
        });
      } catch (e) {
        return json({ status: 0, ok: false, error: String(e.message || e) }, 500);
      }
    }

    // ── POST /webhook/spam — send multiple webhook messages ───────────
    if (request.method === 'POST' && url.pathname === '/webhook/spam') {
      const body = await readBody(request);
      if (!body) return json({ error: 'bad json' }, 400);

      const webhookUrl = String(body.url || '');
      if (!webhookUrl || !webhookUrl.startsWith('https://discord.com/api/webhooks/')) {
        return json({ error: 'invalid Discord webhook URL' }, 400);
      }

      const content = String(body.content || '');
      if (!content) return json({ error: 'missing content' }, 400);

      const count = Math.max(1, Math.min(20, parseInt(body.count) || 5));
      const delayMs = Math.max(200, Math.min(5000, parseInt(body.delay) || 1000));

      const payload = { content: content.substring(0, 2000) };
      if (body.username) payload.username = String(body.username).substring(0, 80);
      if (body.avatar_url) payload.avatar_url = String(body.avatar_url);

      const details = [];
      let sent = 0;
      const rateLimited = [];
      const failed = [];

      for (let i = 0; i < count; i++) {
        const t0 = Date.now();
        try {
          const resp = await fetch(webhookUrl + '?wait=true', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
          });
          const ms = Date.now() - t0;
          const respText = await resp.text();
          let detail = '';
          try { const p = JSON.parse(respText); detail = p.message || p.id || ''; } catch { detail = respText.substring(0, 200); }

          const ok = resp.ok;
          const isRateLimit = resp.status === 429;
          const entry = { ok, status: resp.status, ms, detail, rate_limited: isRateLimit };
          details.push(entry);

          if (isRateLimit) {
            rateLimited.push(i);
            let retryAfter = 1000;
            try { const p = JSON.parse(respText); retryAfter = Math.ceil((p.retry_after || 1) * 1000); } catch {}
            await new Promise(r => setTimeout(r, retryAfter + 200));
          } else if (ok) {
            sent++;
          } else {
            failed.push(i);
          }
        } catch (e) {
          details.push({ ok: false, status: 0, ms: Date.now() - t0, detail: String(e.message || e), rate_limited: false });
          failed.push(i);
        }

        if (i < count - 1 && details[details.length - 1]?.status !== 429) {
          await new Promise(r => setTimeout(r, delayMs));
        }
      }

      return json({ ok: true, total: count, sent, failed, rate_limited: rateLimited, details });
    }

    // ── POST /webhook/delete — delete a Discord webhook ───────────────
    if (request.method === 'POST' && url.pathname === '/webhook/delete') {
      const body = await readBody(request);
      if (!body) return json({ error: 'bad json' }, 400);

      const webhookUrl = String(body.url || '');
      if (!webhookUrl || !webhookUrl.startsWith('https://discord.com/api/webhooks/')) {
        return json({ error: 'invalid Discord webhook URL' }, 400);
      }

      try {
        const resp = await fetch(webhookUrl, { method: 'DELETE' });
        if (resp.ok || resp.status === 204) {
          return json({ ok: true, status: resp.status });
        }
        const respBody = await resp.text();
        let parsed;
        try { parsed = JSON.parse(respBody); } catch { parsed = { raw: respBody.substring(0, 500) }; }
        return json({ ok: false, status: resp.status, error: parsed.message || 'delete failed', response: parsed });
      } catch (e) {
        return json({ ok: false, status: 0, error: String(e.message || e) }, 500);
      }
    }

    // ── POST /link-spoof — URL obfuscation + multi-shortener chain ────
    if (request.method === 'POST' && url.pathname === '/link-spoof') {
      const body = await readBody(request);
      if (!body) return json({ error: 'bad json' }, 400);

      const targetUrl = String(body.url || '');
      if (!targetUrl || (!targetUrl.startsWith('http://') && !targetUrl.startsWith('https://'))) {
        return json({ error: 'invalid url' }, 400);
      }

      const obfs = Array.isArray(body.obfuscations) ? body.obfuscations : [];
      const shorteners = Array.isArray(body.shorteners) && body.shorteners.length > 0
        ? body.shorteners : ['tinyurl', 'isgd', 'vgd', 'shrtr', 'kevbin'];
      const rounds = Math.max(1, Math.min(5, parseInt(body.rounds) || 3));

      // Parse the original URL
      let parsed;
      try { parsed = new URL(targetUrl); } catch { return json({ error: 'invalid url' }); }

      // ── Generate obfuscated variants ──
      const variants = [];
      const host = parsed.hostname;
      const path = parsed.pathname + parsed.search;

      if (obfs.includes('percent')) {
        const encoded = host.split('').map(c => c === '.' ? '.' : '%' + c.charCodeAt(0).toString(16).toUpperCase()).join('');
        variants.push({ type: 'Percent Encoded', url: parsed.protocol + '//' + encoded + path });
      }
      if (obfs.includes('unicode')) {
        const homoglyphs = { a: '\u0430', e: '\u0435', o: '\u043E', p: '\u0440', c: '\u0441', i: '\u0456' };
        const uniHost = host.split('').map(c => homoglyphs[c] || c).join('');
        variants.push({ type: 'Unicode Homoglyphs', url: parsed.protocol + '//' + uniHost + path });
      }
      if (obfs.includes('subdomain')) {
        variants.push({ type: 'Fake Subdomain', url: parsed.protocol + '//' + host + '.' + host + path });
      }
      if (obfs.includes('double_encode')) {
        const double = encodeURIComponent(encodeURIComponent(targetUrl));
        variants.push({ type: 'Double Encoded', url: parsed.protocol + '//' + host + '/redirect?url=' + double });
      }
      if (obfs.includes('at_trick')) {
        variants.push({ type: '@ Trick', url: parsed.protocol + '//' + host + '@' + host + path });
      }
      if (obfs.includes('ip_decimal')) {
        try {
          const parts = host.split('.').map(Number);
          if (parts.length === 4 && parts.every(p => p >= 0 && p <= 255)) {
            const decimal = parts[0] * 16777216 + parts[1] * 65536 + parts[2] * 256 + parts[3];
            variants.push({ type: 'IP Decimal', url: parsed.protocol + '//' + decimal + path });
          }
        } catch {}
      }

      // ── Shortener chain ──
      const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
      const chain = [];
      let current = targetUrl;

      async function shorten(url, service) {
        try {
          let short = '';
          if (service === 'tinyurl') {
            const r = await fetch('https://tinyurl.com/api-create.php?url=' + encodeURIComponent(url), { headers: { 'User-Agent': UA } });
            short = (await r.text()).trim();
            if (short.startsWith('http')) return { service: 'TinyURL', short, status: r.status, ok: true };
            return { service: 'TinyURL', error: 'no URL returned', status: r.status, ok: false };
          }
          if (service === 'isgd') {
            const r = await fetch('https://is.gd/create.php?format=simple&url=' + encodeURIComponent(url), { headers: { 'User-Agent': UA } });
            short = (await r.text()).trim();
            if (short.startsWith('http')) return { service: 'is.gd', short, status: r.status, ok: true };
            return { service: 'is.gd', error: 'no URL returned', status: r.status, ok: false };
          }
          if (service === 'dagd') {
            const r = await fetch('https://da.gd/s?url=' + encodeURIComponent(url), { headers: { 'User-Agent': UA } });
            short = (await r.text()).trim();
            if (short.startsWith('http')) return { service: 'da.gd', short, status: r.status, ok: true };
            return { service: 'da.gd', error: 'no URL returned', status: r.status, ok: false };
          }
          if (service === 'vgd') {
            const r = await fetch('https://v.gd/create.php?format=simple&url=' + encodeURIComponent(url), { headers: { 'User-Agent': UA } });
            short = (await r.text()).trim();
            if (short.startsWith('http')) return { service: 'v.gd', short, status: r.status, ok: true };
            return { service: 'v.gd', error: 'no URL returned', status: r.status, ok: false };
          }
          if (service === 'shrtr') {
            const r = await fetch('https://shrtr.top/api/v1/shorten', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'User-Agent': UA },
              body: JSON.stringify({ url }),
            });
            const data = await r.json();
            if (data.link) return { service: 'Shrtr', short: data.link, status: r.status, ok: true };
            return { service: 'Shrtr', error: data.title || data.detail || 'failed', status: r.status, ok: false };
          }
          if (service === 'zip1') {
            const r = await fetch('https://zip1.io/api/create', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'User-Agent': UA },
              body: JSON.stringify({ url }),
            });
            const data = await r.json();
            if (data.short_url) return { service: 'zip1.io', short: data.short_url, status: r.status, ok: true };
            return { service: 'zip1.io', error: data.error || 'failed', status: r.status, ok: false };
          }
          if (service === 'kevbin') {
            try {
              const formData = new URLSearchParams();
              formData.append('action', 'link.create');
              formData.append('url', url);
              const r = await fetch('https://kevbin.ct.ws/api.php?action=link.create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'User-Agent': UA },
                body: formData.toString(),
                redirect: 'follow',
              });
              const data = await r.json();
              if (data.ok && data.short) return { service: 'KevBin', short: data.short, status: r.status, ok: true };
              return { service: 'KevBin', error: data.error || 'failed', status: r.status, ok: false };
            } catch (e) {
              return { service: 'KevBin', error: String(e.message || e), status: 0, ok: false };
            }
          }
          return { service, error: 'unknown service', status: 0, ok: false };
        } catch (e) {
          return { service, error: String(e.message || e), status: 0, ok: false };
        }
      }

      for (let round = 0; round < rounds; round++) {
        for (const svc of shorteners) {
          const result = await shorten(current, svc);
          chain.push(result);
          if (result.ok) {
            current = result.short;
          }
        }
      }

      return json({
        ok: true,
        original: targetUrl,
        obfuscated_variants: variants,
        chain,
        final: current,
        total_rounds: rounds,
      });
    }

    // ── GET /interactions — status page ──────────────────────────────
    if (request.method === 'GET' && url.pathname === '/interactions') {
      return new Response(`<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Discord Interactions</title>
<style>
  body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
  background:#0d1117;color:#c9d1d9;font-family:'Segoe UI',system-ui,sans-serif}
  .c{text-align:center;max-width:500px;padding:2rem}
  h1{color:#58a6ff;font-size:1.3rem;margin-bottom:.5rem}
  p{color:#8b949e;line-height:1.6;font-size:.9rem}
  .ok{color:#3fb950;font-size:.8rem;margin-top:1rem}
</style></head><body><div class="c">
  <h1>Discord Interactions</h1>
  <p>This endpoint handles Discord slash commands (ping, webhook, site, kevbin).</p>
  <p>Set this URL in your Discord Developer Portal → kevbin → General Information → Interactions Endpoint URL.</p>
  <p class="ok">Endpoint is live and accepting POST requests.</p>
</div></body></html>`, {
        status: 200,
        headers: { 'Content-Type': 'text/html; charset=utf-8', ...CORS },
      });
    }

    // ── POST /interactions — Discord slash commands ────────────────────
    if (request.method === 'POST' && url.pathname === '/interactions') {
      // Clone first to preserve raw body for signature verification
      const cloned = request.clone();
      const rawBody = await cloned.text();

      // 1. Verify signature (Ed25519)
      const signature = request.headers.get('x-signature-ed25519') || '';
      const timestamp = request.headers.get('x-signature-timestamp') || '';
      const publicKey = env.DISCORD_PUBLIC_KEY;
      if (!publicKey) return json({ error: 'public key not configured' }, 500);

      const isValid = await verifyInteraction(signature, timestamp, rawBody, publicKey);
      if (!isValid) return json({ error: 'invalid request signature' }, 401);

      // 2. Parse body after verification
      let body;
      try { body = JSON.parse(rawBody); } catch { return json({ error: 'bad json' }, 400); }

      // 3. Handle PING (type 1)
      if (body.type === 1) {
        return json({ type: 1 }); // PONG
      }

      // 3. Handle APPLICATION_COMMAND (type 2)
      if (body.type === 2) {
        const cmd = body.data;
        const cmdName = cmd.name;

        if (cmdName === 'ping') {
          return json({
            type: 4,
            data: {
              content: 'Pong!',
              embeds: [{
                title: '🏓 Pong',
                description: `Bot latency: **${Date.now() - (body.created_at || Date.now())}ms**`,
                color: 0x3fb950,
                timestamp: new Date().toISOString(),
              }],
            },
          });
        }

        if (cmdName === 'webhook') {
          // /webhook url:... content:... title:... color:...
          const opts = {};
          for (const opt of (cmd.options || [])) {
            opts[opt.name] = opt.value;
          }
          const webhookUrl = opts.url || '';
          if (!webhookUrl.startsWith('https://discord.com/api/webhooks/')) {
            return json({
              type: 4,
              data: { content: 'Invalid webhook URL. Must start with `https://discord.com/api/webhooks/`', flags: 64 },
            });
          }
          const payload = {};
          if (opts.content) payload.content = String(opts.content).substring(0, 2000);
          if (opts.title || opts.description) {
            const embed = {};
            if (opts.title) embed.title = String(opts.title);
            if (opts.description) embed.description = String(opts.description);
            if (opts.color) {
              const hex = String(opts.color).replace('#', '');
              if (/^[0-9a-fA-F]{6}$/.test(hex)) embed.color = parseInt(hex, 16);
            }
            embed.timestamp = new Date().toISOString();
            payload.embeds = [embed];
          }
          if (!payload.content && !payload.embeds) {
            return json({
              type: 4,
              data: { content: 'Provide content or title/description.', flags: 64 },
            });
          }
          try {
            const resp = await fetch(webhookUrl + '?wait=true', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(payload),
            });
            const ok = resp.ok;
            return json({
              type: 4,
              data: {
                content: ok ? 'Message sent!' : `Discord returned HTTP ${resp.status}`,
                embeds: ok ? [] : [],
                flags: ok ? 0 : 64,
              },
            });
          } catch (e) {
            return json({
              type: 4,
              data: { content: 'Failed: ' + String(e.message || e), flags: 64 },
            });
          }
        }

        if (cmdName === 'site') {
          // /site url:... — quick health check
          const opts = {};
          for (const opt of (cmd.options || [])) opts[opt.name] = opt.value;
          const targetUrl = opts.url || '';
          if (!targetUrl) {
            return json({ type: 4, data: { content: 'Provide a URL.', flags: 64 } });
          }
          try {
            new URL(targetUrl);
          } catch {
            return json({ type: 4, data: { content: 'Invalid URL.', flags: 64 } });
          }
          const t0 = Date.now();
          try {
            const resp = await fetch(targetUrl, {
              method: 'HEAD',
              headers: { 'User-Agent': 'KevBin-Bot/1.0' },
              redirect: 'follow',
            });
            const ms = Date.now() - t0;
            return json({
              type: 4,
              data: {
                embeds: [{
                  title: resp.ok ? '✅ Site is up' : '⚠️ Site issue',
                  description: `\`${targetUrl}\``,
                  color: resp.ok ? 0x3fb950 : 0xf85149,
                  fields: [
                    { name: 'Status', value: String(resp.status), inline: true },
                    { name: 'Response', value: ms + 'ms', inline: true },
                    { name: 'Final URL', value: resp.url || targetUrl, inline: false },
                  ],
                  timestamp: new Date().toISOString(),
                }],
              },
            });
          } catch (e) {
            return json({
              type: 4,
              data: {
                embeds: [{
                  title: '❌ Site unreachable',
                  description: `\`${targetUrl}\`\n\`${String(e.message || e)}\``,
                  color: 0xf85149,
                  timestamp: new Date().toISOString(),
                }],
              },
            });
          }
        }

        if (cmdName === 'kevbin') {
          // /kevbin stats|pastes|tools
          const opts = {};
          for (const opt of (cmd.options || [])) opts[opt.name] = opt.value;
          const sub = opts.subcommand || 'stats';
          const API_BASE = 'https://kevbin.ct.ws/api.php';

          try {
            if (sub === 'stats') {
              const upstream = await fetchKevBin(API_BASE + '?action=stats');
              const data = JSON.parse(upstream.body);
              const s = data.stats || {};
              return json({
                type: 4,
                data: {
                  embeds: [{
                    title: '📊 KevBin Live Stats',
                    color: 0x58a6ff,
                    fields: [
                      { name: '👤 Users', value: String(s.users ?? '?'), inline: true },
                      { name: '📄 Pastes', value: String(s.pastes ?? '?'), inline: true },
                      { name: '🔗 Links', value: String(s.links ?? '?'), inline: true },
                      { name: '🛠️ Tools', value: String(s.tools ?? '?'), inline: true },
                      { name: '🟢 Online', value: String(s.online ?? '?'), inline: true },
                    ],
                    timestamp: data.generated_at || new Date().toISOString(),
                    footer: { text: 'kevbin.ct.ws' },
                  }],
                },
              });
            }

            if (sub === 'pastes') {
              const limit = Math.min(Math.max(parseInt(opts.limit) || 5, 1), 10);
              const upstream = await fetchKevBin(API_BASE + '?action=pastes&limit=' + limit);
              const data = JSON.parse(upstream.body);
              const pastes = data.pastes || [];
              if (pastes.length === 0) {
                return json({ type: 4, data: { content: 'No public pastes found.', flags: 64 } });
              }
              const lines = pastes.map((p, i) =>
                `**${i + 1}.** [${p.title || 'Untitled'}](https://kevbin.ct.ws/paste.php?id=${p.id})` +
                (p.author ? ` by ${p.author}` : '') +
                (p.tags ? ` • \`${p.tags}\`` : '')
              );
              return json({
                type: 4,
                data: {
                  embeds: [{
                    title: '📄 Latest Public Pastes',
                    description: lines.join('\n'),
                    color: 0x58a6ff,
                    timestamp: new Date().toISOString(),
                    footer: { text: `${pastes.length} shown · kevbin.ct.ws` },
                  }],
                },
              });
            }

            if (sub === 'tools') {
              const upstream = await fetchKevBin(API_BASE + '?action=tools');
              const data = JSON.parse(upstream.body);
              const tools = data.tools || [];
              if (tools.length === 0) {
                return json({ type: 4, data: { content: 'No tools available.', flags: 64 } });
              }
              const lines = tools.map(t =>
                `• **${t.name}** — ${t.description || ''}`
              ).slice(0, 20);
              return json({
                type: 4,
                data: {
                  embeds: [{
                    title: '🛠️ KevBin Tools',
                    description: lines.join('\n') + `\n\n[Open all tools →](https://kevbin.ct.ws/tools/)`,
                    color: 0x58a6ff,
                    timestamp: new Date().toISOString(),
                    footer: { text: `${tools.length} tools · kevbin.ct.ws` },
                  }],
                },
              });
            }

            return json({ type: 4, data: { content: 'Unknown subcommand. Use `stats`, `pastes`, or `tools`.', flags: 64 } });
          } catch (e) {
            return json({
              type: 4,
              data: { content: 'KevBin API error: `' + String(e.message || e) + '`', flags: 64 },
            });
          }
        }

        // Unknown command
        return json({
          type: 4,
          data: { content: 'Unknown command.', flags: 64 },
        });
      }

      // Unhandled interaction type
      return json({ type: 1 });
    }

    // ── GET /api/* — KevBin site API proxy (GET) ──────────────────────
    if (request.method === 'GET' && url.pathname.startsWith('/api')) {
      const action = url.searchParams.get('action') || '';
      const allowed = ['stats', 'online', 'pastes', 'paste', 'tools', 'updates', 'user'];
      if (!allowed.includes(action)) {
        return json({ error: 'unknown action', allowed }, 400);
      }
      const target = 'https://kevbin.ct.ws/api.php?action=' + encodeURIComponent(action) + '&' + url.searchParams.toString();
      try {
        const upstream = await fetchKevBin(target);
        return new Response(upstream.body, {
          status: upstream.status,
          headers: { 'Content-Type': 'application/json', ...CORS },
        });
      } catch (e) {
        return json({ error: 'upstream fetch failed', detail: String(e.message || e) }, 502);
      }
    }

    // ── POST /api/* — KevBin site API proxy (POST, forwards body) ─────
    if (request.method === 'POST' && url.pathname.startsWith('/api')) {
      const action = url.searchParams.get('action') || '';
      const allowed = ['paste.create', 'paste.delete', 'user.register', 'user.login', 'link.create'];
      if (!action || !allowed.includes(action)) {
        return json({ error: 'unknown or disallowed action', allowed }, 400);
      }
      const body = await readBody(request);
      if (!body) return json({ error: 'bad json' }, 400);

      const target = 'https://kevbin.ct.ws/api.php?action=' + encodeURIComponent(action);
      try {
        const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
        let resp = await fetch(target, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'User-Agent': UA },
          body: JSON.stringify(body),
          redirect: 'follow',
        });
        let respBody = await resp.text();
        if (respBody.includes('toNumbers') && respBody.includes('slowAES')) {
          const cookie = await solveChallenge(respBody);
          if (cookie) {
            const redirMatch = respBody.match(/location\.href="([^"]+)"/);
            const redirUrl = redirMatch ? redirMatch[1] : target;
            resp = await fetch(redirUrl, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'User-Agent': UA, 'Cookie': '__test=' + cookie },
              body: JSON.stringify(body),
              redirect: 'follow',
            });
            respBody = await resp.text();
          }
        }
        return new Response(respBody, {
          status: resp.status,
          headers: { 'Content-Type': 'application/json', ...CORS },
        });
      } catch (e) {
        return json({ error: 'upstream fetch failed', detail: String(e.message || e) }, 502);
      }
    }

    // ── GET /webhook-events — status page ──────────────────────────────
    if (request.method === 'GET' && url.pathname === '/webhook-events') {
      return new Response(`<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Discord Webhook Events</title>
<style>
  body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
  background:#0d1117;color:#c9d1d9;font-family:'Segoe UI',system-ui,sans-serif}
  .c{text-align:center;max-width:500px;padding:2rem}
  h1{color:#58a6ff;font-size:1.3rem;margin-bottom:.5rem}
  p{color:#8b949e;line-height:1.6;font-size:.9rem}
  .ok{color:#3fb950;font-size:.8rem;margin-top:1rem}
</style></head><body><div class="c">
  <h1>Discord Webhook Events</h1>
  <p>This endpoint receives Discord app webhook events and forwards them via DM or a configured webhook.</p>
  <p>Set this URL in your Discord Developer Portal → kevbin → Webhooks → Endpoint URL.</p>
  <p class="ok">Endpoint is live and accepting POST requests.</p>
</div></body></html>`, {
        status: 200,
        headers: { 'Content-Type': 'text/html; charset=utf-8', ...CORS },
      });
    }
    // ── POST /webhook-events — Discord app webhook events (Dev Portal) ──
    if (request.method === 'POST' && url.pathname === '/webhook-events') {
      const publicKey = env.DISCORD_PUBLIC_KEY;
      const rawBody = await request.clone().text();

      // Validate Ed25519 signature (required by Discord)
      if (publicKey) {
        const sig = request.headers.get('x-signature-ed25519') || '';
        const ts = request.headers.get('x-signature-timestamp') || '';
        if (sig && ts) {
          const valid = await verifyInteraction(sig, ts, rawBody, publicKey);
          if (!valid) return new Response('Invalid signature', { status: 401, headers: CORS });
        }
      }

      let body;
      try { body = JSON.parse(rawBody); } catch { body = null; }

      // PING (type: 0) — respond 204 with empty body (Discord requirement)
      if (body && (body.type === 0 || body.t === 'PING')) {
        return new Response(null, { status: 204, headers: { 'Content-Type': 'application/json', ...CORS } });
      }

      // Real event (type: 1)
      if (body && (body.type === 1 || body.event)) {
        const eventType = body.event?.name || body.t || 'UNKNOWN';
        const eventData = body.event || body.d || {};

        console.log('webhook event:', eventType, JSON.stringify(eventData).substring(0, 500));

        // Forward to webhook
        const forwardWebhook = env.DISCORD_FORWARD_WEBHOOK;
        if (forwardWebhook) {
          try {
            await fetch(forwardWebhook + '?wait=true', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                embeds: [{
                  title: 'Discord Event: ' + eventType,
                  description: '```json\n' + JSON.stringify(eventData, null, 2).substring(0, 3900) + '\n```',
                  color: 0x58a6ff,
                  timestamp: new Date().toISOString(),
                }],
              }),
            });
          } catch (e) { console.error('Forward failed:', e.message); }
        }

        // DM the user
        if (env.DISCORD_BOT_TOKEN) {
          try {
            const channelId = await getDMChannel(env.DISCORD_BOT_TOKEN, '1525402840577867803');
            if (channelId) {
              await fetch('https://discord.com/api/v10/channels/' + channelId + '/messages', {
                method: 'POST',
                headers: { 'Authorization': 'Bot ' + env.DISCORD_BOT_TOKEN, 'Content-Type': 'application/json' },
                body: JSON.stringify({
                  embeds: [{
                    title: 'Discord Event: ' + eventType,
                    description: '```json\n' + JSON.stringify(eventData, null, 2).substring(0, 3900) + '\n```',
                    color: 0x58a6ff,
                    timestamp: new Date().toISOString(),
                  }],
                }),
              });
            }
          } catch (e) { console.error('DM failed:', e.message); }
        }

        return new Response(null, { status: 204, headers: CORS });
      }

      // Unknown — still 204 to not block Discord
      return new Response(null, { status: 204, headers: { 'Content-Type': 'application/json', ...CORS } });
    }

    // ── POST /ping — TCP latency ping (PingChecker) ─────────────────
    if (request.method === 'POST' && url.pathname === '/ping') {
      const body = await readBody(request);
      if (!body) return json({ error: 'bad json' }, 400);
      const host = String(body.host || '').trim();
      const port = parseInt(body.port) || 80;
      if (!host) return json({ error: 'missing host' }, 400);

      const [ip, pingResult] = await Promise.all([
        resolveIP(host),
        tcpPing(host, port, Math.min(parseInt(body.timeout_ms) || 5000, 10000)),
      ]);

      return json({
        host,
        ip: ip || 'unknown',
        port,
        latency_ms: pingResult.latency_ms,
        ok: pingResult.ok,
        error: pingResult.error,
      });
    }

    // ── POST /check — HTTP health check (PingChecker) ──────────────
    if (request.method === 'POST' && url.pathname === '/check') {
      const body = await readBody(request);
      if (!body) return json({ error: 'bad json' }, 400);
      const targetUrl = String(body.url || '');
      if (!targetUrl) return json({ error: 'missing url' }, 400);

      try { new URL(targetUrl); } catch { return json({ error: 'invalid url' }, 400); }

      const result = await crawlPage(targetUrl, Math.min(parseInt(body.timeout_ms) || 10000, 20000));
      return json(result);
    }

    // ── POST /batch — multiple URL checks (PingChecker) ────────────
    if (request.method === 'POST' && url.pathname === '/batch') {
      const body = await readBody(request);
      if (!body || !Array.isArray(body.urls)) return json({ error: 'missing urls array' }, 400);

      const urls = body.urls.slice(0, 10).map(u => String(u));
      const results = await Promise.all(urls.map(u => crawlPage(u, 8000)));
      return json({ results });
    }

    // ── POST /crawl — recursive crawl (PingChecker) ────────────────
    if (request.method === 'POST' && url.pathname === '/crawl') {
      const body = await readBody(request);
      if (!body) return json({ error: 'bad json' }, 400);
      const startUrl = String(body.url || '');
      const maxDepth = Math.min(parseInt(body.depth) || 1, 3);
      const maxPages = Math.min(parseInt(body.max_pages) || 10, 50);
      if (!startUrl) return json({ error: 'missing url' }, 400);

      try { new URL(startUrl); } catch { return json({ error: 'invalid url' }, 400); }

      const visited = new Set();
      const pages = [];
      const queue = [{ url: startUrl, depth: 0 }];

      while (queue.length > 0 && pages.length < maxPages) {
        const { url: crawlUrl, depth } = queue.shift();
        if (visited.has(crawlUrl) || depth > maxDepth) continue;
        visited.add(crawlUrl);

        const result = await crawlPage(crawlUrl, 8000);
        pages.push({ ...result, depth });

        if (depth < maxDepth && result.links) {
          const origin = new URL(startUrl).origin;
          for (const link of result.links) {
            try {
              const lu = new URL(link);
              if (lu.origin === origin && !visited.has(link)) {
                queue.push({ url: link, depth: depth + 1 });
              }
            } catch {}
          }
        }
      }

      return json({
        start_url: startUrl,
        pages_found: pages.length,
        pages,
      });
    }

    // ── POST /shorten — KevBin own URL shortener ───────────────────
    if (request.method === 'POST' && url.pathname === '/shorten') {
      const body = await readBody(request);
      if (!body) return json({ error: 'bad json' }, 400);

      const targetUrl = String(body.url || '');
      if (!targetUrl || (!targetUrl.startsWith('http://') && !targetUrl.startsWith('https://'))) {
        return json({ error: 'invalid url — must start with http:// or https://' }, 400);
      }

      const slug = String(body.slug || '').trim();

      try {
        const formData = new URLSearchParams();
        formData.append('action', 'link.create');
        formData.append('url', targetUrl);
        if (slug) formData.append('slug', slug);

        const resp = await fetch('https://kevbin.ct.ws/api.php?action=link.create', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'User-Agent': 'KevBin-Worker/1.0',
          },
          body: formData.toString(),
          redirect: 'follow',
        });
        const data = await resp.json();
        if (data.ok) {
          return json({ ok: true, code: data.code, short: data.short, target: data.target, manage_key: data.manage_key });
        }
        return json({ ok: false, error: data.error || 'link.create failed' }, resp.status || 502);
      } catch (e) {
        return json({ ok: false, error: 'upstream error: ' + String(e.message || e) }, 502);
      }
    }

    // ── GET /l/:slug — KevBin short link redirect ──────────────────
    if (request.method === 'GET' && url.pathname.startsWith('/l/')) {
      const slug = url.pathname.substring(3);
      if (!slug || slug.length > 20) {
        return new Response('Invalid slug', { status: 400, headers: CORS });
      }

      try {
        const upstream = await fetchKevBin('https://kevbin.ct.ws/api.php?action=link.get&code=' + encodeURIComponent(slug));
        const data = JSON.parse(upstream.body);
        if (data.ok && data.url) {
          return Response.redirect(data.url, 302);
        }
        return new Response('Link not found', { status: 404, headers: CORS });
      } catch {
        return new Response('Link not found', { status: 404, headers: CORS });
      }
    }

    return new Response('Not Found', { status: 404, headers: CORS });
  },
};

// ── Ed25519 signature verification for Discord interactions ───────────
async function verifyInteraction(signature, timestamp, body, publicKey) {
  try {
    const encoder = new TextEncoder();
    const keyData = Uint8Array.from(
      publicKey.match(/.{1,2}/g).map(b => parseInt(b, 16))
    );
    const cryptoKey = await crypto.subtle.importKey(
      'raw', keyData,
      { name: 'Ed25519', namedCurve: 'Ed25519' },
      false, ['verify']
    );
    const sigData = Uint8Array.from(
      signature.match(/.{1,2}/g).map(b => parseInt(b, 16))
    );
    return await crypto.subtle.verify(
      'Ed25519', cryptoKey, sigData,
      encoder.encode(timestamp + body)
    );
  } catch {
    return false;
  }
}
