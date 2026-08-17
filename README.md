# KevBin

A pastebin for the anonymity-minded: dark, fast, "secure & untraceable" — with accounts, a full OSINT toolkit, short-link tracking, a public API and hard anti-DevTools protection.

**Official site:** https://kevbin.ct.ws/ — you can run your own instance under any domain by changing `base_url` in `config.php`.

**Recommended host:** [InfinityFree](https://www.infinityfree.com) (free hosting with MySQL). Everything here is plain PHP 8 + MySQL — no frameworks, no build step.

## Features

- Anonymous paste publishing with expiry, edit keys and keyword watermarks
- Accounts with profiles, followers, notifications, comments, likes/dislikes
- Short links with a Grabify-style click tracker (IP, device, referrer, VPN/proxy/Tor detection)
- Password locks on pastes, staff roles, request queue, IP bans
- Read-only public API (`api.php`) + CORS-friendly bypass proxy (`api_proxy.php`)
- 35+ tools: Lua obfuscator, Lua runner (server-side interpreter), SHA-256 cracker (6s capped), JWT inspector, minifier, UID generator, hashes, encoders, QR, DNS, WHOIS/RDAP, header inspector, password generator & more
- Per-user UI theme: choose your own site background (solid color or gradient), layout width (compact / default / wide) and accent color from Settings
- Account recovery keys: one-time random key shown at registration (no email needed) with a `forgot.php` reset flow
- Custom captcha on login / registration / password reset
- **Anti-DevTools protection** (site-wide, "hard mode"):
  - Opening the console seals the page immediately
  - `window.console` is trapped — ANY access (including typing `console` in DevTools) wipes the page
  - F12 / Ctrl+Shift+I / J / C / K / E, Ctrl+U view-source and Ctrl+S are blocked
  - Docked DevTools detected via window-size mismatch (checked on resize + every 250 ms)
  - Floating/detached DevTools detected via debugger-statement timing (also on focus and visibility change)
  - Context-menu inspection and console reassignment are also wipe triggers
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