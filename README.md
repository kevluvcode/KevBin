# KevBin

A pastebin for the anonymity-minded: dark, fast, "secure & untraceable" — with accounts, a full OSINT toolkit, short-link tracking, a public API and hard anti-DevTools protection.

**Official site:** https://kevbin.ct.ws/ — you can run your own instance under any domain by changing `base_url` in `config.php`.

**Recommended host:** [InfinityFree](https://www.infinityfree.com) (free hosting with MySQL). Everything here is plain PHP 8 + MySQL — no frameworks, no build step.

## Features

- Anonymous paste publishing with expiry, edit keys and keyword watermarks
- Accounts with profiles, followers, notifications, comments, likes/dislikes
- Short links with a Grabify-style click tracker (IP, device, referrer, VPN/proxy/Tor detection)
- Link Tracker tool: create a redirect link that logs every click (IP, geo, device, fingerprint) with a live analytics panel
- guns.lol-style bio pages: custom `/b/yourname` URL with your display name, bio text, avatar, gradient backgrounds, accent color and a stack of link buttons
- Analytics trackers: image-pixel beacons (`t/px.php`) and short-link click tracking with a JS fingerprint beacon (`s/stat.php`)
- Password locks on pastes, staff roles, request queue, IP bans
- Read-only public API (`api.php`) + CORS-friendly bypass proxy (`api_proxy.php`)
- 100+ in-browser tools: Lua obfuscator + sandbox, site viewer, page cloner, link expander/bypasser, stealer checker, SHA-256 cracker (6s capped), JWT inspector, minifier, UID generator, hashes, encoders, QR, DNS, WHOIS/RDAP, header inspector, password generator, sens converter, games & more
- Per-user UI theme: choose your own site background (solid color or gradient), layout width (compact / default / wide) and accent color from Settings
- Account recovery keys: one-time random key shown at registration (no email needed) with a `forgot.php` reset flow
- Custom captcha on login / registration / password reset
- **Anti-DevTools + anti-debugger** (site-wide, "hard mode"):
  - Any sign the console or DevTools opened **instantly reloads the page** (a refresh, not a wipe)
  - `debugger;` timing trap — stepping through it while DevTools is open stalls the loop and triggers a reload
  - F12 / Ctrl+Shift+I / J / C / K / E, Ctrl+U view-source and Ctrl+S are blocked (and also trigger a reload)
  - Docked DevTools detected via window-size mismatch (on resize + every 500 ms) → reload
  - `console.log` probe catches DevTools that skip debugger pauses → reload
  - The same protection is applied on standalone public pages (e.g. bio pages)
- Self-hosted assets: bootstrap and fonts are served locally (zero third-party requests), strict CSP headers

## Install (InfinityFree)

1. Create a free account at https://www.infinityfree.com and create a MySQL database (note the hostname, db name, user, password).
2. Edit `config.php`: fill in your DB credentials, set `base_url` to your domain, and generate a random `recovery_salt`:
   ```bash
   php -r "echo bin2hex(random_bytes(32));"
   ```
3. Upload the whole folder to the `htdocs` directory of your account (in InfinityFree's file manager, find the correct *subdomain* folder, e.g. `htdocs` → `yourdomain.ct.ws`).
4. Open your site. The database tables are created automatically on first use (`auto_migrate` is on). If you prefer to create them by hand, run `schema_upgrade2.sql` in phpMyAdmin instead.
5. Create your account, then set the first admin role directly in phpMyAdmin:
   ```sql
   UPDATE users SET role = 'admin' WHERE username = 'yourname';
   ```

## Hardening notes

- All state-changing actions require CSRF tokens.
- SSRF guard on the OSINT/header tools: only public IPs are reachable, DNS is resolved server-side and pinned (`CURLOPT_RESOLVE`) so redirects cannot be rebinding-attacked into private networks, and the header inspector refuses non-public redirect targets.
- Passwords are bcrypt-hashed; recovery keys are stored as salted HMAC-SHA256 (the plain key is shown exactly once).
- Passwords must be at least 4 characters.

## API

`api.php?action=pastes` / `paste&id=` / `stats` / `online` / `tools` / `user`.
Full docs: `https://your-site/api_docs.php`.

## License

Open source. Use it, fork it, host your own — just keep the "education project" footer link intact.