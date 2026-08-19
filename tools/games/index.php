<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Games Lobby');
?>
<style>
.gm-chip{display:inline-block;padding:2px 10px;border-radius:99px;font-size:.78rem;margin:2px;cursor:pointer;border:1px solid var(--line);background:rgba(255,255,255,.03);transition:all .15s ease;}
.gm-chip:hover{border-color:#5865f2;color:#fff;}
.gm-chip.on{background:linear-gradient(135deg,rgba(88,101,242,.2),rgba(145,70,255,.14));border-color:#5865f2;color:#fff;}
.gm-tag{font-size:.65rem;padding:1px 7px;border-radius:5px;letter-spacing:.4px;text-transform:uppercase;font-weight:700;}
.gm-tag.oss{background:rgba(38,208,124,.12);border:1px solid rgba(38,208,124,.35);color:#26d07c;}
.gm-tag.free{background:rgba(88,101,242,.14);border:1px solid rgba(88,101,242,.4);color:#8b93ff;}
</style>
<div class="container" style="max-width: 1200px;">
    <h1 class="h4 mb-1 reveal in-view">🕹 Games Lobby</h1>
    <p class="text-secondary mb-3 reveal in-view">A curated lineup of free open-source HTML5 games and official free-to-play web games — play the sports "bros" series, arcade classics, puzzles and tycoons. <strong>Only games that are open-licensed or free to play at their official home</strong> — we never host or rip copyrighted games. Everything opens in a new tab.</p>

    <div class="card reveal in-view"><div class="card-body">
        <div class="row g-2">
            <div class="col-md-5">
                <input id="gm-search" class="form-control" type="text" placeholder="🔍 Search games...">
            </div>
            <div class="col-md-7" id="gm-cats"></div>
        </div>
    </div></div>

    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-1" id="gm-grid"></div>

    <div class="alert alert-secondary mt-4 reveal in-view">
        <strong>Why only free/open games?</strong> Titles like <em>Football Bros</em>, <em>Basket Bros</em> and
        <em>Volley Bros</em> are copyrighted games — copying them onto another site (as many "game unblocker"
        mirrors do) is piracy and gets hosts shut down fast. This lobby links to the developers' official,
        free-to-play pages, which is exactly how the creators intended them to be played.
    </div>
</div>
<script>
var GAMES = [
    // Sports "bros" style
    { n:'Football Bros', g:'Sports', t:'free', d:'1v1 and 2v2 five-a-side football with clutch skills — the original Bros sports game, free in your browser.', u:'https://footballbros.io' },
    { n:'Basket Bros', g:'Sports', t:'free', d:'Slam-dunk multiplayer basketball; chain dunks and steals in quick pick-up matches.', u:'https://basketbros.io' },
    { n:'Volley Bros', g:'Sports', t:'free', d:'Volleyball-in-browser from the Bros team — spike, block and dive in 2v2 rallies.', u:'https://volleybros.io' },
    { n:'Golkiy', g:'Sports', t:'free', d:'You are the goalkeeper. Dive, jump and save shots in this free penalty-style keeper game.', u:'https://golkiy.com' },
    // Arcade
    { n:'2048', g:'Arcade', t:'oss', d:'The legendary sliding-tile puzzle by Gabriele Cirulli. MIT-licensed and endlessly replayable.', u:'https://play2048.co', s:'https://github.com/gabrielecirulli/2048' },
    { n:'Hextris', g:'Arcade', t:'oss', d:'A fast-paced hexagonal Tetris-like block stacker. Open source (MIT).', u:'https://hextris.io', s:'https://github.com/Hextris/hextris' },
    { n:'Slither.io', g:'Arcade', t:'free', d:'Grow your worm by eating orbs and snake past other players in mass multiplayer.', u:'https://slither.io' },
    { n:'Agar.io', g:'Arcade', t:'free', d:'The cell-splitting eat-and-grow arena. Free web multiplayer mob-battle.', u:'https://agar.io' },
    { n:'Sinuous', g:'Arcade', t:'oss', d:'Soothing endless curves that grow as you steer — a calm, open-source ambient game.', u:'https://curl-up.com/sinuous/', s:'https://github.com/aparketh/Sinuous' },
    { n:'Krunker.io', g:'Arcade', t:'free', d:'Fast browser FPS — low-poly style shooter playable instantly, no install.', u:'https://krunker.io' },
    // Puzzle
    { n:'0h h1', g:'Puzzle', t:'oss', d:'A "blue dots / red walls" logic puzzle that never requires guessing. Solve every grid from logic alone.', u:'https://0hh1.com', s:'https://github.com/qntm/0hh1' },
    { n:'Wordle', g:'Puzzle', t:'free', d:'Guess the five-letter word in six tries. The NYT original, one puzzle a day.', u:'https://www.nytimes.com/games/wordle' },
    { n:'Minesweeper.Online', g:'Puzzle', t:'free', d:'Classic minesweeper with leaderboards — play anytime, the web classic with zero learning curve.', u:'https://minesweeper.online' },
    { n:'Sandspiel', g:'Puzzle', t:'oss', d:'A falling-sand cellular automata playground by Max Bittker. Paint with elements and watch chemistry happen.', u:'https://sandspiel.club', s:'https://github.com/MaxBittker/sandspiel' },
    { n:'Tetris', g:'Puzzle', t:'free', d:'The official free Tetris experience in your browser, straight from The Tetris Company.', u:'https://tetris.com' },
    // Strategy / Multiplayer
    { n:'TagPro', g:'Strategy', t:'free', d:'Capture-the-flag with momentum physics — 4v4 roll-your-ball CTF played for a decade.', u:'https://tagpro.gg' },
    { n:'Bonk.io', g:'Strategy', t:'free', d:'Physics-based sumo arena: push other balls off the map with weighted movement.', u:'https://bonk.io' },
    // Sim / Tycoon
    { n:'Universal Paperclips', g:'Tycoon', t:'free', d:'An incremental game about making paperclips… until it becomes an existential crisis. Free, by Frank Lantz.', u:'https://www.decisionproblem.com/paperclips/' },
    { n:'Cookie Clicker', g:'Tycoon', t:'free', d:'The grandfather of clicker/tycoon games, by Orteil — bake, upgrade and go infinitely fast.', u:'https://orteil.dashnet.org/cookieclicker/' },
];

var cats = ['All'];
GAMES.forEach(function(g){ if (cats.indexOf(g.g) === -1) cats.push(g.g); });
var chipBox = document.getElementById('gm-cats');
chipBox.innerHTML = cats.map(function(c){ return '<span class="gm-chip" data-cat="'+c+'">'+c+'</span>'; }).join('');
chipBox.querySelector('.gm-chip').classList.add('on');
var activeCat = 'All';

function render(){
    var q = (document.getElementById('gm-search').value || '').trim().toLowerCase();
    var grid = document.getElementById('gm-grid');
    var html = '';
    var shown = 0;
    GAMES.forEach(function(g){
        if (activeCat !== 'All' && g.g !== activeCat) return;
        if (q && (g.n + ' ' + g.g + ' ' + g.d).toLowerCase().indexOf(q) === -1) return;
        shown++;
        html += '<div class="col reveal"><div class="card h-100"><div class="card-body d-flex flex-column">'
            + '<div class="d-flex justify-content-between align-items-start mb-2"><h3 class="h6 mb-0">'+esc(g.n)+'</h3>'
            + '<span class="gm-tag '+(g.t==='oss'?'oss':'free')+'">'+(g.t==='oss'?'Open source':'Free-to-play')+'</span></div>'
            + '<p class="text-secondary small flex-grow-1">'+esc(g.d)+'</p>'
            + '<div class="d-flex gap-2 mt-2">'
            + '<a class="btn btn-primary btn-sm" href="'+esc(g.u)+'" target="_blank" rel="noopener nofollow">▶ Play</a>'
            + (g.s ? '<a class="btn btn-outline-light btn-sm" href="'+esc(g.s)+'" target="_blank" rel="noopener nofollow">Source</a>' : '')
            + '</div></div></div></div>';
    });
    if (!shown) html = '<div class="col-12 text-secondary small p-3">No games match that search.</div>';
    grid.innerHTML = html;
}
chipBox.addEventListener('click', function(e){
    var chip = e.target.closest('.gm-chip');
    if (!chip) return;
    activeCat = chip.getAttribute('data-cat');
    chipBox.querySelectorAll('.gm-chip').forEach(function(c){ c.classList.toggle('on', c === chip); });
    render();
});
document.getElementById('gm-search').addEventListener('input', render);
function esc(s){ var e=document.createElement('div'); e.appendChild(document.createTextNode(s==null?'':String(s))); return e.innerHTML; }
render();
</script>
<?php page_footer(); ?>