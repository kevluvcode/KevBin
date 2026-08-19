<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free online photo EXIF stripper — remove GPS coordinates, camera info and all hidden metadata from JPEG, PNG and WebP images, right in your browser. No uploads.',
    'keywords' => 'strip exif, remove exif data, photo metadata remover, exif stripper online, delete gps from photo, privacy photo, wipe metadata',
];
page_header('Photo Metadata Stripper');
?>
<style>
.ps-row{display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;}
#ps-preview{max-width:100%;max-height:320px;border-radius:8px;border:1px solid var(--line);}
</style>
<div class="container" style="max-width: 860px;">
    <h1 class="h4 mb-2 reveal in-view">Photo Metadata Stripper</h1>
    <p class="text-secondary mb-4 reveal in-view">Photos hide invisible data: exact GPS coordinates, camera model, shutter settings, timestamps and sometimes even a thumbnail of what you shot. Strip all of it locally in your browser — nothing is uploaded anywhere.</p>

    <div class="card mb-3 reveal in-view"><div class="card-body">
        <div class="ps-row mb-3">
            <input type="file" id="ps-file" accept="image/jpeg,image/png,image/webp,image/*" class="form-control" style="max-width:340px">
            <select id="ps-format" class="form-control" style="max-width:160px">
                <option value="image/png">PNG (lossless)
                <option value="image/jpeg">JPEG (smaller)
                <option value="image/webp">WebP
            </select>
            <span class="text-secondary small" id="ps-quality-wrap">Quality <input type="range" id="ps-quality" min="40" max="100" value="90" style="vertical-align:middle"></span>
        </div>
        <button class="btn btn-primary" id="ps-go">Strip metadata &amp; download</button>
        <div class="alert alert-warning small mt-3"><strong>How it works:</strong> the image is decoded, re-drawn onto a blank canvas and re-encoded. Everything the original stored — EXIF, XMP, IPTC, GPS, ICC profile, thumbnails — is discarded in the process. For JPEG choose PNG/WebP for lossless quality.</div>
        <div id="ps-result" class="mt-2"></div>
    </div></div>

    <div class="card reveal in-view"><div class="card-body">
        <h2 class="h6 mb-2">What hides inside your photos?</h2>
        <ul class="text-secondary small my-0">
            <li><strong>GPS coordinates</strong> — carbon &quot;leash&quot;: where the shot was taken, down to ~metre accuracy if you share original files.</li>
            <li><strong>Camera info</strong> — make, model, lens, focal length, aperture, ISO, exposure.</li>
            <li><strong>Timestamps</strong> — when the photo was taken (and when your phone thinks it was).</li>
            <li><strong>Thumbnails</strong> — JPEGs can embed a small copy of the previous photo, or the original full-size shot, even if you cropped in an editor.</li>
        </ul>
    </div></div>
</div>
<script>
(function(){
    var file=document.getElementById('ps-file');
    var go=document.getElementById('ps-go');
    var fmt=document.getElementById('ps-format');
    var qw=document.getElementById('ps-quality-wrap');
    fmt.addEventListener('change',function(){ qw.style.display = fmt.value==='image/png' ? 'none':'inline'; });

    go.addEventListener('click', function(){
        var r=document.getElementById('ps-result');
        if(!file.files[0]){ r.innerHTML='<div class="alert alert-error small mb-0">Pick an image first.</div>'; return; }
        var f=file.files[0];
        var url=URL.createObjectURL(f);
        var img=new Image();
        img.onload=function(){
            var canvas=document.createElement('canvas');
            canvas.width=img.naturalWidth; canvas.height=img.naturalHeight;
            var ctx=canvas.getContext('2d');
            var fv=fmt.value;
            if(fv==='image/jpeg'){ ctx.fillStyle='#fff'; ctx.fillRect(0,0,canvas.width,canvas.height); }
            ctx.drawImage(img,0,0);
            var q=parseInt(document.getElementById('ps-quality').value,10)/100;
            canvas.toBlob(function(blob){
                URL.revokeObjectURL(url);
                if(!blob){ r.innerHTML='<div class="alert alert-error small mb-0">Could not re-encode this image.</div>'; return; }
                var a=document.createElement('a');
                var name=f.name.replace(/\.[a-z0-9]+$/i,'');
                var ext={ 'image/png':'.png','image/jpeg':'.jpg','image/webp':'.webp' }[fv];
                a.href=URL.createObjectURL(blob);
                a.download=name+'_clean'+ext;
                a.click();
                setTimeout(function(){ URL.revokeObjectURL(a.href); }, 3000);
                function sz(n){ return n>1048576? (n/1048576).toFixed(2)+' MB' : (n/1024).toFixed(1)+' KB'; }
                r.innerHTML='<div class="alert alert-success small mb-0"><strong>Done.</strong> '+sz(f.size)+' → '+sz(blob.size)+' · EXIF/XMP/GPS/ICC removed · original left untouched.</div>'+
                    '<div class="mt-2"><img id="ps-preview" src="'+canvas.toDataURL('image/webp',0.85)+'" alt="cleaned preview"></div>';
            }, fv, q);
        };
        img.onerror=function(){ URL.revokeObjectURL(url); r.innerHTML='<div class="alert alert-error small mb-0">Could not read that image.</div>'; };
        img.src=url;
    });
})();
</script>
<?php page_footer(); ?>