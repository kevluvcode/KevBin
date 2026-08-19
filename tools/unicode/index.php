<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free Unicode & escapes toolkit. HTML entity encode/decode, JavaScript \\uXXXX escapes, URL encoding, hex escapes, HTML tag stripping and Unicode normalization.',
    'keywords' => 'unicode toolkit, html entity encoder, unicode escapes, url encode, unicode normalization, html tag stripper, character escapes',
];
page_header('Unicode & Escapes Toolkit');
?>
<style>textarea{font-family:ui-monospace,Consolas,monospace;} </style>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-2 reveal in-view">Unicode &amp; Escapes Toolkit</h1>
    <p class="text-secondary mb-4 reveal in-view">Encode and decode text using HTML entities, JavaScript/JSON escapes, URL encoding, hex and more — all in your browser.</p>

    <div class="card mb-3 reveal in-view"><div class="card-body">
        <label class="form-label">Input</label>
        <textarea id="uc-in" class="form-control mb-2" rows="5" placeholder="Paste text here...">KevBin → 💻 100% 🚀</textarea>
        <div class="d-flex flex-wrap gap-2 mb-2">
            <button class="btn btn-primary btn-sm" onclick="run('ent')">HTML encode</button>
            <button class="btn btn-outline-light btn-sm" onclick="run('deent')">HTML decode</button>
            <button class="btn btn-outline-light btn-sm" onclick="run('jsu')">\uXXXX escape</button>
            <button class="btn btn-outline-light btn-sm" onclick="run('dejsu')">\uXXXX decode</button>
            <button class="btn btn-outline-light btn-sm" onclick="run('url')">URL encode</button>
            <button class="btn btn-outline-light btn-sm" onclick="run('deurl')">URL decode</button>
            <button class="btn btn-outline-light btn-sm" onclick="run('hex')">Hex encode</button>
            <button class="btn btn-outline-light btn-sm" onclick="run('dehex')">Hex decode</button>
            <button class="btn btn-outline-light btn-sm" onclick="run('nfc')">Normalize NFC</button>
            <button class="btn btn-outline-light btn-sm" onclick="run('nfkd')">Normalize NFKD</button>
            <button class="btn btn-outline-light btn-sm" onclick="run('strip')">Strip HTML tags</button>
        </div>
        <label class="form-label">Output</label>
        <textarea id="uc-out" class="form-control mb-2" rows="5" readonly></textarea>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-light btn-sm" onclick="copyOut()">Copy output</button>
            <span class="text-secondary small align-self-center" id="uc-info"></span>
        </div>
    </div></div>
</div>
<script>
(function(){
    var inp=document.getElementById('uc-in');
    var out=document.getElementById('uc-out');
    var info=document.getElementById('uc-info');
    window.run=function(mode){
        var t=inp.value; var r='';
        try{
            if(mode==='ent'){ var d=document.createElement('div'); d.appendChild(document.createTextNode(t)); r=d.innerHTML; }
            else if(mode==='deent'){ var x=document.createElement('div'); x.innerHTML=t; r=x.textContent||''; }
            else if(mode==='jsu'){ r=Array.from(t).map(function(c){ var cc=c.codePointAt(0); return cc>0xffff?'\\u{'+cc.toString(16)+'}':'\\u'+cc.toString(16).padStart(4,'0'); }).join(''); }
            else if(mode==='dejsu'){ r=t.replace(/\\u\{([0-9a-fA-F]+)\}|\\u([0-9a-fA-F]{4})/g,function(m,a,b){ var cp=parseInt(a||b,16); return String.fromCodePoint(cp); }); }
            else if(mode==='url'){ r=encodeURIComponent(t); }
            else if(mode==='deurl'){ r=decodeURIComponent(t); }
            else if(mode==='hex'){ r=Array.from(new TextEncoder().encode(t)).map(function(b){return b.toString(16).padStart(2,'0');}).join(' '); }
            else if(mode==='dehex'){ var bytes=t.replace(/\s+/g,'').match(/.{2}/g)||[]; r=new TextDecoder().decode(Uint8Array.from(bytes.map(function(h){return parseInt(h,16);}))); }
            else if(mode==='nfc'){ r=t.normalize('NFC'); }
            else if(mode==='nfkd'){ r=t.normalize('NFKD'); }
            else if(mode==='strip'){ r=t.replace(/<[^>]*>/g,'').replace(/&nbsp;/gi,' '); }
            out.value=r;
            var len=Array.from(r).length;
            info.textContent='Output: '+r.length+' bytes / '+len+' characters';
        }catch(e){ out.value=''; info.textContent=e.message; }
    };
    window.copyOut=function(){
        navigator.clipboard.writeText(out.value).then(function(){ info.textContent='Copied!'; });
    };
})();
</script>
<?php page_footer(); ?>