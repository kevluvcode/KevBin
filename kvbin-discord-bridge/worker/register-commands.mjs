#!/usr/bin/env node
// Register the kevbin-linker slash commands with Discord.
//
// Usage:
//   set DISCORD_BOT_TOKEN=your_bot_token   (PowerShell: $env:DISCORD_BOT_TOKEN=...)
//   node register-commands.mjs
//
// Global commands apply to every guild the bot is in. Add a guild ID as an
// argument to register only there while testing:
//   node register-commands.mjs 1538386751649882234
//
// See: https://discord.com/developers/docs/interactions/application-commands

const token = process.env.DISCORD_BOT_TOKEN || '';
const guild = process.argv[2];

if (!token) {
  console.error('Missing DISCORD_BOT_TOKEN env var.');
  process.exit(1);
}

const commands = [
  {
    name: 'link',
    description: 'Connect your Discord to KevBin (one-click login, nothing is posted).',
  },
  {
    name: 'site',
    description: 'Get the KevBin site link and support page.',
  },
  {
    name: 'ping',
    description: 'Check that the bot is alive.',
  },
];

const url = guild
  ? `https://discord.com/api/v10/applications/${process.env.APP_ID || '1538238715871105084'}/guilds/${guild}/commands`
  : `https://discord.com/api/v10/applications/${process.env.APP_ID || '1538238715871105084'}/commands`;

const res = await fetch(url, {
  method: 'PUT',
  headers: {
    Authorization: 'Bot ' + token,
    'Content-Type': 'application/json',
  },
  body: JSON.stringify(commands),
});

const body = await res.text();
console.log(res.status, body.slice(0, 2000));
