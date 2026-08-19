<?php
require_once __DIR__ . '/../../functions.php';

start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!csrf_verify()) {
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }
    if (!rate_limit_check('techstack', 6, 60)) {
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
    if (!$url || !parse_url($url, PHP_URL_HOST)) {
        echo json_encode(['error' => 'Invalid URL.']);
        exit;
    }
    log_activity('tool_tech_stack', parse_url($url, PHP_URL_HOST));

    $headers = [];
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
        CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$headers) {
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return strlen($header);
        },
        CURLOPT_RANGE => '0-524288',
    ]);
    $html = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $final = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $host = parse_url($final ?: $url, PHP_URL_HOST);
    curl_close($ch);
    $html = mb_substr($html, 0, 524288);
    $u = strtolower($html);

    $found = [];

    // ---- server / header fingerprints ----
    foreach (['server', 'x-powered-by', 'x-generator', 'x-aspnet-version', 'x-wix-request-id', 'x-shopify-stage', 'x-cache', 'via'] as $h) {
        if (isset($headers[$h]) && $headers[$h] !== '') {
            $found[] = ['name' => ucfirst($h) . ' header', 'group' => 'HTTP', 'detail' => $headers[$h], 'icon' => '⚙️'];
        }
    }
    if (isset($headers['cf-ray'])) $found[] = ['name' => 'Cloudflare', 'group' => 'CDN / Edge', 'detail' => 'cf-ray header', 'icon' => '☁️'];
    if (isset($headers['server-timing-headers'])) {}

    // ---- meta generator ----
    foreach (['generator', 'msapplication-config'] as $meta) {
        if (preg_match('/<meta[^>]+name=["\']?' . $meta . '["\']?[^>]*content=["\']([^"\']+)/i', $html, $m)) {
            $found[] = ['name' => $meta, 'group' => 'Meta', 'detail' => trim($m[1]), 'icon' => '🏷'];
        }
    }

    // ---- framework / CMS detection ----
    $rules = [
        ['WordPress', 'CMS', 'wp-content/|wp-includes/|wp-json/|wp-vcd', '🎵'],
        ['Wix', 'CMS', 'wixstatic|wix.com/fas|X-Wix-Request', '🎨'],
        ['Shopify', 'E-commerce', 'cdn.shopify|shopify([ -]checkout| cdn)|/products/.*\.js|X-Shopify', '🛍'],
        ['Squarespace', 'CMS', 'static1.squarespace|squarespace.com/cdn', '🟦'],
        ['Blogger', 'CMS', 'blogger.com|blogspot.com|blogspot\.', '📝'],
        ['Ghost', 'CMS', 'ghost(\.org|-fonts)|ghost-url', '👻'],
        ['Drupal', 'CMS', 'drupal-settings-json|xdrupal|drupal\.org', '💧'],
        ['Joomla', 'CMS', 'joomla|/media/system/js|jcore.js', '🧩'],
        ['Magento', 'E-commerce', 'mage/|Magento|/static/version', '🛒'],
        ['Weebly', 'CMS', 'weebly.com|wix\.com', '🌱'],
        ['PrestaShop', 'E-commerce', 'prestashop|/themes/.*\/modules/', '🛒'],
        ['Tumblr', 'CMS', 'tumblr.com|tumblr_', '🔥'],
        ['Next.js', 'Framework', '__NEXT_DATA__|/_next/static|next/dist', '▲'],
        ['Nuxt.js', 'Framework', '__NUXT__|/nuxt|data-v-.*vue', '💚'],
        ['React', 'Framework', '__reactFiber|__reactContainer|react-dom|_reactRootContainer', '⚛️'],
        ['Vue', 'Framework', '__vue__|vue.runtime|data-v-', '💚'],
        ['Angular', 'Framework', 'ng-version=|ng-app|angular\.js', '🅰️'],
        ['Svelte', 'Framework', '__svelte|svelte-', '🔥'],
        ['Astro', 'Framework', 'astro-|astro.build', '🚀'],
        ['Gatsby', 'Framework', '__gatsby|gatsby-image|www.gatsbyjs.com', '💜'],
        ['jQuery', 'JS library', 'jquery[.-]', '🟡'],
        ['Bootstrap', 'CSS framework', 'bootstrap(\.min)?\.(css|js)', '🅱️'],
        ['Tailwind CSS', 'CSS framework', 'tailwind(\.min)?\.css|tailwindcss', '💨'],
        ['htmx', 'JS library', 'htmx(\.min)?\.js|hx-get|hx-post', '🧲'],
        ['Alpine.js', 'JS library', 'alpinejs|x-data|csp\.alpinejs', '⛰'],
        ['Turbo / Hotwire', 'JS library', 'turbo-links|@hotwired|stimulus', '💠'],
        ['GSAP', 'JS library', 'gsap(\.min)?\.js', '🟩'],
        ['Three.js', 'JS library', 'three(\.min)?\.js|THREE\.', '🎲'],
        ['D3.js', 'JS library', 'd3(\.min)?\.js|d3\.v', '📊'],
    ];
    foreach ($rules as $r) {
        if (preg_match('~' . $r[2] . '~i', $u)) {
            $detail = '';
            if ($r[1] === 'CMS' || $r[1] === 'Framework' || $r[1] === 'JS library' || $r[1] === 'CSS framework') {
                if (preg_match('~' . $r[2] . '~i', $html, $m)) {
                    $detail = mb_substr($m[0], 0, 40);
                }
            }
            $found[] = ['name' => $r[0], 'group' => $r[1], 'detail' => $detail, 'icon' => $r[3]];
        }
    }

    // ---- analytics / trackers ----
    $an = [
        ['Google Analytics / gtag', 'gtag\\(|googletagmanager|google-analytics\\.com|_gaq', '📈'],
        ['Google Tag Manager', 'googletagmanager\\.com/gtm\\.js|GTM-', '📦'],
        ['Meta Pixel', 'connect\\.facebook\\.net|fbq\\(|facebook\\.com/tr', '🎯'],
        ['Cloudflare Web Analytics', 'static\\.cloudflareinsights\\.com|cloudflare\\.com/beacon', '☁️'],
        ['Hotjar', 'hotjar\\.com|hotjar\\.js', '🔥'],
        ['Matomo', 'matomo|piwik(\\\\.)?js|_paq', '📊'],
        ['Umami', 'umami\\.js|umami\\.is', '🍺'],
        ['Plausible', 'plausible\\.io', '📊'],
        ['Baidu Analytics', 'hm\\.baidu\\.com|baidu\\.com/hm', '🐻'],
        ['Fathom', 'cdn\\.usefathom|usefathom\\.com', '🌊'],
        ['Microsoft Clarity', 'clarity\\.ms|cmp\\.clarity\\.ms', '🔍'],
        ['AdSense', 'googlesyndication|adsbygoogle', '💰'],
        ['Taboola', 'taboola\\.com', '🟦'],
        ['Outbrain', 'outbrain\\.com', '🟥'],
    ];
    foreach ($an as $r) {
        if (preg_match('~' . $r[1] . '~i', $u)) {
            $found[] = ['name' => $r[0], 'group' => 'Analytics / tracking', 'detail' => '', 'icon' => $r[2]];
        }
    }

    // ---- deduplicate by name ----
    $seen = [];
    $found = array_values(array_filter($found, function ($f) use (&$seen) {
        $k = $f['name'];
        if (isset($seen[$k])) {
            return false;
        }
        $seen[$k] = true;
        return true;
    }));

    echo json_encode([
        'url' => $url,
        'final_url' => $final,
        'code' => $code,
        'host' => $host,
        'size' => strlen($html),
        'technologies' => $found,
    ]);
    exit;
}

page_header('Tech Stack Detector');
?>
<style>
.ts-tag { display:inline-block; padding:3px 10px; border-radius:7px; font-size:.8rem; margin:3px; border:1px solid rgba(88,101,242,.35); background:rgba(88,101,242,.1); color:#c7cbfa; }
.ts-tag .g { color:var(--dim); font-size:.68rem; margin-left:5px; }
</style>
<div class="container" style="max-width: 860px;">
    <h1 class="h4 mb-2 reveal in-view">&#129518; Tech Stack Detector</h1>
    <p class="text-secondary mb-3 reveal in-view">Fingerprint the technology behind any website — frameworks, CMS, JS libraries, CDN, analytics and server headers — a lightweight, Wappalyzer-style scan.</p>

    <div class="card reveal in-view"><div class="card-body">
        <form id="tsForm" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="row g-2">
                <div class="col-md-8">
                    <input class="form-control" name="url" placeholder="https://example.com" required>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100" type="submit">Scan</button>
                </div>
            </div>
        </form>
    </div></div>

    <div id="tsErr" class="alert alert-danger mt-3 d-none"></div>
    <div class="card mt-3 d-none" id="tsCard"><div class="card-body" id="tsBody"></div></div>
</div>
<script>
(function(){
    var form=document.getElementById('tsForm');
    var err=document.getElementById('tsErr');
    var card=document.getElementById('tsCard');
    form.addEventListener('submit',function(e){
        e.preventDefault();
        err.classList.add('d-none');
        card.classList.add('d-none');
        fetch('index.php',{method:'POST',body:new FormData(form)})
            .then(function(r){return r.json();})
            .then(function(d){
                if(d.error){ err.textContent=d.error; err.classList.remove('d-none'); return; }
                var groups={};
                d.technologies.forEach(function(t){ (groups[t.group]=groups[t.group]||[]).push(t); });
                var h='<div class="text-secondary small mb-3">'+esc(d.final_url)+' — HTTP '+d.code+' · '+fmt(d.size)+' fetched · <strong>'+d.technologies.length+'</strong> technologies detected</div>';
                Object.keys(groups).sort().forEach(function(g){
                    h+='<div class="mb-1"><strong class="h6">'+esc(g)+'</strong></div><div class="mb-3">';
                    groups[g].forEach(function(t){
                        h+='<span class="ts-tag">'+esc(t.icon+' '+t.name)+(t.detail?' <code>'+esc(t.detail)+'</code>':'')+'<span class="g">'+esc(t.group)+'</span></span>';
                    });
                    h+='</div>';
                });
                if(!d.technologies.length) h+='<div class="text-secondary small">Nothing obvious detected. The page may be highly obfuscated, JS-rendered or scanned content only.</div>';
                document.getElementById('tsBody').innerHTML=h;
                card.classList.remove('d-none');
            })
            .catch(function(){ err.textContent='Network error. Try again.'; err.classList.remove('d-none'); });
    });
    function esc(s){ var e=document.createElement('div'); e.appendChild(document.createTextNode(s==null?'':String(s))); return e.innerHTML; }
    function fmt(n){ return n>=1048576?(n/1048576).toFixed(1)+' MB':n>=1024?(n/1024).toFixed(1)+' KB':n+' B'; }
})();
</script>
<?php page_footer(); ?>