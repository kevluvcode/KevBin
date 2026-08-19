<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free online steganography tool — hide secret messages inside PNG images using LSB encoding, or reveal hidden messages. Optional password encryption with SHA-256. 100% client-side, nothing uploaded.',
    'keywords' => 'steganography, hide message in image, LSB steganography, image steganography, reveal hidden text, PNG steganography',
];
page_header('Steganography — Hide &amp; Reveal Messages in Images');
?>
<style>
    .steg-drop{border:2px dashed var(--line,#333);border-radius:12px;padding:32px 16px;text-align:center;cursor:pointer;transition:all .2s;position:relative;min-height:180px;display:flex;align-items:center;justify-content:center;flex-direction:column;}
    .steg-drop.drag-over{border-color:#5865f2;background:rgba(88,101,242,.08);}
    .steg-drop.has-file{border-style:solid;border-color:#2ea043;}
    .steg-drop input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;}
    .steg-drop .steg-drop-icon{font-size:2.2rem;margin-bottom:6px;opacity:.5;}
    .steg-drop .steg-drop-text{font-size:.85rem;color:var(--bs-secondary,#999);}
    .steg-img-wrap{margin-top:12px;text-align:center;}
    .steg-img-wrap img{max-width:100%;max-height:220px;border-radius:10px;border:1px solid var(--line,#333);}
    .steg-pixel-table{font-family:'JetBrains Mono',monospace;font-size:.7rem;margin-top:10px;overflow-x:auto;}
    .steg-pixel-table table{width:100%;border-collapse:collapse;}
    .steg-pixel-table th,.steg-pixel-table td{padding:3px 6px;border:1px solid var(--line,#333);text-align:center;}
    .steg-pixel-table th{background:#1a1a2e;color:var(--bs-secondary,#999);font-weight:600;}
    .steg-pixel-table td{background:#0d1117;}
    .steg-lsb-bit{color:#f97316;font-weight:700;}
    .steg-capacity{background:#1a1a2e;border:1px solid var(--line,#333);border-radius:8px;padding:10px 14px;margin-top:10px;font-size:.82rem;}
    .steg-capacity .steg-cap-fill{height:6px;border-radius:3px;background:#21262d;margin-top:6px;overflow:hidden;}
    .steg-capacity .steg-cap-bar{height:100%;background:linear-gradient(90deg,#2ea043,#f0e130,#f85149);border-radius:3px;transition:width .3s;}
    .steg-status{padding:8px 12px;border-radius:8px;font-size:.82rem;margin-top:10px;display:none;align-items:center;gap:8px;}
    .steg-status.show{display:flex;}
    .steg-status.steg-ok{background:#0f2e1a;border:1px solid #2ea043;color:#3fb950;}
    .steg-status.steg-err{background:#2e0f0f;border:1px solid #f85149;color:#f85149;}
    .steg-status.steg-warn{background:#2e2200;border:1px solid #d29922;color:#d29922;}
    .steg-compare{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:14px;}
    .steg-compare > div{text-align:center;}
    .steg-compare img{max-width:100%;max-height:160px;border-radius:10px;border:1px solid var(--line,#333);}
    .steg-compare .steg-compare-label{font-size:.75rem;color:var(--bs-secondary,#999);margin-bottom:4px;}
    #steg-revealed{font-family:'JetBrains Mono',monospace;font-size:.85rem;min-height:80px;background:#0d1117;color:#c9d1d9;border:1px solid var(--line,#333);border-radius:8px;padding:10px;white-space:pre-wrap;word-break:break-all;}
    @media(max-width:768px){.steg-compare{grid-template-columns:1fr;}}
</style>

<div class="container" style="max-width:1100px;">
    <h1 class="h4 mb-1 reveal in-view">Steganography — Hide &amp; Reveal</h1>
    <p class="text-secondary mb-4 reveal in-view">Embed secret text into a PNG image using <strong>LSB (Least Significant Bit)</strong> steganography, or extract hidden messages. Optional password-based XOR encryption. Everything runs in your browser — nothing is uploaded.</p>

    <div class="alert alert-secondary reveal in-view">
        <strong>How it works:</strong> Each pixel's Red channel has its least significant bit modified to store one bit of your message. This produces visually identical output. <strong>Only PNG works</strong> — JPEG compression destroys the hidden data. The first 32 bits encode the message length so the receiver knows where to stop.
    </div>

    <div class="row g-4">
        <div class="col-lg-6 reveal in-view">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6 mb-3">🔒 Hide Mode</h2>

                    <div class="steg-drop" id="steg-hide-drop">
                        <input type="file" accept="image/png,image/bmp,image/webp" id="steg-hide-file" onchange="stegLoadHide(this.files)">
                        <div class="steg-drop-icon">🖼</div>
                        <div class="steg-drop-text">Drag &amp; drop an image here or click to browse</div>
                        <div class="form-text">PNG recommended (lossless)</div>
                    </div>

                    <div class="steg-img-wrap" id="steg-hide-preview-wrap" style="display:none;">
                        <img id="steg-hide-preview" alt="Preview">
                    </div>

                    <div id="steg-hide-capacity" class="steg-capacity" style="display:none;">
                        <div>Capacity: <strong id="steg-hide-cap-text">0</strong> bytes available</div>
                        <div>Message: <strong id="steg-hide-msg-size">0</strong> bytes</div>
                        <div class="steg-cap-fill"><div class="steg-cap-bar" id="steg-hide-cap-bar" style="width:0%"></div></div>
                    </div>

                    <div id="steg-hide-pixels" class="steg-pixel-table"></div>

                    <div class="mb-2 mt-3">
                        <label class="form-label small text-secondary">Secret message</label>
                        <textarea id="steg-hide-msg" class="form-control" rows="4" placeholder="Type the secret message to hide..." oninput="stegUpdateCap()" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-secondary">Password (optional — XOR encryption)</label>
                        <input type="password" id="steg-hide-pass" class="form-control" placeholder="Optional encryption password">
                    </div>

                    <button class="btn btn-primary w-100 mb-2" id="steg-hide-btn" onclick="stegHide()" disabled>Hide Message</button>

                    <div id="steg-hide-status" class="steg-status"></div>

                    <div id="steg-compare-wrap" style="display:none;">
                        <div class="steg-compare">
                            <div><div class="steg-compare-label">Original</div><img id="steg-compare-orig" alt="Original"></div>
                            <div><div class="steg-compare-label">Stego (with hidden message)</div><img id="steg-compare-stego" alt="Stego"></div>
                        </div>
                    </div>

                    <div class="mt-2">
                        <a id="steg-hide-dl" class="btn btn-outline-light btn-sm" download="stego.png" style="pointer-events:none;opacity:.5;">Download Stego PNG</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 reveal in-view">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6 mb-3">🔓 Reveal Mode</h2>

                    <div class="steg-drop" id="steg-reveal-drop">
                        <input type="file" accept="image/png,image/bmp,image/webp" id="steg-reveal-file" onchange="stegLoadReveal(this.files)">
                        <div class="steg-drop-icon">🖼</div>
                        <div class="steg-drop-text">Drag &amp; drop a stego image here or click to browse</div>
                        <div class="form-text">Must be a PNG with hidden data</div>
                    </div>

                    <div class="steg-img-wrap" id="steg-reveal-preview-wrap" style="display:none;">
                        <img id="steg-reveal-preview" alt="Stego Preview">
                    </div>

                    <div id="steg-reveal-pixels" class="steg-pixel-table"></div>

                    <div class="mb-3 mt-3">
                        <label class="form-label small text-secondary">Password (if encrypted)</label>
                        <input type="password" id="steg-reveal-pass" class="form-control" placeholder="Enter decryption password if used">
                    </div>

                    <button class="btn btn-primary w-100 mb-2" id="steg-reveal-btn" onclick="stegReveal()" disabled>Reveal Message</button>

                    <div id="steg-reveal-status" class="steg-status"></div>

                    <label class="form-label small text-secondary mt-2 mb-1">Revealed message</label>
                    <div id="steg-revealed"></div>
                    <button class="btn btn-outline-light btn-sm mt-2" onclick="stegCopyRevealed()">Copy Revealed Message</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function steg$(id){return document.getElementById(id);}

var stegHideImg=null,stegRevealImg=null,stegStegoBlob=null;

function stegSetupDrop(dropId,inputId,loadFn){
    var drop=steg$(dropId);
    ['dragenter','dragover'].forEach(function(e){
        drop.addEventListener(e,function(ev){ev.preventDefault();ev.stopPropagation();drop.classList.add('drag-over');});
    });
    ['dragleave','drop'].forEach(function(e){
        drop.addEventListener(e,function(ev){ev.preventDefault();ev.stopPropagation();drop.classList.remove('drag-over');});
    });
    drop.addEventListener('drop',function(ev){
        var files=ev.dataTransfer.files;
        if(files.length)loadFn(files);
    });
}
stegSetupDrop('steg-hide-drop','steg-hide-file',stegLoadHide);
stegSetupDrop('steg-reveal-drop','steg-reveal-file',stegLoadReveal);

function stegLoadHide(files){
    if(!files||!files.length)return;
    var file=files[0];
    var reader=new FileReader();
    reader.onload=function(ev){
        var img=new Image();
        img.onload=function(){
            stegHideImg=img;
            steg$('steg-hide-preview').src=img.src;
            steg$('steg-hide-preview-wrap').style.display='';
            steg$('steg-hide-drop').classList.add('has-file');
            steg$('steg-hide-btn').disabled=false;
            stegShowPixels('steg-hide-pixels',img);
            stegUpdateCap();
        };
        img.src=ev.target.result;
    };
    reader.readAsDataURL(file);
}

function stegLoadReveal(files){
    if(!files||!files.length)return;
    var file=files[0];
    var reader=new FileReader();
    reader.onload=function(ev){
        var img=new Image();
        img.onload=function(){
            stegRevealImg=img;
            steg$('steg-reveal-preview').src=img.src;
            steg$('steg-reveal-preview-wrap').style.display='';
            steg$('steg-reveal-drop').classList.add('has-file');
            steg$('steg-reveal-btn').disabled=false;
            steg$('steg-revealed').textContent='';
            stegShowPixels('steg-reveal-pixels',img);
        };
        img.src=ev.target.result;
    };
    reader.readAsDataURL(file);
}

function stegShowPixels(containerId,img){
    var cv=document.createElement('canvas');
    var maxW=Math.min(img.width,8);
    var maxH=Math.min(img.height,5);
    cv.width=maxW;cv.height=maxH;
    var ctx=cv.getContext('2d');
    ctx.drawImage(img,0,0,maxW,maxH);
    var data=ctx.getImageData(0,0,maxW,maxH).data;
    var html='<div style="margin-top:10px;font-size:.72rem;color:var(--bs-secondary,#999);">First '+maxW+'x'+maxH+' pixels (R channel LSB highlighted):</div><table><tr><th>Pixel</th>';
    for(var x=0;x<maxW;x++){
        html+='<th>'+x+'</th>';
    }
    html+='</tr><tr><td style="font-weight:600;">R</td>';
    for(var i=0;i<maxW;i++){
        var r=data[i*4];
        var lsb=r&1;
        html+='<td class="'+(lsb?'steg-lsb-bit':'')+'">'+r+(lsb?'<small>₁</small>':'<small>₀</small>')+'</td>';
    }
    html+='</tr><tr><td style="font-weight:600;">G</td>';
    for(var i=0;i<maxW;i++){
        html+='<td>'+data[i*4+1]+'</td>';
    }
    html+='</tr><tr><td style="font-weight:600;">B</td>';
    for(var i=0;i<maxW;i++){
        html+='<td>'+data[i*4+2]+'</td>';
    }
    html+='</tr></table>';
    steg$(containerId).innerHTML=html;
}

function stegGetMaxBytes(img){
    return Math.floor(img.width*img.height/8)-4;
}

function stegUpdateCap(){
    if(!stegHideImg)return;
    var maxBytes=stegGetMaxBytes(stegHideImg);
    var msg=steg$('steg-hide-msg').value;
    var msgBytes=new TextEncoder().encode(msg).length;
    steg$('steg-hide-cap-text').textContent=maxBytes.toLocaleString();
    steg$('steg-hide-msg-size').textContent=msgBytes.toLocaleString();
    steg$('steg-hide-capacity').style.display='';
    var pct=Math.min(100,msgBytes/maxBytes*100);
    steg$('steg-hide-cap-bar').style.width=pct+'%';
    if(pct>90){
        stegShowStatus('steg-hide-status','steg-warn','Message is very close to capacity limit ('+pct.toFixed(1)+'%)');
    }else if(pct>100){
        stegShowStatus('steg-hide-status','steg-err','Message too large for this image! Max '+maxBytes+' bytes.');
    }else{
        stegHideStatus('steg-hide-status');
    }
}

function stegShowStatus(id,cls,msg){
    var el=steg$(id);
    el.className='steg-status show steg-'+cls;
    var icons={ok:'✅',err:'❌',warn:'⚠️'};
    el.innerHTML='<span>'+(icons[cls]||'')+'</span><span>'+msg+'</span>';
}

function stegHideStatus(id){
    steg$(id).className='steg-status';
    steg$(id).innerHTML='';
}

function stegDeriveKey(password){
    var te=new TextEncoder();
    var hashBuf;
    return crypto.subtle.digest('SHA-256',te.encode(password)).then(function(buf){
        hashBuf=new Uint8Array(buf);
        return hashBuf;
    });
}

function stegXorBytes(data,key){
    var out=new Uint8Array(data.length);
    for(var i=0;i<data.length;i++){
        out[i]=data[i]^key[i%key.length];
    }
    return out;
}

function stegHide(){
    if(!stegHideImg)return;
    var msg=steg$('steg-hide-msg').value;
    if(!msg){stegShowStatus('steg-hide-status','steg-err','Please enter a message to hide.');return;}
    var pass=steg$('steg-hide-pass').value;
    var msgBytes=new TextEncoder().encode(msg);
    var maxBytes=stegGetMaxBytes(stegHideImg);
    if(msgBytes.length>maxBytes){
        stegShowStatus('steg-hide-status','steg-err','Message too large! '+msgBytes.length+' bytes but max is '+maxBytes+'.');
        return;
    }

    var prepareBytes=function(msgB,passHash){
        if(passHash){
            return Promise.resolve(stegXorBytes(msgB,passHash));
        }
        return Promise.resolve(msgB);
    };

    var p=pass?stegDeriveKey(pass):Promise.resolve(null);
    p.then(function(keyHash){
        return prepareBytes(msgBytes,keyHash);
    }).then(function(processedBytes){
        var cv=document.createElement('canvas');
        cv.width=stegHideImg.width;
        cv.height=stegHideImg.height;
        var ctx=cv.getContext('2d');
        ctx.drawImage(stegHideImg,0,0);
        var imgData=ctx.getImageData(0,0,cv.width,cv.height);
        var pixels=imgData.data;

        var totalBits=32+processedBytes.length*8;
        if(totalBits>pixels.length/4){
            stegShowStatus('steg-hide-status','steg-err','Image too small for this message.');
            return;
        }

        var lenBuf=new ArrayBuffer(4);
        var lenView=new DataView(lenBuf);
        lenView.setUint32(0,processedBytes.length,false);

        var allBits=[];
        var lb=new Uint8Array(lenBuf);
        for(var b=0;b<4;b++){
            for(var i=7;i>=0;i--){
                allBits.push((lb[b]>>i)&1);
            }
        }
        for(var b=0;b<processedBytes.length;b++){
            for(var i=7;i>=0;i--){
                allBits.push((processedBytes[b]>>i)&1);
            }
        }

        for(var i=0;i<allBits.length;i++){
            var pixIdx=i;
            var rIdx=pixIdx*4;
            pixels[rIdx]=(pixels[rIdx]&0xFE)|allBits[i];
        }

        ctx.putImageData(imgData,0,0);
        cv.toBlob(function(blob){
            stegStegoBlob=blob;
            var url=URL.createObjectURL(blob);
            steg$('steg-compare-orig').src=stegHideImg.src;
            steg$('steg-compare-stego').src=url;
            steg$('steg-compare-wrap').style.display='';
            var dl=steg$('steg-hide-dl');
            dl.href=url;
            dl.style.pointerEvents='auto';
            dl.style.opacity='1';
            stegShowStatus('steg-hide-status','steg-ok','Message hidden successfully! '+processedBytes.length+' bytes embedded into '+cv.width+'x'+cv.height+' image. Download the PNG below.');
        },'image/png');
    });
}

function stegReveal(){
    if(!stegRevealImg)return;
    var pass=steg$('steg-reveal-pass').value;

    var cv=document.createElement('canvas');
    cv.width=stegRevealImg.width;
    cv.height=stegRevealImg.height;
    var ctx=cv.getContext('2d');
    ctx.drawImage(stegRevealImg,0,0);
    var imgData=ctx.getImageData(0,0,cv.width,cv.height);
    var pixels=imgData.data;

    var totalPixels=Math.floor(pixels.length/4);
    if(totalPixels<32){
        stegShowStatus('steg-reveal-status','steg-err','Image is too small to contain a steganographic message.');
        return;
    }

    var lenBits=[];
    for(var i=0;i<32;i++){
        lenBits.push(pixels[i*4]&1);
    }
    var msgLen=0;
    for(var i=0;i<32;i++){
        msgLen=(msgLen<<1)|lenBits[i];
    }

    if(msgLen<=0||msgLen>totalPixels-32){
        stegShowStatus('steg-reveal-status','steg-err','No hidden message found (invalid length: '+msgLen+' bytes). The image may not contain steganographic data.');
        steg$('steg-revealed').textContent='';
        return;
    }

    var msgBytes=new Uint8Array(msgLen);
    for(var b=0;b<msgLen;b++){
        var val=0;
        for(var bit=7;bit>=0;bit--){
            var pixIdx=32+b*8+(7-bit);
            val=(val<<1)|(pixels[pixIdx*4]&1);
        }
        msgBytes[b]=val;
    }

    var decodeBytes=function(bytes){
        if(pass){
            return stegDeriveKey(pass).then(function(keyHash){
                return stegXorBytes(bytes,keyHash);
            });
        }
        return Promise.resolve(bytes);
    };

    decodeBytes(msgBytes).then(function(decoded){
        var text;
        try{
            text=new TextDecoder('utf-8',{fatal:true}).decode(decoded);
        }catch(e){
            text=new TextDecoder('utf-8').decode(decoded);
            stegShowStatus('steg-reveal-status','steg-warn','Decoded with replacement characters — password may be incorrect or data is corrupted.');
            steg$('steg-revealed').textContent=text;
            return;
        }
        stegShowStatus('steg-reveal-status','steg-ok','Message revealed! '+msgLen+' bytes decoded successfully.');
        steg$('steg-revealed').textContent=text;
    });
}

function stegCopyRevealed(){
    var text=steg$('steg-revealed').textContent;
    if(!text)return;
    if(navigator.clipboard&&navigator.clipboard.writeText){
        navigator.clipboard.writeText(text).then(function(){
            stegShowStatus('steg-reveal-status','steg-ok','Copied to clipboard!');
        });
    }else{
        var ta=document.createElement('textarea');
        ta.value=text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        stegShowStatus('steg-reveal-status','steg-ok','Copied to clipboard!');
    }
}
</script>
<?php page_footer(); ?>
