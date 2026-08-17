# KevBin Discord OAuth — Cloudflare Worker bridge
Holds the Discord client_secret off InfinityFree (which blocks outbound calls)
and performs the OAuth token exchange in the Worker instead.

## Deploy
```
cd worker
npm i -g wrangler@latest
copy wrangler.toml.example wrangler.toml   # fill in values
wrangler login
wrangler secret put DISCORD_CLIENT_SECRET  # enter your secret (better than plain toml)
wrangler secret put DISCORD_BOT_TOKEN      # bot token used for auto-join (guilds.join)
wrangler deploy
```

## Setup steps
1. Create a Discord app at https://discord.com/developers/applications
   -> OAuth2 tab -> copy Client ID (public) and Client Secret (keep private).
2. In the OAuth2 tab add the redirect URL: `https://kevbin.ct.ws/discord_oauth.php`
   and enable the `identify` and `guilds.join` scopes (used on login).
3. Set env: `DISCORD_CLIENT_ID`, `REDIRECT_URI`, and `DISCORD_GUILD_IDS`
   (comma-separated guild IDs users should be auto-added to).
4. Create a bot (Applications -> Bot -> Add Bot) and add it to each guild you
   want users to auto-join, then set its token via `wrangler secret put DISCORD_BOT_TOKEN`.
   The bot needs the `CREATE_INSTANT_INVITE` permission (and must be in the guilds).
5. Deploy, note the worker URL (e.g. `https://kevbin-discord-bridge.workers.dev`).
6. Put that URL into `config.php` as `discord_bridge_url`.

## Endpoints
- `GET  /`                    status
- `GET  /oauth/authorize`     redirects browser to Discord
- `POST /oauth/exchange`      code -> safe profile JSON (no secret exposed)

## Auto-join
On login, the worker exchanges the code, fetches the profile, then calls
`PUT /guilds/{id}/members/{user}` for every ID in `DISCORD_GUILD_IDS` using the
bot token. The site's `discord_oauth.php` reads `joined_guilds` and can mention
the servers the user was added to. The bot must already be a member of each guild.