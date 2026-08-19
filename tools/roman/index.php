<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free Roman numeral converter — decimal to Roman and Roman to decimal with live validation.',
    'keywords' => 'roman numerals, roman numeral converter, decimal to roman, roman to decimal',
];
page_header('Roman Numeral Converter');
?>
<div class="container" style="max-width: 760px;">
    <h1 class="h4 mb-2 reveal in-view">Roman Numeral Converter</h1>
    <p class="text-secondary mb-4 reveal in-view">Convert between Arabic (decimal) numbers and Roman numerals, both directions, with a run history.</p>

    <div class="card mb-3 reveal in-view"><div class="card-body">
        <label class="form-label">Decimal number (1–3999)</label>
        <input id="rm-dec" class="form-control mb-2" type="number" min="1" max="3999" placeholder="e.g. 1944">
        <button class="btn btn-primary btn-sm" onclick="toRoman()">Convert to Roman</button>
        <hr class="my-3" style="border-color:var(--line);">
        <label class="form-label">Roman numeral</label>
        <input id="rm-rom" class="form-control mb-2" style="text-transform:uppercase;" placeholder="e.g. MCMXLIV">
        <button class="btn btn-outline-light btn-sm" onclick="toDec()">Convert to decimal</button>
        <div id="rm-result" class="mt-3" style="font-size:1.4rem;font-weight:700;"></div>
    </div></div>

    <div class="card reveal in-view"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h6 mb-0">History</h2>
            <button class="btn btn-outline-light btn-sm" onclick="clearHist()">Clear</button>
        </div>
        <div id="rm-hist" class="text-secondary small">No conversions yet.</div>
    </div></div>
</div>
<script>
(function(){
    var vals={'M':1000,'CM':900,'D':500,'CD':400,'C':100,'XC':90,'L':50,'XL':40,'X':10,'IX':9,'V':5,'IV':4,'I':1};
    var order=['M','CM','D','CD','C','XC','L','XL','X','IX','V','IV','I'];
    var hist=[]; try{ hist=JSON.parse(sessionStorage.getItem('rm_hist')||'[]'); }catch(e){}
    var res=document.getElementById('rm-result');

    window.toRoman=function(){
        var n=parseInt(document.getElementById('rm-dec').value,10);
        if(!n||n<1||n>3999){ res.textContent='Enter a number between 1 and 3999.'; res.style.color='#ffc107'; return; }
        var out=''; var v=n;
        order.forEach(function(k){ while(v>=vals[k]){ out+=k; v-=vals[k]; } });
        set(n+' = <span style="color:#26d07c">'+out+'</span>');
        push(n+' → '+out);
    };
    window.toDec=function(){
        var s=document.getElementById('rm-rom').value.toUpperCase().replace(/\s+/g,'').trim();
        if(!s){ res.textContent='Enter a Roman numeral.'; res.style.color='#ffc107'; return; }
        if(!/^[IVXLCDM]+$/.test(s)){ res.textContent='Only characters I, V, X, L, C, D, M allowed.'; res.style.color='#ffc107'; return; }
        var total=0; var i=0;
        while(i<s.length){
            var two=s.substr(i,2);
            if(vals[two]!==undefined){ total+=vals[two]; i+=2; }
            else { total+=vals[s[i]]; i++; }
        }
        // validity check: standard form roundtrip
        var check=''; var v=total;
        order.forEach(function(k){ while(v>=vals[k]){ check+=k; v-=vals[k]; } });
        if(check!==s){ res.textContent='Not valid standard Roman form (standard is '+check+').'; res.style.color='#ffc107'; return; }
        set(s+' = <span style="color:#26d07c">'+total+'</span>');
        push(s+' → '+total);
    };
    function set(html){ res.innerHTML=html; res.style.color=''; renderHist(); }
    function push(t){ hist.unshift(t); if(hist.length>12) hist=hist.slice(0,12); try{ sessionStorage.setItem('rm_hist',JSON.stringify(hist));}catch(e){} renderHist(); }
    function renderHist(){
        var el=document.getElementById('rm-hist');
        el.innerHTML=hist.length?hist.map(function(t){return '<div class="mb-1"><code>'+esc(t)+'</code></div>';}).join(''):'No conversions yet.';
    }
    window.clearHist=function(){ hist=[]; try{sessionStorage.removeItem('rm_hist');}catch(e){} renderHist(); };
    function esc(s){ var d=document.createElement('div'); d.appendChild(document.createTextNode(s)); return d.innerHTML; }
    renderHist();
})();
</script>
<?php page_footer(); ?>