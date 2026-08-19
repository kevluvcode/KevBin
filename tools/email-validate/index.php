<?php
require_once __DIR__ . '/../../functions.php';

start_session();

$emailsRaw = trim((string)($_POST['emails'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $emailsRaw !== '') {
    header('Content-Type: application/json');

    if (!csrf_verify()) {
        echo json_encode(['error' => 'Invalid CSRF token. Reload the page and try again.']);
        exit;
    }
    if (!rate_limit_check('emailvalidate', 10, 60)) {
        echo json_encode(['error' => 'Rate limit reached — wait a moment before validating more emails.']);
        exit;
    }

    $lines = array_filter(array_map('trim', explode("\n", $emailsRaw)));
    $lines = array_unique($lines);
    $lines = array_slice($lines, 0, 20);

    if (count($lines) === 0) {
        echo json_encode(['error' => 'No valid email addresses provided.']);
        exit;
    }

    $disposableDomains = get_disposable_domains();
    $freeProviders = get_free_providers();
    $roleAccounts = get_role_accounts();

    $results = [];
    foreach ($lines as $email) {
        $results[] = validate_email($email, $disposableDomains, $freeProviders, $roleAccounts);
    }

    $validCount = 0;
    $invalidCount = 0;
    $suspiciousCount = 0;
    foreach ($results as $r) {
        if ($r['verdict'] === 'Valid') $validCount++;
        elseif ($r['verdict'] === 'Invalid') $invalidCount++;
        elseif ($r['verdict'] === 'Suspicious' || $r['verdict'] === 'Risky') $suspiciousCount++;
    }

    log_activity('tool_emailvalidate', count($lines) . ' emails checked');

    echo json_encode([
        'results' => $results,
        'summary' => [
            'total' => count($results),
            'valid' => $validCount,
            'invalid' => $invalidCount,
            'suspicious' => $suspiciousCount,
        ],
    ]);
    exit;
}

page_header('Email Validation');
?>
<style>
    .ev-result { display: none; }
    .ev-result.visible { display: block; }
    .ev-score-ring {
        width: 72px; height: 72px; border-radius: 50%; display: inline-flex;
        align-items: center; justify-content: center; font-size: 1.3rem;
        font-weight: 700; position: relative;
    }
    .ev-score-ring::before {
        content: ''; position: absolute; inset: 0; border-radius: 50%;
        border: 3px solid rgba(255,255,255,.08);
    }
    .ev-verdict-badge {
        display: inline-block; padding: .25em .65em; border-radius: 6px;
        font-size: .78rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: .04em;
    }
    .ev-card {
        background: var(--bs-body-bg, #1a1d23);
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 10px; overflow: hidden;
    }
    .ev-card-header {
        padding: .75rem 1rem; font-weight: 600; font-size: .9rem;
        border-bottom: 1px solid rgba(255,255,255,.08);
        display: flex; align-items: center; gap: .5rem;
    }
    .ev-card-body { padding: 1rem; }
    .ev-row {
        display: flex; gap: .75rem; padding: .35rem 0;
        border-bottom: 1px solid rgba(255,255,255,.04);
        align-items: flex-start;
    }
    .ev-row:last-child { border-bottom: none; }
    .ev-label {
        min-width: 140px; max-width: 140px; font-size: .78rem;
        color: #8b949e; font-weight: 500; text-transform: uppercase;
        letter-spacing: .03em; padding-top: 1px;
    }
    .ev-value { font-size: .85rem; word-break: break-word; flex: 1; }
    .ev-value code {
        background: rgba(255,255,255,.06); padding: .15em .4em;
        border-radius: 4px; font-size: .82rem;
    }
    .ev-pass { color: #3fb950; }
    .ev-fail { color: #f85149; }
    .ev-warn { color: #e3b341; }
    .ev-info { color: #58a6ff; }
    .ev-mx-list { list-style: none; padding: 0; margin: .25rem 0 0; }
    .ev-mx-list li {
        font-size: .8rem; padding: .2rem 0; color: #c9d1d9;
        font-family: 'JetBrains Mono', monospace;
    }
    .ev-mx-list li::before { content: '→ '; color: #58a6ff; }
    .summary-card {
        text-align: center; padding: 1rem .5rem;
    }
    .summary-card .summary-num {
        font-size: 2rem; font-weight: 700; line-height: 1.1;
    }
    .summary-card .summary-label {
        font-size: .75rem; text-transform: uppercase;
        letter-spacing: .05em; font-weight: 600; margin-top: .25rem;
    }
    .spinner-border-sm { width: 1rem; height: 1rem; border-width: .15em; }
</style>

<div class="container" style="max-width: 960px;">
    <h1 class="h4 mb-1 reveal in-view">&#9993; Email Validation (MX Record Check)</h1>
    <p class="text-secondary mb-4 reveal in-view">Validate email addresses with syntax checking, MX record lookups, disposable domain detection, free provider identification, and role account detection. Enter one email per line (max 20).</p>

    <div class="card reveal in-view"><div class="card-body">
        <form id="evForm" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="mb-3">
                <textarea class="form-control" name="emails" rows="6" maxlength="5000" placeholder="Enter email addresses, one per line:&#10;user@example.com&#10;admin@gmail.com&#10;test@mailinator.com" required style="font-family:'JetBrains Mono',monospace;font-size:.85rem;resize:vertical;"></textarea>
                <div class="form-text">One email per line. Maximum 20 emails per validation.</div>
            </div>
            <button class="btn btn-primary" type="submit" id="evBtn">
                <span id="evBtnLabel">Validate</span>
                <span id="evBtnSpin" class="d-none"><span class="spinner-border spinner-border-sm" role="status"></span></span>
            </button>
        </form>
    </div></div>

    <div id="evError" class="alert alert-danger mt-4 d-none reveal in-view"></div>

    <div id="evSummary" class="row g-3 mt-4 d-none">
        <div class="col-md-3">
            <div class="ev-card reveal in-view"><div class="card-body summary-card">
                <div class="summary-num" id="sumTotal">0</div>
                <div class="summary-label text-secondary">Total Checked</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="ev-card reveal in-view"><div class="card-body summary-card">
                <div class="summary-num ev-pass" id="sumValid">0</div>
                <div class="summary-label ev-pass">Valid</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="ev-card reveal in-view"><div class="card-body summary-card">
                <div class="summary-num ev-fail" id="sumInvalid">0</div>
                <div class="summary-label ev-fail">Invalid</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="ev-card reveal in-view"><div class="card-body summary-card">
                <div class="summary-num ev-warn" id="sumSuspicious">0</div>
                <div class="summary-label ev-warn">Suspicious</div>
            </div></div>
        </div>
    </div>

    <div id="evResults" class="ev-result mt-4"></div>
</div>

<script>
(function () {
    var form = document.getElementById('evForm');
    var btn = document.getElementById('evBtn');
    var btnLabel = document.getElementById('evBtnLabel');
    var btnSpin = document.getElementById('evBtnSpin');
    var errBox = document.getElementById('evError');
    var resultsDiv = document.getElementById('evResults');
    var summaryDiv = document.getElementById('evSummary');

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        errBox.classList.add('d-none');
        resultsDiv.innerHTML = '';
        summaryDiv.classList.add('d-none');
        btn.disabled = true;
        btnLabel.textContent = 'Validating...';
        btnSpin.classList.remove('d-none');

        var fd = new FormData(form);
        fetch('index.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.error) {
                    errBox.textContent = d.error;
                    errBox.classList.remove('d-none');
                    resetBtn();
                    return;
                }
                renderSummary(d.summary);
                renderResults(d.results);
                resetBtn();
                summaryDiv.querySelectorAll('.reveal').forEach(function (el) { el.classList.add('in-view'); });
                resultsDiv.querySelectorAll('.reveal').forEach(function (el) { el.classList.add('in-view'); });
            })
            .catch(function () {
                errBox.textContent = 'Network error. Please try again.';
                errBox.classList.remove('d-none');
                resetBtn();
            });
    });

    function resetBtn() {
        btn.disabled = false;
        btnLabel.textContent = 'Validate';
        btnSpin.classList.add('d-none');
    }

    function renderSummary(s) {
        document.getElementById('sumTotal').textContent = s.total;
        document.getElementById('sumValid').textContent = s.valid;
        document.getElementById('sumInvalid').textContent = s.invalid;
        document.getElementById('sumSuspicious').textContent = s.suspicious;
        summaryDiv.classList.remove('d-none');
    }

    function renderResults(results) {
        var html = '';
        results.forEach(function (r, i) {
            var verdictColor = r.verdict === 'Valid' ? '#3fb950' : r.verdict === 'Invalid' ? '#f85149' : r.verdict === 'Suspicious' ? '#e3b341' : '#d29922';
            var verdictBg = r.verdict === 'Valid' ? 'rgba(35,134,54,.2)' : r.verdict === 'Invalid' ? 'rgba(218,54,51,.2)' : r.verdict === 'Suspicious' ? 'rgba(187,128,9,.2)' : 'rgba(210,153,34,.2)';
            var verdictBorder = r.verdict === 'Valid' ? 'rgba(63,185,80,.3)' : r.verdict === 'Invalid' ? 'rgba(248,81,73,.3)' : r.verdict === 'Suspicious' ? 'rgba(227,179,65,.3)' : 'rgba(210,153,34,.3)';

            html += '<div class="ev-card mb-3 reveal" style="animation-delay:' + (i * 60) + 'ms;">';
            html += '<div class="ev-card-header" style="background:rgba(255,255,255,.03);">';
            html += '<span style="font-family:\'JetBrains Mono\',monospace;font-size:.85rem;">' + esc(r.email) + '</span>';
            html += '<span class="ev-verdict-badge" style="background:' + verdictBg + ';color:' + verdictColor + ';border:1px solid ' + verdictBorder + ';">' + esc(r.verdict) + '</span>';
            html += '<span class="ms-auto"><div class="ev-score-ring" style="border:3px solid ' + verdictColor + ';color:' + verdictColor + ';">' + r.score + '</div></span>';
            html += '</div>';
            html += '<div class="ev-card-body">';

            html += evRow('Syntax', r.syntax.pass ? '<span class="ev-pass">&#10003; Valid syntax</span>' : '<span class="ev-fail">&#10007; ' + esc(r.syntax.reason) + '</span>');
            html += evRow('MX Records', r.mx_records.length > 0
                ? '<ul class="ev-mx-list">' + r.mx_records.map(function(m) { return '<li><code>' + esc(m) + '</code></li>'; }).join('') + '</ul>'
                : '<span class="ev-fail">No MX records found</span>');

            if (r.dns.domain_exists) {
                html += evRow('Domain Exists', '<span class="ev-pass">&#10003; Yes</span>');
            } else {
                html += evRow('Domain Exists', '<span class="ev-fail">&#10007; Domain does not resolve</span>');
            }

            if (r.disposable.is_disposable) {
                html += evRow('Disposable', '<span class="ev-fail">&#9888; Yes — known disposable domain</span>');
            } else {
                html += evRow('Disposable', '<span class="ev-pass">&#10003; No</span>');
            }

            if (r.free_provider.is_free) {
                html += evRow('Free Provider', '<span class="ev-info">' + esc(r.free_provider.name) + '</span>');
            } else {
                html += evRow('Free Provider', '<span class="text-secondary">No — likely custom / business domain</span>');
            }

            if (r.role_account.is_role) {
                html += evRow('Role Account', '<span class="ev-warn">&#9888; Yes — ' + esc(r.role_account.type) + '</span>');
            } else {
                html += evRow('Role Account', '<span class="ev-pass">&#10003; No — personal account</span>');
            }

            html += '</div></div>';
        });

        resultsDiv.innerHTML = html;
        resultsDiv.classList.add('visible');
    }

    function evRow(label, value) {
        return '<div class="ev-row"><div class="ev-label">' + label + '</div><div class="ev-value">' + value + '</div></div>';
    }

    function esc(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s || ''));
        return d.innerHTML;
    }
})();
</script>
<?php page_footer(); ?>

<?php
function validate_email(string $email, array $disposableDomains, array $freeProviders, array $roleAccounts): array
{
    $email = trim($email);
    $email = strtolower($email);
    $score = 50;
    $syntaxPass = false;
    $syntaxReason = '';
    $mxRecords = [];
    $isDisposable = false;
    $isFree = false;
    $freeName = '';
    $isRole = false;
    $roleType = '';
    $domainExists = false;
    $verdict = 'Invalid';

    $parts = explode('@', $email);
    if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
        $syntaxReason = 'Missing local part or domain';
        return build_result($email, $verdict, $score, $syntaxPass, $syntaxReason, $mxRecords, $isDisposable, $isFree, $freeName, $isRole, $roleType, $domainExists);
    }

    $local = $parts[0];
    $domain = $parts[1];

    $rfc5322 = '/^(?:(?!"[a-zA-Z0-9._%+-]{0,63}")[a-zA-Z0-9._%+-]{1,64}|"(?:[^"\\\\]|\\\\.){0,63}")@(?!-)(?:[a-zA-Z0-9-]{1,63}\.)+[a-zA-Z]{2,63}$/';
    if (preg_match($rfc5322, $email)) {
        $syntaxPass = true;
        $score += 20;
    } else {
        $syntaxReason = 'Does not comply with RFC 5322 email format';
        $score -= 20;
    }

    if (strlen($local) > 64) {
        $syntaxPass = false;
        $syntaxReason = 'Local part exceeds 64 characters';
        $score -= 10;
    }

    if (strlen($domain) > 253) {
        $syntaxPass = false;
        $syntaxReason = 'Domain exceeds 253 characters';
        $score -= 10;
    }

    if ($syntaxPass) {
        $mxRecords = @getmxrr($domain, $mxResults) ? $mxResults : [];
        if (count($mxRecords) > 0) {
            $score += 20;
            $domainExists = true;
        } else {
            $ips = @gethostbynamel($domain);
            if (is_array($ips) && count($ips) > 0) {
                $domainExists = true;
                $score += 5;
            }
        }
    }

    if ($syntaxPass && $domainExists && function_exists('dns_get_record')) {
        try {
            $dns = @dns_get_record($domain, DNS_A + DNS_AAAA);
            if (is_array($dns) && count($dns) > 0) {
                $domainExists = true;
            }
        } catch (Throwable $t) {
        }
    }

    $domainParts = explode('.', $domain);
    $baseDomain = count($domainParts) >= 2 ? $domainParts[count($domainParts) - 2] . '.' . $domainParts[count($domainParts) - 1] : $domain;
    if (isset($disposableDomains[$baseDomain]) || isset($disposableDomains[$domain])) {
        $isDisposable = true;
        $score -= 30;
    }

    foreach ($freeProviders as $freeDom => $name) {
        if ($domain === $freeDom || substr($domain, -(strlen($freeDom) + 1)) === '.' . $freeDom) {
            $isFree = true;
            $freeName = $name;
            break;
        }
    }

    if ($isFree) {
        $score += 5;
    }

    foreach ($roleAccounts as $role => $label) {
        if ($local === $role) {
            $isRole = true;
            $roleType = $label;
            $score -= 10;
            break;
        }
    }

    if (!$syntaxPass) {
        $verdict = 'Invalid';
    } elseif (!$domainExists && count($mxRecords) === 0) {
        $verdict = 'Invalid';
        $score = max($score, 0);
    } elseif ($isDisposable) {
        $verdict = 'Suspicious';
    } elseif ($isRole) {
        $verdict = 'Risky';
    } elseif ($domainExists && count($mxRecords) > 0 && !$isDisposable && !$isRole) {
        $verdict = 'Valid';
    } else {
        $verdict = 'Suspicious';
    }

    $score = max(0, min(100, $score));

    return build_result($email, $verdict, $score, $syntaxPass, $syntaxReason, $mxRecords, $isDisposable, $isFree, $freeName, $isRole, $roleType, $domainExists);
}

function build_result(string $email, string $verdict, int $score, bool $syntaxPass, string $syntaxReason, array $mxRecords, bool $isDisposable, bool $isFree, string $freeName, bool $isRole, string $roleType, bool $domainExists): array
{
    return [
        'email' => $email,
        'verdict' => $verdict,
        'score' => $score,
        'syntax' => ['pass' => $syntaxPass, 'reason' => $syntaxReason],
        'mx_records' => array_values(array_map('strval', $mxRecords)),
        'disposable' => ['is_disposable' => $isDisposable],
        'free_provider' => ['is_free' => $isFree, 'name' => $freeName],
        'role_account' => ['is_role' => $isRole, 'type' => $roleType],
        'dns' => ['domain_exists' => $domainExists],
    ];
}

function get_disposable_domains(): array
{
    return array_flip([
        'mailinator.com','guerrillamail.com','guerrillamail.net','guerrillamail.org','guerrillamail.biz',
        'guerrillamail.de','guerrillamail.info','guerrillamail.se','guerrillamail.tv','guerrillamailblock.com',
        'tempmail.com','temp-mail.org','temp-mail.io','tempail.com','tempr.email','tempmailer.com',
        'throwaway.email','yopmail.com','yopmail.fr','yopmail.net','10minutemail.com','10minutemail.co.za',
        '10minutemail.org','10minutemail.net','minuteinbox.com','tempinbox.com','maildrop.cc',
        'mailsac.com','mailnesia.com','mailnull.com','mailscrap.com','discard.email','discardmail.com',
        'discardmail.de','fakeinbox.com','fakemail.fr','fakemailz.com','fakemailgenerator.com',
        'getnada.com','harakirimail.com','jetable.org','jetable.com','jetable.fr.nf','jetable.net',
        'mailexpire.com','mailforspam.com','mailgutter.com','mailhazard.com','mailhz.me',
        'mailimate.com','mailin8r.com','mailinater.com','mailinator.net','mailinator2.com',
        'mailincubator.com','mailismagic.com','mailmate1.com','mailme.ir','mailme.lv',
        'mailme24.com','mailmetrash.com','mailmoat.com','mailnator.com','mailproxsy.com',
        'mailquack.com','mailrock.biz','mailscrap.com','mailshell.com','mailsiphon.com',
        'mailslite.com','mailtemp.info','mailtome.de','mailtothis.com','mailtrash.net',
        'mailtv.net','mailtv.tv','mailzilla.com','mailzilla.org','meltmail.com',
        'messagebeamer.com','mohmal.com','mohmal.in','mohmal.net','nomail.xl.cx',
        'nomail2me.com','nospam.ze.tc','nospamfor.us','nowmymail.com','nxtmail.ru',
        'obobbo.com','odnorazovoe.ru','oneoffemail.com','onewaymail.com','oopi.org',
        'ordinaryamerican.net','otherinbox.com','ourklips.com','outlawspam.com',
        'ovpn.to','owlpic.com','pancakemail.com','pimpedupmyspace.com','pjjkp.com',
        'plexp.com','poczta.onet.pl','privacy.net','privatdemail.net','proxymail.eu',
        'prtnx.com','punkass.com','putthisinyouremail.com','qq.com','quickinbox.com',
        'quickmail.nl','rcpt.at','reallymymail.com','realtyalerts.ca','recode.me',
        'recursor.net','regbypass.com','regbypass.comsafe','rejectmail.com',
        'reliable-mail.com','rhyta.com','rklips.com','rmqkr.net','royal.net',
        'rppkn.com','rtrtr.com','s0ny.net','safe-mail.net','safersignup.de',
        'safetymail.info','sandelf.de','saynotospams.com','scatmail.com','schafmail.de',
        'schrott-email.de','secretemail.de','secure-mail.biz','selfdestructingmail.com',
        'sendspamhere.com','shiftmail.com','shitmail.me','shitmail.org','shitware.nl',
        'shmeriously.com','shortmail.net','sibmail.com','sinnlos-mail.de','skeefmail.com',
        'slaskpost.se','slipry.net','slopsbox.com','slowslow.de','smashmail.de',
        'smellfear.com','snakemail.com','sneakemail.com','sneakymail.de','snkmail.com',
        'sofimail.com','sofort-mail.de','softpls.asia','sogetthis.com','soodonims.com',
        'spam.la','spam.su','spam4.me','spamavert.com','spambob.com','spambob.net',
        'spambob.org','spambog.com','spambog.de','spambog.ru','spambog.us',
        'spambox.info','spambox.irishspringrealty.com','spambox.us','spamcannon.com',
        'spamcannon.net','spamcero.com','spamcorptastic.com','spamcowboy.com',
        'spamcowboy.net','spamcowboy.org','spamday.com','spamex.com','spamfighter.cf',
        'spamfighter.ga','spamfighter.ml','spamfighter.tk','spamfree24.com','spamfree24.de',
        'spamfree24.eu','spamfree24.info','spamfree24.net','spamfree24.org','spamgourmet.com',
        'spamgourmet.net','spamgourmet.org','spamherelots.com','spamhereplease.com',
        'spamhole.com','spamify.com','spaminator.de','spaml.com','spaml.de','spammotel.com',
        'spamobox.com','spamoff.de','spamslicer.com','spamspot.com','spamstack.net',
        'spamthis.co.uk','spamthisplease.com','spamtrail.com','spamtrap.ro',
        'speed.1s.fr','squizzy.de','supermailer.jp','superrito.com','superstachel.de',
        'suremail.info','svk.jp','sweetxxx.de','talkinator.com','tapchicuoihoi.com',
        'teleworm.com','teleworm.us','temp-mail.ru','temp-email.org','temp-mails.com',
        'tempalias.com','tempesthq.com','tempemail.biz','tempemail.co.za','tempemail.com',
        'tempemail.net','tempemail.org','tempinbox.co.uk','tempmail.eu','tempmail.it',
        'tempmail2.com','tempmaildemo.com','tempmailer.com','tempmailer.de','tempomail.fr',
        'temporarily.de','temporarioemail.com','temporarioemail.es','temporarioemail.pt',
        'temporaryemail.net','temporaryemail.us','temporaryforwarding.com','temporaryinbox.com',
        'temporarymailaddress.com','tempthe.net','thankyou2010.com','thc.st','thecloudindex.com',
        'thetempmail.com','throwam.com','tittbit.in','tizi.com','tmailinator.com',
        'toiea.com','toomail.biz','topranklist.de','tradermail.info','trash-amil.com',
        'trash-mail.at','trash-mail.com','trash-mail.de','trash-me.com','trash2009.com',
        'trashdevil.com','trashdevil.de','trashemail.de','trashmail.at','trashmail.com',
        'trashmail.de','trashmail.me','trashmail.net','trashmail.org','trashmail.ws',
        'trashmailer.com','trashmailer.net','trashymail.com','trashymail.net',
        'trillianpro.com','turual.com','twinmail.de','tyldd.com','uggsrock.com',
        'umail.net','upliftnow.com','uplipht.com','venompen.com','veryrealliemail.com',
        'vidlogic.com','viditag.com','viewcastmedia.com','viewcastmedia.net',
        'viewcastmedia.org','vomoto.com','vpn.st','vsimcard.com','vubby.com',
        'wasteland.rfc822.org','webemail.me','weg-werf-email.de','wegwerfadresse.de',
        'wegwerfemail.com','wegwerfemail.de','wegwerfmail.de','wegwerfmail.net',
        'wegwerfmail.org','wh4f.org','whatiaas.com','whatpaas.com','whyspam.me',
        'wickmail.net','wilemail.com','winemaven.info','wronghead.com','wuzup.net',
        'wuzupmail.net','wwwnew.eu','xagloo.com','xemaps.com','xents.com',
        'xjoi.com','xmaily.com','xoxy.net','yapped.net','yeah.net','yep.it',
        'yogamaven.com','yomail.info','yomail.org','youdid.com','your-wife.com',
        'yuurok.com','zehnminutenmail.de','zippymail.info','zoaxe.com','zoemail.org',
        'guerrillamailblock.com','grr.la','guerrillamail.info','gustr.com',
        'h8s.org','hacccc.com','hidemail.de','hidzz.com','hmamail.com',
        'hopemail.biz','hot-mail.cf','hot-mail.ga','hot-mail.gq','hot-mail.tk',
        'hotpop.com','hulapla.de','hushmail.com','ichimail.com','imail.org',
        'inbax.tk','inbox.si','inboxclean.com','inboxclean.org','inboxproxy.com',
        'incognitomail.com','incognitomail.net','incognitomail.org','ineec.net',
        'infocom.zp.ua','inoutmail.de','inoutmail.eu','inoutmail.info','inoutmail.net',
        'investgarant.com','iwi.net','jnxjn.com','jourrapide.com','jsrsolutions.com',
        'junk1e.com','junkmail.com','junkmail.ga','junkmail.gq','kaictl.com',
        'kaypaso.com','keinpferd.com','keynomail.com','killmail.com','killmail.net',
        'kingsq.ga','kir.ch.tc','klassmaster.com','klassmaster.net','klzlk.com',
        'kook.ml','kurzepost.de','lawlita.com','letthemeatspam.com','lhs.com',
        'lhsdf.com','lifebyfood.com','link2mail.net','litedrop.com','lol.ovpn.to',
        'lookugly.com','lopl.co.cc','lortemail.dk','lovemeleaveme.com','lr78.com',
        'lroid.com','lukop.dk','m21.cc','maboard.com','mail-temporaire.fr',
        'mail.by','mail.mezimages.net','mail.zp.ua','mail114.net','mail1a.de',
        'mail21.cc','mail2rss.org','mail333.com','mail4trash.com','mailbidon.com',
        'mailblocks.com','mailblog.biz','mailbucket.org','mailcat.biz','mailcatch.com',
        'maildrop.cc','maildu.de','maildx.com','maileater.com','mailed.ro',
        'maileimer.de','mailexpire.com','mailfa.tk','mailforspam.com','mailfs.com',
        'mailguard.me','mailhazard.us','mailhz.me','mailimate.com','mailin8r.com',
        'mailinater.com','mailinator.com','mailinator.net','mailinator.us',
        'mailinator2.com','mailincubator.com','mailismagic.com','mailmate1.com',
        'mailme.ir','mailme.lv','mailme24.com','mailmetrash.com','mailmoat.com',
        'mailnator.com','mailproxsy.com','mailquack.com','mailrock.biz','mailscrap.com',
        'mailshell.com','mailsiphon.com','mailslite.com','mailtemp.info',
        'mailtome.de','mailtothis.com','mailtrash.net','mailtv.net','mailtv.tv',
        'mailzilla.com','meltmail.com','messagebeamer.com','mezimages.net',
        'mfsa.ru','mierdamail.com','migmail.pl','migumail.com','mindless.com',
        'misterpinball.de','mmmmail.com','moakt.com','mobi.web.id','mohmal.com',
        'mohmal.in','mohmal.net','moncourrier.fr','monemail.fr','monmail.fr',
        'monumentmail.com','msa.minsmail.com','mt2015.com','mx0.wwwnew.eu',
        'my10minutemail.com','myalias.pw','mycard.net.ua','mycleaninbox.net',
        'myemailboxy.com','mymail-in.net','mymailoasis.com','mymailoasis.net',
        'mymailuk.com','mymailwizard.com','mytemp.email','mytempemail.com',
        'mytempmail.com','mytrashmail.com','nabala.com','neomailbox.com',
        'nepwk.com','nervmich.net','nervtansen.de','netmails.com','netmails.net',
        'netzidiot.de','neverbox.com','nice-4u.com','nincsmail.hu','nnh.com',
        'no-spam.ws','noblepioneer.com','nomail.xl.cx','nomail2me.com','nomorespamemails.com',
        'nonspam.eu','nonspammer.de','noref.in','nospam.ze.tc','nospam4.us',
        'nospamforall.info','nospamfor.us','nospammail.net','nospamthanks.info',
        'nothingtoseehere.ca','nowmymail.com','nurfuerspam.de','nuts2.pl',
        'nwldx.com','objectmail.com','obobbo.com','odnorazovoe.ru','oneoffemail.com',
        'onewaymail.com','oopi.org','ordinaryamerican.net','otherinbox.com',
        'ourklips.com','outlawspam.com','ovpn.to','owlpic.com','pancakemail.com',
        'pimpedupmyspace.com','pjjkp.com','plexp.com','poczta.onet.pl',
        'privatdemail.net','proxymail.eu','prtnx.com','punkass.com','putthisinyouremail.com',
        'qq.com','quickinbox.com','quickmail.nl','rcpt.at','reallymymail.com',
        'realtyalerts.ca','recode.me','recursor.net','regbypass.com',
        'regbypass.comsafe','rejectmail.com','reliable-mail.com','rhyta.com',
        'rklips.com','rmqkr.net','royal.net','rppkn.com','rtrtr.com',
        's0ny.net','safe-mail.net','safersignup.de','safetymail.info','sandelf.de',
        'saynotospams.com','scatmail.com','schafmail.de','schrott-email.de',
        'secretemail.de','secure-mail.biz','selfdestructingmail.com','sendspamhere.com',
        'shiftmail.com','shitmail.me','shitmail.org','shitware.nl','shmeriously.com',
        'shortmail.net','sibmail.com','sinnlos-mail.de','skeefmail.com','slaskpost.se',
        'slipry.net','slopsbox.com','slowslow.de','smashmail.de','smellfear.com',
        'snakemail.com','sneakemail.com','sneakymail.de','snkmail.com','sofimail.com',
        'sofort-mail.de','softpls.asia','sogetthis.com','soodonims.com','spam.la',
        'spam.su','spam4.me','spamavert.com','spambob.com','spambob.net',
        'spambob.org','spambog.com','spambog.de','spambog.ru','spambog.us',
        'spambox.info','spambox.us','spamcannon.com','spamcannon.net','spamcero.com',
        'spamcorptastic.com','spamcowboy.com','spamcowboy.net','spamcowboy.org',
        'spamday.com','spamex.com','spamfighter.cf','spamfighter.ga','spamfighter.ml',
        'spamfighter.tk','spamfree24.com','spamfree24.de','spamfree24.eu',
        'spamfree24.info','spamfree24.net','spamfree24.org','spamgourmet.com',
        'spamgourmet.net','spamgourmet.org','spamherelots.com','spamhereplease.com',
        'spamhole.com','spamify.com','spaminator.de','spaml.com','spaml.de',
        'spammotel.com','spamobox.com','spamoff.de','spamslicer.com','spamspot.com',
        'spamstack.net','spamthis.co.uk','spamthisplease.com','spamtrail.com',
        'spamtrap.ro','speed.1s.fr','squizzy.de','supermailer.jp','superrito.com',
        'superstachel.de','suremail.info','svk.jp','sweetxxx.de','talkinator.com',
        'tapchicuoihoi.com','teleworm.com','teleworm.us','temp-mail.ru',
        'temp-email.org','temp-mails.com','tempalias.com','tempesthq.com',
        'tempemail.biz','tempemail.co.za','tempemail.com','tempemail.net',
        'tempemail.org','tempinbox.co.uk','tempmail.eu','tempmail.it',
        'tempmail2.com','tempmaildemo.com','tempmailer.com','tempmailer.de',
        'tempomail.fr','temporarily.de','temporarioemail.com','temporarioemail.es',
        'temporarioemail.pt','temporaryemail.net','temporaryemail.us',
        'temporaryforwarding.com','temporaryinbox.com','temporarymailaddress.com',
        'tempthe.net','thankyou2010.com','thc.st','thecloudindex.com',
        'thetempmail.com','throwam.com','tittbit.in','tizi.com','tmailinator.com',
        'toiea.com','tomail.biz','topranklist.de','tradermail.info',
        'trash-amil.com','trash-mail.at','trash-mail.com','trash-mail.de',
        'trash-me.com','trash2009.com','trashdevil.com','trashdevil.de',
        'trashemail.de','trashmail.at','trashmail.com','trashmail.de',
        'trashmail.me','trashmail.net','trashmail.org','trashmail.ws',
        'trashmailer.com','trashmailer.net','trashymail.com','trashymail.net',
        'trillianpro.com','turual.com','twinmail.de','tyldd.com','uggsrock.com',
        'umail.net','upliftnow.com','uplipht.com','venompen.com',
        'veryrealliemail.com','vidlogic.com','viditag.com','viewcastmedia.com',
        'viewcastmedia.net','viewcastmedia.org','vomoto.com','vpn.st','vsimcard.com',
        'vubby.com','wasteland.rfc822.org','webemail.me','weg-werf-email.de',
        'wegwerfadresse.de','wegwerfemail.com','wegwerfemail.de','wegwerfmail.de',
        'wegwerfmail.net','wegwerfmail.org','wh4f.org','whatiaas.com',
        'whatpaas.com','whyspam.me','wickmail.net','wilemail.com',
        'winemaven.info','wronghead.com','wuzup.net','wuzupmail.net',
        'wwwnew.eu','xagloo.com','xemaps.com','xents.com','xjoi.com',
        'xmaily.com','xoxy.net','yapped.net','yeah.net','yep.it',
        'yogamaven.com','yomail.info','yomail.org','youdid.com',
        'your-wife.com','yuurok.com','zehnminutenmail.de','zippymail.info',
        'zoaxe.com','zoemail.org','guerrillamailblock.com','grr.la',
        'guerrillamail.info','gustr.com','h8s.org','hacccc.com',
        'hidemail.de','hidzz.com','hmamail.com','hopemail.biz',
        'hot-mail.cf','hot-mail.ga','hot-mail.gq','hot-mail.tk',
        'hotpop.com','hulapla.de','hushmail.com','ichimail.com','imail.org',
        'inbax.tk','inbox.si','inboxclean.com','inboxclean.org',
        'inboxproxy.com','incognitomail.com','incognitomail.net',
        'incognitomail.org','ineec.net','infocom.zp.ua','inoutmail.de',
        'inoutmail.eu','inoutmail.info','inoutmail.net','investgarant.com',
        'iwi.net','jnxjn.com','jourrapide.com','jsrsolutions.com',
        'junk1e.com','junkmail.com','junkmail.ga','junkmail.gq',
        'kaictl.com','kaypaso.com','keinpferd.com','keynomail.com',
        'killmail.com','killmail.net','kingsq.ga','klassmaster.com',
        'klassmaster.net','klzlk.com','kook.ml','kurzepost.de',
        'lawlita.com','letthemeatspam.com','lhs.com','lhsdf.com',
        'lifebyfood.com','link2mail.net','litedrop.com','lol.ovpn.to',
        'lookugly.com','lpl.co.cc','lortemail.dk','lovemeleaveme.com',
        'lr78.com','lroid.com','lukop.dk','m21.cc','maboard.com',
        'mail-temporaire.fr','mail.by','mail.mezimages.net','mail.zp.ua',
        'mail114.net','mail1a.de','mail21.cc','mail2rss.org','mail333.com',
        'mail4trash.com','mailbidon.com','mailblocks.com','mailblog.biz',
        'mailbucket.org','mailcat.biz','mailcatch.com','maildrop.cc',
        'maildu.de','maildx.com','maileater.com','mailed.ro',
        'maileimer.de','mailexpire.com','mailfa.tk','mailforspam.com',
        'mailfs.com','mailguard.me','mailhazard.us','mailhz.me',
        'mailimate.com','mailin8r.com','mailinater.com','mailinator.com',
        'mailinator.net','mailinator.us','mailinator2.com',
        'mailincubator.com','mailismagic.com','mailmate1.com','mailme.ir',
        'mailme.lv','mailme24.com','mailmetrash.com','mailmoat.com',
        'mailnator.com','mailproxsy.com','mailquack.com','mailrock.biz',
        'mailscrap.com','mailshell.com','mailsiphon.com','mailslite.com',
        'mailtemp.info','mailtome.de','mailtothis.com','mailtrash.net',
        'mailtv.net','mailtv.tv','mailzilla.com','meltmail.com',
        'messagebeamer.com','mezimages.net','mfsa.ru','mierdamail.com',
        'migmail.pl','migumail.com','mindless.com','misterpinball.de',
        'mmmmail.com','moakt.com','mobi.web.id','mohmal.com',
        'moncourrier.fr','monemail.fr','monmail.fr','monumentmail.com',
        'msa.minsmail.com','mt2015.com','mx0.wwwnew.eu',
        'my10minutemail.com','myalias.pw','mycard.net.ua',
        'mycleaninbox.net','myemailboxy.com','mymail-in.net',
        'mymailoasis.com','mymailoasis.net','mymailuk.com',
        'mymailwizard.com','mytemp.email','mytempemail.com',
        'mytempmail.com','mytrashmail.com','nabala.com','neomailbox.com',
        'nepwk.com','nervmich.net','nervtansen.de','netmails.com',
        'netmails.net','netzidiot.de','neverbox.com','nice-4u.com',
        'nincsmail.hu','nnh.com','no-spam.ws','noblepioneer.com',
        'nomail.xl.cx','nomail2me.com','nomorespamemails.com',
        'nonspam.eu','nonspammer.de','noref.in','nospam.ze.tc',
        'nospam4.us','nospamforall.info','nospamfor.us',
        'nospammail.net','nospamthanks.info','nothingtoseehere.ca',
        'nowmymail.com','nurfuerspam.de','nuts2.pl','nwldx.com',
        'objectmail.com','obobbo.com','odnorazovoe.ru','oneoffemail.com',
        'onewaymail.com','oopi.org','ordinaryamerican.net','otherinbox.com',
        'ourklips.com','outlawspam.com','ovpn.to','owlpic.com',
        'pancakemail.com','pimpedupmyspace.com','pjjkp.com','plexp.com',
        'poczta.onet.pl','privatdemail.net','proxymail.eu','prtnx.com',
        'punkass.com','putthisinyouremail.com','qq.com','quickinbox.com',
        'quickmail.nl','rcpt.at','reallymymail.com','realtyalerts.ca',
        'recode.me','recursor.net','regbypass.com','regbypass.comsafe',
        'rejectmail.com','reliable-mail.com','rhyta.com','rklips.com',
        'rmqkr.net','royal.net','rppkn.com','rtrtr.com','s0ny.net',
        'safe-mail.net','safersignup.de','safetymail.info','sandelf.de',
        'saynotospams.com','scatmail.com','schafmail.de','schrott-email.de',
        'secretemail.de','secure-mail.biz','selfdestructingmail.com',
        'sendspamhere.com','shiftmail.com','shitmail.me','shitmail.org',
        'shitware.nl','shmeriously.com','shortmail.net','sibmail.com',
        'sinnlos-mail.de','skeefmail.com','slaskpost.se','slipry.net',
        'slopsbox.com','slowslow.de','smashmail.de','smellfear.com',
        'snakemail.com','sneakemail.com','sneakymail.de','snkmail.com',
        'sofimail.com','sofort-mail.de','softpls.asia','sogetthis.com',
        'soodonims.com','spam.la','spam.su','spam4.me','spamavert.com',
        'spambob.com','spambob.net','spambob.org','spambog.com',
        'spambog.de','spambog.ru','spambog.us','spambox.info',
        'spambox.us','spamcannon.com','spamcannon.net','spamcero.com',
        'spamcorptastic.com','spamcowboy.com','spamcowboy.net',
        'spamcowboy.org','spamday.com','spamex.com','spamfree24.com',
        'spamfree24.de','spamfree24.eu','spamfree24.info',
        'spamfree24.net','spamfree24.org','spamgourmet.com',
        'spamgourmet.net','spamgourmet.org','spamherelots.com',
        'spamhereplease.com','spamhole.com','spamify.com',
        'spaminator.de','spaml.com','spaml.de','spammotel.com',
        'spamobox.com','spamoff.de','spamslicer.com','spamspot.com',
        'spamstack.net','spamthis.co.uk','spamthisplease.com',
        'spamtrail.com','spamtrap.ro','squizzy.de','supermailer.jp',
        'superrito.com','superstachel.de','suremail.info','svk.jp',
        'sweetxxx.de','talkinator.com','teleworm.com','teleworm.us',
        'temp-mail.ru','temp-email.org','temp-mails.com','tempalias.com',
        'tempesthq.com','tempemail.biz','tempemail.co.za','tempemail.com',
        'tempemail.net','tempemail.org','tempinbox.co.uk','tempmail.eu',
        'tempmail.it','tempmail2.com','tempmaildemo.com','tempmailer.com',
        'tempmailer.de','tempomail.fr','temporarily.de',
        'temporarioemail.com','temporarioemail.es','temporarioemail.pt',
        'temporaryemail.net','temporaryemail.us',
        'temporaryforwarding.com','temporaryinbox.com',
        'temporarymailaddress.com','tempthe.net','thankyou2010.com',
        'thc.st','thecloudindex.com','thetempmail.com','throwam.com',
        'tittbit.in','tizi.com','tmailinator.com','toiea.com',
        'tomail.biz','topranklist.de','tradermail.info',
        'trash-amil.com','trash-mail.at','trash-mail.com',
        'trash-mail.de','trash-me.com','trash2009.com','trashdevil.com',
        'trashdevil.de','trashemail.de','trashmail.at','trashmail.com',
        'trashmail.de','trashmail.me','trashmail.net','trashmail.org',
        'trashmail.ws','trashmailer.com','trashmailer.net',
        'trashymail.com','trashymail.net','trillianpro.com',
        'turual.com','twinmail.de','tyldd.com','uggsrock.com',
        'umail.net','upliftnow.com','uplipht.com','venompen.com',
        'veryrealliemail.com','vidlogic.com','viditag.com',
        'viewcastmedia.com','viewcastmedia.net','viewcastmedia.org',
        'vomoto.com','vpn.st','vsimcard.com','vubby.com',
        'wasteland.rfc822.org','webemail.me','weg-werf-email.de',
        'wegwerfadresse.de','wegwerfemail.com','wegwerfemail.de',
        'wegwerfmail.de','wegwerfmail.net','wegwerfmail.org',
        'wh4f.org','whatiaas.com','whatpaas.com','whyspam.me',
        'wickmail.net','wilemail.com','winemaven.info',
        'wronghead.com','wuzup.net','wuzupmail.net','wwwnew.eu',
        'xagloo.com','xemaps.com','xents.com','xjoi.com',
        'xmaily.com','xoxy.net','yapped.net','yeah.net','yep.it',
        'yogamaven.com','yomail.info','yomail.org','youdid.com',
        'your-wife.com','yuurok.com','zehnminutenmail.de',
        'zippymail.info','zoaxe.com','zoemail.org',
    ]);
}

function get_free_providers(): array
{
    return [
        'gmail.com' => 'Gmail',
        'yahoo.com' => 'Yahoo Mail',
        'yahoo.co.uk' => 'Yahoo Mail UK',
        'yahoo.co.in' => 'Yahoo Mail India',
        'yahoo.co.jp' => 'Yahoo Mail Japan',
        'yahoo.ca' => 'Yahoo Mail Canada',
        'yahoo.com.au' => 'Yahoo Mail Australia',
        'yahoo.com.br' => 'Yahoo Mail Brazil',
        'yahoo.com.mx' => 'Yahoo Mail Mexico',
        'yahoo.de' => 'Yahoo Mail Germany',
        'yahoo.fr' => 'Yahoo Mail France',
        'yahoo.it' => 'Yahoo Mail Italy',
        'yahoo.es' => 'Yahoo Mail Spain',
        'yahoo.com.sg' => 'Yahoo Mail Singapore',
        'yahoo.com.hk' => 'Yahoo Mail Hong Kong',
        'yahoo.com.tw' => 'Yahoo Mail Taiwan',
        'yahoo.com.ar' => 'Yahoo Mail Argentina',
        'yahoo.com.co' => 'Yahoo Mail Colombia',
        'yahoo.com.tr' => 'Yahoo Mail Turkey',
        'yahoo.com.ph' => 'Yahoo Mail Philippines',
        'yahoo.com.vn' => 'Yahoo Mail Vietnam',
        'yahoo.com.sa' => 'Yahoo Mail Saudi Arabia',
        'yahoo.com.eg' => 'Yahoo Mail Egypt',
        'yahoo.com.ng' => 'Yahoo Mail Nigeria',
        'yahoo.co.kr' => 'Yahoo Mail Korea',
        'outlook.com' => 'Microsoft Outlook',
        'outlook.co.uk' => 'Microsoft Outlook UK',
        'outlook.fr' => 'Microsoft Outlook France',
        'outlook.de' => 'Microsoft Outlook Germany',
        'outlook.it' => 'Microsoft Outlook Italy',
        'outlook.es' => 'Microsoft Outlook Spain',
        'outlook.com.br' => 'Microsoft Outlook Brazil',
        'outlook.com.au' => 'Microsoft Outlook Australia',
        'outlook.com.ar' => 'Microsoft Outlook Argentina',
        'outlook.com.in' => 'Microsoft Outlook India',
        'outlook.com.sg' => 'Microsoft Outlook Singapore',
        'outlook.com.my' => 'Microsoft Outlook Malaysia',
        'outlook.com.ph' => 'Microsoft Outlook Philippines',
        'outlook.com.hk' => 'Microsoft Outlook Hong Kong',
        'outlook.com.tw' => 'Microsoft Outlook Taiwan',
        'outlook.co.in' => 'Microsoft Outlook India',
        'outlook.co.jp' => 'Microsoft Outlook Japan',
        'outlook.co.th' => 'Microsoft Outlook Thailand',
        'outlook.com.cn' => 'Microsoft Outlook China',
        'outlook.com.vn' => 'Microsoft Outlook Vietnam',
        'outlook.com.sa' => 'Microsoft Outlook Saudi Arabia',
        'outlook.com.pk' => 'Microsoft Outlook Pakistan',
        'outlook.com.tr' => 'Microsoft Outlook Turkey',
        'outlook.com.mx' => 'Microsoft Outlook Mexico',
        'outlook.com.pe' => 'Microsoft Outlook Peru',
        'outlook.com.co' => 'Microsoft Outlook Colombia',
        'outlook.com.ec' => 'Microsoft Outlook Ecuador',
        'outlook.cl' => 'Microsoft Outlook Chile',
        'live.com' => 'Microsoft Live',
        'live.co.uk' => 'Microsoft Live UK',
        'live.fr' => 'Microsoft Live France',
        'live.de' => 'Microsoft Live Germany',
        'live.nl' => 'Microsoft Live Netherlands',
        'live.com.au' => 'Microsoft Live Australia',
        'live.com.mx' => 'Microsoft Live Mexico',
        'live.ca' => 'Microsoft Live Canada',
        'live.in' => 'Microsoft Live India',
        'live.nl' => 'Microsoft Live Netherlands',
        'live.it' => 'Microsoft Live Italy',
        'live.no' => 'Microsoft Live Norway',
        'live.se' => 'Microsoft Live Sweden',
        'live.dk' => 'Microsoft Live Denmark',
        'live.fi' => 'Microsoft Live Finland',
        'hotmail.com' => 'Microsoft Hotmail',
        'hotmail.co.uk' => 'Microsoft Hotmail UK',
        'hotmail.fr' => 'Microsoft Hotmail France',
        'hotmail.de' => 'Microsoft Hotmail Germany',
        'hotmail.it' => 'Microsoft Hotmail Italy',
        'hotmail.es' => 'Microsoft Hotmail Spain',
        'hotmail.com.br' => 'Microsoft Hotmail Brazil',
        'hotmail.co.jp' => 'Microsoft Hotmail Japan',
        'hotmail.co.in' => 'Microsoft Hotmail India',
        'hotmail.co.th' => 'Microsoft Hotmail Thailand',
        'hotmail.com.au' => 'Microsoft Hotmail Australia',
        'hotmail.com.ar' => 'Microsoft Hotmail Argentina',
        'hotmail.com.sg' => 'Microsoft Hotmail Singapore',
        'hotmail.com.my' => 'Microsoft Hotmail Malaysia',
        'hotmail.com.ph' => 'Microsoft Hotmail Philippines',
        'hotmail.com.hk' => 'Microsoft Hotmail Hong Kong',
        'hotmail.com.tw' => 'Microsoft Hotmail Taiwan',
        'hotmail.com.cn' => 'Microsoft Hotmail China',
        'hotmail.com.vn' => 'Microsoft Hotmail Vietnam',
        'hotmail.com.sa' => 'Microsoft Hotmail Saudi Arabia',
        'hotmail.com.pk' => 'Microsoft Hotmail Pakistan',
        'hotmail.com.tr' => 'Microsoft Hotmail Turkey',
        'hotmail.com.mx' => 'Microsoft Hotmail Mexico',
        'hotmail.com.pe' => 'Microsoft Hotmail Peru',
        'hotmail.com.co' => 'Microsoft Hotmail Colombia',
        'hotmail.com.ec' => 'Microsoft Hotmail Ecuador',
        'hotmail.cl' => 'Microsoft Hotmail Chile',
        'aol.com' => 'AOL Mail',
        'aol.co.uk' => 'AOL Mail UK',
        'aim.com' => 'AOL AIM Mail',
        'protonmail.com' => 'ProtonMail',
        'protonmail.ch' => 'ProtonMail Swiss',
        'pm.me' => 'ProtonMail (pm.me)',
        'proton.me' => 'ProtonMail (proton.me)',
        'protonmail.me' => 'ProtonMail',
        'icloud.com' => 'Apple iCloud',
        'me.com' => 'Apple iCloud (me.com)',
        'mac.com' => 'Apple iCloud (mac.com)',
        'zoho.com' => 'Zoho Mail',
        'zohomail.com' => 'Zoho Mail',
        'mail.com' => 'Mail.com',
        'email.com' => 'Email.com',
        'gmx.com' => 'GMX Mail',
        'gmx.de' => 'GMX Mail Germany',
        'gmx.net' => 'GMX Mail',
        'gmx.at' => 'GMX Mail Austria',
        'gmx.ch' => 'GMX Mail Switzerland',
        'web.de' => 'Web.de Mail',
        't-online.de' => 'T-Online Mail',
        'tiscali.it' => 'Tiscali Mail',
        'libero.it' => 'Libero Mail',
        'virgilio.it' => 'Virgilio Mail',
        'alice.it' => 'Alice Mail',
        'tin.it' => 'Tin Mail',
        'laposte.net' => 'La Poste Mail',
        'free.fr' => 'Free Mail France',
        'wanadoo.fr' => 'Wanadoo Mail',
        'orange.fr' => 'Orange Mail',
        'sfr.fr' => 'SFR Mail',
        'voila.fr' => 'Voila Mail',
        'neuf.fr' => 'Neuf Mail',
        '163.com' => 'NetEase Mail',
        '126.com' => 'NetEase Mail',
        'qq.com' => 'QQ Mail',
        'foxmail.com' => 'Foxmail',
        'sina.com' => 'Sina Mail',
        'sina.cn' => 'Sina Mail',
        'sohu.com' => 'Sohu Mail',
        'aliyun.com' => 'Alibaba Cloud Mail',
        'naver.com' => 'Naver Mail',
        'daum.net' => 'Daum Mail',
        'hanmail.net' => 'Hanmail',
        'nate.com' => 'Nate Mail',
        'rediffmail.com' => 'Rediffmail',
        'rediffmail.com' => 'Rediffmail',
        'yandex.com' => 'Yandex Mail',
        'yandex.ru' => 'Yandex Mail',
        'yandex.ua' => 'Yandex Mail',
        'yandex.by' => 'Yandex Mail',
        'yandex.kz' => 'Yandex Mail',
        'ya.ru' => 'Yandex Mail',
        'mail.ru' => 'Mail.ru',
        'inbox.ru' => 'Mail.ru (inbox)',
        'list.ru' => 'Mail.ru (list)',
        'bk.ru' => 'Mail.ru (bk)',
        'rambler.ru' => 'Rambler Mail',
        'autorambler.ru' => 'Rambler Mail',
        'myrambler.ru' => 'Rambler Mail',
        'ro.ru' => 'Rambler Mail',
        'tut.by' => 'Tut.by Mail',
        'i.ua' => 'I.ua Mail',
        'meta.ua' => 'Meta.ua Mail',
        'ukr.net' => 'Ukr.net Mail',
        'bigpond.com' => 'Telstra BigPond',
        'bigpond.com.au' => 'Telstra BigPond',
        'bigpond.net.au' => 'Telstra BigPond',
        'iinet.net.au' => 'iiNet Mail',
        'optusnet.com.au' => 'Optus Mail',
        'tpg.com.au' => 'TPG Mail',
        'shaw.ca' => 'Shaw Mail',
        'rogers.com' => 'Rogers Mail',
        'sympatico.ca' => 'Sympatico Mail',
        'bell.net' => 'Bell Mail',
        'telus.net' => 'Telus Mail',
        'sasktel.net' => 'SaskTel Mail',
        'att.net' => 'AT&T Mail',
        'sbcglobal.net' => 'SBC Global Mail',
        'bellsouth.net' => 'BellSouth Mail',
        'comcast.net' => 'Comcast Mail',
        'verizon.net' => 'Verizon Mail',
        'cox.net' => 'Cox Mail',
        'earthlink.net' => 'EarthLink Mail',
        'juno.com' => 'Juno Mail',
        'netzero.com' => 'NetZero Mail',
        'charter.net' => 'Charter Mail',
        'frontier.com' => 'Frontier Mail',
        'windstream.net' => 'Windstream Mail',
        'embarqmail.com' => 'Embarq Mail',
        'q.com' => 'Q Mail',
        'windstream.net' => 'Windstream Mail',
    ];
}

function get_role_accounts(): array
{
    return [
        'admin' => 'Administrator',
        'administrator' => 'Administrator',
        'info' => 'Information',
        'support' => 'Support',
        'help' => 'Help Desk',
        'helpdesk' => 'Help Desk',
        'webmaster' => 'Webmaster',
        'postmaster' => 'Postmaster',
        'abuse' => 'Abuse Report',
        'noc' => 'Network Operations',
        'security' => 'Security',
        'billing' => 'Billing',
        'sales' => 'Sales',
        'marketing' => 'Marketing',
        'hr' => 'Human Resources',
        'humanresources' => 'Human Resources',
        'legal' => 'Legal',
        'press' => 'Press/Media',
        'media' => 'Media',
        'news' => 'News',
        'editor' => 'Editor',
        'team' => 'Team',
        'staff' => 'Staff',
        'office' => 'Office',
        'contact' => 'Contact',
        'hello' => 'Hello/Greetings',
        'enquiries' => 'Enquiries',
        'inquiries' => 'Inquiries',
        'feedback' => 'Feedback',
        'no-reply' => 'No Reply',
        'noreply' => 'No Reply',
        'no-reply' => 'No Reply',
        'donotreply' => 'Do Not Reply',
        'mailer-daemon' => 'Mail Daemon (Bounce)',
        'mailerdaemon' => 'Mail Daemon (Bounce)',
        'daemon' => 'Daemon',
        'bounce' => 'Bounce Handler',
        'unsubscribe' => 'Unsubscribe',
        'subscribe' => 'Subscribe',
        'newsletter' => 'Newsletter',
        'notifications' => 'Notifications',
        'notify' => 'Notifications',
        'alert' => 'Alerts',
        'alerts' => 'Alerts',
        'notification' => 'Notification',
        'jobs' => 'Jobs/Careers',
        'careers' => 'Careers',
        'recruitment' => 'Recruitment',
        'apply' => 'Applications',
        'accounts' => 'Accounts',
        'accounting' => 'Accounting',
        'finance' => 'Finance',
        'payroll' => 'Payroll',
        'customerservice' => 'Customer Service',
        'service' => 'Service',
        'complaints' => 'Complaints',
        'returns' => 'Returns',
        'orders' => 'Orders',
        'shipping' => 'Shipping',
        'delivery' => 'Delivery',
        'dispatch' => 'Dispatch',
        'warehouse' => 'Warehouse',
        'operations' => 'Operations',
        'tech' => 'Technical',
        'technical' => 'Technical',
        'it' => 'IT Department',
        'dev' => 'Development',
        'development' => 'Development',
        'ops' => 'Operations',
        'hostmaster' => 'Host Master',
        'usenet' => 'Usenet',
        'uucp' => 'UUCP',
        'list' => 'Mailing List',
        'listserv' => 'Listserv',
        'majordomo' => 'Majordomo',
        'domain' => 'Domain Admin',
        'hostmaster' => 'Hostmaster',
        'dnsadmin' => 'DNS Admin',
        'ssladmin' => 'SSL Admin',
        'ssladministrator' => 'SSL Admin',
        'sslwebmaster' => 'SSL Webmaster',
        'root' => 'Root/Admin',
        'sysadmin' => 'System Admin',
        'system' => 'System',
        'nobody' => 'Nobody',
        'www' => 'Web',
        'www-data' => 'Web Data',
        'http' => 'HTTP',
        'ftp' => 'FTP',
        'backup' => 'Backup',
        'cron' => 'Cron',
        'squid' => 'Squid Proxy',
        'privoxy' => 'Privoxy Proxy',
    ];
}
