<?php
require_once __DIR__ . '/functions.php';

start_session();
$base = $GLOBALS['CFG']['base_url'];
page_header('Public API');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-1 reveal in-view">🔌 Public API</h1>
    <p class="text-secondary mb-1 reveal in-view">Free, read-only and open — no account, no API key, no login. Anyone can use it from <code>curl</code>, JavaScript, Python, Discord bots, PHP or any HTTP client.</p>
    <p class="text-secondary mb-4 reveal in-view">Everything is served from <code>api.php?action=...</code> as JSON with CORS enabled (<code>Access-Control-Allow-Origin: *</code>), so browsers can fetch it directly.</p>

    <div class="alert alert-secondary reveal in-view">
        <strong>Rules:</strong> GET requests only. You can read public data and use the tools listed below — you cannot post, create accounts or sign in through the API, and user browsing is intentionally not exposed. Only public (non-password-protected) pastes are readable.
    </div>

    <h2 class="h6 mt-4 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">Endpoints</h2>
    <div class="table-responsive reveal">
        <table class="table table-dark table-striped align-middle small" style="--bs-table-bg:#101010;">
            <thead>
            <tr><th>Endpoint</th><th>Returns</th><th>Example</th></tr>
            </thead>
            <tbody>
            <tr><td><code>?action=stats</code></td><td>site counters: users, pastes, links, tools, live online</td><td><code><?= e($base) ?>api.php?action=stats</code></td></tr>
            <tr><td><code>?action=online</code></td><td>live online count (real-time widget friendly)</td><td><code><?= e($base) ?>api.php?action=online</code></td></tr>
            <tr><td><code>?action=pastes</code></td><td>latest public pastes (add <code>&limit=50</code>, max 100)</td><td><code><?= e($base) ?>api.php?action=pastes&amp;limit=50</code></td></tr>
            <tr><td><code>?action=paste&amp;id=...</code></td><td>full content of one public paste</td><td><code><?= e($base) ?>api.php?action=paste&amp;id=abcdef12</code></td></tr>
            <tr><td><code>?action=tools</code></td><td>all public tools (usable by anyone, no account)</td><td><code><?= e($base) ?>api.php?action=tools</code></td></tr>
            <tr><td><code>?action=updates</code></td><td>the public update log</td><td><code><?= e($base) ?>api.php?action=updates</code></td></tr>
            <tr><td><code>?action=user</code></td><td>user count only (browsing profiles is not available)</td><td><code><?= e($base) ?>api.php?action=user</code></td></tr>
            </tbody>
        </table>
    </div>

    <h2 class="h6 mt-4 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">Quick start</h2>

    <div class="reveal">
        <div class="text-secondary small mb-1">curl</div>
<pre class="paste-content small">curl "<?= e($base) ?>api.php?action=stats"</pre>
    </div>

    <div class="mt-3 reveal">
        <div class="text-secondary small mb-1">JavaScript (works in any page, CORS-friendly)</div>
<pre class="paste-content small">fetch("<?= e($base) ?>api.php?action=online")
  .then(r =&gt; r.json())
  .then(d =&gt; console.log("online: " + d.online));

fetch("<?= e($base) ?>api.php?action=pastes&amp;limit=10")
  .then(r =&gt; r.json())
  .then(d =&gt; d.pastes.forEach(p =&gt; console.log(p.title, p.url)));</pre>
    </div>

    <div class="mt-3 reveal">
        <div class="text-secondary small mb-1">Python</div>
<pre class="paste-content small">import urllib.request, json

with urllib.request.urlopen("<?= e($base) ?>api.php?action=stats") as r:
    data = json.load(r)
print(data["stats"])</pre>
    </div>

    <div class="mt-3 reveal">
        <div class="text-secondary small mb-1">PHP</div>
<pre class="paste-content small">$data = json_decode(file_get_contents("<?= e($base) ?>api.php?action=stats"), true);
echo "users: " . $data["stats"]["users"];</pre>
    </div>

    <h2 class="h6 mt-4 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">Discord</h2>

    <p class="text-secondary small reveal"><strong>Option 1 — webhook, no code.</strong> Discords have webhooks, and webhooks accept JSON via any HTTP client. Feed KevBin live stats into a channel:</p>
<pre class="paste-content small">curl -H "Content-Type: application/json" \
  -d '{"username":"KevBin Stats","content":"Live now: '"$(curl -s "<?= e($base) ?>api.php?action=online" | grep -o '"online":[0-9]*' | cut -d: -f2)"'" users online on KevBin"}' \
  YOUR_DISCORD_WEBHOOK_URL</pre>

    <p class="text-secondary small mt-3 reveal"><strong>Option 2 — posted embeds.</strong> Paste a KevBin paste URL into Discord and it renders a rich embed automatically (title, description, tags). Keep view access public — password-protected pastes do not embed.</p>

    <p class="text-secondary small mt-3 reveal"><strong>Option 3 — discord.py bot.</strong> Fetch the API inside the bot and post stats to a channel:</p>
<pre class="paste-content small">import discord, urllib.request, json

API = "<?= e($base) ?>api.php"

@client.tree.command(name="kevbin")
async def kevbin(interaction: discord.Interaction):
    with urllib.request.urlopen(API + "?action=stats") as r:
        s = json.load(r)["stats"]
    await interaction.response.send_message(
        f"**KevBin live**\n👤 Users: {s['users']}\n📄 Pastes: {s['pastes']}\n"
        f"🟢 Online now: {s['online']}\n🔗 Links: {s['links']}")</pre>

    <div class="mt-3 reveal">
        <div class="text-secondary small mb-1">Example response (stats)</div>
<pre class="paste-content small">{
  "site": "KevBin",
  "generated_at": "2026-08-12T10:00:00+00:00",
  "stats": {
    "users": 1284,
    "pastes": 9021,
    "links": 312,
    "tools": 23,
    "online": 7,
    "online_window_seconds": 60
  }
}</pre>
    </div>
</div>
<?php page_footer(); ?>