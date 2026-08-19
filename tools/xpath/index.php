<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free XPath tester — paste XML/HTML and evaluate XPath queries live in your browser with matched-node previews.',
    'keywords' => 'xpath tester, xpath evaluator, xml query, xpath online, html xpath',
];
page_header('XPath Tester');
?>
<style>
textarea{font-family:ui-monospace,Consolas,monospace;}
#xp-out{max-height:340px;overflow:auto;}
.xp-node{border:1px solid var(--line);border-radius:6px;padding:.35rem .5rem;margin-bottom:.3rem;font-family:ui-monospace,Consolas,monospace;font-size:.75rem;white-space:pre-wrap;word-break:break-all;color:#cbd5e1;}
</style>
<div class="container" style="max-width: 960px;">
    <h1 class="h4 mb-2 reveal in-view">XPath Tester</h1>
    <p class="text-secondary mb-4 reveal in-view">Evaluate XPath expressions against XML or HTML entirely in your browser — no data is uploaded.</p>

    <div class="row g-3 mb-3">
        <div class="col-md-7 reveal in-view">
            <label class="form-label">Document (XML or HTML)</label>
            <textarea id="xp-doc" class="form-control" rows="10" placeholder="&lt;root&gt;&lt;item id='1'&gt;Apple&lt;/item&gt;..."></textarea>
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" id="xp-html">
                <label class="form-check-label small" for="xp-html">Parse as HTML (browser will fix malformed tags)</label>
            </div>
        </div>
        <div class="col-md-5 reveal in-view">
            <label class="form-label">XPath expression</label>
            <input id="xp-query" class="form-control" placeholder="//item[@id='1']" value="//item">
            <label class="form-label mt-3">Quick presets</label>
            <div class="d-flex flex-wrap gap-1 mb-2">
                <button class="btn btn-outline-light btn-sm" onclick="preset('//item')">//item</button>
                <button class="btn btn-outline-light btn-sm" onclick="preset('//item[@id]')">//item[@id]</button>
                <button class="btn btn-outline-light btn-sm" onclick="preset('//item[position()>1]')">//item[position()>1]</button>
                <button class="btn btn-outline-light btn-sm" onclick="preset('count(//item)')">count(//item)</button>
                <button class="btn btn-outline-light btn-sm" onclick="preset('//item/text()')">//item/text()</button>
                <button class="btn btn-outline-light btn-sm" onclick="preset('//@id')">//@id</button>
            </div>
            <button class="btn btn-primary w-100" onclick="runXp()">Evaluate</button>
        </div>
    </div>

    <div class="card reveal in-view"><div class="card-body">
        <h2 class="h6 mb-2">Results <span class="text-secondary small" id="xp-count"></span></h2>
        <div id="xp-out"><div class="text-secondary small">Results appear here.</div></div>
    </div></div>
</div>
<script>
(function(){
    window.preset=function(q){ document.getElementById('xp-query').value=q; runXp(); };
    window.runXp=function(){
        var doc=document.getElementById('xp-doc').value;
        var q=document.getElementById('xp-query').value;
        var asHtml=document.getElementById('xp-html').checked;
        var out=document.getElementById('xp-out');
        var cnt=document.getElementById('xp-count');
        if(!doc.trim()){ out.innerHTML='<div class="text-secondary small">Paste a document first.</div>'; cnt.textContent=''; return; }
        if(!q.trim()){ out.innerHTML='<div class="text-secondary small">Enter an XPath expression.</div>'; cnt.textContent=''; return; }
        try{
            var dom = asHtml ? new DOMParser().parseFromString(doc,'text/html') : new DOMParser().parseFromString(doc,'text/xml');
            var r = document.evaluate(q, dom, null, XPathResult.ANY_TYPE, null);
            var nodes=[];
            while(true){
                var n=r.iterateNext();
                if(!n) break;
                nodes.push(n);
                if(nodes.length>500) break;
            }
            if(nodes.length===0){ out.innerHTML='<div class="text-secondary small">No matches (or a non-node value like a number was returned). Try count(...) instead.</div>'; cnt.textContent='('+nodes.length+')'; return; }
            cnt.textContent='— '+nodes.length+' node(s)';
            out.innerHTML=nodes.slice(0,100).map(function(n){
                var s;
                if(n.nodeType===1) s=new XMLSerializer().serializeToString(n);
                else if(n.nodeType===2) s='@'+n.nodeName+' = '+n.nodeValue;
                else if(n.nodeType===3) s='"'+n.nodeValue+'"';
                else if(n.nodeType===9) s=n.documentElement? new XMLSerializer().serializeToString(n.documentElement):'<document>';
                else s=String(n.nodeValue);
                if(s.length>800) s=s.slice(0,800)+'…';
                return '<div class="xp-node">'+esc(s)+'</div>';
            }).join('') + (nodes.length>100? '<div class="text-secondary small mt-2">…and '+(nodes.length-100)+' more.</div>':'');
        }catch(e){
            var xmlErr=dom===undefined?null:dom;
            out.innerHTML='<div class="text-secondary small text-danger">Error: '+esc(e.message)+'</div>';
            cnt.textContent='';
        }
    };
    function esc(s){ var d=document.createElement('div'); d.appendChild(document.createTextNode(s==null?'':s)); return d.innerHTML; }
})();
</script>
<?php page_footer(); ?>