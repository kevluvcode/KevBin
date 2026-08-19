<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free percentage calculator — X% of Y, what percent of Y is X, and percent change between two values, with explanations.',
    'keywords' => 'percentage calculator, percent of, percent change, find percentage, math tools',
];
page_header('Percentage Calculator');
?>
<style>
.pc-row{display:grid;grid-template-columns:1fr auto 1fr;gap:.5rem;align-items:end;margin-bottom:.6rem;}
.pc-row .op{font-size:1.2rem;color:var(--dim);text-align:center;padding-bottom:.4rem;}
</style>
<div class="container" style="max-width: 760px;">
    <h1 class="h4 mb-2 reveal in-view">Percentage Calculator</h1>
    <p class="text-secondary mb-4 reveal in-view">Three common percent problems, solved instantly with an explanation.</p>

    <div class="card mb-3 reveal in-view"><div class="card-body">
        <div class="d-flex gap-2 mb-2">
            <button class="btn btn-primary btn-sm" onclick="mode('a')">X% of Y</button>
            <button class="btn btn-outline-light btn-sm" onclick="mode('b')">X is what % of Y?</button>
            <button class="btn btn-outline-light btn-sm" onclick="mode('c')">% change (X → Y)</button>
        </div>
        <div class="pc-row">
            <input id="pc-x" class="form-control" type="number" placeholder="X">
            <div class="op" id="pc-op">% of</div>
            <input id="pc-y" class="form-control" type="number" placeholder="Y">
        </div>
        <button class="btn btn-primary btn-sm" onclick="calc()">Calculate</button>
        <div id="pc-result" class="mt-3" style="font-size:1.5rem;font-weight:700;"></div>
        <div id="pc-explain" class="text-secondary small"></div>
    </div></div>
</div>
<script>
(function(){
    var cur='a';
    var x=document.getElementById('pc-x'),y=document.getElementById('pc-y');
    var op=document.getElementById('pc-op');
    window.mode=function(m){
        cur=m;
        op.textContent = m==='a' ? '% of' : (m==='b' ? 'is ? % of' : ' → (change)');
        document.querySelectorAll('.pc-row .op').forEach(function(){}); op.textContent = m==='a'?(cur='a','% of'):(m==='b'?'is what % of?':'change from → to');
        document.getElementById('pc-result').innerHTML=''; document.getElementById('pc-explain').textContent='';
    };
    window.calc=function(){
        var X=parseFloat(x.value), Y=parseFloat(y.value);
        var r=document.getElementById('pc-result'), e=document.getElementById('pc-explain');
        if(X===null||isNaN(X)||isNaN(Y)){ r.textContent='Enter both values.'; return; }
        var html='', exp='';
        if(cur==='a'){ var v=X/100*Y; html=X+'% of '+Y+' = <span style="color:#26d07c">'+fmt(v)+'</span>'; exp='Multiply '+X+' by '+Y+' and divide by 100: ('+X+' × '+Y+') ÷ 100 = '+fmt(v)+'.'; }
        else if(cur==='b'){ if(Y===0){html='Cannot divide by zero.';}else{ var p=X/Y*100; html=X+' is <span style="color:#26d07c">'+fmt(p)+'%</span> of '+Y; exp='Divide '+X+' by '+Y+' and multiply by 100: ('+X+' ÷ '+Y+') × 100 = '+fmt(p)+'%.'; } }
        else { if(X===0){html='Cannot compute change from zero.';}else{ var c=(Y-X)/Math.abs(X)*100; var dir=c>=0?'increase':'decrease'; html=X+' → '+Y+' is a <span style="color:'+(c>=0?'#26d07c':'#e74c3c')+'">'+fmt(c)+'%</span> '+dir; exp='Difference ('+fmt(Y-X)+') ÷ original ('+X+') × 100 = '+fmt(Math.abs(c))+'% '+dir+'.'; } }
        r.innerHTML=html; e.textContent=exp;
    };
    function fmt(n){ return parseFloat(n.toFixed(6)).toLocaleString(undefined,{maximumFractionDigits:6}); }
    mode('a');
})();
</script>
<?php page_footer(); ?>