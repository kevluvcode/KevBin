<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free browser fingerprint & privacy test — generates your device fingerprint from canvas, WebGL, fonts, audio, timezone, screen and hardware signals, exactly like tracking scripts do.',
    'keywords' => 'browser fingerprint, canvas fingerprint, device fingerprint id, webgl fingerprint, font enumeration, tracking fingerprint, privacy test',
];
page_header('Browser Fingerprint & Privacy Test');
?>
<style>
.fp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:.6rem;}
.fp-cell{background:rgba(255,255,255,.03);border:1px solid var(--line);border-radius:8px;padding:.5rem .6rem;}
.fp-cell .k{font-size:.68rem;text-transform:uppercase;letter-spacing:1px;color:var(--dim);}
.fp-cell .v{font-size:.8rem;word-break:break-all;color:#cbd5e1;margin-top:2px;}
#fp-big{font-family:'JetBrains Mono',monospace;font-size:1.05rem;word-break:break-all;}
</style>
<div class="container" style="max-width: 960px;">
    <h1 class="h4 mb-2 reveal in-view">Browser Fingerprint &amp; Privacy Test</h1>
    <p class="text-secondary mb-4 reveal in-view">Every website you visit can read hundreds of signals about your device. This test collects them the same way a tracking script would — canvas, WebGL, fonts, audio, timezone, screen, hardware — and calculates a fingerprint ID. Nothing is uploaded; everything runs in this tab.</p>

    <div class="card mb-3 reveal in-view"><div class="card-body">
        <button class="btn btn-primary" onclick="runFp()">Compute my fingerprint</button>
        <div class="alert alert-warning small mt-3 mb-2"><strong>Note:</strong> your fingerprint is <em>not</em> unique to you worldwide — it depends on how much your browser reveals. The point is to show you what tracking scripts see.</div>
        <div id="fp-big" class="mt-1"></div>
        <div class="text-secondary small mt-1" id="fp-info"></div>
    </div></div>

    <div class="card mb-3 reveal in-view"><div class="card-body">
        <h2 class="h6 mb-3">Signals Collected</h2>
        <div class="fp-grid" id="fp-cells"></div>
    </div></div>
</div>
<script>
(function(){
    function hash(str){
        var h1=0xdeadbeef,h2=0x41c6ce57;
        for(var i=0;i<str.length;i++){
            var ch=str.charCodeAt(i);
            h1=Math.imul(h1^ch,2654435761);
            h2=Math.imul(h2^ch,1597334677);
        }
        h1=Math.imul(h1^(h1>>>16),2246822507) ^ Math.imul(h2^(h2>>>13),3266489909);
        h2=Math.imul(h2^(h2>>>16),2246822507) ^ Math.imul(h1^(h1>>>13),3266489909);
        return (h2>>>0).toString(16).padStart(8,'0')+(h1>>>0).toString(16).padStart(8,'0');
    }
    function canvasFp(){
        try{
            var c=document.createElement('canvas'); c.width=240; c.height=40;
            var g=c.getContext('2d');
            g.textBaseline='alphabetic'; g.fillStyle='#f00'; g.fillRect(0,0,240,40);
            g.fillStyle='#0f0'; g.fillRect(20,10,200,15);
            g.font='18px Arial'; g.fillStyle='#00f'; g.fillText('KevBin FP ░▒▓█ 😀',30,30);
            g.fillStyle='#333'; g.font='14px Georgia'; g.fillText('abcdefghijklmnopqrstuvwxyz0123456789',4,36);
            g.strokeStyle='#ff0'; g.beginPath(); g.arc(180,20,8,0,Math.PI*2); g.stroke();
            return c.toDataURL();
        }catch(e){ return 'canvas blocked: '+e.message; }
    }
    async function audioFp(){
        try{
            var AC = window.OfflineAudioContext || window.webkitOfflineAudioContext;
            if(!AC) return 'not supported';
            var ctx = new AC(1, 44100, 44100);
            var o = ctx.createOscillator(); o.type='triangle'; o.frequency.value=12345;
            var comp = ctx.createDynamicsCompressor();
            comp.threshold.value=-50; comp.knee.value=40; comp.ratio.value=12;
            o.connect(comp); comp.connect(ctx.destination); o.start(0);
            var buf = await ctx.startRendering();
            var sig = 0;
            for(var i=4500;i<5000;i++) sig = ((sig<<5)-sig + buf.getChannelData(0)[i])|0;
            return 'hash:'+(sig>>>0).toString(16);
        }catch(e){ return 'audio blocked'; }
    }
    async function fontFp(){
        var fonts=['Arial','Verdana','Times New Roman','Courier New','Georgia','Tahoma','Trebuchet MS','Impact','Comic Sans MS','Palatino Linotype','Book Antiqua','Arial Black','Lucida Console','Franklin Gothic Medium','Calibri','Cambria','Segoe UI','Roboto','Helvetica Neue','Consolas'];
        var out=[];
        try{
            var probe=document.fonts;
            for(var i=0;i<fonts.length;i++){
                try{ if(await probe.load('16px "'+fonts[i]+'"')){ var ok=probe.check('16px "'+fonts[i]+'"','mmmmmmm'); if(ok) out.push(fonts[i]); } }catch(e){}
            }
        }catch(e){ return 'fonts blocked'; }
        return out.length? out.join(','):'default set only';
    }
    window.runFp=async function(){
        var cells=document.getElementById('fp-cells');
        var big=document.getElementById('fp-big');
        var info=document.getElementById('fp-info');
        var n={};
        n.user_agent=navigator.userAgent;
        n.platform=navigator.platform||'n/a';
        n.language=navigator.language;
        n.languages=(navigator.languages||[]).join(',');
        n.hardware_concurrency=navigator.hardwareConcurrency||'n/a';
        n.device_memory=navigator.deviceMemory? navigator.deviceMemory+' GB':'n/a';
        n.timezone=Intl.DateTimeFormat().resolvedOptions().timeZone||'n/a';
        n.timezone_offset=new Date().getTimezoneOffset();
        n.screen=screen.width+'×'+screen.height+', depth '+screen.colorDepth+', dpr '+(window.devicePixelRatio||1);
        n.touch=(('ontouchstart' in window)||(navigator.maxTouchPoints>0));
        n.cookies_enabled=navigator.cookieEnabled;
        n.pdf_viewer=(navigator.mimeTypes&&navigator.mimeTypes['application/pdf'])?1:0;
        n.plugins=(navigator.plugins&&navigator.plugins.length)?Array.from(navigator.plugins).slice(0,6).join(', '):'none';
        n.connection=navigator.connection? (navigator.connection.effectiveType+' down:'+navigator.connection.downlink+'Ms') : 'n/a';
        n.do_not_track=navigator.doNotTrack===1?'enabled':(navigator.doNotTrack||'unspecified');
        n.canvas=hash(canvasFp());
        try{
            var gl=document.createElement('canvas').getContext('webgl');
            if(!gl) throw new Error('no webgl');
            var ext=gl.getExtension('WEBGL_debug_renderer_info');
            n.webgl=gl.getParameter(ext?ext.UNMASKED_RENDERER_WEBGL:0x1F01)+' / '+gl.getParameter(ext?ext.UNMASKED_VENDOR_WEBGL:0x1F00);
        }catch(e){ n.webgl='blocked'; }
        n.audio=await audioFp();
        n.fonts=await fontFp();
        try{ n.media_devices=await navigator.mediaDevices.enumerateDevices().then(function(ds){return ds.map(function(d){return d.kind+':'+d.label;}).join(' | ');}); }catch(e){ n.media_devices='blocked'; }

        var sorted={};
        Object.keys(n).sort().forEach(function(k){ sorted[k]=n[k]; });
        var id=hash(JSON.stringify(sorted));

        big.textContent='Fingerprint ID: '+id;
        info.textContent='Based on '+Object.keys(n).length+' signals. Private browsers (Tor, Brave shields, container tabs) and blocklists make this ID change or look generic.';
        var h='';
        Object.keys(sorted).forEach(function(k){
            var v=sorted[k];
            if(String(v).length>90) v=String(v).slice(0,90)+'…';
            h+='<div class="fp-cell"><div class="k">'+k.replace(/_/g,' ')+'</div><div class="v">'+esc(v)+'</div></div>';
        });
        cells.innerHTML=h;
    };
    function esc(s){ var d=document.createElement('div'); d.appendChild(document.createTextNode(s==null?'':String(s))); return d.innerHTML; }
})();
</script>
<?php page_footer(); ?>