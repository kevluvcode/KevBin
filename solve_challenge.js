#!/usr/bin/env node
// KevBin — InfinityFree AES-CBC Anti-Bot Challenge Solver
//
// Usage:
//   node solve_challenge.js <html_file>        — solve from file
//   node solve_challenge.js --url <url>        — fetch URL, solve challenge, print cookie
//   echo '<html>...' | node solve_challenge.js --stdin
//
// Output: JSON { cookie, redirect_url, ok } or { ok: false, error }

const crypto = require('crypto');
const https = require('https');
const http = require('http');
const fs = require('fs');

function solveChallenge(html) {
  const regex = /toNumbers\("([0-9a-f]+)"\)/g;
  const matches = [...html.matchAll(regex)];
  if (matches.length < 3) return null;

  const a = Buffer.from(matches[0][1], 'hex');
  const b = Buffer.from(matches[1][1], 'hex');
  const c = Buffer.from(matches[2][1], 'hex');

  const decipher = crypto.createDecipheriv('aes-128-cbc', b, a);
  decipher.setAutoPadding(false);
  let dec = decipher.update(c);
  dec = Buffer.concat([dec, decipher.final()]);

  // Remove PKCS7 padding
  const padLen = dec[dec.length - 1];
  if (padLen >= 1 && padLen <= 16) {
    dec = dec.slice(0, dec.length - padLen);
  }

  return dec.toString('hex');
}

function extractRedirect(html) {
  const m = html.match(/location\.href="([^"]+)"/);
  return m ? m[1] : null;
}

function fetchUrl(url) {
  return new Promise((resolve, reject) => {
    const mod = url.startsWith('https') ? https : http;
    const req = mod.get(url, { headers: { 'User-Agent': 'Mozilla/5.0' } }, (res) => {
      let data = '';
      if (res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
        return fetchUrl(res.headers.location).then(resolve).catch(reject);
      }
      res.on('data', chunk => data += chunk);
      res.on('end', () => resolve(data));
    });
    req.on('error', reject);
    req.setTimeout(10000, () => { req.destroy(); reject(new Error('timeout')); });
  });
}

async function main() {
  const args = process.argv.slice(2);

  try {
    let html = '';

    if (args.includes('--url')) {
      const urlIdx = args.indexOf('--url') + 1;
      const url = args[urlIdx];
      if (!url) { console.log(JSON.stringify({ ok: false, error: 'missing URL after --url' })); process.exit(1); }
      html = await fetchUrl(url);
    } else if (args.includes('--stdin')) {
      html = fs.readFileSync('/dev/stdin', 'utf8');
    } else if (args.length > 0 && !args[0].startsWith('--')) {
      html = fs.readFileSync(args[0], 'utf8');
    } else {
      console.log(JSON.stringify({ ok: false, error: 'usage: node solve_challenge.js <file> | --url <url> | --stdin' }));
      process.exit(1);
    }

    if (!html.includes('toNumbers') || !html.includes('slowAES')) {
      console.log(JSON.stringify({ ok: false, error: 'no InfinityFree challenge found in input' }));
      process.exit(0);
    }

    const cookie = solveChallenge(html);
    const redirectUrl = extractRedirect(html);

    if (cookie) {
      console.log(JSON.stringify({ ok: true, cookie, redirect_url: redirectUrl }));
    } else {
      console.log(JSON.stringify({ ok: false, error: 'failed to solve challenge (bad decrypt)' }));
    }
  } catch (e) {
    console.log(JSON.stringify({ ok: false, error: e.message }));
  }
}

main();
