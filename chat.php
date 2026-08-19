<?php
require_once __DIR__ . '/functions.php';

start_session();
$cfg = $GLOBALS['CFG'];
$me = $GLOBALS['me'] ?? null;

const CHAT_MAX_PEERS = 7;
const CHAT_NAME_MAX = 24;

function chat_clean_name(string $raw): string
{
    $n = trim(strip_tags($raw));
    $n = preg_replace('/[^\p{L}\p{N} _.\-]/u', '', $n);
    $n = preg_replace('/\s+/u', ' ', $n);
    $n = mb_substr($n, 0, CHAT_NAME_MAX);
    return trim($n);
}

function chat_room_token(): string
{
    return bin2hex(random_bytes(3));
}

function chat_identity(): ?array
{
    if (empty($_SESSION['chat_ident'])) {
        return null;
    }
    return [
        'uid'  => (string)$_SESSION['chat_ident']['uid'],
        'name' => (string)$_SESSION['chat_ident']['name'],
    ];
}

function chat_resolve_ident(): ?array
{
    $ident = chat_identity();
    if ($ident !== null) {
        return $ident;
    }
    if (!empty($GLOBALS['me'])) {
        $m = $GLOBALS['me'];
        return ['uid' => 'u' . (int)$m['id'], 'name' => (string)$m['username']];
    }
    return null;
}

function chat_fix_room(string $r): string
{
    $r = preg_replace('/[^A-Za-z0-9_-]/', '', $r);
    return mb_substr($r, 0, 32);
}

function chat_json(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// ————— Landing / identity setup —————
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chat_join'])) {
    csrf_verify_or_fail();
    if (!rate_limit_check('chat_join', 15, 60)) {
        friendly_error('Too many join attempts. Slow down.', 429);
    }
    $name = chat_clean_name((string)($_POST['name'] ?? ''));
    if ($name === '' || mb_strlen($name) < 2) {
        friendly_error('Display name must be 2–' . CHAT_NAME_MAX . ' characters (letters, numbers, space, dot, dash).', 400);
    }
    $uid = $GLOBALS['me'] !== null ? 'u' . (int)$GLOBALS['me']['id'] : 'g' . bin2hex(random_bytes(8));
    if (isset($_POST['as_account']) && $GLOBALS['me'] !== null) {
        $name = (string)$GLOBALS['me']['username'];
    }
    $_SESSION['chat_ident'] = ['uid' => $uid, 'name' => $name];
    $room = chat_fix_room((string)($_POST['r'] ?? ''));
    if ($room === '' || $room === 'random') {
        $room = chat_room_token();
    }
    header('Location: ' . url('chat.php?r=' . rawurlencode($room)));
    exit;
}

$ident = chat_resolve_ident();

// ————— JSON API (needs identity) —————
$api = (string)($_GET['api'] ?? '');
if ($api !== '') {
    if ($ident === null) {
        chat_json(['error' => 'no_identity'], 401);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        chat_json(['error' => 'post_only'], 405);
    }
    if (!csrf_verify()) {
        chat_json(['error' => 'bad_csrf'], 403);
    }
    $pdo = db();
    $room = chat_fix_room((string)($_POST['room'] ?? ''));
    if ($room === '') {
        chat_json(['error' => 'no_room'], 400);
    }
    $uid = $ident['uid'];
    $name = $ident['name'];

    switch ($api) {
        case 'join':
            if (!rate_limit_check('chat_join', 15, 60)) {
                chat_json(['error' => 'rate_limited'], 429);
            }
            $st = $pdo->prepare('SELECT COUNT(*) FROM chat_presence WHERE room = ? AND uid <> ? AND last_seen > (UTC_TIMESTAMP() - INTERVAL 25 SECOND)');
            $st->execute([$room, $uid]);
            if ((int)$st->fetchColumn() >= CHAT_MAX_PEERS) {
                chat_json(['error' => 'room_full'], 409);
            }
            $pdo->prepare(
                "INSERT INTO chat_presence (room, uid, name, last_seen) VALUES (?, ?, ?, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE name = VALUES(name), last_seen = UTC_TIMESTAMP()"
            )->execute([$room, $uid, $name]);
            chat_json(['ok' => true, 'peers' => chat_peers($pdo, $room, $uid)]);
            // no break

        case 'poll':
            $sinceMsg = max(0, (int)($_POST['since_message'] ?? 0));
            $sinceSig = max(0, (int)($_POST['since_signal'] ?? 0));
            $pdo->prepare(
                "INSERT INTO chat_presence (room, uid, name, last_seen) VALUES (?, ?, ?, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE name = VALUES(name), last_seen = UTC_TIMESTAMP()"
            )->execute([$room, $uid, $name]);
            // GC: stale presence + old signals (this room only).
            $pdo->prepare('DELETE FROM chat_presence WHERE room = ? AND last_seen < (UTC_TIMESTAMP() - INTERVAL 45 SECOND)')->execute([$room]);
            $pdo->prepare('DELETE FROM chat_signals WHERE room = ? AND created_at < (UTC_TIMESTAMP() - INTERVAL 150 SECOND)')->execute([$room]);
            $sig = $pdo->prepare('SELECT id, sender_uid, kind, payload FROM chat_signals WHERE room = ? AND to_uid = ? AND id > ? AND sender_uid <> ? ORDER BY id LIMIT 100');
            $sig->execute([$room, $uid, $sinceSig, $uid]);
            $signals = [];
            $highest = $sinceSig;
            foreach ($sig as $row) {
                $signals[] = ['id' => (int)$row['id'], 'from' => $row['sender_uid'], 'kind' => $row['kind'], 'payload' => $row['payload']];
                $highest = max($highest, (int)$row['id']);
            }
            $msg = $pdo->prepare('SELECT id, sender_uid, sender_name, body, created_at FROM chat_messages WHERE room = ? AND id > ? ORDER BY id LIMIT 200');
            $msg->execute([$room, $sinceMsg]);
            $messages = [];
            $msgHighest = $sinceMsg;
            foreach ($msg as $row) {
                $messages[] = [
                    'id'   => (int)$row['id'],
                    'uid'  => $row['sender_uid'],
                    'name' => $row['sender_name'],
                    'body' => $row['body'],
                    'ts'   => str_replace(' ', 'T', $row['created_at']) . 'Z',
                ];
                $msgHighest = (int)$row['id'];
            }
            chat_json(['ok' => true, 'peers' => chat_peers($pdo, $room, $uid), 'signals' => $signals, 'since_signal' => $highest, 'messages' => $messages, 'since_message' => $msgHighest]);
            // no break

        case 'signal':
            if (!rate_limit_check('chat_sig', 30, 5)) {
                chat_json(['error' => 'rate_limited'], 429);
            }
            $to = (string)($_POST['to'] ?? '');
            if (preg_match('/^[A-Za-z0-9_]{1,48}$/', $to) !== 1) {
                chat_json(['error' => 'bad_to'], 400);
            }
            $kind = in_array((string)($_POST['kind'] ?? ''), ['offer', 'answer', 'ice', 'poke'], true) ? (string)$_POST['kind'] : 'sdp';
            $payload = mb_substr((string)($_POST['payload'] ?? ''), 0, 50000);
            if ($payload === '') {
                chat_json(['error' => 'no_payload'], 400);
            }
            $pdo->prepare('INSERT INTO chat_signals (room, sender_uid, to_uid, kind, payload) VALUES (?, ?, ?, ?, ?)')
                ->execute([$room, $uid, $to, $kind, $payload]);
            chat_json(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
            // no break

        case 'say':
            if (!rate_limit_check('chat_say', 20, 4)) {
                chat_json(['error' => 'rate_limited'], 429);
            }
            $body = trim(strip_tags((string)($_POST['body'] ?? '')));
            $body = mb_substr($body, 0, 1000);
            if ($body === '') {
                chat_json(['error' => 'no_body'], 400);
            }
            $pdo->prepare('INSERT INTO chat_messages (room, sender_uid, sender_name, body) VALUES (?, ?, ?, ?)')
                ->execute([$room, $uid, $name, $body]);
            chat_json(['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'ts' => gmdate('Y-m-d\TH:i:s\Z')]);
            // no break

        case 'leave':
            $pdo->prepare('DELETE FROM chat_presence WHERE room = ? AND uid = ?')->execute([$room, $uid]);
            chat_json(['ok' => true]);
            // no break

        case 'report':
            if (!rate_limit_check('chat_report', 3, 60)) {
                chat_json(['error' => 'rate_limited'], 429);
            }
            $target = (string)($_POST['target_uid'] ?? '');
            $reason = mb_substr(strip_tags((string)($_POST['reason'] ?? '')), 0, 120);
            if ($reason === '') {
                $reason = 'Unspecified concern';
            }
            $pdo->prepare('INSERT INTO chat_reports (room, reporter_uid, target_uid, reason) VALUES (?, ?, ?, ?)')
                ->execute([$room, $uid, $target !== '' ? $target : null, $reason]);
            chat_json(['ok' => true]);
            // no break

        default:
            chat_json(['error' => 'unknown_api'], 400);
    }
}

// ————— Page rendering —————
$room = isset($_GET['r']) ? chat_fix_room((string)$_GET['r']) : '';

if ($ident !== null) {
    $_SESSION['chat_ident'] = $ident; // persist resolves
    if ($room === '') {
        header('Location: ' . url('chat.php?r=' . chat_room_token()));
        exit;
    }
} else {
    // Not identified yet → join screen. Pass along any requested room.
    $room = isset($_GET['r']) ? trim((string)$_GET['r']) : '';
    if ($room !== '' && $room !== 'random') {
        $room = chat_fix_room($room);
    }
}

// Mobile-first: mark body for layout tweaks.
$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
$isMobile = (bool)preg_match('/(Mobile|Android|iPhone|iPod|BlackBerry|Windows Phone)/i', $ua);

page_header('Video Chat');
?>
<style>
    .vc-root { max-width: 1280px; margin: 0 auto; }
    .vt-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: .65rem;
        width: 100%;
    }
    .vid-tile {
        position: relative;
        aspect-ratio: 4 / 3;
        background: #05070c;
        border: 1px solid var(--line);
        border-radius: 12px;
        overflow: hidden;
        min-height: 120px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .vid-tile video {
        position: absolute; inset: 0;
        width: 100%; height: 100%;
        object-fit: cover;
        background: #05070c;
    }
    .vid-tile .vt-ph {
        color: #7b8494; font-size: 2.4rem;
        width: 56px; height: 56px;
        border-radius: 50%;
        background: #171c26;
        display: flex; align-items: center; justify-content: center;
    }
    .vid-tile .vt-label {
        position: absolute; left: 8px; top: 8px;
        background: rgba(0,0,0,.55);
        color: #fff; font-size: .72rem;
        padding: 3px 9px; border-radius: 999px;
        backdrop-filter: blur(6px);
        display: flex; align-items: center; gap: 6px;
        max-width: calc(100% - 16px);
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .vt-label .dot { width: 7px; height: 7px; border-radius: 50%; background: #ffb020; flex: none; }
    .vt-label.live .dot { background: #26d07c; }
    .vid-tile .vt-muted {
        position: absolute; right: 8px; top: 8px;
        background: rgba(0,0,0,.55); color: #ffd0d0;
        font-size: .72rem; padding: 2px 7px; border-radius: 8px;
        display: none;
    }
    .vid-tile .vt-report {
        position: absolute; right: 8px; bottom: 8px;
        background: rgba(0,0,0,.55); color: #ffb020;
        font-size: .72rem; padding: 2px 8px; border-radius: 8px;
        border: 1px solid rgba(255,176,32,.4); cursor: pointer;
        display: none;
    }
    .vid-tile:hover .vt-report, .vid-tile.reportable .vt-report { display: block; }
    .vid-tile .vt-status {
        position: absolute; left: 8px; bottom: 8px;
        color: #9aa3b2; font-size: .7rem;
        background: rgba(0,0,0,.5); padding: 2px 8px; border-radius: 8px;
    }
    .vid-tile.self {
        position: fixed;
        right: 12px; bottom: 74px;
        width: 180px; height: 135px;
        z-index: 40;
        box-shadow: 0 6px 24px rgba(0,0,0,.55);
        border-width: 1px;
    }
    body.kb-mobile .vid-tile.self { width: 42vw; height: auto; aspect-ratio: 4/3; min-width: 110px; }
    body.kb-mobile .vid-tile.self .vt-label { display: none; }

    .vc-dock {
        position: fixed; left: 50%; transform: translateX(-50%);
        bottom: 12px; z-index: 50;
        display: flex; gap: 10px; align-items: center;
        background: rgba(13,16,24,.92);
        border: 1px solid var(--line);
        border-radius: 999px; padding: 8px 14px;
        backdrop-filter: blur(10px);
        box-shadow: 0 8px 30px rgba(0,0,0,.5);
        max-width: calc(100vw - 20px);
    }
    .vc-dock button {
        width: 46px; height: 46px;
        border-radius: 50%;
        border: 1px solid var(--line);
        background: #171c26; color: #e8ecf4;
        font-size: 1.05rem;
        display: flex; align-items: center; justify-content: center;
        padding: 0;
        transition: all .15s ease;
    }
    .vc-dock button:hover { border-color: #5865f2; }
    .vc-dock button.on { background: #5865f2; border-color: #5865f2; color: #fff; }
    .vc-dock button.off { background: #3a1113; border-color: #7a2430; color: #ff9aa5; }
    .vc-dock button.hang { background: #d63447; border-color: #d63447; color: #fff; width: 54px; height: 54px; }
    .vc-dock button.hang:hover { background: #f04256; }
    .vc-dock .unread-badge {
        position: absolute; top: -4px; right: -4px;
        min-width: 18px; height: 18px; line-height: 18px;
        background: #d63447; color: #fff; font-size: .68rem; font-weight: 600;
        border-radius: 999px; text-align: center; padding: 0 4px;
        display: none;
    }
    .vc-dock .wrap { position: relative; }

    .chat-panel {
        position: fixed;
        top: 58px; right: 14px; bottom: 86px;
        width: min(330px, calc(100vw - 28px));
        z-index: 45;
        background: rgba(13,16,24,.96);
        border: 1px solid var(--line);
        border-radius: 16px;
        display: flex; flex-direction: column;
        overflow: hidden;
        box-shadow: 0 12px 40px rgba(0,0,0,.6);
        transform: translateX(calc(100% + 30px));
        transition: transform .22s ease;
    }
    .chat-panel.open { transform: none; }
    body.kb-mobile .chat-panel {
        left: 0; right: 0; top: auto;
        bottom: 0; width: 100%;
        max-height: 62dvh;
        border-radius: 18px 18px 0 0;
        transform: translateY(105%);
    }
    body.kb-mobile .chat-panel.open { transform: none; }
    .chat-msgs {
        flex: 1; overflow-y: auto;
        padding: 12px 14px;
        display: flex; flex-direction: column; gap: 8px;
        scroll-behavior: smooth;
    }
    .cm { display: flex; flex-direction: column; gap: 1px; }
    .cm .cm-head { font-size: .72rem; color: #8b94a6; display: flex; gap: 8px; align-items: baseline; }
    .cm .cm-head b { color: #cdd5e2; font-weight: 600; max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cm .cm-head .ts { font-size: .65rem; color: #5d6678; flex: none; }
    .cm .cm-body {
        background: #171c26; border: 1px solid var(--line);
        border-radius: 10px; padding: 6px 11px;
        font-size: .85rem; word-break: break-word; white-space: pre-wrap;
        align-self: flex-start; max-width: 92%;
        color: #e8ecf4;
    }
    .cm.mine .cm-body { background: #232a44; border-color: #34406e; align-self: flex-end; }
    .cm.sys .cm-body { background: transparent; border: 1px dashed var(--line); color: #8b94a6; align-self: center; font-size: .75rem; }
    .chat-input { display: flex; gap: 8px; padding: 10px 12px; border-top: 1px solid var(--line); }
    .chat-input input { flex: 1; background: #0b0e15; border: 1px solid var(--line); border-radius: 999px; padding: 8px 14px; color: #fff; font-size: .85rem; }
    .chat-input input:focus { outline: none; border-color: #5865f2; }
    .chat-input button {
        border: 1px solid var(--line); background: #171c26; color: #fff;
        border-radius: 999px; padding: 8px 16px; font-size: .85rem;
    }
    .chat-input button:hover { border-color: #5865f2; }

    .vc-topbar {
        display: flex; align-items: center; gap: 10px;
        flex-wrap: wrap; margin-bottom: 12px;
    }
    .vc-roomcode {
        font-family: 'JetBrains Mono', monospace;
        background: #171c26; border: 1px solid var(--line);
        border-radius: 10px; padding: 6px 12px; font-size: .85rem;
    }
    .vc-hint { font-size: .8rem; color: var(--text-secondary, #9aa3b2); }
    .join-card { max-width: 480px; margin: 4vh auto; }
    body.kb-mobile .join-card { margin-top: 2vh; }
    .lang-note { font-size: .78rem; }
</style>

<?php if ($ident === null): ?>
<div class="container vc-root">
    <div class="card join-card"><div class="card-body">
        <h1 class="h4 mb-1">📹 Video Chat</h1>
        <p class="text-secondary mb-3 lang-note">Peer-to-peer video chat — no account needed. Pick a display name and join a room. Rooms work via shared links (<code>?r=</code>) or you can roll a random one.</p>
        <?php if ($me !== null): ?>
            <form method="post" class="mb-3">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="r" value="<?= e($room) ?>">
                <input type="hidden" name="as_account" value="1">
                <button class="btn btn-primary w-100" name="chat_join" value="1">Join as <?= e($me['username']) ?></button>
            </form>
            <div class="text-center text-secondary mb-2">— or use a custom name —</div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="r" value="<?= e($room) ?>">
            <label class="form-label" for="vc-name">Display name</label>
            <input id="vc-name" name="name" class="form-control mb-3" maxlength="24" placeholder="e.g. Volt 🐱" required autofocus>
            <button class="btn btn-primary w-100" name="chat_join" value="1">
                <?= $room !== '' ? 'Join room' : 'Roll a random room →' ?>
            </button>
        </form>
        <p class="text-secondary mt-3 mb-1 lang-note">🔒 Rooms are unlisted: each <code>?r=</code> link is a separate room with a random code — no search, no lobby listing. Video goes peer-to-peer (WebRTC, public STUN, no TURN — best results on the same network); text messages relay through the server. Don't share anything sensitive, and never trust remote video.
        </p>
    </div></div>
</div>
<?php else: ?>
<div class="container vc-root">
    <div class="vc-topbar">
        <h1 class="h4 mb-0">📹 <span id="vc-room" class="vc-roomcode"><?= e($room) ?></span></h1>
        <button class="btn btn-outline-light btn-sm" onclick="copyLink()" title="Copy room invite link">🔗 Invite</button>
        <span class="text-secondary small">as <b id="vc-myname"><?= e($ident['name']) ?></b></span>
        <span class="ms-auto text-secondary small" id="vc-note">starting…</span>
        <button class="btn btn-outline-danger btn-sm" onclick="leaveRoom(true)">Leave</button>
    </div>

    <div id="vc-grid" class="vt-grid"></div>

    <div class="vc-dock" id="vc-dock">
        <button id="btn-mic" class="on" title="Microphone" onclick="toggleMic()">🎤</button>
        <button id="btn-cam" class="on" title="Camera" onclick="toggleCam()">📷</button>
        <button id="btn-camswitch" title="Switch camera" onclick="switchCam()" style="display:none">🔄</button>
        <div class="wrap">
            <button id="btn-chat" title="Chat" onclick="toggleChat()">💬</button>
            <span class="unread-badge" id="chat-unread">0</span>
        </div>
        <button class="hang" title="Leave room" onclick="leaveRoom(true)">📞</button>
    </div>

    <aside class="chat-panel" id="chat-panel">
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom" style="border-color:var(--line)!important;">
            <span class="small fw-semibold">Room chat</span>
            <button class="btn btn-sm btn-outline-light" onclick="toggleChat()">✕</button>
        </div>
        <div class="chat-msgs" id="chat-msgs"></div>
        <form class="chat-input" onsubmit="sendChat(event)">
            <input id="chat-body" maxlength="1000" placeholder="Message room…" autocomplete="off">
            <button type="submit">Send</button>
        </form>
    </aside>
</div>

<script>
    var CHAT = {
        uid: <?= json_encode($ident['uid']) ?>,
        name: <?= json_encode($ident['name']) ?>,
        room: <?= json_encode($room) ?>,
        csrf: <?= json_encode(csrf_token()) ?>,
        peers: {},
        sinceMsg: 0,
        sinceSig: 0,
        stream: null,
        chatOpen: (window.innerWidth >= 768),
        unread: 0,
        timer: null,
        leaving: false,
        micOn: true,
        camOn: true,
        secure: (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1'),
        mobile: <?= $isMobile ? 'true' : 'false' ?>
    };
    var STUNS = [{ urls: ['stun:stun.l.google.com:19302', 'stun:stun1.l.google.com:19302'] }];

    function note(txt, err) {
        var el = document.getElementById('vc-note');
        el.textContent = txt;
        el.className = 'ms-auto text-secondary small' + (err ? ' text-warning' : '');
    }

    function api(action, data, okCb) {
        var fd = new URLSearchParams(data);
        fd.set('csrf', CHAT.csrf);
        fetch('chat.php?api=' + action, { method: 'POST', body: fd })
            .then(function (r) {
                if (r.status === 401) { location.href = 'chat.php'; throw new Error('ident'); }
                return r.json();
            })
            .then(okCb)
            .catch(function (e) { if (e.message !== 'ident') console.warn('[chat] ' + action, e); });
    }

    function copyLink() {
        var url = location.origin + location.pathname + '?r=' + encodeURIComponent(CHAT.room);
        var done = function () { flash('Invite link copied ✅'); };
        if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(url).then(done, function () { window.prompt('Copy this link:', url); done(); });
        else { window.prompt('Copy this link:', url); done(); }
    }
    function flash(msg) {
        note(msg);
        setTimeout(function () { note(''); }, 2500);
    }

    // ————— Text chat —————
    function sendChat(e) {
        e.preventDefault();
        var b = document.getElementById('chat-body');
        var body = b.value.trim();
        if (!body) return;
        api('say', { room: CHAT.room, body: body }, function () {
            b.value = '';
            b.focus();
        });
    }
    function addMsg(m) {
        var box = document.getElementById('chat-msgs');
        var no = box.querySelector('.cm');
        if (no && no.classList.contains('sys')) no.remove();
        var div = document.createElement('div');
        div.className = 'cm' + (m.uid === CHAT.uid ? ' mine' : '') + (m.uid ? '' : ' sys');
        var head = m.uid ? '<div class="cm-head"><b>' + esc(m.name) + '</b><span class="ts">' + fmtTs(m.ts) + '</span></div>' : '';
        div.innerHTML = head + '<div class="cm-body">' + esc(m.body) + '</div>';
        box.appendChild(div);
        box.scrollTop = box.scrollHeight;
        if (m.uid !== CHAT.uid && !CHAT.chatOpen) {
            CHAT.unread++;
            document.getElementById('chat-unread').style.display = 'block';
            document.getElementById('chat-unread').textContent = CHAT.unread;
            beep();
        }
    }
    function toggleChat() {
        CHAT.chatOpen = !CHAT.chatOpen;
        document.getElementById('chat-panel').classList.toggle('open', CHAT.chatOpen);
        if (CHAT.chatOpen) {
            CHAT.unread = 0;
            document.getElementById('chat-unread').style.display = 'none';
        }
        if (CHAT.chatOpen) document.getElementById('chat-body').focus();
    }
    function fmtTs(ts) {
        var d = new Date(ts);
        if (isNaN(d)) return '';
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    function esc(s) {
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }
    function beep() {
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();
            var o = ctx.createOscillator(), g = ctx.createGain();
            o.frequency.value = 660; o.type = 'sine';
            g.gain.value = 0.04;
            o.connect(g); g.connect(ctx.destination);
            o.start(); o.stop(ctx.currentTime + 0.12);
            setTimeout(function () { ctx.close(); }, 300);
        } catch (e) {}
    }

    // ————— Video mesh —————
    function bootPeer(u, name) {
        if (CHAT.peers[u]) return;
        var tile = document.createElement('div');
        tile.className = 'vid-tile reportable';
        tile.id = 'tile-' + u;
        tile.innerHTML = '<div class="vt-ph">👤</div><div class="vt-label">' + esc(name) + '</div>' +
            '<div class="vt-muted">🔇 mic off</div>' +
            '<div class="vt-status">connecting…</div>' +
            '<button class="vt-report" title="Report this user">⚠ report</button>';
        document.getElementById('vc-grid').appendChild(tile);
        tile.querySelector('.vt-report').addEventListener('click', function () { reportUser(u, name); });
        CHAT.peers[u] = { tile: tile, video: null, pc: null, name: name, status: tile.querySelector('.vt-status'), label: tile.querySelector('.vt-label'), gotRemote: false };
        var p = CHAT.peers[u];
        if (CHAT.stream) {
            offer(u);
        } else {
            p.status.textContent = 'waiting for your camera…';
        }
    }

    function newPc(u) {
        var p = CHAT.peers[u];
        var pc = new RTCPeerConnection({ iceServers: STUNS });
        pc.onicecandidate = function (e) {
            if (e.candidate) api('signal', { room: CHAT.room, to: u, kind: 'ice', payload: JSON.stringify(e.candidate) });
        };
        pc.ontrack = function (e) { attachStream(u, e.streams && e.streams[0]); };
        pc.onconnectionstatechange = function () {
            var s = pc.connectionState;
            if (s === 'connected') {
                p.status.textContent = '';
                p.label.className = 'vt-label live';
            } else if (s === 'failed') {
                p.status.textContent = 'reconnecting…';
                try { pc.restartIce(); } catch (e) {}
            } else {
                p.status.textContent = s + '…';
            }
        };
        if (CHAT.stream) CHAT.stream.getTracks().forEach(function (t) { pc.addTrack(t, CHAT.stream); });
        else { try { pc.addTransceiver('video', { direction: 'recvonly' }); pc.addTransceiver('audio', { direction: 'recvonly' }); } catch (e) {} }
        p.pc = pc;
        return pc;
    }

    function offer(u) {
        var p = CHAT.peers[u];
        if (!p || !p.pc) newPc(u);
        p = CHAT.peers[u];
        p.pc.createOffer().then(function (o) {
            return p.pc.setLocalDescription(o);
        }).then(function () {
            api('signal', { room: CHAT.room, to: u, kind: 'offer', payload: JSON.stringify(p.pc.localDescription) });
        }).catch(function (e) { console.warn('offer fail', e); });
    }

    function attachStream(u, stream) {
        var p = CHAT.peers[u];
        if (!p) return;
        if (!stream) { portConnect(u); return; }
        p.gotRemote = true;
        if (!p.video) {
            var v = document.createElement('video');
            v.autoplay = true; v.playsInline = true;
            p.tile.appendChild(v);
            p.video = v;
        }
        p.video.srcObject = stream;
        p.status.textContent = '';
        p.label.className = 'vt-label live';
        stream.getAudioTracks().forEach(function (t) {
            t.addEventListener('mute', function () { p.tile.querySelector('.vt-muted').style.display = t.muted ? 'block' : 'none'; });
        });
        if (stream.getAudioTracks()[0] && !stream.getAudioTracks()[0].enabled) p.tile.querySelector('.vt-muted').style.display = 'block';
    }
    // 'skyhook': if ontrack fires without streams[0] (rare), treat as connected
    function portConnect(u) {
        var p = CHAT.peers[u];
        if (!p) return;
        p.status.textContent = '';
        p.label.className = 'vt-label live';
    }

    function onSignal(sig) {
        if (sig.from === CHAT.uid) return;
        if (!CHAT.peers[sig.from]) {
            var known = (window.CHAT_PEERS || []).filter(function (x) { return x.uid === sig.from; })[0];
            bootPeer(sig.from, known ? known.name : 'peer');
        }
        var p = CHAT.peers[sig.from];
        if (!p.pc) newPc(sig.from);
        var pc = p.pc;
        if (sig.kind === 'offer') {
            if (pc.signalingState === 'have-local-offer') {
                if (sig.from < CHAT.uid) {
                    try { pc.setLocalDescription({ type: 'rollback' }); } catch (e) {}
                } else { return; } // they'll answer our offer
            }
            pc.setRemoteDescription(JSON.parse(sig.payload)).then(function () {
                return pc.createAnswer();
            }).then(function (ans) {
                return pc.setLocalDescription(ans);
            }).then(function () {
                api('signal', { room: CHAT.room, to: sig.from, kind: 'answer', payload: JSON.stringify(pc.localDescription) });
            }).catch(function (e) { console.warn('answer fail', e); });
        } else if (sig.kind === 'answer') {
            if (pc.signalingState === 'have-local-offer') {
                pc.setRemoteDescription(JSON.parse(sig.payload)).catch(function (e) { console.warn('setRemote answer', e); });
            }
        } else if (sig.kind === 'ice') {
            pc.addIceCandidate(JSON.parse(sig.payload)).catch(function () {
                // We missed the offer — poke them to re-offer.
                api('signal', { room: CHAT.room, to: sig.from, kind: 'poke', payload: 'x' });
            });
        } else if (sig.kind === 'poke') {
            if (pc.signalingState === 'stable' && CHAT.stream) offer(sig.from);
        }
    }

    // ————— Media controls —————
    function startMedia() {
        if (!CHAT.secure) {
            note('⚠ Non-HTTPS: video is disabled (browser policy). Text chat still works.', true);
            window.CHAT_PEERS.forEach(function (p) {
                if (p.uid !== CHAT.uid && CHAT.peers[p.uid]) CHAT.peers[p.uid].status.textContent = 'text-only';
            });
            return;
        }
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            note('⚠ No camera API in this browser — text chat only.', true);
            return;
        }
        var wantCam = CHAT.camOn;
        getUserMediaSafe(wantCam).then(function (stream) {
            CHAT.stream = stream;
            selfVideoSetup();
            updateDock();
            Object.keys(CHAT.peers).forEach(function (u) {
                var p = CHAT.peers[u];
                if (p && p.pc) {
                    CHAT.stream.getTracks().forEach(function (t) {
                        if (p.pc.getSenders && !p.pc.getSenders().filter(function (s) { return s.track === t; }).length) {
                            try { p.pc.addTrack(t, CHAT.stream); } catch (e) {}
                        }
                    });
                    offer(u);
                }
            });
        }).catch(function () {
            note('⚠ Camera/mic denied — text chat only.', true);
        });
    }
    function getUserMediaSafe(wantCam) {
        var ok = true;
        var v = CHAT.stream && CHAT.stream.getVideoTracks()[0];
        var a = CHAT.stream && CHAT.stream.getAudioTracks()[0];
        if (!v && wantCam) ok = false;
        if (!ok) {
            return navigator.mediaDevices.getUserMedia({
                video: wantCam ? { width: { ideal: 640 }, facingMode: 'user' } : false,
                audio: true
            });
        }
        return Promise.resolve(CHAT.stream);
    }
    function selfVideoSetup() {
        if (!CHAT.selfTile) {
            var t = document.createElement('div');
            t.className = 'vid-tile self';
            t.id = 'tile-self';
            t.innerHTML = '<video autoplay playsinline muted></video><div class="vt-label">you</div>';
            document.body.appendChild(t);
            CHAT.selfTile = t;
            CHAT.selfVideo = t.querySelector('video');
        }
        CHAT.selfVideo.srcObject = CHAT.stream;
        rememberDock();
    }
    function updateDock() {
        document.getElementById('btn-mic').className = CHAT.micOn ? 'on' : 'off';
        document.getElementById('btn-cam').className = CHAT.camOn ? 'on' : 'off';
        if (CHAT.stream) {
            if (CHAT.stream.getAudioTracks()[0]) CHAT.stream.getAudioTracks()[0].enabled = CHAT.micOn;
            if (CHAT.stream.getVideoTracks()[0]) CHAT.stream.getVideoTracks()[0].enabled = CHAT.camOn;
        }
        if (navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
            navigator.mediaDevices.enumerateDevices().then(function (ds) {
                var cams = ds.filter(function (d) { return d.kind === 'videoinput'; });
                document.getElementById('btn-camswitch').style.display = CHAT.mobile && cams.length > 1 && CHAT.camOn ? 'inline-flex' : 'none';
            }).catch(function () {});
        }
    }
    function toggleMic() {
        if (!CHAT.stream || !CHAT.stream.getAudioTracks()[0]) { note('No mic available'); return; }
        CHAT.micOn = !CHAT.micOn;
        updateDock();
        flash(CHAT.micOn ? '🎤 mic on' : '🎤 mic muted');
    }
    function toggleCam() {
        if (!CHAT.stream) { note('Camera not available'); return; }
        if (!CHAT.stream.getVideoTracks()[0]) {
            navigator.mediaDevices.getUserMedia({ video: { width: { ideal: 640 }, facingMode: 'user' }, audio: false })
                .then(function (ns) {
                    ns.getVideoTracks().forEach(function (t) {
                        CHAT.stream.addTrack(t);
                        Object.keys(CHAT.peers).forEach(function (u) { var p = CHAT.peers[u]; if (p.pc) try { p.pc.addTrack(t, CHAT.stream); } catch (e) {} });
                    });
                    CHAT.camOn = true; updateDock();
                }).catch(function () { note('Camera denied'); });
            return;
        }
        CHAT.camOn = !CHAT.camOn;
        updateDock();
        flash(CHAT.camOn ? '📷 cam on' : '📷 cam off');
    }
    function switchCam() {
        if (!CHAT.stream) return;
        var old = CHAT.stream.getVideoTracks()[0];
        if (!old) return;
        if (CHAT.mobile) {
            navigator.mediaDevices.getUserMedia({ video: { width: { ideal: 640 }, facingMode: { ideal: 'environment' } }, audio: false })
                .then(function (ns) {
                    var t = ns.getVideoTracks()[0];
                    CHAT.stream.removeTrack(old);
                    old.stop();
                    CHAT.stream.addTrack(t);
                    Object.keys(CHAT.peers).forEach(function (u) {
                        var p = CHAT.peers[u];
                        if (!p || !p.pc) return;
                        var senders = p.pc.getSenders ? p.pc.getSenders().filter(function (s) { return s.track === old; }) : [];
                        if (senders.length) senders.forEach(function (s) { s.replaceTrack(t); });
                        else try { p.pc.addTrack(t, CHAT.stream); } catch (e) {}
                        if (CHAT.stream) offer(u);
                    });
                }).catch(function () { note('Camera switch failed'); });
        }
    }

    // ————— Polling loop —————
    function poll() {
        api('poll', { room: CHAT.room, since_message: CHAT.sinceMsg, since_signal: CHAT.sinceSig }, function (d) {
            window.CHAT_PEERS = d.peers;
            (d.peers || []).forEach(function (p) { if (p.uid !== CHAT.uid && !CHAT.peers[p.uid]) bootPeer(p.uid, p.name); });
            (d.signals || []).forEach(onSignal);
            if (d.since_signal) CHAT.sinceSig = d.since_signal;
            (d.messages || []).forEach(addMsg);
            if (d.since_message) CHAT.sinceMsg = d.since_message;
            if (!CHAT.started) {
                CHAT.started = true;
                if (CHAT.secure) startMedia();
                ensurePokeRace();
            }
        });
        CHAT.timer = setTimeout(poll, CHAT.mobile ? 2600 : 2000);
    }
    function ensurePokeRace() {
        // small backstop: re-offer to peers that have no remote stream after 4s
        setTimeout(function () {
            Object.keys(CHAT.peers).forEach(function (u) {
                var p = CHAT.peers[u];
                if (p && p.pc && !p.gotRemote && CHAT.stream && p.pc.iceConnectionState !== 'connected') {
                    try { p.pc.restartIce(); } catch (e) {}
                }
            });
        }, 4000);
    }

    function reportUser(u, name) {
        var reason = prompt('Report ' + name + '? Reason (abuse, spam, etc.):');
        if (!reason) return;
        api('report', { room: CHAT.room, target_uid: u, reason: reason }, function () {
            flash('Report sent — thanks.');
        });
    }
    function leaveRoom() {
        CHAT.leaving = true;
        var fd = new URLSearchParams({ room: CHAT.room, csrf: CHAT.csrf });
        var blob = new Blob([fd.toString()], { type: 'application/x-www-form-urlencoded' });
        if (navigator.sendBeacon) navigator.sendBeacon('chat.php?api=leave', blob);
        else api('leave', { room: CHAT.room }, function () {});
        if (CHAT.stream) CHAT.stream.getTracks().forEach(function (t) { t.stop(); });
        location.href = 'chat.php';
    }

    // ————— init —————
    function init() {
        document.body.classList.toggle('kb-mobile', CHAT.mobile);
        document.getElementById('chat-panel').classList.toggle('open', CHAT.chatOpen);
        window.addEventListener('beforeunload', function () {
            if (!CHAT.leaving) {
                var fd = new URLSearchParams({ room: CHAT.room, csrf: CHAT.csrf });
                var blob = new Blob([fd.toString()], { type: 'application/x-www-form-urlencoded' });
                try { navigator.sendBeacon('chat.php?api=leave', blob); } catch (e) {}
            }
        });
        poll();
    }
    init();
</script>
<?php endif; ?>

<?php
function chat_peers($pdo, string $room, string $meUid): array
{
    $st = $pdo->prepare('SELECT uid, name FROM chat_presence WHERE room = ? AND uid <> ? AND last_seen > (UTC_TIMESTAMP() - INTERVAL 25 SECOND) ORDER BY name LIMIT 50');
    $st->execute([$room, $meUid]);
    $out = [];
    foreach ($st as $row) {
        $out[] = ['uid' => $row['uid'], 'name' => $row['name']];
    }
    return $out;
}

page_footer();