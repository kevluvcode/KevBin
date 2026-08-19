<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free online barcode generator. Create Code 128, Code 39, EAN-13, UPC-A, ITF-14 and Codabar barcodes entirely in your browser — no data leaves your device.',
    'keywords' => 'barcode generator, code 128, code 39, ean-13, upc-a, itf-14, codabar, barcode maker, barcode creator',
];
page_header('Barcode Generator — Code 128, Code 39, EAN-13, UPC-A, ITF-14, Codabar');
?>
<div class="container" style="max-width:900px;">
    <h1 class="h4 mb-2 reveal in-view">Barcode Generator</h1>
    <p class="text-secondary mb-1 reveal in-view">Generate barcodes in six popular formats — <strong>Code 128</strong>, <strong>Code 39</strong>, <strong>EAN-13</strong>, <strong>UPC-A</strong>, <strong>ITF-14</strong>, and <strong>Codabar</strong> — entirely in your browser. No data is uploaded. Download as PNG or SVG, or copy the SVG markup directly.</p>
    <p class="text-secondary mb-4 reveal in-view">Customise bar width, height, colours and whether the human-readable text is shown. The preview updates live as you type.</p>

    <div class="card reveal">
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Text / Data</label>
                <input id="bc-data" class="form-control" style="font-size:.95rem;" value="KevBin2026" oninput="gen()">
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Barcode Type</label>
                    <select id="bc-type" class="form-select" onchange="gen()">
                        <option value="code128">Code 128</option>
                        <option value="code39">Code 39</option>
                        <option value="ean13">EAN-13</option>
                        <option value="upca">UPC-A</option>
                        <option value="itf14">ITF-14</option>
                        <option value="codabar">Codabar</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Bar Width</label>
                    <input id="bc-width" class="form-control" type="number" min="1" max="4" step="1" value="2" oninput="gen()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Height</label>
                    <input id="bc-height" class="form-control" type="number" min="40" max="300" step="10" value="100" oninput="gen()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Bar Colour</label>
                    <input id="bc-bar" class="form-control form-control-color" value="#000000" oninput="gen()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Background</label>
                    <input id="bc-bg" class="form-control form-control-color" value="#ffffff" oninput="gen()">
                </div>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="bc-text" checked onchange="gen()">
                <label class="form-check-label" for="bc-text">Show human-readable text</label>
            </div>

            <div id="bc-err" class="text-danger small mb-2"></div>

            <div class="text-center p-3" style="background:#0b0b0b;border:1px solid var(--line);border-radius:12px;">
                <canvas id="bc-cv" style="max-width:100%;border-radius:8px;"></canvas>
                <div id="bc-placeholder" class="text-secondary small">Enter data above to generate a barcode</div>
                <div class="mt-3 d-flex flex-wrap gap-2 justify-content-center">
                    <a id="bc-dlpng" class="btn btn-primary btn-sm" href="#" onclick="dlPng(event)">⬇ Download PNG</a>
                    <a id="bc-dlsvg" class="btn btn-outline-light btn-sm" href="#" onclick="dlSvg(event)">⬇ Download SVG</a>
                    <button class="btn btn-outline-light btn-sm" onclick="copySvg()">Copy SVG</button>
                </div>
            </div>
            <p class="text-secondary small mt-3 mb-0" id="bc-info"></p>
        </div>
    </div>
</div>

<script>
(function(){
function $(id){ return document.getElementById(id); }

var C128_CHARS = ' ! "#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_`abcdefghijklmnopqrstuvwxyz{|}~';
var C128B_PATTERNS = [
    '11011001100','11001101100','11001100110','10010011000','10010001100','10001001100','10011001000','10011000100','10001100100','11001001000',
    '11001000100','11000100100','10110011100','10011011100','10011001110','10111001100','10011101100','10011100110','11001110010','11001011100',
    '11001001110','11011100100','11001110100','11101101110','11101001100','11100101100','11100100110','11101100100','11100110100','11100110010',
    '11011011000','11011000110','11000110110','10100011000','10001011000','10001000110','10110001000','10001101000','10001100010','11010001000',
    '11000101000','11000100010','10110111000','10110001110','10001101110','10111011000','10111000110','10001110110','11101110110','11010001110',
    '11000101110','11011101000','11011100010','11011101110','11101011000','11101000110','11100010110','11101101000','11101100010','11100011010',
    '11101111010','11001000010','11110001010','10100110000','10100001100','10010110000','10010000110','10000101100','10000100110','10110010000',
    '10110000100','10011010000','10011000010','10000110100','10000110010','11000010010','11001010000','11110111010','11000010100','10001111010',
    '10100111100','10010111100','10010011110','10111100100','10011110100','10011110010','11110100100','11110010100','11110010010','11011011110',
    '11011110110','11110110110','10101111000','10100011110','10001011110','10111101000','10111100010','11110101000','11110100010','10111011110',
    '10111101110','11101011110','11110101110','11010000100','11010010000','11010011100','11000111010'
];

var C39_CHARS = {
    '0':'nnnwwnwnn','1':'wnnwnnnnw','2':'nnwwnnnnw','3':'wnwwnnnnn','4':'nnnwwnnnw','5':'wnnwwnnnn','6':'nnwwwnnnn','7':'nnnwnnwnw','8':'wnnwnnwnn','9':'nnwwnnwnn',
    'A':'wnnnnwnnw','B':'nnwnnwnnw','C':'wnwnnwnnn','D':'nnnnwwnnw','E':'wnnnwwnnn','F':'nnwnwwnnn','G':'nnnnnwwnw','H':'wnnnnwwnn','I':'nnwnnwwnn','J':'nnnnwwwnn',
    'K':'wnnnnnnww','L':'nnwnnnnww','M':'wnwnnnnwn','N':'nnnnwnnww','O':'wnnnwnnwn','P':'nnwnwnnwn','Q':'nnnnnnwww','R':'wnnnnnwwn','S':'nnwnnnwwn','T':'nnnnwnwwn',
    'U':'wwnnnnnnn','V':'nwwnnnnnn','W':'wwwnnnnnn','X':'nwnnwnnnn','Y':'wwnnwnnnn','Z':'nwwnwnnnn','-':'nwnnnnwnn','*':'nwnnwnwnn','.':'wwnnnnnnn',' ':'nwwnnnnwn',
    '$':'nwnwnwnnn','/':'nwnnnnwnw','+':'nwnnwnwnn','%':'nnnwnwnwn'
};

var EAN_L = ['0001101','0011001','0010011','0111101','0100011','0110001','0101111','0111011','0110111','0001011'];
var EAN_G = ['0100111','0110011','0011011','0100001','0011101','0111001','0000101','0010001','0001001','0010111'];
var EAN_R = ['1110010','1100110','1101100','1000010','1011100','1001110','1010000','1000100','1001000','1110100'];
var EAN_PARITY = [
    [0,0,0,0,0,0],[0,0,1,0,1,1],[0,0,1,1,0,1],[0,0,1,1,1,0],[0,1,0,0,1,1],
    [0,1,1,0,0,1],[0,1,1,1,0,0],[0,1,0,1,0,1],[0,1,0,1,1,0],[0,1,1,0,1,0]
];

var CODABAR_CHARS = {
    '0':'nnnnnwwd','1':'dnnnnnwd','2':'nwdnnnnd','3':'dnnwdnnd','4':'nnnwdwnd','5':'dnnndnwn','6':'nwdndnnd','7':'nnnnwdnd','8':'dnnwdwnd','9':'dnwnnndn',
    '-':'dnndnwnn','$':':nndnwndn','.':'dwndnndn',':':'dndnndwn','/':'dndndnwn','+':'dnwndnnd','A':'dnwnndnd','B':'ndwndndn','C':'ndndnwdn','D':'ndndndwn'
};

var lastSvgMarkup = '';

function e(str){ return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function checksum128(str){
    var sum=104;
    for(var i=0;i<str.length;i++) sum+=C128_CHARS.indexOf(str[i])*(i+1);
    return sum%103;
}

function encode128(str){
    if(!str) return null;
    var bars=[];
    var startB=104;
    bars.push(C128B_PATTERNS[startB]);
    var sum=startB;
    for(var i=0;i<str.length;i++){
        var idx=C128_CHARS.indexOf(str[i]);
        if(idx<0) return null;
        bars.push(C128B_PATTERNS[idx]);
        sum+=idx*(i+1);
    }
    bars.push(C128B_PATTERNS[sum%103]);
    bars.push(C128B_PATTERNS[106]);
    return {bars:bars, text:str};
}

function encode39(str){
    str=str.toUpperCase();
    var valid='0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ-. $/+%';
    for(var i=0;i<str.length;i++) if(valid.indexOf(str[i])<0) return null;
    var input='*'+str+'*';
    var bars=[];
    for(var i=0;i<input.length;i++){
        var pat=C39_CHARS[input[i]];
        if(!pat) return null;
        for(var j=0;j<pat.length;j++) bars.push(pat[j]==='w'?1:0);
        bars.push(0);
    }
    var bitStr='';
    for(var i=0;i<bars.length;i++) bitStr+=(i%2===0?'1':'0').repeat(bars[i]+1);
    return {bars:[bitStr], text:str};
}

function eanCheckDigit(digits){
    var sum=0;
    for(var i=0;i<digits.length;i++) sum+=digits[i]*(i%2===0?1:3);
    return (10-sum%10)%10;
}

function encodeEan13(str){
    str=str.replace(/[^0-9]/g,'');
    if(str.length===12) str+=eanCheckDigit(str);
    if(str.length!==13) return null;
    var d=[];
    for(var i=0;i<13;i++) d.push(parseInt(str[i],10));
    var check=eanCheckDigit(d.slice(0,12));
    if(d[12]!==check) return null;
    var bars=[];
    bars.push('101');
    var par=EAN_PARITY[d[0]];
    for(var i=0;i<6;i++) bars.push(par[i]===0?EAN_L[d[i+1]]:EAN_G[d[i+1]]);
    bars.push('01010');
    for(var i=0;i<6;i++) bars.push(EAN_R[d[i+7]]);
    bars.push('101');
    return {bars:bars, text:str};
}

function encodeUpca(str){
    str=str.replace(/[^0-9]/g,'');
    if(str.length===11) str+=eanCheckDigit(str);
    if(str.length!==12) return null;
    var d=[];
    for(var i=0;i<12;i++) d.push(parseInt(str[i],10));
    var check=eanCheckDigit(d.slice(0,11));
    if(d[11]!==check) return null;
    var bars=[];
    bars.push('101');
    for(var i=0;i<6;i++) bars.push(EAN_L[d[i]]);
    bars.push('01010');
    for(var i=0;i<6;i++) bars.push(EAN_R[d[i+6]]);
    bars.push('101');
    return {bars:bars, text:str};
}

function encodeItf14(str){
    str=str.replace(/[^0-9]/g,'');
    if(str.length===13) str+=eanCheckDigit(str);
    if(str.length!==14) return null;
    var bars=[];
    bars.push('1010');
    for(var i=0;i<14;i+=2){
        var d1=parseInt(str[i],10);
        var d2=parseInt(str[i+1],10);
        var p1=itfWide(d1);
        var p2=itfNarrow(d2);
        bars.push(p1+p2);
    }
    bars.push('11101');
    return {bars:bars, text:str};
}

function itfWide(d){
    var W='11',N='1';
    var m=[[N,N,N,N,N],[N,N,N,N,W],[N,N,N,W,N],[N,N,N,W,W],[N,N,W,N,N],[N,N,W,N,W],[N,N,W,W,N],[N,W,N,N,N],[N,W,N,N,W],[N,W,N,W,N]];
    return m[d].join('');
}

function itfNarrow(d){
    var W='00',N='0';
    var m=[[N,N,N,N,N],[N,N,N,N,W],[N,N,N,W,N],[N,N,N,W,W],[N,N,W,N,N],[N,N,W,N,W],[N,N,W,W,N],[N,W,N,N,N],[N,W,N,N,W],[N,W,N,W,N]];
    return m[d].join('');
}

function encodeCodabar(str){
    str=str.toUpperCase();
    var valid='0123456789-$:/.+ABCD';
    if(str.length<2) return null;
    var startStop='ABCD';
    if(startStop.indexOf(str[0])<0) str='A'+str;
    if(startStop.indexOf(str[str.length-1])<0) str+='B';
    for(var i=0;i<str.length;i++) if(valid.indexOf(str[i])<0) return null;
    var bars=[];
    for(var i=0;i<str.length;i++){
        var pat=CODABAR_CHARS[str[i]];
        if(!pat) return null;
        for(var j=0;j<pat.length;j++){
            if(pat[j]==='n') bars.push(j%2===0?'1':'0');
            else if(pat[j]==='w') bars.push(j%2===0?'11':'00');
            else if(pat[j]==='d') bars.push(j%2===0?'1':'0');
        }
        if(i<str.length-1) bars.push('0');
    }
    return {bars:[bars.join('')], text:str};
}

function parseBars(barsStr){
    var segments=[];
    var isBar=true;
    var count=0;
    for(var i=0;i<barsStr.length;i++){
        count++;
        if(i===barsStr.length-1 || barsStr[i]!==barsStr[i+1]){
            segments.push({isBar:isBar, width:count});
            isBar=!isBar;
            count=0;
        }
    }
    return segments;
}

function renderCanvas(encoded, type){
    var cv=$('bc-cv');
    var ctx=cv.getContext('2d');
    var barW=parseInt($('bc-width').value,10)||2;
    var barH=parseInt($('bc-height').value,10)||100;
    var barColor=$('bc-bar').value;
    var bgColor=$('bc-bg').value;
    var showText=$('bc-text').checked;
    var text=encoded.text;

    var allBars=encoded.bars.join('');
    var segments=parseBars(allBars);
    var totalWidth=0;
    var maxW=barW*7;
    for(var i=0;i<segments.length;i++) totalWidth+=segments[i].width*barW;

    var padX=20;
    var textH=showText?18:0;
    var totalH=padX+barH+textH+padX;

    cv.width=Math.max(totalWidth+padX*2,200);
    cv.height=totalH;

    ctx.fillStyle=bgColor;
    ctx.fillRect(0,0,cv.width,cv.height);

    ctx.fillStyle=barColor;
    var x=padX;
    for(var i=0;i<segments.length;i++){
        if(segments[i].isBar){
            ctx.fillRect(x,padX,segments[i].width*barW,barH);
        }
        x+=segments[i].width*barW;
    }

    if(showText){
        ctx.fillStyle=barColor;
        ctx.font='12px monospace';
        ctx.textAlign='center';
        ctx.textBaseline='top';
        var displayText=text;
        if(type==='codabar'){
            displayText=displayText.replace(/^[ABCD]/,'').replace(/[ABCD]$/,'');
        }
        ctx.fillText(displayText,cv.width/2,padX+barH+4);
    }

    var area=type;
    var labels={code128:'Code 128',code39:'Code 39',ean13:'EAN-13',upca:'UPC-A',itf14:'ITF-14',codabar:'Codabar'};
    $('bc-info').textContent='Type: '+labels[type]+' | Characters: '+text.length;
}

function renderSvg(encoded, type){
    var barW=parseInt($('bc-width').value,10)||2;
    var barH=parseInt($('bc-height').value,10)||100;
    var barColor=$('bc-bar').value;
    var bgColor=$('bc-bg').value;
    var showText=$('bc-text').checked;
    var text=encoded.text;
    var padX=20;
    var textH=showText?18:0;
    var allBars=encoded.bars.join('');
    var segments=parseBars(allBars);
    var totalWidth=0;
    for(var i=0;i<segments.length;i++) totalWidth+=segments[i].width*barW;
    var totalH=padX+barH+textH+padX;
    var w=totalWidth+padX*2;

    var rects='';
    var x=padX;
    for(var i=0;i<segments.length;i++){
        if(segments[i].isBar){
            rects+='<rect x="'+x+'" y="'+padX+'" width="'+(segments[i].width*barW)+'" height="'+barH+'" fill="'+barColor+'"/>';
        }
        x+=segments[i].width*barW;
    }
    var displayText=text;
    if(type==='codabar') displayText=displayText.replace(/^[ABCD]/,'').replace(/[ABCD]$/,'');
    var textEl=showText?'<text x="'+w/2+'" y="'+(padX+barH+14)+'" text-anchor="middle" fill="'+barColor+'" font-family="monospace" font-size="12">'+e(displayText)+'</text>':'';

    var xmld = '<' + '?xml version="1.0" encoding="UTF-8"?' + '>';
    return xmld + '\n<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '+w+' '+totalH+'" width="'+w+'" height="'+totalH+'">\n<rect width="'+w+'" height="'+totalH+'" fill="'+bgColor+'"/>\n'+rects+'\n'+textEl+'\n</svg>';
}

var validators={
    code128:function(v){ if(!v)return'Enter some data.'; for(var i=0;i<v.length;i++){if(C128_CHARS.indexOf(v[i])<0)return 'Unsupported character: "'+v[i]+'". Code 128B supports ASCII 32-127.';} return null; },
    code39:function(v){ v=v.toUpperCase(); if(!v)return'Enter some data.'; var ok='0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ-. $/+%'; for(var i=0;i<v.length;i++){if(ok.indexOf(v[i])<0)return 'Code 39 only supports: A-Z, 0-9, space, -, ., $, /, +, %';} return null; },
    ean13:function(v){ var d=v.replace(/[^0-9]/g,''); if(d.length!==12&&d.length!==13)return 'EAN-13 requires 12 or 13 digits (checksum auto-calculated).'; if(d.length===13){var chk=eanCheckDigit(d.slice(0,12));if(parseInt(d[12],10)!==chk)return 'Invalid check digit. Expected '+chk+'.';} return null; },
    upca:function(v){ var d=v.replace(/[^0-9]/g,''); if(d.length!==11&&d.length!==12)return 'UPC-A requires 11 or 12 digits (checksum auto-calculated).'; if(d.length===12){var chk=eanCheckDigit(d.slice(0,11));if(parseInt(d[11],10)!==chk)return 'Invalid check digit. Expected '+chk+'.';} return null; },
    itf14:function(v){ var d=v.replace(/[^0-9]/g,''); if(d.length!==13&&d.length!==14)return 'ITF-14 requires 13 or 14 digits (checksum auto-calculated).'; if(d.length===14){var chk=eanCheckDigit(d.slice(0,13));if(parseInt(d[13],10)!==chk)return 'Invalid check digit. Expected '+chk+'.';} return null; },
    codabar:function(v){ if(v.length<2)return 'Codabar needs at least 2 characters (start + stop: A, B, C or D).'; var ok='0123456789-$:/.+ABCD'; for(var i=0;i<v.length;i++){if(ok.indexOf(v[i].toUpperCase())<0)return 'Codabar supports: 0-9, -, $, :, /, ., +, and start/stop A-D.';} return null; }
};

window.gen=function(){
    var data=$('bc-data').value;
    var type=$('bc-type').value;
    var errBox=$('bc-err');
    var cv=$('bc-cv');
    var placeholder=$('bc-placeholder');
    var err=validators[type](data);
    if(err){errBox.textContent=err;cv.style.display='none';placeholder.style.display='';$('bc-info').textContent='';lastSvgMarkup='';return;}
    errBox.textContent='';

    var encoded;
    switch(type){
        case 'code128': encoded=encode128(data); break;
        case 'code39': encoded=encode39(data); break;
        case 'ean13': encoded=encodeEan13(data); break;
        case 'upca': encoded=encodeUpca(data); break;
        case 'itf14': encoded=encodeItf14(data); break;
        case 'codabar': encoded=encodeCodabar(data); break;
    }
    if(!encoded){errBox.textContent='Encoding failed.';cv.style.display='none';placeholder.style.display='';lastSvgMarkup='';return;}

    renderCanvas(encoded,type);
    cv.style.display='inline-block';
    placeholder.style.display='none';
    lastSvgMarkup=renderSvg(encoded,type);
};

function dlPng(e){
    e.preventDefault();
    var cv=$('bc-cv');
    if(cv.style.display==='none') return;
    cv.toBlob(function(blob){
        var url=URL.createObjectURL(blob);
        var a=document.createElement('a');
        a.href=url;a.download='barcode.png';
        document.body.appendChild(a);a.click();
        document.body.removeChild(a);URL.revokeObjectURL(url);
    },'image/png');
}

function dlSvg(e){
    e.preventDefault();
    if(!lastSvgMarkup) return;
    var blob=new Blob([lastSvgMarkup],{type:'image/svg+xml'});
    var url=URL.createObjectURL(blob);
    var a=document.createElement('a');
    a.href=url;a.download='barcode.svg';
    document.body.appendChild(a);a.click();
    document.body.removeChild(a);URL.revokeObjectURL(url);
}

window.copySvg=function(){
    if(!lastSvgMarkup){alert('Generate a barcode first.');return;}
    if(navigator.clipboard&&navigator.clipboard.writeText){
        navigator.clipboard.writeText(lastSvgMarkup).then(function(){
            var btn=event.target;var orig=btn.textContent;btn.textContent='Copied!';setTimeout(function(){btn.textContent=orig;},1500);
        });
    } else {
        var ta=document.createElement('textarea');ta.value=lastSvgMarkup;
        document.body.appendChild(ta);ta.select();document.execCommand('copy');
        document.body.removeChild(ta);
        var btn=event.target;var orig=btn.textContent;btn.textContent='Copied!';setTimeout(function(){btn.textContent=orig;},1500);
    }
};

gen();
})();
</script>
<?php page_footer(); ?>
