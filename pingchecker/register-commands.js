// register-commands.js — Register Discord slash commands for the KevBin bot
//
// Usage:
//   1. Set env vars:
//      BOT_TOKEN=your_bot_token_here
//      APP_ID=1538238715871105084
//   2. Run: node register-commands.js
//
// This registers /ping, /webhook, /site, /kevbin commands globally.
// Commands appear in Discord after ~1 hour (Discord caches them).

const APP_ID = process.env.APP_ID || '1538238715871105084';
const BOT_TOKEN = process.env.BOT_TOKEN;
const WORKER_URL = process.env.WORKER_URL || 'https://autumn-term-1e5c.kevinkevcounts1.workers.dev';

const COMMANDS = [
  {
    name: 'ping',
    description: 'Check bot latency',
    type: 1,
  },
  {
    name: 'webhook',
    description: 'Send a message via a Discord webhook',
    type: 1,
    options: [
      {
        name: 'url',
        description: 'Webhook URL (https://discord.com/api/webhooks/...)',
        type: 3,
        required: true,
      },
      {
        name: 'content',
        description: 'Message text (max 2000 chars)',
        type: 3,
        required: false,
      },
      {
        name: 'title',
        description: 'Embed title',
        type: 3,
        required: false,
      },
      {
        name: 'description',
        description: 'Embed description',
        type: 3,
        required: false,
      },
      {
        name: 'color',
        description: 'Embed color hex (e.g. #FF0000)',
        type: 3,
        required: false,
      },
    ],
  },
  {
    name: 'site',
    description: 'Quick health check on a URL',
    type: 1,
    options: [
      {
        name: 'url',
        description: 'URL to check',
        type: 3,
        required: true,
      },
    ],
  },
  {
    name: 'kevbin',
    description: 'KevBin API — live stats, pastes and tools',
    type: 1,
    options: [
      {
        name: 'subcommand',
        description: 'What to look up',
        type: 3,
        required: true,
        choices: [
          { name: 'stats', value: 'stats', description: 'Live site stats (users, pastes, online)' },
          { name: 'pastes', value: 'pastes', description: 'Latest public pastes' },
          { name: 'tools', value: 'tools', description: 'Available tools' },
        ],
      },
      {
        name: 'limit',
        description: 'Number of pastes to show (1–10, default 5)',
        type: 4,
        required: false,
      },
    ],
  },
];

async function register() {
  if (!BOT_TOKEN) {
    console.error('Set BOT_TOKEN env var first.\n  $env:BOT_TOKEN="your_token"\n  node register-commands.js');
    process.exit(1);
  }

  console.log(`Registering ${COMMANDS.length} commands for app ${APP_ID}...`);

  // Global commands (takes ~1 hour to propagate)
  const url = `https://discord.com/api/v10/applications/${APP_ID}/commands`;

  for (const cmd of COMMANDS) {
    const resp = await fetch(url, {
      method: 'PUT',
      headers: {
        'Authorization': `Bot ${BOT_TOKEN}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(cmd),
    });
    const data = await resp.json();
    if (data.id) {
      console.log(`  ✅ /${cmd.name} registered (id: ${data.id})`);
    } else {
      console.error(`  ❌ /${cmd.name} failed:`, JSON.stringify(data));
    }
  }

  console.log('\nDone! Commands will appear in Discord within ~1 hour.');
  console.log(`Make sure your Interactions Endpoint URL is set to: ${WORKER_URL}/interactions`);
}

register().catch(e => { console.error(e); process.exit(1); });
