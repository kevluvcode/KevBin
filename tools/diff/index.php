<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free line-by-line diff checker with added/removed/unchanged highlighting, case-sensitivity and whitespace options.',
    'keywords' => 'diff checker, text compare, line diff, compare files, side by side diff',
];
page_header('Text Diff Checker');
?>
<style>
.df-line{font-family:ui-monospace,Consolas,monospace;font-size:.8rem;white-space:pre-wrap;word-break:break-all;padding:1px 8px;}
.df-add{background:rgba(38,208,124,.14);color:#7ce8b5;border-left:3px solid #26d07c;}
.df-del{background:rgba(231,76,60,.14);color:#ffb3b3;border-left:3px solid #e74c3c;}
.df-eq{color:#cbd5e1;}
.df-num{color:#64748b;display:inline-block;min-width:2.2em;text-align:right;margin-right:8px;user-select:none;}
</style>
<div class="container" style="max-width: 1000px;">
    <h1 class="h4 mb-2 reveal in-view">Text Diff Checker</h1>
    <p class="text-secondary mb-4 reveal in-view">Compare two blocks of text line by line — additions, deletions and unchanged lines highlighted, with a similarity score.</p>

    <div class="row g-3 mb-3">
        <div class="col-md-6 reveal in-view">
            <label class="form-label">Original</label>
            <textarea id="df-a" class="form-control" rows="10" placeholder="Original text..."></textarea>
        </div>
        <div class="col-md-6 reveal in-view">
            <label class="form-label">Changed</label>
            <textarea id="df-b" class="form-control" rows="10" placeholder="Changed text..."></textarea>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" id="df-case">
            <label class="form-check-label small" for="df-case">Ignore case</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" id="df-ws">
            <label class="form-check-label small" for="df-ws">Ignore whitespace</label>
        </div>
        <button class="btn btn-primary btn-sm" onclick="runDiff()">Compare</button>
        <span class="text-secondary small" id="df-score"></span>
    </div>
    <div class="card reveal in-view"><div class="card-body">
        <div id="df-out"><div class="text-secondary small">Paste both texts and hit Compare.</div></div>
    </div></div>
</div>
<script>
(function(){
    var A=document.getElementById('df-a'),B=document.getElementById('df-b');
    window.runDiff=function(){
        var caseOff=document.getElementById('df-case').checked;
        var wsOff=document.getElementById('df-ws').checked;
        function norm(s){ if(wsOff) s=s.replace(/\s+/g,' ').trim(); if(caseOff) s=s.toLowerCase(); return s; }
        var al=A.value.split(/\r?\n/).map(norm);
        var bl=B.value.split(/\r?\n/).map(norm);
        // LCS
        var n=al.length,m=bl.length;
        var dp=[]; for(var i=0;i<=n;i++){ dp.push(new Array(m+1).fill(0)); }
        for(var i=1;i<=n;i++) for(var j=1;j<=m;j++){
            dp[i][j]= al[i-1]===bl[j-1] ? dp[i-1][j-1]+1 : Math.max(dp[i-1][j],dp[i][j-1]);
        }
        var ops=[]; var i=n,j=m;
        while(i>0||j>0){
            if(i>0&&j>0&&al[i-1]===bl[j-1]){ ops.unshift({t:'eq',txt:al[i-1],a:i,b:j}); i--;j--; }
            else if(j>0&&(i===0||dp[i][j-1]>=dp[i-1][j])){ ops.unshift({t:'del',txt:bl[j-1],a:null,b:j}); j--; }
            else { ops.unshift({t:'add',txt:al[i-1],a:i,b:null}); i--; }
        }
        var h='';
        var adds=0,dels=0;
        ops.forEach(function(op){
            if(op.t==='eq'){ h+='<div class="df-line df-eq"><span class="df-num">'+op.a+' '+op.b+'</span>'+esc(op.txt)+'</div>'; }
            else if(op.t==='add'){ adds++; h+='<div class="df-line df-add"><span class="df-num">+'+(op.a||'')+'</span>'+esc(op.txt)+'</div>'; }
            else { dels++; h+='<div class="df-line df-del"><span class="df-num">-'+(op.b||'')+'</span>'+esc(op.txt)+'</div>'; }
        });
        document.getElementById('df-out').innerHTML=h;
        var total=Math.max(n,m)||1;
        var similar=Math.round((1-(adds+dels)/total)*100);
        document.getElementById('df-score').textContent=adds+' added, '+dels+' removed · similarity '+similar+'%';
    };
    function esc(s){ var d=document.createElement('div'); d.appendChild(document.createTextNode(s==null?'':s)); return d.innerHTML; }
})();
</script>
<?php page_footer(); ?>