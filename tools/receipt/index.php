<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free realistic receipt generator. 17 store templates with correct logos (Walmart, Target, Costco, Kroger, Aldi and more), custom logo upload and itemized taxes, payment and barcode. Export as PNG in your browser.',
    'keywords' => 'receipt generator, walmart receipt, fake receipt, grocery receipt, receipt maker, receipt mockup, shopping receipt, store receipt',
];
page_header('Shopping Receipt Generator — Realistic Store Receipt Maker');
?>
<style>
.rc-paper-wrap{display:flex;justify-content:center;padding:1rem 0;background:repeating-linear-gradient(45deg,rgba(255,255,255,.02) 0 2px,transparent 2px 6px),var(--bs-body-bg,#141420);border-radius:14px;border:1px solid var(--line,#2a2a3e);overflow:hidden;}
.rc-paper{width:320px;max-width:100%;background:linear-gradient(180deg,#fffef9,#fbf6ecc7 55%,#f6efe0 100%);color:#17181a;font-family:'Courier New',Courier,monospace;position:relative;box-shadow:0 12px 30px rgba(0,0,0,.55),0 2px 6px rgba(0,0,0,.4);padding:18px 14px 30px;font-size:12px;line-height:1.5;transform:rotate(-.6deg);}
.rc-paper.lion{background:linear-gradient(180deg,#fffdf5,#f8f4e8);}
.rc-paper .rc-grain{position:absolute;inset:0;pointer-events:none;opacity:.5;background:repeating-linear-gradient(0deg,rgba(90,80,60,.05) 0 1px,transparent 1px 3px);mix-blend-mode:multiply;}
.rc-top-jag{position:absolute;top:-8px;left:0;right:0;height:9px;background:
  linear-gradient(-45deg,transparent 0 6px,#fdfdf8 6px 12px) 0 0/12px 12px repeat-x,
  linear-gradient(45deg,transparent 0 6px,#fdfdf8 6px 12px) 0 0/12px 12px repeat-x;
  filter:drop-shadow(0 0 1px rgba(0,0,0,.15));}
.rc-bot-jag{position:absolute;bottom:-9px;left:0;right:0;height:9px;background:
  linear-gradient(-45deg,transparent 0 6px,#fdfdf8 6px 12px) 0 0/12px 12px repeat-x,
  linear-gradient(45deg,transparent 0 6px,#fdfdf8 6px 12px) 0 0/12px 12px repeat-x;}
.rc-logo{text-align:center;margin:0 0 4px;}
.rc-logo svg{max-width:220px;height:auto;}
.rc-logo img{max-width:220px;max-height:64px;margin:0 auto;}
.rc-store{font-size:21px;font-weight:700;letter-spacing:1px;color:#0421a8;text-align:center;font-family:Arial,Helvetica,sans-serif;text-transform:uppercase;line-height:1.1;}
.rc-slogan{font-size:8px;color:#6a6a6a;text-align:center;letter-spacing:1px;margin-top:2px;font-family:Arial,Helvetica,sans-serif;text-transform:uppercase;}
.rc-meta{text-align:center;font-size:10.5px;color:#333;margin-top:6px;line-height:1.5;}
.rc-hr{border-top:1px dashed #999;margin:8px 0;}
.rc-hr-s{border-top:1px solid #bbb;margin:6px 0;}
.rc-row{display:flex;justify-content:space-between;white-space:nowrap;}
.rc-row .l{flex:0 0 auto;max-width:62%;overflow:hidden;text-overflow:ellipsis;}
.rc-row .r{flex:0 0 auto;margin-left:8px;}
.rc-row .c{width:100%;text-align:center;}
.rc-qty{color:#8a8a8a;}
.rc-total{font-weight:700;font-size:13.5px;}
.rc-big{font-weight:700;font-size:14px;text-align:center;margin:4px 0;}
.rc-save{color:#007333;font-weight:700;}
.rc-promo{text-align:center;font-size:8px;color:#444;margin:3px 0;line-height:1.5;}
.rc-thanks{text-align:center;font-size:11px;font-weight:700;margin-top:10px;}
.rc-barcode{display:flex;justify-content:center;margin:8px 0 2px;height:42px;align-items:stretch;}
.rc-barcode span{display:block;background:#17181a;margin:0 .6px;}
.rc-barnum{text-align:center;font-size:9.5px;letter-spacing:3px;color:#333;}
.rc-side{position:absolute;top:0;bottom:0;left:8px;width:1px;background:linear-gradient(180deg,transparent,rgba(180,120,40,.18) 12%,rgba(180,120,40,.18) 88%,transparent);}
.rc-side.right{left:auto;right:8px;}
.rc-tear{display:flex;justify-content:space-between;align-items:center;color:#8d8980;font-size:9px;letter-spacing:2px;margin:9px 0 2px;text-transform:uppercase;}
.rc-tear .dash{flex:1;border-top:1px dashed #b9b4a6;margin:0 7px;}
.rc-fade{position:absolute;left:0;right:0;bottom:0;height:70px;pointer-events:none;background:linear-gradient(180deg,rgba(190,160,110,0) 8%,rgba(160,128,75,.14) 55%,rgba(120,95,55,.3));mix-blend-mode:multiply;}
.rc-punch{position:absolute;top:6px;bottom:6px;width:10px;pointer-events:none;background:repeating-linear-gradient(90deg,transparent 0 13px,var(--rc-perf,#e6dfcb) 13px 15px);opacity:.85;}
.rc-punch.l{left:-6px;} .rc-punch.r{right:-6px;}
.rc-headline{margin-top:2px;font-size:7.5px;color:#9a958a;text-align:center;letter-spacing:2px;}
.receipt-controls .form-label{font-size:.8rem;color:#94a3b8;margin-bottom:.15rem;}
.receipt-controls .item-row{display:grid;grid-template-columns:1fr 74px 64px 28px;gap:.3rem;margin-bottom:.35rem;}
.receipt-controls .item-row input{font-size:.8rem;padding:.3rem .5rem;}
.itm-preset{display:flex;flex-wrap:wrap;gap:.3rem;margin-bottom:.5rem;}
.itm-preset .btn{font-size:.72rem;padding:.15rem .5rem;}
</style>

<div class="container" style="max-width:1040px;">
    <h1 class="h4 mb-2 reveal in-view">Shopping Receipt Generator</h1>
    <p class="text-secondary mb-1 reveal in-view">Create a realistic <strong>store receipt</strong> with 17 built-in brand templates and their correct logos — plus the option to upload any custom logo. Itemized lines, quantities, coupons, taxes, payment card and a scannable barcode. Everything runs in your browser — nothing is uploaded.</p>
    <p class="text-secondary mb-4 reveal in-view">Click an item preset to add grocery items with believable prices, edit any line, add a promo/survey footer, then download your receipt as a PNG image.</p>

    <div class="row g-4">
        <div class="col-lg-5 reveal in-view">
            <div class="card h-100 receipt-controls"><div class="card-body">

                <div class="d-flex align-items-center justify-content-between mb-2">
                    <label class="form-label mb-0">Store Template</label>
                    <select id="rc-preset" class="form-select form-select-sm" style="max-width:190px;" onchange="applyPreset(this.value)">
                        <option value="walmart">Walmart</option>
                        <option value="target">Target</option>
                        <option value="costco">Costco</option>
                        <option value="kroger">Kroger</option>
                        <option value="aldi">Aldi</option>
                        <option value="wholefoods">Whole Foods</option>
                        <option value="traderjoes">Trader Joe's</option>
                        <option value="publix">Publix</option>
                        <option value="wawa">Wawa</option>
                        <option value="cvs">CVS</option>
                        <option value="walgreens">Walgreens</option>
                        <option value="heb">H-E-B</option>
                        <option value="meijer">Meijer</option>
                        <option value="safeway">Safeway</option>
                        <option value="dollargeneral">Dollar General</option>
                        <option value="dollartree">Dollar Tree</option>
                        <option value="generic">Generic Grocery</option>
                    </select>
                </div>

                <div class="mb-2">
                    <label class="form-label">Logo</label>
                    <div class="d-flex gap-2 flex-wrap align-items-center">
                        <select id="rc-logo-mode" class="form-select form-select-sm" style="max-width:150px;" onchange="renderReceipt()">
                            <option value="preset">Preset logo</option>
                            <option value="custom">Custom image</option>
                            <option value="none">Text only</option>
                        </select>
                        <label class="btn btn-outline-light btn-sm mb-0" style="font-size:.75rem;">Upload logo
                            <input type="file" id="rc-logo-file" accept="image/*" style="display:none;" onchange="onLogoFile(event)">
                        </label>
                        <button class="btn btn-outline-light btn-sm" style="font-size:.75rem;" onclick="clearLogo()">Remove custom</button>
                    </div>
                    <div class="form-text small" id="rc-logo-status">Using built-in template logo.</div>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-12">
                        <label class="form-label">Store Name</label>
                        <input id="rc-store" class="form-control form-control-sm" value="WALMART SUPERCENTER" oninput="renderReceipt()">
                    </div>
                    <div class="col-8">
                        <label class="form-label">Address</label>
                        <input id="rc-addr" class="form-control form-control-sm" value="1001 W CHESTNUT ST" oninput="renderReceipt()">
                    </div>
                    <div class="col-4">
                        <label class="form-label">ZIP</label>
                        <input id="rc-zip" class="form-control form-control-sm" value="42003" oninput="renderReceipt()">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Phone</label>
                        <input id="rc-phone" class="form-control form-control-sm" value="(270) 442-4611" oninput="renderReceipt()">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Store #</label>
                        <input id="rc-storeno" class="form-control form-control-sm" value="STORE #4272" oninput="renderReceipt()">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Cashier</label>
                        <input id="rc-cashier" class="form-control form-control-sm" placeholder="e.g. #JESSICA" oninput="renderReceipt()">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Coupon Savings ($)</label>
                        <input id="rc-coupon" type="number" class="form-control form-control-sm" value="0" min="0" step="0.01" oninput="renderReceipt()">
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label">Add Grocery Item</label>
                    <div class="itm-preset" id="rc-presets"></div>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-1">
                    <label class="form-label mb-0">Items</label>
                    <button class="btn btn-outline-light btn-sm" style="font-size:.75rem;" onclick="addItem()">+ Add line</button>
                </div>
                <div id="rc-items" class="mb-2"></div>

                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label">Tax Rate (%)</label>
                        <input id="rc-tax" type="number" class="form-control form-control-sm" value="0" min="0" max="25" step="0.1" oninput="renderReceipt()">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Discount ($)</label>
                        <input id="rc-disc" type="number" class="form-control form-control-sm" value="0" min="0" step="0.01" oninput="renderReceipt()">
                    </div>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="rc-promo" onchange="renderReceipt()">
                            <label class="form-check-label small" for="rc-promo">Show promo / survey footer (adds realism)</label>
                        </div>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label">Card Type</label>
                        <select id="rc-card" class="form-select form-select-sm" onchange="renderReceipt()">
                            <option value="VISA">VISA</option>
                            <option value="MASTERCARD">Mastercard</option>
                            <option value="AMEX">AMEX</option>
                            <option value="CASH">Cash</option>
                            <option value="WALMART PAY">Walmart Pay</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Card Last 4</label>
                        <input id="rc-card4" class="form-control form-control-sm" maxlength="4" value="4821" oninput="renderReceipt()">
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-primary btn-sm" onclick="downloadPng()">&#11015; Download PNG</button>
                    <button class="btn btn-outline-light btn-sm" onclick="randomizeItems()">&#127922; Random cart</button>
                    <button class="btn btn-outline-light btn-sm" onclick="copyReceiptText()">Copy text</button>
                </div>
            </div></div>
        </div>

        <div class="col-lg-7 reveal in-view">
            <div class="card h-100"><div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h2 class="h6 mb-0">Receipt Preview</h2>
                </div>
                <div class="rc-paper-wrap">
                    <div class="rc-paper lion" id="rc-paper">
                        <div class="rc-grain"></div>
                        <div class="rc-side"></div><div class="rc-side right"></div>
                        <div id="rc-content"></div>
                    </div>
                </div>
                <p class="text-secondary small mt-3 mb-0">Unconstrained fake-receipt generator — rendered locally for mockups, demos and memes. Logos are simplified vector recreations for realism; totals don't require exact arithmetic.</p>
            </div></div>
        </div>
    </div>
</div>

<script>
(function(){
    var $ = function(id){ return document.getElementById(id); };

    var PRESETS = [
        {n:'Milk 1G 2%', q:'1', u:'ea', p:'4.28'},
        {n:'Bananas', q:'1.2', u:'lb', p:'1.27'},
        {n:'Wheat Bread', q:'1', u:'ea', p:'2.94'},
        {n:'Eggs 12pk', q:'1', u:'ea', p:'3.86'},
        {n:'Chicken Thighs', q:'1.5', u:'lb', p:'5.42'},
        {n:'Shredded Cheese', q:'1', u:'ea', p:'4.06'},
        {n:'Apples', q:'2', u:'lb', p:'3.79'},
        {n:'Cereal 18oz', q:'1', u:'ea', p:'5.48'},
        {n:'Chips', q:'1', u:'ea', p:'4.67'},
        {n:'Pasta 16oz', q:'2', u:'ea', p:'1.24'},
        {n:'Ground Beef 93', q:'1.3', u:'lb', p:'7.19'},
        {n:'Ice Cream', q:'1', u:'ea', p:'3.64'},
        {n:'Spinach', q:'1', u:'ea', p:'3.98'},
        {n:'Gatorade 32oz', q:'2', u:'ea', p:'2.58'},
        {n:'Detergent', q:'1', u:'ea', p:'9.94'},
        {n:'Toilet Paper 12pk', q:'1', u:'ea', p:'8.47'}
    ];

    var SPARK = '<path fill="#FFC220" d="M210 4 c1.6 4 3.4 6 6 8 -2.6 2 -4.4 4 -6 8 -1.6 -4 -3.4 -6 -6 -8 2.6 -2 4.4 -4 6 -8z"/>';
    var RAYS = '<g fill="#F2591E">' +
        '<polygon points="70,6 74,14 82,15 75,20 77,28 70,23 63,28 65,20 58,15 66,14"/>' +
        '<polygon points="96,4 99,11 106,12 100,16 102,23 96,19 90,23 92,16 86,12 93,11"/>' +
        '</g>';

    var PRESETS_META = {
        walmart: {store:'WALMART SUPERCENTER', slogan:'Save money. Live better.', addr:'2820 KENNEDY DR', zip:'14304', phone:'(716) 297-0474', storeno:'STORE #819', color:'#0071CE', accent:'#FFC220', promo:'Order again at walmart.com/grocery\nDeliveries & pickup available daily',
            logo:'<svg xmlns="http://www.w3.org/2000/svg" width="250" height="40" viewBox="0 0 250 40"><text x="0" y="28" font-family="Arial,Helvetica,sans-serif" font-weight="700" font-size="30" fill="#0071CE">Walmart</text><g transform="translate(165,17)" fill="#FFC220"><polygon points="0,-10 1.8,-1.8 10,0 1.8,1.8 0,10 -1.8,1.8 -10,0 -1.8,-1.8"/><polygon points="0,-6 1.1,-1.1 6,0 1.1,1.1 0,6 -1.1,1.1 -6,0 -1.1,-1.1"/></g></svg>'},
        target: {store:'TARGET', slogan:'Expect More. Pay Less.', addr:'1000 NGS MARKET', zip:'78660', phone:'(512) 255-0424', storeno:'T-0391', color:'#E31937', accent:'#E31937', promo:'Connect your Target account at target.com\nTrack orders & reorder in the app',
            logo:'<svg xmlns="http://www.w3.org/2000/svg" width="76" height="40" viewBox="0 0 76 40"><circle cx="38" cy="20" r="19" fill="#E31937"/><circle cx="38" cy="20" r="8" fill="#fff"/></svg>'},
        costco: {store:'COSTCO WHOLESALE', slogan:'Costco Wholesale', addr:'820 N SCHROEDER ST', zip:'98686', phone:'(360) 574-4231', storeno:'WHSE #469', color:'#d40e14', accent:'#005c9e', promo:'Membership savings applied automatically\nTell us how we did at costco.com/survey',
            logo:'<svg xmlns="http://www.w3.org/2000/svg" width="250" height="32" viewBox="0 0 250 32"><text x="0" y="24" font-family="Georgia" font-weight="800" font-size="24" font-style="italic" fill="#d40e14" letter-spacing="2">COSTCO</text><rect x="0" y="28" width="96" height="3" fill="#005c9e"/><text x="104" y="18" font-family="Arial" font-weight="700" font-size="9" fill="#005c9e" letter-spacing="1">WHOLESALE</text></svg>'},
        kroger: {store:'KROGER', slogan:'Fresh for Everyone', addr:'7330 NW BARBUR BLVD', zip:'97219', phone:'(503) 244-2832', storeno:'STORE #0469', color:'#d0153a', accent:'#0a66c2', promo:'Kroger Plus Card savings loaded digitally\nCall 1-800-KROGERS to share feedback',
            logo:'<svg xmlns="http://www.w3.org/2000/svg" width="150" height="36" viewBox="0 0 150 36"><rect x="0" y="4" width="126" height="28" rx="14" fill="#d0153a"/><text x="63" y="25" font-family="Arial" font-weight="700" font-size="17" fill="#fff" text-anchor="middle">Kroger</text><circle cx="140" cy="18" r="7" fill="#0a66c2"/></svg>'},
        aldi: {store:'ALDI', slogan:'Genuine Quality. Feel the Difference.', addr:'1500 S MESA HILLS DR', zip:'85254', phone:'(602) 923-9100', storeno:'STORE #108', color:'#053a79', accent:'#e84b0b', promo:'Everyday low prices — guaranteed\nPrices may vary by location',
            logo:'<svg xmlns="http://www.w3.org/2000/svg" width="150" height="34" viewBox="0 0 150 34"><text x="0" y="24" font-family="Arial" font-weight="900" font-size="24" fill="#053a79" letter-spacing="3">ALDI</text><rect x="108" y="12" width="34" height="6" fill="#e84b0b"/><circle cx="125" cy="25" r="5" fill="#e84b0b"/></svg>'},
        wholefoods: {store:'WHOLE FOODS MARKET', slogan:'Whole Foods Market', addr:'625 W NEWPORT BLVD', zip:'92663', phone:'(949) 650-6757', storeno:'STORE #10162', color:'#00463f', accent:'#e67700', promo:'Amazon Prime members save 10% on select items daily\nUse the Whole Foods app for rewards',
            logo:'<svg xmlns="http://www.w3.org/2000/svg" width="180" height="38" viewBox="0 0 180 38"><path fill="#e67700" d="M14 26 c0 -9 7 -12 14 -12 7 0 13 1 13 9 0 3 -2 5 -4 4 -4 6 -12 8 -17 5 -2 3 -6 2 -6 -6z"/><text x="34" y="24" font-family="Arial" font-weight="800" font-size="15" fill="#00463f" letter-spacing="1">WHOLE FOODS</text><text x="34" y="35" font-family="Arial" font-weight="700" font-size="9" fill="#00463f" letter-spacing="2">MARKET</text></svg>'},
        traderjoes: {store:'TRADER JOE&rsquo;S', slogan:'Everything we sell is worth knowing about', addr:'200 SANTA ANA CT', zip:'91101', phone:'(626) 795-2611', storeno:'STORE #018', color:'#00A6A0', accent:'#F2591E', promo:'We welcome all food-lovers &amp; kids of all ages\nNo phone orders — we\'re busy tasting!',
            logo:'<svg xmlns="http://www.w3.org/2000/svg" width="180" height="44" viewBox="0 0 180 44">' + RAYS + '<text x="18" y="32" font-family="Arial" font-weight="900" font-size="21" fill="#00A6A0">TRADER JOE&#39;S</text></svg>'},
        publix: {store:'PUBLIX', slogan:'Where shopping is a pleasure', addr:'15000 N DALE MABRY HWY', zip:'33618', phone:'(813) 264-0476', storeno:'STORE #0965', color:'#007333', accent:'#007333', promo:'Thank you for shopping Publix\nOrder ahead at publix.com -- no fees, ever',
            logo:'<svg xmlns="http://www.w3.org/2000/svg" width="150" height="40" viewBox="0 0 150 40"><text x="0" y="30" font-family="Georgia" font-weight="700" font-size="30" font-style="italic" fill="#007333">Publix</text></svg>'},
        wawa: {store:'WAWA', slogan:'Your Convenience Store', addr:'7821 US HWY 30', zip:'17402', phone:'(717) 764-0077', storeno:'STORE #8317', color:'#274a78', accent:'#D92128', promo:'Order ahead in the Wawa app &amp; skip the line\nCoffee rewards earned on every cup',
            logo:'<svg xmlns="http://www.w3.org/2000/svg" width="170" height="42" viewBox="0 0 170 42"><rect x="4" y="2" width="108" height="38" rx="8" fill="#274a78"/><g fill="#FFD100"><path d="M20 8 c7 2 11 8 8 20 -2 -6 -1 -10 2 -13 3 -4 8 -5 12 -3 2 1 3 3 3 6 0 5 -4 10 -9 13 -7 4 -16 2 -20 -5 -7 -2 -8 -8 -1 -13 3 -2 5 -3 5 -5z"/><path d="M30 18 c6 -1 10 2 10 8 0 4 -3 8 -8 10 -4 2 -8 1 -8 -4 4 2 7 1 9 -2 2 -3 1 -8 -3 -12z"/></g><text x="120" y="28" font-family="Arial,Helvetica,sans-serif" font-weight="800" font-size="22" fill="#D92128">Wawa</text></svg>'},
        cvs: {store:'CVS/PHARMACY', slogan:'CVS Health', addr:'500 MAIN STREET', zip:'10001', phone:'(212) 555-0143', storeno:'STORE #9999', color:'#cc0000', accent:'#cc0000', promo:'ExtraCare rewards loaded to your account\nRefill &amp; shop again at cvs.com',
            logo:'<svg xmlns="http://www.w3.org/2000/svg" width="180" height="36" viewBox="0 0 180 36"><text x="0" y="26" font-family="Arial" font-weight="900" font-size="28" fill="#cc0000">CVS</text><path fill="#cc0000" d="M72 8 c3 -3 8 0 8 5 0 4 -5 8 -8 10 -3 -2 -8 -6 -8 -10 0 -5 5 -8 8 -5z"/><text x="88" y="24" font-family="Arial" font-weight="700" font-size="16" fill="#cc0000">pharmacy</text></svg>'},
        walgreens: {store:'WALGREENS', slogan:'Trusted since 1901', addr:'800 NEW CENTER DR', zip:'48280', phone:'(313) 555-0198', storeno:'STORE #12197', color:'#0057a3', accent:'#009d3e', promo:'myWalgreens rewards loaded\nPickup &amp; delivery at walgreens.com',
            logo:'<svg xmlns="http://www.w3.org/2000/svg" width="170" height="36" viewBox="0 0 170 36"><text x="0" y="27" font-family="Georgia" font-weight="700" font-size="26" font-style="italic" fill="#0057a3">Walgreens</text></svg>'},
        heb: {store:'H-E-B', slogan:'Here Everything\u2019s Better', addr:'646 S FLORES ST', zip:'78204', phone:'(210) 225-0541', storeno:'STORE #0064', color:'#e31837', accent:'#e31837', promo:'Committed to retail simplicity\n112 years of Texas pride',
            logo:'<svg xmlns="http://www.w3.org/2000/svg" width="120" height="46" viewBox="0 0 120 46"><circle cx="60" cy="24" r="21" fill="#e31837"/><path fill="#fff" d="M60 6 l2.4 4.8 5.2 .7 -3.8 3.7 .9 5.3 -4.7 -2.5 -4.7 2.5 .9 -5.3 -3.8 -3.7 5.2 -.7z"/><text x="60" y="31" font-family="Arial" font-weight="900" font-size="17" fill="#fff" text-anchor="middle">H-E-B</text></svg>'},
        meijer: {store:'MEIJER', slogan:'Fresh for everyone', addr:'12350 PLAZA DR', zip:'44333', phone:'(330) 668-2244', storeno:'STORE #127', color:'#e4002b', accent:'#f58220', promo:'mPerks offers applied automatically\nOrder pickup &amp; delivery at meijer.com',
            logo:'<svg xmlns="http://www.w3.org/2000/svg" width="140" height="42" viewBox="0 0 140 42"><g fill="#f58220"><polygon points="70,4 72,10 78,11 73,15 74,21 70,17 66,21 67,15 62,11 68,10"/><polygon points="88,2 90,7 95,8 91,12 92,17 88,14 84,17 85,12 81,8 86,7"/></g><text x="24" y="32" font-family="Arial" font-weight="900" font-size="26" fill="#e4002b">meijer</text></svg>'},
        safeway: {store:'SAFEWAY', slogan:'Ingredients for Life', addr:'9790 N STATE ST', zip:'84075', phone:'(801) 774-6350', storeno:'STORE #1932', color:'#d0072f', accent:'#d0072f', promo:'Safeway for U rewards loaded\nAsk us about delivery today',
            logo:'<svg xmlns="http://www.w3.org/2000/svg" width="160" height="38" viewBox="0 0 160 38"><path fill="#d0072f" d="M6 6 h88 l-3 5 h-82 z"/><text x="6" y="26" font-family="Arial" font-weight="900" font-size="22" fill="#d0072f">SAFEWAY</text></svg>'},
        dollargeneral: {store:'DOLLAR GENERAL', slogan:'Save time. Save money.', addr:'12268 NW RD', zip:'73034', phone:'(405) 555-0177', storeno:'STORE #20083', color:'#000', accent:'#fdb717', promo:'DG Digital Coupons -- clip them in the app\nGreat prices. Good people.',
            logo:'<svg xmlns="http://www.w3.org/2000/svg" width="150" height="42" viewBox="0 0 150 42"><rect x="0" y="0" width="52" height="42" rx="8" fill="#fdb717"/><text x="26" y="29" font-family="Arial" font-weight="900" font-size="22" fill="#000" text-anchor="middle">DG</text><text x="60" y="18" font-family="Arial" font-weight="800" font-size="11" fill="#000">Dollar General</text><text x="60" y="31" font-family="Arial" font-weight="400" font-size="9" fill="#444">Save time. Save money.</text></svg>'},
        dollartree: {store:'DOLLAR TREE', slogan:'The. Tree. That. Rocks!', addr:'500 BURKE CENTRE PKWY', zip:'22015', phone:'(703) 555-0122', storeno:'STORE #2860', color:'#00a651', accent:'#00843d', promo:'Dollar Tree Plus! rewards loaded\nEverything $1.25 &amp; under, every day',
            logo:'<svg xmlns="http://www.w3.org/2000/svg" width="150" height="40" viewBox="0 0 150 40"><circle cx="26" cy="20" r="20" fill="#00843d"/><text x="26" y="26" font-family="Arial" font-weight="900" font-size="17" fill="#fff" text-anchor="middle">$</text><text x="52" y="25" font-family="Arial" font-weight="800" font-size="17" fill="#00a651">Dollar Tree</text></svg>'},
        generic: {store:'FAMILY GROCERY', slogan:'Fresh Food, Friendly Service', addr:'500 MAIN ST', zip:'10001', phone:'(555) 010-3344', storeno:'STORE #0012', color:'#1b5e20', accent:'#1b5e20', promo:'Thank you and come again!', logo:''}
    };

    function renderPresetBtns(){
        var h = '';
        PRESETS.forEach(function(p){
            h += '<button class="btn btn-outline-light btn-sm" onclick="addItem(' + JSON.stringify(p).replace(/"/g,'&quot;') + ')">' + p.n.replace(/&/g,'&amp;').replace(/</g,'&lt;') + '</button>';
        });
        $('rc-presets').innerHTML = h;
    }

    window.items = [
        {n:'Milk 1G 2%', q:'1', u:'ea', p:'4.28'},
        {n:'Bananas', q:'1.2', u:'lb', p:'1.27'},
        {n:'Wheat Bread', q:'1', u:'ea', p:'2.94'},
        {n:'Eggs 12pk', q:'1', u:'ea', p:'3.86'},
        {n:'Chicken Thighs', q:'1.5', u:'lb', p:'5.42'},
        {n:'Shredded Cheese', q:'1', u:'ea', p:'4.06'}
    ];

    window.addItem = function(p){
        p = p || {};
        items.push({n:p.n||'New Item', q:p.q||'1', u:p.u||'ea', p:p.p||'0.99'});
        renderItems(); renderReceipt();
    };

    function renderItems(){
        var h = '';
        for (var i=0;i<items.length;i++){
            h += '<div class="item-row">' +
                '<input value="'+esc(items[i].n)+'" oninput="items['+i+'].n=this.value;renderReceipt()" title="Item">' +
                '<input value="'+esc(items[i].q)+'" oninput="items['+i+'].q=this.value;renderReceipt()" title="Qty">' +
                '<input value="'+esc(items[i].p)+'" oninput="items['+i+'].p=this.value;renderReceipt()" title="Price">' +
                '<button class="btn btn-outline-danger btn-sm" style="padding:.15rem .35rem;line-height:1;" onclick="items.splice('+i+',1);renderItems();renderReceipt()">&times;</button>' +
                '</div>';
        }
        $('rc-items').innerHTML = h;
    }
    window.renderItems = renderItems;

    function esc(s){
        return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function money(x){
        return '$' + Number(x).toFixed(2);
    }

    window.__customLogo = null;

    window.onLogoFile = function(evt){
        var f = evt.target.files && evt.target.files[0];
        if (!f) return;
        var r = new FileReader();
        r.onload = function(){
            window.__customLogo = r.result;
            $('rc-logo-mode').value = 'custom';
            $('rc-logo-status').textContent = 'Custom logo loaded: ' + esc(f.name);
            renderReceipt();
        };
        r.readAsDataURL(f);
    };

    window.clearLogo = function(){
        window.__customLogo = null;
        $('rc-logo-file').value = '';
        $('rc-logo-status').textContent = 'Custom logo removed. Using template logo.';
        renderReceipt();
    };

    function buildLines(){
        var store = $('rc-store').value || 'GROCERY';
        var addr = $('rc-addr').value || '';
        var zip = $('rc-zip').value || '';
        var phone = $('rc-phone').value || '';
        var storeno = $('rc-storeno').value || 'STORE #0000';
        var cashier = $('rc-cashier').value || '';
        var taxRate = parseFloat($('rc-tax').value) || 0;
        var disc = parseFloat($('rc-disc').value) || 0;
        var coupon = parseFloat($('rc-coupon').value) || 0;
        var card = $('rc-card').value;
        var card4 = $('rc-card4').value || '0000';
        var showPromo = $('rc-promo').checked;
        var logoMode = $('rc-logo-mode').value;

        var now = new Date();
        var mo = ('0'+(now.getMonth()+1)).slice(-2);
        var dy = ('0'+now.getDate()).slice(-2);
        var yr = String(now.getFullYear()).slice(-2);
        var hh = now.getHours(), ampm = hh>=12?'PM':'AM'; hh = hh%12||12;
        var mi = ('0'+now.getMinutes()).slice(-2);
        var dateStr = mo+'/'+dy+'/'+yr+'  '+hh+':'+mi+' '+ampm;

        var lineItems = [];
        var sub = 0, qtyTotal = 0;
        items.forEach(function(it){
            var q = parseFloat(it.q)||0;
            var p = parseFloat(it.p)||0;
            var lineTotal = q * p;
            sub += lineTotal;
            qtyTotal += q;
            lineItems.push({n:it.n, q:it.q, u:it.u, p:lineTotal});
        });
        var subAfter = sub - disc;
        if (subAfter < 0) subAfter = 0;
        var tax = subAfter * (taxRate/100);
        var total = subAfter + tax;
        var savings = disc + coupon;

        var trans = String(Math.floor(100000 + Math.random()*900000));

        var meta = PRESETS_META[window.__rcPreset] || null;

        return {
            store: store, color:'#0421a8', accent:'#ffc220',
            logoSvg: meta ? (meta.logo || '') : '',
            slogan: meta ? meta.slogan : 'Save money. Live better.',
            promo: meta ? (meta.promo || '') : '',
            storeno: storeno, addr: addr, zip: zip, phone: phone, cashier: cashier,
            dateStr: dateStr, opCode:'0047', reg:'05', trans:trans,
            lineItems: lineItems, disc:disc, coupon:coupon, savings:savings,
            sub:sub, subAfter:subAfter, tax:tax, total:total, taxRate:taxRate, qtyTotal:qtyTotal,
            card:card, card4:card4, showPromo: showPromo, logoMode: logoMode
        };
    }

    window.renderReceipt = function(){
        var d = buildLines();
        var h = '';

        h += '<div class="rc-top-jag"></div>';
        h += '<div class="rc-headline">_ _ _ _ _ _ _ _ _ _ _ _ _ _ _</div>';
        h += '<div class="rc-headline" style="letter-spacing:4px;">RE-&Oslash;0 &middot; OPEN / CLOSE</div>';

        var logoHtml = '';
        if (d.logoMode === 'custom' && window.__customLogo) {
            logoHtml = '<div class="rc-logo"><img src="' + window.__customLogo + '" alt="logo"></div>';
        } else if (d.logoMode === 'preset' && d.logoSvg) {
            logoHtml = '<div class="rc-logo">' + d.logoSvg + '</div>';
        }
        if (logoHtml) h += logoHtml;

        var storeHtml = '';
        d.store.split(' ').forEach(function(seg){
            var first = String(seg).charAt(0).toUpperCase();
            storeHtml += (first === 'W') ? '<span>'+esc(seg)+'</span>' : esc(seg);
            storeHtml += ' ';
        });
        h += '<div class="rc-store">' + storeHtml.trim() + '</div>';
        h += '<div class="rc-slogan">' + esc(d.slogan) + '</div>';
        h += '<div class="rc-meta">' + esc(d.storeno) + '&nbsp; REG ' + d.reg +
            (d.cashier ? '<br>CASHIER ' + esc(d.cashier) : '') +
            '<br>' + esc(d.addr) + '<br>' + esc(d.zip ? 'ZIP ' + d.zip : '') + '<br>' + (d.phone ? esc(d.phone) : '') + '</div>';
        h += '<div class="rc-hr"></div>';

        h += '<div class="rc-row"><span class="l">'+d.dateStr+'</span></div>';
        h += '<div class="rc-row"><span class="l">OP CODE '+d.opCode+'&nbsp; REG '+d.reg+'</span><span class="r">TRAN '+d.trans+'</span></div>';
        h += '<div class="rc-hr-s"></div>';

        d.lineItems.forEach(function(it){
            var line = it.q + ' ' + it.u.toUpperCase() + ' ' + it.n;
            h += '<div class="rc-row"><span class="l">'+esc(line)+'</span><span class="r">'+money(it.p)+'</span></div>';
        });

        h += '<div class="rc-hr"></div>';

        h += '<div class="rc-row"><span class="l">SUBTOTAL</span><span class="r">'+money(d.sub)+'</span></div>';
        if (d.disc > 0){
            h += '<div class="rc-row"><span class="l">DISCOUNT</span><span class="r">-'+money(d.disc)+'</span></div>';
        }
        if (d.coupon > 0){
            h += '<div class="rc-row"><span class="l">COUPON SRVGS</span><span class="r">-'+money(d.coupon)+'</span></div>';
        }
        if (d.taxRate > 0){
            h += '<div class="rc-row"><span class="l">TAX ('+d.taxRate.toFixed(1)+'%)</span><span class="r">'+money(d.tax)+'</span></div>';
        }
        h += '<div class="rc-row rc-total"><span class="l">TOTAL</span><span class="r">'+money(d.total)+'</span></div>';
        h += '<div class="rc-row"><span class="l">NUMBER OF ITEMS</span><span class="r">'+Math.round(d.qtyTotal)+'</span></div>';

        h += '<div class="rc-hr"></div>';

        if (d.card === 'CASH'){
            var tendered = Math.ceil(d.total);
            h += '<div class="rc-row"><span class="l">CASH</span><span class="r">'+money(tendered)+'</span></div>';
            h += '<div class="rc-row"><span class="l">CHANGE</span><span class="r">'+money(tendered-d.total)+'</span></div>';
        } else if (d.card === 'WALMART PAY'){
            h += '<div class="rc-row"><span class="l">WALMART PAY</span><span class="r">'+money(d.total)+'</span></div>';
            h += '<div class="rc-row"><span class="l qty">Acct ************'+d.card4+'</span></div>';
        } else {
            h += '<div class="rc-big">'+esc(d.card)+'</div>';
            h += '<div class="rc-row"><span class="l">AUTH CODE</span><span class="r">EV735A</span></div>';
            h += '<div class="rc-row"><span class="l">ACCT</span><span class="r">************'+d.card4+'</span></div>';
            h += '<div class="rc-row"><span class="l">AMOUNT</span><span class="r">'+money(d.total)+'</span></div>';
        }

        h += '<div class="rc-hr"></div>';

        if (d.savings > 0){
            h += '<div class="rc-row rc-save"><span class="l">TOTAL SAVINGS</span><span class="r">'+money(d.savings)+'</span></div>';
        }

        if (d.showPromo && d.promo){
            d.promo.split('\n').forEach(function(line){
                h += '<div class="rc-promo">' + line + '</div>';
            });
        }

        h += '<div class="rc-row"><span class="l">REFUNDS &amp; EXCHANGES</span></div>';
        h += '<div class="rc-row"><span class="l">WITHIN 90 DAYS</span></div>';

        h += '<div class="rc-tear"><span>¤</span><span class="dash"></span>Tear / &mdash; Cut here &mdash;<span class="dash"></span><span>¤</span></div>';
        h += '<div class="rc-barcode" id="rc-barcode">'+bars(d.trans)+'</div>';
        h += '<div class="rc-barnum">' + d.trans + '</div>';
        h += '<div class="rc-thanks">THANK YOU FOR SHOPPING WITH US!</div>';
        h += '<div class="rc-punch l"></div><div class="rc-punch r"></div>';
        h += '<div class="rc-fade"></div>';
        h += '<div class="rc-bot-jag"></div>';

        $('rc-content').innerHTML = h;
        window.__rc = d;
    };

    function bars(seed){
        var out = '';
        var widths = [1,2,1,3,1,1,2,1];
        var s = seed + '0123456789';
        for (var i=0;i<42;i++){
            var w = widths[(i + (seed.charCodeAt(0)||7)) % widths.length];
            if (i%2===0) out += '<span style="width:'+w+'px">&nbsp;</span>';
            else out += '<span style="width:'+w+'px;background:transparent">&nbsp;</span>';
        }
        return out;
    }

    window.randomizeItems = function(){
        var count = 4 + Math.floor(Math.random()*8);
        items = [];
        for (var i=0;i<count;i++){
            var it = PRESETS[Math.floor(Math.random()*PRESETS.length)];
            var q = (Math.random()>0.6) ? (Math.random()*2+0.5).toFixed(1) : '1';
            items.push({n:it.n, q:String(q), u:it.u, p:it.p});
        }
        $('rc-tax').value = Math.random()>0.5 ? '0' : '8.5';
        renderItems(); renderReceipt();
    };

    window.applyPreset = function(key){
        var p = PRESETS_META[key];
        if (!p) return;
        window.__rcPreset = key;
        $('rc-store').value = p.store;
        $('rc-addr').value = p.addr;
        $('rc-zip').value = p.zip;
        $('rc-phone').value = p.phone;
        $('rc-storeno').value = p.storeno;
        renderReceipt();
    };

    window.downloadPng = function(){
        var data = window.__rc;
        if (!data) return;
        var paper = $('rc-paper');
        var w = paper.offsetWidth, h = paper.offsetHeight;
        var scale = 3;
        var canvas = document.createElement('canvas');
        canvas.width = w*scale; canvas.height = h*scale;
        var ctx = canvas.getContext('2d');
        ctx.scale(scale,scale);

        var src = null;
        if (data.logoMode === 'custom' && window.__customLogo) src = window.__customLogo;
        else if (data.logoMode === 'preset' && data.logoSvg) src = svgDataUrl(data.logoSvg);

        var logoImg = null, logoImgW = 0, logoImgH = 0;
        var logoPromise = src ? loadImg(src).then(function(im){
            logoImg = im;
            var tw = Math.min(w - 30, 190);
            logoImgW = tw;
            logoImgH = im.height * (tw / im.width);
        }).catch(function(){}) : Promise.resolve();

        logoPromise.then(function(){
            draw(ctx, canvas, w, h, data, logoImg, logoImgW, logoImgH);
            var a = document.createElement('a');
            a.href = canvas.toDataURL('image/png');
            a.download = 'receipt.png';
            a.click();
            if (window.toast) window.toast('Receipt PNG downloaded.');
        });
    };

    function svgDataUrl(svg){
        return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
    }

    function loadImg(src){
        return new Promise(function(res, rej){
            var im = new Image();
            im.onload = function(){ res(im); };
            im.onerror = function(){ rej(new Error('image load failed')); };
            im.src = src;
        });
    }

    function draw(ctx, canvas, w, h, d, logoImg, logoImgW, logoImgH){
        // paper bg
        var grad = ctx.createLinearGradient(0,0,0,h);
        grad.addColorStop(0,'#fffdf5'); grad.addColorStop(1,'#f6f1e4');
        ctx.fillStyle = grad; ctx.fillRect(0,0,w,h);

        // light grain
        ctx.fillStyle = 'rgba(90,80,60,0.05)';
        for (var y=-h; y<h; y+=3){ ctx.fillRect(0,y,w,1); }

        // side creases
        ctx.fillStyle = 'rgba(180,120,40,0.16)';
        ctx.fillRect(8,10,1,h-20); ctx.fillRect(w-9,10,1,h-20);

        var yPos = 12;
        if (logoImg){
            ctx.drawImage(logoImg, w/2 - logoImgW/2, yPos, logoImgW, logoImgH);
            yPos += logoImgH + 10;
        } else {
            yPos = 28;
        }

        ctx.font = '700 21px Arial';
        ctx.textAlign = 'center'; ctx.fillStyle = '#0421a8';
        ctx.fillText(d.store, w/2, yPos);
        ctx.font = '8px Arial'; ctx.fillStyle = '#6a6a6a';
        yPos += 12; ctx.fillText(d.slogan.toUpperCase(), w/2, yPos);

        ctx.font = '10.5px "Courier New"'; ctx.fillStyle = '#333'; yPos += 15;
        ctx.textAlign = 'center';
        ctx.fillText(d.storeno + '   REG 05' + (d.cashier ? '   CASHIER ' + d.cashier : ''), w/2, yPos); yPos+=12;
        ctx.fillText(d.addr, w/2, yPos); yPos+=12;
        if (d.zip) { ctx.fillText('ZIP '+d.zip, w/2, yPos); yPos+=12; }
        if (d.phone) { ctx.fillText(d.phone, w/2, yPos); yPos+=12; }
        yPos += 6;
        dashHr(ctx, 14, w-14, yPos, '#999'); yPos += 13;

        ctx.textAlign = 'left'; ctx.fillStyle = '#17181a';
        ctx.fillText(d.dateStr, 14, yPos); yPos+=14;
        ctx.font = '12px "Courier New"';
        ctx.fillText('OP CODE '+d.opCode+'  REG '+d.reg, 14, yPos);
        ctx.textAlign = 'right';
        ctx.fillText('TRAN '+d.trans, w-14, yPos);
        ctx.textAlign = 'left';
        yPos += 15;
        dashHr(ctx, 14, w-14, yPos, '#bbb'); yPos += 14;

        d.lineItems.forEach(function(it){
            var line = it.q + ' ' + it.u.toUpperCase() + ' ' + it.n;
            ctx.fillText(line, 14, yPos);
            ctx.textAlign = 'right';
            ctx.fillText(money(it.p), w-14, yPos);
            ctx.textAlign = 'left';
            yPos += 14;
        });

        yPos += 4;
        dashHr(ctx, 14, w-14, yPos, '#999'); yPos += 14;

        ctx.fillText('SUBTOTAL',14,yPos); ctx.textAlign='right'; ctx.fillText(money(d.sub),w-14,yPos); ctx.textAlign='left'; yPos+=14;
        if (d.disc>0){ ctx.fillText('DISCOUNT',14,yPos); ctx.textAlign='right'; ctx.fillText('-'+money(d.disc),w-14,yPos); ctx.textAlign='left'; yPos+=14; }
        if (d.coupon>0){ ctx.fillText('COUPON SRVGS',14,yPos); ctx.textAlign='right'; ctx.fillText('-'+money(d.coupon),w-14,yPos); ctx.textAlign='left'; yPos+=14; }
        if (d.taxRate>0){ ctx.fillText('TAX ('+d.taxRate.toFixed(1)+'%)',14,yPos); ctx.textAlign='right'; ctx.fillText(money(d.tax),w-14,yPos); ctx.textAlign='left'; yPos+=14; }
        ctx.font='700 13.5px "Courier New"';
        ctx.fillText('TOTAL',14,yPos); ctx.textAlign='right'; ctx.fillText(money(d.total),w-14,yPos); ctx.textAlign='left'; yPos+=18;
        ctx.font='12px "Courier New"';
        ctx.fillText('NUMBER OF ITEMS  '+Math.round(d.qtyTotal),14,yPos); yPos+=14;
        dashHr(ctx,14,w-14,yPos,'#999'); yPos+=14;

        if (d.card === 'CASH'){
            var tendered = Math.ceil(d.total);
            ctx.fillText('CASH',14,yPos); ctx.textAlign='right'; ctx.fillText(money(tendered),w-14,yPos); ctx.textAlign='left'; yPos+=14;
            ctx.fillText('CHANGE',14,yPos); ctx.textAlign='right'; ctx.fillText(money(tendered-d.total),w-14,yPos); ctx.textAlign='left'; yPos+=14;
        } else if (d.card === 'WALMART PAY'){
            ctx.fillText('WALMART PAY',14,yPos); ctx.textAlign='right'; ctx.fillText(money(d.total),w-14,yPos); ctx.textAlign='left'; yPos+=14;
            ctx.fillText('Acct ************'+d.card4,14,yPos); yPos+=14;
        } else {
            ctx.font='700 14px "Courier New"'; ctx.textAlign='center';
            ctx.fillText(d.card, w/2, yPos+10); yPos+=16;
            ctx.font='12px "Courier New"'; ctx.textAlign='left';
            ctx.fillText('AUTH CODE',14,yPos); ctx.textAlign='right'; ctx.fillText('EV735A',w-14,yPos); ctx.textAlign='left'; yPos+=14;
            ctx.fillText('ACCT',14,yPos); ctx.textAlign='right'; ctx.fillText('************'+d.card4,w-14,yPos); ctx.textAlign='left'; yPos+=14;
            ctx.fillText('AMOUNT',14,yPos); ctx.textAlign='right'; ctx.fillText(money(d.total),w-14,yPos); ctx.textAlign='left'; yPos+=14;
        }

        dashHr(ctx,14,w-14,yPos,'#999'); yPos+=14;

        if (d.savings > 0){
            ctx.font='700 12px "Courier New"'; ctx.fillStyle = '#007333';
            ctx.fillText('TOTAL SAVINGS',14,yPos); ctx.textAlign='right'; ctx.fillText(money(d.savings),w-14,yPos); ctx.textAlign='left';
            ctx.fillStyle = '#17181a'; ctx.font='12px "Courier New"';
            yPos+=16;
        }

        if (d.showPromo && d.promo){
            ctx.font = '8.5px "Courier New"'; ctx.fillStyle = '#444'; ctx.textAlign = 'center';
            d.promo.split('\n').forEach(function(line){
                ctx.fillText(line, w/2, yPos+6); yPos+=10;
            });
            ctx.textAlign = 'left'; ctx.fillStyle = '#17181a'; ctx.font='12px "Courier New"';
        }

        ctx.fillText('REFUNDS & EXCHANGES',14,yPos); yPos+=14;
        ctx.fillText('WITHIN 90 DAYS',14,yPos); yPos+=12;

        // barcode
        var bx = w/2 - 60, by = yPos + 4;
        var s = d.trans + '0123456789';
        var widths=[1,2,1,3,1,1,2,1];
        for (var i=0;i<42;i++){
            var bw = widths[(i+(s.charCodeAt(0)||7))%widths.length];
            if (i%2===0){ ctx.fillStyle='#17181a'; ctx.fillRect(bx,by,bw,42); }
            bx += bw;
        }
        ctx.fillStyle='#333'; ctx.font='9px "Courier New"'; ctx.textAlign='center';
        ctx.fillText(d.trans, w/2, by+52);
        yPos = by+58;

        ctx.font='700 11px "Courier New"'; ctx.fillStyle='#17181a';
        ctx.fillText('THANK YOU FOR SHOPPING WITH US!', w/2, yPos+14);
        yPos += 26;

        // zigzag bottom
        ctx.beginPath();
        ctx.moveTo(0, h);
        var teeth = 13;
        for (var t=0;t<teeth;t++){
            var x1 = (w/teeth)*t;
            var x2 = (w/teeth)*(t+1);
            ctx.lineTo(x1, h-8);
            ctx.lineTo((x1+x2)/2, h);
            ctx.lineTo(x2, h-8);
        }
        ctx.lineTo(w, h); ctx.closePath();
        ctx.fillStyle = '#f6f1e4';
        ctx.fill();
    }

    function dashHr(ctx, x1, x2, y, color){
        ctx.strokeStyle = color; ctx.lineWidth = 1;
        ctx.setLineDash([4,4]); ctx.beginPath(); ctx.moveTo(x1,y); ctx.lineTo(x2,y); ctx.stroke();
        ctx.setLineDash([]);
    }

    window.copyReceiptText = function(){
        var d = window.__rc;
        if (!d) return;
        var lines = [];
        lines.push(d.store);
        lines.push(d.storeno+' REG '+d.reg);
        if (d.cashier) lines.push('CASHIER '+d.cashier);
        lines.push(d.addr); lines.push('ZIP '+d.zip); lines.push(d.phone);
        lines.push(d.dateStr+'  TRAN '+d.trans);
        lines.push('');
        d.lineItems.forEach(function(it){ lines.push(it.q+' '+it.u.toUpperCase()+' '+it.n+'   '+money(it.p)); });
        lines.push('');
        lines.push('SUBTOTAL  '+money(d.sub));
        if (d.disc>0) lines.push('DISCOUNT  -'+money(d.disc));
        if (d.coupon>0) lines.push('COUPON SRVGS  -'+money(d.coupon));
        if (d.taxRate>0) lines.push('TAX  '+money(d.tax));
        lines.push('TOTAL  '+money(d.total));
        lines.push('NUMBER OF ITEMS  '+Math.round(d.qtyTotal));
        navigator.clipboard.writeText(lines.join('\n')).then(function(){ showToast('Receipt text copied!'); });
    };

    function showToast(m){
        var t=document.createElement('div');
        t.textContent=m; t.style.cssText='position:fixed;bottom:20px;right:20px;background:#34d399;color:#000;padding:.5rem 1rem;border-radius:8px;font-weight:600;font-size:.85rem;z-index:9999;';
        document.body.appendChild(t); setTimeout(function(){t.remove();},2000);
    }

    renderPresetBtns();
    renderItems();
    window.__rcPreset = 'walmart';
    renderReceipt();
})();
</script>
<?php page_footer(); ?>