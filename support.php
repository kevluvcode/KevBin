<?php
require_once __DIR__ . '/functions.php';

start_session();
$cfg = $GLOBALS['CFG'];
$btc = (string)($cfg['bitcoin_address'] ?? '');
$contact = (string)($cfg['donation_contact'] ?? '');
$tiers = premium_tiers();
$me = current_user();

// ——— Claim a BTC payment by TXID ———
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    if ($me === null) {
        flash_set('error', 'Please log in to claim a payment.');
        redirect('login.php');
    }
    $txid = trim((string)($_POST['txid'] ?? ''));
    $plan = (string)($_POST['plan'] ?? '');
    if ($txid === '') {
        flash_set('error', 'Enter the transaction ID (TXID).');
    } else {
        $res = claim_btc_payment((int)$me['id'], (string)$me['username'], $txid, $plan);
        flash_set($res['ok'] ? 'success' : 'error', $res['msg']);
    }
    redirect('support.php');
}

$myTier = premium_tier($me);
$myExpires = $me !== null ? (string)($me['premium_expires_at'] ?? '') : '';
$myClaims = [];
if ($me !== null) {
    try {
        $stmt = db()->prepare('SELECT txid, plan, amount_sats, status, created_at, verified_at FROM premium_payments WHERE user_id = ? ORDER BY created_at DESC LIMIT 20');
        $stmt->execute([(int)$me['id']]);
        $myClaims = $stmt->fetchAll();
    } catch (Throwable $t) {
        $myClaims = [];
    }
}

$statusBadge = [
    'pending'  => '<span class="badge bg-warning text-dark">PENDING</span>',
    'detected' => '<span class="badge bg-info text-dark">DETECTED</span>',
    'verified' => '<span class="badge bg-success">VERIFIED</span>',
    'rejected' => '<span class="badge bg-danger">REJECTED</span>',
];

page_header('Support KevBin');
?>
<div class="container" style="max-width: 1050px;">
    <div class="text-center mb-5">
        <h1 class="h3 mb-2">Support KevBin</h1>
        <p class="text-secondary mb-0">KevBin is free, ad-free and untraceable. If you like it, a small Bitcoin donation keeps the servers running — or grab a Supporter / Pro / Lifetime plan for perks and a badge. Payments are verified automatically by the block explorer.</p>
    </div>

    <?php if ($me !== null): ?>
    <div class="card mb-4">
        <div class="card-body d-flex align-items-center gap-2 flex-wrap">
            <?php if ($myTier !== ''): ?>
                <?= premium_badge($me) ?>
                <div>
                    <strong>You are supporting KevBin.</strong>
                    <?php if ($myTier === 'lifetime' || $myExpires === ''): ?>
                        <span class="text-secondary small">Lifetime plan. Thank you!</span>
                    <?php else: ?>
                        <span class="text-secondary small">Plan: <strong><?= e($tiers[$myTier]['name'] ?? ucfirst($myTier)) ?></strong> · active until <?= e(gmdate('Y-m-d', strtotime($myExpires . ' UTC'))) ?> UTC.</span>
                    <?php endif; ?>
                    <div class="text-secondary small">Renew anytime — a new verified payment simply extends your current expiry.</div>
                </div>
            <?php else: ?>
                <span class="text-secondary">You are not a supporter yet. Pick a plan below.</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4 mb-5">
        <div class="col-12 col-md-6">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h2 class="h5 mb-1">One-time donation</h2>
                    <p class="text-secondary small">Any amount. No account needed.</p>
                    <div class="mb-2">
                        <span class="fs-2">&#127940;</span>
                    </div>
                    <div class="input-group mb-2">
                        <input id="btc-addr" class="form-control text-center" readonly value="<?= e($btc) ?>" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;user-select:all;">
                        <button type="button" class="btn btn-outline-light" onclick="copyBtc()">Copy</button>
                    </div>
                    <div class="text-secondary small">Pay in BTC to the address above.</div>
                    <?php if ($contact !== ''): ?>
                        <div class="text-secondary small mt-2">Questions? Contact: <?= e($contact) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">What your support pays for</h2>
                    <ul class="mb-0 text-secondary" style="list-style:none;padding-left:0;">
                        <li class="mb-2">&#8226; Hosting for kevbin.ct.ws</li>
                        <li class="mb-2">&#8226; The Cloudflare Worker bridge (Discord login)</li>
                        <li class="mb-2">&#8226; New tools, features and maintenance</li>
                        <li class="mb-0">&#8226; Keeping everything free, ad-free and private</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <h2 class="h4 mb-3">Plans</h2>
    <div class="row g-4 mb-4">
        <div class="col-12 col-md-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h3 class="h5">Free</h3>
                    <div class="fs-4 mb-3">$0</div>
                    <ul class="text-secondary small mb-3" style="list-style:none;padding-left:0;">
                        <li class="mb-2">&#10003; Unlimited pastes</li>
                        <li class="mb-2">&#10003; Up to 100,000 chars / paste</li>
                        <li class="mb-2">&#10003; File uploads up to 9 MB</li>
                        <li class="mb-2">&#10003; Anonymous posting</li>
                        <li class="mb-0">&#10007; Premium badge</li>
                    </ul>
                </div>
            </div>
        </div>

        <?php
        $cardOrder = ['supporter', 'pro', 'lifetime'];
        $cardAccent = ['supporter' => 'border-warning', 'pro' => 'border-info', 'lifetime' => 'border-success'];
        $cardBtn = ['supporter' => 'btn-warning', 'pro' => 'btn-info', 'lifetime' => 'btn-success'];
        foreach ($cardOrder as $tkey):
            $t = $tiers[$tkey] ?? null;
            if ($t === null) continue;
            $lifetime = (int)($t['days'] ?? 30) <= 0;
            $chars = (int)($t['chars'] ?? 500000);
            $isMine = $myTier === $tkey;
            $isUpgrade = $myTier !== '' && $myTier !== $tkey && (tier_rank($tkey) > tier_rank($myTier) || $myTier === 'supporter');
        ?>
        <div class="col-12 col-md-3">
            <div class="card h-100 <?= e($cardAccent[$tkey] ?? '') ?>">
                <div class="card-body text-center d-flex flex-column">
                    <h3 class="h5"><?= e($t['name']) ?></h3>
                    <div class="fs-4 mb-1">$<?= (int)$t['price_usd'] ?></div>
                    <div class="text-secondary small mb-3"><?= $lifetime ? 'one-time, in BTC' : 'per month, in BTC' ?></div>
                    <ul class="text-secondary small mb-3" style="list-style:none;padding-left:0;">
                        <li class="mb-2">&#10003; <strong>&#9733; <?= e($t['badge']) ?></strong> badge</li>
                        <li class="mb-2">&#10003; Up to <?= number_format($chars) ?> chars / paste</li>
                        <li class="mb-2">&#10003; File uploads up to 9 MB</li>
                        <li class="mb-2">&#10003; 3&times; higher upload limits</li>
                        <?php if ($lifetime): ?>
                            <li class="mb-0">&#10003; No renewal, ever</li>
                        <?php else: ?>
                            <li class="mb-0">&#10003; Auto-renews by verified payment</li>
                        <?php endif; ?>
                    </ul>
                    <?php if ($isMine): ?>
                        <span class="badge bg-secondary mt-auto">Your current plan</span>
                    <?php elseif ($me !== null): ?>
                        <form method="post" action="support.php" class="mt-auto">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="plan" value="<?= e($tkey) ?>">
                            <a class="btn <?= e($cardBtn[$tkey] ?? 'btn-outline-light') ?> w-100 mb-2" href="#btc-addr" onclick="document.getElementById('btc-addr').select()">Pay with Bitcoin</a>
                            <div class="input-group input-group-sm">
                                <input class="form-control" type="text" name="txid" required pattern="[A-Fa-f0-9]{64}" title="64-character transaction ID"
                                       placeholder="Paste TXID after paying" style="font-family:'JetBrains Mono',monospace;font-size:.8rem;">
                                <button class="btn btn-sm btn-outline-light" type="submit">I paid</button>
                            </div>
                            <div class="form-text">Auto-verified, no admin needed.</div>
                        </form>
                    <?php else: ?>
                        <div class="mt-auto">
                            <a class="btn <?= e($cardBtn[$tkey] ?? 'btn-outline-light') ?> w-100 mb-2" href="#btc-addr" onclick="document.getElementById('btc-addr').select()">Pay with Bitcoin</a>
                            <a class="btn btn-outline-light w-100 btn-sm" href="login.php">Log in to claim</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-2">How to pay with Bitcoin</h2>
            <ol class="text-secondary small mb-0" style="padding-left:1.2rem;">
                <li class="mb-1">Send the plan amount (in BTC) to: <code style="font-family:'JetBrains Mono',monospace;"><?= e($btc) ?></code></li>
                <li class="mb-1">Copy the transaction ID (TXID) from your wallet (usually under “Transaction details”).</li>
                <li class="mb-1">Log in, click “Pay with Bitcoin” on the plan, paste the TXID and click “I paid”.</li>
                <li class="mb-1">Your plan activates automatically once the transaction confirms — usually within minutes. No admin needed.</li>
                <li class="mb-0">Check Settings → Supporter/Premium to see your status and expiry.</li>
            </ol>
        </div>
    </div>

    <?php if ($me !== null && count($myClaims) > 0): ?>
    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-2">My payment claims</h2>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr>
                        <th>Status</th><th>Plan</th><th>TXID</th><th>Sats</th><th>Claimed</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($myClaims as $c): ?>
                            <tr>
                                <td><?= $statusBadge[$c['status']] ?? '<span class="badge bg-secondary">' . e($c['status']) . '</span>' ?></td>
                                <td><?= e($tiers[$c['plan']]['name'] ?? (ucfirst((string)$c['plan']) ?: '—')) ?></td>
                                <td><code class="small"><?= e(substr((string)$c['txid'], 0, 20)) ?>…</code></td>
                                <td class="small"><?= $c['amount_sats'] !== null ? number_format((int)$c['amount_sats']) : '—' ?></td>
                                <td class="small text-secondary"><?= e(substr((string)$c['created_at'], 0, 16)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<script>
function copyBtc() {
    var el = document.getElementById('btc-addr');
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(el.value).then(function () {
            el.focus();
            var b = el.nextElementSibling;
            b.textContent = 'Copied!';
            setTimeout(function () { b.textContent = 'Copy'; }, 1500);
        });
    } else {
        el.focus(); el.select();
        try { document.execCommand('copy'); } catch (e) {}
    }
}
</script>
<?php page_footer(); ?>
