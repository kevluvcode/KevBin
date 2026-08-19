<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free age calculator — exact age in years, months and days, plus total days, weeks and hours since birth and the next birthday countdown.',
    'keywords' => 'age calculator, date of birth age, age in days, birthday countdown, duration calculator',
];
page_header('Age Calculator');
?>
<div class="container" style="max-width: 760px;">
    <h1 class="h4 mb-2 reveal in-view">Age Calculator</h1>
    <p class="text-secondary mb-4 reveal in-view">Enter a date of birth to get the exact age in years, months and days — plus totals and your next birthday countdown.</p>

    <div class="card mb-3 reveal in-view"><div class="card-body">
        <label class="form-label">Date of Birth</label>
        <input id="ac-dob" class="form-control mb-2" type="date">
        <button class="btn btn-primary btn-sm" onclick="calcAge()">Calculate age</button>
        <div id="ac-big" class="mt-3" style="font-size:1.8rem;font-weight:700;"></div>
        <div id="ac-details" class="mt-2"></div>
        <div class="mt-3" id="ac-bday"></div>
    </div></div>

    <div class="card mb-3 reveal in-view"><div class="card-body">
        <h2 class="h6 mb-3">Age at a date in the past or future</h2>
        <div class="row g-2">
            <div class="col-md-6"><input id="ac-dob2" class="form-control" type="date" placeholder="DOB"></div>
            <div class="col-md-6"><input id="ac-on" class="form-control" type="date" placeholder="On date"></div>
        </div>
        <button class="btn btn-outline-light btn-sm mt-2" onclick="calcOn()">Calculate at date</button>
        <div id="ac-on-result" class="mt-2"></div>
    </div></div>
</div>
<script>
(function(){
    function diff(start,end){
        if(end<start) return null;
        var y=end.getFullYear()-start.getFullYear();
        var m=end.getMonth()-start.getMonth();
        var d=end.getDate()-start.getDate();
        if(d<0){ m--; var prev=new Date(end.getFullYear(),end.getMonth(),0).getDate(); d+=prev; }
        if(m<0){ y--; m+=12; }
        return {y:y,m:m,d:d};
    }
    function row(l,v){ return '<div class="d-flex justify-content-between py-1" style="border-bottom:1px solid rgba(255,255,255,.05)"><span class="text-secondary">'+l+'</span><strong>'+v+'</strong></div>'; }
    window.calcAge=function(){
        var v=document.getElementById('ac-dob').value;
        var out=document.getElementById('ac-big'),det=document.getElementById('ac-details'),bd=document.getElementById('ac-bday');
        if(!v){ out.textContent='Pick a date first.'; det.innerHTML=''; bd.innerHTML=''; return; }
        var dob=new Date(v+'T00:00:00');
        var now=new Date(); now.setHours(0,0,0,0);
        var r=diff(dob,now);
        if(!r){ out.textContent='Birth date is in the future.'; det.innerHTML=''; bd.innerHTML=''; return; }
        out.innerHTML='You are <span style="color:#26d07c">'+r.y+' yrs</span> '+r.m+' mo '+r.d+' days old';
        var ms=now-dob;
        var days=Math.floor(ms/86400000);
        var weeks=Math.floor(days/7);
        var hrs=Math.floor(ms/3600000);
        det.innerHTML = row('Total days',days.toLocaleString()) + row('Total weeks',weeks.toLocaleString()) + row('Total hours',hrs.toLocaleString()) + row('Total minutes',Math.floor(ms/60000).toLocaleString());
        var next=new Date(now.getFullYear(),dob.getMonth(),dob.getDate());
        if(next<=now) next=new Date(now.getFullYear()+1,dob.getMonth(),dob.getDate());
        var left=Math.ceil((next-now)/86400000);
        bd.innerHTML='<span class="text-secondary small">🎂 Next birthday in <strong style="color:#6872f2">'+left+' days</strong> ('+next.toDateString()+')</span>';
    };
    window.calcOn=function(){
        var dob=new Date((document.getElementById('ac-dob2').value||'')+'T00:00:00');
        var on=new Date((document.getElementById('ac-on').value||'')+'T00:00:00');
        var el=document.getElementById('ac-on-result');
        if(isNaN(dob)||isNaN(on)){ el.textContent='Pick both dates.'; return; }
        var r=diff(dob,on);
        el.innerHTML = r===null? "The 'on' date is before the birthday." : 'Age on that date: <strong>'+r.y+'y '+r.m+'m '+r.d+'d</strong>';
    };
})();
</script>
<?php page_footer(); ?>