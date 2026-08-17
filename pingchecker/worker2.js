// KevBin PingChecker — Cloudflare Worker (worker2)
//
// Site uptime monitor, HTTP health check, ping latency, and lightweight crawler.
// Deploy:  wrangler deploy (as a separate worker)
//
// Endpoints:
//   GET  /                          → info page
//   POST /ping                      → {host} → {host, ip, latency_ms, status}
//   POST /check                     → {url, follow_redirects} → {url, status, ok, response_time_ms, headers, title, ssl}
//   POST /batch                     → {urls: [...]} → [{url, status, ok, response_time_ms}, ...]
//   POST /crawl                     → {url, depth, max_pages} → {url, status, title, links, images, scripts, meta, pages}

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

// TCP connect timing — rough "ping" via connect() latency
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

// Resolve hostname → IP via DNS
async function resolveIP(host) {
  try {
    const resp = await fetch(`https://dns.google/resolve?name=${encodeURIComponent(host)}&type=A`);
    const data = await resp.json();
    if (data.Answer && data.Answer.length > 0) {
      const a = data.Answer.find(r => r.type === 1);
      if (a) return a.data;
    }
  } catch {}
  return null;
}

// Extract <title> from HTML
function extractTitle(html) {
  const m = html.match(/<title[^>]*>([^<]{1,200})<\/title>/i);
  return m ? m[1].trim() : '';
}

// Extract meta tags
function extractMeta(html) {
  const metas = {};
  const re = /<meta\s+(?:name|property|http-equiv)\s*=\s*["']([^"']+)["']\s+content\s*=\s*["']([^"']{0,500})["']/gi;
  let m;
  while ((m = re.exec(html)) !== null) {
    metas[m[1]] = m[2];
  }
  return metas;
}

// Extract links from HTML
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

// Crawl a page and extract info
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

    if (request.method === 'OPTIONS') {
      return new Response(null, { status: 204, headers: CORS });
    }

    // GET / — info page
    if (request.method === 'GET' && url.pathname === '/') {
      return new Response(`<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>KevBin PingChecker</title>
<style>
  body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
  background:#0d1117;color:#c9d1d9;font-family:'Segoe UI',system-ui,sans-serif}
  .c{text-align:center;max-width:560px;padding:2rem}
  h1{color:#3fb950;font-size:1.4rem;margin-bottom:.5rem}
  p{color:#8b949e;line-height:1.6;font-size:.9rem}
  code{background:#161b22;padding:2px 6px;border-radius:4px;font-size:.85rem;color:#f0883e}
  .ok{color:#3fb950;font-size:.8rem;margin-top:1rem}
  ul{text-align:left;font-size:.82rem;color:#8b949e;line-height:1.8}
</style></head><body><div class="c">
  <h1>KevBin PingChecker</h1>
  <p>Site uptime monitor, HTTP health checks, latency ping and lightweight crawler.</p>
  <ul>
    <li><code>POST /ping</code> — TCP connect ping {host} → latency</li>
    <li><code>POST /check</code> — HTTP health check {url} → status + response time</li>
    <li><code>POST /batch</code> — Check multiple URLs at once</li>
    <li><code>POST /crawl</code> — Crawl a page: links, images, meta, title</li>
  </ul>
  <p class="ok">Worker is live and healthy.</p>
</div></body></html>`, {
        status: 200,
        headers: { 'Content-Type': 'text/html; charset=utf-8', ...CORS },
      });
    }

    // POST /ping — TCP latency ping
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

    // POST /check — HTTP health check
    if (request.method === 'POST' && url.pathname === '/check') {
      const body = await readBody(request);
      if (!body) return json({ error: 'bad json' }, 400);
      const targetUrl = String(body.url || '');
      if (!targetUrl) return json({ error: 'missing url' }, 400);

      try { new URL(targetUrl); } catch { return json({ error: 'invalid url' }, 400); }

      const result = await crawlPage(targetUrl, Math.min(parseInt(body.timeout_ms) || 10000, 20000));
      return json(result);
    }

    // POST /batch — multiple URL checks
    if (request.method === 'POST' && url.pathname === '/batch') {
      const body = await readBody(request);
      if (!body || !Array.isArray(body.urls)) return json({ error: 'missing urls array' }, 400);

      const urls = body.urls.slice(0, 10).map(u => String(u));
      const results = await Promise.all(urls.map(u => crawlPage(u, 8000)));
      return json({ results });
    }

    // POST /crawl — recursive crawl
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

        // Queue discovered links (same origin only)
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

    return new Response('Not Found', { status: 404, headers: CORS });
  },
};
