<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$result = null;
$inputHash = '';
$mode = 'wordlist';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    if (!rate_limit_check('tool_crack', 15, 600)) {
        friendly_error('Too many crack attempts from your IP. Try again in 10 minutes.', 429);
    }
    $inputHash = strtolower(trim((string)($_POST['hash'] ?? '')));
    if (strpos($inputHash, 'sha256:') === 0) { $inputHash = substr($inputHash, 7); }
    if (!preg_match('/^[a-f0-9]{64}$/', $inputHash)) {
        flash_set('error', 'Enter a valid SHA-256 hash (64 hex characters).');
    } else {
        $mode = in_array(($_POST['mode'] ?? 'wordlist'), ['wordlist', 'wordlist_digits', 'digits', 'letters'], true) ? (string)$_POST['mode'] : 'wordlist';
        $result = try_crack($inputHash, $mode);
        log_activity('tool_crack', $mode . ':' . ($result['found'] ? 'found' : 'miss'));
    }
}

function try_crack(string $hash, string $mode): array
{
    $t0 = microtime(true);
    $limit = 6.0;
    $attempts = 0;
    $found = null;

    $check = function (string $candidate) use (&$attempts, $hash, &$found) {
        $attempts++;
        if (hash('sha256', $candidate) === $hash) { $found = $candidate; return true; }
        return false;
    };

    $words = [
        'password','123456','12345678','123456789','1234567890','qwerty','abc123','letmein','admin','welcome','monkey','dragon','master','login','princess','football','shadow','sunshine','iloveyou','trustno1','batman','superman','pokemon','baseball','whatever','starwars','freedom','hello','hunter2','passw0rd','zaq1zaq1','qazwsx','1q2w3e4r','asdfgh','zxcvbn','000000','111111','112233','121212','123123','131313','654321','666666','696969','777777','888888','999999','aaaaaa','abcabc','abcdef','a1b2c3','1qaz2wsx','qwertyuiop','qwerty123','password1','password123','admin123','root','toor','test','test123','guest','changeme','default','killer','jordan','michelle','jennifer','ginger','pepper','soccer','hockey','ranger','buster','thomas','george','harley','ginger','love','secret','whatever','dallas','austin','andrew','matrix','computer','internet','monica','cowboy','redwings','mississippi','michael','robert','jordan','hunter','shadow1','maggie','charlie','summer','winter','spring','autumn','orange','purple','yellow','green','blue','red','silver','golden','money','million','tennis','guitar','chicago','atlanta','phoenix','denver','seattle','boston','dallas','houston','miami','orlando','tampa','angels','yankees','cubs','raiders','packers','cheese','cookie','monkey1','bigdog','panther','mustang','corvette','camaro','jackson','jasmine','ashley','bailey','bubbles','cooper','diamond','fluffy','harley1','jackson1','lucky','midnight','oliver','princess1','rocky','snoopy','sparky','tigger','whiskey','william','winston'];

    try {
        if ($mode === 'wordlist' || $mode === 'wordlist_digits') {
            foreach ($words as $w) {
                if ($check($w) || $check(strtoupper($w)) || $check(ucfirst($w))) { break; }
                if (microtime(true) - $t0 > $limit) { break; }
            }
            if ($found === null && $mode === 'wordlist_digits') {
                foreach ($words as $w) {
                    for ($d = 0; $d <= 999; $d++) {
                        if ($check($w . $d)) { break 2; }
                        if (microtime(true) - $t0 > $limit) { break 2; }
                    }
                }
            }
        } elseif ($mode === 'digits') {
            for ($len = 1; $len <= 6; $len++) {
                $max = (int)str_repeat('9', $len);
                for ($i = 0; $i <= $max; $i++) {
                    if ($check(str_pad((string)$i, $len, '0', STR_PAD_LEFT))) { break 2; }
                    if ((microtime(true) - $t0) > $limit) { break 2; }
                }
            }
        } elseif ($mode === 'letters') {
            $chars = 'abcdefghijklmnopqrstuvwxyz';
            for ($len = 1; $len <= 4; $len++) {
                $comb = $len === 1 ? $chars : '';
                if ($len === 2) {
                    for ($a = 0; $a < 26; $a++) {
                        for ($b = 0; $b < 26; $b++) {
                            if ($check($chars[$a] . $chars[$b])) { break 3; }
                            if ((microtime(true) - $t0) > $limit) { break 3; }
                        }
                    }
                } elseif ($len === 3) {
                    for ($a = 0; $a < 26; $a++) {
                        for ($b = 0; $b < 26; $b++) {
                            for ($c = 0; $c < 26; $c++) {
                                if ($check($chars[$a] . $chars[$b] . $chars[$c])) { break 4; }
                                if ((microtime(true) - $t0) > $limit) { break 4; }
                            }
                        }
                    }
                } elseif ($len === 4) {
                    for ($a = 0; $a < 26; $a++) {
                        for ($b = 0; $b < 26; $b++) {
                            for ($c = 0; $c < 26; $c++) {
                                for ($d = 0; $d < 26; $d++) {
                                    if ($check($chars[$a] . $chars[$b] . $chars[$c] . $chars[$d])) { break 5; }
                                    if ((microtime(true) - $t0) > $limit) { break 5; }
                                }
                            }
                        }
                    }
                }
            }
        }
    } catch (Throwable $t) {
        // keep going — report what we have
    }

    $elapsed = microtime(true) - $t0;
    $timedOut = $found === null && $elapsed >= $limit - 0.05;
    return [
        'found' => $found !== null,
        'value' => $found,
        'attempts' => $attempts,
        'elapsed' => $elapsed,
        'timed_out' => $timedOut,
    ];
}

page_header('SHA-256 Cracker');
?>
<div class="container" style="max-width: 760px;">
    <h1 class="h4 mb-1 reveal in-view">🎯 SHA-256 Cracker</h1>
    <p class="text-secondary mb-3 reveal in-view">Try to find the original input for a SHA-256 hash — wordlist, wordlist+digits, digits or lowercase letters. Runs on the server for 6 seconds max, then reports what it tried.</p>

    <div class="card mb-3 reveal"><div class="card-body">
        <form method="post" action="index.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="mb-3">
                <label class="form-label">SHA-256 hash</label>
                <input class="form-control" name="hash" value="<?= e($inputHash) ?>" maxlength="71" required
                    placeholder="64 hex characters" style="font-family:'JetBrains Mono',monospace;">
            </div>
            <div class="mb-3">
                <label class="form-label">Attack mode</label>
                <select class="form-select" name="mode">
                    <option value="wordlist" <?= $mode === 'wordlist' ? 'selected' : '' ?>>Common passwords (~190 words, case variants)</option>
                    <option value="wordlist_digits" <?= $mode === 'wordlist_digits' ? 'selected' : '' ?>>Common passwords + 0-999 suffix</option>
                    <option value="digits" <?= $mode === 'digits' ? 'selected' : '' ?>>Digits 1-6 chars (brute force)</option>
                    <option value="letters" <?= $mode === 'letters' ? 'selected' : '' ?>>Lowercase letters 1-4 chars (brute force)</option>
                </select>
            </div>
            <button class="btn btn-primary" type="submit">💥 Try to crack</button>
        </form>
    </div></div>

    <?php if ($result !== null): ?>
        <div class="card reveal"><div class="card-body">
            <h2 class="h6 mb-2">Result</h2>
            <?php if ($result['found']): ?>
                <div class="alert alert-success mb-2">🎉 FOUND: <code><?= e($result['value']) ?></code></div>
            <?php else: ?>
                <div class="alert alert-danger mb-2">Not found in <?= number_format($result['attempts']) ?> tries<?= $result['timed_out'] ? ' (hit the 6s time limit)' : '' ?>. Try a different mode.</div>
            <?php endif; ?>
            <div class="text-secondary small">Tried: <?= number_format($result['attempts']) ?> candidates · Time: <?= number_format($result['elapsed'], 2) ?>s</div>
            <p class="text-secondary small mb-0 mt-2">⚠️ Educational only — real SHA-256 cannot be reversed; this only works on weak, guessable inputs.</p>
        </div></div>
    <?php endif; ?>

    <div class="card mt-3 reveal"><div class="card-body">
        <h2 class="h6 mb-2">Quick test hashes</h2>
        <p class="text-secondary small mb-0">Try these (click to fill):
            <button type="button" class="btn btn-sm btn-outline-light ms-1" data-hash="5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8">"password"</button>
            <button type="button" class="btn btn-sm btn-outline-light ms-1" data-hash="ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f">"hello"</button>
            <button type="button" class="btn btn-sm btn-outline-light ms-1" data-hash="a665a45920422f9d417e4867efdc4fb8a04a1f3fff1fa07e998e86f7f7a27ae3">"123"</button>
        </p>
    </div></div>
</div>
<script>
document.querySelectorAll('[data-hash]').forEach(function (b) {
    b.addEventListener('click', function () {
        document.querySelector('input[name="hash"]').value = b.getAttribute('data-hash');
    });
});
</script>
<?php page_footer(); ?>