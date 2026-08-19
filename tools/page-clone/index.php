<?php
require_once __DIR__ . '/../../functions.php';

start_session();

define('PC_MAX_CLONE', 1_048_576);

function pc_temp_dir(): string
{
    $d = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'kevbin_clones';
    if (!is_dir($d)) {
        @mkdir($d, 0700, true);
    }
    return $d;
}

function pc_absolutize(string $html, string $base): string
{
    $base = rtrim(htmlspecialchars($base, ENT_QUOTES, 'UTF-8'), '/') . '/';
    $tag = '<base href="' . $base . '">';
    if (preg_match('~<head[^>]*>~i', $html, $m, PREG_OFFSET_CAPTURE)) {
        $pos = $m[0][1] + strlen($m[0][0]);
        return substr($html, 0, $pos) . $tag . substr($html, $pos);
    }
    if (preg_match('~<html[^>]*>~i', $html, $m, PREG_OFFSET_CAPTURE)) {
        $pos = $m[0][1] + strlen($m[0][0]);
        return substr($html, 0, $pos) . $tag . substr($html, $pos);
    }
    return $tag . $html;
}

function pc_title(?string $html): string
{
    if ($html !== null && preg_match('~<title[^>]*>(.*?)</title>~is', $html, $m)) {
        return trim(strip_tags($m[1]));
    }
    return 'Untitled page';
}

function pc_site_name(string $url): string
{
    return (string)parse_url($url, PHP_URL_HOST);
}

// Sandboxed preview handler for a stored clone (never sets cookies, scripts/form blocked by iframe sandbox).
if (isset($_GET['view'])) {
    start_session();
    if (!csrf_verify()) {
        http_response_code(403);
        exit;
    }
    if (!rate_limit_check('pageclone_view', 20, 60)) {
        http_response_code(429);
        exit;
    }
    $id = (string)$_GET['view'];
    if (!preg_match('/^[a-f0-9]{16}$/', $id)) {
        http_response_code(400);
        exit;
    }
    $file = pc_temp_dir() . DIRECTORY_SEPARATOR . 'clone_' . $id . '.html';
    if (!is_file($file)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\' https: http:; img-src https: http: data:; font-src https: http:;');
    header('Cache-Control: no-store');
    readfile($file);
    exit;
}

// Download handler for a stored clone.
if (isset($_GET['dl'])) {
    start_session();
    if (!csrf_verify()) {
        http_response_code(403);
        exit;
    }
    if (!rate_limit_check('pageclone_dl', 10, 60)) {
        http_response_code(429);
        exit;
    }
    $id = (string)$_GET['dl'];
    if (!preg_match('/^[a-f0-9]{16}$/', $id)) {
        http_response_code(400);
        exit;
    }
    $file = pc_temp_dir() . DIRECTORY_SEPARATOR . 'clone_' . $id . '.html';
    if (!is_file($file)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="clone.html"');
    header('X-Content-Type-Options: nosniff');
    readfile($file);
    @unlink($file);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!csrf_verify()) {
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }
    if (!rate_limit_check('pageclone', 5, 60)) {
        echo json_encode(['error' => 'Rate limit reached. Wait a moment.']);
        exit;
    }

    $url = trim((string)($_POST['url'] ?? ''));
    if ($url === '') {
        echo json_encode(['error' => 'Enter a URL to clone.']);
        exit;
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'http://' . $url;
    }
    $url = filter_var($url, FILTER_VALIDATE_URL);
    if (!$url || !parse_url($url, PHP_URL_HOST)) {
        echo json_encode(['error' => 'Invalid URL.']);
        exit;
    }
    log_activity('tool_page_clone', parse_url($url, PHP_URL_HOST));

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 14,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_RANGE => '0-' . (PC_MAX_CLONE - 1),
    ]);
    $html = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $final = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
    $ctype = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($code === 0) {
        echo json_encode(['error' => 'Could not reach the site.']);
        exit;
    }
    if ($code >= 400) {
        echo json_encode(['error' => 'The site returned HTTP ' . $code . '.']);
        exit;
    }
    if ($html === '') {
        echo json_encode(['error' => 'No content returned — the page may be JS-rendered or empty.']);
        exit;
    }
    $bin = preg_split('~;~', $ctype)[0] ?? '';
    if ($bin !== '' && !preg_match('~text/html~i', $bin) && !preg_match('~application/xhtml~i', $bin) && !preg_match('~/html|\<head\b|\<body\b|\<html\b~i', $html)) {
        echo json_encode(['error' => 'The URL did not return an HTML page (type: ' . ($bin !== '' ? $bin : 'unknown') . ').']);
        exit;
    }

    $cloned = pc_absolutize($html, $final);
    $title = pc_title($html);
    $has_pw = (bool)preg_match('~<input[^>]+type=["\']?password~i', $cloned);
    $has_form = (bool)preg_match('~<form[ >]~i', $cloned);

    $id = bin2hex(random_bytes(8));
    $file = pc_temp_dir() . DIRECTORY_SEPARATOR . 'clone_' . $id . '.html';
    $ok = @file_put_contents($file, $cloned) !== false;
    if (!$ok) {
        echo json_encode(['error' => 'Could not save the clone.']);
        exit;
    }

    echo json_encode([
        'title' => $title,
        'site' => pc_site_name($url),
        'final_url' => $final,
        'code' => $code,
        'size' => strlen($cloned),
        'has_password' => $has_pw,
        'has_form' => $has_form,
        'download_id' => $id,
        'view_id' => $id,
    ]);
    exit;
}

page_header('Page Cloner');
?>
<style>
.pc-prev { width: 100%; height: 480px; border: 1px solid var(--line); border-radius: 12px; background: #fff; }
.pc-note { font-size: .86rem; }
.pc-form-target { background: rgba(255,255,255,.03); border: 1px dashed rgba(255,255,255,.15); border-radius: 10px; padding: .6rem .9rem; }
</style>
<div class="container" style="max-width: 960px;">
    <h1 class="h4 mb-2 reveal in-view">&#128128; Page Cloner <span class="text-secondary" style="font-size:.85rem">(phishing awareness edition)</span></h1>
    <p class="text-secondary mb-2 reveal in-view">See how trivially a legitimate web page can be impersonated. This tool downloads any public page and
        produces a byte-identical standalone copy &mdash; <code>&lt;base href&gt;</code> is injected so every asset resolves to the original
        site. The clone keeps the <strong>original form actions untouched</strong>: any form it contains still submits to the real site,
        and this tool <strong>never captures, stores or forwards a single keystroke</strong>.</p>

    <div class="alert alert-warning pc-note reveal in-view mb-3">
        <strong>&#9888;&#65039; Educational use only.</strong> Clone only pages you own or are authorized to test.
        Do not host cloned login pages, redirect real traffic to them, or pair this with any credential collection.
        Running a clone that intercepts passwords is a criminal offence in most jurisdictions.
    </div>

    <div class="card reveal in-view"><div class="card-body">
        <form id="pcForm" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="row g-2">
                <div class="col-md-8">
                    <input class="form-control" name="url" placeholder="https://example.com/login" required>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100" type="submit">Clone page</button>
                </div>
            </div>
        </form>
    </div></div>

    <div id="pcErr" class="alert alert-danger mt-3 d-none"></div>
    <div class="card mt-3 d-none" id="pcCard"><div class="card-body" id="pcBody"></div></div>

    <div class="card mt-4 reveal in-view"><div class="card-body">
        <h2 class="h6 mb-3">&#129300; How to spot a cloned login page</h2>
        <ul class="small text-secondary mb-2 ps-3">
            <li><strong>Check the URL bar, twice.</strong> Lookalike domains (<code>go0gle.com</code>, <code>amaz0n</code>, punycode <code>xn&#45;&#45;</code>) are the #1 giveaway a clone is hosting a fake brand page.</li>
            <li><strong>Hover every link before clicking.</strong> The visible text can say anything; the <code>href</code> can point anywhere &mdash; especially with link-spoof tricks.</li>
            <li><strong>Don't trust the padlock alone.</strong> A cloned page copied to a hosting site gets its own valid HTTPS certificate &mdash; encryption and authenticity are different things.</li>
            <li><strong>Forms that "keep you logged in" or skip MFA</strong> are classic credential-harvester goals. Legit services rarely ask for a password sent to an address you didn't type.</li>
            <li><strong>Search the exact phrase</strong> from a suspicious email: clones are often re-used verbatim across campaigns.</li>
        </ul>
        <p class="small text-primary mb-0"><a href="../link-spoof/">Next: try the link spoof generator</a> and see both halves of the attack together.</p>
    </div></div>

    <div class="card mt-3 reveal in-view"><div class="card-body">
        <h2 class="h6 mb-3">&#128737;&#65039; What this tool deliberately does not do</h2>
        <table class="table table-sm tbl mb-0">
            <tbody>
                <tr><td style="width:200px" class="text-secondary">Form interception</td><td>Form <code>action</code> attributes are left exactly as the original page ships them &mdash; nothing redirects submissions anywhere.</td></tr>
                <tr><td class="text-secondary">Password capture</td><td>There is no logging, storage or exfiltration of anything a visitor types.</td></tr>
                <tr><td class="text-secondary">Public hosting</td><td>Clones exist only in a random temporary file on this server, viewable by you, deleted after download.</td></tr>
                <tr><td class="text-secondary">Script execution</td><td>The preview runs in a fully sandboxed iframe with scripts and forms blocked.</td></tr>
            </tbody>
        </table>
    </div></div>
</div>
<script>
(function(){
    var form=document.getElementById('pcForm');
    var err=document.getElementById('pcErr');
    var card=document.getElementById('pcCard');
    var body=document.getElementById('pcBody');
    form.addEventListener('submit',function(e){
        e.preventDefault();
        err.classList.add('d-none');
        card.classList.add('d-none');
        var btn=form.querySelector('button'); btn.disabled=true;
        fetch('index.php',{method:'POST',body:new FormData(form)})
            .then(function(r){return r.json();})
            .then(function(d){
                btn.disabled=false;
                if(d.error){ err.textContent=d.error; err.classList.remove('d-none'); return; }
                var h='<div class="d-flex justify-content-between align-items-start mb-2">';
                h+='<div><strong>'+esc(d.title)+'</strong><br><span class="small text-secondary">cloned from <code>'+esc(d.final_url)+'</code> &mdash; HTTP '+d.code+' &middot; '+fmt(d.size)+'</span></div>';
                h+='<a class="btn btn-outline-light btn-sm" href="index.php?dl='+d.download_id+'&csrf='+encodeURIComponent(document.querySelector('input[name=csrf]').value)+'">&#11015; Download clone</a>';
                h+='</div>';
                if(d.has_password){
                    h+='<div class="alert alert-danger py-2 small mb-2"><strong>Heads up:</strong> this page contains a password field. The clone keeps the original form action, so we are not intercepting it &mdash; but this is exactly the type of page real phishing campaigns clone. Do not host it.</div>';
                } else if(d.has_form){
                    h+='<div class="alert alert-secondary py-2 small mb-2">This page contains a form. Its action is unchanged &mdash; submissions go to the original site.</div>';
                }
                var csrf=encodeURIComponent(document.querySelector('input[name=csrf]').value);
                h+='<div class="pc-prev-box"><iframe class="pc-prev" sandbox="" loading="lazy" src="index.php?view='+d.view_id+'&csrf='+csrf+'"></iframe></div>';
                h+='<div class="small text-secondary mt-2">Sandboxed preview &mdash; scripts and forms are blocked, so the clone&apos;s JS cannot run here.</div>';
                body.innerHTML=h;
                card.classList.remove('d-none');
            })
            .catch(function(){ btn.disabled=false; err.textContent='Network error. Try again.'; err.classList.remove('d-none'); });
    });
    function esc(s){ var e=document.createElement('div'); e.appendChild(document.createTextNode(s==null?'':String(s))); return e.innerHTML; }
    function fmt(n){ return n>=1048576?(n/1048576).toFixed(1)+' MB':n>=1024?(n/1024).toFixed(1)+' KB':n+' B'; }
})();
</script>
<?php page_footer(); ?>