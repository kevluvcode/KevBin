<?php
require_once __DIR__ . '/../../functions.php';

start_session();

define('SV_MAX', 2_000_000);

function sv_temp_dir(): string
{
    $d = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'kevbin_sites';
    if (!is_dir($d)) {
        @mkdir($d, 0700, true);
    }
    return $d;
}

// Reject private/reserved destinations to reduce SSRF-to-internal abuse.
function sv_host_ok(string $host): bool
{
    $ip = @gethostbyname($host);
    if ($ip === $host) {
        $ip = '';
    }
    $bad = ['0.', '10.', '127.', '169.254.', '172.16.', '172.17.', '172.18.', '172.19.', '172.20.', '172.21.', '172.22.', '172.23.', '172.24.', '172.25.', '172.26.', '172.27.', '172.28.', '172.29.', '172.30.', '172.31.', '192.168.', '100.64.', '224.', '225.', '226.', '227.', '228.', '229.', '230.', '231.', '232.', '233.', '234.', '235.', '236.', '237.', '238.', '239.', '240.', '241.', '242.', '243.', '244.', '245.', '246.', '247.', '248.', '249.', '250.', '251.', '252.', '253.', '254.', '255.'];
    foreach ($bad as $p) {
        if (str_starts_with($ip, $p)) {
            return false;
        }
    }
    return $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

function sv_abs(string $u, string $base): string
{
    if (preg_match('#^https?://#i', $u)) {
        return $u;
    }
    if (str_starts_with($u, '//')) {
        return (parse_url($base, PHP_URL_SCHEME)) . ':' . $u;
    }
    $parts = parse_url($base);
    $scheme = $parts['scheme'] ?? 'https';
    $host = $parts['host'] ?? '';
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    $path = isset($parts['path']) ? $parts['path'] : '/';
    if (str_starts_with($u, '/')) {
        return $scheme . '://' . $host . $port . $u;
    }
    $dir = substr($path, 0, strrpos($path, '/') + 1);
    if ($dir === '') {
        $dir = '/';
    }
    return $scheme . '://' . $host . $port . $dir . $u;
}

// Rewrite navigation attributes so every link stays inside the viewer.
function sv_rewrite(string $html, string $base): string
{
    $out = preg_replace_callback(
        '~\b(href|src|action|poster)=(["\'])(.*?)\2~is',
        function ($m) use ($base) {
            $attr = strtolower($m[1]);
            $u = $m[3];
            if ($u === '' || preg_match('#^(mailto:|tel:|javascript:|data:|blob:|about:)#i', $u)) {
                return $m[0];
            }
            if ($attr === 'src' && preg_match('#\.(css|js)(\?|$)#i', $u)) {
                return $m[0];
            }
            $abs = sv_abs($u, $base);
            return $attr . '=' . $m[2] . urlencode($abs) . $m[2] . ' data-v="1"';
        },
        $html
    );
    return $out;
}

function sv_fetch(string $url, array &$err): ?string
{
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
        CURLOPT_RANGE => '0-' . (SV_MAX - 1),
    ]);
    $html = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $final = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
    curl_close($ch);
    if ($code === 0) {
        $err = ['Could not reach the site.'];
    } elseif ($code >= 400) {
        $err = ['The site returned HTTP ' . $code . '.'];
    } elseif ($html === '') {
        $err = ['No content returned — the page may be JS-rendered or empty.'];
    }
    return [$html, $final];
}

// Serve a stored page view (script-blocked, sandboxed iframe).
if (isset($_GET['view'])) {
    start_session();
    if (!rate_limit_check('siteview', 60, 60)) {
        http_response_code(429);
        exit;
    }
    $id = (string)$_GET['view'];
    if (!preg_match('/^[a-f0-9]{20}$/', $id)) {
        http_response_code(400);
        exit;
    }
    $file = sv_temp_dir() . DIRECTORY_SEPARATOR . 'view_' . $id . '.html';
    if (!is_file($file)) {
        http_response_code(404);
        exit('Page not found.');
    }
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; img-src http: https: data:; style-src 'unsafe-inline' http: https:; font-src http: https:; media-src http: https:; script-src 'none'; frame-ancestors 'none'");
    header('Cache-Control: no-store');
    readfile($file);
    exit;
}

// Follow a link through the viewer: fetch, rewrite, then 302 to its stored view.
if (isset($_GET['go'])) {
    start_session();
    if (!rate_limit_check('sitego', 60, 60)) {
        http_response_code(429);
        exit;
    }
    $target = (string)$_GET['go'];
    if (preg_match('#^https?://#i', $target) === false || strlen($target) > 3000) {
        http_response_code(400);
        exit;
    }
    $host = parse_url($target, PHP_URL_HOST);
    if (!$host || !sv_host_ok($host)) {
        http_response_code(400);
        exit('Destination host not allowed.');
    }
    [$html, $final] = sv_fetch($target, $err);
    if ($err) {
        http_response_code(502);
        exit($err[0]);
    }
    $rewritten = sv_rewrite($html, $final);
    $id = bin2hex(random_bytes(10));
    if (@file_put_contents(sv_temp_dir() . DIRECTORY_SEPARATOR . 'view_' . $id . '.html', $rewritten) === false) {
        http_response_code(500);
        exit;
    }
    header('Location: index.php?view=' . $id, true, 302);
    exit;
}

// Initial load from the form.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!csrf_verify()) {
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }
    if (!rate_limit_check('siteload', 12, 60)) {
        echo json_encode(['error' => 'Rate limit reached. Wait a moment.']);
        exit;
    }
    $url = trim((string)($_POST['url'] ?? ''));
    if ($url === '') {
        echo json_encode(['error' => 'Enter a URL.']);
        exit;
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'http://' . $url;
    }
    $url = filter_var($url, FILTER_VALIDATE_URL);
    $host = $url ? parse_url($url, PHP_URL_HOST) : null;
    if (!$url || !$host) {
        echo json_encode(['error' => 'Invalid URL.']);
        exit;
    }
    if (!sv_host_ok($host)) {
        echo json_encode(['error' => 'That host resolves to a private/reserved address and cannot be viewed here.']);
        exit;
    }
    log_activity('tool_site_viewer', $host);

    $err = [];
    [$html, $final] = sv_fetch($url, $err);
    if ($err) {
        echo json_encode(['error' => $err[0]]);
        exit;
    }
    $rewritten = sv_rewrite($html, $final);
    $id = bin2hex(random_bytes(10));
    if (@file_put_contents(sv_temp_dir() . DIRECTORY_SEPARATOR . 'view_' . $id . '.html', $rewritten) === false) {
        echo json_encode(['error' => 'Could not store the page.']);
        exit;
    }
    $title = '';
    if (preg_match('~<title[^>]*>(.*?)</title>~is', $html, $m)) {
        $title = trim(strip_tags($m[1]));
    }
    echo json_encode(['token' => $id, 'title' => $title, 'url' => $final, 'size' => strlen($rewritten)]);
    exit;
}

page_header('Site Viewer');
?>
<style>
.sv-frame { width:100%; height:600px; border:1px solid var(--line); border-radius:12px; background:#fff; }
</style>
<div class="container" style="max-width: 1100px;">
    <h1 class="h4 mb-2 reveal in-view">🪟 Site Viewer <span class="text-secondary" style="font-size:.85rem">(sandboxed browsing proxy)</span></h1>
    <p class="text-secondary mb-3 reveal in-view">View any public web page <em>inside</em> this tool, link after link, without opening your own browser to the destination. The server fetches each page, keeps links pointing back through the viewer, and serves it into a sandboxed iframe where scripts, cookies and forms are blocked. Useful for previewing sites safely and inspecting what a page loads.</p>

    <div class="card reveal in-view"><div class="card-body">
        <form id="svForm" class="row g-2 align-items-end">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="col-md-8">
                <label class="form-label small text-secondary mb-1">URL to view</label>
                <input class="form-control" name="url" placeholder="https://example.com" required>
            </div>
            <div class="col-md-4">
                <button class="btn btn-primary w-100" type="submit">Load in viewer</button>
            </div>
        </form>
        <div id="svErr" class="alert alert-danger mt-2 mb-0 d-none"></div>
    </div></div>

    <div id="svCard" class="card mt-3 d-none reveal in-view"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <strong id="svTitle"></strong>
            <span class="small text-secondary" id="svMeta"></span>
        </div>
        <iframe id="svFrame" class="sv-frame" sandbox="" loading="lazy"></iframe>
        <p class="small text-secondary mt-2 mb-0">Sandboxed — JavaScript, cookies and forms are disabled inside the viewer. Sites that require JS (most modern web apps) may appear blank; the viewer is best for static pages, docs and previews.</p>
    </div></div>

    <div class="card mt-3 reveal in-view"><div class="card-body">
        <h2 class="h6 mb-2">⚠️ Notes</h2>
        <ul class="small text-secondary ps-3 mb-0">
            <li>Only http/https destinations are allowed; private and reserved network ranges are blocked.</li>
            <li>Fetches are rate-limited. Heavy or aggressive sites may time out — this is not a general-purpose anonymous proxy.</li>
            <li>Use it to view sites you own or are authorized to look at.</li>
        </ul>
    </div></div>
</div>
<script>
(function(){
    var form=document.getElementById('svForm'), err=document.getElementById('svErr'), card=document.getElementById('svCard');
    form.addEventListener('submit',function(e){
        e.preventDefault();
        err.classList.add('d-none'); card.classList.add('d-none');
        var btn=form.querySelector('button'); btn.disabled=true;
        fetch('index.php',{method:'POST',body:new FormData(form)})
            .then(function(r){return r.json();})
            .then(function(d){
                btn.disabled=false;
                if(d.error){ err.textContent=d.error; err.classList.remove('d-none'); return; }
                document.getElementById('svTitle').textContent=d.title || d.url;
                document.getElementById('svMeta').textContent=d.url+' — '+fmt(d.size);
                document.getElementById('svFrame').src='index.php?view='+d.token;
                card.classList.remove('d-none');
            })
            .catch(function(){ btn.disabled=false; err.textContent='Network error. Try again.'; err.classList.remove('d-none'); });
    });
    function fmt(n){ return n>=1048576?(n/1048576).toFixed(1)+' MB':n>=1024?(n/1024).toFixed(1)+' KB':n+' B'; }
})();
</script>
<?php page_footer(); ?>